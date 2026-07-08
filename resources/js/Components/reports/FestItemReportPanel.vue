<template>
    <div class="space-y-5 mt-2">
        <div class="card flex flex-wrap items-start justify-between gap-4">
            <div>
                <p v-if="head?.head_name" class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ head.head_name }}</p>
                <h3 class="section-title mt-1">{{ item.title }}</h3>
                <p v-if="item.item_code" class="text-xs font-mono text-slate-500 mt-0.5">{{ item.item_code }}</p>
                <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-slate-600">
                    <div><span class="text-slate-400">Participants</span> <strong class="text-slate-800">{{ item.participant_count ?? 0 }}</strong></div>
                    <div v-if="item.chest_missing"><span class="text-slate-400">Chest pending</span> <strong class="text-amber-700">{{ item.chest_missing }}</strong></div>
                </dl>
            </div>
            <Link :href="competitionUrl" class="btn-secondary text-sm shrink-0">Competition hub</Link>
        </div>

        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
            <Link v-for="action in actions" :key="action.key"
                  :href="action.href"
                  :target="action.external ? '_blank' : undefined"
                  :rel="action.external ? 'noopener' : undefined"
                  class="fest-ops-card group">
                <span class="fest-ops-card__icon" aria-hidden="true">{{ action.icon }}</span>
                <div class="min-w-0">
                    <p class="font-semibold text-slate-900 group-hover:text-[color:var(--brand-navy)]">{{ action.label }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">{{ action.hint }}</p>
                </div>
                <span class="fest-ops-card__arrow" aria-hidden="true">→</span>
            </Link>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    item: { type: Object, required: true },
    head: { type: Object, default: null },
});

const eventBase = computed(() => `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}`);
const reportsBase = computed(() => `${eventBase.value}/reports`);

const headParam = computed(() => {
    const id = props.head?.head_id ?? props.item.head_id;
    if (id == null) return 'other';
    return id;
});

const competitionUrl = computed(() => {
    return `${eventBase.value}/competition?head_id=${headParam.value}&item_id=${props.item.id}`;
});

function scopedQuery(extra = {}) {
    const params = new URLSearchParams({
        head_id: String(headParam.value),
        item_id: String(props.item.id),
        ...extra,
    });
    return `?${params.toString()}`;
}

const actions = computed(() => {
    const list = [
        {
            key: 'item-wise',
            label: 'Participants & marks',
            hint: 'Full item-wise browser with ranks and scores',
            href: `${reportsBase.value}/item-wise${scopedQuery()}`,
            icon: '📊',
        },
        {
            key: 'participants',
            label: 'Head-wise list',
            hint: 'Participant register filtered to this item',
            href: `${reportsBase.value}/head-wise-participants${scopedQuery()}`,
            icon: '👥',
        },
        {
            key: 'registrations',
            label: 'Registration register',
            hint: 'School entries and approval status',
            href: `${reportsBase.value}/registration-register${scopedQuery()}`,
            icon: '📋',
        },
        {
            key: 'marks',
            label: 'Mark entry status',
            hint: 'Pending vs completed mark entry',
            href: `${reportsBase.value}/mark-entry-status${scopedQuery()}`,
            icon: '🏅',
        },
        {
            key: 'numbering',
            label: 'Chest & item numbers',
            hint: 'Assigned chest and item registration numbers',
            href: `${reportsBase.value}/numbering-register${scopedQuery()}`,
            icon: '🔢',
        },
        {
            key: 'pending',
            label: 'Pending approvals',
            hint: 'Registrations awaiting review',
            href: `${reportsBase.value}/pending-approvals${scopedQuery()}`,
            icon: '⏳',
        },
    ];

    if (props.item.id) {
        list.push(
            {
                key: 'export-xls',
                label: 'Export spreadsheet',
                hint: 'Download item participants (XLS)',
                href: `${reportsBase.value}/export/item-participants${scopedQuery()}`,
                icon: '⬇️',
                external: true,
            },
            {
                key: 'export-pdf',
                label: 'Export PDF',
                hint: 'Printable participant list',
                href: `${reportsBase.value}/export/item-wise${scopedQuery()}`,
                icon: '🖨️',
                external: true,
            },
        );
    }

    return list;
});
</script>

<style scoped>
.fest-ops-card {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem 1.1rem;
    border-radius: 0.75rem;
    border: 1px solid rgb(226 232 240);
    background: white;
    text-decoration: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.fest-ops-card:hover {
    border-color: rgb(199 210 254);
    box-shadow: 0 4px 14px rgb(15 23 42 / 0.06);
}
.fest-ops-card__icon { font-size: 1.25rem; line-height: 1; flex-shrink: 0; }
.fest-ops-card__arrow {
    margin-left: auto;
    color: rgb(99 102 241);
    font-size: 0.875rem;
    opacity: 0;
    transition: opacity 0.15s;
}
.fest-ops-card:hover .fest-ops-card__arrow { opacity: 1; }
</style>
