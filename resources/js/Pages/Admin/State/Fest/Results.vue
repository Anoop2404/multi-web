<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h2 class="text-sm font-bold text-slate-900">Results</h2>
            <p class="mb-4 mt-0.5 text-xs text-slate-500">
                Published item by item, not all at once — an item under appeal is held back without
                freezing the rest. A provisional result is visible here but not publicly.
            </p>

            <div class="mb-3 flex flex-wrap gap-2 text-xs">
                <button v-for="f in ['all', 'ready', 'provisional', 'published']" :key="f" type="button"
                        class="rounded-full px-3 py-1 font-semibold"
                        :class="filter === f ? 'bg-[color:var(--brand-navy)] text-white' : 'bg-slate-100 text-slate-600'"
                        @click="filter = f">{{ f }}</button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[48rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-2">Item</th>
                            <th class="py-2 px-2 text-center">Marks</th>
                            <th class="py-2 px-2 text-center">Ranked</th>
                            <th class="py-2 px-2">Status</th>
                            <th class="py-2 px-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="i in visible" :key="i.item_id" class="border-b border-slate-100">
                            <td class="py-2 pr-2">
                                <p class="font-medium text-slate-800">{{ i.title }}</p>
                                <p class="font-mono text-[11px] text-slate-400">{{ i.item_code }}</p>
                            </td>
                            <td class="py-2 px-2 text-center tabular-nums text-slate-600">{{ i.marks }}</td>
                            <td class="py-2 px-2 text-center tabular-nums">
                                {{ i.ranked || '—' }}
                                <span v-if="i.ties" class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">{{ i.ties }} tie</span>
                            </td>
                            <td class="py-2 px-2">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                                      :class="{
                                          'bg-slate-100 text-slate-600': i.status === 'draft',
                                          'bg-sky-50 text-sky-700': i.status === 'provisional',
                                          'bg-emerald-50 text-emerald-700': i.status === 'published',
                                          'bg-violet-50 text-violet-700': i.status === 'locked',
                                      }">{{ i.status }}</span>
                            </td>
                            <td class="py-2 px-2 text-right">
                                <span class="inline-flex gap-2 text-xs">
                                    <button v-if="!i.is_locked && i.marks" type="button" class="font-semibold text-[color:var(--brand-blue)] hover:underline" @click="act('compute', i)">Compute</button>
                                    <button v-if="i.status === 'provisional'" type="button" class="font-semibold text-emerald-700 hover:underline" @click="act('publish', i)">Publish</button>
                                    <button v-if="i.status === 'published'" type="button" class="text-slate-500 hover:underline" @click="act('unpublish', i)">Withdraw</button>
                                    <button v-if="i.status === 'published'" type="button" class="font-semibold text-violet-700 hover:underline" @click="act('lock', i)">Lock</button>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({ event: Object, events: Array, sahodayas: Array, permissions: Array, items: Array, actionUrls: Object });

const filter = ref('all');
const visible = computed(() => props.items.filter((i) => {
    if (filter.value === 'ready') return i.marks > 0 && i.status === 'draft';
    if (filter.value === 'provisional') return i.status === 'provisional';
    if (filter.value === 'published') return i.status === 'published' || i.status === 'locked';
    return true;
}));

function act(action, item) {
    // Withdrawing something already seen has to say why.
    const reason = action === 'unpublish' ? window.prompt('Why is this published result being withdrawn?') : null;
    if (action === 'unpublish' && !reason) return;

    router.post(props.actionUrls[action], { item_id: item.item_id, reason }, { preserveScroll: true });
}
</script>
