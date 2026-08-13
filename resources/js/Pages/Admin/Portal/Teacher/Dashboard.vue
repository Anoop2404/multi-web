<template>
    <PortalLayout
        role-label="Teacher Portal"
        :title="teacher.name"
        :subtitle="profileSubtitle"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="teacher.photo_url"
        show-avatar-placeholder
    >
        <!-- Hero Card -->
        <div class="dash-hero relative overflow-hidden rounded-3xl border border-slate-200/80 bg-gradient-to-br from-[#041525] via-[#091e36] via-60% to-[#0f3d7a] p-6 sm:p-8 text-white shadow-[0_12px_40px_rgba(4,21,37,0.25)]">
            <!-- Decorative backdrop glow & ambient shapes -->
            <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-[#d4a017]/15 blur-3xl" />
            <div class="pointer-events-none absolute -left-16 -bottom-16 h-56 w-56 rounded-full bg-[#1e5aa8]/20 blur-2xl" />

            <div class="relative z-10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
                <div class="flex items-start sm:items-center gap-5 min-w-0">
                    <div class="shrink-0 relative">
                        <img
                            v-if="teacher.photo_url"
                            :src="teacher.photo_url"
                            :alt="teacher.name"
                            class="h-20 w-20 rounded-2xl object-cover border-2 border-white/30 shadow-xl ring-4 ring-white/10"
                        >
                        <div
                            v-else
                            class="h-20 w-20 rounded-2xl bg-gradient-to-br from-white/20 to-white/10 backdrop-blur-md flex items-center justify-center text-2xl font-extrabold border-2 border-white/30 shadow-xl text-white ring-4 ring-white/10"
                        >
                            {{ initials }}
                        </div>
                        <span class="absolute -bottom-1 -right-1 h-5 w-5 rounded-full bg-emerald-500 border-2 border-[#091e36] flex items-center justify-center text-white text-[10px]" title="Active Portal">
                            ✓
                        </span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="dash-badge dash-badge--gold uppercase tracking-wider text-[10px] font-bold">Teacher Portal</span>
                            <span class="dash-badge dash-badge--success text-[10px] font-bold flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse" />
                                Verified Member
                            </span>
                        </div>
                        <h1 class="dash-hero-title text-2xl sm:text-3xl font-extrabold tracking-tight truncate text-white mt-1">{{ teacher.name }}</h1>
                        <p class="text-sm font-medium text-white/80 flex items-center gap-1.5 mt-0.5">
                            <svg class="w-4 h-4 text-white/60 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            {{ school.name }}
                        </p>

                        <div class="dash-hero-badges mt-3">
                            <span v-if="teacher.designation" class="dash-badge dash-badge--gold">{{ teacher.designation }}</span>
                            <span v-if="teacher.subject" class="dash-badge">{{ teacher.subject }}</span>
                            <span v-if="teacher.reg_no" class="dash-badge">ID: {{ teacher.reg_no }}</span>
                        </div>
                    </div>
                </div>

                <a :href="`/portal/teacher/${school.id}/profile`"
                   class="shrink-0 inline-flex items-center gap-2 text-xs font-bold text-white bg-white/15 hover:bg-white/25 active:scale-95 px-4 py-2.5 rounded-xl transition duration-150 backdrop-blur-md border border-white/20 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Profile
                </a>
            </div>
        </div>

        <!-- Quick Links -->
        <section>
            <div class="flex items-center justify-between gap-2 mb-3">
                <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-[#0f3d7a]" />
                    Quick Action Hub
                </h2>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <QuickActionCard
                    v-for="link in quickLinks"
                    :key="link.href"
                    :href="link.href"
                    :label="link.label"
                    :description="link.description"
                    :icon="link.icon"
                />
            </div>
        </section>

        <!-- Key Metrics -->
        <section>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <DashboardStatCard
                    label="Open training"
                    :value="openPrograms?.length ?? 0"
                    icon="📚"
                    tone="navy"
                    :href="`${base}/training`"
                />
                <DashboardStatCard
                    label="My training"
                    :value="training?.length ?? 0"
                    icon="🎓"
                    tone="indigo"
                    :href="`${base}/training`"
                />
                <DashboardStatCard
                    label="Fest schedule"
                    :value="festDaySlots?.length ?? 0"
                    icon="🎭"
                    tone="gold"
                    :href="`${base}/fest/schedule`"
                />
                <DashboardStatCard
                    label="Notifications"
                    :value="notifications?.length ?? 0"
                    icon="🔔"
                    tone="green"
                />
            </div>
        </section>

        <!-- Open training programmes -->
        <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-2 mb-4">
                <div>
                    <h2 class="section-title text-base font-bold text-slate-900">Teacher Training Opportunities</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Explore open programmes for professional growth & Sahodaya credits.</p>
                </div>
                <Link :href="`${base}/training`" class="link-brand text-xs flex items-center gap-1 font-semibold">
                    View all
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </Link>
            </div>

            <div v-if="registerablePrograms.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="p in registerablePrograms" :key="p.id" class="program-card group flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-[#0f3d7a]/30 hover:shadow-md">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="program-card-icon h-10 w-10 flex items-center justify-center rounded-xl bg-[#eff6ff] text-[#0f3d7a] shrink-0 font-bold">
                                📚
                            </span>
                            <p class="font-bold text-slate-900 group-hover:text-[#0f3d7a] transition line-clamp-1">{{ p.title }}</p>
                        </div>
                        <p v-if="p.description" class="text-xs text-slate-600 mt-2 line-clamp-2 leading-relaxed">{{ p.description }}</p>

                        <div class="mt-3 space-y-1.5 text-xs text-slate-500 border-t border-slate-100 pt-3">
                            <p v-if="p.venue" class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="truncate">{{ p.venue }}</span>
                            </p>
                            <p v-if="p.start_date" class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ formatDate(p.start_date) }}<span v-if="p.end_date && p.end_date !== p.start_date"> – {{ formatDate(p.end_date) }}</span>
                            </p>
                            <p v-if="p.has_fee" class="flex items-center gap-1.5 font-semibold text-slate-700">
                                <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Fee: ₹{{ p.fee_amount }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 pt-2 border-t border-slate-100">
                        <button type="button"
                                class="btn-primary text-xs w-full justify-center !min-h-0 !py-2 !px-4 shadow-sm"
                                :disabled="registering === p.id"
                                @click="register(p)">
                            <svg v-if="registering !== p.id" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ registering === p.id ? 'Registering…' : 'Register Now' }}
                        </button>
                    </div>
                </div>
            </div>
            <p v-else-if="openPrograms?.length" class="notice-banner notice-banner--warning flex items-center justify-between">
                <span>No programmes currently open for self-registration.</span>
                <Link :href="`${base}/training`" class="font-semibold underline">Check training page</Link>
            </p>
            <EmptyState v-else title="No open training programmes" description="Check back later for upcoming Sahodaya teacher training events." icon="📚" />
        </section>

        <!-- Two column grid: My Training & Fest Schedule -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- My Training Overview -->
            <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                            🎓 My Training Status
                        </h2>
                        <Link :href="`${base}/training`" class="link-brand text-xs font-semibold">Manage →</Link>
                    </div>
                    <ul v-if="training?.length" class="text-sm divide-y divide-slate-100">
                        <li v-for="t in training" :key="t.id" class="py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-slate-900 truncate">{{ t.program?.title }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold capitalize bg-slate-100 text-slate-700">
                                        {{ t.status }}
                                    </span>
                                    <span v-if="t.fee_status" class="text-xs text-slate-500 capitalize">
                                        · Fee: {{ t.fee_status.replace('_', ' ') }}
                                    </span>
                                </div>
                            </div>
                            <a v-if="t.certificate_uuid"
                               :href="`${base}/training/${t.id}/certificate`"
                               target="_blank"
                               class="inline-flex items-center gap-1 text-xs font-bold text-[#0f3d7a] bg-[#eff6ff] hover:bg-[#dbeafe] px-2.5 py-1.5 rounded-lg transition shrink-0">
                                Certificate
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </a>
                        </li>
                    </ul>
                    <EmptyState v-else title="No registrations yet" description="Enroll in an open training programme above." icon="🎓" />
                </div>
            </section>

            <!-- Upcoming Fest Schedule -->
            <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                            🎭 Upcoming Fest Schedule
                        </h2>
                        <Link :href="`${base}/fest/schedule`" class="link-brand text-xs font-semibold">Full schedule →</Link>
                    </div>
                    <ul v-if="festDaySlots?.length" class="text-sm divide-y divide-slate-100">
                        <li v-for="(slot, i) in festDaySlots" :key="i" class="py-3 flex items-start gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700 font-bold text-xs mt-0.5">
                                #{ i + 1 }
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-slate-900">{{ slot.event_title }} — {{ slot.item_title }}</p>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 mt-1">
                                    <span v-if="slot.level_reg" class="bg-slate-100 px-2 py-0.5 rounded font-mono text-[11px]">Reg: {{ slot.level_reg }}</span>
                                    <span v-if="slot.chest_no" class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-bold text-[11px]">Chest #{{ slot.chest_no }}</span>
                                    <span v-if="slot.stage" class="text-slate-600 font-medium">📍 {{ slot.stage }}</span>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <EmptyState v-else title="No fest entries" description="No scheduled fest entries assigned yet." icon="🎭" />
                </div>
            </section>
        </div>

        <!-- Activity & Notifications Stream -->
        <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
            <h2 class="section-title text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                🔔 Recent Activity & Notifications
            </h2>
            <ul v-if="notifications?.length" class="activity-timeline space-y-3">
                <li v-for="n in notifications" :key="n.id" class="activity-item flex gap-4 items-start">
                    <span class="activity-dot mt-1" />
                    <div class="min-w-0 flex-1 bg-slate-50/80 border border-slate-100 rounded-xl p-3">
                        <p class="text-sm font-bold text-slate-900">{{ n.title }}</p>
                        <p v-if="n.body" class="text-xs text-slate-600 mt-1 leading-relaxed">{{ n.body }}</p>
                    </div>
                </li>
            </ul>
            <EmptyState v-else title="No notifications" description="You're all caught up!" icon="🔔" />
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import DashboardStatCard from '@/Components/ui/DashboardStatCard.vue';
import QuickActionCard from '@/Components/ui/QuickActionCard.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { teacherPortalNavItems } from '@/support/teacherPortalNav.js';

const props = defineProps({
    school: Object,
    teacher: Object,
    openPrograms: { type: Array, default: () => [] },
    training: { type: Array, default: () => [] },
    festDaySlots: { type: Array, default: () => [] },
    notifications: { type: Array, default: () => [] },
});

const navItems = computed(() => teacherPortalNavItems(props.school.id));
const registering = ref(null);
const base = computed(() => `/portal/teacher/${props.school.id}`);

const profileSubtitle = computed(() => {
    const parts = [props.school?.name];
    if (props.teacher?.reg_no) parts.push(`Reg: ${props.teacher.reg_no}`);
    return parts.filter(Boolean).join(' · ');
});

const initials = computed(() => {
    const parts = (props.teacher?.name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
});

const registerablePrograms = computed(() =>
    (props.openPrograms ?? []).filter(p => p.can_register).slice(0, 3),
);

const quickLinks = computed(() => [
    { href: `${base.value}/training`, label: 'Training', description: 'Register & track', icon: '📚' },
    { href: `${base.value}/fest`, label: 'Fest', description: 'Entries & schedule', icon: '🎭' },
    { href: `${base.value}/exams`, label: 'Talent Search', description: 'Open exams', icon: '📝' },
    { href: `${base.value}/question-banks`, label: 'Question Banks', description: 'MCQ Banks', icon: '🗂️' },
    { href: `${base.value}/question-papers`, label: 'Question Papers', description: 'Upload & manage', icon: '📄' },
    { href: `${base.value}/certificates`, label: 'Certificates', description: 'Print & download', icon: '🏆' },
]);

function formatDate(d) {
    if (!d) return '';
    return new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function register(program) {
    registering.value = program.id;
    router.post(`${base.value}/training/programs/${program.id}/register`, {}, {
        preserveScroll: true,
        onFinish: () => { registering.value = null; },
    });
}
</script>

