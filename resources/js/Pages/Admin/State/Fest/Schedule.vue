<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Item schedule</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Reporting time is when participants must be at the green room; start is when
                        the item begins. Finish is worked out from the duration unless you set it.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600">{{ scheduled }} of {{ items.length }} scheduled</span>
                    <label class="inline-flex items-center gap-1.5 text-slate-600">
                        <input v-model="onlyUnscheduled" type="checkbox" class="rounded border-slate-300"> Only unscheduled
                    </label>
                </div>
            </div>

            <p v-if="!venues.length" class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                No stages or rooms yet — add them under Venues before scheduling.
            </p>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[60rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-2">Item</th>
                            <th class="py-2 px-2 text-center">Entries</th>
                            <th class="py-2 px-2">Date</th>
                            <th class="py-2 px-2">Report</th>
                            <th class="py-2 px-2">Start</th>
                            <th class="py-2 px-2">Finish</th>
                            <th class="py-2 px-2">Stage</th>
                            <th class="py-2 px-2 text-center">Public</th>
                            <th class="py-2 px-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="i in visible" :key="i.item_id" class="border-b border-slate-100">
                            <td class="py-2 pr-2">
                                <p class="font-medium text-slate-800">{{ i.title }}</p>
                                <p class="font-mono text-[11px] text-slate-400">{{ i.item_code }}</p>
                            </td>
                            <td class="py-2 px-2 text-center tabular-nums text-slate-600">{{ i.participants }}</td>
                            <td class="py-2 px-2"><input v-model="draft(i).scheduled_on" type="date" class="inp"></td>
                            <td class="py-2 px-2"><input v-model="draft(i).reporting_at" type="time" class="inp w-24"></td>
                            <td class="py-2 px-2"><input v-model="draft(i).starts_at" type="time" class="inp w-24"></td>
                            <td class="py-2 px-2"><input v-model="draft(i).ends_at" type="time" class="inp w-24"></td>
                            <td class="py-2 px-2">
                                <select v-model="draft(i).venue_id" class="inp">
                                    <option :value="null">—</option>
                                    <option v-for="v in venues" :key="v.id" :value="v.id">{{ v.name }}</option>
                                </select>
                            </td>
                            <td class="py-2 px-2 text-center">
                                <input v-model="draft(i).is_public" type="checkbox" class="rounded border-slate-300">
                            </td>
                            <td class="py-2 px-2 text-right">
                                <button type="button" class="text-xs font-semibold text-[color:var(--brand-blue)] hover:underline" @click="save(i)">Save</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    items: Array, scheduled: Number, venues: Array, actionUrls: Object,
});

const onlyUnscheduled = ref(false);
const drafts = reactive({});

const visible = computed(() => props.items.filter((i) => !onlyUnscheduled.value || !i.is_scheduled));

function draft(item) {
    if (!drafts[item.item_id]) {
        drafts[item.item_id] = {
            scheduled_on: item.scheduled_on, reporting_at: item.reporting_at,
            starts_at: item.starts_at, ends_at: item.ends_at,
            venue_id: item.venue_id, is_public: item.is_public,
            duration_minutes: item.duration_minutes,
        };
    }
    return drafts[item.item_id];
}

function save(item) {
    router.post(props.actionUrls.save, { item_id: item.item_id, ...draft(item) }, { preserveScroll: true, preserveState: true });
}
</script>

<style scoped>
.inp { width: 100%; border-radius: 0.5rem; border: 1px solid rgb(203 213 225); padding: 0.25rem 0.5rem; font-size: 0.8125rem; }
</style>
