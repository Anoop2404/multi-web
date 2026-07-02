<template>
    <div class="sa-layout min-h-screen flex">
        <div v-if="mobileNavOpen"
             class="fixed inset-0 z-40 bg-black/50 lg:hidden"
             @click="mobileNavOpen = false" />

        <aside
            class="sa-sidebar sa-sidebar--events w-72 lg:w-64 h-screen text-white flex flex-col shrink-0 shadow-xl overflow-hidden
                   fixed inset-y-0 left-0 z-50 lg:sticky lg:top-0
                   transition-transform duration-200 ease-out
                   -translate-x-full lg:translate-x-0"
            :class="mobileNavOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="sa-sidebar-head px-5 pt-5 pb-4 border-b border-white/10 shrink-0">
                <div class="flex items-center gap-3">
                    <div v-if="sahodaya.logo_url" class="sa-logo-ring w-11 h-11 rounded-full overflow-hidden shrink-0 bg-white">
                        <img :src="sahodaya.logo_url" :alt="sahodaya.name"
                             class="w-full h-full object-cover scale-[1.18]">
                    </div>
                    <div v-else class="sa-logo-ring w-11 h-11 rounded-full flex items-center justify-center font-bold text-lg text-[#fbbf24] shrink-0">
                        {{ sahodaya.name?.charAt(0) ?? 'S' }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold text-[#fbbf24] uppercase tracking-[0.14em] leading-none">Sahodaya</p>
                        <p class="text-sm font-semibold text-white truncate mt-1 leading-tight">{{ sahodaya.name }}</p>
                    </div>
                </div>

                <nav class="flex flex-col gap-1 mt-4 mb-1" aria-label="Leave event">
                    <Link :href="`/sahodaya-admin/${sahodaya.id}`"
                          class="flex items-center gap-2 text-xs text-white/50 hover:text-white/80 transition">
                        <span aria-hidden="true">←</span> Sahodaya home
                    </Link>
                    <Link v-if="programContext"
                          :href="`${programHubHref}${eventQuery}`"
                          class="flex items-center gap-2 text-xs text-white/50 hover:text-white/80 transition">
                        <span aria-hidden="true">←</span> {{ programContext.label }}
                    </Link>
                    <Link v-else-if="eventContext?.id"
                          :href="`/sahodaya-admin/${sahodaya.id}/events`"
                          class="flex items-center gap-2 text-xs text-white/50 hover:text-white/80 transition">
                        <span aria-hidden="true">←</span> All events
                    </Link>
                </nav>

                <div v-if="eventContext?.id" class="flex items-center gap-3 mt-2">
                    <div class="w-10 h-10 rounded-xl bg-[#fbbf24]/15 border border-[#fbbf24]/30 flex items-center justify-center shrink-0">
                        <SahodayaSvgIcon :name="sidebarIcon" class="w-5 h-5 text-[#fbbf24]" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold text-[#fbbf24] uppercase tracking-[0.14em] leading-none">
                            {{ sidebarEyebrow }}
                        </p>
                        <p class="text-sm font-semibold text-white truncate mt-1 leading-tight">{{ sidebarTitle }}</p>
                    </div>
                </div>
            </div>

            <SahodayaSidebarNavSearch v-model="navSearch" />

            <nav class="flex-1 min-h-0 py-1 px-2 overflow-y-auto space-y-0.5">
                <p v-if="navSearch.trim() && !filteredNavGroups.length"
                   class="px-3 py-6 text-center text-sm text-white/50">
                    No menus match “{{ navSearch.trim() }}”
                </p>
                <template v-for="group in filteredNavGroups" :key="group.section">
                    <p class="px-3 pt-4 pb-1 text-[10px] font-bold text-[#fbbf24]/75 uppercase tracking-widest">
                        {{ group.section }}
                    </p>
                    <SahodayaNavItem v-for="item in group.items" :key="item.href"
                                     :href="item.href"
                                     :icon="item.icon"
                                     :label="item.label"
                                     :active="navItemActive(page.url, item.href, item.exact)" />
                </template>
            </nav>

            <div class="sa-sidebar-foot p-3 border-t border-white/10 shrink-0 bg-[#041525]/40">
                <SignOutButton
                    class="flex items-center gap-2 w-full px-3 py-2.5 rounded-lg text-sm text-white/80 hover:text-white hover:bg-white/10 transition font-medium">
                    <SahodayaSvgIcon name="log-out" class="w-4 h-4" />
                    <span>Sign out</span>
                </SignOutButton>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 min-h-screen w-full lg:w-auto">
            <header class="sa-header bg-white border-b border-[#dbeafe] px-4 lg:px-6 py-3 flex items-center justify-between gap-3 shrink-0 shadow-sm">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <button type="button"
                            class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 shrink-0"
                            aria-label="Open menu"
                            @click="mobileNavOpen = true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <div v-if="showHeaderTitle" class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#0f3d7a]">{{ headerEyebrow }}</p>
                        <h1 class="text-base font-bold text-[#041525] truncate">{{ title }}</h1>
                    </div>
                    <span v-if="isStaffUser" class="hidden sm:inline text-xs bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded font-medium">View only</span>
                    <slot name="header-suffix" />
                </div>
            </header>

            <main class="sa-main flex-1 p-4 sm:p-6 lg:p-8 overflow-auto" :class="{ 'staff-readonly': isStaffUser }">
                <div class="sa-page mx-auto w-full max-w-6xl">
                    <StaffReadOnlyBanner v-if="isStaffUser" />
                    <FlashBanner />
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import SignOutButton from '@/Components/SignOutButton.vue';
import StaffReadOnlyBanner from '@/Components/StaffReadOnlyBanner.vue';
import FlashBanner from '@/Components/ui/FlashBanner.vue';
import SahodayaNavItem from '@/Components/sahodaya/SahodayaNavItem.vue';
import SahodayaSidebarNavSearch from '@/Components/sahodaya/SahodayaSidebarNavSearch.vue';
import SahodayaSvgIcon from '@/Components/sahodaya/SahodayaSvgIcon.vue';
import { eventsModuleNav, eventScopedNav, navItemActive } from '@/support/sahodayaEventNav.js';
import { filterNavByPermissions, staffCanSeeNavItem } from '@/support/sahodayaEventNavPermissions.js';
import { filterNavGroups } from '@/support/filterNavGroups.js';
import { programForEventType, programScopedNav, sahodayaProgramHref } from '@/support/sahodayaPrograms.js';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    sahodaya: { type: Object, required: true },
    publicUrl: { type: String, default: null },
    pendingPaymentsCount: { type: Number, default: 0 },
    isStaff: { type: Boolean, default: false },
    event: { type: Object, default: null },
    program: { type: Object, default: null },
    programEvents: { type: Array, default: null },
    /** When false, the top bar shows only menu + badges — use PageHeader in page body for the title. */
    showHeaderTitle: { type: Boolean, default: true },
});

const page = usePage();
const mobileNavOpen = ref(false);
const navSearch = ref('');
const isStaffUser = computed(() => props.isStaff || page.props.isStaff);

const eventContext = computed(() => props.event ?? page.props.event ?? null);

const programEventsList = computed(() => props.programEvents ?? page.props.programEvents ?? []);

const programContext = computed(() => {
    if (props.program?.slug) {
        return props.program;
    }
    if (page.props.program?.slug) {
        return page.props.program;
    }
    if (eventContext.value?.event_type) {
        return programForEventType(eventContext.value.event_type);
    }
    return null;
});

const eventQuery = computed(() => {
    const id = eventContext.value?.id;
    return id ? `?event_id=${id}` : '';
});

const programHubHref = computed(() => {
    const slug = programContext.value?.slug;
    return slug ? sahodayaProgramHref(props.sahodaya.id, slug) : `/sahodaya-admin/${props.sahodaya.id}/events`;
});

const sidebarEyebrow = computed(() => programContext.value?.label ?? 'Event');

const sidebarTitle = computed(() => eventContext.value?.title ?? 'Event');

const sidebarIcon = computed(() => programContext.value?.icon ?? 'star');

const headerEyebrow = computed(() => {
    if (eventContext.value?.id) return programContext.value?.label ?? 'Event';
    if (programContext.value) return 'Program';
    return 'Events';
});

watch(() => page.url, () => {
    mobileNavOpen.value = false;
    navSearch.value = '';
});

const navGroups = computed(() => {
    const sid = props.sahodaya.id;
    let groups;
    if (eventContext.value?.id) {
        groups = eventScopedNav(sid, eventContext.value.id, eventContext.value, programEventsList.value);
    } else if (programContext.value?.slug) {
        groups = programScopedNav(sid, programContext.value.slug, programEventsList.value);
    } else {
        groups = eventsModuleNav(sid);
    }

    if (!isStaffUser.value) {
        return groups;
    }

    const perms = page.props.staffPermissions ?? [];
    return filterNavByPermissions(groups, (item) => staffCanSeeNavItem(item, perms));
});

const filteredNavGroups = computed(() => filterNavGroups(navGroups.value, navSearch.value));
</script>

<style scoped>
.sa-layout {
    background: #f0f9ff;
    font-family: 'Inter', system-ui, sans-serif;
}

.sa-sidebar {
    background:
        radial-gradient(ellipse 80% 50% at 0% 0%, rgba(251, 191, 36, 0.12) 0%, transparent 55%),
        linear-gradient(180deg, #041525 0%, #0a2744 35%, #0f3d7a 100%);
}

.sa-sidebar--events {
    background:
        radial-gradient(ellipse 70% 40% at 100% 0%, rgba(251, 191, 36, 0.14) 0%, transparent 50%),
        linear-gradient(180deg, #0a2744 0%, #0f3d7a 50%, #041525 100%);
}

.sa-logo-ring {
    border: 2px solid rgba(251, 191, 36, 0.45);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
}

.staff-readonly :deep(button[type="submit"]:not(.staff-allow)),
.staff-readonly :deep(input:not([type="hidden"]):not([readonly])),
.staff-readonly :deep(select),
.staff-readonly :deep(textarea) {
    pointer-events: none;
    opacity: 0.65;
}

.sa-main {
    background:
        radial-gradient(ellipse 120% 80% at 100% 0%, rgba(15, 61, 122, 0.06) 0%, transparent 55%),
        radial-gradient(ellipse 80% 50% at 0% 100%, rgba(212, 160, 23, 0.05) 0%, transparent 50%),
        linear-gradient(180deg, #f4f7fb 0%, #f8fafc 100%);
}
</style>
