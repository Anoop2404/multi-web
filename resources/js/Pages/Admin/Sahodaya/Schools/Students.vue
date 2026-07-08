<template>
    <SahodayaAdminLayout :title="`${school.name} — Students`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-5">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}`"
                  class="inline-flex items-center gap-1 text-xs font-semibold text-[#0f3d7a] hover:text-[#041525]">
                ← Back to {{ school.name }}
            </Link>

            <!-- School summary -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-extrabold text-gray-900">{{ school.name }}</h2>
                    <p v-if="school.school_prefix" class="text-xs font-mono text-gray-400 mt-0.5">{{ school.school_prefix }}</p>
                </div>
                <div class="flex flex-wrap gap-4 text-sm">
                    <div class="text-center">
                        <p class="text-2xl font-extrabold text-[#0f3d7a]">{{ totalStudents.toLocaleString('en-IN') }}</p>
                        <p class="text-xs text-gray-400">Active students</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-extrabold text-emerald-700">{{ verifiedCount }}</p>
                        <p class="text-xs text-gray-400">Verified</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-extrabold text-amber-700">{{ unverifiedCount }}</p>
                        <p class="text-xs text-gray-400">Pending verification</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-extrabold text-green-700">{{ classesCount }}</p>
                        <p class="text-xs text-gray-400">Classes set up</p>
                    </div>
                </div>
            </div>

            <!-- By category -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-900">Students by Class Category</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Counts from enrolled students against the class master.</p>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 p-5">
                    <button v-for="cat in categories" :key="cat.id"
                            @click="filterByCategory(cat.id)"
                            class="text-left p-3 rounded-xl border transition"
                            :class="filters.class_category_id === cat.id
                                ? 'border-[#bfdbfe] bg-[#eff6ff] ring-1 ring-[#93c5fd]'
                                : 'border-gray-100 hover:border-[#dbeafe] hover:bg-gray-50'">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-mono text-[10px] text-[#0f3d7a] bg-white border border-[#bfdbfe] px-1.5 py-0.5 rounded">{{ cat.code }}</span>
                            <span class="text-sm font-semibold text-gray-800 truncate">{{ cat.label }}</span>
                        </div>
                        <p class="text-xl font-extrabold text-[#0f3d7a]">{{ cat.student_count.toLocaleString('en-IN') }}</p>
                    </button>
                </div>
            </div>

            <!-- Filters + table -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center gap-3">
                    <select v-model="filterForm.verification"
                            class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                        <option value="all">All verification</option>
                        <option value="verified">Verified</option>
                        <option value="unverified">Pending verification</option>
                    </select>
                    <select v-model="filterForm.class_category_id"
                            class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                        <option :value="null">All categories</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.label }}</option>
                    </select>
                    <select v-model="filterForm.school_class_id"
                            class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                        <option :value="null">All classes</option>
                        <option v-for="c in filteredClasses" :key="c.id" :value="c.id">Class {{ c.name }}</option>
                    </select>
                    <input v-model="filterForm.search" type="search" placeholder="Search name, admission no..."
                           class="border border-gray-200 rounded-lg px-3 py-2 text-sm w-48">
                    <button v-if="unverifiedCount > 0" type="button"
                            @click="bulkVerifySchool"
                            class="btn-secondary text-sm py-2 px-3">
                        Verify all pending ({{ unverifiedCount }})
                    </button>
                    <button v-if="hasFilters" @click="clearFilters" class="text-sm text-gray-400 hover:underline">Clear</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Admission No.</th>
                                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Name</th>
                                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Category</th>
                                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Class</th>
                                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Verified</th>
                                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Status</th>
                                <th class="px-4 py-2.5 w-24"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="s in students.data" :key="s.id" class="hover:bg-gray-50/50">
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ s.admission_number }}</td>
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <Link :href="`/sahodaya-admin/${sahodaya.id}/students/${s.id}`"
                                          class="text-[#0f3d7a] hover:underline">
                                        {{ s.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ s.school_class?.class_category?.label || '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ s.school_class?.name || '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                          :class="s.verified_at ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800'">
                                        {{ s.verified_at ? 'Verified' : 'Pending' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-xs px-2 py-0.5 rounded-full capitalize font-medium"
                                          :class="s.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                                        {{ s.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <Link :href="`/sahodaya-admin/${sahodaya.id}/students/${s.id}`"
                                          class="text-xs font-semibold text-[#0f3d7a] hover:underline mr-3">
                                        Profile
                                    </Link>
                                    <button v-if="!s.verified_at" type="button"
                                            @click="verifyStudent(s.id)"
                                            class="text-xs font-semibold text-emerald-700 hover:text-emerald-900">
                                        Verify
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!students.data?.length">
                                <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-sm">
                                    No students enrolled yet. School admin adds students from the Students page.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="students.links?.length > 3" class="flex justify-center gap-1 py-4 border-t border-gray-50">
                    <Link v-for="link in students.links" :key="link.label"
                          :href="link.url || '#'"
                          class="px-3 py-1 rounded text-sm"
                          :class="link.active ? 'pagination-link--active' : 'pagination-link'"
                          v-html="link.label" />
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';

const props = defineProps({
    sahodaya:                Object,
    publicUrl:               { type: String, default: null },
    pendingSchoolsCount:     { type: Number, default: 0 },
    pendingSubmissionsCount: { type: Number, default: 0 },
    pendingPaymentsCount:    { type: Number, default: 0 },
    school:                  Object,
    categories:              { type: Array, default: () => [] },
    classes:                 { type: Array, default: () => [] },
    students:                Object,
    filters:                 { type: Object, default: () => ({}) },
    totalStudents:           { type: Number, default: 0 },
    verifiedCount:           { type: Number, default: 0 },
    unverifiedCount:         { type: Number, default: 0 },
    classesCount:            { type: Number, default: 0 },
});

const filterForm = reactive({
    class_category_id: props.filters?.class_category_id ?? null,
    school_class_id:   props.filters?.school_class_id ?? null,
    search:            props.filters?.search ?? '',
    verification:      props.filters?.verification ?? 'all',
});

const filteredClasses = computed(() => {
    if (!filterForm.class_category_id) return props.classes;
    return props.classes.filter(c => c.class_category_id === filterForm.class_category_id);
});

const hasFilters = computed(() =>
    filterForm.class_category_id || filterForm.school_class_id || filterForm.search
    || (filterForm.verification && filterForm.verification !== 'all')
);

function onCategoryChange() {
    const valid = filteredClasses.value.some(c => c.id === filterForm.school_class_id);
    if (!valid) filterForm.school_class_id = null;
}

watch(() => filterForm.class_category_id, onCategoryChange);

function applyFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}/students`, { ...filterForm }, { preserveState: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function filterByCategory(id) {
    filterForm.class_category_id = filterForm.class_category_id === id ? null : id;
    onCategoryChange();
}

function clearFilters() {
    filterForm.class_category_id = null;
    filterForm.school_class_id = null;
    filterForm.search = '';
    filterForm.verification = 'all';
    applyFilters();
}

function verifyStudent(studentId) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/students/${studentId}/verify`, {}, { preserveScroll: true });
}

function bulkVerifySchool() {
    if (!confirm(`Verify all ${props.unverifiedCount} pending student(s) at ${props.school.name}?`)) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/students/verification/bulk-verify`, {
        verify_all_unverified: true,
        school_id: props.school.id,
    }, { preserveScroll: true });
}
</script>
