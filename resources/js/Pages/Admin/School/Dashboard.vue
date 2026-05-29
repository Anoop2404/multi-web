<template>
    <SchoolAdminLayout title="Dashboard" :school="school">
        <div class="space-y-6">
            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div v-for="stat in stats" :key="stat.label"
                     class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">{{ stat.label }}</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ stat.value }}</p>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                <h3 class="font-bold text-gray-800 mb-4">Quick Actions</h3>
                <div class="flex flex-wrap gap-3">
                    <Link :href="`/school-admin/${school.id}/news/create`"
                          class="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
                        + New Article
                    </Link>
                    <Link :href="`/school-admin/${school.id}/events/create`"
                          class="px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition">
                        + New Event
                    </Link>
                    <Link :href="`/school-admin/${school.id}/downloads`"
                          class="px-4 py-2.5 bg-purple-600 text-white rounded-lg text-sm font-semibold hover:bg-purple-700 transition">
                        Upload File
                    </Link>
                    <Link :href="`/school-admin/${school.id}/enquiries`"
                          class="px-4 py-2.5 border border-gray-200 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 transition">
                        View Enquiries
                    </Link>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    school: Object,
    stats:  { type: Array, default: () => [] },
});
</script>
