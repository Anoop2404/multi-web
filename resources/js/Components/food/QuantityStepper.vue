<template>
    <div class="inline-flex h-9 items-center rounded-full border border-slate-200 bg-slate-50" :class="disabled ? 'opacity-50' : ''">
        <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-slate-600 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-30"
                :disabled="disabled || modelValue <= min" aria-label="Decrease quantity" @click="set(modelValue - 1)">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M5 12h14" /></svg>
        </button>
        <input type="number" class="w-9 border-0 bg-transparent p-0 text-center text-sm font-semibold tabular-nums text-slate-900 focus:outline-none focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
               :value="modelValue" :min="min" :max="max ?? undefined" :disabled="disabled" aria-label="Quantity"
               @input="onInput" @blur="clamp">
        <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-slate-600 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-30"
                :disabled="disabled || (max != null && modelValue >= max)" aria-label="Increase quantity" @click="set(modelValue + 1)">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
        </button>
    </div>
</template>

<script setup>
const props = defineProps({
    modelValue: { type: Number, default: 1 },
    min: { type: Number, default: 1 },
    max: { type: Number, default: null },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

function set(val) {
    let next = Number.isFinite(val) ? val : props.min;
    next = Math.max(props.min, next);
    if (props.max != null) next = Math.min(props.max, next);
    emit('update:modelValue', next);
}

function onInput(event) {
    const raw = event.target.value;
    if (raw === '') return;
    const parsed = parseInt(raw, 10);
    if (Number.isFinite(parsed)) emit('update:modelValue', parsed);
}

function clamp() {
    set(props.modelValue);
}
</script>
