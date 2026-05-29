<template>
    <SahodayaAdminLayout title="Dashboard" :sahodaya="sahodaya">
        <div class="space-y-6">
            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <p class="text-2xl font-bold text-gray-800">{{ stats.member_schools }}</p>
                    <p class="text-xs text-gray-500 mt-1">Member Schools</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <p class="text-2xl font-bold text-gray-800">{{ stats.office_bearers }}</p>
                    <p class="text-xs text-gray-500 mt-1">Office Bearers</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <p class="text-2xl font-bold text-gray-800">{{ stats.circulars }}</p>
                    <p class="text-xs text-gray-500 mt-1">Circulars</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <p class="text-2xl font-bold text-gray-800">{{ stats.kalotsav_events }}</p>
                    <p class="text-xs text-gray-500 mt-1">Kalotsav Events</p>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <!-- Active Kalotsav -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="font-bold text-gray-800 mb-4">Active Kalotsav Event</h3>
                    <div v-if="activeKalotsav" class="space-y-2">
                        <p class="font-semibold text-purple-700">{{ activeKalotsav.name }}</p>
                        <p class="text-sm text-gray-500">{{ activeKalotsav.academic_year }} · {{ activeKalotsav.venue }}</p>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/kalotsav/${activeKalotsav.id}`"
                              class="inline-block mt-2 text-xs bg-purple-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-purple-700 transition">
                            Manage Event →
                        </Link>
                    </div>
                    <p v-else class="text-sm text-gray-400">No active Kalotsav event.</p>
                </div>

                <!-- Recent Circulars -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-800">Recent Circulars</h3>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/circulars`"
                              class="text-xs text-purple-600 hover:underline">View all</Link>
                    </div>
                    <div v-if="recentCirculars.length" class="space-y-2">
                        <div v-for="c in recentCirculars" :key="c.id"
                             class="flex items-start gap-3 py-2 border-b border-gray-50 last:border-0">
                            <span class="text-gray-300 mt-0.5">📄</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-700 font-medium truncate">{{ c.title }}</p>
                                <p class="text-xs text-gray-400">{{ c.category }} · {{ c.issued_date }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">No circulars yet.</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-bold text-gray-800 mb-4">Quick Actions</h3>
                <div class="flex flex-wrap gap-3">
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/circulars`"
                          class="bg-purple-50 text-purple-700 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-purple-100 transition">
                        Upload Circular
                    </Link>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/kalotsav`"
                          class="bg-purple-50 text-purple-700 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-purple-100 transition">
                        Kalotsav Events
                    </Link>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/office-bearers`"
                          class="bg-purple-50 text-purple-700 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-purple-100 transition">
                        Office Bearers
                    </Link>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/schools`"
                          class="bg-purple-50 text-purple-700 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-purple-100 transition">
                        Member Schools
                    </Link>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    sahodaya:        Object,
    stats:           { type: Object, default: () => ({}) },
    recentCirculars: { type: Array, default: () => [] },
    activeKalotsav:  { type: Object, default: null },
});
</script>
