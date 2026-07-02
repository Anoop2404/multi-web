<template>
    <SchoolAdminLayout title="Events" :school="school" :show-header-title="false">
        <PageHeader title="Events" eyebrow="Programs"
            description="Fest programs, exams, training, and Sahodaya circulars." />


        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">{{ events.total }} events</p>
                <Link :href="`/school-admin/${school.id}/events/create`"
                      class="btn-primary px-4 py-2 rounded-lg text-sm font-semibold transition">
                    + New Event
                </Link>
            </div>

            <div class="card card--flush">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Title</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Date</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Venue</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="event in events.data" :key="event.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-medium text-gray-800">{{ event.title }}</td>
                            <td class="px-5 py-3 text-gray-500 text-xs">
                                {{ new Date(event.start_date).toLocaleDateString('en-IN') }}
                            </td>
                            <td class="px-5 py-3 text-gray-400">{{ event.venue || '—' }}</td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <Link :href="`/school-admin/${school.id}/events/${event.id}/edit`"
                                      class="text-xs text-blue-600 hover:underline">Edit</Link>
                                <button @click="destroy(event)"
                                        class="text-xs text-red-400 hover:underline">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!events.data.length">
                            <td colspan="4" class="px-5 py-10 text-center text-gray-400">
                                No events yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({ school: Object, events: Object });

function destroy(event) {
    if (!confirm(`Delete "${event.title}"?`)) return;
    router.delete(`/school-admin/${props.school.id}/events/${event.id}`);
}
</script>
