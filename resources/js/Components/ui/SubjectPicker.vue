<template>
    <div class="space-y-2">
        <div class="flex flex-wrap gap-1.5">
            <span v-for="label in subjects" :key="label"
                  class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 text-xs font-medium px-2 py-1 rounded-full">
                {{ label }}
                <button type="button" class="text-slate-400 hover:text-red-600 leading-none" title="Remove"
                        @click="$emit('remove', label)">×</button>
            </span>
            <span v-if="!subjects?.length" class="text-xs text-slate-400 italic py-1">No subjects added yet.</span>
        </div>

        <div class="flex flex-wrap gap-2 items-center">
            <select v-model="pickedLabel" class="form-input text-xs !w-auto" @change="addPicked">
                <option value="">+ Add existing subject…</option>
                <optgroup v-for="(group, category) in subjectsByCategory" :key="category" :label="categoryLabel(category)">
                    <option v-for="subj in group" :key="subj.id" :value="subj.label" :disabled="subjects?.includes(subj.label)">
                        {{ subj.label }}
                    </option>
                </optgroup>
            </select>
            <span class="text-xs text-slate-400">or</span>
            <input v-model="customLabel" type="text" maxlength="120" placeholder="Type a custom subject name…"
                   class="form-input text-xs !w-48" @keydown.enter.prevent="addCustom">
            <button type="button" class="btn-secondary text-xs !min-h-0 !px-2 !py-1" @click="addCustom">Add</button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    subjects: { type: Array, default: () => [] },
    subjectsByCategory: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['add', 'remove']);

const pickedLabel = ref('');
const customLabel = ref('');

const CATEGORY_LABELS = {
    language: 'Languages',
    science: 'Science electives',
    commerce: 'Commerce electives',
    humanities: 'Humanities electives',
    skill: 'Skill subjects',
    other: 'Other',
};

function categoryLabel(category) {
    return CATEGORY_LABELS[category] ?? (category ? category[0].toUpperCase() + category.slice(1) : 'Other');
}

function addPicked() {
    const label = pickedLabel.value.trim();
    pickedLabel.value = '';
    if (!label || props.subjects?.includes(label)) return;
    emit('add', label);
}

function addCustom() {
    const label = customLabel.value.trim();
    customLabel.value = '';
    if (!label || props.subjects?.includes(label)) return;
    emit('add', label);
}
</script>
