<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Certificates</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Eligibility is computed, not assumed — anyone who cannot receive one is listed
                        with the reason. Each certificate records the result it was printed from.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-bold text-slate-600">{{ tally.total }} issued</span>
                    <span v-if="tally.stale" class="rounded-full bg-rose-50 px-2.5 py-1 font-bold text-rose-700">{{ tally.stale }} stale</span>
                    <button type="button" class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600 hover:bg-slate-200" @click="detectStale">
                        Check for stale
                    </button>
                </div>
            </div>

            <div class="mb-3 flex flex-wrap gap-2">
                <select v-model="f.type" class="inp" @change="apply">
                    <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
                </select>
                <select v-model="f.sahodaya_id" class="inp" @change="apply">
                    <option :value="null">All Sahodayas</option>
                    <option v-for="s in sahodayas" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <select v-model="f.item_id" class="inp" @change="apply">
                    <option :value="null">All items</option>
                    <option v-for="i in items" :key="i.id" :value="i.id">{{ i.item_code }} — {{ i.title }}</option>
                </select>
                <button type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white" @click="generate">
                    Generate for {{ eligibleCount }} eligible
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-2">Recipient</th>
                            <th class="py-2 px-2">Sahodaya</th>
                            <th class="py-2 px-2">School</th>
                            <th class="py-2 px-2">Item</th>
                            <th class="py-2 px-2 text-center">Position</th>
                            <th class="py-2 px-2">Certificate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in eligibility" :key="r.participant_id" class="border-b border-slate-100"
                            :class="{ 'opacity-60': !r.eligible }">
                            <td class="py-1.5 pr-2 font-medium text-slate-800">{{ r.name }}</td>
                            <td class="py-1.5 px-2 text-slate-600">{{ r.sahodaya }}</td>
                            <td class="py-1.5 px-2 text-slate-600">{{ r.school }}</td>
                            <td class="py-1.5 px-2 font-mono text-xs text-slate-500">{{ r.item_code }}</td>
                            <td class="py-1.5 px-2 text-center tabular-nums">{{ r.position ?? '—' }}</td>
                            <td class="py-1.5 px-2 text-xs">
                                <span v-if="r.certificate_number" class="font-mono"
                                      :class="r.certificate_status === 'stale' ? 'text-rose-600' : 'text-emerald-700'">
                                    {{ r.certificate_number }}
                                    <span v-if="r.certificate_status === 'stale'" class="font-sans font-bold"> — stale</span>
                                </span>
                                <span v-else-if="!r.eligible" class="text-slate-500">{{ r.reason }}</span>
                                <span v-else class="text-slate-400">not generated</span>
                            </td>
                        </tr>
                        <tr v-if="!eligibility.length"><td colspan="6" class="py-10 text-center text-sm text-slate-400">Nothing matches.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="batches.length" class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h2 class="mb-2 text-sm font-bold text-slate-900">Generation batches</h2>
            <ul class="space-y-1 text-xs text-slate-600">
                <li v-for="b in batches" :key="b.id" class="flex flex-wrap gap-x-2 border-b border-slate-100 pb-1">
                    <span class="font-mono text-slate-400">{{ b.at }}</span>
                    <span class="font-semibold text-slate-800">{{ types[b.type] || b.type }}</span>
                    <span>{{ b.generated }} of {{ b.requested }}</span>
                    <span class="text-slate-400">{{ b.scope }}</span>
                    <span v-if="b.by" class="text-slate-400">by {{ b.by }}</span>
                </li>
            </ul>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    types: Object, filters: Object, eligibility: Array, tally: Object, items: Array, batches: Array, actionUrls: Object, baseUrl: String,
});

const f = reactive({
    type: props.filters?.type ?? 'merit',
    sahodaya_id: props.filters?.sahodaya_id ?? null,
    item_id: props.filters?.item_id ?? null,
});

const eligibleCount = computed(() => props.eligibility.filter((r) => r.eligible).length);

function apply() {
    router.get(props.baseUrl, Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: false });
}
function generate() { router.post(props.actionUrls.generate, { ...f }, { preserveScroll: true }); }
function detectStale() { router.post(props.actionUrls.detectStale, {}, { preserveScroll: true }); }
</script>

<style scoped>
.inp { border-radius: 0.75rem; border: 1px solid rgb(203 213 225); padding: 0.5rem 0.75rem; font-size: 0.875rem; }
</style>
