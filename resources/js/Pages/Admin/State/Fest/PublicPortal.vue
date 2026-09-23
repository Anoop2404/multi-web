<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h2 class="text-sm font-bold text-slate-900">Public portal</h2>
            <p class="mt-0.5 text-xs text-slate-500">
                What the outside world can see. Each section is off until switched on here, and results
                additionally need the item's own result published — so you can compute and check every
                ranking internally with nothing leaking.
            </p>

            <form @submit.prevent="save" class="mt-4 space-y-2">
                <label v-for="row in rows" :key="row.key"
                       class="flex items-start gap-3 rounded-xl border px-3 py-2.5"
                       :class="form[row.key] ? 'border-emerald-300 bg-emerald-50/50' : 'border-slate-200'">
                    <input v-model="form[row.key]" type="checkbox" class="mt-0.5">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-slate-800">{{ row.label }}</span>
                        <span class="block text-xs text-slate-500">{{ row.hint }}</span>
                        <span v-if="row.blocked" class="mt-0.5 block text-xs font-semibold text-amber-700">{{ row.blocked }}</span>
                    </span>
                    <a v-if="visibility[row.state]" :href="row.link" target="_blank"
                       class="shrink-0 text-xs font-semibold text-[color:var(--brand-blue)] hover:underline">View ↗</a>
                </label>

                <div class="flex items-center gap-3 pt-1">
                    <button type="submit" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white" :disabled="form.processing">
                        Save visibility
                    </button>
                    <span class="text-xs text-slate-400">Takes effect immediately.</span>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h3 class="text-sm font-bold text-slate-900">Currently live</h3>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Scheduled items</p>
                    <p class="text-lg font-bold text-slate-900">{{ counts.scheduled_items }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Results published</p>
                    <p class="text-lg font-bold text-slate-900">{{ counts.published_items }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Items visible publicly</p>
                    <p class="text-lg font-bold text-slate-900">{{ counts.public_results }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Sahodayas ranked</p>
                    <p class="text-lg font-bold text-slate-900">{{ counts.ranked_sahodayas }}</p>
                </div>
            </div>

            <p class="mt-3 text-xs text-slate-500">
                Certificate verification is always available, whatever these switches say — a certificate
                in someone's hand is already public, and refusing to confirm it helps nobody.
                <a :href="links.verify" target="_blank" class="font-semibold text-[color:var(--brand-blue)] hover:underline">Verification page ↗</a>
            </p>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    visibility: Object, counts: Object, links: Object, actionUrls: Object,
});

const form = useForm({
    // Reflects the raw switch, not the computed visibility: a switch that is on while results are
    // unpublished must stay visibly on, or the operator flips it twice wondering why nothing happened.
    public_schedule_visible: props.visibility.schedule,
    public_results_visible: props.visibility.results,
    public_ranking_visible: props.visibility.ranking,
});

const rows = computed(() => [
    {
        key: 'public_schedule_visible', state: 'schedule', link: props.links.schedule,
        label: 'Schedule', hint: 'Day, reporting time, start time and stage for every scheduled item.',
        blocked: props.counts.scheduled_items ? null : 'Nothing is scheduled yet, so this page would be empty.',
    },
    {
        key: 'public_results_visible', state: 'results', link: props.links.results,
        label: 'Results', hint: 'Placings and grades for items whose results are published. Marks are never shown.',
        blocked: props.counts.published_items ? null : 'No item results are published yet.',
    },
    {
        key: 'public_ranking_visible', state: 'ranking', link: props.links.ranking,
        label: 'Sahodaya ranking', hint: 'Points and medal counts per Sahodaya, with a per-school drill-down.',
        blocked: props.event.results_published ? null : 'Results are not published for this event yet.',
    },
]);

function save() {
    form.post(props.actionUrls.save, { preserveScroll: true });
}
</script>
