<template>
    <SahodayaAdminLayout :title="`${program.title} — Registrations`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="program.title" eyebrow="Training registrations"
                    :description="`${counts.total} registration(s) · ${program.status}`">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}`" class="btn-secondary text-sm">
                    ← Program
                </Link>
                <Link v-if="hasFee"
                      :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/payments`"
                      class="btn-secondary text-sm">
                    Fee approvals
                </Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/qr-reports`"
                      class="btn-secondary text-sm">
                    QR reports
                </Link>
                <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations/export-pdf`"
                   class="btn-primary text-sm">
                    Download PDF
                </a>
                <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations/export`"
                   class="btn-secondary text-sm">
                    Excel
                </a>
                <a v-if="counts.confirmed"
                   :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/certificates/export`"
                   class="btn-secondary text-sm">
                    Certificates (ZIP)
                </a>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <button type="button" class="card text-center hover:ring-2 hover:ring-[#0f3d7a]/20 transition" @click="setStatus('all')">
                <p class="text-2xl font-bold">{{ counts.total }}</p>
                <p class="text-xs text-gray-500">Total</p>
            </button>
            <button type="button" class="card text-center hover:ring-2 hover:ring-amber-500/20 transition" @click="setStatus('registered')">
                <p class="text-2xl font-bold text-amber-600">{{ counts.registered }}</p>
                <p class="text-xs text-gray-500">Registered</p>
            </button>
            <button type="button" class="card text-center hover:ring-2 hover:ring-green-500/20 transition" @click="setStatus('confirmed')">
                <p class="text-2xl font-bold text-green-700">{{ counts.confirmed }}</p>
                <p class="text-xs text-gray-500">Confirmed</p>
            </button>
            <button type="button" class="card text-center hover:ring-2 hover:ring-slate-400/30 transition" @click="setStatus('waitlisted')">
                <p class="text-2xl font-bold text-slate-600">{{ counts.waitlisted }}</p>
                <p class="text-xs text-gray-500">Waitlisted</p>
            </button>
            <button type="button" class="card text-center hover:ring-2 hover:ring-indigo-500/20 transition" @click="setSource('qr')">
                <p class="text-2xl font-bold text-indigo-700">{{ counts.qr }}</p>
                <p class="text-xs text-gray-500">Via QR</p>
            </button>
        </div>

        <button v-if="counts.no_school" type="button"
                class="mb-6 w-full text-left rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 hover:bg-rose-100 transition"
                @click="setSchoolFilter('none')">
            <span class="font-semibold">{{ counts.no_school }} teacher(s) have no school on this registration</span>
            <span class="block text-xs text-rose-700 mt-0.5">Not linked to a member school and no pending-school request was submitted. Click to view &amp; assign a school.</span>
        </button>

        <SahodayaDataTable
            :columns="columns"
            :links="registrations.links"
            :meta="{ from: registrations.from, to: registrations.to, total: registrations.total, last_page: registrations.last_page }"
            :sort="filters.sort"
            :dir="filters.dir"
            :has-rows="!!registrations.data?.length"
            empty="No registrations match the filters."
            @sort="toggleSort"
        >
            <template #toolbar>
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:flex-wrap">
                    <div class="flex-1 min-w-[14rem]">
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Search</label>
                        <input v-model="filterForm.search" type="search" class="field text-sm"
                               placeholder="Teacher, email, school…">
                    </div>
                    <div class="min-w-[10rem]">
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Status</label>
                        <select v-model="filterForm.status" class="field text-sm">
                            <option value="all">All statuses</option>
                            <option value="registered">Registered</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="waitlisted">Waitlisted</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="min-w-[9rem]">
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Source</label>
                        <select v-model="filterForm.source" class="field text-sm">
                            <option value="all">All sources</option>
                            <option value="qr">QR</option>
                            <option value="portal">Portal</option>
                            <option value="school">School</option>
                        </select>
                    </div>
                    <div class="min-w-[11rem]">
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Verification</label>
                        <select v-model="filterForm.verification" class="field text-sm">
                            <option value="all">All teachers</option>
                            <option value="verified">Verified</option>
                            <option value="unverified">Unverified</option>
                        </select>
                    </div>
                    <div class="min-w-[10rem]">
                        <label class="text-xs font-semibold text-slate-600 block mb-1">School</label>
                        <select v-model="filterForm.school" class="field text-sm">
                            <option value="all">All</option>
                            <option value="assigned">Has a school</option>
                            <option value="pending">Pending school</option>
                            <option value="none">No school at all</option>
                        </select>
                    </div>
                    <div class="min-w-[7rem]">
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Per page</label>
                        <select v-model="filterForm.per_page" class="field text-sm">
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>
                    <button v-if="hasActiveFilters" type="button" class="btn-ghost text-sm" @click="clearFilters">
                        Clear
                    </button>
                </div>
            </template>

            <tr v-for="r in registrations.data" :key="r.id" class="hover:bg-gray-50/80">
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">{{ r.teacher?.name || `#${r.id}` }}</div>
                    <div class="text-xs text-gray-400">{{ r.teacher?.email || '' }}</div>
                    <div class="flex flex-wrap gap-1 mt-1">
                        <span v-if="r.teacher_created"
                              class="text-[10px] uppercase tracking-wide text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">
                            New teacher
                        </span>
                        <span v-if="r.teacher && !r.teacher.verified_at"
                              class="text-[10px] uppercase tracking-wide text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">
                            Unverified
                        </span>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-sm text-gray-800">{{ schoolName(r) }}</div>
                    <span v-if="r.pending_school_id"
                          class="text-[10px] uppercase tracking-wide text-rose-700 bg-rose-50 px-1.5 py-0.5 rounded">
                        Pending school
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ r.teacher?.teaching_type?.label || '—' }}</td>
                <td class="px-4 py-3">
                    <span v-if="r.registration_source === 'qr'"
                          class="text-[10px] uppercase tracking-wide text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">
                        QR
                    </span>
                    <span v-else class="text-xs text-gray-400 capitalize">{{ r.registration_source || 'portal' }}</span>
                </td>
                <td class="px-4 py-3 capitalize text-gray-600">
                    {{ r.status }}
                    <span v-if="r.status === 'waitlisted' && r.waitlist_position"
                          class="block text-[10px] text-slate-500 normal-case">
                        Position #{{ r.waitlist_position }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    <template v-if="hasFee">
                        <span v-if="r.fee_receipt?.status === 'approved'" class="text-xs text-green-700 font-semibold">Approved</span>
                        <span v-else-if="r.fee_receipt?.status === 'uploaded'" class="text-xs text-amber-700 font-semibold">Pending</span>
                        <span v-else-if="r.fee_receipt?.status === 'rejected'" class="text-xs text-red-600 font-semibold">Rejected</span>
                        <span v-else-if="r.fee_status === 'auto_approved'" class="text-xs text-indigo-700 font-semibold">Auto approved</span>
                        <span v-else class="text-xs text-gray-400">No proof</span>
                    </template>
                    <span v-else class="text-xs text-gray-400">—</span>
                </td>
                <td class="px-4 py-3 text-right">
                    <div class="flex justify-end items-center gap-2 flex-wrap">
                        <Link v-if="hasFee && r.status === 'registered'"
                              :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/payments`"
                              class="text-xs text-indigo-600 font-semibold">
                            Fee approvals →
                        </Link>
                        <a v-if="!['cancelled','rejected'].includes(r.status)"
                           :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations/${r.id}/id-card`"
                           target="_blank" rel="noopener"
                           class="text-xs text-slate-600 font-semibold">ID card ↓</a>
                        <a v-if="hasFee && program.fee_type === 'flat'"
                           :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations/${r.id}/invoice`"
                           target="_blank" rel="noopener"
                           class="text-xs text-indigo-600 font-semibold">Invoice ↓</a>
                        <template v-if="r.status === 'confirmed'">
                            <button type="button" @click="issueCertificate(r)"
                                    class="text-xs text-purple-600 font-semibold">
                                {{ r.certificate ? 'Reissue cert' : 'Issue cert' }}
                            </button>
                            <a v-if="r.certificate"
                               :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations/${r.id}/certificate/print`"
                               target="_blank" rel="noopener"
                               class="text-xs text-indigo-600 font-semibold">Print ↗</a>
                        </template>
                        <button v-if="canCancel(r)"
                                type="button"
                                @click="cancelRegistration(r)"
                                class="text-xs text-red-600 font-semibold">
                            Cancel
                        </button>
                    </div>
                </td>
            </tr>
        </SahodayaDataTable>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    registrations: { type: Object, required: true },
    counts: {
        type: Object,
        default: () => ({ total: 0, registered: 0, confirmed: 0, waitlisted: 0, qr: 0, no_school: 0 }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            status: 'all',
            source: 'all',
            verification: 'all',
            school: 'all',
            sort: 'id',
            dir: 'desc',
            per_page: 50,
        }),
    },
});

