<template>
    <SahodayaEventsLayout :title="pageTitle" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :program="program"
                         :show-header-title="false">
        <PageHeader
            :title="pageTitle"
            eyebrow="Master catalog"
            description="Set up items once, browse the list, then assign enabled items into fest events."
        />

        <CatalogSubNav :sahodaya-id="sahodaya.id" :program-slug="program.slug" active="hub" />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ summary.total }}</p>
                <p class="text-xs text-slate-500 mt-1">Total items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ summary.enabled }}</p>
                <p class="text-xs text-slate-500 mt-1">Enabled</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ summary.cksc }}</p>
                <p class="text-xs text-slate-500 mt-1">CKSC seed</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ summary.custom }}</p>
                <p class="text-xs text-slate-500 mt-1">Custom</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
            <Link :href="`${catalogBase}/master${eventQuery}`" class="card hover:border-[color:var(--brand-blue)]/40 transition group !py-5">
                <p class="font-semibold text-slate-900 group-hover:text-[color:var(--brand-blue)]">Items & fees</p>
                <p class="text-sm text-slate-500 mt-1">Enable/disable items, set fees, add custom entries — common across all years.</p>
            </Link>
            <Link :href="`${catalogBase}/list${eventQuery}`" class="card hover:border-[color:var(--brand-blue)]/40 transition group !py-5">
                <p class="font-semibold text-slate-900 group-hover:text-[color:var(--brand-blue)]">Item listing</p>
                <p class="text-sm text-slate-500 mt-1">Read-only browse by section — share or verify the catalog.</p>
            </Link>
            <Link :href="`${catalogBase}/assign${eventQuery}`" class="card hover:border-[color:var(--brand-blue)]/40 transition group !py-5">
                <p class="font-semibold text-slate-900 group-hover:text-[color:var(--brand-blue)]">Assign to event</p>
                <p class="text-sm text-slate-500 mt-1">Import enabled master items into a fest event.</p>
            </Link>
        </div>

        <div v-if="sections.length" class="space-y-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Sections</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <Link v-for="sec in sections" :key="sec.slug"
                      :href="`${catalogBase}/master/${sec.slug}${eventQuery}`"
                      class="card hover:border-[color:var(--brand-blue)]/40 transition group !py-5">
                    <p class="font-semibold text-slate-900 group-hover:text-[color:var(--brand-blue)]">{{ sec.label }}</p>
                    <p class="text-sm text-slate-500 mt-1">{{ sec.description }}</p>
                    <p class="text-xs text-slate-400 mt-3">{{ sec.enabled }} enabled · {{ sec.total }} total</p>
                    <p class="text-xs text-[color:var(--brand-blue)] mt-2 opacity-0 group-hover:opacity-100">Open in master setup →</p>
                </Link>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
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
    activityLogs: { type: Array, default: () => [] },
});

const page = usePage();

const eventQuery = computed(() => (page.props.event?.id ? `?event_id=${page.props.event.id}` : ''));

const catalogBase = `/sahodaya-admin/${props.sahodaya.id}/programs/${props.program.slug}/catalog`;
const pageTitle = computed(() => `${props.program.label} catalog`);
</script>
