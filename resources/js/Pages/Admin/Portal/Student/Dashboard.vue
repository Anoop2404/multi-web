<template>
    <PortalLayout
        role-label="Student Portal"
        :title="student.name"
        :subtitle="`${school.name} · ${student.reg_no}`"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="student.photo_url || ''"
    >
        <!-- Next exam hero -->
        <section
            v-if="nextExam"
            class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#041525] via-[#0f3d7a] to-[#1e5aa8] text-white p-5 sm:p-7 shadow-lg mb-5"
        >
            <div class="absolute inset-0 opacity-30 bg-[radial-gradient(ellipse_at_top_right,_rgba(212,160,23,0.35),_transparent_55%)]" />
            <div class="relative space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-amber-300">Next exam</span>
                    <span
                        v-if="nextExam.can_take_online"
                        class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-emerald-400/20 text-emerald-200 ring-1 ring-emerald-300/40"
                    >Ready to start</span>
                </div>
                <h2 class="text-xl sm:text-2xl font-extrabold leading-tight">{{ nextExam.exam?.title ?? nextExam.title }}</h2>
                <p class="text-sm text-slate-200/90">
                    <span v-if="nextExam.scheduled_at">{{ formatWhen(nextExam.scheduled_at) }}</span>
                    <span v-if="nextExam.duration_minutes"> · {{ nextExam.duration_minutes }} min</span>
                    <span v-if="nextExam.delivery_mode"> · {{ nextExam.delivery_mode }}</span>
                </p>
                <div v-if="countdownLabel" class="inline-flex items-center gap-2 rounded-2xl bg-black/25 px-4 py-2.5 ring-1 ring-white/10">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-300">Starts in</span>
                    <span class="font-mono text-lg font-bold tabular-nums tracking-tight">{{ countdownLabel }}</span>
                </div>
                <div class="flex flex-wrap gap-2 pt-1">
                    <a
                        v-if="nextExam.can_take_online"
                        :href="`/portal/student/${school.id}/mcq/${nextExam.registration_route_id}/exam`"
                        class="inline-flex items-center justify-center rounded-xl bg-amber-400 px-4 py-2.5 text-sm font-extrabold text-[#041525] hover:bg-amber-300"
                    >Take exam</a>
                    <a
                        :href="`/portal/student/${school.id}/mcq`"
                        class="inline-flex items-center justify-center rounded-xl bg-white/10 px-4 py-2.5 text-sm font-bold text-white ring-1 ring-white/20 hover:bg-white/15"
                    >All exams</a>
                    <a
                        v-if="nextExam.show_hall_ticket"
                        :href="`/portal/student/${school.id}/mcq/${nextExam.registration_route_id}/hall-ticket`"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center rounded-xl bg-white/10 px-4 py-2.5 text-sm font-bold text-white ring-1 ring-white/20 hover:bg-white/15"
                    >Hall ticket</a>
                </div>
            </div>
        </section>

        <!-- Quick actions -->
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <a
                v-for="action in quickActions"
                :key="action.href"
                :href="action.href"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-[#0f3d7a]/40 hover:shadow transition"
            >
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ action.kicker }}</p>
                <p class="mt-1 text-sm font-bold text-slate-900">{{ action.label }}</p>
            </a>
        </section>

        <!-- Running / actionable exams -->
        <section class="mb-6">
            <div class="flex items-center justify-between gap-2 mb-3">
                <h2 class="text-sm font-extrabold text-slate-900">Running exams</h2>
                <a :href="`/portal/student/${school.id}/mcq`" class="text-xs font-bold text-[#0f3d7a]">View all →</a>
            </div>
            <div v-if="runningExams.length" class="space-y-3">
                <StudentMcqExamCard
                    v-for="exam in runningExams"
                    :key="exam.registration_route_id ?? exam.id"
                    :school-id="school.id"
                    :exam="exam"
                />
            </div>
            <p v-else class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-400">
                No exams ready to take right now.
            </p>
        </section>

        <!-- Fest & sports (secondary) -->
        <details class="group rounded-2xl border border-slate-200 bg-white shadow-sm open:shadow-md mb-5">
            <summary class="cursor-pointer list-none px-4 py-3.5 flex items-center justify-between gap-2">
                <span class="text-sm font-extrabold text-slate-900">Fest &amp; sports</span>
                <span class="text-xs font-semibold text-slate-400 group-open:hidden">Show</span>
                <span class="text-xs font-semibold text-slate-400 hidden group-open:inline">Hide</span>
            </summary>
            <div class="border-t border-slate-100 px-4 py-4 space-y-4">
                <div v-if="upcomingEvents?.length">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Upcoming events</h3>
                    <ul class="text-sm divide-y">
                        <li v-for="ev in upcomingEvents" :key="ev.id" class="py-2 flex justify-between gap-2">
                            <span>{{ ev.title }}</span>
                            <span class="text-xs text-slate-500 shrink-0">{{ ev.event_start || ev.status }}</span>
                        </li>
                    </ul>
                </div>

                <StudentSportsProfileSection
                    v-if="showSportsSection"
                    :sports-profile="sportsProfile"
                    context="portal"
                    :school-id="school.id"
                />

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Registrations</h3>
                        <a :href="`/portal/student/${school.id}/fest-registrations`" class="text-xs font-bold text-[#0f3d7a]">Manage →</a>
                    </div>
                    <ul class="text-sm divide-y">
                        <li v-for="r in registrations" :key="r.id" class="py-2">
                            {{ r.event?.title }} — {{ r.item?.title ?? 'General' }}
                            <span class="text-xs text-slate-400 ml-1 capitalize">({{ r.status }})</span>
                        </li>
                        <li v-if="!registrations?.length" class="text-slate-400 py-2">No fest registrations yet</li>
                    </ul>
                </div>

                <div id="fest-schedule">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Schedule</h3>
                        <a :href="`/portal/student/${school.id}/fest/schedule`" class="text-xs font-bold text-[#0f3d7a]">Full schedule →</a>
                    </div>
                    <ul v-if="festDaySlots?.length" class="text-sm divide-y">
                        <li v-for="(slot, i) in festDaySlots.slice(0, 4)" :key="i" class="py-2">
                            <p class="font-medium">{{ slot.event_title }} — {{ slot.item_title }}</p>
                            <p v-if="slot.scheduled_at" class="text-xs text-[#0f3d7a] mt-0.5">{{ formatWhen(slot.scheduled_at) }}</p>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-slate-400 py-2">No scheduled fest items yet</p>
                </div>

                <div v-if="festAppeals?.length || appealableParticipants?.length" class="border-t border-slate-100 pt-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Appeals</h3>
                    <ul v-if="festAppeals?.length" class="text-sm divide-y mb-3">
                        <li v-for="(a, i) in festAppeals" :key="i" class="py-2">
                            <p class="font-medium">{{ a.event_title }} — {{ a.item_title }}</p>
                            <p class="text-xs capitalize mt-0.5" :class="a.status === 'approved' ? 'text-green-700' : a.status === 'rejected' ? 'text-red-600' : 'text-amber-700'">{{ a.status }}</p>
                        </li>
                    </ul>
                    <form v-if="appealableParticipants?.length" @submit.prevent="submitAppeal" class="space-y-2">
                        <SearchableSelect
                            v-model="appealForm.participant_id"
                            :options="appealParticipantOptions"
                            :all-option="true"
                            all-label="Select entry to appeal…"
                            :required="true"
                        />
                        <textarea v-model="appealForm.reason" class="field text-sm" rows="2" placeholder="Reason" required />
                        <button type="submit" class="btn-primary text-xs">Submit appeal</button>
                    </form>
                </div>
            </div>
        </details>

        <section v-if="notifications?.length" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-extrabold text-slate-900 mb-2">Notifications</h2>
            <ul class="text-sm divide-y">
                <li v-for="n in notifications" :key="n.id" class="py-2 text-slate-700">{{ n.title }}</li>
            </ul>
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import StudentMcqExamCard from '@/Components/portal/StudentMcqExamCard.vue';
import StudentSportsProfileSection from '@/Components/students/StudentSportsProfileSection.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    school: Object,
    student: Object,
    registrations: Array,
    sportsProfile: { type: Object, default: () => ({ sports_events: [], other_fest: [] }) },
    upcomingEvents: { type: Array, default: () => [] },
    festResults: Array,
    festDaySlots: Array,
    festCerts: Array,
    festAppeals: Array,
    appealableParticipants: Array,
    mcqExams: Array,
    notifications: Array,
    admitCardEvents: { type: Array, default: () => [] },
});

