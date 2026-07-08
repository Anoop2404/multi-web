<template>
    <Head :title="title" />
    <div class="sa-layout min-h-screen flex">
        <div v-if="mobileNavOpen"
             class="fixed inset-0 z-40 bg-black/50 lg:hidden"
             @click="mobileNavOpen = false" />

        <!-- Sidebar -->
        <aside
            class="sa-sidebar w-72 lg:w-60 h-screen text-white flex flex-col shrink-0 shadow-xl overflow-hidden
                   fixed inset-y-0 left-0 z-50 lg:sticky lg:top-0
                   transition-transform duration-200 ease-out
                   -translate-x-full lg:translate-x-0"
            :class="mobileNavOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="sa-sidebar-head px-5 pt-5 pb-4 border-b border-white/10 shrink-0">
                <div class="flex items-center gap-3">
                    <div v-if="school?.logo_url" class="sa-logo-ring w-11 h-11 rounded-full overflow-hidden shrink-0 bg-white">
                        <img :src="school.logo_url" :alt="school.name"
                             class="w-full h-full object-cover scale-[1.18]">
                    </div>
                    <div v-else class="sa-logo-ring w-11 h-11 rounded-full flex items-center justify-center font-bold text-lg text-[#fbbf24] shrink-0">
                        {{ school?.name?.charAt(0) ?? 'S' }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold text-[#fbbf24] uppercase tracking-[0.14em] leading-none">School</p>
                        <p class="text-sm font-semibold text-white truncate mt-1 leading-tight">{{ school?.name }}</p>
                        <p v-if="school?.school_prefix" class="text-[10px] font-mono text-white/50 mt-0.5">{{ school.school_prefix }}</p>
                    </div>
                </div>
                <a v-if="websiteEnabled && publicUrl" :href="publicUrl" target="_blank" rel="noopener"
                   class="sa-portal-link mt-3 flex items-center gap-1.5 w-full rounded-lg px-3 py-2 text-xs font-medium transition group">
                    <SahodayaSvgIcon name="external-link" class="w-3.5 h-3.5 shrink-0 opacity-70" />
                    <span class="flex-1 truncate font-mono text-[11px]">{{ publicUrl }}</span>
                    <span class="text-white/40 group-hover:text-[#fbbf24] transition text-[10px] shrink-0">↗</span>
                </a>
            </div>

            <SahodayaSidebarNavSearch v-model="navSearch" />

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
                                     :active="schoolNavItemActive(page.url, item.href, item.exact, item.matchQuery)" />
                </template>
            </nav>

            <div class="sa-sidebar-foot p-3 border-t border-white/10 shrink-0 bg-[#041525]/40">
                <p v-if="$page.props.auth?.user?.name" class="px-3 pb-2 text-[11px] text-white/50 truncate">
                    {{ $page.props.auth.user.name }}
                </p>
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
                    <h1 v-if="showHeaderTitle" class="text-base font-bold text-[#041525] truncate">{{ title }}</h1>
                    <span v-if="isEventCoordinatorUser" class="hidden sm:inline text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded font-medium">Event coordinator</span>
                    <span v-else-if="isStaffUser" class="hidden sm:inline text-xs bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded font-medium">View only</span>
                    <slot name="header-suffix" />
                </div>
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <a v-if="websiteEnabled && publicUrl" :href="publicUrl" target="_blank" rel="noopener"
                       class="sa-preview-btn inline-flex items-center gap-1.5 px-3 sm:px-4 py-2 rounded-lg text-white text-xs font-semibold transition shadow-sm">
                        <SahodayaSvgIcon name="external-link" class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Preview Site</span>
                    </a>
                    <span v-if="$page.props.auth?.user?.name" class="hidden sm:inline text-xs text-gray-500 max-w-[10rem] truncate">
                        {{ $page.props.auth.user.name }}
                    </span>
                </div>
            </header>

            <main class="sa-main flex-1 p-4 lg:p-6 overflow-auto" :class="{ 'staff-readonly': isStaffUser }">
                <StaffReadOnlyBanner v-if="isStaffUser" />
                <FlashBanner />
                <ValidationBanner />
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import SignOutButton from '@/Components/SignOutButton.vue';
import StaffReadOnlyBanner from '@/Components/StaffReadOnlyBanner.vue';
import FlashBanner from '@/Components/ui/FlashBanner.vue';
import ValidationBanner from '@/Components/ui/ValidationBanner.vue';
import SahodayaNavItem from '@/Components/sahodaya/SahodayaNavItem.vue';
import SahodayaSidebarNavSearch from '@/Components/sahodaya/SahodayaSidebarNavSearch.vue';
import SahodayaSvgIcon from '@/Components/sahodaya/SahodayaSvgIcon.vue';
import { filterNavGroups } from '@/support/filterNavGroups.js';
import {
    detectSchoolFestContextFromUrl,
    detectSchoolMcqExamIdFromUrl,
    detectSchoolMcqHubFromUrl,
    detectSchoolMembershipFromUrl,
    detectSchoolProgramFromUrl,
    detectSchoolTrainingFromUrl,
    schoolAdminNav,
    schoolEventCoordinatorNav,
    schoolFestScopedNav,
    schoolMcqExamScopedNav,
    schoolMcqHubNav,
    schoolMembershipScopedNav,
    schoolNavItemActive,
    schoolProgramScopedNav,
    schoolTrainingHubNav,
} from '@/support/schoolAdminNav.js';
import { detectSchoolEventFromUrl, schoolEventScopedNav } from '@/support/schoolEventNav.js';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    title:   { type: String, default: 'Dashboard' },
    school:  { type: Object, default: null },
    isStaff: { type: Boolean, default: false },
    showHeaderTitle: { type: Boolean, default: true },
    pendingChangeRequests: { type: Number, default: 0 },
});

