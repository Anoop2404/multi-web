<template>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div v-if="$slots.toolbar" class="px-4 py-3 border-b border-gray-100 bg-gray-50/50">
            <slot name="toolbar" />
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th v-for="col in columns" :key="col.key"
                            class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap"
                            :class="[col.align === 'right' ? 'text-right' : '', col.class]">
                            <button v-if="col.sortable"
                                    type="button"
                                    @click="emit('sort', col.key)"
                                    class="inline-flex items-center gap-1 hover:text-[#0f3d7a] transition"
                                    :class="sort === col.key ? 'text-[#0f3d7a]' : ''">
                                {{ col.label }}
                                <span v-if="sort === col.key" class="text-[10px]">{{ dir === 'asc' ? '↑' : '↓' }}</span>
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

        <p v-if="!hasRows" class="px-4 py-12 text-center text-gray-400 text-sm">{{ empty }}</p>

        <div v-if="links?.length > 3" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-gray-100">
            <p v-if="meta" class="text-xs text-gray-500">
                Showing {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of {{ meta.total ?? 0 }}
            </p>
            <div class="flex flex-wrap gap-1 ml-auto">
                <Link v-for="link in links" :key="link.label"
                      :href="link.url || '#'"
                      class="px-3 py-1 rounded-lg text-sm min-w-[2rem] text-center"
                      :class="link.active ? 'bg-[#0f3d7a] text-white' : link.url ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300 pointer-events-none'"
                      v-html="link.label" />
            </div>
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
    hasRows: { type: Boolean, default: true },
});

const emit = defineEmits(['sort']);
</script>
