<template>
    <SchoolAdminLayout title="School Events" :school="school" :show-header-title="false">
        <PageHeader title="School Events" eyebrow="Programs"
            description="Fest programs, exams, training, and Sahodaya circulars." />


        <div class="space-y-6">
            <p class="text-sm text-gray-600">
                Create custom school-level events hosted at your school. Cluster-wide Sahodaya events are managed separately under Programs.
            </p>
            <div class="rounded-xl border border-violet-200 bg-violet-50 p-4 text-sm text-violet-900">
                School events support registration and participation policy.
                <strong>Sports school rounds</strong> also use marks, auto-ranking, and winner submission under
                <strong>Sports → My school events</strong>.
                Other programs use the generic school event workspace.
            </div>

            <form @submit.prevent="createEvent" class="card space-y-3">
                <h3 class="font-semibold">New school event</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    <input v-model="form.title" class="field" placeholder="Event title" required>
                    <select v-model="form.event_type" class="field">
                        <option v-for="(label, key) in eventTypes" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <button class="btn-primary">Create</button>
            </form>

            <div class="card card--flush">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="p-3">Title</th>
                            <th class="p-3">Type</th>
                            <th class="p-3">Status</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="event in events" :key="event.id" class="border-t">
                            <td class="p-3 font-medium">{{ event.title }}</td>
                            <td class="p-3">{{ eventTypes[event.event_type] ?? event.event_type }}</td>
                            <td class="p-3">{{ event.status }}</td>
                            <td class="p-3 text-right">
                                <Link :href="eventManageUrl(event)" class="text-indigo-600">Open →</Link>
                            </td>
                        </tr>
                        <tr v-if="!events.length">
                            <td colspan="4" class="p-6 text-center text-gray-400">No school events yet</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({
    school: Object,
    events: Array,
    eventTypes: Object,
});

const form = useForm({ title: '', event_type: 'kalolsavam' });

function eventManageUrl(event) {
    if (event.event_type === 'sports') {
        return `/school-admin/${props.school.id}/sports/my-event/${event.id}`;
    }
    return `/school-admin/${props.school.id}/fest-programs/${event.id}`;
}

function createEvent() {
    form.post(`/school-admin/${props.school.id}/fest-programs`, { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

