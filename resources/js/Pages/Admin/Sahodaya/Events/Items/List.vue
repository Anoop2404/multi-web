<template>
    <SahodayaEventsLayout :title="`${event.title} — Item listing`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Item listing`" eyebrow="Read-only"
                    description="Full item details as configured in items setup.">
            <template #actions>
                <Link :href="`${base}/items`" class="btn-secondary text-xs">Edit in items setup</Link>
            </template>
        </PageHeader>

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="items-list" />

        <div class="flex flex-wrap gap-3 items-center mb-4">
            <input v-model="searchQuery" type="search" class="field flex-1 min-w-[12rem] max-w-md"
                   placeholder="Search by name, code, venue, discipline…" autocomplete="off">
            <button v-if="searchQuery.trim()" type="button" class="btn-secondary text-sm" @click="searchQuery = ''">
                Clear
            </button>
        </div>

        <p class="text-sm text-slate-500 mb-4">
            <template v-if="searchQuery.trim()">
                {{ filteredItems.length }} of {{ flatItems.length }} items
            </template>
            <template v-else>
                {{ flatItems.length }} items
            </template>
        </p>

        <div class="form-section overflow-hidden !p-0">
            <EmptyState v-if="!flatItems.length" title="No items" description="Import from catalog or add custom items." icon="📋" class="p-8" />
            <EmptyState v-else-if="!filteredItems.length" title="No matches" :description="`Nothing matches “${searchQuery.trim()}”. Try another term.`" icon="🔍" class="p-8" />
            <div v-else class="overflow-x-auto">
                <table class="data-table text-sm">
                    <thead>
                        <tr>
                            <th class="w-12 text-center">On</th>
                            <th class="w-14 text-center">Type</th>
                            <th class="min-w-[10rem]">Item</th>
                            <th v-if="isSports" class="whitespace-nowrap">Venue</th>
                            <th v-if="isSports" class="whitespace-nowrap">Discipline</th>
                            <th class="whitespace-nowrap">{{ isSports ? 'Age group' : (event.event_type === 'kids_fest' ? 'Band' : 'Class') }}</th>
                            <th class="whitespace-nowrap">Gender</th>
                            <th class="whitespace-nowrap">Participant</th>
                            <th class="whitespace-nowrap text-center">Qualifiers</th>
                            <th class="whitespace-nowrap text-center">Max/school</th>
                            <th class="whitespace-nowrap text-right">Fee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in filteredItems" :key="item.id" :class="item.is_enabled === false ? 'opacity-60' : ''">
                            <td class="text-center">
                                <span :class="item.is_enabled !== false ? 'text-emerald-700 text-xs font-semibold' : 'text-slate-400 text-xs'">
                                    {{ item.is_enabled !== false ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <FestItemMetaIcons :gender="item.gender" :participant-type="item.participant_type" />
                            </td>
                            <td>
                                <p class="font-medium text-slate-900">{{ item.title }}</p>
                                <p v-if="item.item_code" class="text-xs text-slate-400 font-mono mt-0.5">{{ item.item_code }}</p>
                                <p v-if="squadSummary(item)" class="text-xs text-slate-500 mt-0.5">{{ squadSummary(item) }}</p>
                            </td>
                            <td v-if="isSports" class="text-slate-600">{{ venueLabel(item.venue_type) }}</td>
                            <td v-if="isSports" class="text-slate-600">{{ disciplineLabel(item.sport_discipline) }}</td>
                            <td class="text-slate-600">{{ categoryLabel(item) }}</td>
                            <td class="text-slate-600">{{ genderLabel(item.gender) }}</td>
                            <td class="text-slate-600">{{ participantLabel(item.participant_type) }}</td>
                            <td class="text-center text-slate-600">{{ item.qualify_count ?? '—' }}</td>
                            <td class="text-center text-slate-600">{{ item.max_per_school ?? '—' }}</td>
                            <td class="text-right text-slate-600">
                                <span v-if="item.fee_amount != null">₹{{ item.fee_amount }}</span>
                                <span v-else class="text-slate-400">Default</span>
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
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import FestItemMetaIcons from '@/Components/sahodaya/FestItemMetaIcons.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import {
    festItemGenderLabel,
    festItemListingDetails,
    festItemParticipantTypeLabel,
    festItemSearchHaystack,
} from '@/support/festItemListingMeta.js';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, itemsByLevel: Object, groupedItems: Object, taxonomy: Object,
    ownerLevelLabels: Object, activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const isSports = computed(() => props.event.event_type === 'sports');
const searchQuery = ref('');

const flatItems = computed(() => {
    if (props.groupedItems) return Object.values(props.groupedItems).flat();
    return Object.values(props.itemsByLevel ?? {}).flat();
});

function itemMetaOptions() {
    return { taxonomy: props.taxonomy, eventType: props.event.event_type };
}

const filteredItems = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return flatItems.value;
    }

    return flatItems.value.filter((item) => {
        const haystack = festItemSearchHaystack(item, itemMetaOptions());
        const terms = q.split(/\s+/).filter(Boolean);
        return terms.every((term) => haystack.includes(term));
    });
});

function venueLabel(value) {
    if (!value) return '—';
    return value === 'outdoor' ? 'Outdoor' : value === 'indoor' ? 'Indoor' : value;
}

function disciplineLabel(value) {
    if (!value) return '—';
    return props.taxonomy?.sport_discipline?.[value] ?? value;
}

function categoryLabel(item) {
    if (isSports.value && item.age_group) {
        return props.taxonomy?.age_group?.[item.age_group] ?? item.age_group;
    }
    if (props.event.event_type === 'kids_fest' && item.kids_band) {
        return props.taxonomy?.kids_band?.[item.kids_band] ?? item.kids_band;
    }
    if (item.class_group) {
        return props.taxonomy?.class_group?.[item.class_group] ?? item.class_group;
    }
    return '—';
}

function genderLabel(gender) {
    return festItemGenderLabel(gender, props.taxonomy);
}

function participantLabel(type) {
    return festItemParticipantTypeLabel(type);
}

function squadSummary(item) {
    const squad = festItemListingDetails(item, itemMetaOptions()).find((f) => f.label === 'Squad rules');
    return squad?.value ?? '';
}
</script>
