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
                   -translate-x-full lg:translate-x-0 print:hidden"
            :class="mobileNavOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <!-- Logo / Brand -->
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
                <a v-if="showPublicSiteLink" :href="publicUrl" target="_blank" rel="noopener"
                   class="sa-portal-link mt-3 flex items-center gap-1.5 w-full rounded-lg px-3 py-2 text-xs font-medium transition group">
                    <SvgIcon name="external-link" class="w-3.5 h-3.5 shrink-0 opacity-70" />
                    <span class="flex-1 truncate font-mono text-[11px]">{{ publicUrl }}</span>
                    <span class="text-white/40 group-hover:text-[#fbbf24] transition text-[10px] shrink-0">↗</span>
                </a>
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

            <!-- Footer — always pinned at bottom -->
            <div class="sa-sidebar-foot p-3 border-t border-white/10 shrink-0 bg-[#041525]/40">
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

        <!-- Main -->
        <div class="flex-1 flex flex-col min-w-0 min-h-screen w-full lg:w-auto">
            <header class="sa-header bg-white border-b border-[#dbeafe] px-4 lg:px-6 py-3 flex items-center justify-between gap-3 shrink-0 shadow-sm print:hidden">
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
                    <span v-if="isReadOnlyStaff" class="hidden sm:inline text-xs bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded font-medium">View only</span>
                    <span v-else-if="isStaffUser" class="hidden sm:inline text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded font-medium">Scoped access</span>
                    <slot name="header-suffix" />
                </div>
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <a v-if="showPublicSiteLink" :href="publicUrl" target="_blank" rel="noopener"
                       class="sa-preview-btn inline-flex items-center gap-1.5 px-3 sm:px-4 py-2 rounded-lg text-white text-xs font-semibold transition shadow-sm">
                        <SvgIcon name="external-link" class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Preview Site</span>
                    </a>
                    <span v-if="$page.props.auth?.user?.name" class="hidden sm:inline text-xs text-gray-500 max-w-[10rem] truncate">
                        {{ $page.props.auth.user.name }}
                    </span>
                </div>
            </header>

            <main class="sa-main flex-1 p-4 lg:p-6 overflow-auto" :class="{ 'staff-readonly': isReadOnlyStaff }" :inert="isReadOnlyStaff">
                <ImpersonationBanner />
                <AnnouncementBanner />
                <StaffReadOnlyBanner v-if="isReadOnlyStaff" />
                <FlashBanner />
                <ValidationBanner />
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import SignOutButton from '@/Components/SignOutButton.vue';
import StaffReadOnlyBanner from '@/Components/StaffReadOnlyBanner.vue';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';
import AnnouncementBanner from '@/Components/AnnouncementBanner.vue';
import FlashBanner from '@/Components/ui/FlashBanner.vue';
import ValidationBanner from '@/Components/ui/ValidationBanner.vue';
import SahodayaNavItem from '@/Components/sahodaya/SahodayaNavItem.vue';
import SahodayaSidebarNavSearch from '@/Components/sahodaya/SahodayaSidebarNavSearch.vue';
import SvgIcon from '@/Components/icons/SvgIcon.vue';
import { filterNavGroups } from '@/support/filterNavGroups.js';
import {
    adminNavItemActive,
    detectSahodayaMcqExamIdFromUrl,
    detectSahodayaMcqHubFromUrl,
    detectSahodayaMcqSeriesIdFromUrl,
    detectSahodayaMembershipFromUrl,
    detectSahodayaTrainingHubFromUrl,
    detectSahodayaTrainingProgramIdFromUrl,
    sahodayaAdminNav,
    sahodayaMcqExamScopedNav,
    sahodayaMcqHubNav,
    sahodayaMcqSeriesScopedNav,
    sahodayaMembershipScopedNav,
    sahodayaTrainingHubNav,
    sahodayaTrainingProgramScopedNav,
} from '@/support/sahodayaAdminNav.js';
import { computed, defineComponent, h, ref, watch } from 'vue';

const props = defineProps({
    title:                  { type: String, default: '' },
    sahodaya:               { type: Object, required: true },
    publicUrl:              { type: String, default: null },
    approvedSchoolsCount:   { type: Number, default: 0 },
    pendingSchoolsCount:    { type: Number, default: 0 },
    pendingSubmissionsCount:{ type: Number, default: 0 },
    pendingPaymentsCount:   { type: Number, default: 0 },
    setupIncompleteCount:    { type: Number, default: 0 },
    pendingChangeRequests:  { type: Number, default: 0 },
    unverifiedStudentsCount:{ type: Number, default: 0 },
    pendingFestAppealsCount:{ type: Number, default: 0 },
    isStaff:                { type: Boolean, default: false },
    showHeaderTitle:        { type: Boolean, default: true },
});

const page = usePage();
const mobileNavOpen = ref(false);
const navSearch = ref('');
const isStaffUser = computed(() => props.isStaff || page.props.isStaff);
const staffPermissions = computed(() => page.props.staffPermissions ?? []);
const isReadOnlyStaff = computed(() => isStaffUser.value
    && !staffPermissions.value.some(permission => !permission.endsWith('.view')));
