<template>
    <SahodayaAdminLayout title="Custom domains" :sahodaya="sahodaya" :publicUrl="publicUrl" :show-header-title="false">
        <PageHeader title="Custom domains" eyebrow="Website"
                    description="Point your own domain at this Sahodaya website. Subdomain hosting continues to work alongside custom domains.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/site-builder`" class="btn-secondary text-sm">Site builder</Link>
            </template>
        </PageHeader>

        <div class="card mb-6 space-y-2 text-sm text-slate-600 max-w-3xl">
            <p><strong>Current public URL:</strong> {{ publicUrl || 'Not configured' }}</p>
            <p v-if="currentSubdomain">Subdomain: <span class="font-mono">{{ currentSubdomain }}.{{ baseDomain }}</span></p>
            <p>After adding a domain, create a DNS TXT record <span class="font-mono text-xs">sahodaya-verify=…</span> then click Verify.</p>
        </div>

        <form @submit.prevent="addDomain" class="card mb-6 flex flex-wrap gap-3 items-end max-w-3xl">
            <FormField label="Custom domain" classExtra="flex-1 min-w-[14rem]">
                <input v-model="form.domain" class="field" placeholder="www.yoursahodaya.org" required>
            </FormField>
            <button type="submit" class="btn-primary" :disabled="form.processing">Add domain</button>
        </form>

        <div class="card card--flush overflow-hidden max-w-3xl">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Verified</th>
                        <th>SSL</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in domains" :key="d.id">
                        <td class="font-mono text-sm">{{ d.domain }}</td>
                        <td>
                            <span v-if="d.verified_at" class="text-emerald-700 text-xs font-semibold">Verified</span>
                            <span v-else class="text-amber-700 text-xs">Pending — TXT: {{ d.txt_record }}</span>
                        </td>
                        <td class="text-xs">{{ d.ssl_status || '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <button v-if="!d.verified_at" type="button" class="btn-ghost text-xs text-indigo-600 mr-2" @click="verify(d)">Verify</button>
                            <button type="button" class="btn-ghost text-xs text-red-600" @click="remove(d)">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="!domains.length">
                        <td colspan="4" class="p-8 text-center text-slate-400">No custom domains yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    domains: { type: Array, default: () => [] },
    currentDomain: String,
    currentSubdomain: String,
    baseDomain: String,
    sites: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/website/domains`;
const form = useForm({ domain: '' });

function addDomain() {
    form.post(base, { preserveScroll: true, onSuccess: () => form.reset() });
}
function verify(d) {
    router.post(`${base}/${d.id}/verify`, {}, { preserveScroll: true });
}
function remove(d) {
    if (!confirm(`Remove ${d.domain}?`)) return;
    router.delete(`${base}/${d.id}`, { preserveScroll: true });
}
</script>
