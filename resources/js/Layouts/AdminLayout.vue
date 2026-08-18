<template>
    <Head :title="title" />
    <div class="admin-layout min-h-screen flex">
        <div v-if="mobileNavOpen"
             class="fixed inset-0 z-40 bg-black/50 lg:hidden"
             @click="mobileNavOpen = false" />

        <!-- Sidebar -->
        <aside
            class="admin-sidebar w-72 lg:w-60 h-screen text-white flex flex-col shrink-0 shadow-xl overflow-hidden
                   fixed inset-y-0 left-0 z-50 lg:sticky lg:top-0
                   transition-transform duration-200 ease-out
                   -translate-x-full lg:translate-x-0 print:hidden"
            :class="mobileNavOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <!-- Brand -->
            <div class="admin-sidebar-head px-5 pt-5 pb-4 border-b border-white/10 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="admin-logo-ring w-11 h-11 rounded-full flex items-center justify-center font-bold text-lg text-[#fbbf24] shrink-0">
                        {{ isStateAdmin && !isSuperAdmin ? 'ST' : 'S' }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold text-[#fbbf24] uppercase tracking-[0.14em] leading-none">
                            {{ isSuperAdmin ? 'Platform' : 'State' }}
                        </p>
                        <p class="text-sm font-semibold text-white truncate mt-1 leading-tight">Sahodaya Platform</p>
                        <p v-if="isStateStaff && !isSuperAdmin" class="text-[11px] text-amber-300 mt-0.5">State Staff (view only)</p>
                        <p v-else-if="isStateAdmin && !isSuperAdmin" class="text-[11px] text-amber-300 mt-0.5">State Admin</p>
                    </div>
                </div>
            </div>

            <SahodayaSidebarNavSearch v-model="navSearch" />

            <!-- Navigation -->
            <nav class="flex-1 min-h-0 py-1 px-2 overflow-y-auto space-y-0.5">
                <p v-if="navSearch.trim() && !filteredNavGroups.length"
                   class="px-3 py-6 text-center text-sm text-white/50">
                    No menus match “{{ navSearch.trim() }}”
                </p>
                <template v-for="group in filteredNavGroups" :key="group.section">
                    <p class="px-3 pt-4 pb-1 text-[11px] font-bold text-[#fbbf24]/90 uppercase tracking-widest">
                        {{ group.section }}
                    </p>
                    <SahodayaNavItem v-for="item in group.items" :key="item.href"
                                     :href="item.href"
                                     :icon="item.icon"
                                     :label="item.label"
                                     :badge="item.badge ?? 0"
                                     :active="adminNavItemActive(page.url, item.href, item.exact)" />
                </template>
            </nav>

            <!-- Footer -->
            <div class="admin-sidebar-foot p-3 border-t border-white/10 shrink-0 bg-[#041525]/40">
                <p v-if="$page.props.auth?.user?.name" class="px-3 pb-2 text-[11px] text-white/50 truncate">
                    {{ $page.props.auth.user.name }}
                </p>
                <SignOutButton
                    class="flex items-center gap-2 w-full px-3 py-2.5 rounded-lg text-sm text-white/80 hover:text-white hover:bg-white/10 transition font-medium">
                    <SvgIcon name="log-out" class="w-4 h-4" />
                    <span>Sign out</span>
                </SignOutButton>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col min-w-0 min-h-screen w-full lg:w-auto">
            <header class="admin-header bg-white border-b border-[#dbeafe] px-4 lg:px-6 py-3 flex items-center justify-between gap-3 shrink-0 shadow-sm print:hidden">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <button type="button"
                            class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 shrink-0"
                            aria-label="Open menu"
                            @click="mobileNavOpen = true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <h2 class="text-base font-bold text-[#041525] truncate">{{ title }}</h2>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <slot name="header-actions" />
                </div>
            </header>
            <main class="admin-main flex-1 p-4 lg:p-6 overflow-auto" :class="{ 'staff-readonly': isStateStaff && !isSuperAdmin }" :inert="isStateStaff && !isSuperAdmin">
                <AnnouncementBanner />
                <StaffReadOnlyBanner v-if="isStateStaff && !isSuperAdmin" />
                <FlashBanner />
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import SignOutButton from '@/Components/SignOutButton.vue';
import StaffReadOnlyBanner from '@/Components/StaffReadOnlyBanner.vue';
import AnnouncementBanner from '@/Components/AnnouncementBanner.vue';
import FlashBanner from '@/Components/ui/FlashBanner.vue';
import SvgIcon from '@/Components/icons/SvgIcon.vue';
import SahodayaNavItem from '@/Components/sahodaya/SahodayaNavItem.vue';
import SahodayaSidebarNavSearch from '@/Components/sahodaya/SahodayaSidebarNavSearch.vue';
import { filterNavGroups } from '@/support/filterNavGroups.js';
import { adminNavItemActive, stateAdminNav, superadminNav } from '@/support/adminNav.js';
import { computed, ref, watch } from 'vue';

defineProps({
    title: { type: String, default: 'Dashboard' },
});

const page = usePage();
const mobileNavOpen = ref(false);
const navSearch = ref('');

watch(() => page.url, () => {
    mobileNavOpen.value = false;
    navSearch.value = '';
});

const userRoles = computed(() => page.props.auth?.user?.roles ?? []);
const isStateAdmin = computed(() => userRoles.value.some(r => ['state_admin', 'state_staff'].includes(r)));
const isStateStaff = computed(() => userRoles.value.includes('state_staff'));
const isSuperAdmin = computed(() => userRoles.value.includes('superadmin'));
const websiteEnabled = computed(() => page.props.features?.website_enabled ?? false);

const navGroups = computed(() => {
    if (isSuperAdmin.value) return superadminNav({ websiteEnabled: websiteEnabled.value });
    if (isStateAdmin.value) return stateAdminNav();
    return [];
});

const filteredNavGroups = computed(() => filterNavGroups(navGroups.value, navSearch.value));
</script>

<style scoped>
.admin-layout {
    background: #f0f9ff;
    font-family: 'Inter', system-ui, sans-serif;
}

.admin-sidebar {
    background:
        radial-gradient(ellipse 80% 50% at 0% 0%, rgba(251, 191, 36, 0.1) 0%, transparent 55%),
        linear-gradient(180deg, #041525 0%, #0a2744 35%, #0f3d7a 100%);
}

.admin-logo-ring {
    border: 2px solid rgba(251, 191, 36, 0.45);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
    background: rgba(255, 255, 255, 0.06);
}

.admin-main {
    background:
        radial-gradient(ellipse 120% 80% at 100% 0%, rgba(15, 61, 122, 0.06) 0%, transparent 55%),
        radial-gradient(ellipse 80% 50% at 0% 100%, rgba(212, 160, 23, 0.05) 0%, transparent 50%),
        linear-gradient(180deg, #f4f7fb 0%, #f8fafc 100%);
}

.staff-readonly :deep(button[type="submit"]:not(.staff-allow)),
.staff-readonly :deep(input:not([type="hidden"]):not([readonly])),
.staff-readonly :deep(select),
.staff-readonly :deep(textarea) {
    pointer-events: none;
    opacity: 0.65;
}
</style>
