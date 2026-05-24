<template>
    <div class="min-h-screen bg-gray-100 flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-gray-900 text-gray-300 flex flex-col">
            <div class="p-4 border-b border-gray-700">
                <h1 class="text-white font-bold text-lg">Sahodaya Admin</h1>
            </div>
            <nav class="flex-1 p-4 space-y-1">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm hover:bg-gray-800 hover:text-white transition-colors"
                    :class="{ 'bg-gray-800 text-white': isActive(item.href) }"
                >
                    <span>{{ item.label }}</span>
                </Link>
            </nav>
            <div class="p-4 border-t border-gray-700 text-sm">
                <p class="text-gray-400">{{ $page.props.auth?.user?.name }}</p>
                <Link href="/logout" method="post" as="button" class="text-red-400 hover:text-red-300 mt-1">
                    Logout
                </Link>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col min-h-screen">
            <header class="bg-white shadow-sm px-6 py-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-700">{{ title }}</h2>
            </header>
            <main class="flex-1 p-6">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    title: { type: String, default: 'Dashboard' },
});

const page = usePage();

const navItems = [
    { href: '/admin/dashboard', label: 'Dashboard' },
    { href: '/admin/tenants', label: 'Tenants' },
];

function isActive(href) {
    return page.url.startsWith(href);
}
</script>
