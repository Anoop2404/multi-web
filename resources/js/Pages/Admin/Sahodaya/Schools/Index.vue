<template>
    <SahodayaAdminLayout title="Schools" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="approvedSchoolsCount"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-6">
            <PageHeader
                title="Member schools"
                eyebrow="Membership"
                :description="`${verifiedCount} verified schools${activeAcademicYear ? ' · ' + activeAcademicYear : ''}`"
            >
                <template #actions>
                    <Link v-if="pendingSchoolsCount > 0"
                          :href="`/sahodaya-admin/${sahodaya.id}/schools/applications`"
                          class="btn-secondary text-sm">
                        {{ pendingSchoolsCount }} pending application{{ pendingSchoolsCount === 1 ? '' : 's' }} →
                    </Link>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`" class="btn-secondary text-sm">
                        Pending approvals →
                    </Link>
                </template>
            </PageHeader>

            <!-- Summary stats -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <DashboardStatCard label="Verified schools" :value="verifiedCount" icon="🏫" tone="navy" />
                <DashboardStatCard
                    label="Pending applications"
                    :value="pendingSchoolsCount ?? 0"
                    icon="📥"
                    tone="amber"
                    :hint="pendingSchoolsCount > 0 ? 'Review applications →' : null"
                    :href="pendingSchoolsCount > 0 ? `/sahodaya-admin/${sahodaya.id}/schools/applications` : null"
                />
                <DashboardStatCard label="Total students" :value="summary?.total_students ?? 0" icon="👨‍🎓" tone="green" />
                <DashboardStatCard label="Active classes" :value="summary?.total_classes ?? 0" icon="📚" tone="indigo" />
            </div>

            <!-- Bulk credential actions -->
            <div v-if="selectedIds.length" class="flex flex-wrap items-center gap-2 rounded-xl border border-[#bfdbfe] bg-[#eff6ff] px-4 py-3">
                <span class="text-sm font-semibold text-[#0f3d7a]">{{ selectedIds.length }} selected</span>
                <button type="button" class="btn-primary text-sm" :disabled="bulkForm.processing" @click="bulkCreateLogin">
                    🔑 Create login & send credentials
                </button>
                <button type="button" class="btn-secondary text-sm" :disabled="bulkForm.processing" @click="bulkResetPassword">
                    Reset password
                </button>
                <button type="button" class="btn-secondary text-sm" :disabled="bulkForm.processing" @click="bulkSendCredentials">
                    Resend credentials
                </button>
                <button type="button" class="text-sm text-slate-500 ml-auto" @click="clearSelection">Clear</button>
            </div>

            <!-- Quick Payment Status Filter Pills -->
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="setPaymentStatusFilter('all')"
                        :class="['px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer',
                                 (filterForm.payment_status || 'all') === 'all' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']">
                    All Member Schools ({{ verifiedCount }})
                </button>
                <button type="button" @click="setPaymentStatusFilter('no_proof')"
                        :class="['px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer flex items-center gap-1.5',
                                 filterForm.payment_status === 'no_proof' || filterForm.payment_status === 'payment_not_done' ? 'bg-rose-700 text-white shadow-xs' : 'bg-rose-50 text-rose-800 border border-rose-200 hover:bg-rose-100']">
                    <span>⚠️ Approved — No Payment Proof / Fee Due</span>
                </button>
                <button type="button" @click="setPaymentStatusFilter('payment_pending')"
                        :class="['px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer flex items-center gap-1.5',
                                 filterForm.payment_status === 'payment_pending' ? 'bg-amber-600 text-white shadow-xs' : 'bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100']">
                    <span>⏳ Payment Uploaded (Pending Review)</span>
                </button>
                <button type="button" @click="setPaymentStatusFilter('payment_verified')"
                        :class="['px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer flex items-center gap-1.5',
                                 filterForm.payment_status === 'payment_verified' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100']">
                    <span>✓ Fee Verified</span>
                </button>
                <button type="button" @click="setLoginStatusFilter(filterForm.login_status === 'no_login' ? 'all' : 'no_login')"
                        :class="['px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer flex items-center gap-1.5',
                                 filterForm.login_status === 'no_login' ? 'bg-amber-700 text-white shadow-xs' : 'bg-amber-50 text-amber-900 border border-amber-300 hover:bg-amber-100']">
                    <span>🔑 Schools Without Login</span>
                </button>
            </div>

            <!-- Filters -->
            <div class="filter-bar">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[180px] flex-1 max-w-sm">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                        <input v-model="filterForm.search" type="search" placeholder="Name or code…"
                               class="field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Payment status</label>
                        <SearchableSelect v-model="filterForm.payment_status"
                            :options="[
                                { value: 'all', label: 'All payment statuses' },
                                { value: 'no_proof', label: '⚠️ Approved — No Payment Proof / Fee Due' },
                                { value: 'payment_pending', label: '⏳ Payment Uploaded (Pending Review)' },
                                { value: 'payment_verified', label: '✓ Fee Verified' },
                            ]"
                            :all-option="false" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">From</label>
                        <input v-model="filterForm.date_from" type="date" class="field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">To</label>
                        <input v-model="filterForm.date_to" type="date" class="field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sort</label>
                        <SearchableSelect :model-value="sortSelection" @update:model-value="applySort"
                            :options="[
                                { value: 'name-asc', label: 'Name A–Z' },
                                { value: 'name-desc', label: 'Name Z–A' },
                                { value: 'created_at-desc', label: 'Newest first' },
                                { value: 'created_at-asc', label: 'Oldest first' },
                            ]"
                            :all-option="false" />
                    </div>
                    <div class="flex items-end gap-2">
                        <button v-if="hasActiveFilters" @click="clearFilters"
                                class="btn-secondary text-sm">
                            Clear
                        </button>
                    </div>
                    <a :href="exportUrl()"
                       class="ml-auto inline-flex items-center gap-1.5 rounded-xl border border-[#bfdbfe] bg-[#eff6ff] px-4 py-2.5 text-sm font-semibold text-[#0f3d7a] transition hover:bg-[#dbeafe]">
                        Download Excel ↓
                    </a>
                </div>
            </div>

            <!-- School cards -->
            <label v-if="schools.data?.length" class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" :checked="allSelected" @change="toggleSelectAll">
                Select all on page
            </label>
            <div v-if="schools.data?.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="school in schools.data" :key="school.id" class="relative">
                    <input type="checkbox" :value="school.id" v-model="selectedIds"
                           class="absolute top-3 right-3 z-10 rounded border-slate-300 shadow-sm">
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}`"
                          class="school-card group">
                        <div class="flex items-start gap-4">
                            <div class="school-card-avatar">{{ schoolInitials(school.name) }}</div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate pr-6 font-semibold text-slate-900 group-hover:text-[#0f3d7a]">{{ school.name }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <p v-if="school.school_prefix" class="font-mono text-xs text-[#0f3d7a]">{{ school.school_prefix }}</p>
                                    <p v-else class="text-xs text-slate-400">No code set</p>
                                    <span v-if="school.is_non_affiliated"
                                          class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 ring-1 ring-amber-200">
                                        Non-affiliated
                                    </span>
                                    <span v-else
                                          class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-200">
                                        Affiliated
                                    </span>
                                    <span v-if="!school.has_login"
                                          class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-amber-900 ring-1 ring-amber-300">
                                        🔑 No login
                                    </span>
                                    <span v-if="school.payment_status === 'payment_verified'"
                                          class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-emerald-700 ring-1 ring-emerald-200">
                                        ✓ Fee verified
                                    </span>
                                    <span v-else-if="school.payment_status === 'payment_pending'"
                                          class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-amber-800 ring-1 ring-amber-200">
                                        ⏳ Payment uploaded
                                    </span>
                                    <span v-else-if="school.payment_status === 'payment_not_done'"
                                          class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-rose-800 ring-1 ring-rose-200">
                                        ⚠️ Fee due (Unpaid)
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1 text-xs text-slate-500">
                            <p v-if="school.contact_email" class="truncate font-medium text-slate-700">✉ {{ school.contact_email }}</p>
                            <p v-else class="text-amber-700 italic">No email entered</p>
                            <p v-if="school.contact_phone">📞 {{ school.contact_phone }}</p>
                            <p v-if="school.affiliation" class="font-mono">Aff. {{ school.affiliation }}</p>
                            <p v-else-if="school.is_non_affiliated" class="text-amber-700">No CBSE affiliation no.</p>
                        </div>

                        <div v-if="!school.has_login" class="mt-2.5 pt-2.5 border-t border-amber-100/80 flex items-center justify-between gap-2">
                            <span class="text-[11px] font-semibold text-amber-900">No login assigned</span>
                            <button type="button"
                                    class="btn-primary text-xs !py-1 !px-2.5 bg-amber-700 hover:bg-amber-800 border-amber-700 shrink-0 cursor-pointer relative z-20"
                                    @click.prevent.stop="createLoginForSchool(school)">
                                🔑 Assign Login
                            </button>
                        </div>

                        <div class="school-card-metrics">
                            <div class="school-card-metric">
                                <p class="school-card-metric-value">{{ school.student_count ?? 0 }}</p>
                                <p class="school-card-metric-label">Students</p>
                            </div>
                            <div class="school-card-metric">
                                <p class="school-card-metric-value">{{ school.classes_count ?? 0 }}</p>
                                <p class="school-card-metric-label">Classes</p>
                            </div>
                        </div>

                        <p class="mt-3 text-[10px] font-medium uppercase tracking-wide text-slate-400">
                            Joined {{ formatDate(school.created_at) }}
                        </p>
                    </Link>
                </div>
            </div>

            <EmptyState v-else title="No verified schools found" description="Try adjusting your search or date filters." icon="🏫">
                <template v-if="pendingSchoolsCount > 0" #action>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/applications`" class="btn-secondary text-sm">
                        Review {{ pendingSchoolsCount }} pending application{{ pendingSchoolsCount === 1 ? '' : 's' }}
                    </Link>
                </template>
            </EmptyState>

            <!-- Pagination -->
            <div v-if="schools.data?.length && schools.links?.length > 3"
                 class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200/80 bg-white px-4 py-3 text-sm">
                <p class="text-slate-500">
                    Showing <span class="font-semibold text-slate-700">{{ schools.from }}–{{ schools.to }}</span>
                    of <span class="font-semibold text-slate-700">{{ schools.total }}</span>
                </p>
                <nav class="flex flex-wrap gap-1">
                    <Link v-for="link in schools.links" :key="link.label"
                          :href="link.url ?? '#'"
                          class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                          :class="[
                              link.active ? 'bg-[#0f3d7a] text-white' : 'text-slate-600 hover:bg-slate-100',
                              !link.url && 'pointer-events-none opacity-40',
                          ]"
                          v-html="link.label" />
                </nav>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import DashboardStatCard from '@/Components/ui/DashboardStatCard.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { reactive, computed, ref } from 'vue';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object, publicUrl: String,
    approvedSchoolsCount: Number, pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number, pendingPaymentsCount: Number,
    schools: Object, filters: Object,
    verifiedCount: { type: Number, default: 0 },
    activeAcademicYear: { type: String, default: null },
    summary: { type: Object, default: () => ({}) },
});

