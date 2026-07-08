<template>
    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-[12rem] flex-1 max-w-md">
            <label :for="inputId" class="block text-xs font-semibold text-slate-600 mb-1.5">{{ label }}</label>
            <input :id="inputId"
                   v-model="query"
                   type="search"
                   :placeholder="searchPlaceholder"
                   autocomplete="off"
                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
        </div>
        <div class="min-w-[14rem] flex-1 max-w-lg">
            <label :for="selectId" class="block text-xs font-semibold text-slate-600 mb-1.5">Select item</label>
            <div class="flex gap-2">
                <select :id="selectId"
                        :value="modelValue ?? ''"
                        class="field flex-1 min-w-0"
                        @change="onSelect">
                    <option value="">{{ allItemsLabel }}</option>
                    <option v-for="item in filteredItems" :key="item.id" :value="item.id">
                        {{ itemLabel(item) }}
                    </option>
                </select>
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
            <p v-if="query && !filteredItems.length" class="text-xs text-amber-700 mt-1.5">No items match your search.</p>
            <p v-else-if="items.length > filteredItems.length" class="text-xs text-slate-500 mt-1.5">
                {{ filteredItems.length }} of {{ items.length }} items shown
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    modelValue: { type: [String, Number], default: null },
    label: { type: String, default: 'Search items' },
    searchPlaceholder: { type: String, default: 'Type to filter items…' },
    allItemsLabel: { type: String, default: 'All items in this section' },
    showViewButton: { type: Boolean, default: false },
    viewEnabledFor: { type: Function, default: null },
});

const emit = defineEmits(['update:modelValue', 'select', 'view']);

const query = ref('');
const inputId = `item-search-${Math.random().toString(36).slice(2, 9)}`;
const selectId = `item-select-${Math.random().toString(36).slice(2, 9)}`;

watch(() => props.items, () => { query.value = ''; });

const filteredItems = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.items;
    return props.items.filter((item) => {
        const haystack = [
            item.title,
            item.item_code,
            item.age_group,
            item.head_name,
        ].filter(Boolean).join(' ').toLowerCase();
        return haystack.includes(q);
    });
});

const viewEnabled = computed(() => {
    if (!props.modelValue) return false;
    if (typeof props.viewEnabledFor === 'function') {
        return props.viewEnabledFor(props.modelValue);
    }
    return true;
});

function itemLabel(item) {
    const parts = [item.title];
    if (item.participant_count) parts.push(`(${item.participant_count} reg.)`);
    return parts.join(' ');
}

function onSelect(event) {
    const value = event.target.value || null;
    emit('update:modelValue', value);
    emit('select', value);
}
</script>
