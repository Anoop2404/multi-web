<template>
    <PortalLayout
        role-label="Teacher Portal"
        :title="teacher.name"
        :subtitle="profileSubtitle"
        accent="indigo"
        :nav-items="navItems"
        :avatar-url="teacher.photo_url"
        show-avatar-placeholder
    >
        <!-- Profile summary -->
        <section class="card mb-5 !p-0 overflow-hidden">
            <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 px-5 py-4 text-white">
                <div class="flex items-center gap-4">
                    <div class="shrink-0">
                        <img
                            v-if="teacher.photo_url"
                            :src="teacher.photo_url"
                            :alt="teacher.name"
                            class="h-16 w-16 rounded-full object-cover border-2 border-white/30 shadow"
                        >
                        <div
                            v-else
                            class="h-16 w-16 rounded-full bg-white/20 flex items-center justify-center text-xl font-bold border-2 border-white/30"
                        >
                            {{ initials }}
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-lg font-bold truncate">{{ teacher.name }}</p>
                        <p v-if="teacher.designation" class="text-sm text-indigo-100">{{ teacher.designation }}</p>
                        <p class="text-xs text-indigo-200 mt-0.5">{{ school.name }}<span v-if="teacher.reg_no"> · {{ teacher.reg_no }}</span></p>
                    </div>
                    <a :href="`/portal/teacher/${school.id}/profile`"
                       class="shrink-0 text-xs font-semibold bg-white/15 hover:bg-white/25 px-3 py-1.5 rounded-lg transition">
                        Edit profile
                    </a>
                </div>
            </div>
        </section>

        <!-- Quick links -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <a v-for="link in quickLinks" :key="link.href"
               :href="link.href"
               class="card !p-4 hover:shadow-md hover:border-indigo-200 transition group text-center">
                <span class="text-2xl block mb-1">{{ link.icon }}</span>
                <span class="text-xs font-semibold text-slate-700 group-hover:text-indigo-700">{{ link.label }}</span>
            </a>
        </div>

        <!-- Open training programmes -->
        <section class="card mb-5">
            <div class="flex items-center justify-between gap-2 mb-3">
                <h2 class="font-semibold text-sm text-slate-900">Teacher training — register</h2>
                <a :href="`/portal/teacher/${school.id}/training`" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">View all →</a>
            </div>
            <div v-if="registerablePrograms.length" class="space-y-3">
                <div v-for="p in registerablePrograms" :key="p.id"
                     class="rounded-lg border border-slate-200 p-3 bg-slate-50/50">
                    <p class="font-medium text-sm text-slate-900">{{ p.title }}</p>
                    <p class="text-xs text-slate-500 mt-1">
                        <span v-if="p.venue">{{ p.venue }}</span>
                        <span v-if="p.start_date"> · {{ formatDate(p.start_date) }}<span v-if="p.end_date && p.end_date !== p.start_date"> – {{ formatDate(p.end_date) }}</span></span>
                        <span v-if="p.has_fee"> · Fee ₹{{ p.fee_amount }}</span>
                    </p>
                    <button type="button"
                            class="btn-primary text-xs mt-2 !min-h-0 !py-1.5 !px-3"
                            :disabled="registering === p.id"
                            @click="register(p)">
                        {{ registering === p.id ? 'Registering…' : 'Register now' }}
                    </button>
                </div>
            </div>
            <p v-else-if="openPrograms?.length" class="text-sm text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                No programmes open for self-registration right now.
                <a :href="`/portal/teacher/${school.id}/training`" class="font-semibold underline">Check training page</a> for details.
            </p>
            <p v-else class="text-sm text-slate-400 py-2">No open training programmes at the moment.</p>
        </section>

        <!-- My training -->
        <section v-if="training?.length" class="card mb-5">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="font-semibold text-sm text-slate-900">My training</h2>
                <a :href="`/portal/teacher/${school.id}/training`" class="text-xs font-semibold text-indigo-600">Manage →</a>
            </div>
            <ul class="text-sm divide-y divide-slate-100">
                <li v-for="t in training" :key="t.id" class="py-2.5 flex justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ t.program?.title }}</p>
                        <p class="text-xs text-slate-500 capitalize">{{ t.status }}<span v-if="t.fee_status"> · fee {{ t.fee_status.replace('_', ' ') }}</span></p>
                    </div>
                    <a v-if="t.certificate_uuid"
                       :href="`/portal/teacher/${school.id}/training/${t.id}/certificate`"
                       target="_blank"
                       class="text-xs font-semibold text-indigo-600 shrink-0">Certificate ↗</a>
                </li>
            </ul>
        </section>

        <!-- Fest schedule -->
        <section v-if="festDaySlots?.length" class="card mb-5">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="font-semibold text-sm text-slate-900">Upcoming fest schedule</h2>
                <a :href="`/portal/teacher/${school.id}/fest/schedule`" class="text-xs font-semibold text-indigo-600">Full schedule →</a>
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
            <h2 class="font-semibold text-sm text-slate-900 mb-2">Notifications</h2>
            <ul class="text-sm divide-y divide-slate-100">
                <li v-for="n in notifications" :key="n.id" class="py-2">
                    <p class="font-medium text-slate-800">{{ n.title }}</p>
                    <p v-if="n.body" class="text-xs text-slate-500 mt-0.5">{{ n.body }}</p>
                </li>
                <li v-if="!notifications?.length" class="text-slate-400 py-2">No notifications</li>
            </ul>
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
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

const quickLinks = computed(() => {
    const base = `/portal/teacher/${props.school.id}`;
    return [
        { href: `${base}/training`, label: 'Training', icon: '📚' },
        { href: `${base}/fest`, label: 'Fest', icon: '🎭' },
        { href: `${base}/question-banks`, label: 'Talent Search', icon: '📝' },
        { href: `${base}/certificates`, label: 'Certificates', icon: '🏆' },
    ];
});

function formatDate(d) {
    if (!d) return '';
    return new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function register(program) {
    registering.value = program.id;
    router.post(`/portal/teacher/${props.school.id}/training/programs/${program.id}/register`, {}, {
        preserveScroll: true,
        onFinish: () => { registering.value = null; },
    });
}
</script>
