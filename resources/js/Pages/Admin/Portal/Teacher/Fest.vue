<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Teacher Fest"
        :subtitle="school.name"
        accent="indigo"
        :nav-items="navItems"
    >
        <section v-if="festRegistrations?.length" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Registrations</h2>
            <ul class="text-sm divide-y">
                <li v-for="r in festRegistrations" :key="r.id" class="py-2">
                    {{ r.event?.title }} — {{ r.item?.title }}
                    <span class="text-xs text-gray-400 capitalize">({{ r.status }})</span>
                </li>
            </ul>
        </section>

        <section v-if="festDaySlots?.length" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Schedule</h2>
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

        <section v-if="admitCardEvents?.length" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Admit cards</h2>
            <ul class="text-sm divide-y">
                <li v-for="ev in admitCardEvents" :key="ev.id" class="py-2 flex justify-between gap-2">
                    <span>{{ ev.title }}</span>
                    <a :href="`/portal/teacher/${school.id}/fest/${ev.id}/admit-card`" target="_blank" class="text-xs font-semibold text-indigo-600">Download PDF ↗</a>
                </li>
            </ul>
        </section>

        <section v-if="festResults?.length" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Results</h2>
            <ul class="text-sm divide-y">
                <li v-for="(r, i) in festResults" :key="i" class="py-2">
                    {{ r.event_title }} — {{ r.item_title }}
                    <span v-if="r.grade || r.position" class="text-xs text-indigo-700"> · {{ r.grade || r.position }}</span>
                </li>
            </ul>
        </section>

        <section v-if="festCerts?.length" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Certificates</h2>
            <ul class="text-sm divide-y">
                <li v-for="(c, i) in festCerts" :key="i" class="py-2 flex justify-between gap-2">
                    <span>{{ c.event?.title ?? 'Event' }} — {{ c.item?.title ?? '' }}</span>
                    <a v-if="c.uuid" :href="`/certificates/print/${c.uuid}`" target="_blank" class="text-xs font-semibold text-indigo-600">Print ↗</a>
                </li>
            </ul>
        </section>

        <section v-if="festFees?.length" class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">School fest fees</h2>
            <ul class="text-sm divide-y">
                <li v-for="(f, i) in festFees" :key="i" class="py-2 flex justify-between gap-2">
                    <span>{{ f.event_title }}</span>
                    <span class="text-xs capitalize">{{ f.status }} · ₹{{ f.total_due }}</span>
                </li>
            </ul>
        </section>

        <section v-if="festAppeals?.length || appealableParticipants?.length" class="card">
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

        <p v-if="!hasFestContent" class="text-sm text-gray-400 py-8 text-center">No teacher fest entries yet.</p>
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

const hasFestContent = computed(() =>
    props.festRegistrations?.length
    || props.festDaySlots?.length
    || props.festResults?.length
    || props.festCerts?.length
    || props.admitCardEvents?.length
    || props.festAppeals?.length
    || props.appealableParticipants?.length
);

function submitAppeal() {
    const p = props.appealableParticipants.find(x => String(x.participant_id) === String(appealForm.value.participant_id));
    if (!p) return;
    router.post(`/portal/teacher/${props.school.id}/fest/${p.event_id}/appeals`, appealForm.value, {
        preserveScroll: true,
        onSuccess: () => { appealForm.value = { participant_id: '', reason: '' }; },
    });
}
</script>
