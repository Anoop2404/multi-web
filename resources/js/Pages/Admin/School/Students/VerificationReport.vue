<template>
    <SchoolAdminLayout title="Student Verification Report" :school="school" :show-header-title="false">
        <PageHeader
            title="Verification report"
            eyebrow="Students"
            :description="`${summary.total_active} active students · ${summary.verified_pct}% verified`"
        >
            <template #actions>
                <Link :href="`/school-admin/${school.id}/students`" class="btn-secondary text-sm">← Students</Link>
                <button v-if="summary.unverified > 0" type="button"
                        :disabled="bulkVerifyForm.processing"
                        class="btn-secondary text-sm"
                        @click="verifyAllUnverified">
                    {{ bulkVerifyForm.processing ? 'Verifying…' : `Verify all (${summary.unverified})` }}
                </button>
                <button v-if="showVerifyFiltered" type="button"
                        :disabled="bulkVerifyForm.processing"
                        class="btn-secondary text-sm"
                        @click="verifyFiltered">
                    {{ bulkVerifyForm.processing ? 'Verifying…' : `Verify filtered (${filteredUnverifiedCount})` }}
                </button>
                <a :href="exportUrl('verified')" class="btn-secondary text-sm">Export verified ↓</a>
                <a :href="exportUrl('unverified')" class="btn-secondary text-sm">Export unverified ↓</a>
                <a :href="exportUrl(filterForm.verification)" class="btn-primary text-sm">Export current view ↓</a>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-slate-500 tracking-wide">Active students</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ summary.total_active }}</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-emerald-700 tracking-wide">Verified</p>
                <p class="text-2xl font-bold text-emerald-900 mt-1">{{ summary.verified }}</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-amber-700 tracking-wide">Needs verification</p>
                <p class="text-2xl font-bold text-amber-900 mt-1">{{ summary.unverified }}</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-blue-700 tracking-wide">Completion</p>
                <p class="text-2xl font-bold text-blue-900 mt-1">{{ summary.verified_pct }}%</p>
            </div>
        </div>

        <div v-if="classStats.length" class="card mb-6 overflow-hidden p-0">
            <div class="px-4 py-3 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-900">By class</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Category</th>
                            <th>Total</th>
                            <th>Verified</th>
                            <th>Unverified</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in classStats" :key="row.class_id">
                            <td class="font-medium">Class {{ row.class_name }}</td>
                            <td class="text-gray-500">{{ row.category || '—' }}</td>
                            <td>{{ row.total }}</td>
                            <td class="text-emerald-700 font-medium">{{ row.verified }}</td>
                            <td class="text-amber-700 font-medium">{{ row.unverified }}</td>
                            <td class="text-right space-x-2">
                                <button v-if="row.unverified > 0" type="button"
                                        class="link-brand text-xs"
                                        :disabled="bulkVerifyForm.processing"
                                        @click="verifyClass(row.class_id, row.unverified)">
                                    Verify class
                                </button>
                                <button v-if="row.unverified > 0" type="button"
                                        class="link-brand text-xs"
                                        @click="filterClassUnverified(row.class_id)">
                                    View
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <SahodayaDataTable
            :columns="columns"
            :links="students.links"
            :meta="{ from: students.from, to: students.to, total: students.total }"
            :sort="filters.sort"
            :dir="filters.dir"
            :has-rows="!!students.data?.length"
            empty="No students match these filters."
            @sort="toggleSort"
        >
            <template #toolbar>
                <div class="space-y-3">
                    <div v-if="pageUnverifiedStudents.length" class="flex flex-wrap items-center gap-3 rounded-lg border border-emerald-100 bg-emerald-50/60 px-3 py-2">
                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                            <input type="checkbox" class="rounded"
                                   :checked="allPageSelected"
                                   :indeterminate.prop="somePageSelected && !allPageSelected"
                                   @change="toggleSelectAllOnPage">
                            Select page
                        </label>
                        <button type="button" class="btn-primary text-xs !py-1.5"
                                :disabled="!selectedIds.length || bulkVerifyForm.processing"
                                @click="verifySelected">
                            {{ bulkVerifyForm.processing ? 'Verifying…' : `Verify selected (${selectedIds.length})` }}
                        </button>
                        <button v-if="selectedIds.length" type="button" class="btn-ghost text-xs" @click="clearSelection">
                            Clear
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="tab in verificationTabs" :key="tab.key" type="button"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition"
                                :class="filterForm.verification === tab.key
                                    ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]'
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                @click="setVerificationTab(tab.key)">
                            {{ tab.label }}
                            <span class="ml-1 opacity-70">({{ tab.count }})</span>
                        </button>
                    </div>
                    <FormGrid class-extra="sm:grid-cols-2 lg:grid-cols-4 items-end">
                        <FormField label="Category">
                            <select v-model="filterForm.class_category_id" @change="onCategoryChange" class="field">
                                <option :value="null">All categories</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Class">
                            <select v-model="filterForm.school_class_id" class="field">
                                <option :value="null">All classes</option>
                                <option v-for="c in filteredClasses" :key="c.id" :value="c.id">Class {{ c.name }}</option>
                            </select>
                        </FormField>
                        <FormField label="Status">
                            <select v-model="filterForm.status" class="field">
                                <option value="active">Active</option>
                                <option value="all">All statuses</option>
                                <option value="transferred">Transferred</option>
                                <option value="graduated">Graduated</option>
                                <option value="withdrawn">Withdrawn</option>
                            </select>
                        </FormField>
                        <div class="flex flex-wrap gap-2">
                            <button v-if="hasActiveFilters" type="button" @click="clearFilters" class="btn-ghost">Clear</button>
                        </div>
                    </FormGrid>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <FormField label="Search" class-extra="flex-1 max-w-md">
                            <input v-model="filterForm.search" type="search" placeholder="Name, reg no, email…"
                                   class="field">
                        </FormField>
                    </div>
                </div>
            </template>

            <tr v-for="student in students.data" :key="student.id" class="hover:bg-gray-50/80">
                <td v-if="summary.unverified > 0" class="px-4 py-3 w-10">
                    <input v-if="!student.is_verified" type="checkbox" class="rounded"
                           :checked="selectedIds.includes(student.id)"
                           @change="toggleSelect(student.id)">
                </td>
                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ student.reg_no || '—' }}</td>
                <td class="px-4 py-3 font-medium text-gray-900">{{ student.name }}</td>
                <td class="px-4 py-3 text-gray-600">{{ student.class_name || '—' }}</td>
                <td class="px-4 py-3 text-xs text-gray-500 capitalize">{{ formatGender(student.gender) }}</td>
                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ student.dob || '—' }}</td>
                <td class="px-4 py-3">
                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold"
                          :class="student.is_verified ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                        {{ student.is_verified ? 'Verified' : 'Pending' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                    <template v-if="student.is_verified">
                        {{ formatDate(student.verified_at) }}
                        <span v-if="student.verified_by" class="block text-gray-400">by {{ student.verified_by }}</span>
                    </template>
                    <span v-else>—</span>
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <button v-if="!student.is_verified" type="button"
                            class="link-brand text-xs mr-3 text-emerald-700"
                            @click="verifyOne(student)">
                        Verify
                    </button>
                    <Link :href="`/school-admin/${school.id}/students/${student.id}?edit=1`"
                          class="link-brand text-xs">Open record</Link>
                </td>
            </tr>
        </SahodayaDataTable>
    </SchoolAdminLayout>
