<template>
    <div>
        <div v-if="subjects.length" class="flex flex-wrap gap-2">
            <button v-for="s in subjects" :key="s.id"
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-sm transition"
                    :class="isSelected(s.id)
                        ? 'border-[#041525] bg-[#041525] text-white'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                    @click="toggle(s.id)">
                <span v-if="isSelected(s.id)">✓</span>
                {{ s.label }}
            </button>
        </div>
        <p v-else class="text-xs text-slate-400">
            No subjects configured yet. Ask your Sahodaya office to add subjects under Membership → Subject Master.
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const selected = computed(() => props.modelValue || []);

function isSelected(id) {
    return selected.value.map(Number).includes(Number(id));
}

function toggle(id) {
    const current = selected.value.map(Number);
    const numId = Number(id);
    const next = current.includes(numId)
        ? current.filter((x) => x !== numId)
        : [...current, numId];
    emit('update:modelValue', next);
}
</script>
