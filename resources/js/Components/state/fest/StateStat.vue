<template>
    <div class="rounded-xl border px-3 py-2.5"
         :class="{
             'border-slate-200 bg-slate-50/60': !tone && !emphasis,
             'border-[color:var(--brand-navy)]/20 bg-[color:var(--brand-navy)]/5': emphasis && !tone,
             'border-amber-200 bg-amber-50': tone === 'warn',
             'border-emerald-200 bg-emerald-50': tone === 'ok',
         }">
        <p class="text-[10px] font-bold uppercase tracking-wider"
           :class="tone === 'warn' ? 'text-amber-700' : tone === 'ok' ? 'text-emerald-700' : 'text-slate-500'">
            {{ label }}
        </p>
        <p class="mt-0.5 text-xl font-extrabold tabular-nums"
           :class="tone === 'warn' ? 'text-amber-900' : tone === 'ok' ? 'text-emerald-900' : 'text-slate-900'">
            {{ value ?? '—' }}
        </p>
    </div>
</template>

<script setup>
defineProps({
    label: String,
    value: [Number, String],
    tone: { type: String, default: null },
    emphasis: { type: Boolean, default: false },
});
</script>
