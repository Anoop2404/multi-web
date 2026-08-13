<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Teacher Fest Hub"
        :subtitle="school.name"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="teacher?.photo_url"
        show-avatar-placeholder
    >
        <!-- Hero Banner -->
        <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-r from-[#041525] via-[#0f3d7a] to-[#d4a017] p-6 text-white shadow-md">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-white/10 flex items-center justify-center text-2xl shrink-0 backdrop-blur-sm border border-white/20">
                    🎭
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight">Cultural & Teacher Fest Dashboard</h1>
                    <p class="text-xs sm:text-sm text-white/80 mt-1">View your competition registrations, stage schedules, admit cards, and published results.</p>
                </div>
            </div>
        </div>

        <!-- Admit Cards Section -->
        <section v-if="admitCardEvents?.length" class="card rounded-2xl border border-blue-200/80 bg-gradient-to-r from-blue-50/50 to-indigo-50/50 p-5 shadow-sm">
            <div class="flex items-center gap-2 mb-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-white font-bold text-sm">🎫</span>
                <h2 class="section-title text-base font-bold text-slate-900">Fest Admit Cards Available</h2>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div v-for="ev in admitCardEvents" :key="ev.id" class="flex items-center justify-between gap-3 bg-white p-3.5 rounded-xl border border-blue-100 shadow-sm">
                    <span class="font-bold text-slate-900 text-sm truncate">{{ ev.title }}</span>
                    <a :href="`${base}/fest/${ev.id}/admit-card`" target="_blank"
                       class="inline-flex items-center gap-1.5 text-xs font-bold text-white bg-[#0f3d7a] hover:bg-[#041525] px-3 py-1.5 rounded-lg transition shadow-sm shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Download PDF
                    </a>
                </div>
            </div>
        </section>

        <!-- Registrations & Schedules -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Registrations -->
            <section v-if="festRegistrations?.length" class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
                <h2 class="section-title text-base font-bold text-slate-900 mb-3 flex items-center gap-2">
                    📋 Registered Fest Items
                </h2>
                <ul class="text-sm divide-y divide-slate-100">
                    <li v-for="r in festRegistrations" :key="r.id" class="py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-bold text-slate-900 truncate">{{ r.event?.title }}</p>
                            <p class="text-xs text-slate-500 mt-0.5 font-medium">{{ r.item?.title }}</p>
                        </div>
                        <span class="track-status-pill font-bold shrink-0" :class="pillClass(r.status)">{{ r.status }}</span>
                    </li>
                </ul>
            </section>

            <!-- Schedule -->
            <section v-if="festDaySlots?.length" class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
                <h2 class="section-title text-base font-bold text-slate-900 mb-3 flex items-center gap-2">
                    📍 Event Schedule & Chest Numbers
                </h2>
                <ul class="text-sm divide-y divide-slate-100">
                    <li v-for="(slot, i) in festDaySlots" :key="i" class="py-3">
                        <p class="font-bold text-slate-900">{{ slot.event_title }} — {{ slot.item_title }}</p>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 mt-1">
                            <span v-if="slot.level_reg" class="bg-slate-100 px-2 py-0.5 rounded font-mono text-[11px]">Reg: {{ slot.level_reg }}</span>
                            <span v-if="slot.chest_no" class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-bold text-[11px]">Chest #{{ slot.chest_no }}</span>
                            <span v-if="slot.stage" class="text-slate-600 font-medium">Stage: {{ slot.stage }}</span>
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <!-- Results & Certificates -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Results -->
            <section v-if="festResults?.length" class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
                <h2 class="section-title text-base font-bold text-slate-900 mb-3 flex items-center gap-2">
                    🏆 Results & Performance
                </h2>
                <ul class="text-sm divide-y divide-slate-100">
                    <li v-for="(r, i) in festResults" :key="i" class="py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-bold text-slate-900 truncate">{{ r.event_title }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ r.item_title }}</p>
                        </div>
                        <span v-if="r.grade || r.position" class="status-pill status-pill--published font-bold shrink-0">
                            {{ r.position ? `#${r.position}` : '' }} {{ r.grade ? `Grade ${r.grade}` : '' }}
                        </span>
                    </li>
                </ul>
            </section>

            <!-- Certificates -->
            <section v-if="festCerts?.length" class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
                <h2 class="section-title text-base font-bold text-slate-900 mb-3 flex items-center gap-2">
                    🏅 Fest Certificates
                </h2>
                <ul class="text-sm divide-y divide-slate-100">
                    <li v-for="(c, i) in festCerts" :key="i" class="py-3 flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-bold text-slate-900 truncate">{{ c.event?.title ?? 'Fest Event' }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ c.item?.title ?? '' }}</p>
                        </div>
                        <a v-if="c.uuid" :href="`/certificates/print/${c.uuid}`" target="_blank"
                           class="inline-flex items-center gap-1 text-xs font-bold text-[#0f3d7a] bg-[#eff6ff] hover:bg-[#dbeafe] px-3 py-1.5 rounded-lg transition shrink-0">
                            Print PDF
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                        </a>
                    </li>
                </ul>
            </section>
        </div>

        <!-- Appeals Section -->
        <section v-if="festAppeals?.length || appealableParticipants?.length" class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
            <h2 class="section-title text-base font-bold text-slate-900 mb-3 flex items-center gap-2">
                ⚖️ Fest Appeals & Discrepancies
            </h2>
            <ul v-if="festAppeals?.length" class="text-sm divide-y divide-slate-100 mb-4">
                <li v-for="(a, i) in festAppeals" :key="i" class="py-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="font-bold text-slate-900">{{ a.event_title }} — {{ a.item_title }}</p>
                        <p v-if="a.reason" class="text-xs text-slate-500 mt-0.5 line-clamp-1">Reason: {{ a.reason }}</p>
                    </div>
                    <span class="track-status-pill font-bold shrink-0" :class="pillClass(a.status)">{{ a.status }}</span>
                </li>
            </ul>

            <form v-if="appealableParticipants?.length" @submit.prevent="submitAppeal" class="border-t border-slate-100 pt-4 space-y-3">
                <p class="text-xs font-bold text-slate-800">Submit New Result Appeal</p>
                <FormField label="Select Entry" required>
                    <select v-model="appealForm.participant_id" class="field" required>
                        <option value="">Select entry…</option>
                        <option v-for="p in appealableParticipants" :key="p.participant_id" :value="p.participant_id">{{ p.event_title }} — {{ p.item_title }}</option>
                    </select>
                </FormField>
                <FormField label="Reason for Appeal" required>
                    <textarea v-model="appealForm.reason" class="field text-xs" rows="2" required placeholder="Explain the grounds for your result appeal"></textarea>
                </FormField>
                <button type="submit" class="btn-primary text-xs !min-h-0 !py-2 px-4 shadow-sm">Submit Appeal Request</button>
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