const columns = [
    { key: 'teacher', label: 'Teacher', sortable: true },
    { key: 'school', label: 'School' },
    { key: 'category', label: 'Category' },
    { key: 'source', label: 'Source', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'fee', label: 'Fee' },
    { key: 'actions', label: '', align: 'right', class: 'w-48' },
];

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? 'all',
    source: props.filters.source ?? 'all',
    verification: props.filters.verification ?? 'all',
    school: props.filters.school ?? 'all',
    per_page: Number(props.filters.per_page ?? 50),
});

watch(() => props.filters, (f) => {
    if (!f) return;
    filterForm.search = f.search ?? '';
    filterForm.status = f.status ?? 'all';
    filterForm.source = f.source ?? 'all';
    filterForm.verification = f.verification ?? 'all';
    filterForm.school = f.school ?? 'all';
    filterForm.per_page = Number(f.per_page ?? 50);
}, { deep: true });

const hasFee = computed(() => props.program.fee_type !== 'none' && Number(props.program.fee_amount) > 0);

const hasActiveFilters = computed(() =>
    !!filterForm.search
    || filterForm.status !== 'all'
    || filterForm.source !== 'all'
    || filterForm.verification !== 'all'
    || filterForm.school !== 'all'
    || Number(filterForm.per_page) !== 50,
);

