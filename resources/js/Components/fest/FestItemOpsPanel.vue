<template>
    <div class="space-y-5 mt-2">
        <div class="card flex flex-wrap items-start justify-between gap-4">
            <div>
                <p v-if="head?.head_name" class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ head.head_name }}</p>
                <h3 class="section-title mt-1">{{ item.title }}</h3>
                <p v-if="item.item_code" class="text-xs font-mono text-slate-500 mt-0.5">{{ item.item_code }}</p>
                <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-slate-600">
                    <div><span class="text-slate-400">Participants</span> <strong class="text-slate-800">{{ item.participant_count ?? 0 }}</strong></div>
                    <div v-if="isSports && item.chest_missing"><span class="text-slate-400">Chest pending</span> <strong class="text-amber-700">{{ item.chest_missing }}</strong></div>
                    <div v-if="item.results_published"><span class="text-emerald-600 font-semibold">Results published</span></div>
                </dl>
            </div>
            <span v-if="item.results_published"
                  class="text-xs px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 font-medium shrink-0">
                Published
            </span>
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

        <div class="flex flex-wrap gap-2 pt-1">
            <button v-if="isSports"
                    type="button"
                    class="btn-secondary text-sm"
                    :disabled="autoRanking"
                    @click="autoRank">
                {{ autoRanking ? 'Ranking…' : 'Auto-rank (sports)' }}
            </button>
            <button v-if="!item.results_published"
                    type="button"
                    class="btn-primary text-sm"
                    :disabled="publishing"
                    @click="publishResults">
                {{ publishing ? 'Publishing…' : 'Publish item results' }}
            </button>
            <Link :href="resultsUrl" class="btn-secondary text-sm">Event results page</Link>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    event: { type: Object, required: true },
    item: { type: Object, required: true },
    head: { type: Object, default: null },
});

const autoRanking = ref(false);
const publishing = ref(false);

const isSports = computed(() => props.event.event_type === 'sports');
const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/events/${props.event.id}`);
const headParam = computed(() => {
    const id = props.head?.head_id ?? props.item.head_id;
    if (id == null) return 'other';
    return id;
});

function scopedQuery(extra = {}) {
    const params = new URLSearchParams({
        head_id: String(headParam.value),
        item_id: String(props.item.id),
        ...extra,
    });
    return `?${params.toString()}`;
}

const registrationsUrl = computed(() => `${base.value}/registrations${scopedQuery()}`);
const marksUrl = computed(() => `${base.value}/marks${scopedQuery()}`);
const chestUrl = computed(() => `${base.value}/chest-numbers${scopedQuery()}`);
const resultsUrl = computed(() => `${base.value}/results${scopedQuery()}`);
const registerUrl = computed(() => `${base.value}/registrations${scopedQuery({ register: '1' })}`);

const actions = computed(() => {
    const list = [
        {
            key: 'registrations',
            label: 'Registrations',
            hint: 'Review, approve, or reject entries for this item',
            href: registrationsUrl.value,
            icon: '📋',
        },
        {
            key: 'register',
            label: 'Register on behalf',
            hint: 'Add a school registration for this item',
            href: registerUrl.value,
            icon: '➕',
        },
        {
            key: 'marks',
            label: 'Assign rank / marks',
            hint: 'Enter position, grade, score, or measurement',
            href: marksUrl.value,
            icon: '🏅',
        },
    ];

    if (isSports.value) {
        list.push({
            key: 'chest',
            label: 'Chest numbers',
            hint: 'Assign chest and item registration numbers',
            href: chestUrl.value,
            icon: '🔢',
        });
    }

    list.push({
        key: 'reports',
        label: 'Item reports',
        hint: 'Participants list and exports for this item',
        href: `${base.value}/reports/head-wise-participants${scopedQuery()}`,
        icon: '📊',
    });

    return list;
});

function autoRank() {
    autoRanking.value = true;
    router.post(`${base.value}/items/${props.item.id}/auto-rank`, {}, {
        preserveScroll: true,
        onFinish: () => { autoRanking.value = false; },
    });
}

function publishResults() {
    publishing.value = true;
    router.post(`${base.value}/items/${props.item.id}/publish-results`, {}, {
        preserveScroll: true,
        onFinish: () => { publishing.value = false; },
    });
}
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
.fest-ops-card__icon {
    font-size: 1.25rem;
    line-height: 1;
    flex-shrink: 0;
}
.fest-ops-card__arrow {
    margin-left: auto;
    color: rgb(99 102 241);
    font-size: 0.875rem;
    opacity: 0;
    transition: opacity 0.15s;
}
.fest-ops-card:hover .fest-ops-card__arrow {
    opacity: 1;
}
</style>