const navItems = computed(() => studentPortalNavItems(props.school.id));

const showSportsSection = computed(() =>
    (props.sportsProfile?.sports_events ?? []).length > 0
    || (props.sportsProfile?.other_fest ?? []).length > 0
    || props.sportsProfile?.has_open_sports_events,
);

const runningExams = computed(() =>
    (props.mcqExams ?? []).filter((e) => e.can_take_online),
);

const nextExam = computed(() => {
    const actionable = runningExams.value;
    if (actionable.length) {
        return actionable[0];
    }
    const upcoming = (props.mcqExams ?? [])
        .filter((e) => e.status !== 'submitted' && e.scheduled_at)
        .slice()
        .sort((a, b) => new Date(a.scheduled_at) - new Date(b.scheduled_at));
    return upcoming[0] ?? (props.mcqExams ?? []).find((e) => e.status !== 'submitted') ?? null;
});

const now = ref(Date.now());
let timerId = null;
onMounted(() => {
    timerId = window.setInterval(() => { now.value = Date.now(); }, 1000);
});
onUnmounted(() => {
    if (timerId) window.clearInterval(timerId);
});

const countdownLabel = computed(() => {
    if (!nextExam.value?.scheduled_at || nextExam.value.can_take_online) {
        return null;
    }
    const target = new Date(nextExam.value.scheduled_at).getTime();
    if (Number.isNaN(target)) return null;
    const diff = target - now.value;
    if (diff <= 0) return null;
    const totalSec = Math.floor(diff / 1000);
    const d = Math.floor(totalSec / 86400);
    const h = Math.floor((totalSec % 86400) / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    if (d > 0) return `${d}d ${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

const quickActions = computed(() => [
    { kicker: 'Exams', label: 'Online exams', href: `/portal/student/${props.school.id}/mcq` },
    { kicker: 'Prepare', label: 'System check', href: `/portal/student/${props.school.id}/mcq/system-check` },
    { kicker: 'Files', label: 'Documents', href: `/portal/student/${props.school.id}/documents` },
    { kicker: 'Account', label: 'Profile', href: `/portal/student/${props.school.id}/profile` },
]);

function formatWhen(iso) {
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return iso;
    }
}

const appealForm = ref({ participant_id: '', reason: '' });
const appealParticipantOptions = computed(() =>
    (props.appealableParticipants ?? []).map((p) => ({
        value: p.participant_id,
        label: `${p.event_title} — ${p.item_title}`,
    })),
);

function submitAppeal() {
    const p = props.appealableParticipants.find((x) => String(x.participant_id) === String(appealForm.value.participant_id));
    if (!p) return;
    router.post(`/portal/student/${props.school.id}/fest/${p.event_id}/appeals`, appealForm.value, {
        preserveScroll: true,
        onSuccess: () => { appealForm.value = { participant_id: '', reason: '' }; },
    });
}
</script>
