<template>
    <div class="min-h-screen bg-gray-50 flex flex-col">
        <header class="bg-white border-b border-gray-200 shrink-0">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide" :class="accentClass">{{ roleLabel }}</p>
                    <h1 class="text-lg font-bold text-gray-900 truncate">{{ title }}</h1>
                    <p v-if="subtitle" class="text-xs text-gray-500 truncate">{{ subtitle }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <SignOutButton class="text-xs text-red-600 hover:text-red-700 font-semibold px-2 py-1" />
                </div>
            </div>
            <nav v-if="navItems.length" class="max-w-5xl mx-auto px-4 sm:px-6 pb-2 flex flex-wrap gap-1">
                <Link v-for="item in navItems" :key="item.href"
                      :href="item.href"
                      class="text-xs font-semibold px-3 py-1.5 rounded-lg transition"
                      :class="isActive(item.href)
                          ? 'bg-indigo-600 text-white'
                          : 'text-gray-600 hover:bg-gray-100'">
                    {{ item.label }}
                </Link>
            </nav>
        </header>
        <FlashBanner class="max-w-5xl mx-auto px-4 sm:px-6 pt-3 w-full" />
        <main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 py-6">
            <slot />
        </main>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import SignOutButton from '@/Components/SignOutButton.vue';
import FlashBanner from '@/Components/ui/FlashBanner.vue';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    roleLabel: { type: String, required: true },
    accent: { type: String, default: 'indigo' },
    navItems: { type: Array, default: () => [] },
});

const page = usePage();

const accentClass = computed(() => ({
    indigo: 'text-indigo-600',
    amber: 'text-amber-600',
    emerald: 'text-emerald-700',
    violet: 'text-violet-600',
}[props.accent] ?? 'text-indigo-600'));

function isActive(href) {
    return page.url === href || page.url.startsWith(href + '/');
}
</script>
