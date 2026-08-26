<template>
    <div ref="containerRef" class="relative w-full">
        <!-- Trigger button -->
        <button
            :id="id"
            type="button"
            class="form-input text-sm w-full bg-slate-50 border-slate-200 rounded-lg text-left flex items-center justify-between gap-2 px-3 py-2 border transition"
            :class="disabled ? 'opacity-60 cursor-not-allowed' : 'hover:bg-white cursor-pointer'"
            :disabled="disabled"
            @click="!disabled && (isOpen = !isOpen)"
        >
            <span class="truncate" :class="selectedLabel ? 'text-slate-900 font-medium' : 'text-slate-500'">
                {{ selectedLabel || placeholder }}
            </span>
            <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform" :class="{ 'rotate-180': isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Hidden native select: preserves HTML5 required/constraint validation on the surrounding form -->
        <select
            v-if="required"
            tabindex="-1"
            aria-hidden="true"
            class="sr-only absolute inset-0 h-0 w-0 opacity-0"
            :required="required"
            :value="modelValue ?? ''"
            @focus="!disabled && (isOpen = true)"
        >
            <option value="" disabled></option>
            <option v-for="opt in normalizedOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>

        <!-- Dropdown panel -->
        <div
            v-if="isOpen && !disabled"
            class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg z-50 overflow-hidden flex flex-col max-h-64"
        >
            <!-- Search input inside dropdown -->
            <div v-if="searchable" class="p-2 border-b border-slate-100 bg-slate-50/50">
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
                    :key="opt.value"
                    type="button"
                    class="w-full text-left text-xs px-2.5 py-2 rounded-md transition flex items-center justify-between"
                    :class="opt.disabled ? 'text-slate-300 cursor-not-allowed' : (isOptionSelected(opt) ? 'bg-indigo-50/80 text-indigo-700 font-bold hover:bg-indigo-50 hover:text-indigo-700' : 'text-slate-700 hover:bg-indigo-50 hover:text-indigo-700')"
                    :disabled="opt.disabled"
                    @click="!opt.disabled && selectOption(opt.value)"
                >
                    <span class="truncate">{{ opt.label }}</span>
                    <span v-if="isOptionSelected(opt)" class="text-indigo-600">✓</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: [String, Number, Boolean],
    // Accepts primitives (string/number), {id, name}, or {value, label} shaped items.
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Select…',
    },
    searchPlaceholder: {
        type: String,
        default: 'Type to search…',
    },
    // Show the inline search box. Defaults on everywhere; set false for the rare
    // case where search adds no value (e.g. a 2-option toggle).
    searchable: {
        type: Boolean,
        default: true,
    },
    // Defaults on for backward compatibility with existing filter-style usages;
    // new single-value form fields (status, gender, etc.) should pass :all-option="false".
    allOption: {
        type: Boolean,
        default: true,
    },
    allLabel: {
        type: String,
        default: 'All',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    id: {
        type: String,
        default: undefined,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref(null);
const searchInputRef = ref(null);

function normalizeOption(opt) {
    if (opt !== null && typeof opt === 'object') {
        const value = 'value' in opt ? opt.value : (opt.id ?? '');
        const label = 'label' in opt ? opt.label : (opt.name ?? String(value));
        return { value, label, disabled: !!opt.disabled };
    }
    return { value: opt, label: String(opt), disabled: false };
}

const normalizedOptions = computed(() => props.options.map(normalizeOption));

const selectedLabel = computed(() => {
    if ((props.modelValue === '' || props.modelValue === null || props.modelValue === undefined) && props.allOption) {
        return props.allLabel;
    }
    const found = normalizedOptions.value.find(o => String(o.value) === String(props.modelValue));
    return found ? found.label : '';
});

const filteredOptions = computed(() => {
    if (!props.searchable || !searchQuery.value.trim()) {
        return normalizedOptions.value;
    }
    const q = searchQuery.value.toLowerCase().trim();
    return normalizedOptions.value.filter(o => o.label.toLowerCase().includes(q));
});

function isOptionSelected(opt) {
    return String(opt.value) === String(props.modelValue);
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

watch(() => props.disabled, (isDisabled) => {
    if (isDisabled) isOpen.value = false;
});

watch(isOpen, (newVal) => {
    if (newVal && props.searchable) {
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
