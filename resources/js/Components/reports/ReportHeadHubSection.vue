<template>
    <section class="mb-8">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <div>
                <h3 class="section-title mb-1">By item head</h3>
                <p class="text-sm text-slate-600">Primary navigation — open a head section, then pick an item.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-sm">
                <Link v-if="manageUrl" :href="manageUrl" class="btn-secondary text-xs">Competition hub</Link>
            </div>
        </div>

        <div v-if="compact" class="reports-tile-grid">
            <Link v-for="head in headsWithItems" :key="head.head_id ?? 'other'"
                  :href="headHref(head.head_id)"
                  class="reports-head-card group block hover:no-underline">
                <span v-if="head.participant_count || head.registration_count" class="reports-head-card__count">
                    {{ head.registration_count ?? head.participant_count }}
                </span>
                <p class="font-semibold text-slate-900 pr-16 group-hover:text-[color:var(--brand-navy)]">{{ head.head_name }}</p>
                <p class="text-xs text-slate-500 mt-1">
                    {{ head.item_count }} item{{ head.item_count === 1 ? '' : 's' }}
                    · {{ head.registration_count ?? 0 }} reg{{ (head.registration_count ?? 0) === 1 ? '' : 's' }}
                    · {{ head.participant_count }} participant{{ head.participant_count === 1 ? '' : 's' }}
                    <span v-if="head.max_item_reg_count"> · max {{ head.max_item_reg_count }}/item</span>
                </p>
                <p class="mt-3 text-xs font-semibold text-indigo-600 group-hover:underline">
                    {{ mode === 'ops' ? 'Open section →' : 'Open report →' }}
                </p>
            </Link>
        </div>

        <div v-else class="space-y-4">
            <div v-for="head in headsWithItems" :key="head.head_id ?? 'other'" class="card !p-0 overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-slate-50/80 border-b border-slate-100">
                    <div>
                        <p class="font-semibold text-slate-900">{{ head.head_name }}</p>
                        <p class="text-xs text-slate-500">{{ head.item_count }} item{{ head.item_count === 1 ? '' : 's' }}
                            · {{ head.registration_count ?? 0 }} reg{{ (head.registration_count ?? 0) === 1 ? '' : 's' }}
                            · {{ head.participant_count }} participant{{ head.participant_count === 1 ? '' : 's' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="headHref(head.head_id)" class="btn-primary text-xs px-3 py-2">
                            {{ mode === 'ops' ? 'Open section' : 'Open report' }}
                        </Link>
                        <a v-if="exportBaseUrl && mode !== 'ops'"
                           :href="exportHref(head.head_id)"
                           target="_blank" rel="noopener"
                           class="btn-secondary text-xs px-3 py-2">Export ↓</a>
                    </div>
                </div>
                <ul v-if="head.items?.length" class="divide-y divide-slate-100">
                    <li v-for="item in head.items" :key="item.id">
                        <Link :href="itemHref(head.head_id, item.id)"
                              class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm hover:bg-slate-50 transition">
                            <span class="font-medium text-slate-800">{{ item.title }}</span>
                            <span class="text-xs text-slate-500">
                                <span v-if="item.participant_count">{{ item.participant_count }} participants</span>
                                <span v-if="item.chest_missing" class="text-amber-700 ml-2">{{ item.chest_missing }} chest pending</span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    heads: { type: Array, default: () => [] },
    headItemGroups: { type: Array, default: () => [] },
    headReportBase: { type: String, required: true },
    exportBaseUrl: { type: String, default: null },
    manageUrl: { type: String, default: null },
    mode: { type: String, default: 'report' },
    opsBaseUrl: { type: String, default: null },
    /** Tile grid only — avoids listing every item on the reports hub. */
    compact: { type: Boolean, default: false },
});

const headsWithItems = computed(() =>
    props.headItemGroups.length
        ? props.headItemGroups
        : props.heads.map((h) => ({ ...h, items: [] })),
);

function headParam(headId) {
    return headId == null ? 'other' : headId;
}

function headHref(headId) {
    if (props.mode === 'ops' && props.opsBaseUrl) {
        return `${props.opsBaseUrl}?head_id=${headParam(headId)}`;
    }
    return `${props.headReportBase}?head_id=${headId ?? 'other'}`;
}

function itemHref(headId, itemId) {
    if (props.mode === 'ops' && props.opsBaseUrl) {
        return `${props.opsBaseUrl}?head_id=${headParam(headId)}&item_id=${itemId}`;
    }
    return `${props.headReportBase}?head_id=${headParam(headId)}&item_id=${itemId}`;
}

function exportHref(headId) {
    const sep = props.exportBaseUrl.includes('?') ? '&' : '?';
    const param = headId == null ? 'other' : headId;
    return `${props.exportBaseUrl}${sep}head_id=${param}`;
}
</script>
