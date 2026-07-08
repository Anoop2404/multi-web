<template>
    <div class="space-y-4">
        <!-- Breadcrumb -->
        <nav v-if="showBreadcrumb && (selectedHeadId || selectedItemId)" class="flex flex-wrap items-center gap-1.5 text-sm">
            <Link :href="baseUrl" class="text-slate-500 hover:text-indigo-600">{{ flatItemsMode ? 'All items' : 'All heads' }}</Link>
            <template v-if="selectedHead && !flatItemsMode">
                <span class="text-slate-300">/</span>
                <Link v-if="selectedItemId"
                      :href="headUrl(selectedHead.head_id)"
                      class="text-slate-500 hover:text-indigo-600">
                    {{ selectedHead.head_name }}
                </Link>
                <span v-else class="font-semibold text-slate-800">{{ selectedHead.head_name }}</span>
            </template>
            <template v-if="selectedItem">
                <span class="text-slate-300">/</span>
                <span class="font-semibold text-slate-800">{{ selectedItem.title }}</span>
            </template>
        </nav>

        <!-- Level 1: pick head or item (flat mode) -->
        <section v-if="!selectedHeadId && !selectedItemId">
            <p v-if="displayHint" class="text-sm text-slate-600 mb-4">{{ displayHint }}</p>
            <EmptyState v-if="!navGroups.length" title="No competition items" :description="emptyHeadsText" icon="📂" />
            <div v-else-if="flatItemsMode" class="reports-tile-grid">
                <Link v-for="item in flatItems" :key="item.id"
                      :href="itemUrl(null, item.id)"
                      class="reports-head-card group block hover:no-underline">
                    <span v-if="item.participant_count" class="reports-head-card__count">{{ item.participant_count }}</span>
                    <p class="font-semibold text-slate-900 group-hover:text-[color:var(--brand-navy)]">{{ item.title }}</p>
                    <p v-if="item.item_code" class="text-xs font-mono text-slate-500 mt-0.5">{{ item.item_code }}</p>
                    <p v-if="item.age_group" class="text-xs text-slate-500 mt-0.5">{{ item.age_group }}</p>
                    <dl v-if="showItemStats" class="mt-3 grid grid-cols-2 gap-2 text-xs border-t border-slate-100 pt-3">
                        <div v-if="showResultStats">
                            <dt class="text-slate-400">Marks</dt>
                            <dd :class="item.marks_ready ? 'text-emerald-700 font-semibold' : 'text-amber-700'">
                                {{ item.marks_entered ?? 0 }}/{{ item.performers ?? item.participant_count ?? 0 }}
                            </dd>
                        </div>
                        <div v-if="showResultStats">
                            <dt class="text-slate-400">Status</dt>
                            <dd :class="item.results_published ? 'text-emerald-700 font-semibold' : 'text-slate-600'">
                                {{ item.results_published ? 'Published' : (item.marks_ready ? 'Ready' : 'Pending') }}
                            </dd>
                        </div>
                        <div v-if="!showResultStats">
                            <dt class="text-slate-400">Chest start</dt>
                            <dd class="font-mono font-semibold">{{ item.chest_no_start }}</dd>
                        </div>
                        <div v-if="!showResultStats">
                            <dt class="text-slate-400">Assigned</dt>
                            <dd :class="item.chest_missing ? 'text-amber-700' : 'text-emerald-700'">
                                {{ item.chest_assigned }}/{{ item.participant_count }}
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-xs font-semibold text-indigo-600 group-hover:underline">Open item →</p>
                </Link>
            </div>
            <div v-else class="reports-tile-grid">
                <Link v-for="head in navGroups" :key="head.head_id ?? 'other'"
                      :href="headUrl(head.head_id)"
                      class="reports-head-card group block hover:no-underline">
                    <span v-if="head.participant_count" class="reports-head-card__count">{{ head.participant_count }}</span>
                    <p class="font-semibold text-slate-900 pr-16 group-hover:text-[color:var(--brand-navy)]">{{ head.head_name }}</p>
                    <p class="text-xs text-slate-500 mt-1">{{ head.item_count }} item{{ head.item_count === 1 ? '' : 's' }}</p>
                    <p v-if="head.competition_start || head.competition_end || head.competition_time" class="text-xs text-slate-500 mt-1">
                        {{ formatHeadCompetition(head) }}
                        <span v-if="head.schedule_mode === 'same_time'" class="ml-1 text-[10px] font-semibold text-sky-700">· same time</span>
                    </p>
                    <p v-if="head.published_count != null" class="text-xs mt-1"
                       :class="head.pending_count ? 'text-amber-700' : 'text-emerald-700'">
                        {{ head.published_count }}/{{ head.item_count }} published
                    </p>
                    <p class="mt-3 text-xs font-semibold text-indigo-600 group-hover:underline">Open section →</p>
                </Link>
            </div>
        </section>

        <!-- Level 2: pick item under head -->
        <section v-else-if="selectedHeadId && !selectedItemId && selectedHead">
            <slot name="head-detail" :head="selectedHead" />
            <div class="mb-4">
                <h3 v-if="!$slots['head-detail']" class="section-title">{{ selectedHead.head_name }}</h3>
                <p class="text-sm text-slate-600">Select a competition item to continue.</p>
            </div>
            <EmptyState v-if="!selectedHead.items?.length" title="No items in this head" icon="📋" />
            <div v-else class="reports-tile-grid">
                <Link v-for="item in selectedHead.items" :key="item.id"
                      :href="itemUrl(selectedHead.head_id, item.id)"
                      class="reports-head-card group block hover:no-underline">
                    <span v-if="item.participant_count" class="reports-head-card__count">{{ item.participant_count }}</span>
                    <p class="font-semibold text-slate-900 group-hover:text-[color:var(--brand-navy)]">{{ item.title }}</p>
                    <p v-if="item.item_code" class="text-xs font-mono text-slate-500 mt-0.5">{{ item.item_code }}</p>
                    <p v-if="item.age_group" class="text-xs text-slate-500 mt-0.5">{{ item.age_group }}</p>
                    <dl v-if="showItemStats" class="mt-3 grid grid-cols-2 gap-2 text-xs border-t border-slate-100 pt-3">
                        <div v-if="showResultStats">
                            <dt class="text-slate-400">Marks</dt>
                            <dd :class="item.marks_ready ? 'text-emerald-700 font-semibold' : 'text-amber-700'">
                                {{ item.marks_entered ?? 0 }}/{{ item.performers ?? item.participant_count ?? 0 }}
                            </dd>
                        </div>
                        <div v-if="showResultStats">
                            <dt class="text-slate-400">Status</dt>
                            <dd :class="item.results_published ? 'text-emerald-700 font-semibold' : 'text-slate-600'">
                                {{ item.results_published ? 'Published' : (item.marks_ready ? 'Ready' : 'Pending') }}
                            </dd>
                        </div>
                        <div v-if="!showResultStats">
                            <dt class="text-slate-400">Chest start</dt>
                            <dd class="font-mono font-semibold">{{ item.chest_no_start }}</dd>
                        </div>
                        <div v-if="!showResultStats">
                            <dt class="text-slate-400">Assigned</dt>
                            <dd :class="item.chest_missing ? 'text-amber-700' : 'text-emerald-700'">
                                {{ item.chest_assigned }}/{{ item.participant_count }}
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-xs font-semibold text-indigo-600 group-hover:underline">Open item →</p>
                </Link>
            </div>
        </section>

        <!-- Invalid head in URL -->
        <section v-else-if="selectedHeadId && !selectedItemId && !selectedHead">
            <EmptyState title="Section not found" description="That item head is missing or was removed." icon="📂">
                <template #action>
                    <Link :href="baseUrl" class="btn-secondary text-sm inline-flex">← Back</Link>
                </template>
            </EmptyState>
        </section>

        <!-- Level 3: slot for item detail -->
        <template v-if="selectedItemId">
            <slot v-if="selectedItem" :item="selectedItem" :head="selectedHead" />
            <EmptyState v-else title="Item not found" description="This item is disabled or no longer on the event." icon="📋">
                <template #action>
                    <Link :href="baseUrl" class="btn-secondary text-sm inline-flex">← Back to list</Link>
                </template>
            </EmptyState>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useHeadItemNav } from '@/composables/useHeadItemNav.js';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    baseUrl: { type: String, required: true },
    selectedHeadId: { type: [String, Number], default: null },
    selectedItemId: { type: [String, Number], default: null },
    hasItemHeads: { type: Boolean, default: true },
    flatWhenSingleGroup: { type: Boolean, default: true },
    showBreadcrumb: { type: Boolean, default: true },
    showItemStats: { type: Boolean, default: true },
    showResultStats: { type: Boolean, default: false },
    hint: { type: String, default: '' },
    emptyHeadsText: { type: String, default: 'Add items to this event from the catalog, then return here.' },
});

