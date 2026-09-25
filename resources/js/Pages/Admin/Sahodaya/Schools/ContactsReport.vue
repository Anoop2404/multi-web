<template>
    <SahodayaAdminLayout title="School Principals & Contacts" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="stats.total" :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-6">
            <PageHeader title="School Principals & Contacts" eyebrow="Reports"
                        :description="`Principal, Vice Principal and Events Coordinator for all ${stats.total} member schools — not tied to any event.`">
                <template #actions>
                    <Link :href="`${base}/schools`" class="btn-secondary text-sm">← Member schools</Link>
                    <a :href="exportUrl('pdf', true)" target="_blank" class="btn-secondary text-sm">👁 Preview PDF</a>
                    <a :href="exportUrl('pdf')" class="btn-secondary text-sm font-semibold text-rose-700 bg-rose-50 border-rose-200">⬇ Download PDF</a>
                    <a :href="exportUrl('excel')" class="btn-secondary text-sm font-semibold text-emerald-700 bg-emerald-50 border-emerald-200">📊 Excel</a>
                </template>
            </PageHeader>

            <div class="grid grid-cols-3 gap-4">
                <DashboardStatCard label="Member schools" :value="stats.total" icon="🏫" tone="navy" />
                <DashboardStatCard label="No principal on file" :value="stats.missing_principal" icon="⚠️" tone="amber" />
                <DashboardStatCard label="No vice principal on file" :value="stats.missing_vice" icon="ℹ️" tone="slate" />
            </div>

            <div class="card !py-3 flex flex-wrap items-center gap-x-5 gap-y-2">
                <span class="text-sm font-semibold text-slate-700">Include in PDF / Excel:</span>
                <label v-for="r in roleOptions" :key="r.key" class="text-sm text-slate-700 flex items-center gap-1.5">
                    <input v-model="include" type="checkbox" :value="r.key"> {{ r.label }}
                </label>
                <span v-if="include.length === 0" class="text-xs text-amber-600">Nothing ticked — all three will be included.</span>
            </div>

            <div class="card space-y-3">
                <div class="flex flex-wrap items-center gap-3">
                    <input v-model="search" type="search" class="field text-sm flex-1 min-w-[220px]" placeholder="Search school, principal, phone…">
                    <label class="text-sm text-slate-600 flex items-center gap-2">
                        <input v-model="onlyMissing" type="checkbox"> Only schools missing a principal
                    </label>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-3 w-10">Sl</th>
                                <th class="py-2 pr-3">School</th>
                                <th class="py-2 pr-3">Principal</th>
                                <th class="py-2 pr-3">Vice Principal</th>
                                <th class="py-2 pr-3">Event Manager</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(r, i) in filtered" :key="r.id" class="align-top">
                                <td class="py-2 pr-3 text-slate-500">{{ i + 1 }}</td>
                                <td class="py-2 pr-3">
                                    <div class="font-semibold text-slate-800">{{ r.school }}</div>
                                    <div class="text-xs text-slate-400">{{ [r.code, r.school_phone].filter(Boolean).join(' · ') }}</div>
                                </td>
                                <td class="py-2 pr-3"><ContactCell :name="r.principal_name" :phone="r.principal_phone" :email="r.principal_email" /></td>
                                <td class="py-2 pr-3"><ContactCell :name="r.vice_principal_name" :phone="r.vice_principal_phone" :email="r.vice_principal_email" /></td>
                                <td class="py-2 pr-3"><ContactCell :name="r.coordinator_name" :phone="r.coordinator_phone" :email="r.coordinator_email" /></td>
                            </tr>
                            <tr v-if="filtered.length === 0">
                                <td colspan="5" class="py-6 text-center text-slate-400">No schools match.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, h, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import DashboardStatCard from '@/Components/ui/DashboardStatCard.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    rows: { type: Array, default: () => [] },
    stats: Object,
});

const base = `/sahodaya-admin/${props.sahodaya.id}`;
const search = ref('');
const roleOptions = [
    { key: 'principal', label: 'Principal' },
    { key: 'vice', label: 'Vice Principal' },
    { key: 'coordinator', label: 'Event Manager' },
];
const include = ref(roleOptions.map((r) => r.key));

function exportUrl(kind, preview = false) {
    const params = new URLSearchParams();
    include.value.forEach((k) => params.append('include[]', k));
    if (preview) params.set('preview', '1');
    const qs = params.toString();

    return `${base}/schools/contacts-report/${kind}${qs ? `?${qs}` : ''}`;
}
const onlyMissing = ref(false);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.rows.filter((r) => {
        if (onlyMissing.value && (r.principal_name || r.principal_phone)) return false;
        if (!q) return true;

        return Object.values(r).some((v) => typeof v === 'string' && v.toLowerCase().includes(q));
    });
});

const ContactCell = (p) => {
    if (!p.name && !p.phone && !p.email) return null;

    return h('div', [
        h('div', { class: 'text-slate-800' }, p.name || '—'),
        p.phone ? h('a', { class: 'block text-xs text-[#0f3d7a]', href: `tel:${p.phone}` }, p.phone) : null,
        p.email ? h('a', { class: 'block text-xs text-slate-500 break-all', href: `mailto:${p.email}` }, p.email) : null,
    ]);
};
ContactCell.props = ['name', 'phone', 'email'];
</script>