const { confirm } = useConfirm();
const selectedIds = ref([]);
const bulkForm = useForm({ school_ids: [] });

const allSelected = computed(() => {
    const ids = (props.schools.data ?? []).map((s) => s.id);
    return ids.length > 0 && ids.every((id) => selectedIds.value.includes(id));
});

function toggleSelectAll(event) {
    const ids = (props.schools.data ?? []).map((s) => s.id);
    selectedIds.value = event.target.checked ? ids : [];
}

function clearSelection() {
    selectedIds.value = [];
}

async function createLoginForSchool(school) {
    let email = (school.contact_email || '').trim();
    if (!email) {
        email = await prompt({ message: `Enter portal login email address for "${school.name}":`, inputPlaceholder: 'school@example.com' });
        if (!email?.trim()) return;
        email = email.trim();
    } else {
        if (!(await confirm({ message: `Create portal login for "${school.name}" using email ${email}? Credentials will be sent by email.`, destructive: false }))) return;
    }

    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${school.id}/create-login`, {
        email: email,
    }, { preserveScroll: true });
}

async function bulkCreateLogin() {
    if (!selectedIds.value.length) return;
    if (!(await confirm({ message: `Create portal logins and email login details for ${selectedIds.value.length} selected school(s)?`, destructive: false }))) return;
    bulkForm.school_ids = [...selectedIds.value];
    bulkForm.post(`/sahodaya-admin/${props.sahodaya.id}/schools/bulk-create-login`, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; bulkForm.reset(); },
    });
}

async function bulkResetPassword() {
    if (!selectedIds.value.length) return;
    if (!(await confirm({ message: `Reset the password for ${selectedIds.value.length} school(s)? New temporary passwords will be emailed.` }))) return;
    bulkForm.school_ids = [...selectedIds.value];
    bulkForm.post(`/sahodaya-admin/${props.sahodaya.id}/schools/bulk-reset-password`, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; bulkForm.reset(); },
    });
}

async function bulkSendCredentials() {
    if (!selectedIds.value.length) return;
    if (!(await confirm({ message: `Resend current credentials to ${selectedIds.value.length} school(s)?`, destructive: false }))) return;
    bulkForm.school_ids = [...selectedIds.value];
    bulkForm.post(`/sahodaya-admin/${props.sahodaya.id}/schools/bulk-send-credentials`, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; bulkForm.reset(); },
    });
}

const filterForm = reactive({
    search:         props.filters?.search ?? '',
    date_from:      props.filters?.date_from ?? '',
    date_to:        props.filters?.date_to ?? '',
    payment_status: props.filters?.payment_status ?? 'all',
    login_status:   props.filters?.login_status ?? 'all',
});

const sortSelection = computed({
    get: () => `${props.filters?.sort ?? 'name'}-${props.filters?.dir ?? 'asc'}`,
    set: () => {},
});

function applySort(value) {
    const [sort, dir] = value.split('-');
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({ sort, dir }), {
        preserveState: true, replace: true,
    });
}

const hasActiveFilters = computed(() =>
    filterForm.search || filterForm.date_from || filterForm.date_to || (filterForm.payment_status && filterForm.payment_status !== 'all') || (filterForm.login_status && filterForm.login_status !== 'all')
);

function listParams(overrides = {}) {
    return {
        search:         props.filters?.search ?? '',
        date_from:      props.filters?.date_from ?? '',
        date_to:        props.filters?.date_to ?? '',
        payment_status: props.filters?.payment_status ?? 'all',
        login_status:   props.filters?.login_status ?? 'all',
        sort:           props.filters?.sort ?? 'name',
        dir:            props.filters?.dir ?? 'asc',
        ...overrides,
    };
}

function setPaymentStatusFilter(status) {
    filterForm.payment_status = status;
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({ payment_status: status }), {
        preserveState: true, replace: true,
    });
}

function setLoginStatusFilter(status) {
    filterForm.login_status = status;
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({ login_status: status }), {
        preserveState: true, replace: true,
    });
}

function applyFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({
        search:         filterForm.search,
        date_from:      filterForm.date_from,
        date_to:        filterForm.date_to,
        payment_status: filterForm.payment_status,
        login_status:   filterForm.login_status,
    }), { preserveState: true, replace: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function clearFilters() {
    filterForm.search = '';
    filterForm.date_from = '';
    filterForm.date_to = '';
    filterForm.payment_status = 'all';
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({
        search: '', date_from: '', date_to: '', payment_status: 'all',
    }), { preserveState: true, replace: true });
}

function exportUrl() {
    const params = new URLSearchParams();
    const p = listParams({
        search: filterForm.search,
        date_from: filterForm.date_from,
        date_to: filterForm.date_to,
    });
    Object.entries(p).forEach(([key, value]) => {
        if (value) params.set(key, value);
    });
    const qs = params.toString();
    return `/sahodaya-admin/${props.sahodaya.id}/schools/export${qs ? `?${qs}` : ''}`;
}

function schoolInitials(name) {
    if (!name) return '?';
    const parts = String(name).trim().split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return parts[0].slice(0, 2).toUpperCase();
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
