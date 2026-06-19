<template>
    <div class="space-y-4">
        <template v-for="field in fields" :key="field.key">
            <!-- Repeater -->
            <div v-if="field.type === 'repeater'" class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-semibold text-gray-600">{{ field.label }}</label>
                    <button type="button" @click="addRepeaterItem(field)"
                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">+ Add</button>
                </div>
                <div v-if="!model[field.key]?.length" class="text-xs text-gray-400 bg-gray-50 rounded-lg p-3 text-center">None added</div>
                <div v-for="(item, i) in model[field.key]" :key="i"
                     class="border border-gray-100 rounded-lg p-3 space-y-2">
                    <div class="grid sm:grid-cols-2 gap-2">
                        <div v-for="sub in field.fields" :key="sub.key">
                            <label class="block text-[10px] font-semibold text-gray-500 mb-0.5">{{ sub.label }}</label>
                            <input v-if="sub.type !== 'textarea'"
                                   v-model="item[sub.key]"
                                   :type="inputType(sub.type)"
                                   class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                            <textarea v-else v-model="item[sub.key]" rows="2"
                                      class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs"></textarea>
                        </div>
                    </div>
                    <button type="button" @click="model[field.key].splice(i, 1)"
                            class="text-xs text-red-400 hover:text-red-600">Remove</button>
                </div>
            </div>

            <!-- Academic years (nested repeater) -->
            <div v-else-if="field.type === 'academic_years'" class="space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-semibold text-gray-600">{{ field.label }}</label>
                    <button type="button" @click="addAcademicYear(field.key)"
                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">+ Add Year</button>
                </div>
                <div v-for="(yearBlock, yi) in model[field.key]" :key="yi"
                     class="border border-indigo-100 rounded-lg p-3 space-y-2 bg-indigo-50/20">
                    <div class="flex gap-2 items-center">
                        <input v-model="yearBlock.year" placeholder="2025-26"
                               class="border rounded-lg px-2 py-1.5 text-xs font-bold w-28">
                        <button type="button" @click="addYearLink(field.key, yi)"
                                class="text-xs text-indigo-600">+ Link</button>
                        <button type="button" @click="model[field.key].splice(yi, 1)"
                                class="ml-auto text-xs text-red-400">Remove year</button>
                    </div>
                    <div v-for="(link, li) in yearBlock.links" :key="li" class="grid grid-cols-12 gap-1 pl-2">
                        <input v-model="link.icon" placeholder="🔗" class="col-span-1 border rounded px-1 py-1 text-xs text-center bg-white">
                        <input v-model="link.label" placeholder="Label" class="col-span-4 border rounded px-2 py-1 text-xs bg-white">
                        <input v-model="link.url" placeholder="URL" class="col-span-6 border rounded px-2 py-1 text-xs bg-white">
                        <button type="button" @click="yearBlock.links.splice(li, 1)"
                                class="col-span-1 text-red-400 text-xs">✕</button>
                    </div>
                </div>
            </div>

            <!-- Textarea -->
            <div v-else-if="field.type === 'textarea' || field.type === 'wysiwyg'">
                <label class="block text-xs font-semibold text-gray-600 mb-1">{{ field.label }}</label>
                <textarea v-model="model[field.key]" rows="3"
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>

            <!-- Standard inputs -->
            <div v-else>
                <label class="block text-xs font-semibold text-gray-600 mb-1">{{ field.label }}</label>
                <input v-model="model[field.key]" :type="inputType(field.type)"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            </div>
        </template>
    </div>
</template>

<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
    fields:     { type: Array, default: () => [] },
    config:     { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:config']);

const model = reactive({ ...props.config });

watch(model, () => emit('update:config', { ...model }), { deep: true });

watch(() => props.config, (val) => {
    Object.keys(model).forEach(k => delete model[k]);
    Object.assign(model, val ?? {});
}, { deep: true });

function inputType(type) {
    if (type === 'email') return 'email';
    if (type === 'url') return 'url';
    if (type === 'tel') return 'tel';
    if (type === 'number') return 'number';
    return 'text';
}

function blankRepeaterItem(field) {
    const item = {};
    (field.fields ?? []).forEach(f => { item[f.key] = ''; });
    return item;
}

function addRepeaterItem(field) {
    if (!model[field.key]) model[field.key] = [];
    model[field.key].push(blankRepeaterItem(field));
}

function addAcademicYear(key) {
    if (!model[key]) model[key] = [];
    model[key].push({ year: '', links: [] });
}

function addYearLink(key, yi) {
    if (!model[key][yi].links) model[key][yi].links = [];
    model[key][yi].links.push({ label: '', url: '#', icon: '🔗' });
}

// Ensure repeater/academic_years keys exist
props.fields.forEach(field => {
    if (field.type === 'repeater' && !model[field.key]) model[field.key] = [];
    if (field.type === 'academic_years' && !model[field.key]) model[field.key] = [];
});
</script>
