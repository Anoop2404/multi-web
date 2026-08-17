<template>
    <div class="card space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h4 class="section-title !mb-0 flex items-center gap-2 text-base">
                    <span>📊</span> Region Drill-Down
                </h4>
                <p class="section-desc mt-0.5">
                    Registration counts, results status &amp; key stats for every regional child event — without leaving this page.
                </p>
            </div>
            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
                {{ regions.length }} {{ regions.length === 1 ? 'Region' : 'Regions' }}
            </span>
        </div>

        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
            <div v-for="r in regions" :key="r.id"
                 class="rounded-xl border border-slate-200 bg-white p-4 space-y-3 hover:border-indigo-300 hover:shadow-md transition flex flex-col">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-bold text-slate-900 text-sm leading-snug">{{ r.label }}</p>
                        <p v-if="r.venue" class="text-[11px] text-slate-500 mt-0.5">📍 {{ r.venue }}</p>
                    </div>
                    <span class="text-[10px] font-mono font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border shrink-0"
                          :class="statusClass(r.status)">
                        {{ r.status }}
                    </span>
                </div>

                <!-- Key stats -->
                <div class="grid grid-cols-2 gap-2 text-center">
                    <div class="rounded-lg bg-slate-50 border border-slate-100 py-2">
                        <p class="text-lg font-black text-indigo-600">{{ r.registrations_count }}</p>
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Registrations</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 border border-slate-100 py-2">
                        <p class="text-lg font-black text-slate-700">{{ r.items_count }}</p>
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Items</p>
                    </div>
                    <template v-if="r.schools_count !== null">
                        <div class="rounded-lg bg-slate-50 border border-slate-100 py-2">
                            <p class="text-lg font-black text-emerald-600">{{ r.schools_count }}</p>
                            <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Schools</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 border border-slate-100 py-2">
                            <p class="text-lg font-black text-violet-600">{{ r.athletes_count }}</p>
                            <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Athletes</p>
                        </div>
                    </template>
                </div>

                <!-- Results status badge -->
                <div class="flex items-center justify-between text-[11px]">
                    <span class="font-semibold text-slate-500 uppercase tracking-wider text-[10px]">Public results</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border"
                          :class="r.results_published ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'">
                        {{ r.results_published ? 'Published' : 'Hidden' }}
                    </span>
                </div>

                <Link :href="`/sahodaya-admin/${sahodayaId}/events/${r.id}`"
                      class="btn-secondary text-xs justify-center !py-2 w-full mt-auto flex items-center gap-1.5">
                    <span>View region</span>
                    <span>→</span>
                </Link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    sahodayaId: { type: [String, Number], required: true },
    regions: { type: Array, default: () => [] },
});

function statusClass(status) {
    return {
        draft: 'bg-slate-100 text-slate-700 border-slate-200',
        published: 'bg-indigo-100 text-indigo-800 border-indigo-200',
        registration_open: 'bg-emerald-100 text-emerald-800 border-emerald-200',
        ongoing: 'bg-amber-100 text-amber-900 border-amber-200',
        completed: 'bg-violet-100 text-violet-800 border-violet-200',
        cancelled: 'bg-red-100 text-red-800 border-red-200',
    }[status] ?? 'bg-slate-100 text-slate-700 border-slate-200';
}
</script>
