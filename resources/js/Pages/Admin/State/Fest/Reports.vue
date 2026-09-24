<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section v-for="(label, key) in groups" :key="key" v-show="reportsIn(key).length"
                 class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h2 class="mb-3 text-sm font-bold text-slate-900">{{ label }}</h2>

            <ul class="divide-y divide-slate-100">
                <li v-for="r in reportsIn(key)" :key="r.id" class="flex flex-wrap items-start justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium" :class="r.available ? 'text-slate-800' : 'text-slate-400'">
                            {{ r.label }}
                        </p>
                        <p v-if="r.description" class="mt-0.5 text-xs text-slate-500">{{ r.description }}</p>
                        <!-- Says which phase it waits on, rather than rendering an empty table that
                             would read as "nobody registered" instead of "this does not exist yet". -->
                        <p v-else-if="!r.available" class="mt-0.5 text-xs text-amber-700">{{ r.blocked_by }}</p>
                    </div>

                    <div v-if="r.available" class="flex shrink-0 items-center gap-2">
                        <Link :href="`${baseUrl}/${r.id}`"
                              class="rounded-lg border border-[color:var(--brand-blue)]/30 px-3 py-1.5 text-xs font-semibold text-[color:var(--brand-blue)] hover:bg-[color:var(--brand-blue)]/10">
                            Open
                        </Link>
                        <a v-for="f in r.formats" :key="f" :href="`${baseUrl}/${r.id}/download?format=${f}`"
                           class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-[11px] font-bold uppercase text-slate-600 hover:bg-slate-200">
                            {{ f }}
                        </a>
                    </div>
                    <span v-else class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-400">
                        not yet
                    </span>
                </li>
            </ul>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    groups: Object, reports: Array, baseUrl: String,
});

function reportsIn(group) {
    return props.reports.filter((r) => r.group === group);
}
</script>
