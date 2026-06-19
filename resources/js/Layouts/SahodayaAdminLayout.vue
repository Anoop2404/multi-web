<template>
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
                <a v-if="websiteEnabled && publicUrl" :href="publicUrl" target="_blank" rel="noopener"
                   class="sa-portal-link mt-3 flex items-center gap-1.5 w-full rounded-lg px-3 py-2 text-xs font-medium transition group">
                    <SvgIcon name="external-link" class="w-3.5 h-3.5 shrink-0 opacity-70" />
                    <span class="flex-1 truncate font-mono text-[11px]">{{ publicUrl }}</span>
                    <span class="text-white/40 group-hover:text-[#fbbf24] transition text-[10px] shrink-0">↗</span>
                </a>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 min-h-0 py-3 px-2 overflow-y-auto space-y-0.5">
                <NavItem :href="`/sahodaya-admin/${sahodaya.id}`" icon="grid" label="Dashboard"
                         :active="isExact(`/sahodaya-admin/${sahodaya.id}`)" />

                <template v-if="websiteEnabled">
                <NavSectionLabel text="Website" />
                <NavItem :href="`/sahodaya-admin/${sahodaya.id}/site-builder`"    icon="layers"     label="Layout & Sections" :active="isActive(`/sahodaya-admin/${sahodaya.id}/site-builder`)" />
                <NavItem :href="`/sahodaya-admin/${sahodaya.id}/public-content`"  icon="edit"       label="Website Content"   :active="isActive(`/sahodaya-admin/${sahodaya.id}/public-content`)" />
                <NavItem :href="`/sahodaya-admin/${sahodaya.id}/office-bearers`"  icon="users"      label="Office Bearers"    :active="isActive(`/sahodaya-admin/${sahodaya.id}/office-bearers`)" />
                <NavItem :href="`/sahodaya-admin/${sahodaya.id}/circulars`"       icon="file-text"  label="Circulars"         :active="isActive(`/sahodaya-admin/${sahodaya.id}/circulars`)" />

                <NavSectionLabel text="Events" />
                <NavItem :href="`/sahodaya-admin/${sahodaya.id}/kalotsav`" icon="star" label="Kalotsav / Events" :active="isActive(`/sahodaya-admin/${sahodaya.id}/kalotsav`)" />
                </template>

                <NavSectionLabel text="Membership" />
                <NavItem :href="`/sahodaya-admin/${sahodaya.id}/public-content`"
                         icon="edit" label="Portal Content"
                         :active="isActive(`/sahodaya-admin/${sahodaya.id}/public-content`)" />
                <NavItem :href="`/sahodaya-admin/${sahodaya.id}/membership/settings`"
                         icon="settings" label="Configuration"
                         :active="isActive(`/sahodaya-admin/${sahodaya.id}/membership/settings`)" />
                <NavItem :href="`/sahodaya-admin/${sahodaya.id}/schools`"
                         icon="building" label="Schools"
                         :badge="approvedSchoolsCount"
                         :active="isActive(`/sahodaya-admin/${sahodaya.id}/schools`)" />
                <NavItem :href="`/sahodaya-admin/${sahodaya.id}/membership/submissions`"
                         icon="inbox" label="Student Counts"
                         :active="isActive(`/sahodaya-admin/${sahodaya.id}/membership/submissions`)" />
                <NavItem :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`"
                         icon="credit-card" label="Payments"
                         :badge="pendingPaymentsCount"
                         :active="isActive(`/sahodaya-admin/${sahodaya.id}/membership/payments`)" />
                <NavItem :href="`/sahodaya-admin/${sahodaya.id}/membership/reports`"
                         icon="bar-chart" label="Reports"
                         :active="isActive(`/sahodaya-admin/${sahodaya.id}/membership/reports`)" />
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
                    <h1 class="text-base font-bold text-[#041525] truncate">{{ title }}</h1>
                    <slot name="header-suffix" />
                </div>
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <transition name="fade">
                        <span v-if="$page.props.flash?.success"
                              class="text-xs font-medium bg-green-50 text-green-700 border border-green-200 px-3 py-1.5 rounded-lg flex items-center gap-1.5">
                            <span class="text-green-500">✓</span> {{ $page.props.flash.success }}
                        </span>
                    </transition>
                    <transition name="fade">
                        <span v-if="$page.props.flash?.error"
                              class="text-xs font-medium bg-red-50 text-red-700 border border-red-200 px-3 py-1.5 rounded-lg flex items-center gap-1.5">
                            <span>✕</span> {{ $page.props.flash.error }}
                        </span>
                    </transition>
                    <a v-if="websiteEnabled && publicUrl" :href="publicUrl" target="_blank" rel="noopener"
                       class="sa-preview-btn inline-flex items-center gap-1.5 px-3 sm:px-4 py-2 rounded-lg text-white text-xs font-semibold transition shadow-sm">
                        <SvgIcon name="external-link" class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Preview Site</span>
                    </a>
                    <div class="hidden sm:flex items-center gap-2 pl-2 border-l border-gray-200">
                        <span v-if="$page.props.auth?.user?.name" class="text-xs text-gray-500 hidden sm:inline max-w-[8rem] truncate">
                            {{ $page.props.auth.user.name }}
                        </span>
                        <SignOutButton
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold text-gray-600 hover:text-red-600 hover:bg-red-50 transition">
                            <SvgIcon name="log-out" class="w-3.5 h-3.5" />
                            Sign out
                        </SignOutButton>
                    </div>
                </div>
            </header>

            <main class="sa-main flex-1 p-4 lg:p-6 overflow-auto">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import SignOutButton from '@/Components/SignOutButton.vue';
import { computed, defineComponent, h, ref, watch } from 'vue';

defineProps({
    title:                  { type: String, default: '' },
    sahodaya:               { type: Object, required: true },
    publicUrl:              { type: String, default: null },
    approvedSchoolsCount:   { type: Number, default: 0 },
    pendingSchoolsCount:    { type: Number, default: 0 },
    pendingSubmissionsCount:{ type: Number, default: 0 },
    pendingPaymentsCount:   { type: Number, default: 0 },
});

const page = usePage();
const mobileNavOpen = ref(false);
const websiteEnabled = computed(() => page.props.features?.website_enabled ?? false);

watch(() => page.url, () => {
    mobileNavOpen.value = false;
});

function isActive(href)  { return page.url.startsWith(href); }
function isExact(href)   { return page.url === href || page.url === href + '/'; }

const icons = {
    grid:          '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    edit:          '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
    users:         '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'file-text':   '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
    star:          '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
    settings:      '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    building:      '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01M12 14h.01"/>',
    inbox:         '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    'credit-card': '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
    'bar-chart':   '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
    'log-out':     '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    'external-link':'<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
    layers:         '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
};

const SvgIcon = defineComponent({
    props: { name: String, class: String },
    setup(props) {
        return () => h('svg', {
            xmlns: 'http://www.w3.org/2000/svg',
            viewBox: '0 0 24 24',
            fill: 'none',
            stroke: 'currentColor',
            'stroke-width': '2',
            'stroke-linecap': 'round',
            'stroke-linejoin': 'round',
            class: props.class || 'w-4 h-4',
            innerHTML: icons[props.name] || '',
        });
    },
});

const NavSectionLabel = defineComponent({
    props: { text: String },
    setup(props) {
        return () => h('p', {
            class: 'px-3 pt-4 pb-1 text-[10px] font-bold text-[#fbbf24]/75 uppercase tracking-widest',
        }, props.text);
    },
});

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
    background: linear-gradient(180deg, #f0f9ff 0%, #f8fafc 100%);
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
