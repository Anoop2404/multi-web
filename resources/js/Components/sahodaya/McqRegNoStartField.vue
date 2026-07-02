<template>
    <div class="space-y-2">
        <input
            :id="inputId"
            v-model.number="model"
            type="number"
            min="1"
            max="99999999"
            step="1"
            class="field"
            :disabled="disabled"
            placeholder="e.g. 10001"
        >
        <div v-if="!disabled" class="flex flex-wrap gap-1.5">
            <button
                v-for="preset in presets"
                :key="preset"
                type="button"
                class="text-xs px-2 py-1 rounded-md border border-slate-200 bg-slate-50 text-slate-600 hover:border-indigo-300 hover:text-indigo-700 transition"
                @click="model = preset"
            >
                {{ preset.toLocaleString() }}
            </button>
        </div>
        <p v-if="disabled" class="text-xs text-amber-700">Locked — hall tickets have already been issued.</p>
    </div>
</template>

<script setup>
const model = defineModel({ type: Number, default: 100 });

defineProps({
    inputId: { type: String, default: undefined },
    disabled: { type: Boolean, default: false },
});

const presets = [100, 1000, 10000];
</script>
