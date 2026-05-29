<template>
    <div class="min-h-screen bg-gray-50 flex">
        <!-- Sidebar -->
        <aside class="w-56 bg-white border-r border-gray-100 flex flex-col shrink-0">
            <div class="px-5 py-4 border-b border-gray-100">
                <p class="text-xs font-bold text-purple-600 uppercase tracking-widest">Sahodaya Admin</p>
                <p class="text-sm font-semibold text-gray-800 mt-0.5 truncate">{{ sahodaya.name }}</p>
            </div>

            <nav class="flex-1 py-4 space-y-0.5 overflow-y-auto px-2">
                <!-- Overview -->
                <p class="px-3 pt-3 pb-1 text-xs font-bold text-gray-400 uppercase tracking-wider">Overview</p>
                <Link :href="`/sahodaya-admin/${sahodaya.id}`"
                      class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition w-full"
                      :class="isActive(`/sahodaya-admin/${sahodaya.id}/`) || page.url === `/sahodaya-admin/${sahodaya.id}` ? 'bg-purple-50 text-purple-700 font-semibold' : 'text-gray-600 hover:bg-gray-50'">
                    <span class="text-base leading-none">◈</span><span>Dashboard</span>
                </Link>

                <!-- Content -->
                <p class="px-3 pt-4 pb-1 text-xs font-bold text-gray-400 uppercase tracking-wider">Content</p>
                <Link v-for="item in [
                    { href: `/sahodaya-admin/${sahodaya.id}/office-bearers`, label: 'Office Bearers', icon: '👥' },
                    { href: `/sahodaya-admin/${sahodaya.id}/circulars`,      label: 'Circulars',      icon: '📄' },
                ]" :key="item.href" :href="item.href"
                      class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition w-full"
                      :class="isActive(item.href) ? 'bg-purple-50 text-purple-700 font-semibold' : 'text-gray-600 hover:bg-gray-50'">
                    <span class="text-base leading-none">{{ item.icon }}</span><span>{{ item.label }}</span>
                </Link>

                <!-- Kalotsav -->
                <p class="px-3 pt-4 pb-1 text-xs font-bold text-gray-400 uppercase tracking-wider">Kalotsav</p>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/kalotsav`"
                      class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition w-full"
                      :class="isActive(`/sahodaya-admin/${sahodaya.id}/kalotsav`) ? 'bg-purple-50 text-purple-700 font-semibold' : 'text-gray-600 hover:bg-gray-50'">
                    <span class="text-base leading-none">🏆</span><span>Events & Results</span>
                </Link>

                <!-- Network -->
                <p class="px-3 pt-4 pb-1 text-xs font-bold text-gray-400 uppercase tracking-wider">Network</p>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/schools`"
                      class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition w-full"
                      :class="isActive(`/sahodaya-admin/${sahodaya.id}/schools`) ? 'bg-purple-50 text-purple-700 font-semibold' : 'text-gray-600 hover:bg-gray-50'">
                    <span class="text-base leading-none">🏫</span><span>Member Schools</span>
                </Link>
            </nav>

            <div class="p-4 border-t border-gray-100">
                <Link href="/admin/dashboard" class="text-xs text-gray-400 hover:text-gray-600 block mb-2">← Superadmin</Link>
                <Link href="/logout" method="post" as="button"
                      class="text-xs text-gray-400 hover:text-red-500 transition">Sign out</Link>
            </div>
        </aside>

        <!-- Main -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Header -->
            <header class="bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-800">{{ title }}</h1>
                <div class="flex items-center gap-3">
                    <span v-if="$page.props.flash?.success"
                          class="text-sm text-green-600 font-medium bg-green-50 px-3 py-1 rounded-lg">
                        {{ $page.props.flash.success }}
                    </span>
                </div>
            </header>

            <!-- Content -->
            <main class="flex-1 p-6 overflow-auto">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    title:    { type: String, default: '' },
    sahodaya: { type: Object, required: true },
});

const page = usePage();

function isActive(href) {
    return page.url.startsWith(href);
}
</script>
