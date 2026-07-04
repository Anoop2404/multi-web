<template>
    <PortalLayout title="My event registrations" :school="school">
        <PageHeader title="Fest & sports registrations" eyebrow="Student portal" :description="`Registrations for ${student.name}`" />

        <div v-for="ev in events" :key="ev.id" class="card mb-4 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="font-semibold text-slate-900">{{ ev.title }}</h3>
                    <p class="text-xs text-slate-500 capitalize">{{ ev.event_type?.replace('_', ' ') }}</p>
                </div>
                <button v-if="!ev.registered && ev.registration_open" type="button" class="btn-primary text-sm"
                        @click="registerEvent(ev.id)">
                    Register for event
                </button>
                <span v-else-if="ev.registered" class="text-xs font-medium text-emerald-700">Registered for event</span>
            </div>

            <div v-if="ev.items?.length" class="border-t border-slate-100 pt-3">
                <p class="text-xs font-semibold text-slate-500 mb-2">My items</p>
                <ul class="text-sm space-y-1">
                    <li v-for="item in ev.items" :key="item.id" class="flex justify-between gap-2">
                        <span>{{ item.item_title }}</span>
                        <span class="text-slate-500">{{ item.status }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <EmptyState v-if="!events.length" title="No self-registration events" description="Your Sahodaya has not enabled student self-registration for open events." />
    </PortalLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    school: Object,
    student: Object,
    events: { type: Array, default: () => [] },
});

function registerEvent(eventId) {
    router.post(`/portal/student/${props.school.id}/fest/${eventId}/register`, {}, { preserveScroll: true });
}
</script>
