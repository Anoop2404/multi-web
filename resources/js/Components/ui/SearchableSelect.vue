<template>
    <div ref="containerRef" class="relative w-full">
        <!-- Trigger button -->
        <button
            type="button"
            class="form-input text-sm w-full bg-slate-50 border-slate-200 rounded-lg text-left flex items-center justify-between gap-2 px-3 py-2 border hover:bg-white transition cursor-pointer"
            @click="isOpen = !isOpen"
        >
            <span class="truncate" :class="selectedLabel ? 'text-slate-900 font-medium' : 'text-slate-500'">
                {{ selectedLabel || placeholder }}
            </span>
            <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform" :class="{ 'rotate-180': isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown panel -->
        <div
            v-if="isOpen"
            class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg z-50 overflow-hidden flex flex-col max-h-64"
        >
            <!-- Search input inside dropdown -->
            <div class="p-2 border-b border-slate-100 bg-slate-50/50">
                <input
                    ref="searchInputRef"
                    v-model="searchQuery"
                    type="text"
                    class="w-full text-xs px-2.5 py-1.5 bg-white border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                    :placeholder="searchPlaceholder"
                    @click.stop
                />
            </div>

            <!-- Options list -->
            <div class="overflow-y-auto flex-1 p-1">
                <button
                    v-if="allOption"
                    type="button"
                    class="w-full text-left text-xs px-2.5 py-2 rounded-md hover:bg-indigo-50 hover:text-indigo-700 transition flex items-center justify-between"
                    :class="!modelValue ? 'bg-indigo-50/80 text-indigo-700 font-bold' : 'text-slate-700'"
                    @click="selectOption('')"
                >
                    <span>{{ allLabel }}</span>
                    <span v-if="!modelValue" class="text-indigo-600">✓</span>
                </button>

                <div v-if="filteredOptions.length === 0" class="p-3 text-center text-xs text-slate-400">
                    No results match "{{ searchQuery }}"
                </div>

                <button
                    v-for="opt in filteredOptions"
                    :key="opt.id ?? opt.value"
                    type="button"
                    class="w-full text-left text-xs px-2.5 py-2 rounded-md hover:bg-indigo-50 hover:text-indigo-700 transition flex items-center justify-between"
                    :class="isOptionSelected(opt) ? 'bg-indigo-50/80 text-indigo-700 font-bold' : 'text-slate-700'"
                    @click="selectOption(opt.id ?? opt.value)"
                >
                    <span class="truncate">{{ opt.name ?? opt.label }}</span>
                    <span v-if="isOptionSelected(opt)" class="text-indigo-600">✓</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: [String, Number],
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Select school…',
    },
    searchPlaceholder: {
        type: String,
        default: 'Type school name to search…',
    },
    allOption: {
        type: Boolean,
        default: true,
    },
    allLabel: {
        type: String,
        default: 'All member schools',
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref(null);
const searchInputRef = ref(null);

const selectedLabel = computed(() => {
    if (!props.modelValue && props.allOption) {
        return props.allLabel;
    }
    const found = props.options.find(o => String(o.id ?? o.value) === String(props.modelValue));
    return found ? (found.name ?? found.label) : '';
});

const filteredOptions = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.options;
    }
    const q = searchQuery.value.toLowerCase().trim();
    return props.options.filter(o => {
        const text = String(o.name ?? o.label ?? '').toLowerCase();
        return text.includes(q);
    });
});

function isOptionSelected(opt) {
    return String(opt.id ?? opt.value) === String(props.modelValue);
}

function selectOption(val) {
    emit('update:modelValue', val);
    emit('change', val);
    isOpen.value = false;
    searchQuery.value = '';
}

function handleClickOutside(event) {
    if (containerRef.value && !containerRef.value.contains(event.target)) {
        isOpen.value = false;
    }
}

watch(isOpen, (newVal) => {
    if (newVal) {
        nextTick(() => searchInputRef.value?.focus());
    } else {
        searchQuery.value = '';
    }
});

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>
