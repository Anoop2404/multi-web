<template>
    <section v-if="headItemGroups.length" class="mb-6">
        <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">By item head</h3>
                <p class="text-xs text-slate-500 mt-0.5">Pick a section — data below updates for that head.</p>
            </div>
            <Link v-if="hubUrl" :href="hubUrl" class="text-xs font-semibold text-indigo-600 hover:underline">← All sections</Link>
        </div>

        <div class="flex flex-wrap gap-2">
            <Link v-if="showAllHeadsLink"
                  :href="baseUrl"
                  class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full border transition-colors"
                  :class="!selectedHeadId
                      ? 'bg-indigo-600 text-white border-indigo-600'
                      : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300 hover:text-indigo-800'">
                All heads
            </Link>
            <Link v-for="head in headItemGroups"
                  :key="head.head_id ?? 'other'"
                  :href="headUrl(head.head_id)"
                  class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full border transition-colors"
                  :class="isHeadActive(head.head_id)
                      ? 'bg-indigo-600 text-white border-indigo-600'
                      : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300 hover:text-indigo-800'">
                {{ head.head_name }}
                <span class="opacity-75 font-normal" :class="{ 'opacity-90': isHeadActive(head.head_id) }">{{ head.item_count }} items</span>
                <span v-if="head.participant_count" class="opacity-75 font-normal" :class="{ 'opacity-90': isHeadActive(head.head_id) }">· {{ head.participant_count }} ppl</span>
                <span v-if="head.competition_start || head.competition_end || head.competition_time" class="opacity-75 font-normal" :class="{ 'opacity-90': isHeadActive(head.head_id) }">· {{ formatCompWindow(head) }}</span>
                <span v-if="head.schedule_mode === 'same_time'" class="font-normal opacity-90">· same time</span>
                <span v-if="head.published_count != null" class="opacity-75 font-normal" :class="{ 'opacity-90': isHeadActive(head.head_id) }">· {{ head.published_count }}/{{ head.item_count }} pub.</span>
            </Link>
        </div>

        <div v-if="showItemLinks && activeHead?.items?.length" class="mt-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <p class="text-xs font-semibold text-slate-700">{{ activeHead.head_name }} — select an item</p>
                <Link :href="headUrl(activeHead.head_id)" class="text-[11px] font-semibold text-indigo-600 hover:underline">
                    Clear item filter
                </Link>
            </div>
            <ReportItemSearchSelect :items="activeHead.items"
                                    :model-value="selectedItemId"
                                    :all-items-label="`All ${activeHead.item_count} items in ${activeHead.head_name}`"
                                    :show-view-button="showParticipantView"
                                    :view-enabled-for="hasParticipants"
                                    search-placeholder="Search by item name or code…"
                                    @select="onItemSelect"
                                    @view="emitViewParticipants" />
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import ReportItemSearchSelect from '@/Components/reports/ReportItemSearchSelect.vue';

const props = defineProps({
    headItemGroups: { type: Array, default: () => [] },
    baseUrl: { type: String, required: true },
    selectedHeadId: { type: [String, Number], default: null },
    selectedItemId: { type: [String, Number], default: null },
    headQueryKey: { type: String, default: 'head_id' },
    itemQueryKey: { type: String, default: 'item_id' },
    showItemLinks: { type: Boolean, default: true },
    showAllHeadsLink: { type: Boolean, default: true },
    hubUrl: { type: String, default: null },
    preserveQuery: { type: Object, default: () => ({}) },
    participantCountsByItem: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['view-participants']);

const showParticipantView = computed(() => Object.keys(props.participantCountsByItem ?? {}).length > 0);

function hasParticipants(itemId) {
    return (props.participantCountsByItem?.[itemId] ?? 0) > 0
        || (props.participantCountsByItem?.[String(itemId)] ?? 0) > 0;
}

function emitViewParticipants(itemId) {
    emit('view-participants', itemId);
}

function onItemSelect(itemId) {
    if (!activeHead.value) return;
    if (!itemId) {
        router.get(headUrl(activeHead.value.head_id), {}, { preserveScroll: true, preserveState: true });
        return;
    }
    router.get(itemUrl(activeHead.value.head_id, itemId), {}, { preserveScroll: true, preserveState: true });
}

const activeHead = computed(() => {
    if (props.selectedHeadId == null || props.selectedHeadId === '') {
        return null;
    }
    const id = String(props.selectedHeadId);
    if (id === 'other') {
        return props.headItemGroups.find((h) => h.head_id == null) ?? null;
    }
    return props.headItemGroups.find((h) => String(h.head_id) === id) ?? null;
});

function headParam(headId) {
    return headId == null ? 'other' : String(headId);
}

function isHeadActive(headId) {
    if (props.selectedHeadId == null || props.selectedHeadId === '') {
        return false;
    }
    if (headId == null) {
        return String(props.selectedHeadId) === 'other';
    }
    return String(props.selectedHeadId) === String(headId);
}

function buildUrl(headId, itemId = null) {
    const q = new URLSearchParams();
    for (const [key, val] of Object.entries(props.preserveQuery)) {
        if (val != null && val !== '') {
            q.set(key, String(val));
        }
    }
    if (headId !== undefined && headId !== false) {
        if (headId != null) {
            q.set(props.headQueryKey, headParam(headId));
        }
    } else {
        q.delete(props.headQueryKey);
        q.delete(props.itemQueryKey);
    }
    if (itemId) {
        q.set(props.itemQueryKey, String(itemId));
    } else if (headId !== undefined) {
        q.delete(props.itemQueryKey);
    }
    const qs = q.toString();
    return qs ? `${props.baseUrl}?${qs}` : props.baseUrl;
}

function headUrl(headId) {
    return buildUrl(headId, null);
}

function itemUrl(headId, itemId) {
    return buildUrl(headId, itemId);
}

function formatRegWindow(head) {
    const start = head.reg_start;
    const end = head.reg_end;
    if (start && end) return `${formatShortDate(start)}–${formatShortDate(end)}`;
    if (start) return `from ${formatShortDate(start)}`;
    if (end) return `until ${formatShortDate(end)}`;
    return '';
}

function formatCompWindow(head) {
    const start = head.competition_start;
    const end = head.competition_end;
    const time = formatTime(head.competition_time);
    if (head.schedule_mode === 'same_time' && start) {
        return time ? `${formatShortDate(start)} ${time}` : formatShortDate(start);
    }
    if (start && end) return `${formatShortDate(start)}–${formatShortDate(end)}`;
    if (start) return `from ${formatShortDate(start)}`;
    if (end) return `until ${formatShortDate(end)}`;
    return time;
}

function formatShortDate(iso) {
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
}

function formatTime(hhmm) {
    if (!hhmm) return '';
    const [h, m] = String(hhmm).slice(0, 5).split(':');
    const d = new Date();
    d.setHours(Number(h), Number(m), 0, 0);
    return d.toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' });
}
</script>

