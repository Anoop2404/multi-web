<template>
    <div class="program-card relative" :class="muted ? 'opacity-60' : ''">
        <div v-if="$slots.corner" class="absolute right-3 top-3">
            <slot name="corner" />
        </div>
        <div class="flex items-start gap-3">
            <div class="program-card-icon shrink-0 bg-[color:var(--brand-gold-soft)]" aria-hidden="true">{{ icon }}</div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-900">{{ name }}</p>
                <p v-if="description" class="line-clamp-2 text-xs text-slate-500">{{ description }}</p>
                <p class="mt-1 text-sm font-bold text-[color:var(--brand-navy)]">₹{{ price.toFixed(2) }}</p>
            </div>
        </div>
        <div v-if="badges.length" class="flex flex-wrap gap-1.5">
            <span v-for="b in badges" :key="b.label" class="status-pill" :class="badgeToneClass(b.tone)">{{ b.label }}</span>
        </div>
        <div v-if="$slots.actions" class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
            <slot name="actions" />
        </div>
    </div>
</template>

<script setup>
defineProps({
    name: { type: String, required: true },
    description: { type: String, default: '' },
    price: { type: Number, required: true },
    icon: { type: String, default: '🍴' },
    muted: { type: Boolean, default: false },
    badges: { type: Array, default: () => [] },
});

const TONE_CLASSES = {
    slate: 'bg-slate-100 text-slate-600',
    amber: 'bg-amber-50 text-amber-800',
    emerald: 'bg-emerald-50 text-emerald-700',
};

function badgeToneClass(tone) {
    return TONE_CLASSES[tone] || TONE_CLASSES.slate;
}
</script>
