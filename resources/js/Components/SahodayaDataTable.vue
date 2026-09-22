<template>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div v-if="$slots.toolbar" class="px-4 py-3 border-b border-gray-100 bg-gray-50/50">
            <slot name="toolbar" />
        </div>

        <div v-if="hasRows && showMobileHint" class="flex items-center gap-1.5 border-b border-sky-100 bg-sky-50/70 px-4 py-2 text-[11px] font-medium text-sky-800 sm:hidden">
            <span aria-hidden="true">↔</span>
            Swipe sideways to see every column
        </div>

        <div class="overflow-x-auto overscroll-x-contain" tabindex="0" :aria-label="`${label} table. Scroll horizontally to see more columns.`">
            <table class="w-full text-sm" :class="minWidthClass">
                <caption class="sr-only">{{ label }}</caption>
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th v-for="col in columns" :key="col.key"
                            class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap"
                            :class="[col.align === 'right' ? 'text-right' : '', col.class]">
                            <button v-if="col.sortable"
                                    type="button"
                                    @click="emit('sort', col.key)"
                                    class="inline-flex items-center gap-1 hover:text-[#0f3d7a] transition"
                                    :class="sort === col.key ? 'text-[#0f3d7a]' : ''"
                                    :aria-label="`Sort by ${col.label}${sort === col.key ? `, currently ${dir === 'asc' ? 'ascending' : 'descending'}` : ''}`">
                                {{ col.label }}
                                <span v-if="sort === col.key" class="text-[10px]" aria-hidden="true">{{ dir === 'asc' ? '↑' : '↓' }}</span>
                            </button>
                            <span v-else>{{ col.label }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <slot />
                </tbody>
            </table>
        </div>

        <div v-if="!hasRows" class="px-4 py-12 text-center">
            <div class="text-3xl" aria-hidden="true">{{ emptyIcon }}</div>
            <p class="mt-2 text-sm font-semibold text-slate-600">{{ empty }}</p>
            <p v-if="emptyDescription" class="mx-auto mt-1 max-w-md text-xs leading-5 text-slate-400">{{ emptyDescription }}</p>
            <slot name="empty-actions" />
        </div>

        <div v-if="meta?.last_page > 1 || (links?.length > 3)" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-gray-100">
            <p v-if="meta" class="text-xs text-gray-500">
                Showing {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of {{ meta.total ?? 0 }}
            </p>
            <div class="flex flex-wrap gap-1 ml-auto">
                <Link v-for="link in links" :key="link.label"
                      :href="link.url || '#'"
                      preserve-scroll preserve-state
                      class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg px-2.5 py-1 text-sm"
                      :class="link.active ? 'bg-[#0f3d7a] text-white' : link.url ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300 pointer-events-none'"
                      :aria-current="link.active ? 'page' : undefined"
                      v-html="link.label" />
            </div>
        </div>
        <div v-else-if="meta?.total" class="px-4 py-2 border-t border-gray-100 text-xs text-gray-400 text-center">
            Showing all {{ meta.total }} record{{ meta.total === 1 ? '' : 's' }}
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    columns: { type: Array, required: true },
    links:   { type: Array, default: () => [] },
    meta:    { type: Object, default: null },
    sort:    { type: String, default: null },
    dir:     { type: String, default: 'asc' },
    empty:   { type: String, default: 'No records found.' },
    emptyDescription: { type: String, default: '' },
    emptyIcon: { type: String, default: '📋' },
    hasRows: { type: Boolean, default: true },
    label: { type: String, default: 'Records' },
    minWidthClass: { type: String, default: 'min-w-[42rem]' },
    showMobileHint: { type: Boolean, default: true },
});

const emit = defineEmits(['sort']);
</script>
