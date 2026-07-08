<template>
    <SahodayaAdminLayout title="Storage migration" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Migrate uploads to S3" eyebrow="Settings"
                    description="Copy this Sahodaya's legacy local files (photos, proofs, documents) to AWS S3." />

        <div class="grid sm:grid-cols-3 gap-4 mb-6">
            <div class="card !p-4">
                <p class="text-xs uppercase text-slate-400">S3 configured</p>
                <p class="text-lg font-bold" :class="status.s3_configured ? 'text-green-700' : 'text-red-700'">
                    {{ status.s3_configured ? 'Yes' : 'No' }}
                </p>
            </div>
            <div class="card !p-4">
                <p class="text-xs uppercase text-slate-400">Upload disk</p>
                <p class="text-lg font-bold text-[#0f3d7a]">{{ status.upload_disk }}</p>
            </div>
            <div class="card !p-4">
                <p class="text-xs uppercase text-slate-400">Pending files</p>
                <p class="text-lg font-bold text-amber-700">{{ scan.totals?.pending ?? 0 }}</p>
            </div>
        </div>

        <div v-if="!status.s3_configured" class="notice-banner notice-banner--warning mb-6">
            Ask your platform admin to configure AWS S3 (<code>UPLOAD_DISK=s3</code>) before migrating.
        </div>

        <form class="card !p-5 mb-6 space-y-4" @submit.prevent="submit">
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.include_filesystem" type="checkbox" class="rounded">
                Include orphan files on local disks
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.delete_local" type="checkbox" class="rounded">
                Delete local copy after upload (use only after verifying S3 access)
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.sync" type="checkbox" class="rounded">
                Run now (sync) — otherwise queued in background
            </label>
            <div class="flex gap-2">
                <button type="button" class="btn-secondary" @click="refreshScan">Refresh scan</button>
                <button type="submit" class="btn-primary" :disabled="form.processing || !status.s3_configured">
                    Migrate to S3
                </button>
            </div>
        </form>

        <div v-if="jobStatus" class="card !p-4 mb-6 text-sm">
            <p class="font-semibold capitalize">Migration: {{ jobStatus.status }}</p>
            <p v-if="jobStatus.result" class="mt-1">
                Migrated {{ jobStatus.result.migrated }} · Skipped {{ jobStatus.result.skipped }}
            </p>
        </div>

        <div class="card overflow-x-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr><th>Source</th><th>Pending</th><th>On S3</th><th>Missing</th></tr>
                </thead>
                <tbody>
                    <tr v-for="row in scan.sources ?? []" :key="row.label">
                        <td>{{ row.label }}</td>
                        <td class="text-amber-700 font-semibold">{{ row.pending }}</td>
                        <td class="text-green-700">{{ row.on_s3 }}</td>
                        <td>{{ row.missing }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    status: Object,
    scan: Object,
    lastJobKey: String,
});

const page = usePage();
const jobStatus = ref(null);
let pollTimer = null;

const form = useForm({
    delete_local: false,
    include_filesystem: false,
    sync: false,
});

const base = `/sahodaya-admin/${props.sahodaya.id}/settings/storage-migration`;

function refreshScan() {
    fetch(`${base}/scan`, { headers: { Accept: 'application/json' } })
        .then(r => r.json())
        .then(() => window.location.reload());
}

function submit() {
    form.post(`${base}/migrate`, {
        onSuccess: () => startPolling(page.props.flash?.storage_migration_key ?? props.lastJobKey),
    });
}

function startPolling(key) {
    if (!key) return;
    pollTimer = setInterval(async () => {
        const res = await fetch(`${base}/progress?key=${encodeURIComponent(key)}`, { headers: { Accept: 'application/json' } });
        jobStatus.value = await res.json();
        if (['completed', 'failed'].includes(jobStatus.value?.status)) {
            clearInterval(pollTimer);
        }
    }, 3000);
}

onMounted(() => { if (props.lastJobKey) startPolling(props.lastJobKey); });
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer); });
</script>
