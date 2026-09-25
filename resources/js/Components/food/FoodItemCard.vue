<template>
    <div class="group relative flex flex-col justify-between rounded-2xl border transition-all duration-200 bg-white p-4"
         :class="[
             inOrder ? 'border-emerald-300 ring-2 ring-emerald-100 shadow-sm bg-gradient-to-b from-emerald-50/20 to-white' : 'border-slate-200/80 hover:border-slate-300 hover:shadow-md shadow-sm',
             muted ? 'opacity-60 bg-slate-50' : ''
         ]">
        
        <!-- Top header row: Veg badge + Corner slot (e.g. sort order or status) -->
        <div class="flex items-center justify-between gap-2 mb-2.5">
            <VegBadge :name="name" :description="description" show-label />
            <div v-if="$slots.corner" class="shrink-0">
                <slot name="corner" />
            </div>
        </div>

        <!-- Body: Icon + Details -->
        <div class="flex items-start gap-3.5 mb-3 flex-1 min-w-0">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center text-xl shrink-0 border border-amber-100/80 bg-gradient-to-br from-amber-50 to-orange-50 text-slate-800 shadow-xs"
                 aria-hidden="true">
                {{ icon }}
            </div>
            <div class="min-w-0 flex-1">
                <h4 class="text-sm font-bold text-slate-900 leading-snug line-clamp-2 group-hover:text-slate-950">{{ name }}</h4>
                <p v-if="description" class="text-xs text-slate-500 line-clamp-2 mt-1 leading-relaxed">{{ description }}</p>
                <div class="mt-2 flex items-baseline gap-1.5">
                    <span class="text-base font-extrabold text-slate-900 tracking-tight">₹{{ Number(price).toFixed(2) }}</span>
                </div>
            </div>
        </div>

        <!-- Badges (e.g. limit, order count, slots count) -->
        <div v-if="badges.length" class="flex flex-wrap gap-1.5 mb-3">
            <span v-for="b in badges" :key="b.label"
                  class="inline-flex items-center text-[10px] font-semibold px-2 py-0.5 rounded-full"
                  :class="badgeToneClass(b.tone)">
                {{ b.label }}
            </span>
        </div>

        <!-- Actions / Stepper slot -->
        <div v-if="$slots.actions" class="pt-3 border-t border-slate-100 mt-auto flex items-center justify-between gap-2">
            <slot name="actions-left" />
            <div class="ml-auto flex items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>

<script setup>
import VegBadge from '@/Components/food/VegBadge.vue';

defineProps({
    name: { type: String, required: true },
    description: { type: String, default: '' },
    price: { type: Number, required: true },
    icon: { type: String, default: '🍴' },
    muted: { type: Boolean, default: false },
    inOrder: { type: Boolean, default: false },
    badges: { type: Array, default: () => [] },
});

const TONE_CLASSES = {
    slate: 'bg-slate-100 text-slate-700',
    amber: 'bg-amber-100/80 text-amber-800 font-medium',
    emerald: 'bg-emerald-100 text-emerald-800 font-medium',
    indigo: 'bg-indigo-50 text-indigo-700 font-medium',
};

function badgeToneClass(tone) {
    return TONE_CLASSES[tone] || TONE_CLASSES.slate;
}
</script>
