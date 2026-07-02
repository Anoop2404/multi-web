<template>
    <SchoolAdminLayout title="Staff Members" :school="school" :show-header-title="false">
        <PageHeader title="Staff Members" eyebrow="Website"
            description="School website content and public pages." />


        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex gap-2">
                    <button v-for="t in ['all','teaching','non-teaching','admin']" :key="t"
                            @click="typeFilter = t"
                            class="chip-tab"
                            :class="{ 'chip-tab--active': typeFilter === t }">
                        {{ t === 'all' ? 'All' : t.charAt(0).toUpperCase() + t.slice(1) }}
                    </button>
                </div>
                <Link :href="`/school-admin/${school.id}/staff/create`"
                      class="btn-primary">
                    + Add Staff
                </Link>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="member in filtered" :key="member.id"
                     class="card-list-row hover:shadow-md">
                    <div class="w-14 h-14 rounded-full overflow-hidden bg-gray-100 shrink-0">
                        <img v-if="member.photo" :src="member.photo" class="w-full h-full object-cover">
                        <div v-else class="w-full h-full flex items-center justify-center bg-blue-100 text-blue-700 font-bold text-lg">
                            {{ member.name.charAt(0).toUpperCase() }}
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-gray-800 truncate">{{ member.name }}</p>
                        <p class="text-xs text-gray-500">{{ member.designation }}</p>
                        <p class="text-xs text-gray-400">{{ member.department }}</p>
                    </div>
                    <div class="flex flex-col gap-1 shrink-0">
                        <Link :href="`/school-admin/${school.id}/staff/${member.id}/edit`"
                              class="text-xs text-blue-600 hover:underline">Edit</Link>
                        <button @click="remove(member)" class="text-xs text-red-400 hover:underline">Remove</button>
                    </div>
                </div>

                <div v-if="!filtered.length" class="col-span-full bg-white rounded-xl border border-dashed border-gray-200 p-10 text-center text-gray-400">
                    No staff members found.
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    school: Object,
    staff:  { type: Array, default: () => [] },
});

const typeFilter = ref('all');
const filtered = computed(() =>
    typeFilter.value === 'all'
        ? props.staff
        : props.staff.filter(m => m.type === typeFilter.value)
);

function remove(member) {
    if (!confirm(`Remove "${member.name}"?`)) return;
    router.delete(`/school-admin/${props.school.id}/staff/${member.id}`);
}
</script>
