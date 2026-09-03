<template>
    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-[14rem] flex-1 max-w-lg">
            <label :for="selectId" class="block text-xs font-semibold text-slate-600 mb-1.5">{{ label }}</label>
            <div class="flex gap-2">
                <SearchableSelect :id="selectId"
                                  :model-value="modelValue"
                                  :options="selectOptions"
                                  :placeholder="allItemsLabel"
                                  :search-placeholder="searchPlaceholder"
                                  :all-option="true"
                                  :all-label="allItemsLabel"
                                  class="flex-1 min-w-0"
                                  @update:model-value="onSelect" />
                <button v-if="showViewButton && modelValue && viewEnabled"
                        type="button"
                        class="shrink-0 inline-flex items-center justify-center h-[42px] w-10 rounded-xl border border-slate-200 text-slate-500 hover:text-indigo-700 hover:bg-indigo-50 transition"
                        title="View participants"
                        @click="emit('view', modelValue)">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    modelValue: { type: [String, Number], default: null },
    label: { type: String, default: 'Search items' },
    searchPlaceholder: { type: String, default: 'Type to filter items…' },
    allItemsLabel: { type: String, default: 'All items in this section' },
    showViewButton: { type: Boolean, default: false },
    viewEnabledFor: { type: Function, default: null },
    statusFor: { type: Function, default: null },
});

const emit = defineEmits(['update:modelValue', 'select', 'view']);

const selectId = `item-select-${Math.random().toString(36).slice(2, 9)}`;

const viewEnabled = computed(() => {
    if (!props.modelValue) return false;
    if (typeof props.viewEnabledFor === 'function') {
        return props.viewEnabledFor(props.modelValue);
    }
    return true;
});

// Humanizes a raw class_group/category value the same way other report
// pages already do (e.g. Attendance.vue's formatItemCat) — used only when
// the backend hasn't attached a ready-made category_label to the item yet.
function humanize(value) {
    return String(value).replace(/[_-]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function categoryFor(item) {
    if (item.category_label) return item.category_label;
    if (item.class_group && item.class_group !== 'open') return humanize(item.class_group).toUpperCase();
    if (item.category && item.category !== 'general') return humanize(item.category);
    return '';
}

function itemLabel(item) {
    const parts = [];
    const status = typeof props.statusFor === 'function' ? props.statusFor(item) : null;
    if (status) parts.push(status);
    let title = item.title;
    const category = categoryFor(item);
    if (category) title += ` — ${category}`;
    parts.push(title);
    if (item.participant_count) parts.push(`(${item.participant_count} reg.)`);
    return parts.join(' ');
}

// {value, label} pairs feed SearchableSelect directly — its own dropdown-internal
// search box replaces the separate always-visible free-text filter this component
// used to pair with a native <select>, so picking one item out of a catalog of 100+
// no longer needs scrolling a long native list to find a match.
const selectOptions = computed(() => props.items.map((item) => ({ value: item.id, label: itemLabel(item) })));

function onSelect(value) {
    const normalized = value || null;
    emit('update:modelValue', normalized);
    emit('select', normalized);
}
</script>
