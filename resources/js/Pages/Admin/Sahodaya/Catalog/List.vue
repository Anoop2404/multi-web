<template>
    <SahodayaEventsLayout :title="pageTitle" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :program="program"
                         :show-header-title="false">
        <PageHeader
            :title="pageTitle"
            eyebrow="Item listing"
            :description="section?.description ?? 'Read-only view of the year-independent master catalog.'"
        >
            <template #actions>
                <Link :href="masterUrl" class="btn-primary text-xs">Edit items & fees</Link>
            </template>
        </PageHeader>

        <CatalogSubNav :sahodaya-id="sahodaya.id" :program-slug="program.slug" active="list" />

        <div class="card card--muted !p-4 mb-4">
            <CatalogSectionNav :base="catalogBase" mode="list" :sections="sections" :section="section" />
        </div>

        <form @submit.prevent="applyFilters" class="flex flex-wrap gap-3 items-end mb-4">
            <input v-model="filterForm.q" class="field flex-1 min-w-[12rem]" placeholder="Search name">
            <select v-model="filterForm.enabled" class="field w-40">
                <option value="">All statuses</option>
                <option value="1">Enabled only</option>
                <option value="0">Disabled only</option>
            </select>
            <button type="submit" class="btn-primary text-sm">Apply</button>
            <button type="button" class="btn-secondary text-sm" @click="clearFilters">Clear</button>
        </form>

        <p class="text-sm text-slate-500 mb-3">{{ flatItems.length }} items shown</p>

        <div class="form-section overflow-hidden !p-0">
            <EmptyState v-if="!flatItems.length" title="No items" description="Try another section or adjust filters." icon="📋" class="p-8" />
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-16 text-center">Type</th>
                            <th>Item</th>
                            <th class="w-24">Status</th>
                            <th class="w-24 text-right">Fee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in flatItems" :key="item.id">
                            <td class="text-center">
                                <FestItemMetaIcons :gender="item.gender" :participant-type="item.participant_type" />
                            </td>
                            <td>
                                <p :class="item.is_enabled ? 'font-medium text-slate-900' : 'text-slate-400 line-through'">{{ item.title }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">{{ itemTags(item) }}</p>
                            </td>
                            <td>
                                <span :class="item.is_enabled ? 'text-emerald-700 text-xs font-medium' : 'text-slate-400 text-xs'">
                                    {{ item.is_enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="text-right text-sm text-slate-600">
                                <span v-if="item.fee_enabled && item.fee_amount != null">₹{{ item.fee_amount }}</span>
                                <span v-else class="text-slate-300">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import CatalogSubNav from '@/Components/sahodaya/CatalogSubNav.vue';
import CatalogSectionNav from '@/Components/sahodaya/CatalogSectionNav.vue';
import FestItemMetaIcons from '@/Components/sahodaya/FestItemMetaIcons.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    section: Object,
    sections: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    filters: Object,
    taxonomy: Object,
    ageGroupLabels: Object,
    groupedItems: Object,
    activityLogs: { type: Array, default: () => [] },
});

const catalogBase = `/sahodaya-admin/${props.sahodaya.id}/programs/${props.program.slug}/catalog`;
const masterUrl = computed(() => {
    if (!props.section?.slug || props.section.slug === 'all') return `${catalogBase}/master`;
    return `${catalogBase}/master/${props.section.slug}`;
});
const pageBase = computed(() => `${catalogBase}/list${props.section?.slug && props.section.slug !== 'all' ? `/${props.section.slug}` : ''}`);
const isSports = computed(() => props.program.eventType === 'sports');

const pageTitle = computed(() =>
    `${props.program.label} — ${props.section?.label ?? 'Listing'}`,
);

const flatItems = computed(() => {
    if (!props.groupedItems) return props.items;
    return Object.values(props.groupedItems).flat();
});

const filterForm = useForm({
    q: props.filters?.q ?? '',
    enabled: props.filters?.enabled ?? '',
});

function itemTags(item) {
    const parts = [];
    if (item.item_code) parts.push(item.item_code);
    if (isSports.value && item.age_group) parts.push(props.ageGroupLabels?.[item.age_group] ?? item.age_group);
    else if (item.kids_band) parts.push(props.taxonomy?.kids_band?.[item.kids_band] ?? item.kids_band);
    else if (item.class_group && item.class_group !== 'open') parts.push(props.taxonomy?.class_group?.[item.class_group] ?? item.class_group);
    return parts.join(' · ');
}

function applyFilters() {
    filterForm.get(pageBase.value, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    filterForm.reset();
    router.get(pageBase.value, {}, { preserveState: true, preserveScroll: true });
}
</script>
