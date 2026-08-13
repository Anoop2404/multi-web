<template>
    <PortalLayout
        role-label="Teacher Portal"
        :title="teacher.name"
        :subtitle="profileSubtitle"
        accent="navy"
        :nav-items="navItems"
    >
        <!-- Hero -->
        <div class="dash-hero">
            <div class="flex items-start gap-4">
                <div class="shrink-0">
                    <img
                        v-if="teacher.photo_url"
                        :src="teacher.photo_url"
                        :alt="teacher.name"
                        class="h-16 w-16 rounded-full object-cover border-2 border-white/30 shadow"
                    >
                    <div
                        v-else
                        class="h-16 w-16 rounded-full bg-white/15 flex items-center justify-center text-xl font-bold border-2 border-white/30"
                    >
                        {{ initials }}
                    </div>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="dash-hero-eyebrow">Teacher portal</p>
                    <h1 class="dash-hero-title truncate">{{ teacher.name }}</h1>
                    <p class="dash-hero-desc">{{ school.name }}</p>
                    <div class="dash-hero-badges">
                        <span v-if="teacher.designation" class="dash-badge dash-badge--gold">{{ teacher.designation }}</span>
                        <span v-if="teacher.subject" class="dash-badge">{{ teacher.subject }}</span>
                        <span v-if="teacher.reg_no" class="dash-badge">{{ teacher.reg_no }}</span>
                    </div>
                </div>
                <a :href="`/portal/teacher/${school.id}/profile`"
                   class="shrink-0 text-xs font-semibold bg-white/15 hover:bg-white/25 px-3 py-1.5 rounded-lg transition">
                    Edit profile
                </a>
            </div>
        </div>

        <!-- Quick links -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 my-6">
            <QuickActionCard
                v-for="link in quickLinks"
                :key="link.href"
                :href="link.href"
                :label="link.label"
                :description="link.description"
                :icon="link.icon"
            />
        </div>

        <!-- Key stats -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 mb-6">
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

        <!-- Open training programmes -->
        <section class="card mb-5">
            <div class="flex items-center justify-between gap-2 mb-3">
                <h2 class="section-title text-base">Teacher training — register</h2>
                <Link :href="`${base}/training`" class="link-brand text-xs">View all →</Link>
            </div>
            <div v-if="registerablePrograms.length" class="grid gap-3 sm:grid-cols-2">
                <div v-for="p in registerablePrograms" :key="p.id" class="program-card">
                    <div class="flex items-center gap-3">
                        <span class="program-card-icon">📚</span>
                        <p class="font-semibold text-slate-900">{{ p.title }}</p>
                    </div>
                    <p class="text-xs text-slate-500">
                        <span v-if="p.venue">{{ p.venue }}</span>
                        <span v-if="p.start_date"> · {{ formatDate(p.start_date) }}<span v-if="p.end_date && p.end_date !== p.start_date"> – {{ formatDate(p.end_date) }}</span></span>
                        <span v-if="p.has_fee"> · Fee ₹{{ p.fee_amount }}</span>
                    </p>
                    <button type="button"
                            class="btn-primary text-xs mt-1 !min-h-0 !py-1.5 !px-3 w-fit"
                            :disabled="registering === p.id"
                            @click="register(p)">
                        {{ registering === p.id ? 'Registering…' : 'Register now' }}
                    </button>
                </div>
            </div>
            <p v-else-if="openPrograms?.length" class="notice-banner notice-banner--warning">
                No programmes open for self-registration right now.
                <Link :href="`${base}/training`" class="font-semibold underline">Check training page</Link> for details.
            </p>
            <EmptyState v-else title="No open training programmes" description="Check back later for new Sahodaya teacher training opportunities." icon="📚" />
        </section>

        <!-- My training -->
        <section v-if="training?.length" class="card mb-5">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="section-title text-base">My training</h2>
                <Link :href="`${base}/training`" class="link-brand text-xs">Manage →</Link>
            </div>
            <ul class="text-sm divide-y divide-slate-100">
                <li v-for="t in training" :key="t.id" class="py-2.5 flex justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ t.program?.title }}</p>
                        <p class="text-xs text-slate-500 capitalize">{{ t.status }}<span v-if="t.fee_status"> · fee {{ t.fee_status.replace('_', ' ') }}</span></p>
                    </div>
                    <a v-if="t.certificate_uuid"
                       :href="`${base}/training/${t.id}/certificate`"
                       target="_blank"
                       class="text-xs font-semibold text-[#0f3d7a] shrink-0">Certificate ↗</a>
                </li>
            </ul>
        </section>

        <!-- Fest schedule -->
        <section v-if="festDaySlots?.length" class="card mb-5">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="section-title text-base">Upcoming fest schedule</h2>
                <Link :href="`${base}/fest/schedule`" class="link-brand text-xs">Full schedule →</Link>
            </div>
            <ul class="text-sm divide-y divide-slate-100">
                <li v-for="(slot, i) in festDaySlots" :key="i" class="py-2">
                    <p class="font-medium">{{ slot.event_title }} — {{ slot.item_title }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <span v-if="slot.level_reg">Reg: {{ slot.level_reg }}</span>
                        <span v-if="slot.chest_no"> · Chest #{{ slot.chest_no }}</span>
                        <span v-if="slot.stage"> · {{ slot.stage }}</span>
                    </p>
                </li>
            </ul>
        </section>

        <!-- Notifications -->
        <section class="card">
            <h2 class="section-title text-base mb-2">Notifications</h2>
            <ul v-if="notifications?.length" class="activity-timeline">
                <li v-for="n in notifications" :key="n.id" class="activity-item">
                    <span class="activity-dot" />
                    <div class="min-w-0 flex-1 pb-1">
                        <p class="text-sm font-medium text-slate-800">{{ n.title }}</p>
                        <p v-if="n.body" class="text-xs text-slate-500 mt-0.5">{{ n.body }}</p>
                    </div>
                </li>
            </ul>
            <EmptyState v-else title="No notifications" icon="🔔" />
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
    if (props.teacher?.reg_no) parts.push(props.teacher.reg_no);
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
    { href: `${base.value}/question-banks`, label: 'Question banks', description: 'Talent Search items', icon: '🗂️' },
    { href: `${base.value}/question-papers`, label: 'Question papers', description: 'Upload & manage', icon: '📄' },
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
