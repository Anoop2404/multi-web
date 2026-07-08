<template>
    <div class="card mb-6 space-y-3">
        <div>
            <h3 class="section-title">{{ head.head_name }} — section reports</h3>
            <p class="section-desc text-xs">Reports for all {{ head.item_count }} item(s) in this head, or pick an item below for item-specific views.</p>
        </div>
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
            <Link v-for="action in actions" :key="action.key"
                  :href="action.href"
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
    head: { type: Object, required: true },
});

const reportsBase = computed(() => `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}/reports`);

function scopedQuery(extra = {}) {
    const headParam = props.head?.head_id == null ? 'other' : props.head.head_id;
    const params = new URLSearchParams({ head_id: String(headParam), ...extra });
    return `?${params.toString()}`;
}

const actions = computed(() => [
    {
        key: 'participants',
        label: 'All participants',
        hint: 'Every registration in this head section',
        href: `${reportsBase.value}/head-wise-participants${scopedQuery()}`,
        icon: '👥',
    },
    {
        key: 'registrations',
        label: 'Registration register',
        hint: 'Approved and pending entries by item',
        href: `${reportsBase.value}/registration-register${scopedQuery()}`,
        icon: '📋',
    },
    {
        key: 'marks',
        label: 'Mark entry status',
        hint: 'Which items still need ranks or scores',
        href: `${reportsBase.value}/mark-entry-status${scopedQuery()}`,
        icon: '🏅',
    },
    {
        key: 'counts',
        label: 'Item counts',
        hint: 'Registrations per item in this head',
        href: `${reportsBase.value}/item-counts${scopedQuery()}`,
        icon: '📊',
    },
    {
        key: 'numbering',
        label: 'Chest & item numbers',
        hint: 'Numbering assignment status',
        href: `${reportsBase.value}/numbering-register${scopedQuery()}`,
        icon: '🔢',
    },
    {
        key: 'export',
        label: 'Export section',
        hint: 'Download spreadsheet for this head',
        href: `${reportsBase.value}/export/head-wise-participants${scopedQuery()}`,
        icon: '⬇️',
    },
]);
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