const page = usePage();
const mobileNavOpen = ref(false);
const navSearch = ref('');
const isStaffUser = computed(() => props.isStaff || page.props.isStaff);
const isEventCoordinatorUser = computed(() => page.props.isEventCoordinator ?? false);
const eventScopes = computed(() => page.props.eventScopes ?? []);
const school = computed(() => props.school ?? page.props.school);
const tid = computed(() => school.value?.id ?? '');
const publicUrl = computed(() => page.props.publicUrl ?? null);
const websiteEnabled = computed(() => page.props.features?.website_enabled ?? false);

const STAFF_NAV = {
    students: ['fest.view', 'website.view', 'website.manage', 'membership.view'],
    membership: ['membership.view', 'membership.manage'],
    fest: ['fest.view', 'fest.manage'],
    mcq: ['mcq.view', 'mcq.manage'],
    training: ['training.view', 'training.manage', 'fest.view', 'fest.manage'],
    website: ['website.view', 'website.manage', 'website.news'],
    users: ['users.manage'],
};

function canNav(section) {
    if (!isStaffUser.value) return true;
    const perms = page.props.staffPermissions ?? [];
    const required = STAFF_NAV[section];
    if (!required) return true;
    return required.some(p => perms.includes(p));
}

watch(() => page.url, () => {
    mobileNavOpen.value = false;
    navSearch.value = '';
});

const activeProgramSlug = computed(() => detectSchoolProgramFromUrl(page.url));
const festContext = computed(() => detectSchoolFestContextFromUrl(page.url));

const navGroups = computed(() => {
    if (!tid.value) {
        return [];
    }

    const options = {
        canNav,
        websiteEnabled: websiteEnabled.value,
        schoolHasPrefix: Boolean(school.value?.school_prefix),
        pendingChangeRequests: props.pendingChangeRequests || page.props.pendingChangeRequests || 0,
        coordinatorMode: isEventCoordinatorUser.value,
        navVisibility: page.props.navVisibility ?? null,
        membershipPaid: page.props.membershipPaid !== false,
    };

    const mcqExamId = detectSchoolMcqExamIdFromUrl(page.url);
    if (mcqExamId) {
        const exam = page.props.exam;
        return schoolMcqExamScopedNav(tid.value, mcqExamId, {
            ...options,
            resultsPublished: Boolean(exam?.results_published),
        });
    }

    if (detectSchoolMcqHubFromUrl(page.url)) {
        return schoolMcqHubNav(tid.value, options);
    }

    if (detectSchoolMembershipFromUrl(page.url)) {
        return schoolMembershipScopedNav(tid.value, options);
    }

    if (detectSchoolTrainingFromUrl(page.url)) {
        return schoolTrainingHubNav(tid.value, options);
    }

    const schoolEventCtx = detectSchoolEventFromUrl(page.url);
    const festEvent = page.props.event;
    if (schoolEventCtx?.eventId && festEvent?.id) {
        return schoolEventScopedNav(tid.value, schoolEventCtx.programSlug, festEvent, {
            ...options,
            programPrefix: schoolEventCtx.programPrefix ?? page.props.programPrefix,
            isSports: festEvent.event_type === 'sports' || schoolEventCtx.programSlug === 'sports-meet',
            programEvents: page.props.programEvents ?? [],
        });
    }

    if (activeProgramSlug.value) {
        return schoolProgramScopedNav(tid.value, activeProgramSlug.value, options);
    }

    if (festContext.value && !isEventCoordinatorUser.value) {
        return schoolFestScopedNav(tid.value, options);
    }

    if (isEventCoordinatorUser.value) {
        return schoolEventCoordinatorNav(tid.value, eventScopes.value);
    }

    return schoolAdminNav(tid.value, options);
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
        radial-gradient(ellipse 80% 50% at 0% 0%, rgba(251, 191, 36, 0.1) 0%, transparent 55%),
        linear-gradient(180deg, #041525 0%, #0a2744 35%, #0f3d7a 100%);
}

.sa-logo-ring {
    border: 2px solid rgba(251, 191, 36, 0.45);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
}

.sa-portal-link {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.75);
}

.sa-portal-link:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(251, 191, 36, 0.3);
    color: #fff;
}

.sa-preview-btn {
    background: linear-gradient(135deg, #0f3d7a, #1e5aa8);
    box-shadow: 0 2px 8px rgba(15, 61, 122, 0.3);
}

.sa-preview-btn:hover {
    background: linear-gradient(135deg, #1a4f8c, #2563eb);
}

.sa-main {
    background:
        radial-gradient(ellipse 120% 80% at 100% 0%, rgba(15, 61, 122, 0.06) 0%, transparent 55%),
        radial-gradient(ellipse 80% 50% at 0% 100%, rgba(212, 160, 23, 0.05) 0%, transparent 50%),
        linear-gradient(180deg, #f4f7fb 0%, #f8fafc 100%);
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.staff-readonly :deep(button[type="submit"]:not(.staff-allow)),
.staff-readonly :deep(input:not([type="hidden"]):not([readonly])),
.staff-readonly :deep(select),
.staff-readonly :deep(textarea) {
    pointer-events: none;
    opacity: 0.65;
}
</style>
