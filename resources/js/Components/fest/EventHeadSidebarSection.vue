<template>
    <div v-if="groups.length" class="event-head-sidebar">
        <p class="event-head-sidebar__title">{{ resolvedTitle }}</p>
        <Link :href="sectionBase"
              class="event-head-sidebar__link event-head-sidebar__link--hub"
              :class="{ 'event-head-sidebar__link--active': isHubActive }">
            All {{ isSports ? 'Event Heads' : 'item heads' }}
        </Link>
        <Link v-for="group in groups"
              :key="group.head_id ?? 'other'"
              :href="headUrl(group.head_id)"
              class="event-head-sidebar__head"
              :class="{ 'event-head-sidebar__head--active': isHeadActive(group.head_id) }">
            <span class="min-w-0 flex-1 text-left">
                <span class="block truncate text-xs font-semibold text-white">{{ group.head_name }}</span>
                <span class="block truncate text-[10px] text-white/50">
                    {{ group.item_count }} item{{ group.item_count === 1 ? '' : 's' }}
                    <span v-if="group.participant_count"> · {{ group.participant_count }} athletes</span>
                </span>
            </span>
            <span class="text-white/40 text-[10px] shrink-0" aria-hidden="true">→</span>
        </Link>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    headQueryParam,
    parseHeadFromUrl,
    sahodayaCompetitionBase,
    sahodayaEventBase,
    schoolCompetitionBase,
    schoolEventBase,
    schoolProgramBase,
} from '@/support/eventHeadNav.js';
import { SPORTS_HEAD_SIDEBAR_PATHS } from '@/support/sportsEventNav.js';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    portal: { type: String, default: 'sahodaya' },
    sahodayaId: { type: [String, Number], default: '' },
    schoolId: { type: [String, Number], default: '' },
    programPrefix: { type: String, default: 'sports' },
    eventId: { type: [String, Number], required: true },
    isSports: { type: Boolean, default: false },
    title: { type: String, default: '' },
});

const resolvedTitle = computed(() => {
    if (props.title) return props.title;
    return props.isSports ? 'Event Heads' : 'Item heads';
});

const page = usePage();

const eventBase = computed(() => {
    if (props.portal === 'school') {
        return schoolEventBase(props.schoolId, props.programPrefix, props.eventId);
    }

    return sahodayaEventBase(props.sahodayaId, props.eventId);
});

const competitionBase = computed(() => {
    if (props.portal === 'school') {
        return schoolCompetitionBase(props.schoolId, props.programPrefix, props.eventId);
    }

    return sahodayaCompetitionBase(props.sahodayaId, props.eventId);
});

/** Current operational page — head links stay in the same section (marks, reports, etc.). */
const sectionBase = computed(() => {
    const path = page.url.split('?')[0];

    if (props.portal === 'school') {
        if (props.isSports) {
            if (path.includes('/reports/') || path.includes('/head-wise')) {
                return `${schoolProgramBase(props.schoolId, props.programPrefix)}/reports/${props.eventId}/head-wise`;
            }
            // The event-level registration page (step 1) doesn't scope anything by head —
            // only the item registration page (step 2) does. Route every head click there
            // so "pick a head" always lands somewhere that actually registers students to it.
            if (path.includes('/registration') || path.includes('/items')) {
                return `${eventBase.value}/items`;
            }
        }

        return `${eventBase.value}/registration`;
    }

    if (props.isSports) {
        for (const segment of SPORTS_HEAD_SIDEBAR_PATHS) {
            if (path.includes(segment)) {
                if (segment === '/reports/by-head') {
                    return `${eventBase.value}/reports/by-head`;
                }

                return `${eventBase.value}${segment}`;
            }
        }
    }

    if (path.includes('/competition')) {
        return competitionBase.value;
    }

    return competitionBase.value;
});

function headUrl(headId) {
    return `${sectionBase.value}${headQueryParam(headId)}`;
}

function isHeadActive(headId) {
    const urlHead = parseHeadFromUrl(page.url);
    if (headId == null) {
        return urlHead === 'other' || urlHead === 0;
    }

    return String(urlHead) === String(headId);
}

const isHubActive = computed(() => parseHeadFromUrl(page.url) === null && !page.url.includes('item_id='));
</script>
