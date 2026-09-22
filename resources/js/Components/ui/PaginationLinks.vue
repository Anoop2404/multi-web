<template>
    <div v-if="visible" class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3">
        <p v-if="meta" class="text-xs text-gray-500">
            Showing {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of {{ meta.total ?? 0 }}
        </p>
        <nav class="flex flex-wrap gap-1" :class="meta ? '' : 'ml-auto'" aria-label="Pagination">
            <Link
                v-for="link in links"
                :key="link.label"
                :href="link.url || '#'"
                preserve-scroll preserve-state
                class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg px-2.5 py-1 text-sm"
                :class="link.active ? 'bg-[#0f3d7a] text-white' : link.url ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300 pointer-events-none'"
                :aria-current="link.active ? 'page' : undefined"
                v-html="link.label"
            />
        </nav>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    links: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
});

const visible = computed(() => (props.meta?.last_page ?? 0) > 1 || (props.links?.length ?? 0) > 3);
</script>
