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
            <SearchableSelect
                v-model="pickedLabel"
                :options="pickableSubjectOptions"
                :all-option="true"
                all-label="+ Add existing subject…"
                placeholder="+ Add existing subject…"
                class="!w-auto"
                @change="addPicked"
            />
            <span class="text-xs text-slate-400">or</span>
            <input v-model="customLabel" type="text" maxlength="120" placeholder="Type a custom subject name…"
                   class="form-input text-xs !w-48" @keydown.enter.prevent="addCustom">
            <button type="button" class="btn-secondary text-xs !min-h-0 !px-2 !py-1" @click="addCustom">Add</button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

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

// Flattens the grouped subjectsByCategory map into a single options list (SearchableSelect has no
// optgroup/disabled-option support), prefixing each label with its category and dropping subjects
// already added — selecting an already-added subject was already a no-op in addPicked().
const pickableSubjectOptions = computed(() => {
    const options = [];
    for (const [category, group] of Object.entries(props.subjectsByCategory ?? {})) {
        for (const subj of group) {
            if (props.subjects?.includes(subj.label)) continue;
            options.push({ value: subj.label, label: `${categoryLabel(category)}: ${subj.label}` });
        }
    }
    return options;
});

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
