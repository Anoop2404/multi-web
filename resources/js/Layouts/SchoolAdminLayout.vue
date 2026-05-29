<template>
    <div class="min-h-screen bg-gray-50 flex">
        <!-- Sidebar -->
        <aside class="w-60 bg-white border-r border-gray-100 flex flex-col shrink-0 shadow-sm">
            <!-- Branding -->
            <div class="px-4 py-4 border-b border-gray-100">
                <p class="text-xs text-gray-400 uppercase tracking-widest font-medium">School Admin</p>
                <h2 class="font-bold text-gray-800 text-sm mt-0.5 leading-snug">{{ school?.name }}</h2>
            </div>

            <nav class="flex-1 p-3 space-y-0.5 overflow-y-auto">
                <template v-for="group in navGroups" :key="group.label">
                    <p class="text-xs uppercase tracking-widest text-gray-400 px-3 pt-4 pb-1">{{ group.label }}</p>
                    <Link
                        v-for="item in group.items"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors"
                        :class="isActive(item.href)
                            ? 'bg-blue-50 text-blue-700 font-semibold'
                            : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
                    >
                        <span class="text-base">{{ item.icon }}</span>
                        <span>{{ item.label }}</span>
                        <span v-if="item.badge" class="ml-auto text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full font-bold">
                            {{ item.badge }}
                        </span>
                    </Link>
                </template>
            </nav>

            <div class="p-3 border-t border-gray-100 space-y-1 text-xs">
                <Link href="/admin/dashboard" class="flex items-center gap-1.5 text-gray-400 hover:text-gray-600 transition px-3 py-1.5 rounded-lg hover:bg-gray-50">
                    ← Superadmin panel
                </Link>
                <Link href="/logout" method="post" as="button"
                      class="flex items-center gap-1.5 text-red-400 hover:text-red-600 transition px-3 py-1.5 rounded-lg hover:bg-red-50 w-full text-left">
                    Sign out
                </Link>
            </div>
        </aside>

        <!-- Main -->
        <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
            <header class="bg-white border-b border-gray-100 px-6 py-3 flex items-center justify-between shrink-0">
                <h1 class="text-base font-semibold text-gray-700">{{ title }}</h1>
                <div class="flex items-center gap-2">
                    <span v-if="$page.props.flash?.success"
                          class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-medium animate-in fade-in">
                        ✓ {{ $page.props.flash.success }}
                    </span>
                    <span v-if="$page.props.flash?.error"
                          class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-medium">
                        {{ $page.props.flash.error }}
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
import { computed } from 'vue';

const props = defineProps({
    title:  { type: String, default: 'Dashboard' },
    school: { type: Object, default: null },
});

const page   = usePage();
const school = computed(() => props.school ?? page.props.school);
const tid    = computed(() => school.value?.id ?? '');

const navGroups = computed(() => [
    {
        label: 'Overview',
        items: [
            { href: `/school-admin/${tid.value}`, icon: '📊', label: 'Dashboard' },
        ],
    },
    {
        label: 'Content',
        items: [
            { href: `/school-admin/${tid.value}/news`,         icon: '📰', label: 'News' },
            { href: `/school-admin/${tid.value}/events`,       icon: '📅', label: 'Events' },
            { href: `/school-admin/${tid.value}/gallery`,      icon: '🖼️', label: 'Gallery' },
            { href: `/school-admin/${tid.value}/staff`,        icon: '👨‍🏫', label: 'Staff' },
            { href: `/school-admin/${tid.value}/achievements`,  icon: '🏆', label: 'Achievements' },
            { href: `/school-admin/${tid.value}/downloads`,    icon: '📂', label: 'Downloads' },
            { href: `/school-admin/${tid.value}/job-vacancies`,icon: '💼', label: 'Job Vacancies' },
            { href: `/school-admin/${tid.value}/board-results`,icon: '📈', label: 'Board Results' },
            { href: `/school-admin/${tid.value}/alumni`,       icon: '🎓', label: 'Alumni' },
        ],
    },
    {
        label: 'Admissions',
        items: [
            { href: `/school-admin/${tid.value}/enquiries`,   icon: '📋', label: 'Enquiries' },
            { href: `/school-admin/${tid.value}/tc-requests`, icon: '📄', label: 'TC Requests' },
        ],
    },
    {
        label: 'School',
        items: [
            { href: `/school-admin/${tid.value}/settings`, icon: '⚙️', label: 'Settings' },
        ],
    },
]);

function isActive(href) {
    return page.url === href || (href !== `/school-admin/${tid.value}` && page.url.startsWith(href));
}
</script>
