<template>
    <div class="min-h-screen bg-gray-100 flex">
        <div v-if="mobileNavOpen"
             class="fixed inset-0 z-40 bg-black/50 lg:hidden"
             @click="mobileNavOpen = false" />

        <!-- Sidebar -->
        <aside
            class="w-72 lg:w-64 bg-gray-900 text-gray-300 flex flex-col shrink-0
                   fixed inset-y-0 left-0 z-50 lg:sticky lg:top-0 h-screen
                   transition-transform duration-200 ease-out
                   -translate-x-full lg:translate-x-0"
            :class="mobileNavOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="p-4 border-b border-gray-700 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-sm shrink-0">S</div>
                <h1 class="text-white font-bold text-base leading-tight">Sahodaya Platform</h1>
            </div>

            <nav class="flex-1 p-3 space-y-0.5 overflow-y-auto">
                <template v-for="group in navGroups" :key="group.label">
                    <p class="text-xs uppercase tracking-widest text-gray-500 px-3 pt-4 pb-1.5">{{ group.label }}</p>
                    <Link
                        v-for="item in group.items"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors"
                        :class="isActive(item.href)
                            ? 'bg-indigo-600 text-white'
                            : 'text-gray-400 hover:bg-gray-800 hover:text-white'"
                    >
                        <span class="text-base leading-none">{{ item.icon }}</span>
                        <span>{{ item.label }}</span>
                    </Link>
                </template>
            </nav>

            <div class="p-4 border-t border-gray-700 text-xs space-y-1">
                <p class="text-gray-400 truncate">{{ $page.props.auth?.user?.name }}</p>
                <p class="text-gray-500 truncate">{{ $page.props.auth?.user?.email }}</p>
                <Link href="/logout" method="post" as="button"
                      class="text-red-400 hover:text-red-300 transition mt-1 block">
                    Sign out
                </Link>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col min-h-screen overflow-hidden w-full lg:w-auto">
            <header class="bg-white border-b border-gray-200 px-4 lg:px-6 py-3 flex items-center justify-between gap-3 shrink-0">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <button type="button"
                            class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 shrink-0"
                            aria-label="Open menu"
                            @click="mobileNavOpen = true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <h2 class="text-base font-semibold text-gray-700 truncate">{{ title }}</h2>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span v-if="$page.props.flash?.success"
                          class="hidden sm:inline bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-medium">
                        {{ $page.props.flash.success }}
                    </span>
                </div>
            </header>
            <main class="flex-1 p-4 lg:p-6 overflow-auto">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

defineProps({
    title: { type: String, default: 'Dashboard' },
});

const page = usePage();
const mobileNavOpen = ref(false);

watch(() => page.url, () => {
    mobileNavOpen.value = false;
});

const websiteEnabled = computed(() => page.props.features?.website_enabled ?? false);

const navGroups = computed(() => {
    const groups = [
        {
            label: 'Overview',
            items: [
                { href: '/admin/dashboard', icon: '📊', label: 'Dashboard' },
            ],
        },
        {
            label: 'Sahodaya Clusters',
            items: [
                { href: '/admin/sahodayas',        icon: '🏛️', label: 'All Sahodayas' },
                { href: '/admin/sahodayas/create', icon: '➕', label: 'Add Sahodaya' },
            ],
        },
        {
            label: 'Member Schools',
            items: [
                { href: '/admin/schools',        icon: '🏫', label: 'All Schools' },
                { href: '/admin/schools/create', icon: '➕', label: 'Add School' },
            ],
        },
        {
            label: 'Platform Rules',
            items: [
                { href: '/admin/master-data/class-categories', icon: '📚', label: 'Class Categories' },
                { href: '/admin/master-data/teaching-types',   icon: '👩‍🏫', label: 'Teaching Types' },
            ],
        },
    ];

    if (websiteEnabled.value) {
        groups.push(
            {
                label: 'Site Builder',
                items: [
                    { href: '/admin/builder/sections',  icon: '📐', label: 'Sections' },
                    { href: '/admin/builder/theme',     icon: '🎨', label: 'Theme & Skin' },
                    { href: '/admin/builder/nav',       icon: '🧭', label: 'Navigation' },
                    { href: '/admin/builder/footer',    icon: '🦶', label: 'Footer' },
                    { href: '/admin/builder/widgets',   icon: '🔧', label: 'Widgets' },
                ],
            },
            {
                label: 'Content',
                items: [
                    { href: '/admin/skin-presets', icon: '🖌️', label: 'Skin Presets' },
                ],
            },
        );
    }

    return groups;
});

function isActive(href) {
    return page.url === href || page.url.startsWith(href + '/');
}
</script>
