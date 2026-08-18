<template>
    <div class="card space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h4 class="section-title !mb-0 flex items-center gap-2 text-base">
                    <span>🍽️</span> Food by Region
                </h4>
                <p class="section-desc mt-0.5">
                    This hub's own food data is always empty — schools order and pay against each region's own event below.
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
                    <span v-if="r.menu_items_count === 0"
                          class="text-[10px] font-mono font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border shrink-0 bg-amber-50 text-amber-700 border-amber-200">
                        No menu yet
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-2 text-center">
                    <div class="rounded-lg bg-slate-50 border border-slate-100 py-2">
                        <p class="text-lg font-black text-indigo-600">{{ r.menu_items_count }}</p>
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Menu items</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 border border-slate-100 py-2">
                        <p class="text-lg font-black text-slate-700">{{ r.bills_count }}</p>
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Bills</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 border border-slate-100 py-2">
                        <p class="text-lg font-black text-emerald-600">₹{{ Number(r.paid).toLocaleString('en-IN') }}</p>
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Collected</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 border border-slate-100 py-2">
                        <p class="text-lg font-black text-violet-600">{{ r.coupons_issued }}</p>
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Coupons</p>
                    </div>
                </div>

                <Link :href="`/sahodaya-admin/${sahodayaId}/events/${r.id}/${targetPath}`"
                      class="btn-secondary text-xs justify-center !py-2 w-full mt-auto flex items-center gap-1.5">
                    <span>Manage food</span>
                    <span>→</span>
                </Link>
            </div>
        </div>

        <div v-if="!regions.length" class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-slate-400 text-xs">
            No region partitions yet — set these up from the Levels &amp; Regions page.
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    sahodayaId: { type: [String, Number], required: true },
    regions: { type: Array, default: () => [] },
    /** Which food sub-page each region's "Manage food" link opens — e.g. 'food-menu', 'food-billing', 'food-coupons', 'catering'. */
    targetPath: { type: String, default: 'food-menu' },
});
</script>
