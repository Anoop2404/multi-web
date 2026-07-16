<template>
    <SchoolAdminLayout :title="event.title" :school="school" :show-header-title="false">
        <PageHeader :title="event.title" :eyebrow="programMeta.label"
                    :description="`Your school's workspace for this Sahodaya ${programMeta.label} event.`">
            <template #actions>
                <Link :href="eventRegistrationHref" class="btn-primary text-sm">Register students →</Link>
            </template>
        </PageHeader>

        <!-- Dates & venue strip -->
        <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-600 bg-slate-50 border border-slate-200/60 rounded-xl px-4 py-2.5 mb-5">
            <div class="flex items-center gap-1.5">
                <span class="text-base" aria-hidden="true">📅</span>
                <span><strong>Event dates:</strong> {{ formatDateRange(event.event_start, event.event_end) }}</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="text-base" aria-hidden="true">📝</span>
                <span><strong>Registration:</strong> {{ formatDateRange(event.registration_open, event.registration_close) }}</span>
            </div>
            <div v-if="event.venue" class="flex items-center gap-1.5">
                <span class="text-base" aria-hidden="true">📍</span>
                <span><strong>Venue:</strong> {{ event.venue }}</span>
            </div>
        </div>

        <SchoolEventWorkflowStepper :school-id="school.id"
                                    :program-prefix="programPrefix"
                                    :event-id="event.id"
                                    :is-sports="isSports"
                                    current-step="overview" />

        <!-- Registration window banner -->
        <div v-if="isRegClosed" class="notice-banner notice-banner--warning text-sm mb-5">
            <p class="font-semibold">Registration is Closed</p>
            <p class="mt-0.5">The registration deadline for this event was {{ formatDate(event.registration_close) }}. You can still view details but cannot modify entries.</p>
        </div>
        <div v-else-if="!isRegOpenYet" class="notice-banner notice-banner--info text-sm mb-5">
            <p class="font-semibold">Registration Not Open Yet</p>
            <p class="mt-0.5">Registration will open on {{ formatDate(event.registration_open) }}.</p>
        </div>
        <div v-else class="notice-banner notice-banner--success text-sm mb-5">
            <p class="font-semibold">Registration is Open</p>
            <p class="mt-0.5">You can register students and submit entries until {{ formatDate(event.registration_close) }}.</p>
        </div>

        <div v-if="schoolRegion?.applies" class="mb-5">
            <div v-if="schoolRegion.region" class="notice-banner notice-banner--info text-sm">
                <p>Kalotsav region: <strong>{{ schoolRegion.region }}</strong>.
                    <a :href="schoolRegion.set_url" class="link-brand font-semibold">Change →</a>
                </p>
            </div>
            <div v-else class="notice-banner notice-banner--warning text-sm">
                <p class="font-semibold">Select your Kalotsav region</p>
                <p class="mt-1">Your Sahodaya runs Kalotsav by region. Choose it in
                    <a :href="schoolRegion.set_url" class="link-brand font-semibold">annual registration →</a>
                    before registering students.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold text-emerald-700">{{ stats.registrations }}</p>
                <p class="text-xs text-slate-500 mt-1">Registrations</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold text-indigo-700">{{ stats.items_enabled }}</p>
                <p class="text-xs text-slate-500 mt-1">Open items</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold text-amber-700">₹{{ formatAmount(stats.fees_due) }}</p>
                <p class="text-xs text-slate-500 mt-1">Fee due</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-sm font-bold capitalize text-slate-800">{{ stats.fee_status?.replace(/_/g, ' ') ?? '—' }}</p>
                <p class="text-xs text-slate-500 mt-1">Payment status</p>
            </div>
        </div>

        <!-- Billing / Fee Payment Callout -->
        <div v-if="stats.fees_due > 0 && stats.fee_status !== 'approved'" 
             class="notice-banner mb-6 text-sm flex flex-wrap items-center justify-between gap-4"
             :class="stats.fee_status === 'proof_uploaded' ? 'notice-banner--info' : 'notice-banner--warning'">
            <div>
                <p class="font-semibold">{{ stats.fee_status === 'proof_uploaded' ? 'Payment receipt pending approval' : 'Action required: Fee payment due' }}</p>
                <p class="mt-0.5">₹{{ formatAmount(stats.fees_due) }} is outstanding for this event.</p>
            </div>
            <Link :href="eventRegistrationHref" class="btn-primary text-xs !min-h-0">Go to Billing & Pay →</Link>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-8">
            <HubCard :href="eventRegistrationHref" icon="📝"
                     :label="isSports ? 'Step 1 · Register students' : 'Register students'"
                     :hint="isSports ? 'Add athletes to this event & pay fees' : 'Add participants & pay fees'" />
            <HubCard v-if="isSports" :href="itemRegistrationHref" icon="🏃" label="Step 2 · Register by Sport Event"
                     hint="Pick a sport event (Athletics, Chess…) and add athletes to its items" />
            <HubCard :href="reportsHref" icon="📋" label="Reports & ID cards" hint="Admit cards, ID cards, exports" />
            <HubCard :href="clashHref" icon="⚠️" label="Clash requests" hint="Report schedule conflicts" />
            <HubCard :href="substitutionHref" icon="🔄" label="Substitutions" hint="Request participant swaps" />
            <HubCard :href="festDayHref" icon="📅" label="Fest day view" hint="Day-of-event snapshot" />
        </div>

        <section v-if="eventHeadNav?.headItemGroups?.length" class="card space-y-4">
            <div>
                <h3 class="section-title text-base">{{ isSports ? 'Sport Events' : 'Item heads' }}</h3>
                <p class="text-sm text-slate-500 mt-1">
                    {{ isSports
                        ? 'Jump straight into registering students for a specific sport event, or view its reports.'
                        : 'Jump to registration or reports for a section.' }}
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div v-for="head in eventHeadNav.headItemGroups" :key="head.head_id ?? 'other'"
                     class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                    <p class="font-semibold text-slate-900">{{ head.head_name }}</p>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ head.item_count }} items · {{ head.participant_count }} registered
                    </p>
                    <div v-if="head.items?.length" class="mt-3 space-y-2.5 border-t border-slate-200/80 pt-3">
                        <div v-for="item in head.items" :key="item.id" class="text-xs flex flex-col gap-0.5">
                            <span class="font-semibold text-slate-900">{{ item.title }}</span>
                            <div class="text-[10px] text-slate-500 flex flex-wrap items-center gap-x-1.5 gap-y-0.5">
                                <span class="text-indigo-700 font-semibold">{{ item.participant_count }} registered</span>
                                <span>·</span>
                                <span class="capitalize">{{ item.participant_type }}</span>
                                <span>·</span>
                                <span class="uppercase font-mono">{{ item.age_group || 'open' }}</span>
                                <span v-if="item.gender && item.gender !== 'open'">·</span>
                                <span v-if="item.gender && item.gender !== 'open'" class="capitalize">{{ item.gender }}</span>
                                <span v-if="item.squad_summary">·</span>
                                <span v-if="item.squad_summary" class="text-slate-400">{{ item.squad_summary }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-4 border-t border-slate-100 pt-3">
                        <Link v-if="isSports" :href="headLink(head.head_id, 'items')" class="btn-primary text-xs !min-h-0">Register students →</Link>
                        <Link v-else :href="headLink(head.head_id, 'registration')" class="btn-secondary text-xs">Register</Link>
                        <Link :href="headLink(head.head_id, 'reports')" class="btn-secondary text-xs">Reports</Link>
                    </div>
                </div>
            </div>
        </section>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import HubCard from '@/Components/ui/HubCard.vue';
import SchoolEventWorkflowStepper from '@/Components/school/SchoolEventWorkflowStepper.vue';
import { headQueryParam, schoolEventBase } from '@/support/eventHeadNav.js';

const props = defineProps({
    school: Object,
    event: Object,
    program: String,
    programMeta: Object,
    programPrefix: { type: String, default: 'sports' },
    eventHeadNav: { type: Object, default: () => ({ headItemGroups: [] }) },
    stats: { type: Object, default: () => ({}) },
    schoolRegion: { type: Object, default: null },
});

const isSports = computed(() => props.event?.event_type === 'sports' || props.program === 'sports-meet');
const eventBase = computed(() => schoolEventBase(props.school.id, props.programPrefix, props.event.id));
const eventRegistrationHref = computed(() => `${eventBase.value}/registration`);
const itemRegistrationHref = computed(() => `${eventBase.value}/items`);
const reportsHref = computed(() => `/school-admin/${props.school.id}/${props.programPrefix}/reports/${props.event.id}`);
const clashHref = computed(() => `${eventBase.value}/clash-requests`);
const substitutionHref = computed(() => `${eventBase.value}/substitution-requests`);
const festDayHref = computed(() => `/school-admin/${props.school.id}/${props.programPrefix}/fest-day/${props.event.id}`);

function formatAmount(value) {
    const n = Number(value);
    return Number.isFinite(n) ? n.toLocaleString('en-IN') : '0';
}

function headLink(headId, action) {
    const q = headQueryParam(headId);
    if (action === 'registration') {
        return `${eventBase.value}/registration${q}`;
    }
    if (action === 'items') {
        return `${eventBase.value}/items${q}`;
    }

    return `${reportsHref.value}${q}`;
}

const isRegClosed = computed(() => {
    if (!props.event.registration_close) return false;
    return new Date() > new Date(`${props.event.registration_close}T23:59:59`);
});

const isRegOpenYet = computed(() => {
    if (!props.event.registration_open) return true;
    return new Date() >= new Date(`${props.event.registration_open}T00:00:00`);
});

function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(`${iso}T12:00:00`);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatDateRange(start, end) {
    const startOk = start && !Number.isNaN(new Date(`${start}T12:00:00`).getTime());
    const endOk = end && !Number.isNaN(new Date(`${end}T12:00:00`).getTime());
    if (!startOk && !endOk) return 'Not scheduled';
    if (startOk && endOk) {
        if (start === end) return formatDate(start);
        return `${formatDate(start)} – ${formatDate(end)}`;
    }
    return startOk ? `From ${formatDate(start)}` : `Until ${formatDate(end)}`;
}
</script>
