<template>
    <div class="min-h-screen bg-slate-50 flex font-sans text-slate-900 portal-shell">
        <!-- Desktop Fixed/Sticky Left Sidebar Navigation -->
        <aside class="hidden lg:flex w-64 bg-[#041525] text-white flex-col shrink-0 border-r border-slate-800/80 sticky top-0 h-screen overflow-y-auto shadow-xl">
            <!-- Top Vibrant Accent Strip -->
            <div class="h-1 bg-gradient-to-r from-[#041525] via-[#0f3d7a] via-40% via-[#1e5aa8] to-[#d4a017]" />

            <!-- Brand & Teacher Identity Header (Static Header Title) -->
            <div class="p-5 border-b border-slate-800/80">
                <div class="flex items-center gap-3">
                    <div v-if="avatarUrl" class="shrink-0 relative group">
                        <img
                            :src="avatarUrl"
                            :alt="headerTitle || roleLabel"
                            class="h-11 w-11 rounded-2xl object-cover border-2 border-[#0f3d7a] shadow-md ring-2 ring-[#0f3d7a]/30"
                        >
                        <span class="absolute bottom-0 right-0 h-3 w-3 rounded-full bg-emerald-500 ring-2 ring-[#041525]" title="Active Account" />
                    </div>
                    <div v-else class="shrink-0 relative">
                        <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-[#0f3d7a] to-[#1e5aa8] text-white flex items-center justify-center text-xs font-extrabold shadow-md ring-2 ring-[#0f3d7a]/30">
                            TP
                        </div>
                        <span class="absolute bottom-0 right-0 h-3 w-3 rounded-full bg-emerald-500 ring-2 ring-[#041525]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-white/10 text-amber-400">
                            {{ roleLabel }}
                        </span>
                        <h2 class="text-sm font-bold text-white truncate mt-0.5 leading-tight">{{ headerTitle || 'Teacher Portal' }}</h2>
                        <p v-if="subtitle" class="text-xs text-slate-400 truncate mt-0.5" :title="subtitle">{{ subtitle }}</p>
                    </div>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav v-if="navItems.length" class="flex-1 p-3 space-y-1 overflow-y-auto">
                <p class="px-3 pt-2 pb-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Portal Navigation</p>
                <Link v-for="item in navItems" :key="item.href"
                      :href="item.href"
                      class="flex items-center gap-3 text-xs font-semibold px-3.5 py-2.5 rounded-xl transition duration-150 group"
                      :class="isActive(item)
                          ? 'bg-[#0f3d7a] text-white font-bold shadow-md ring-1 ring-white/10'
                          : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'">
                    <span v-if="getNavIcon(item.icon)" class="w-4 h-4 shrink-0 opacity-80 group-hover:opacity-100" v-html="getNavIcon(item.icon)" />
                    <span class="truncate">{{ item.label }}</span>
                </Link>
            </nav>

            <!-- Bottom Sign Out Action -->
            <div class="p-4 border-t border-slate-800/80 bg-[#020b14]">
                <SignOutButton class="w-full justify-center inline-flex items-center gap-2 text-xs font-bold text-slate-300 hover:text-white bg-slate-800/70 hover:bg-red-950/80 hover:border-red-800/50 py-2.5 px-3 rounded-xl transition duration-150 border border-slate-700/50" />
            </div>
        </aside>

        <!-- Main Workspace (Content + Mobile Topbar) -->
        <div class="flex-1 flex flex-col min-w-0 min-h-screen">
            <!-- Mobile Top Header Bar -->
            <header class="lg:hidden sticky top-0 z-40 bg-[#041525] text-white border-b border-slate-800 px-4 py-3 flex items-center justify-between shadow-md">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-[#0f3d7a] to-[#1e5aa8] text-white flex items-center justify-center font-bold text-xs shrink-0">
                        TP
                    </div>
                    <div class="min-w-0">
                        <span class="text-[10px] uppercase font-extrabold text-amber-400 tracking-wider">{{ roleLabel }}</span>
                        <h1 class="text-sm font-bold text-white truncate">{{ title }}</h1>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <SignOutButton class="text-xs text-slate-300 hover:text-red-400 bg-slate-800/80 px-2.5 py-1.5 rounded-lg" />
                    <button
                        v-if="navItems.length"
                        type="button"
                        class="p-2 rounded-xl text-slate-300 hover:bg-slate-800 transition"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </header>

            <!-- Mobile Slide-over Drawer -->
            <div v-if="navItems.length && mobileMenuOpen"
                 class="lg:hidden fixed inset-0 z-50 flex">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="mobileMenuOpen = false" />
                <aside class="relative w-72 max-w-[80vw] bg-[#041525] text-white flex flex-col h-full z-10 shadow-2xl">
                    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                        <div class="min-w-0">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400">{{ roleLabel }}</span>
                            <h2 class="text-sm font-bold text-white truncate">{{ headerTitle || 'Teacher Portal' }}</h2>
                        </div>
                        <button type="button" class="p-1.5 text-slate-400 hover:text-white" @click="mobileMenuOpen = false">
                            ✕
                        </button>
                    </div>
                    <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
                        <Link v-for="item in navItems" :key="item.href"
                              :href="item.href"
                              class="flex items-center gap-3 text-xs font-semibold px-3.5 py-2.5 rounded-xl transition"
                              :class="isActive(item) ? 'bg-[#0f3d7a] text-white font-bold' : 'text-slate-300 hover:bg-slate-800'"
                              @click="mobileMenuOpen = false">
                            <span v-if="getNavIcon(item.icon)" class="w-4 h-4 shrink-0" v-html="getNavIcon(item.icon)" />
                            {{ item.label }}
                        </Link>
                    </nav>
                </aside>
            </div>

            <!-- Global Flash Messages -->
            <FlashBanner class="max-w-7xl mx-auto px-4 sm:px-6 pt-4 w-full" />

            <!-- Main Page View Content -->
            <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-6 space-y-6">
                <!-- Page Content Top Heading -->
                <div v-if="title" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-1 border-b border-slate-200/60">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">{{ title }}</h1>
                        <p v-if="subtitle" class="text-xs text-slate-500 mt-0.5">{{ subtitle }}</p>
                    </div>
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import SignOutButton from '@/Components/SignOutButton.vue';
import FlashBanner from '@/Components/ui/FlashBanner.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    headerTitle: { type: String, default: 'Teacher Portal' },
    subtitle: { type: String, default: '' },
    roleLabel: { type: String, required: true },
    accent: { type: String, default: 'navy' },
    navItems: { type: Array, default: () => [] },
    avatarUrl: { type: String, default: '' },
    showAvatarPlaceholder: { type: Boolean, default: false },
});

const page = usePage();
const mobileMenuOpen = ref(false);

const initials = computed(() => {
    const parts = (props.headerTitle || props.roleLabel || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'TP';
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
});

function isActive(item) {
    const href = item.href;
    const url = page.url.split('?')[0];
    if (item.exact) {
        return url === href || url === `${href}/`;
    }
    if (url === href || url === `${href}/`) {
        return true;
    }
    if (url.startsWith(`${href}/`)) {
        return !props.navItems.some((other) =>
            other.href !== href
            && other.href.startsWith(`${href}/`)
            && (url === other.href || url.startsWith(`${other.href}/`)),
        );
    }
    return false;
}

function getNavIcon(name) {
    const icons = {
        home: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>`,
        fest: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>`,
        schedule: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>`,
        results: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>`,
        certificates: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>`,
        training: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>`,
        exams: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>`,
        banks: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>`,
        papers: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>`,
        profile: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>`,
    };
    return icons[name] || null;
}
</script>

<style scoped>
.scrollbar-none {
    scrollbar-width: none;
}
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
</style>

