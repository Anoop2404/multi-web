<template>
    <div class="min-h-screen bg-gray-100 flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-gray-900 text-gray-300 flex flex-col shrink-0">
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
        <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
            <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between shrink-0">
                <h2 class="text-base font-semibold text-gray-700">{{ title }}</h2>
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span v-if="$page.props.flash?.success"
                          class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-medium">
                        {{ $page.props.flash.success }}
                    </span>
                </div>
            </header>
            <main class="flex-1 p-6 overflow-auto">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: 'Dashboard' },
});

const page = usePage();

const navGroups = [
    {
        label: 'Overview',
        items: [
            { href: '/admin/dashboard', icon: '📊', label: 'Dashboard' },
        ],
    },
    {
        label: 'Tenants',
        items: [
            { href: '/admin/tenants',  icon: '🏫', label: 'All Tenants' },
            { href: '/admin/tenants/create', icon: '➕', label: 'Add Tenant' },
        ],
    },
    {
        label: 'Site Builder',
        items: [
            { href: '/admin/builder/sections',  icon: '📐', label: 'Sections' },
            { href: '/admin/builder/theme',     icon: '🎨', label: 'Theme & Skin' },
            { href: '/admin/builder/nav',       icon: '🧭', label: 'Navigation' },
            { href: '/admin/builder/widgets',   icon: '🔧', label: 'Widgets' },
        ],
    },
    {
        label: 'Content',
        items: [
            { href: '/admin/skin-presets', icon: '🖌️', label: 'Skin Presets' },
        ],
    },
];

function isActive(href) {
    return page.url === href || page.url.startsWith(href + '/');
}
</script>
