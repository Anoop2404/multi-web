<template>
    <PortalLayout
        role-label="Teacher Portal"
        :title="teacher.name"
        :subtitle="school.name"
        accent="indigo"
        :nav-items="navItems"
    >
        <nav v-if="jumpSections.length" class="sticky top-0 z-20 -mx-1 mb-4 px-1 py-2 bg-white/95 backdrop-blur border-b border-slate-100">
            <div class="flex flex-wrap gap-2">
                <a v-for="section in jumpSections" :key="section.id"
                   :href="`#${section.id}`"
                   class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 hover:bg-indigo-50 hover:text-indigo-800 transition">
                    {{ section.label }}
                </a>
            </div>
        </nav>

        <section v-if="mcqBanks?.length" id="section-mcq" class="card mb-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="font-semibold text-sm">MCQ question banks</h2>
                <a :href="`/portal/teacher/${school.id}/question-banks`" class="text-xs font-semibold text-indigo-600">Manage →</a>
            </div>
            <ul class="text-sm divide-y">
                <li v-for="b in mcqBanks" :key="b.id" class="py-2 flex justify-between gap-2">
                    <span>{{ b.title }}</span>
                    <span class="text-xs text-gray-500">{{ b.questions_count ?? 0 }} questions</span>
                </li>
            </ul>
        </section>

        <section v-if="training?.length" id="section-training" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Training programs</h2>
            <div v-for="t in training" :key="t.id" class="border-t first:border-0 pt-3 first:pt-0 mb-3">
                <p class="font-medium text-sm">{{ t.program?.title }}</p>
                <p class="text-xs text-gray-500 capitalize">{{ t.status }}</p>
                <ul v-if="t.sessions?.length" class="mt-2 text-xs space-y-1">
                    <li v-for="s in t.sessions" :key="s.id" class="text-gray-600">
                        {{ s.title }} · {{ s.scheduled_at ? new Date(s.scheduled_at).toLocaleString() : 'TBA' }}
                        <span v-if="s.venue"> · {{ s.venue }}</span>
                        <span v-if="s.attendance" class="ml-1 capitalize">({{ s.attendance }})</span>
                    </li>
                </ul>
                <a v-if="t.certificate_uuid" :href="`/portal/teacher/${school.id}/training/${t.id}/certificate`" target="_blank"
                   class="text-xs font-semibold text-indigo-600 mt-1 inline-block">Download certificate ↗</a>
            </div>
        </section>

        <section v-if="festRegistrations?.length" id="section-fest-reg" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Teacher Fest Registrations</h2>
            <ul class="text-sm divide-y">
                <li v-for="r in festRegistrations" :key="r.id" class="py-2">
                    {{ r.event?.title }} — {{ r.item?.title }} <span class="text-xs text-gray-400 capitalize">({{ r.status }})</span>
                </li>
            </ul>
        </section>

        <section v-if="festDaySlots?.length" id="section-schedule" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">My Fest Schedule</h2>
            <ul class="text-sm divide-y">
                <li v-for="(slot, i) in festDaySlots" :key="i" class="py-2">
                    <p class="font-medium">{{ slot.event_title }} — {{ slot.item_title }}</p>
                    <p class="text-xs text-gray-600">
                        <span v-if="slot.level_reg">Reg: {{ slot.level_reg }}</span>
                        <span v-if="slot.chest_no"> · Chest #{{ slot.chest_no }}</span>
                        <span v-if="slot.stage"> · {{ slot.stage }}</span>
                    </p>
                </li>
            </ul>
        </section>

        <section v-if="admitCardEvents?.length" id="section-admit" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Fest Admit Cards</h2>
            <ul class="text-sm divide-y">
                <li v-for="ev in admitCardEvents" :key="ev.id" class="py-2 flex justify-between gap-2">
                    <span>{{ ev.title }}</span>
                    <a :href="`/portal/teacher/${school.id}/fest/${ev.id}/admit-card`" target="_blank" class="text-xs font-semibold text-indigo-600">Download PDF ↗</a>
                </li>
            </ul>
        </section>

        <section v-if="festResults?.length" id="section-results" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Fest Results</h2>
            <ul class="text-sm divide-y">
                <li v-for="(r, i) in festResults" :key="i" class="py-2">
                    {{ r.event_title }} — {{ r.item_title }}
                    <span v-if="r.grade || r.position" class="text-xs text-indigo-700"> · {{ r.grade || r.position }}</span>
                </li>
            </ul>
        </section>

        <section v-if="festCerts?.length" id="section-certs" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Fest Certificates</h2>
            <ul class="text-sm divide-y">
                <li v-for="(c, i) in festCerts" :key="i" class="py-2 flex justify-between gap-2">
                    <span>{{ c.event?.title ?? 'Event' }} — {{ c.item?.title ?? '' }}</span>
                    <a v-if="c.uuid" :href="`/certificates/print/${c.uuid}`" target="_blank" class="text-xs font-semibold text-indigo-600">Print ↗</a>
                </li>
            </ul>
        </section>

        <section v-if="festFees?.length" id="section-fees" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">School Fest Fees</h2>
            <ul class="text-sm divide-y">
                <li v-for="(f, i) in festFees" :key="i" class="py-2 flex justify-between gap-2">
                    <span>{{ f.event_title }}</span>
                    <span class="text-xs capitalize">{{ f.status }} · ₹{{ f.total_due }}</span>
                </li>
            </ul>
        </section>

        <section v-if="festAppeals?.length || appealableParticipants?.length" id="section-appeals" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Appeals</h2>
            <ul v-if="festAppeals?.length" class="text-sm divide-y mb-3">
                <li v-for="(a, i) in festAppeals" :key="i" class="py-2">
                    <p class="font-medium">{{ a.event_title }} — {{ a.item_title }}</p>
                    <p class="text-xs capitalize">{{ a.status }}</p>
                </li>
            </ul>
            <form v-if="appealableParticipants?.length" @submit.prevent="submitAppeal" class="border-t pt-3 space-y-2">
                <select v-model="appealForm.participant_id" class="field text-sm" required>
                    <option value="">Select entry…</option>
                    <option v-for="p in appealableParticipants" :key="p.participant_id" :value="p.participant_id">{{ p.event_title }} — {{ p.item_title }}</option>
                </select>
                <textarea v-model="appealForm.reason" class="field text-sm" rows="2" required placeholder="Reason"></textarea>
                <button type="submit" class="btn-primary text-xs">Submit appeal</button>
            </form>
        </section>

        <section id="section-notifications" class="card">
            <h2 class="font-semibold text-sm mb-2">Notifications</h2>
            <ul class="text-sm divide-y">
                <li v-for="n in notifications" :key="n.id" class="py-2">
                    <p class="font-medium">{{ n.title }}</p>
                    <p v-if="n.body" class="text-xs text-gray-500 mt-0.5">{{ n.body }}</p>
                </li>
                <li v-if="!notifications.length" class="text-gray-400 py-2">No notifications</li>
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
    training: Array,
    festRegistrations: { type: Array, default: () => [] },
    festResults: { type: Array, default: () => [] },
    festDaySlots: { type: Array, default: () => [] },
    festCerts: { type: Array, default: () => [] },
    festAppeals: { type: Array, default: () => [] },
    festFees: { type: Array, default: () => [] },
    mcqBanks: { type: Array, default: () => [] },
    appealableParticipants: { type: Array, default: () => [] },
    admitCardEvents: { type: Array, default: () => [] },
    notifications: Array,
});

