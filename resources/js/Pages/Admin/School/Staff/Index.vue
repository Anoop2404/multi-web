<template>
    <SchoolAdminLayout title="Staff Members" :school="school" :show-header-title="false">
        <PageHeader title="Staff Members" eyebrow="Website"
            description="Search and manage the staff directory displayed on your school website.">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/staff/create`" class="btn-primary">+ Add Staff</Link>
            </template>
        </PageHeader>


        <div class="space-y-4">
            <div class="card space-y-4">
                <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="applyFilters">
                    <label class="form-label min-w-0 flex-1">Search
                        <input v-model="filterForm.search" type="search" class="field mt-1" placeholder="Name, designation, department or qualification">
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary text-sm">Search</button>
                        <button v-if="hasFilters" type="button" class="btn-ghost text-sm" @click="clearFilters">Clear</button>
                    </div>
                </form>
                <div class="flex flex-wrap gap-2">
                    <button v-for="t in ['all','teaching','non-teaching','admin']" :key="t"
                            type="button"
                            @click="selectType(t)"
                            :aria-pressed="filterForm.type === t"
                            class="chip-tab"
                            :class="{ 'chip-tab--active': filterForm.type === t }">
                        {{ t === 'all' ? 'All' : t.charAt(0).toUpperCase() + t.slice(1) }}
                    </button>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="member in staff.data" :key="member.id"
                     class="card-list-row flex-wrap hover:shadow-md sm:flex-nowrap">
                    <div class="w-14 h-14 rounded-full overflow-hidden bg-gray-100 shrink-0">
                        <img v-if="member.photo_url" :src="member.photo_url" :alt="member.name" class="w-full h-full object-cover">
                        <div v-else class="w-full h-full flex items-center justify-center bg-blue-100 text-blue-700 font-bold text-lg">
                            {{ member.name.charAt(0).toUpperCase() }}
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-gray-800 truncate">{{ member.name }}</p>
                        <p class="text-xs text-gray-500">{{ member.designation }}</p>
                        <p class="text-xs text-gray-400">{{ member.department }}</p>
                    </div>
                    <div class="flex w-full shrink-0 justify-end gap-2 border-t border-slate-100 pt-3 sm:w-auto sm:flex-col sm:border-0 sm:pt-0">
                        <Link :href="`/school-admin/${school.id}/staff/${member.id}/edit`"
                              class="btn-ghost text-xs">Edit</Link>
                        <button type="button" @click="remove(member)" class="btn-ghost text-xs text-red-600">Remove</button>
                    </div>
                </div>

                <div v-if="!staff.data.length" class="col-span-full bg-white rounded-xl border border-dashed border-gray-200 p-10 text-center">
                    <div class="text-3xl" aria-hidden="true">🧑‍🏫</div>
                    <p class="mt-2 font-semibold text-gray-600">No staff members found</p>
                    <p class="mt-1 text-xs text-gray-400">Try clearing the search or choosing another staff type.</p>
                </div>
            </div>

            <div v-if="staff.total" class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <PaginationLinks :links="staff.links" :meta="staff" />
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useConfirm } from '@/composables/useConfirm';
import PaginationLinks from '@/Components/ui/PaginationLinks.vue';

const { confirm } = useConfirm();

const props = defineProps({
    school: Object,
    staff: Object,
    filters: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
    search: props.filters.search ?? '',
    type: props.filters.type || 'all',
});
const hasFilters = computed(() => Boolean(filterForm.search || (filterForm.type && filterForm.type !== 'all')));
const base = `/school-admin/${props.school.id}/staff`;

function applyFilters() {
    router.get(base, {
        search: filterForm.search || undefined,
        type: filterForm.type === 'all' ? undefined : filterForm.type,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function selectType(type) {
    filterForm.type = type;
    applyFilters();
}

function clearFilters() {
    filterForm.search = '';
    filterForm.type = 'all';
    applyFilters();
}

async function remove(member) {
    if (!(await confirm({ message: `Remove "${member.name}"?`, destructive: true }))) return;
    router.delete(`/school-admin/${props.school.id}/staff/${member.id}`);
}
</script>
