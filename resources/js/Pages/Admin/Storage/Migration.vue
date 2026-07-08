<template>
    <AdminLayout title="Storage migration">
        <PageHeader title="Migrate uploads to S3" eyebrow="Platform"
                    description="Copy legacy files from local/shared disks to AWS S3 and update storage metadata." />

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
                <p class="text-xs uppercase text-slate-400">Pending (scan)</p>
                <p class="text-lg font-bold text-amber-700">{{ scan.totals?.pending ?? 0 }}</p>
            </div>
        </div>

        <div v-if="!status.s3_configured" class="notice-banner notice-banner--warning mb-6">
            Configure AWS credentials and set <code>UPLOAD_DISK=s3</code> in <code>.env</code> before migrating.
        </div>

        <form class="card !p-5 mb-6 space-y-4" @submit.prevent="submit">
            <FormField label="Sahodaya scope">
                <select v-model="form.tenant" class="field">
                    <option value="">All Sahodayas</option>
                    <option v-for="s in sahodayas" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </FormField>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.include_filesystem" type="checkbox" class="rounded">
                Also scan orphan files on local disks (first {{ batchSize }} per run)
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.delete_local" type="checkbox" class="rounded">
                Delete local copy after successful upload
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.sync" type="checkbox" class="rounded">
                Run synchronously (small tenants only — use queue for large migrations)
            </label>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-secondary" :disabled="scanning" @click="refreshScan">
                    {{ scanning ? 'Scanning…' : 'Refresh scan' }}
                </button>
                <button type="submit" class="btn-primary" :disabled="form.processing || !status.s3_configured">
                    {{ form.processing ? 'Starting…' : 'Migrate to S3' }}
                </button>
            </div>
        </form>

        <div v-if="jobStatus" class="card !p-4 mb-6 text-sm">
            <p class="font-semibold capitalize">Job: {{ jobStatus.status }}</p>
            <p v-if="jobStatus.result" class="mt-1 text-slate-600">
                Migrated {{ jobStatus.result.migrated }} · Skipped {{ jobStatus.result.skipped }} ·
                Failed {{ jobStatus.result.failed }}
            </p>
            <p v-if="jobStatus.error" class="mt-1 text-red-600">{{ jobStatus.error }}</p>
        </div>

        <div class="card overflow-x-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Records</th>
                        <th>Pending</th>
                        <th>On S3</th>
                        <th>Missing</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in scanSources" :key="row.label">
                        <td>{{ row.label }}</td>
                        <td>{{ row.records }}</td>
                        <td class="text-amber-700 font-semibold">{{ row.pending }}</td>
                        <td class="text-green-700">{{ row.on_s3 }}</td>
                        <td class="text-slate-500">{{ row.missing }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-xs text-slate-500 mt-4">
            CLI: <code>php artisan erp:migrate-uploads-to-s3 --scan</code> ·
            <code>php artisan erp:migrate-uploads-to-s3 --tenant=ID</code>
        </p>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FormField from '@/Components/ui/FormField.vue';

const props = defineProps({
    status: Object,
    scan: Object,
    sahodayas: Array,
    lastJobKey: String,
});

const page = usePage();
const batchSize = 200;
const scanning = ref(false);
const scanData = ref(props.scan);
const jobStatus = ref(null);
let pollTimer = null;

const scanSources = computed(() => scanData.value?.sources ?? []);

const form = useForm({
    tenant: '',
    delete_local: false,
    include_filesystem: false,
    sync: false,
});

function refreshScan() {
    scanning.value = true;
    const params = form.tenant ? `?tenant=${form.tenant}` : '';
    fetch(`/admin/storage-migration/scan${params}`, { headers: { Accept: 'application/json' } })
        .then(r => r.json())
        .then(data => { scanData.value = data; })
        .finally(() => { scanning.value = false; });
}

function submit() {
    form.post('/admin/storage-migration/migrate', {
        onSuccess: () => startPolling(page.props.flash?.storage_migration_key ?? props.lastJobKey),
    });
}

function startPolling(key) {
    if (!key) return;
    pollTimer = setInterval(async () => {
        const res = await fetch(`/admin/storage-migration/progress?key=${encodeURIComponent(key)}`, {
            headers: { Accept: 'application/json' },
        });
        jobStatus.value = await res.json();
        if (['completed', 'failed'].includes(jobStatus.value?.status)) {
            clearInterval(pollTimer);
            refreshScan();
        }
    }, 3000);
}

onMounted(() => {
    const key = props.lastJobKey;
    if (key) startPolling(key);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>