const websiteEnabled = computed(() => page.props.features?.website_enabled ?? false);
const publicWebsiteEnabled = computed(() => page.props.publicWebsiteEnabled ?? true);
const showPublicSiteLink = computed(() => websiteEnabled.value && publicWebsiteEnabled.value && props.publicUrl);

const STAFF_NAV = {
    website: ['website.view', 'website.manage', 'website.news'],
    membership: ['membership.view', 'membership.manage'],
    fest: ['fest.view', 'fest.manage', 'fest.marks', 'fest.registrations', 'fest.results', 'fest.settings', 'fest.finance', 'fest.certificates', 'fest.catering', 'fest.schedule'],
    mcq: ['mcq.view', 'mcq.manage', 'mcq.attendance', 'mcq.marks'],
    training: ['training.view', 'training.manage'],
    ledger: ['finance.view', 'membership.view', 'membership.manage'],
    users: ['users.manage'],
};

function canNav(section) {
    if (!isStaffUser.value) return true;
    const perms = staffPermissions.value;
    const required = STAFF_NAV[section];
    if (!required) return true;
    return required.some(p => perms.includes(p));
}

watch(() => page.url, () => {
    mobileNavOpen.value = false;
    navSearch.value = '';
});

const navGroups = computed(() => {
    const options = {
        canNav,
        isStaffUser: isStaffUser.value,
        websiteEnabled: websiteEnabled.value,
        publicWebsiteEnabled: publicWebsiteEnabled.value,
        approvedSchoolsCount: props.approvedSchoolsCount,
        pendingPaymentsCount: props.pendingPaymentsCount,
        pendingSubmissionsCount: props.pendingSubmissionsCount || page.props.pendingSubmissionsCount || 0,
        pendingSchoolsCount: props.pendingSchoolsCount || page.props.pendingSchoolsCount || 0,
        setupIncompleteCount: props.setupIncompleteCount ?? page.props.setupIncompleteCount ?? 0,
        pendingChangeRequests: props.pendingChangeRequests || page.props.pendingChangeRequests || 0,
        unverifiedStudentsCount: props.unverifiedStudentsCount || page.props.unverifiedStudentsCount || 0,
        pendingFestAppealsCount: props.pendingFestAppealsCount || page.props.pendingFestAppealsCount || 0,
        stateRemittancesEnabled: page.props.stateRemittancesEnabled !== false,
        navVisibility: page.props.navVisibility ?? null,
        competitionPrograms: page.props.competitionPrograms ?? {},
        scopedEventTypes: page.props.scopedEventTypes ?? null,
    };

    const examId = detectSahodayaMcqExamIdFromUrl(page.url);
    if (examId) {
        return sahodayaMcqExamScopedNav(props.sahodaya.id, examId, options);
    }

    const seriesId = detectSahodayaMcqSeriesIdFromUrl(page.url);
    if (seriesId) {
        return sahodayaMcqSeriesScopedNav(props.sahodaya.id, seriesId, options);
    }

    if (detectSahodayaMcqHubFromUrl(page.url)) {
        return sahodayaMcqHubNav(props.sahodaya.id, options);
    }

    const trainingProgramId = detectSahodayaTrainingProgramIdFromUrl(page.url);
    if (trainingProgramId) {
        return sahodayaTrainingProgramScopedNav(props.sahodaya.id, trainingProgramId, options);
    }

    if (detectSahodayaTrainingHubFromUrl(page.url)) {
        return sahodayaTrainingHubNav(props.sahodaya.id, options);
    }

    if (detectSahodayaMembershipFromUrl(page.url)) {
        return sahodayaMembershipScopedNav(props.sahodaya.id, options);
    }

    return sahodayaAdminNav(props.sahodaya.id, options);
});

const filteredNavGroups = computed(() => filterNavGroups(navGroups.value, navSearch.value));

function isActive(href)  { return page.url.startsWith(href); }
function isExact(href)   { return page.url === href || page.url === href + '/'; }

const NavItem = defineComponent({
    props: {
        href: String,
        icon: String,
        label: String,
        active: Boolean,
        badge: { type: Number, default: 0 },
    },
    setup(props) {
        return () => h(Link, {
            href: props.href,
            class: [
                'flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition w-full border-l-2',
                props.active
                    ? 'sa-nav-active border-[#fbbf24] bg-white/12 text-white font-semibold'
                    : 'border-transparent text-white/60 hover:bg-white/8 hover:text-white/90',
            ],
        }, {
            default: () => [
                h(SvgIcon, { name: props.icon, class: 'w-4 h-4 shrink-0' }),
                h('span', { class: 'flex-1 truncate' }, props.label),
                props.badge > 0
                    ? h('span', {
                        class: 'bg-[#fbbf24] text-[#041525] text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none',
                    }, props.badge > 99 ? '99+' : props.badge)
                : null,
            ],
        });
    },
});
</script>

<style scoped>
/* Malappuram logo palette — navy + gold */
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
