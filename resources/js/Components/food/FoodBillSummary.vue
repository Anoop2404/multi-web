<template>
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden" :class="dense ? 'p-4' : 'p-5'">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <span class="text-base">💳</span>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-700">Financial Summary</p>
            </div>
            <FoodBillStatusBadge v-if="status" :status="status" />
        </div>

        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden mb-4 border border-slate-200/50">
            <div class="h-2 rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 transition-all duration-300"
                 :style="{ width: `${pct}%` }" />
        </div>

        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                <p class="font-extrabold text-slate-900" :class="dense ? 'text-base' : 'text-lg'">₹{{ total.toFixed(2) }}</p>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mt-0.5">Total Billed</p>
            </div>
            <div class="p-2.5 rounded-xl bg-emerald-50/60 border border-emerald-100">
                <p class="font-extrabold text-emerald-700" :class="dense ? 'text-base' : 'text-lg'">₹{{ paid.toFixed(2) }}</p>
                <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 mt-0.5">Amount Paid</p>
            </div>
            <div class="p-2.5 rounded-xl border"
                 :class="balance > 0 ? 'bg-rose-50/60 border-rose-100 text-rose-700' : 'bg-slate-50 border-slate-100 text-slate-700'">
                <p class="font-extrabold" :class="dense ? 'text-base' : 'text-lg'">₹{{ balance.toFixed(2) }}</p>
                <p class="text-[10px] font-bold uppercase tracking-wider mt-0.5"
                   :class="balance > 0 ? 'text-rose-700' : 'text-slate-500'">
                    Balance Due
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import FoodBillStatusBadge from '@/Components/food/FoodBillStatusBadge.vue';

const props = defineProps({
    total: { type: Number, required: true },
    paid: { type: Number, required: true },
    balance: { type: Number, required: true },
    status: { type: String, default: null },
    dense: { type: Boolean, default: false },
});

const pct = computed(() => (props.total > 0 ? Math.min(100, Math.max(0, (props.paid / props.total) * 100)) : 0));
</script>
