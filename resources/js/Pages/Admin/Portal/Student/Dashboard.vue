<template>
    <PortalLayout
        role-label="Student Portal"
        :title="student.name"
        :subtitle="`${school.name} · ${student.reg_no}`"
        accent="indigo"
        :nav-items="navItems"
    >
        <section v-if="upcomingEvents?.length" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Upcoming events</h2>
            <ul class="text-sm divide-y">
                <li v-for="ev in upcomingEvents" :key="ev.id" class="py-2 flex justify-between gap-2">
                    <span>{{ ev.title }} <span class="text-xs text-gray-400 capitalize">({{ ev.event_type?.replace(/_/g, ' ') }})</span></span>
                    <span class="text-xs text-gray-500 shrink-0">{{ ev.event_start || ev.status }}</span>
                </li>
            </ul>
        </section>

        <StudentSportsProfileSection
            v-if="showSportsSection"
            :sports-profile="sportsProfile"
            context="portal"
            :school-id="school.id"
        />

        <section class="card mb-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="font-semibold text-sm">My registrations</h2>
                <a :href="`/portal/student/${school.id}/sports-results`" class="text-xs text-indigo-600 font-semibold">Sports results →</a>
            </div>
            <ul class="text-sm divide-y">
                <li v-for="r in registrations" :key="r.id" class="py-2">
                    {{ r.event?.title }} — {{ r.item?.title ?? 'General' }}
                    <span v-if="r.chest_no" class="text-xs text-indigo-700 ml-1">· Chest #{{ r.chest_no }}</span>
                    <span class="text-xs text-gray-400 ml-1 capitalize">({{ r.status }})</span>
                </li>
                <li v-if="!registrations?.length" class="text-gray-400 py-2">No fest registrations yet</li>
            </ul>
        </section>

        <section class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Talent Search Exams</h2>
            <ul class="text-sm divide-y">
                <li v-for="r in mcqExams" :key="r.registration_route_id ?? r.id" class="py-2 flex justify-between items-center gap-2 flex-wrap">
                    <div>
                        <span class="font-medium">{{ r.exam?.title ?? r.title }}</span>
                        <span class="text-xs text-gray-400 ml-1">({{ r.status }})</span>
                        <span v-if="r.delivery_mode === 'offline'" class="text-xs text-slate-500 ml-1">· offline</span>
                        <p v-if="r.show_results && r.mark" class="text-xs text-indigo-700 mt-0.5">
                            Score: {{ r.mark.score }}
                            <span v-if="r.mark.grade"> · Grade: {{ r.mark.grade }}</span>
                            <span v-if="r.mark.rank"> · Rank: {{ r.mark.rank }}</span>
                        </p>
                        <p v-else-if="r.show_results && !r.mark" class="text-xs text-gray-400 mt-0.5">Results pending</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a v-if="r.can_take_online"
                           :href="`/portal/student/${school.id}/mcq/${r.registration_route_id ?? r.id}/exam`"
                           class="text-xs font-semibold text-indigo-600">Take exam →</a>
                        <a v-if="r.show_hall_ticket"
                           :href="`/portal/student/${school.id}/mcq/${r.registration_route_id ?? r.id}/hall-ticket`"
                           target="_blank"
                           class="text-xs font-semibold text-indigo-600">Hall ticket ↗</a>
                        <span v-if="r.status === 'submitted' && !r.show_results" class="text-xs text-gray-400">Submitted</span>
                        <span v-else-if="r.delivery_mode === 'offline' && !r.show_hall_ticket" class="text-xs text-gray-400">Offline exam</span>
                        <span v-else-if="!r.show_hall_ticket && !r.can_take_online" class="text-xs text-gray-400">Ticket pending</span>
                    </div>
                </li>
                <li v-if="!mcqExams.length" class="text-gray-400 py-2">No Talent Search registrations</li>
            </ul>
        </section>

        <section id="fest-schedule" class="card mb-4 scroll-mt-24">
            <h2 class="font-semibold text-sm mb-2">My Fest Schedule</h2>
            <ul v-if="festDaySlots?.length" class="text-sm divide-y">
                <li v-for="(slot, i) in festDaySlots" :key="i" class="py-2">
                    <p class="font-medium">{{ slot.event_title }} — {{ slot.item_title }}</p>
                    <p class="text-xs text-gray-600 mt-0.5">
                        <span v-if="slot.level_reg">Reg: {{ slot.level_reg }}</span>
                        <span v-if="slot.chest_no"> · Chest #{{ slot.chest_no }}</span>
                        <span v-if="slot.order"> · Order #{{ slot.order }}</span>
                        <span v-if="slot.stage"> · {{ slot.stage }}</span>
                    </p>
                    <p v-if="slot.scheduled_at" class="text-xs text-indigo-700 mt-0.5">
                        {{ new Date(slot.scheduled_at).toLocaleString() }}
                    </p>
                </li>
            </ul>
            <p v-else class="text-sm text-gray-400 py-2">No scheduled fest items yet</p>
        </section>

        <section id="fest-results" class="card mb-4 scroll-mt-24">
            <h2 class="font-semibold text-sm mb-2">Fest Results</h2>
            <ul class="text-sm divide-y">
                <li v-for="(r, i) in festResults" :key="i" class="py-2">
                    <p class="font-medium">{{ r.event_title }} — {{ r.item_title }}</p>
                    <p v-if="r.grade || r.position || r.score" class="text-xs text-indigo-700 mt-0.5">
                        <span v-if="r.grade">Grade: {{ r.grade }}</span>
                        <span v-if="r.position"> · Position: {{ r.position }}</span>
                        <span v-if="r.score"> · Score: {{ r.score }}</span>
                        <span v-if="r.chest_no"> · Chest #{{ r.chest_no }}</span>
                    </p>
                    <p v-else class="text-xs text-gray-400 mt-0.5">Results not yet recorded</p>
                </li>
                <li v-if="!festResults.length" class="text-gray-400 py-2">No published fest results yet</li>
            </ul>
        </section>

        <section id="fest-certs" class="card mb-4 scroll-mt-24">
            <h2 class="font-semibold text-sm mb-2">Fest Certificates</h2>
            <ul class="text-sm divide-y">
                <li v-for="(c, i) in festCerts" :key="i" class="py-2 flex justify-between items-center gap-2">
                    <div>
                        <p class="font-medium">{{ c.event?.title ?? 'Event' }} — {{ c.item?.title ?? c.student?.name }}</p>
                        <p v-if="c.mark?.grade || c.mark?.position" class="text-xs text-indigo-700 mt-0.5">
                            <span v-if="c.mark?.grade">Grade: {{ c.mark.grade }}</span>
                            <span v-if="c.mark?.position"> · Position: {{ c.mark.position }}</span>
                        </p>
                    </div>
                    <a v-if="c.uuid"
                       :href="`/certificates/print/${c.uuid}`"
                       target="_blank"
                       rel="noopener"
                       class="text-xs font-semibold text-indigo-600 shrink-0">Download ↗</a>
                </li>
                <li v-if="!festCerts?.length" class="text-gray-400 py-2">No certificates yet</li>
            </ul>
        </section>

        <section v-if="admitCardEvents?.length" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Fest Admit Cards</h2>
            <ul class="text-sm divide-y">
                <li v-for="ev in admitCardEvents" :key="ev.id" class="py-2 flex justify-between items-center gap-2">
                    <span class="font-medium">{{ ev.title }}</span>
                    <a :href="`/portal/student/${school.id}/fest/${ev.id}/admit-card`"
                       target="_blank"
                       class="text-xs font-semibold text-indigo-600 shrink-0">Download PDF ↗</a>
                </li>
            </ul>
        </section>

        <section v-if="festAppeals?.length || appealableParticipants?.length" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Appeals</h2>
            <ul v-if="festAppeals?.length" class="text-sm divide-y mb-3">
                <li v-for="(a, i) in festAppeals" :key="i" class="py-2">
                    <p class="font-medium">{{ a.event_title }} — {{ a.item_title }}</p>
                    <p class="text-xs text-gray-500">{{ a.reason }}</p>
                    <p class="text-xs capitalize mt-0.5" :class="a.status === 'approved' ? 'text-green-700' : a.status === 'rejected' ? 'text-red-600' : 'text-amber-700'">{{ a.status }}</p>
                </li>
            </ul>
            <form v-if="appealableParticipants?.length" @submit.prevent="submitAppeal" class="border-t pt-3 space-y-2">
                <select v-model="appealForm.participant_id" class="field text-sm" required>
                    <option value="">Select entry to appeal…</option>
                    <option v-for="p in appealableParticipants" :key="p.participant_id" :value="p.participant_id">{{ p.event_title }} — {{ p.item_title }}</option>
                </select>
                <textarea v-model="appealForm.reason" class="field text-sm" rows="2" placeholder="Reason" required></textarea>
                <button type="submit" class="btn-primary text-xs">Submit appeal</button>
            </form>
        </section>

        <section class="card">
            <h2 class="font-semibold text-sm mb-2">Notifications</h2>
            <ul class="text-sm divide-y">
                <li v-for="n in notifications" :key="n.id" class="py-2">{{ n.title }}</li>
                <li v-if="!notifications.length" class="text-gray-400 py-2">No notifications</li>
            </ul>
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import StudentSportsProfileSection from '@/Components/students/StudentSportsProfileSection.vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';
import { computed, ref } from 'vue';
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

const showSportsSection = computed(() =>
    (props.sportsProfile?.sports_events ?? []).length > 0
    || (props.sportsProfile?.other_fest ?? []).length > 0
    || props.sportsProfile?.has_open_sports_events,
);

const appealForm = ref({ participant_id: '', reason: '' });

function submitAppeal() {
    const p = props.appealableParticipants.find(x => String(x.participant_id) === String(appealForm.value.participant_id));
    if (!p) return;
    router.post(`/portal/student/${props.school.id}/fest/${p.event_id}/appeals`, appealForm.value, {
        preserveScroll: true,
        onSuccess: () => { appealForm.value = { participant_id: '', reason: '' }; },
    });
}

const navItems = computed(() => studentPortalNavItems(props.school.id));
</script>
