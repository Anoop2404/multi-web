<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-bold text-slate-900">Attendance</h2>
                <div class="flex flex-wrap gap-2">
                    <select v-model="f.item_id" class="inp" @change="apply">
                        <option :value="null">All items</option>
                        <option v-for="i in items" :key="i.id" :value="i.id">{{ i.item_code }} — {{ i.title }}</option>
                    </select>
                    <select v-model="f.sahodaya_id" class="inp" @change="apply">
                        <option :value="null">All Sahodayas</option>
                        <option v-for="s in sahodayas" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
            </div>

            <div v-if="rows.length" class="mb-3 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                <span class="text-xs font-bold text-slate-700">Mark all as</span>
                <button v-for="s in statuses" :key="s" type="button"
                        class="rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100"
                        @click="setAll(s)">{{ s }}</button>
                <input v-model="reason" class="ml-auto min-w-[12rem] flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                       placeholder="Reason — recorded when a mark is changed">
                <button type="button" class="rounded-lg bg-[color:var(--brand-navy)] px-4 py-1.5 text-xs font-bold text-white" @click="save">Save</button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-2">Chest</th>
                            <th class="py-2 px-2">Item</th>
                            <th class="py-2 px-2">Participant</th>
                            <th class="py-2 px-2">Sahodaya</th>
                            <th class="py-2 px-2">School</th>
                            <th class="py-2 px-2">Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.id" class="border-b border-slate-100">
                            <td class="py-1.5 pr-2 font-mono text-xs">{{ r.chest_number || '—' }}</td>
                            <td class="py-1.5 px-2 font-mono text-xs text-slate-500">{{ r.item_code }}</td>
                            <td class="py-1.5 px-2 text-slate-700">{{ r.participants }}</td>
                            <td class="py-1.5 px-2 text-slate-600">{{ r.sahodaya }}</td>
                            <td class="py-1.5 px-2 text-slate-600">{{ r.school }}</td>
                            <td class="py-1.5 px-2">
                                <select v-model="draft[r.id]" class="inp w-36">
                                    <option :value="undefined">Not marked</option>
                                    <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </td>
                        </tr>
                        <tr v-if="!rows.length"><td colspan="6" class="py-10 text-center text-sm text-slate-400">No approved entries for this selection.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Corrections are the thing an appeal asks about, so they are on the screen. -->
        <section v-if="corrections.length" class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h2 class="mb-2 text-sm font-bold text-slate-900">Attendance corrections</h2>
            <ul class="space-y-1 text-xs text-slate-600">
                <li v-for="(c, i) in corrections" :key="i" class="border-b border-slate-100 pb-1">
                    <span class="font-mono text-slate-400">{{ c.at }}</span>
                    <span class="ml-1 font-mono">{{ c.item_code }}</span>
                    <span class="ml-1">{{ c.from }} → <span class="font-semibold">{{ c.to }}</span></span>
                    <span v-if="c.by" class="ml-1 text-slate-400">by {{ c.by }}</span>
                    <span v-if="c.reason" class="ml-1 italic">“{{ c.reason }}”</span>
                </li>
            </ul>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    rows: Array, statuses: Array, items: Array, filters: Object, corrections: Array, actionUrls: Object, baseUrl: String,
});

const f = reactive({ item_id: props.filters?.item_id ?? null, sahodaya_id: props.filters?.sahodaya_id ?? null });
const draft = reactive(Object.fromEntries(props.rows.map((r) => [r.id, r.status ?? undefined])));
const reason = ref('');

function apply() {
    router.get(props.baseUrl, Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: false });
}
function setAll(status) {
    props.rows.forEach((r) => { draft[r.id] = status; });
}
function save() {
    const statuses = Object.fromEntries(Object.entries(draft).filter(([, v]) => v));
    router.post(props.actionUrls.mark, { statuses, reason: reason.value || null }, { preserveScroll: true });
}
</script>

<style scoped>
.inp { border-radius: 0.5rem; border: 1px solid rgb(203 213 225); padding: 0.375rem 0.625rem; font-size: 0.8125rem; }
</style>
