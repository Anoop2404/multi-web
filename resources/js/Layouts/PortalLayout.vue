<template>
    <div class="min-h-screen bg-slate-50 flex flex-col portal-shell font-sans text-slate-900">
        <!-- Sticky Top Navigation Header -->
        <header class="sticky top-0 z-40 bg-white/85 backdrop-blur-md border-b border-slate-200/80 shrink-0 shadow-[0_4px_20px_rgba(15,23,42,0.03)]">
            <!-- Top Multi-Tone Vibrant Gradient Strip -->
            <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-[#041525] via-[#0f3d7a] via-40% via-[#1e5aa8] to-[#d4a017]" />

            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between gap-4">
                <!-- Branding / Profile Identity -->
                <div class="flex items-center gap-3.5 min-w-0">
                    <div v-if="avatarUrl" class="shrink-0 relative group">
                        <img
                            :src="avatarUrl"
                            :alt="title"
                            class="h-11 w-11 rounded-full object-cover border-2 border-white shadow-md ring-2 ring-[#0f3d7a]/20 transition duration-200 group-hover:scale-105"
                        >
                        <span class="absolute bottom-0 right-0 h-3 w-3 rounded-full bg-emerald-500 ring-2 ring-white" title="Active Account" />
                    </div>
                    <div v-else-if="showAvatarPlaceholder" class="shrink-0 relative">
                        <div class="h-11 w-11 rounded-full bg-gradient-to-br from-[#041525] via-[#0f3d7a] to-[#1e5aa8] text-white flex items-center justify-center text-sm font-extrabold shadow-md ring-2 ring-[#0f3d7a]/20">
                            {{ initials }}
                        </div>
                        <span class="absolute bottom-0 right-0 h-3 w-3 rounded-full bg-emerald-500 ring-2 ring-white" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-[#0f3d7a]">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#0f3d7a]" />
                                {{ roleLabel }}
                            </span>
                        </div>
                        <h1 class="text-base sm:text-lg font-bold text-slate-900 truncate leading-tight mt-0.5">{{ title }}</h1>
                        <p v-if="subtitle" class="text-xs text-slate-500 truncate mt-0.5 flex items-center gap-1">
                            <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            {{ subtitle }}
                        </p>
                    </div>
                </div>

                <!-- Right Header Controls -->
                <div class="flex items-center gap-2 shrink-0">
                    <SignOutButton class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-red-600 bg-slate-100 hover:bg-red-50 px-3 py-1.5 rounded-xl transition duration-150 border border-slate-200/60" />

                    <!-- Mobile Menu Toggle Button -->
                    <button
                        v-if="navItems.length"
                        type="button"
                        class="sm:hidden p-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition"
                        :aria-expanded="mobileMenuOpen"
                        aria-label="Toggle Navigation Menu"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Desktop Horizontal Navigation Tabs -->
            <nav v-if="navItems.length"
                 class="hidden sm:flex max-w-6xl mx-auto px-6 pb-2.5 gap-1.5 overflow-x-auto scrollbar-none">
                <Link v-for="item in navItems" :key="item.href"
                      :href="item.href"
                      class="inline-flex items-center gap-2 text-xs font-semibold px-3.5 py-2 rounded-xl transition duration-150 shrink-0 whitespace-nowrap"
                      :class="isActive(item)
                          ? 'bg-[#041525] text-white shadow-sm ring-1 ring-[#041525]'
                          : 'text-slate-600 hover:bg-slate-100/90 hover:text-slate-900'">
                    <span v-if="getNavIcon(item.icon)" class="w-3.5 h-3.5 opacity-90" v-html="getNavIcon(item.icon)" />
                    {{ item.label }}
                </Link>
            </nav>

            <!-- Mobile Navigation Drawer / Dropdown -->
            <div v-if="navItems.length && mobileMenuOpen"
                 class="sm:hidden border-t border-slate-200 bg-white/95 backdrop-blur-md px-4 py-3 shadow-lg transition duration-200">
                <div class="grid grid-cols-2 gap-1.5">
                    <Link v-for="item in navItems" :key="item.href"
                          :href="item.href"
                          class="inline-flex items-center gap-2 text-xs font-semibold px-3 py-2 rounded-xl transition duration-150"
                          :class="isActive(item)
                              ? 'bg-[#041525] text-white font-bold'
                              : 'text-slate-700 bg-slate-50 hover:bg-slate-100'"
                          @click="mobileMenuOpen = false">
                        <span v-if="getNavIcon(item.icon)" class="w-3.5 h-3.5" v-html="getNavIcon(item.icon)" />
                        {{ item.label }}
                    </Link>
                </div>
            </div>
        </header>

        <!-- Global Flash Messages -->
        <FlashBanner class="max-w-6xl mx-auto px-4 sm:px-6 pt-4 w-full" />

        <!-- Main Page View Content -->
        <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 py-6 space-y-6">
            <slot />
        </main>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import SignOutButton from '@/Components/SignOutButton.vue';
import FlashBanner from '@/Components/ui/FlashBanner.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
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
    const parts = (props.title || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
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