</template>

<script setup>
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';

const props = defineProps({
    school:     Object,
    students:   Object,
    filters:    Object,
    categories: { type: Array, default: () => [] },
    classes:    { type: Array, default: () => [] },
    summary:    { type: Object, required: true },
    classStats: { type: Array, default: () => [] },
    filteredUnverifiedCount: { type: Number, default: 0 },
});

const base = computed(() => `/school-admin/${props.school.id}/students/verification-report`);
const bulkVerifyUrl = computed(() => `/school-admin/${props.school.id}/students/bulk-verify`);
const selectedIds = ref([]);
const bulkVerifyForm = useForm({});
const verifyOneForm = useForm({});

const pageUnverifiedStudents = computed(() =>
    (props.students.data ?? []).filter(s => !s.is_verified),
);

const allPageSelected = computed(() =>
    pageUnverifiedStudents.value.length > 0
    && pageUnverifiedStudents.value.every(s => selectedIds.value.includes(s.id)),
);

const somePageSelected = computed(() =>
    pageUnverifiedStudents.value.some(s => selectedIds.value.includes(s.id)),
);

const showVerifyFiltered = computed(() =>
    props.filteredUnverifiedCount > 0
    && props.filteredUnverifiedCount < props.summary.unverified,
);

const columns = computed(() => {
    const cols = [];
    if (props.summary.unverified > 0) {
        cols.push({ key: 'select', label: '', sortable: false, class: 'w-10' });
    }
    return [
        ...cols,
        { key: 'reg_no', label: 'Reg No', sortable: true },
        { key: 'name', label: 'Name', sortable: true },
        { key: 'class', label: 'Class', sortable: true },
        { key: 'gender', label: 'Gender', sortable: false },
        { key: 'dob', label: 'DOB', sortable: false },
        { key: 'verification', label: 'Status', sortable: false },
        { key: 'verified_at', label: 'Verified', sortable: true },
        { key: 'actions', label: '', sortable: false, align: 'right' },
    ];
});

