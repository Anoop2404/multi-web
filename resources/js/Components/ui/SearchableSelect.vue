<template>
    <div ref="containerRef" class="relative w-full">
        <!-- Trigger button -->
        <button
            :id="id"
            type="button"
            ref="triggerRef"
            aria-haspopup="listbox"
            :aria-label="ariaLabel"
            :aria-expanded="isOpen"
            :aria-controls="menuId"
            @keydown="onTriggerKeydown"
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
                class="absolute left-0 top-full mt-1 w-max max-w-sm bg-white border border-slate-200 rounded-lg shadow-lg z-50 overflow-hidden flex flex-col max-h-64"
                :id="menuId"
                role="listbox"
                :class="escapeOverflow ? '' : 'min-w-full'"
                @keydown="onMenuKeydown"
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
                        role="combobox"
                        :aria-expanded="isOpen"
                        :aria-controls="menuId"
                        aria-label="Search options"
                    />
                </div>

                <!-- Options list -->
                <div class="overflow-y-auto overscroll-contain min-h-0 flex-1 p-1">
                    <button
                        v-if="allOption"
                        data-select-option
                        role="option"
                        :aria-selected="!modelValue"
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
                        data-select-option
                        role="option"
                        :aria-selected="isOptionSelected(opt)"
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
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount, useId } from 'vue';
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
    ariaLabel: { type: String, default: undefined },
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
const triggerRef = ref(null);
const menuId = `select-${useId()}`;
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
    triggerRef.value?.focus({ preventScroll: true });
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
    // A menu teleported out of a dialog must stay above that dialog's backdrop.
    let layer = 50;
    for (let ancestor = containerRef.value; ancestor; ancestor = ancestor.parentElement) {
        const z = Number.parseInt(window.getComputedStyle(ancestor).zIndex, 10);
        if (Number.isFinite(z)) layer = Math.max(layer, z + 1);
    }
    const gap = 8;
    const spaceBelow = window.innerHeight - rect.bottom - gap;
    const spaceAbove = rect.top - gap;
    const openUpward = spaceBelow < 256 && spaceAbove > spaceBelow;
    const width = Math.min(Math.max(rect.width, 160), window.innerWidth - gap * 2);
    menuStyle.value = {
        position: 'fixed',
        zIndex: layer,
        marginTop: '0',
        minWidth: '0',
        maxWidth: `${window.innerWidth - gap * 2}px`,
        maxHeight: `${Math.max(0, Math.min(256, openUpward ? spaceAbove : spaceBelow))}px`,
        left: `${Math.max(gap, Math.min(rect.left, window.innerWidth - width - gap))}px`,
        width: `${width}px`,
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

function optionButtons() {
    return [...(menuRef.value?.querySelectorAll('[data-select-option]:not(:disabled)') ?? [])];
}

// Scroll only the option list, never the document or the surrounding marks table.
function revealOption(option) {
    const list = option?.parentElement;
    if (!list) return;
    const bounds = list.getBoundingClientRect();
    const target = option.getBoundingClientRect();
    if (target.top < bounds.top) list.scrollTop -= bounds.top - target.top;
    else if (target.bottom > bounds.bottom) list.scrollTop += target.bottom - bounds.bottom;
}

function onTriggerKeydown(event) {
    if (props.disabled || !['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    isOpen.value = true;
    nextTick(() => {
        const options = optionButtons();
        const target = event.key === 'End' || event.key === 'ArrowUp' ? options.at(-1) : options[0];
        target?.focus({ preventScroll: true });
        revealOption(target);
    });
}

function onMenuKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        isOpen.value = false;
        triggerRef.value?.focus({ preventScroll: true });
        return;
    }
    if (event.key === 'Tab') { isOpen.value = false; return; }
    const options = optionButtons();
    if (event.key === 'Enter' && event.target === searchInputRef.value) {
        event.preventDefault();
        const firstMatch = props.allOption && searchQuery.value.trim() ? options[1] : options[0];
        firstMatch?.click();
        return;
    }
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
    if (event.target === searchInputRef.value && ['Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const current = options.indexOf(document.activeElement);
    const index = event.key === 'Home' ? 0 : event.key === 'End' ? options.length - 1
        : event.key === 'ArrowDown' ? Math.min(options.length - 1, current + 1)
        : current < 0 ? options.length - 1 : Math.max(0, current - 1);
    options[index]?.focus({ preventScroll: true });
    revealOption(options[index]);
}

watch(isOpen, async (newVal) => {
    if (!newVal) { searchQuery.value = ''; return; }
    await nextTick();
    updateMenuPosition();
    await nextTick();
    if (props.searchable) searchInputRef.value?.focus({ preventScroll: true });
    else menuRef.value?.querySelector('[aria-selected="true"]')?.focus({ preventScroll: true });
    revealOption(menuRef.value?.querySelector('[aria-selected="true"]'));
});

watch(filteredOptions, () => { if (isOpen.value) nextTick(updateMenuPosition); });

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
