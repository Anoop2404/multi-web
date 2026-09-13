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

        <!-- Dropdown panel. Teleported to <body> only when escape-overflow is set (e.g. a
             cell inside a horizontally-scrollable table, whose overflow-x-auto wrapper
             also clips vertically per the CSS overflow spec) so the menu can overlap
             ancestors that would otherwise cut it off; Teleport's `disabled` keeps every
             other caller's DOM exactly as before. -->
        <Teleport to="body" :disabled="!escapeOverflow">
            <div
                v-if="isOpen && !disabled"
                ref="menuRef"
                class="absolute left-0 top-full mt-1 min-w-full w-max max-w-sm bg-white border border-slate-200 rounded-lg shadow-lg z-50 overflow-hidden flex flex-col max-h-64"
                :style="escapeOverflow ? menuStyle : undefined"
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
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { genderLabel } from '@/support/festItemEligibility.js';

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
    // Teleports the menu to <body> and positions it with `position: fixed`, computed
    // from the trigger's own bounding rect, instead of `absolute` inside this component's
    // own DOM position. Needed inside a scrollable/overflow-clipped ancestor (e.g. a
    // horizontally-scrolling table) where a plain in-flow menu gets cut off for rows near
    // the clipping edge. Off by default — everywhere else keeps its exact current DOM.
    escapeOverflow: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref(null);
const searchInputRef = ref(null);
const menuRef = ref(null);
const menuStyle = ref({});

function normalizeOption(opt) {
    if (opt !== null && typeof opt === 'object') {
        const value = 'value' in opt ? opt.value : (opt.id ?? '');
        let rawLabel = 'label' in opt ? opt.label : (opt.name ?? opt.title ?? String(value));

        if (!('label' in opt) && (opt.category_label || opt.class_group || opt.age_group || opt.category || opt.gender)) {
            const metaParts = [];
            const cat = opt.category_label
                || (opt.age_group && opt.age_group !== 'open' ? String(opt.age_group).toUpperCase() : null)
                || (opt.class_group && opt.class_group !== 'open' ? String(opt.class_group).replace(/[_-]/g, ' ').toUpperCase() : null)
                || (opt.category && opt.category !== 'general' ? String(opt.category).replace(/[_-]/g, ' ') : null);
            if (cat && !rawLabel.toLowerCase().includes(cat.toLowerCase())) metaParts.push(cat);

            const g = genderLabel(opt.gender);
            if (g && !rawLabel.toLowerCase().includes(g.toLowerCase())) metaParts.push(g);

            if (metaParts.length) {
                rawLabel = `${rawLabel} — ${metaParts.join(' · ')}`;
            }
        }

        const codePrefix = opt.item_code && !('label' in opt) && !rawLabel.includes(`[${opt.item_code}]`) ? `[${opt.item_code}] ` : '';
        const label = `${codePrefix}${rawLabel}`;
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
    const inContainer = containerRef.value?.contains(event.target);
    const inMenu = menuRef.value?.contains(event.target);
    if (!inContainer && !inMenu) {
        isOpen.value = false;
    }
}

// Positions the (teleported) menu from the trigger's own rect, flipping above it when
// there isn't room below — otherwise a menu opened near the bottom of the viewport (e.g.
// the last row of a tall table) would render off-screen instead of overlapping content.
function updateMenuPosition() {
    if (!props.escapeOverflow || !containerRef.value) return;

    const rect = containerRef.value.getBoundingClientRect();
    const estimatedHeight = menuRef.value?.offsetHeight || 256;
    const spaceBelow = window.innerHeight - rect.bottom;
    const openUpward = spaceBelow < estimatedHeight + 8 && rect.top > spaceBelow;

    menuStyle.value = {
        position: 'fixed',
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        top: openUpward ? 'auto' : `${rect.bottom + 4}px`,
        bottom: openUpward ? `${window.innerHeight - rect.top + 4}px` : 'auto',
    };
}

function handleReposition() {
    if (isOpen.value) updateMenuPosition();
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
    if (newVal && props.escapeOverflow) {
        nextTick(updateMenuPosition);
    }
});

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    if (props.escapeOverflow) {
        // capture: true so this also fires for scrolling inside an ancestor container
        // (e.g. the table's own overflow-x-auto wrapper), which doesn't bubble to window.
        window.addEventListener('scroll', handleReposition, true);
        window.addEventListener('resize', handleReposition);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('scroll', handleReposition, true);
    window.removeEventListener('resize', handleReposition);
});
</script>
