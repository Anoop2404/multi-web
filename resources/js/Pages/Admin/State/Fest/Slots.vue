<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Sahodaya slots</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        How many entries each Sahodaya may send per item. An item's figure applies to
                        every Sahodaya; an override applies to one.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600">{{ items.length }} items</span>
                    <span v-if="overrideCount" class="rounded-full bg-amber-50 px-2.5 py-1 font-semibold text-amber-700">{{ overrideCount }} overridden</span>
                    <span v-if="exceededCount" class="rounded-full bg-rose-50 px-2.5 py-1 font-semibold text-rose-700">{{ exceededCount }} over limit</span>
                </div>
            </div>

            <div class="mb-3 flex flex-wrap gap-2">
                <input v-model="search" type="search" placeholder="Filter items…"
                       class="w-56 rounded-xl border border-slate-300 px-3 py-2 text-sm">
                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input v-model="onlyActive" type="checkbox" class="rounded border-slate-300">
                    Only items with entries or overrides
                </label>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[46rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-3">Item</th>
                            <th class="py-2 px-3 text-center">Slots each</th>
                            <th class="py-2 px-3 text-center">Sahodayas</th>
                            <th class="py-2 px-3 text-center">Used</th>
                            <th class="py-2 px-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="item in visibleItems" :key="item.item_id">
                            <tr class="border-b border-slate-100 align-top">
                                <td class="py-2.5 pr-3">
                                    <p class="font-medium text-slate-800">{{ item.title }}</p>
                                    <p class="text-xs text-slate-400">
                                        <span v-if="item.item_code" class="font-mono">{{ item.item_code }}</span>
                                        <span v-if="item.class_group"> · {{ item.class_group }}</span>
                                        <span v-if="item.is_team" class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-600">team — one slot per entry</span>
                                    </p>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">
                                        {{ item.default_slots ?? 'Unlimited' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-center tabular-nums text-slate-600">{{ item.sahodayas_entered }}</td>
                                <td class="py-2.5 px-3 text-center tabular-nums"
                                    :class="item.exceeded ? 'font-bold text-rose-600' : 'text-slate-600'">
                                    {{ item.total_used }}
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <button type="button" class="text-xs font-semibold text-[color:var(--brand-blue)] hover:underline"
                                            @click="expanded = expanded === item.item_id ? null : item.item_id">
                                        {{ expanded === item.item_id ? 'Hide' : 'Manage' }}
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="expanded === item.item_id" class="border-b border-slate-100 bg-slate-50/60">
                                <td colspan="5" class="p-4">
                                    <div class="mb-4 flex flex-wrap items-end gap-2">
                                        <div>
                                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Slots for every Sahodaya</label>
                                            <input v-model.number="itemForm.slots" type="number" min="0" max="99"
                                                   class="w-32 rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Unlimited">
                                        </div>
                                        <div class="flex-1 min-w-[12rem]">
                                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Reason</label>
                                            <input v-model="itemForm.reason" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                                   placeholder="Recorded in the slot history">
                                        </div>
                                        <button type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white"
                                                @click="saveItem(item)">Apply to item</button>
                                    </div>

                                    <p v-if="!item.cells.length" class="text-xs text-slate-400">
                                        No Sahodaya has entered this item yet, and none has an override.
                                    </p>

                                    <table v-else class="w-full text-xs">
                                        <thead>
                                            <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400">
                                                <th class="py-1 pr-3">Sahodaya</th>
                                                <th class="py-1 px-2 text-center">Slots</th>
                                                <th class="py-1 px-2 text-center">Used</th>
                                                <th class="py-1 px-2 text-center">Available</th>
                                                <th class="py-1 px-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="cell in item.cells" :key="cell.sahodaya_id" class="border-t border-slate-200/70">
                                                <td class="py-1.5 pr-3">
                                                    {{ cell.sahodaya_name }}
                                                    <span v-if="cell.district" class="text-slate-400"> · {{ cell.district }}</span>
                                                    <span v-if="cell.is_override" class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 font-semibold text-amber-700">override</span>
                                                </td>
                                                <td class="py-1.5 px-2 text-center tabular-nums">{{ cell.slots ?? '∞' }}</td>
                                                <td class="py-1.5 px-2 text-center tabular-nums" :class="cell.exceeded ? 'font-bold text-rose-600' : ''">{{ cell.used }}</td>
                                                <td class="py-1.5 px-2 text-center tabular-nums">{{ cell.available ?? '∞' }}</td>
                                                <td class="py-1.5 px-2 text-right">
                                                    <input v-model.number="cellDraft[cell.sahodaya_id]" type="number" min="0" max="99"
                                                           class="w-16 rounded-lg border border-slate-300 px-2 py-1 text-xs"
                                                           :placeholder="String(item.default_slots ?? '∞')">
                                                    <button type="button" class="ml-1 font-semibold text-[color:var(--brand-blue)] hover:underline"
                                                            @click="saveCell(item, cell)">Set</button>
                                                    <button v-if="cell.is_override" type="button" class="ml-1 text-slate-400 hover:text-slate-700"
                                                            @click="clearCell(item, cell)">Clear</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Quota changes decide who competes, so the history is part of the screen, not an export. -->
        <section v-if="history.length" class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h2 class="mb-3 text-sm font-bold text-slate-900">Slot change history</h2>
            <ul class="space-y-1.5 text-xs">
                <li v-for="h in history" :key="h.id" class="flex flex-wrap gap-x-2 border-b border-slate-100 pb-1.5 text-slate-600">
                    <span class="font-mono text-slate-400">{{ h.at }}</span>
                    <span class="font-semibold text-slate-800">{{ h.scope === 'item' ? 'Item' : 'Sahodaya' }}</span>
                    <span>{{ h.slots_from ?? '∞' }} → {{ h.slots_to ?? '∞' }}</span>
                    <span v-if="h.by" class="text-slate-400">by {{ h.by }}</span>
                    <span v-if="h.reason" class="italic text-slate-500">“{{ h.reason }}”</span>
                </li>
            </ul>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    items: Array, history: Array, actionUrls: Object,
});

const search = ref('');
const onlyActive = ref(true);
const expanded = ref(null);
const cellDraft = reactive({});
const itemForm = reactive({ slots: null, reason: '' });

const visibleItems = computed(() => {
    const term = search.value.trim().toLowerCase();
    return props.items.filter((i) => {
        if (onlyActive.value && !i.total_used && !i.overrides) return false;
        if (!term) return true;
        return (i.title || '').toLowerCase().includes(term) || (i.item_code || '').toLowerCase().includes(term);
    });
});

const overrideCount = computed(() => props.items.reduce((a, i) => a + (i.overrides || 0), 0));
const exceededCount = computed(() => props.items.reduce((a, i) => a + (i.exceeded || 0), 0));

function saveItem(item) {
    router.post(props.actionUrls.setItem, {
        item_id: item.item_id,
        slots: itemForm.slots === '' ? null : itemForm.slots,
        reason: itemForm.reason || null,
    }, { preserveScroll: true, onSuccess: () => { itemForm.reason = ''; } });
}

function saveCell(item, cell) {
    const value = cellDraft[cell.sahodaya_id];
    router.post(props.actionUrls.setSahodaya, {
        item_id: item.item_id,
        sahodaya_id: cell.sahodaya_id,
        slots: value === '' || value === undefined ? null : value,
        reason: itemForm.reason || null,
    }, { preserveScroll: true });
}

function clearCell(item, cell) {
    router.post(props.actionUrls.setSahodaya, {
        item_id: item.item_id, sahodaya_id: cell.sahodaya_id, slots: null, reason: itemForm.reason || null,
    }, { preserveScroll: true });
}
</script>
