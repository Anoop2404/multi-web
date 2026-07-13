<template>
    <SahodayaAdminLayout title="Pending applications" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="approvedSchoolsCount"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <PageHeader
            title="Pending school applications"
            eyebrow="Membership"
            :description="`${schools.total ?? 0} application${(schools.total ?? 0) === 1 ? '' : 's'} awaiting review`"
        >
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/schools`" class="btn-secondary text-sm">Verified schools →</Link>
            </template>
        </PageHeader>

        <div v-if="selectedIds.length" class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-[#bfdbfe] bg-[#eff6ff] px-4 py-3">
            <span class="text-sm font-semibold text-[#0f3d7a]">{{ selectedIds.length }} selected</span>
            <button type="button" class="btn-primary text-sm" :disabled="bulkForm.processing" @click="bulkApprove">
                Approve selected
            </button>
            <button type="button" class="btn-secondary text-sm text-red-700 border-red-200" :disabled="bulkForm.processing" @click="bulkReject">
                Reject selected
            </button>
            <button type="button" class="text-sm text-slate-500 ml-auto" @click="clearSelection">Clear</button>
        </div>

        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[180px] max-w-sm">
                <input v-model="filterForm.search" type="search" placeholder="Search name…"
                       class="field">
            </div>
            <label v-if="schools.data?.length" class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" :checked="allSelected" @change="toggleSelectAll">
                Select all on page
            </label>
        </div>

        <div v-if="schools.data?.length" class="space-y-3">
            <div v-for="school in schools.data" :key="school.id"
                 class="flex items-center gap-3 bg-white rounded-2xl border border-amber-100 shadow-sm px-5 py-4 hover:border-[#0f3d7a]/30 transition">
                <input type="checkbox" :value="school.id" v-model="selectedIds" class="rounded border-slate-300 shrink-0">
                <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}`"
                      class="flex-1 min-w-0 group">
                    <p class="font-bold text-gray-900 group-hover:text-[#0f3d7a]">
                        {{ school.name }}
                        <span v-if="school.is_non_affiliated"
                              class="ml-2 inline-flex align-middle rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 ring-1 ring-amber-200">
                            Non-affiliated
                        </span>
                        <span v-else
                              class="ml-2 inline-flex align-middle rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-200">
                            Affiliated
                        </span>
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Applied {{ formatDate(school.created_at) }}
                        <span v-if="school.contact_email"> · {{ school.contact_email }}</span>
                        <span v-if="school.affiliation"> · Aff. {{ school.affiliation }}</span>
                    </p>
                </Link>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <button type="button" class="btn-primary text-xs py-1.5 px-3" @click="approveOne(school.id)">Approve</button>
                    <button type="button" class="btn-secondary text-xs py-1.5 px-3 text-red-700 border-red-200" @click="rejectOne(school.id)">Reject</button>
                </div>
            </div>
            <div v-if="schools.links?.length > 3" class="flex justify-center gap-1 pt-2">
                <Link v-for="link in schools.links" :key="link.label"
                      :href="link.url || '#'"
                      class="px-3 py-1 rounded text-sm"
                      :class="link.active ? 'bg-[#0f3d7a] text-white' : 'text-gray-600 hover:bg-gray-100'"
                      v-html="link.label" />
            </div>
        </div>

        <EmptyState v-else title="No pending applications" description="New school applications will appear here until membership is approved." icon="🏫" />
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';

const props = defineProps({
    sahodaya: Object, publicUrl: String,
    approvedSchoolsCount: Number, pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number, pendingPaymentsCount: Number,
    schools: Object, filters: Object,
});

const filterForm = reactive({ search: props.filters?.search ?? '' });
const selectedIds = ref([]);
const bulkForm = useForm({ school_ids: [], reason: '' });

const allSelected = computed(() => {
    const ids = (props.schools.data ?? []).map((s) => s.id);
    return ids.length > 0 && ids.every((id) => selectedIds.value.includes(id));
});

function applyFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools/applications`, {
        search: filterForm.search,
    }, { preserveState: true, replace: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function toggleSelectAll(event) {
    const ids = (props.schools.data ?? []).map((s) => s.id);
    selectedIds.value = event.target.checked ? ids : [];
}

function clearSelection() {
    selectedIds.value = [];
}

function approveOne(schoolId) {
    if (!confirm('Approve this school application?')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${schoolId}/approve`, {}, { preserveScroll: true });
}

function rejectOne(schoolId) {
    const reason = prompt('Rejection reason:');
    if (!reason?.trim()) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${schoolId}/reject`, { reason }, { preserveScroll: true });
}

function bulkApprove() {
    if (!selectedIds.value.length) return;
    if (!confirm(`Approve ${selectedIds.value.length} school application(s)?`)) return;
    bulkForm.school_ids = [...selectedIds.value];
    bulkForm.post(`/sahodaya-admin/${props.sahodaya.id}/schools/applications/bulk-approve`, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; bulkForm.reset(); },
    });
}

function bulkReject() {
    if (!selectedIds.value.length) return;
    const reason = prompt('Rejection reason for all selected schools:');
    if (!reason?.trim()) return;
    bulkForm.school_ids = [...selectedIds.value];
    bulkForm.reason = reason;
    bulkForm.post(`/sahodaya-admin/${props.sahodaya.id}/schools/applications/bulk-reject`, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; bulkForm.reset(); },
    });
}
</script>