const listUrl = computed(() =>
    `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/registrations`,
);

function listParams(extra = {}) {
    return {
        search: filterForm.search || undefined,
        status: filterForm.status,
        source: filterForm.source,
        verification: filterForm.verification,
        school: filterForm.school,
        sort: props.filters.sort ?? 'id',
        dir: props.filters.dir ?? 'desc',
        per_page: Number(filterForm.per_page) || 50,
        ...extra,
    };
}

function applyFilters() {
    router.get(listUrl.value, listParams(), { preserveState: true, preserveScroll: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => ({
    search: props.filters.search ?? '',
    status: props.filters.status ?? 'all',
    source: props.filters.source ?? 'all',
    verification: props.filters.verification ?? 'all',
    school: props.filters.school ?? 'all',
    per_page: Number(props.filters.per_page ?? 50),
}));

function clearFilters() {
    filterForm.search = '';
    filterForm.status = 'all';
    filterForm.source = 'all';
    filterForm.verification = 'all';
    filterForm.school = 'all';
    filterForm.per_page = 50;
    router.get(listUrl.value, {
        sort: props.filters.sort ?? 'id',
        dir: props.filters.dir ?? 'desc',
        per_page: 50,
    }, { preserveState: true, preserveScroll: true });
}

function setStatus(status) {
    filterForm.status = status;
    filterForm.source = 'all';
    applyFilters();
}

function setSource(source) {
    filterForm.source = source;
    filterForm.status = 'all';
    applyFilters();
}

function setSchoolFilter(school) {
    filterForm.school = school;
    filterForm.status = 'all';
    filterForm.source = 'all';
    applyFilters();
}

function toggleSort(key) {
    const sort = props.filters.sort ?? 'id';
    const dir = props.filters.dir ?? 'desc';
    const nextDir = sort === key && dir === 'asc' ? 'desc' : 'asc';
    router.get(listUrl.value, listParams({
        sort: key,
        dir: sort === key ? nextDir : 'asc',
    }), { preserveState: true, preserveScroll: true });
}

function schoolName(r) {
    // Pending-school QR rows historically stored the Sahodaya id as school_id —
    // always prefer the pending school name when present.
    if (r.pending_school?.school_name) {
        return r.pending_school.school_name;
    }
    return r.display_school_name || r.school?.name || '—';
}

function canCancel(r) {
    return !['cancelled', 'completed'].includes(r.status);
}

function cancelRegistration(registration) {
    if (!confirm(`Cancel registration for ${registration.teacher?.name || 'this teacher'}? A waitlisted participant may be promoted.`)) {
        return;
    }
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/registrations/${registration.id}/cancel`,
        {},
        { preserveScroll: true },
    );
}

function issueCertificate(registration) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/registrations/${registration.id}/certificate`, {}, { preserveScroll: true });
}
</script>
