<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Teacher Fest"
        :subtitle="school.name"
        accent="navy"
        :nav-items="navItems"
    >
        <section v-if="festRegistrations?.length" class="card mb-4">
            <h2 class="section-title text-base mb-3">Registrations</h2>
            <ul class="text-sm divide-y divide-slate-100">
                <li v-for="r in festRegistrations" :key="r.id" class="py-2.5 flex items-center justify-between gap-3">
                    <span class="min-w-0 truncate">{{ r.event?.title }} — {{ r.item?.title }}</span>
                    <span class="track-status-pill shrink-0" :class="pillClass(r.status)">{{ r.status }}</span>
                </li>
            </ul>
        </section>

        <section v-if="festDaySlots?.length" class="card mb-4">
            <h2 class="section-title text-base mb-3">Schedule</h2>
            <ul class="text-sm divide-y divide-slate-100">
                <li v-for="(slot, i) in festDaySlots" :key="i" class="py-2.5">
                    <p class="font-medium text-slate-900">{{ slot.event_title }} — {{ slot.item_title }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <span v-if="slot.level_reg">Reg: {{ slot.level_reg }}</span>
                        <span v-if="slot.chest_no"> · Chest #{{ slot.chest_no }}</span>
                        <span v-if="slot.stage"> · {{ slot.stage }}</span>
                    </p>
                </li>
            </ul>
        </section>

        <section v-if="admitCardEvents?.length" class="card mb-4">
            <h2 class="section-title text-base mb-3">Admit cards</h2>
            <ul class="text-sm divide-y divide-slate-100">
                <li v-for="ev in admitCardEvents" :key="ev.id" class="py-2.5 flex justify-between items-center gap-2">
                    <span class="min-w-0 truncate">{{ ev.title }}</span>
                    <a :href="`${base}/fest/${ev.id}/admit-card`" target="_blank" class="text-xs font-semibold text-[#0f3d7a] shrink-0">Download PDF ↗</a>
                </li>
            </ul>
        </section>

        <section v-if="festResults?.length" class="card mb-4">
            <h2 class="section-title text-base mb-3">Results</h2>
            <ul class="text-sm divide-y divide-slate-100">
                <li v-for="(r, i) in festResults" :key="i" class="py-2.5 flex items-center justify-between gap-3">
                    <span class="min-w-0 truncate">{{ r.event_title }} — {{ r.item_title }}</span>
                    <span v-if="r.grade || r.position" class="status-pill status-pill--published shrink-0">{{ r.grade || r.position }}</span>
                </li>
            </ul>
        </section>

        <section v-if="festCerts?.length" class="card mb-4">
            <h2 class="section-title text-base mb-3">Certificates</h2>
            <ul class="text-sm divide-y divide-slate-100">
                <li v-for="(c, i) in festCerts" :key="i" class="py-2.5 flex justify-between items-center gap-2">
                    <span class="min-w-0 truncate">{{ c.event?.title ?? 'Event' }} — {{ c.item?.title ?? '' }}</span>
                    <a v-if="c.uuid" :href="`/certificates/print/${c.uuid}`" target="_blank" class="text-xs font-semibold text-[#0f3d7a] shrink-0">Print ↗</a>
                </li>
            </ul>
        </section>

        <section v-if="festFees?.length" class="card mb-4">
            <h2 class="section-title text-base mb-3">School fest fees</h2>
            <ul class="text-sm divide-y divide-slate-100">
                <li v-for="(f, i) in festFees" :key="i" class="py-2.5 flex justify-between items-center gap-2">
                    <span class="min-w-0 truncate">{{ f.event_title }}</span>
                    <span class="text-xs shrink-0"><span class="track-status-pill" :class="pillClass(f.status)">{{ f.status }}</span> · ₹{{ f.total_due }}</span>
                </li>
            </ul>
        </section>

        <section v-if="festAppeals?.length || appealableParticipants?.length" class="card">
            <h2 class="section-title text-base mb-3">Appeals</h2>
            <ul v-if="festAppeals?.length" class="text-sm divide-y divide-slate-100 mb-4">
                <li v-for="(a, i) in festAppeals" :key="i" class="py-2.5 flex items-center justify-between gap-3">
                    <span class="font-medium min-w-0 truncate">{{ a.event_title }} — {{ a.item_title }}</span>
                    <span class="track-status-pill shrink-0" :class="pillClass(a.status)">{{ a.status }}</span>
                </li>
            </ul>
            <form v-if="appealableParticipants?.length" @submit.prevent="submitAppeal" class="border-t border-slate-100 pt-4 space-y-3">
                <FormField label="Entry" required>
                    <select v-model="appealForm.participant_id" class="field" required>
                        <option value="">Select entry…</option>
                        <option v-for="p in appealableParticipants" :key="p.participant_id" :value="p.participant_id">{{ p.event_title }} — {{ p.item_title }}</option>
                    </select>
                </FormField>
                <FormField label="Reason" required>
                    <textarea v-model="appealForm.reason" class="field" rows="2" required placeholder="Explain the issue with this result"></textarea>
                </FormField>
                <button type="submit" class="btn-primary text-xs !min-h-0 !py-2">Submit appeal</button>
            </form>
        </section>

        <EmptyState v-if="!hasFestContent" title="No teacher fest entries yet" description="Once your school registers you for fest items, they'll show up here." icon="🎭" />
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import FormField from '@/Components/ui/FormField.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { teacherPortalNavItems } from '@/support/teacherPortalNav.js';

const props = defineProps({
    school: Object,
    teacher: Object,
    festRegistrations: { type: Array, default: () => [] },
    festDaySlots: { type: Array, default: () => [] },
    festResults: { type: Array, default: () => [] },
    festCerts: { type: Array, default: () => [] },
    admitCardEvents: { type: Array, default: () => [] },
    festAppeals: { type: Array, default: () => [] },
    festFees: { type: Array, default: () => [] },
    appealableParticipants: { type: Array, default: () => [] },
});

const appealForm = ref({ participant_id: '', reason: '' });

const navItems = computed(() => teacherPortalNavItems(props.school.id));
const base = computed(() => `/portal/teacher/${props.school.id}`);

const hasFestContent = computed(() =>
    props.festRegistrations?.length
    || props.festDaySlots?.length
    || props.festResults?.length
    || props.festCerts?.length
    || props.admitCardEvents?.length
    || props.festAppeals?.length
    || props.appealableParticipants?.length
);

function pillClass(status) {
    const key = String(status ?? '').toLowerCase();
    if (['approved', 'confirmed', 'paid', 'accepted'].includes(key)) return 'track-status-pill--approved';
    if (['rejected', 'declined', 'withdrawn'].includes(key)) return 'track-status-pill--rejected';
    if (['submitted', 'pending', 'review', 'under_review'].includes(key)) return 'track-status-pill--submitted';
    return 'track-status-pill--pending';
}

function submitAppeal() {
    const p = props.appealableParticipants.find(x => String(x.participant_id) === String(appealForm.value.participant_id));
    if (!p) return;
    router.post(`${base.value}/fest/${p.event_id}/appeals`, appealForm.value, {
        preserveScroll: true,
        onSuccess: () => { appealForm.value = { participant_id: '', reason: '' }; },
    });
}
</script>