const filterForm = reactive({
    class_category_id: props.filters?.class_category_id ?? null,
    school_class_id:   props.filters?.school_class_id ?? null,
    status:            props.filters?.status ?? 'active',
    verification:      props.filters?.verification ?? 'all',
    search:            props.filters?.search ?? '',
});

const schoolClasses = computed(() =>
    props.classes.filter(c => c.is_active !== false),
);

const filteredClasses = computed(() => {
    if (!filterForm.class_category_id) return schoolClasses.value;
    return schoolClasses.value.filter(c => Number(c.class_category_id) === Number(filterForm.class_category_id));
});

const hasActiveFilters = computed(() =>
    filterForm.class_category_id != null
    || filterForm.school_class_id != null
    || filterForm.status !== 'active'
    || filterForm.verification !== 'all'
    || !!filterForm.search
);

watch(() => props.filters, (f) => {
    if (!f) return;
    filterForm.class_category_id = f.class_category_id ?? null;
    filterForm.school_class_id   = f.school_class_id ?? null;
    filterForm.status            = f.status ?? 'active';
    filterForm.verification      = f.verification ?? 'all';
    filterForm.search            = f.search ?? '';
    selectedIds.value = [];
}, { deep: true });

const verificationTabs = computed(() => [
    { key: 'all', label: 'All', count: props.summary.total_active },
    { key: 'verified', label: 'Verified', count: props.summary.verified },
    { key: 'unverified', label: 'Unverified', count: props.summary.unverified },
]);

function bulkVerifyPayload(overrides = {}) {
    return {
        student_ids: [],
        verify_all_unverified: false,
        verify_filtered: false,
        class_category_id: filterForm.class_category_id,
        school_class_id: filterForm.school_class_id,
        status: filterForm.status,
        search: filterForm.search || null,
        ...overrides,
    };
}

function submitBulkVerify(payload, confirmMessage) {
    if (confirmMessage && !confirm(confirmMessage)) return;
    bulkVerifyForm.transform(() => payload).post(bulkVerifyUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            bulkVerifyForm.transform(() => ({}));
        },
    });
}

function verifyAllUnverified() {
    submitBulkVerify(
        bulkVerifyPayload({ verify_all_unverified: true }),
        `Verify all ${props.summary.unverified} unverified student(s)?`,
    );
}

function verifyFiltered() {
    submitBulkVerify(
        bulkVerifyPayload({ verify_filtered: true }),
        `Verify ${props.filteredUnverifiedCount} unverified student(s) matching current filters?`,
    );
}

