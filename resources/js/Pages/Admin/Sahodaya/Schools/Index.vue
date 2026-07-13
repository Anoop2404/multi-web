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

            <!-- Filters -->
            <div class="filter-bar">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[180px] flex-1 max-w-sm">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                        <input v-model="filterForm.search" type="search" placeholder="Name or code…"
                               class="field">
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
                        <select :value="sortSelection" @change="applySort" class="field">
                            <option value="name-asc">Name A–Z</option>
                            <option value="name-desc">Name Z–A</option>
                            <option value="created_at-desc">Newest first</option>
                            <option value="created_at-asc">Oldest first</option>
                        </select>
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
            <div v-if="schools.data?.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Link v-for="school in schools.data" :key="school.id"
                      :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}`"
                      class="school-card group">
                    <div class="flex items-start gap-4">
                        <div class="school-card-avatar">{{ schoolInitials(school.name) }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-slate-900 group-hover:text-[#0f3d7a]">{{ school.name }}</p>
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
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 space-y-1 text-xs text-slate-500">
                        <p v-if="school.contact_email" class="truncate">✉ {{ school.contact_email }}</p>
                        <p v-if="school.contact_phone">📞 {{ school.contact_phone }}</p>
                        <p v-if="school.affiliation" class="font-mono">Aff. {{ school.affiliation }}</p>
                        <p v-else-if="school.is_non_affiliated" class="text-amber-700">No CBSE affiliation no.</p>
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
import { Link, router } from '@inertiajs/vue3';
import { reactive, computed } from 'vue';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';

const props = defineProps({
    sahodaya: Object, publicUrl: String,
    approvedSchoolsCount: Number, pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number, pendingPaymentsCount: Number,
    schools: Object, filters: Object,
    verifiedCount: { type: Number, default: 0 },
    activeAcademicYear: { type: String, default: null },
    summary: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
    search:    props.filters?.search ?? '',
    date_from: props.filters?.date_from ?? '',
    date_to:   props.filters?.date_to ?? '',
});

const sortSelection = computed({
    get: () => `${props.filters?.sort ?? 'name'}-${props.filters?.dir ?? 'asc'}`,
    set: () => {},
});

function applySort(e) {
    const [sort, dir] = e.target.value.split('-');
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({ sort, dir }), {
        preserveState: true, replace: true,
    });
}

const hasActiveFilters = computed(() =>
    filterForm.search || filterForm.date_from || filterForm.date_to
);

function listParams(overrides = {}) {
    return {
        search:    props.filters?.search ?? '',
        date_from: props.filters?.date_from ?? '',
        date_to:   props.filters?.date_to ?? '',
        sort:      props.filters?.sort ?? 'name',
        dir:       props.filters?.dir ?? 'asc',
        ...overrides,
    };
}

function applyFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({
        search: filterForm.search,
        date_from: filterForm.date_from,
        date_to: filterForm.date_to,
    }), { preserveState: true, replace: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function clearFilters() {
    filterForm.search = '';
    filterForm.date_from = '';
    filterForm.date_to = '';
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({
        search: '', date_from: '', date_to: '',
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
