<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Items and catalog</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        The State program's items. Slots shown here are the effective per-Sahodaya
                        allowance — change them on the Sahodaya Slots tab, where usage and history live.
                    </p>
                </div>
                <Link :href="slotsUrl" class="rounded-xl border border-[color:var(--brand-blue)]/30 px-3 py-2 text-xs font-semibold text-[color:var(--brand-blue)]">
                    Manage slots →
                </Link>
            </div>

            <input v-model="search" type="search" placeholder="Filter items…" class="mb-3 w-64 rounded-xl border border-slate-300 px-3 py-2 text-sm">

            <div class="overflow-x-auto">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-3">Code</th>
                            <th class="py-2 px-3">Item</th>
                            <th class="py-2 px-3">Category</th>
                            <th class="py-2 px-3">Type</th>
                            <th class="py-2 px-3 text-center">Slots each</th>
                            <th class="py-2 px-3 text-center">Fee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="i in visible" :key="i.id" class="border-b border-slate-100">
                            <td class="py-2 pr-3 font-mono text-xs text-slate-500">{{ i.item_code || '—' }}</td>
                            <td class="py-2 px-3 font-medium text-slate-800">{{ i.title }}</td>
                            <td class="py-2 px-3 text-slate-600">{{ i.class_group || '—' }}</td>
                            <td class="py-2 px-3 text-slate-600">
                                {{ i.participant_type || 'individual' }}
                                <span v-if="i.gender && i.gender !== 'open'" class="text-slate-400"> · {{ i.gender }}</span>
                            </td>
                            <td class="py-2 px-3 text-center">
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">{{ i.slots ?? '∞' }}</span>
                            </td>
                            <td class="py-2 px-3 text-center tabular-nums text-slate-600">{{ i.fee_amount != null ? '₹' + i.fee_amount : '—' }}</td>
                        </tr>
                        <tr v-if="!visible.length">
                            <td colspan="6" class="py-10 text-center text-sm text-slate-400">No items match.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-slate-400">{{ visible.length }} of {{ items.length }} items</p>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({ event: Object, events: Array, sahodayas: Array, permissions: Array, items: Array, slotsUrl: String });
const search = ref('');

const visible = computed(() => {
    const t = search.value.trim().toLowerCase();
    if (!t) return props.items;
    return props.items.filter((i) => (i.title || '').toLowerCase().includes(t) || (i.item_code || '').toLowerCase().includes(t));
});
</script>