function verifyClass(classId, count) {
    submitBulkVerify(
        bulkVerifyPayload({
            verify_filtered: true,
            school_class_id: classId,
            class_category_id: null,
            search: null,
            status: 'active',
        }),
        `Verify ${count} unverified student(s) in this class?`,
    );
}

function verifySelected() {
    submitBulkVerify(
        bulkVerifyPayload({ student_ids: [...selectedIds.value] }),
        `Verify ${selectedIds.value.length} selected student(s)?`,
    );
}

function verifyOne(student) {
    verifyOneForm.post(`/school-admin/${props.school.id}/students/${student.id}/verify`, {
        preserveScroll: true,
    });
}

function toggleSelect(id) {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) selectedIds.value.push(id);
    else selectedIds.value.splice(idx, 1);
}

function toggleSelectAllOnPage(event) {
    if (event.target.checked) {
        selectedIds.value = pageUnverifiedStudents.value.map(s => s.id);
    } else {
        selectedIds.value = [];
    }
}

function clearSelection() {
    selectedIds.value = [];
}

function listParams(overrides = {}) {
    return {
        class_category_id: props.filters?.class_category_id ?? null,
        school_class_id:   props.filters?.school_class_id ?? null,
        status:            props.filters?.status ?? 'active',
        verification:      props.filters?.verification ?? 'all',
        search:            props.filters?.search ?? '',
        sort:              props.filters?.sort ?? 'name',
        dir:               props.filters?.dir ?? 'asc',
        ...overrides,
    };
}

function applyFilters() {
    router.get(base.value, {
        class_category_id: filterForm.class_category_id,
        school_class_id:   filterForm.school_class_id,
        status:            filterForm.status,
        verification:      filterForm.verification,
        search:            filterForm.search,
        sort:              props.filters?.sort ?? 'name',
        dir:               props.filters?.dir ?? 'asc',
    }, { preserveState: true, preserveScroll: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function clearFilters() {
    filterForm.class_category_id = null;
    filterForm.school_class_id   = null;
    filterForm.status            = 'active';
    filterForm.verification      = 'all';
    filterForm.search            = '';
    router.get(base.value, listParams({
        class_category_id: null,
        school_class_id:   null,
        status:            'active',
        verification:      'all',
        search:            '',
    }), { preserveState: true, preserveScroll: true });
}

function setVerificationTab(key) {
    filterForm.verification = key;
    applyFilters();
}

function filterClassUnverified(classId) {
    filterForm.school_class_id = classId;
    filterForm.verification = 'unverified';
    filterForm.status = 'active';
    applyFilters();
}

function onCategoryChange() {
    const stillValid = filteredClasses.value.some(c => c.id === filterForm.school_class_id);
    if (!stillValid) filterForm.school_class_id = null;
}

function toggleSort(key) {
    const sortable = {
        name: 'name',
        reg_no: 'reg_no',
        class: 'class',
        verified_at: 'verified_at',
    };
    const sortKey = sortable[key];
    if (!sortKey) return;

    const nextDir = props.filters?.sort === sortKey && props.filters?.dir === 'asc' ? 'desc' : 'asc';
    router.get(base.value, listParams({
        class_category_id: filterForm.class_category_id,
        school_class_id:   filterForm.school_class_id,
        status:            filterForm.status,
        verification:      filterForm.verification,
        search:            filterForm.search,
        sort: sortKey,
        dir:  nextDir,
    }), { preserveState: true, preserveScroll: true });
}

function exportUrl(verification) {
    const params = new URLSearchParams();
    params.set('verification', verification);
    if (filterForm.class_category_id != null) params.set('class_category_id', filterForm.class_category_id);
    if (filterForm.school_class_id != null) params.set('school_class_id', filterForm.school_class_id);
    if (filterForm.status) params.set('status', filterForm.status);
    if (filterForm.search) params.set('search', filterForm.search);
    return `${base.value}/export?${params.toString()}`;
}

function formatGender(gender) {
    if (!gender) return '—';
    return gender.charAt(0).toUpperCase() + gender.slice(1);
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>