const appealForm = ref({ participant_id: '', reason: '' });

function submitAppeal() {
    const p = props.appealableParticipants.find(x => String(x.participant_id) === String(appealForm.value.participant_id));
    if (!p) return;
    router.post(`/portal/teacher/${props.school.id}/fest/${p.event_id}/appeals`, appealForm.value, {
        preserveScroll: true,
        onSuccess: () => { appealForm.value = { participant_id: '', reason: '' }; },
    });
}

const navItems = computed(() => teacherPortalNavItems(props.school.id));

const jumpSections = computed(() => {
    const sections = [];
    if (props.mcqBanks?.length) sections.push({ id: 'section-mcq', label: 'MCQ banks' });
    if (props.training?.length) sections.push({ id: 'section-training', label: 'Training' });
    if (props.festRegistrations?.length) sections.push({ id: 'section-fest-reg', label: 'Fest reg' });
    if (props.festDaySlots?.length) sections.push({ id: 'section-schedule', label: 'Schedule' });
    if (props.admitCardEvents?.length) sections.push({ id: 'section-admit', label: 'Admit cards' });
    if (props.festResults?.length) sections.push({ id: 'section-results', label: 'Results' });
    if (props.festCerts?.length) sections.push({ id: 'section-certs', label: 'Certificates' });
    if (props.festFees?.length) sections.push({ id: 'section-fees', label: 'Fees' });
    if (props.festAppeals?.length || props.appealableParticipants?.length) sections.push({ id: 'section-appeals', label: 'Appeals' });
    sections.push({ id: 'section-notifications', label: 'Notifications' });
    return sections;
});
</script>
