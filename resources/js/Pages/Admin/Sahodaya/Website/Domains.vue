<template>
    <SahodayaAdminLayout title="Custom Domains" :sahodaya="sahodaya" :publicUrl="publicUrl" :show-header-title="false">
        <div class="max-w-4xl mx-auto space-y-6">
            <PageHeader title="Custom Domains & DNS" eyebrow="Website Settings"
                        description="Point and verify custom domain names for this Sahodaya cluster website. Subdomain hosting remains active automatically.">
                <template #actions>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/site-builder`" class="btn-secondary text-xs">
                        Site Builder →
                    </Link>
                </template>
            </PageHeader>

            <!-- Superadmin lockdown / info banner -->
            <div v-if="!isSuperAdmin" class="bg-indigo-950 text-white rounded-2xl p-5 border border-indigo-900 shadow-xs flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-indigo-800/80 flex items-center justify-center text-xl shrink-0">
                    🔒
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-extrabold text-sm text-white">Domain Provisioning is Super Admin Controlled</h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-400 text-indigo-950">Managed Centrally</span>
                    </div>
                    <p class="text-xs text-indigo-200 mt-1 leading-relaxed">
                        Custom domain mapping, DNS verification tokens, and SSL certificate provisioning are handled exclusively by the Platform Super Admin. The records below are shown for your DNS reference.
                    </p>
                </div>
            </div>

            <div v-else class="bg-gradient-to-r from-purple-900 to-indigo-950 text-white rounded-2xl p-4 border border-purple-800 shadow-xs flex items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-xs font-bold uppercase tracking-wider text-purple-200">Super Admin Access</span>
                    <span class="text-xs text-slate-300">• You have full authority to add, verify, and detach domains.</span>
                </div>
            </div>

            <!-- Current Domain Status Card -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)] space-y-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Current Host & Network Routing</h3>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 space-y-1">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Live Public URL</p>
                        <p v-if="publicUrl" class="text-sm font-mono font-bold text-indigo-600 truncate">
                            <a :href="publicUrl" target="_blank" rel="noopener" class="hover:underline">{{ publicUrl }} ↗</a>
                        </p>
                        <p v-else class="text-xs text-slate-400 italic">Not configured</p>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 space-y-1">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Platform Subdomain</p>
                        <p v-if="currentSubdomain" class="text-sm font-mono font-bold text-slate-800 truncate">
                            {{ currentSubdomain }}.{{ baseDomain }}
                        </p>
                        <p v-else class="text-xs text-slate-400 italic">Not configured</p>
                    </div>
                </div>
            </div>

            <!-- Add Domain Form (Super Admin Only) -->
            <div v-if="isSuperAdmin" class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)] space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Attach New Custom Domain</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Enter the fully qualified domain name (e.g. www.keralasahodaya.org).</p>
                </div>

                <form @submit.prevent="addDomain" class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Domain Name</label>
                        <input v-model="form.domain" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-medium outline-none focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition"
                               placeholder="e.g. www.yoursahodaya.org" required>
                    </div>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition shadow-xs disabled:opacity-40 shrink-0"
                            :disabled="form.processing">
                        {{ form.processing ? 'Registering…' : '+ Attach Domain' }}
                    </button>
                </form>
            </div>

            <!-- Domains Table -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)] overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Configured Custom Domains</h3>
                    <span class="text-xs font-semibold text-slate-500">{{ domains.length }} domain{{ domains.length === 1 ? '' : 's' }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs min-w-[650px]">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-500 text-[11px] font-bold uppercase tracking-wider border-b border-slate-200/80">
                                <th class="px-5 py-3.5">Domain Name</th>
                                <th class="px-4 py-3.5">DNS Verification Status</th>
                                <th class="px-4 py-3.5">SSL Status</th>
                                <th v-if="isSuperAdmin" class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="d in domains" :key="d.id" class="hover:bg-slate-50/50">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-slate-900">{{ d.domain }}</span>
                                        <span v-if="d.is_primary" class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                            Primary
                                        </span>
                                    </div>
                                </td>

                                <td class="px-4 py-4">
                                    <div v-if="d.verified_at" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span>✓</span>
                                        <span>Verified DNS</span>
                                    </div>
                                    <div v-else class="space-y-1">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                            <span>⏳</span>
                                            <span>Pending DNS Verification</span>
                                        </span>
                                        <p class="text-[10px] text-slate-500 font-mono">
                                            TXT Record: <code class="bg-slate-100 px-1 py-0.5 rounded text-slate-800">{{ d.txt_record }}</code>
                                        </p>
                                    </div>
                                </td>

                                <td class="px-4 py-4">
                                    <span v-if="d.ssl_status === 'active'" class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700">
                                        🔒 Active SSL
                                    </span>
                                    <span v-else class="text-xs text-slate-400">
                                        {{ d.ssl_status || 'Pending Verification' }}
                                    </span>
                                </td>

                                <td v-if="isSuperAdmin" class="px-5 py-4 text-right whitespace-nowrap space-x-2">
                                    <button v-if="!d.verified_at" type="button" @click="verify(d)"
                                            class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-bold text-xs hover:bg-indigo-100 transition">
                                        Verify TXT
                                    </button>
                                    <button type="button" @click="remove(d)"
                                            class="px-2.5 py-1 rounded-lg border border-rose-200 text-rose-700 font-bold text-xs hover:bg-rose-50 transition">
                                        Remove
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!domains.length">
                                <td :colspan="isSuperAdmin ? 4 : 3" class="px-6 py-12 text-center text-slate-400">
                                    No custom domains attached to this Sahodaya cluster.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- DNS Guide Card -->
            <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200/80 text-xs text-slate-600 space-y-3">
                <h4 class="font-bold text-slate-900 uppercase tracking-wider text-[11px]">DNS Configuration Guide</h4>
                <ol class="list-decimal pl-4 space-y-1.5 leading-relaxed text-slate-600">
                    <li>Add a <strong>CNAME record</strong> pointing your custom domain or subdomain to <code class="font-mono text-slate-800 bg-white px-1.5 py-0.5 rounded border border-slate-200">{{ baseDomain }}</code>.</li>
                    <li>Add a <strong>TXT record</strong> on the root or subdomain with the exact value <code class="font-mono text-slate-800 bg-white px-1.5 py-0.5 rounded border border-slate-200">sahodaya-verify=…</code> shown in the table above.</li>
                    <li>Once DNS propagation is complete (typically 1–15 minutes), click <strong>Verify TXT</strong> to activate the domain and provision SSL.</li>
                </ol>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    domains: { type: Array, default: () => [] },
    currentDomain: String,
    currentSubdomain: String,
    baseDomain: String,
    sites: { type: Array, default: () => [] },
    isSuperAdmin: { type: Boolean, default: false },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/website/domains`;
const form = useForm({ domain: '' });
const { confirm } = useConfirm();

function addDomain() {
    form.post(base, { preserveScroll: true, onSuccess: () => form.reset() });
}

function verify(d) {
    router.post(`${base}/${d.id}/verify`, {}, { preserveScroll: true });
}

async function remove(d) {
    if (!(await confirm({ message: `Permanently detach custom domain "${d.domain}"?`, destructive: true }))) return;
    router.delete(`${base}/${d.id}`, { preserveScroll: true });
}
</script>