const { groups: navGroups, selectedHead, selectedItem } = useHeadItemNav(props);

const flatItemsMode = computed(() => {
    if (!props.flatWhenSingleGroup) {
        return false;
    }
    if (!props.hasItemHeads) {
        return navGroups.value.length > 0;
    }
    return navGroups.value.length === 1 && navGroups.value[0].head_id == null;
});

const flatItems = computed(() => navGroups.value.flatMap((g) => g.items ?? []));

const displayHint = computed(() => {
    if (props.hint) {
        return props.hint;
    }
    return flatItemsMode.value
        ? 'Select a competition item to continue.'
        : 'Events are organized by item head — pick a section first, then an item.';
});

function headUrl(headId) {
    if (headId == null) {
        return `${props.baseUrl}?head_id=other`;
    }
    return `${props.baseUrl}?head_id=${headId}`;
}

function itemUrl(headId, itemId) {
    if (headId == null) {
        return `${props.baseUrl}?item_id=${itemId}`;
    }
    const headParam = headId === 'other' ? 'other' : headId;
    return `${props.baseUrl}?head_id=${headParam}&item_id=${itemId}`;
}

function formatWindow(start, end) {
    if (start && end) return `${formatShortDate(start)} – ${formatShortDate(end)}`;
    if (start) return `from ${formatShortDate(start)}`;
    if (end) return `until ${formatShortDate(end)}`;
    return '';
}

function formatHeadCompetition(head) {
    if (head.schedule_mode === 'same_time' && head.competition_start) {
        const time = formatCompTime(head.competition_time);
        return time ? `${formatShortDate(head.competition_start)} ${time}` : formatShortDate(head.competition_start);
    }
    return formatWindow(head.competition_start, head.competition_end);
}

function formatCompTime(hhmm) {
    if (!hhmm) return '';
    const [h, m] = String(hhmm).slice(0, 5).split(':');
    const d = new Date();
    d.setHours(Number(h), Number(m), 0, 0);
    return d.toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' });
}

function formatShortDate(iso) {
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
}
</script>
