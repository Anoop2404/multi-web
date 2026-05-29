<template>
    <SahodayaAdminLayout title="Member Schools" :sahodaya="sahodaya">
        <div class="space-y-4">
            <!-- Summary row -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-6 py-4 flex items-center gap-4">
                <p class="text-2xl font-bold text-gray-800">{{ schools.length }}</p>
                <p class="text-sm text-gray-500">member schools in this Sahodaya cluster</p>
            </div>

            <!-- Schools grid -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="school in schools" :key="school.id"
                     class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-gray-800 truncate">{{ school.name }}</h4>
                            <p class="text-xs text-gray-400 font-mono truncate">{{ school.domain }}</p>
                        </div>
                        <span :class="school.is_active !== false ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-400'"
                              class="text-xs px-2 py-0.5 rounded-full font-medium shrink-0">
                            {{ school.is_active !== false ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <!-- Stats chips -->
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full font-medium">
                            {{ school.news_count }} news
                        </span>
                        <span class="bg-green-50 text-green-700 px-2 py-0.5 rounded-full font-medium">
                            {{ school.events_count }} events
                        </span>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-3 pt-1 border-t border-gray-50 text-xs">
                        <a :href="`//${school.domain}`" target="_blank"
                           class="text-blue-500 hover:underline">Visit site →</a>
                        <Link :href="`/school-admin/${school.id}`"
                              class="text-purple-500 hover:underline">Admin panel →</Link>
                    </div>
                </div>

                <div v-if="!schools.length"
                     class="sm:col-span-2 lg:col-span-3 bg-white rounded-xl border border-dashed border-gray-200 p-12 text-center text-gray-400">
                    No member schools found under this Sahodaya cluster.
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    sahodaya: Object,
    schools:  { type: Array, default: () => [] },
});
</script>
