<template>
    <SahodayaEventsLayout :title="pageTitle" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :program="program"
                         :show-header-title="false">
        <PageHeader
            :title="pageTitle"
            eyebrow="Assign to event"
            description="Import enabled master catalog items into a fest event. Disabled items are skipped."
        >
            <template #actions>
                <Link :href="`${catalogBase}/master${eventQuery}`" class="btn-secondary text-xs">Master setup</Link>
            </template>
        </PageHeader>

        <CatalogSubNav :sahodaya-id="sahodaya.id" :program-slug="program.slug" active="assign" />

        <div class="grid lg:grid-cols-2 gap-8 items-start">
            <FormSection title="Import items" :hint="`${summary.enabled} enabled items in master catalog.`">
                <form @submit.prevent="importToEvent" class="space-y-4 max-w-md">
                    <label class="block">
                        <span class="field-label">Fest event</span>
                        <select v-model="importForm.event_id" class="field mt-1" required>
                            <option value="">Choose event</option>
                            <option v-for="ev in events" :key="ev.id" :value="ev.id">
                                {{ ev.title }} ({{ ev.items_count ?? 0 }} items)
                            </option>
                        </select>
                    </label>
                    <label v-if="sections.length" class="block">
                        <span class="field-label">Catalog section</span>
                        <select v-model="importForm.catalog_section" class="field mt-1">
                            <option value="all">All enabled items ({{ summary.enabled }})</option>
                            <option v-for="sec in sections" :key="sec.slug" :value="sec.slug">
                                {{ sec.label }} ({{ sec.enabled }} enabled)
                            </option>
                        </select>
                    </label>
                    <button type="submit" class="btn-primary" :disabled="importForm.processing || !importForm.event_id">
                        Import into event
                    </button>
                </form>
            </FormSection>

            <FormSection title="Events in program" hint="Open an event to manage its item list after import.">
                <EmptyState v-if="!events.length" title="No events yet" description="Create a fest event first, then return here to import catalog items." icon="📅" />
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="ev in events" :key="ev.id" class="py-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium text-slate-900">{{ ev.title }}</p>
                            <p class="text-xs text-slate-500">{{ ev.items_count ?? 0 }} items · {{ ev.status }}</p>
                        </div>
                        <Link :href="eventUrl(ev.id)" class="text-xs text-[color:var(--brand-blue)] hover:underline shrink-0">Open event</Link>
                    </li>
                </ul>
            </FormSection>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import CatalogSubNav from '@/Components/sahodaya/CatalogSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    sections: { type: Array, default: () => [] },
    summary: Object,
    events: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const page = usePage();

const eventQuery = computed(() => (page.props.event?.id ? `?event_id=${page.props.event.id}` : ''));

const catalogBase = `/sahodaya-admin/${props.sahodaya.id}/programs/${props.program.slug}/catalog`;
const pageTitle = computed(() => `${props.program.label} — Assign to event`);

const importForm = useForm({
    event_id: page.props.event?.id ?? '',
    catalog_section: 'all',
});

function eventUrl(eventId) {
    return `/sahodaya-admin/${props.sahodaya.id}/events/${eventId}`;
}

function importToEvent() {
    router.post(`${catalogBase}/import/${importForm.event_id}${eventQuery.value}`, {
        catalog_section: importForm.catalog_section === 'all' ? null : importForm.catalog_section,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            if (!page.props.event?.id) {
                importForm.reset('event_id');
            }
        },
    });
}
</script>
