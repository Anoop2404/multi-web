<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative flex-1 max-w-md">
            <input v-model="localQuery"
                   type="search"
                   class="reports-search pl-10"
                   :placeholder="placeholder"
                   @input="$emit('update:query', localQuery)">
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">🔍</span>
        </div>
        <div v-if="categories?.length" class="flex flex-wrap gap-1.5">
            <button type="button"
                    class="reports-category-pill"
                    :class="{ 'reports-category-pill--active': !activeCategory }"
                    @click="$emit('update:category', null)">
                All
            </button>
            <button v-for="cat in categories" :key="cat.key"
                    type="button"
                    class="reports-category-pill"
                    :class="{ 'reports-category-pill--active': activeCategory === cat.key }"
                    @click="$emit('update:category', cat.key)">
                {{ cat.icon }} {{ cat.label }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    query: { type: String, default: '' },
    activeCategory: { type: String, default: null },
    categories: { type: Array, default: null },
    placeholder: { type: String, default: 'Search reports…' },
});

defineEmits(['update:query', 'update:category']);

const localQuery = ref(props.query);
watch(() => props.query, (v) => { localQuery.value = v; });
</script>
