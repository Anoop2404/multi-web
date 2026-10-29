<template>
    <PortalLayout
        role-label="Student Portal"
        :title="student.name"
        :subtitle="`${school.name} · ${student.reg_no}`"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="student.photo_url || ''"
    >
        <!-- Student Welcome & Quick ID Banner -->
        <section class="rounded-3xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-[0_2px_12px_rgba(15,23,42,0.04)] mb-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="relative shrink-0">
                        <img
                            v-if="student.photo_url"
                            :src="student.photo_url"
                            :alt="student.name"
                            class="h-16 w-16 sm:h-20 sm:w-20 rounded-2xl object-cover border-2 border-[#0f3d7a]/20 shadow-sm"
                        >
                        <div
                            v-else
                            class="h-16 w-16 sm:h-20 sm:w-20 rounded-2xl bg-gradient-to-br from-[#041525] to-[#0f3d7a] text-white flex items-center justify-center font-extrabold text-xl shadow-sm"
                        >
                            {{ initials }}
                        </div>
                        <span class="absolute -bottom-1 -right-1 h-5 w-5 rounded-full bg-emerald-500 border-2 border-white flex items-center justify-center text-[10px] text-white font-bold" title="Verified Student">✓</span>
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded-full bg-sky-50 text-sky-800 border border-sky-200">
                                Student Portal
                            </span>
                            <span class="font-mono text-xs font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md">
                                ID: {{ student.reg_no }}
                            </span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight mt-1 truncate">
                            {{ student.name }}
                        </h1>
                        <p class="text-xs text-slate-500 mt-0.5 truncate">
                            {{ school.name }}
                        </p>
                    </div>
                </div>

                <!-- Milestone Quick Chips -->
                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-semibold text-slate-700">
                        <span class="h-2 w-2 rounded-full bg-emerald-500" />
                        {{ runningExams.length }} Exam{{ runningExams.length === 1 ? '' : 's' }} Ready
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-semibold text-slate-700">
                        <span class="h-2 w-2 rounded-full bg-blue-500" />
                        {{ festDaySlots?.length ?? 0 }} Stage Slot{{ (festDaySlots?.length ?? 0) === 1 ? '' : 's' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-semibold text-slate-700">
                        <span class="h-2 w-2 rounded-full bg-amber-500" />
                        {{ (festCerts?.length ?? 0) + certsFromExams }} Certificate{{ ((festCerts?.length ?? 0) + certsFromExams) === 1 ? '' : 's' }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Smart Dynamic Hero Banner -->
        <!-- 1. Next Online Exam Hero -->
        <section
            v-if="nextExam && (nextExam.can_take_online || countdownLabel)"
            class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#041525] via-[#0f3d7a] to-[#1e5aa8] text-white p-5 sm:p-7 shadow-lg mb-5"
        >
            <div class="absolute inset-0 opacity-30 bg-[radial-gradient(ellipse_at_top_right,_rgba(212,160,23,0.35),_transparent_55%)] pointer-events-none" />
            <div class="relative space-y-3.5">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-amber-300">
                        {{ nextExam.can_take_online ? 'Action Required · Live Exam' : 'Upcoming Online Exam' }}
                    </span>
                    <span
                        v-if="nextExam.can_take_online"
                        class="text-[10px] font-bold uppercase tracking-wide px-2.5 py-0.5 rounded-full bg-emerald-400/25 text-emerald-200 ring-1 ring-emerald-300/40 animate-pulse"
                    >
                        Ready to start now
                    </span>
                </div>
                <div>
                    <h2 class="text-xl sm:text-2xl font-extrabold leading-tight tracking-tight">
                        {{ nextExam.exam?.title ?? nextExam.title }}
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-200/90 mt-1 flex flex-wrap items-center gap-2">
                        <span v-if="nextExam.scheduled_at">📅 {{ formatWhen(nextExam.scheduled_at) }}</span>
                        <span v-if="nextExam.duration_minutes">⏱️ {{ nextExam.duration_minutes }} mins</span>
                        <span v-if="nextExam.delivery_mode">🌐 {{ nextExam.delivery_mode }}</span>
                    </p>
                </div>

                <div v-if="countdownLabel" class="inline-flex items-center gap-2.5 rounded-2xl bg-black/30 backdrop-blur px-4 py-2 ring-1 ring-white/15">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-300">Starts in</span>
                    <span class="font-mono text-lg font-bold tabular-nums tracking-tight text-amber-300">{{ countdownLabel }}</span>
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    <a
                        v-if="nextExam.can_take_online"
                        :href="`/portal/student/${school.id}/mcq/${nextExam.registration_route_id ?? nextExam.id}/exam`"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-400 px-5 py-2.5 text-sm font-extrabold text-[#041525] hover:bg-amber-300 shadow-md transition"
                    >
                        <span>Take exam now</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                    <a
                        :href="`/portal/student/${school.id}/mcq`"
                        class="inline-flex items-center justify-center rounded-xl bg-white/10 px-4 py-2.5 text-sm font-bold text-white ring-1 ring-white/20 hover:bg-white/15 transition"
                    >
                        All exams
                    </a>
                    <a
                        v-if="nextExam.show_hall_ticket"
                        :href="`/portal/student/${school.id}/mcq/${nextExam.registration_route_id ?? nextExam.id}/hall-ticket`"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-bold text-white ring-1 ring-white/20 hover:bg-white/15 transition"
                    >
                        <span>Download hall ticket</span>
                        <svg class="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- 2. Next Fest Stage Slot Hero (when no urgent MCQ exam) -->
        <section
            v-else-if="nextFestSlot"
            class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0f3d7a] to-[#041525] text-white p-5 sm:p-7 shadow-lg mb-5"
        >
            <div class="relative space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-amber-300">Next Fest / Sports Competition</span>
                    <span v-if="nextFestSlot.stage" class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-white/15 text-white">
                        {{ nextFestSlot.stage }}
                    </span>
                </div>
                <h2 class="text-xl sm:text-2xl font-extrabold leading-tight">
                    {{ nextFestSlot.event_title }} — {{ nextFestSlot.item_title }}
                </h2>
                <div class="flex flex-wrap items-center gap-3 text-xs sm:text-sm text-slate-200">
                    <span v-if="nextFestSlot.chest_no" class="font-bold text-amber-300 bg-amber-400/20 px-2 py-0.5 rounded-md">
                        Chest #{{ nextFestSlot.chest_no }}
                    </span>
                    <span v-if="nextFestSlot.order">Order #{{ nextFestSlot.order }}</span>
                    <span v-if="nextFestSlot.scheduled_at">📅 {{ formatWhen(nextFestSlot.scheduled_at) }}</span>
                </div>
                <div class="pt-1">
                    <a
                        :href="`/portal/student/${school.id}/fest/schedule`"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-amber-400 px-4 py-2 text-xs font-extrabold text-[#041525] hover:bg-amber-300 transition"
                    >
                        <span>View complete timetable</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- Key Metrics Strip -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-5">
            <DashboardStatCard
                label="Online Exams"
                :value="(mcqExams ?? []).length"
                icon="📝"
                hint="Tests & talent searches"
                :href="`/portal/student/${school.id}/mcq`"
                tone="navy"
            />
            <DashboardStatCard
                label="Fest & Sports"
                :value="(festDaySlots?.length ?? 0) + (registrations?.length ?? 0)"
                icon="🎭"
                hint="Timetables & stages"
                :href="`/portal/student/${school.id}/fest/schedule`"
                tone="navy"
            />
            <DashboardStatCard
                label="Results & Medals"
                :value="(festResults?.length ?? 0) + completedExamsCount"
                icon="🏆"
                hint="Positions & grades"
                :href="`/portal/student/${school.id}/results`"
                tone="navy"
            />
            <DashboardStatCard
                label="Documents & Passes"
                :value="(festCerts?.length ?? 0) + certsFromExams + (admitCardEvents?.length ?? 0)"
                icon="📄"
                hint="Certificates & passes"
                :href="`/portal/student/${school.id}/documents`"
                tone="navy"
            />
        </section>

        <!-- Quick Access Shortcuts -->
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <QuickActionCard
                label="Online Exams"
                description="Take or review tests"
                icon="📝"
                :href="`/portal/student/${school.id}/mcq`"
            />
            <QuickActionCard
                label="Device Check"
                description="Practice before exam day"
                icon="💻"
                :href="`/portal/student/${school.id}/mcq/system-check`"
            />
            <QuickActionCard
                label="My Schedule"
                description="Stages & chest numbers"
                icon="🎭"
                :href="`/portal/student/${school.id}/fest/schedule`"
            />
            <QuickActionCard
                label="Document Vault"
                description="Hall tickets & certificates"
                icon="📄"
                :href="`/portal/student/${school.id}/documents`"
            />
        </section>

        <!-- Main Workspace Tabs: Exams | Fest & Sports | Appeals -->
        <div class="space-y-6">
            <!-- Active / Running Exams Section -->
            <section class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-900 tracking-tight">Active Online Exams</h2>
                        <p class="text-xs text-slate-500 mt-0.5">MCQ assessments and talent searches assigned to you</p>
                    </div>
                    <a :href="`/portal/student/${school.id}/mcq`" class="inline-flex items-center gap-1 text-xs font-bold text-[#0f3d7a] hover:underline">
                        <span>All exams</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <div v-if="runningExams.length" class="space-y-3">
                    <StudentMcqExamCard
                        v-for="exam in runningExams"
                        :key="exam.registration_route_id ?? exam.id"
                        :school-id="school.id"
                        :exam="exam"
                    />
                </div>
                <div v-else-if="upcomingExams.length" class="space-y-3">
                    <StudentMcqExamCard
                        v-for="exam in upcomingExams.slice(0, 2)"
                        :key="exam.registration_route_id ?? exam.id"
                        :school-id="school.id"
                        :exam="exam"
                    />
                </div>
                <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-6 text-center">
                    <p class="text-sm font-semibold text-slate-600">No active online exams right now</p>
                    <p class="text-xs text-slate-400 mt-1">Upcoming assessments will appear here when scheduled by your school or Sahodaya.</p>
                    <a :href="`/portal/student/${school.id}/mcq/system-check`" class="btn-secondary text-xs mt-3 inline-flex items-center gap-1.5">
                        <span>Run device practice check</span>
                    </a>
                </div>
            </section>

            <!-- Fest & Sports Schedule & Activities (Clean Section, Not Collapsed) -->
            <section class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-900 tracking-tight">Fest & Sports Participation</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Your competition schedule, stages, and registrations</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a :href="`/portal/student/${school.id}/fest/schedule`" class="text-xs font-bold text-[#0f3d7a] hover:underline">
                            Full timetable →
                        </a>
                    </div>
                </div>

                <!-- Schedule slots -->
                <div v-if="festDaySlots?.length" class="space-y-2.5 mb-5">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Scheduled items</h3>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="(slot, i) in festDaySlots.slice(0, 4)"
                            :key="i"
                            class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3.5 space-y-2 hover:border-[#0f3d7a]/30 transition"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-bold text-slate-900 leading-snug">{{ slot.event_title }}</p>
                                <span v-if="slot.stage" class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-blue-100 text-blue-900 shrink-0">
                                    {{ slot.stage }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-600 font-medium">{{ slot.item_title }}</p>
                            <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-200/60 text-xs text-slate-500">
                                <span v-if="slot.chest_no" class="font-bold text-[#0f3d7a] bg-blue-50 px-1.5 py-0.5 rounded">
                                    Chest #{{ slot.chest_no }}
                                </span>
                                <span v-if="slot.order">Order #{{ slot.order }}</span>
                                <span v-if="slot.scheduled_at" class="text-slate-600">🕒 {{ formatWhen(slot.scheduled_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs text-slate-400 mb-5">
                    No scheduled stage times recorded yet. Check back closer to the event date.
                </div>

                <!-- Sports profile section if present -->
                <StudentSportsProfileSection
                    v-if="showSportsSection"
                    :sports-profile="sportsProfile"
                    context="portal"
                    :school-id="school.id"
                    class="mb-5"
                />

                <!-- Registrations summary -->
                <div class="pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Event Registrations</h3>
                        <a :href="`/portal/student/${school.id}/fest-registrations`" class="text-xs font-bold text-[#0f3d7a] hover:underline">
                            Manage entries →
                        </a>
                    </div>
                    <ul v-if="registrations?.length" class="text-sm divide-y divide-slate-100">
                        <li v-for="r in registrations" :key="r.id" class="py-2.5 flex items-center justify-between gap-2">
                            <div>
                                <span class="font-semibold text-slate-800">{{ r.event?.title }}</span>
                                <span class="text-slate-500 text-xs ml-1.5">— {{ r.item?.title ?? 'General' }}</span>
                            </div>
                            <span class="text-[11px] font-bold uppercase px-2 py-0.5 rounded-full capitalize"
                                  :class="r.status === 'confirmed' || r.status === 'registered' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'">
                                {{ r.status }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-xs text-slate-400 py-2">No fest registrations found.</p>
                </div>
            </section>

            <!-- Results & Appeals Section -->
            <section class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-900 tracking-tight">Appeals & Inquiries</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Submit an appeal for your evaluated performance within allowed deadlines</p>
                    </div>
                </div>

                <!-- Existing appeals list -->
                <div v-if="festAppeals?.length" class="mb-5 space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Your submitted appeals</h3>
                    <div v-for="(a, i) in festAppeals" :key="i" class="rounded-xl border border-slate-200/80 p-3 text-xs bg-slate-50/50">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-slate-900">{{ a.event_title }} — {{ a.item_title }}</span>
                            <span class="font-bold uppercase tracking-wider text-[10px] px-2 py-0.5 rounded-full"
                                  :class="a.status === 'approved' ? 'bg-emerald-50 text-emerald-800' : a.status === 'rejected' ? 'bg-red-50 text-red-800' : 'bg-amber-50 text-amber-800'">
                                {{ a.status }}
                            </span>
                        </div>
                        <p class="text-slate-600 mt-1">Reason: {{ a.reason }}</p>
                        <p v-if="a.resolution" class="text-slate-800 font-medium mt-1 pt-1 border-t border-slate-200/60">
                            Resolution: {{ a.resolution }}
                        </p>
                    </div>
                </div>

                <!-- New appeal form using FormField (Solves UI/UX Audit Finding 6) -->
                <div v-if="appealableParticipants?.length" class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-3">Submit new appeal</h3>
                    <form @submit.prevent="submitAppeal" class="space-y-3">
                        <FormField
                            label="Select event & item to appeal"
                            hint="Only entries within the allowed appeal window are listed"
                            required
                        >
                            <SearchableSelect
                                v-model="appealForm.participant_id"
                                :options="appealParticipantOptions"
                                :all-option="true"
                                all-label="Choose an entry…"
                                :required="true"
                            />
                        </FormField>

                        <FormField
                            label="Reason for appeal"
                            hint="Clearly describe the discrepancy or procedural concern"
                            required
                        >
                            <textarea
                                v-model="appealForm.reason"
                                class="field text-sm"
                                rows="3"
                                placeholder="Explain reason for contesting the result..."
                                required
                            />
                        </FormField>

                        <div class="flex justify-end pt-1">
                            <button type="submit" class="btn-primary text-xs !min-h-10 px-4">
                                Submit appeal
                            </button>
                        </div>
                    </form>
                </div>
                <p v-else-if="!festAppeals?.length" class="text-xs text-slate-400 py-1">
                    No items currently open for appeal.
                </p>
            </section>

            <!-- Notifications / In-app alerts -->
            <section v-if="notifications?.length" class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-base">🔔</span>
                    <h2 class="text-sm font-extrabold text-slate-900 tracking-tight">Announcements & Notifications</h2>
                </div>
                <ul class="text-xs divide-y divide-slate-100">
                    <li v-for="n in notifications" :key="n.id" class="py-2.5 text-slate-700 flex items-start gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#0f3d7a] mt-1.5 shrink-0" />
                        <div>
                            <p class="font-semibold text-slate-900">{{ n.title }}</p>
                            <p v-if="n.body" class="text-slate-500 mt-0.5">{{ n.body }}</p>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import StudentMcqExamCard from '@/Components/portal/StudentMcqExamCard.vue';
import StudentSportsProfileSection from '@/Components/students/StudentSportsProfileSection.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import DashboardStatCard from '@/Components/ui/DashboardStatCard.vue';
import QuickActionCard from '@/Components/ui/QuickActionCard.vue';
import FormField from '@/Components/ui/FormField.vue';
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

const initials = computed(() => {
    const parts = (props.student?.name || 'S').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'S';
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
});

const showSportsSection = computed(() =>
    (props.sportsProfile?.sports_events ?? []).length > 0
    || (props.sportsProfile?.other_fest ?? []).length > 0
    || props.sportsProfile?.has_open_sports_events,
);

const runningExams = computed(() =>
    (props.mcqExams ?? []).filter((e) => e.can_take_online),
);

const upcomingExams = computed(() =>
    (props.mcqExams ?? []).filter((e) => !e.can_take_online && e.status !== 'submitted'),
);

const completedExamsCount = computed(() =>
    (props.mcqExams ?? []).filter((e) => e.status === 'submitted' || e.show_results).length,
);

const certsFromExams = computed(() =>
    (props.mcqExams ?? []).filter((e) => e.certificate_url).length,
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

const nextFestSlot = computed(() => {
    if (!props.festDaySlots?.length) return null;
    return props.festDaySlots[0];
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

function formatWhen(iso) {
    try {
        return new Date(iso).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
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
