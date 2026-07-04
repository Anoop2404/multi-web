<template>
    <nav v-if="items.length" aria-label="Breadcrumb" class="breadcrumb-trail text-xs text-slate-500 mb-3">
        <ol class="flex flex-wrap items-center gap-1">
            <li v-for="(item, index) in items" :key="index" class="flex items-center gap-1 min-w-0">
                <span v-if="index > 0" class="text-slate-300 shrink-0">/</span>
                <Link v-if="item.href && index < items.length - 1"
                      :href="item.href"
                      class="truncate hover:text-[#0f3d7a] hover:underline">
                    {{ item.label }}
                </Link>
                <span v-else class="truncate font-medium text-slate-700">{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: { type: Array, default: () => [] },
});
</script>
