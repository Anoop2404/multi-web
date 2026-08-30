<template>
    <div class="card" :class="dense ? 'p-4' : 'p-5'">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bill summary</p>
            <FoodBillStatusBadge v-if="status" :status="status" />
        </div>
        <div class="setup-progress" :class="dense ? 'mb-3' : ''">
            <div class="setup-progress-bar" :style="{ width: `${pct}%` }" />
        </div>
        <div class="grid grid-cols-3 gap-2 text-center">
            <div>
                <p class="font-bold text-slate-900" :class="dense ? 'text-base' : 'text-lg'">₹{{ total.toFixed(2) }}</p>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Total</p>
            </div>
            <div>
                <p class="font-bold text-emerald-700" :class="dense ? 'text-base' : 'text-lg'">₹{{ paid.toFixed(2) }}</p>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Paid</p>
            </div>
            <div>
                <p class="font-bold" :class="[dense ? 'text-base' : 'text-lg', balance > 0 ? 'text-amber-700' : 'text-slate-700']">₹{{ balance.toFixed(2) }}</p>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Balance</p>
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
