<template>
    <SahodayaEventsLayout :title="`${event.title} — Setup`" :sahodaya="sahodaya" :event="event"
                          :show-header-title="false">
        
        <!-- Page Header with Action -->
        <PageHeader :title="`${event.title} — Sports setup`" eyebrow="Sports Setup"
                    :description="isSeason
                        ? 'Configure this sports season: age groups, master catalog, then manage each sport event.'
                        : 'Configure this sport event: items, fees, registration windows — then open registration.'">
            <template #actions>
                <div class="flex items-center gap-2">
                    <Link v-if="sportsHubUrl" :href="sportsHubUrl" class="btn-secondary text-xs">
                        All sports hub ↗
                    </Link>
                    <Link :href="competitionUrl" class="btn-primary text-xs flex items-center gap-1.5">
                        <span>{{ isSeason ? 'Open sport events →' : 'Items →' }}</span>
                    </Link>
                </div>
            </template>
        </PageHeader>

        <SportsSetupSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" :event="event" active="setup" />

        <!-- Hero Progress Banner -->
        <div class="card mb-6 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 text-white !p-5 shadow-lg">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-xl font-bold text-white backdrop-blur">
                        ⚙️
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-white leading-snug">{{ event.title }}</h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider border border-white/20 bg-white/10 text-white">
                                {{ isSeason ? 'Sports Season' : 'Sport Event' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-300 mt-0.5">
                            {{ isSeason ? 'Configure age groups & catalog once, then manage each sport event below.' : 'Standalone sport event configuration' }}
                        </p>
                    </div>
                </div>

                <button v-if="canAddSport" type="button" class="btn-primary text-xs !bg-indigo-600 hover:!bg-indigo-500 !text-white !border-transparent flex items-center gap-1"
                        @click="showAddSport = !showAddSport">
                    <span>{{ showAddSport ? 'Close form' : '+ Add sport event' }}</span>
                </button>
            </div>

            <!-- Progress Bar inside Hero -->
            <div class="space-y-1.5 pt-2 border-t border-white/10">
                <div class="flex items-center justify-between text-xs text-slate-300">
                    <span class="font-semibold text-white">Setup Progress</span>
                    <span class="font-mono font-bold text-white">{{ checklistProgress.done }}/{{ checklistProgress.total }} Complete ({{ progressPct }}%)</span>
                </div>
                <div class="h-2 w-full rounded-full bg-white/10 overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-indigo-400 to-emerald-400 rounded-full transition-all duration-300"
                         :style="{ width: `${progressPct}%` }"></div>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left Panel: Checklist & Sport Events -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Section 1: Event Setup Checklist -->
                <section class="card space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="section-title !mb-0">Setup Checklist</h3>
                            <p class="section-desc mt-0.5">Step-by-step setup tasks for this sports meet.</p>
                        </div>
                        <button type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                                @click="showAllChecklist = !showAllChecklist">
                            {{ showAllChecklist ? 'Show pending only' : `Show all (${checklist.length})` }}
                        </button>
                    </div>

                    <ul class="divide-y divide-slate-100 text-xs">
                        <li v-for="step in visibleChecklist" :key="step.key"
                            class="py-3 flex items-center justify-between gap-3 group transition">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                      :class="step.done ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                    {{ step.done ? '✓' : '○' }}
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900 group-hover:text-indigo-700 transition"
                                       :class="{ 'line-through text-slate-400': step.done }">
                                        {{ step.label }}
                                        <span v-if="step.optional" class="ml-1 text-[9px] font-normal uppercase tracking-wide text-slate-400 no-underline">(Optional)</span>
                                    </p>
                                    <p class="text-[11px] text-slate-500 truncate mt-0.5">{{ step.hint }}</p>
                                </div>
                            </div>
                            <Link :href="step.href" class="btn-secondary text-[11px] !py-1 !px-2.5 shrink-0 group-hover:border-indigo-300">
                                Open →
                            </Link>
                        </li>
                    </ul>
                </section>

                <!-- Section 2: Sport Events Directory -->
                <section v-if="headItemGroups.length || canAddSport" class="card space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="section-title !mb-0">Sport Events</h3>
                            <p class="section-desc mt-0.5">
                                {{ isSeason
                                    ? 'Each sport is its own event — set fees, items, registration, marks, and results there.'
                                    : 'Sport events under this meet container.' }}
                            </p>
                        </div>
                        <button v-if="canAddSport" type="button" class="btn-primary text-xs"
                                @click="showAddSport = !showAddSport">
                            + Add sport
                        </button>
                    </div>

                    <!-- Add Sport Inline Form Card -->
                    <form v-if="showAddSport && canAddSport" class="rounded-xl border border-indigo-200 bg-gradient-to-b from-indigo-50/60 to-white p-4 space-y-3 shadow-sm"
                          @submit.prevent="submitAddSport">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-900">Create New Sport Event</h4>
                            <button type="button" class="text-slate-400 hover:text-slate-600 text-lg leading-none" @click="showAddSport = false">×</button>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-xs" for="add-sport-name">Sport name</label>
                                <input id="add-sport-name" v-model="addSportForm.name" type="text" required
                                       class="field text-sm" placeholder="e.g. Athletics, Chess, Kabaddi" />
                                <p v-if="addSportForm.errors.name" class="text-xs text-rose-600 mt-1">{{ addSportForm.errors.name }}</p>
                            </div>
                            <div>
                                <label class="form-label text-xs" for="add-sport-discipline">Discipline (optional)</label>
                                <input id="add-sport-discipline" v-model="addSportForm.sport_discipline" type="text"
                                       class="field text-sm" placeholder="e.g. athletics, racket" />
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-xs text-slate-700 font-medium">
                            <input v-model="addSportForm.is_team_heading" type="checkbox" class="rounded border-slate-300" />
                            Has team items
                        </label>
                        <div class="flex justify-end gap-2 pt-1">
                            <button type="button" class="btn-secondary text-xs" @click="showAddSport = false">Cancel</button>
                            <button type="submit" class="btn-primary text-xs" :disabled="addSportForm.processing">
                                {{ addSportForm.processing ? 'Creating...' : 'Create sport event' }}
                            </button>
                        </div>
                    </form>

                    <!-- Sport Events Card Grid -->
                    <div class="grid sm:grid-cols-2 gap-3 pt-1">
                        <div v-for="head in headItemGroups" :key="head.head_id ?? 'other'"
                             class="card !p-4 hover:border-indigo-300 hover:shadow-md transition group bg-white border border-slate-200/80 flex flex-col justify-between">
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <h4 class="font-bold text-slate-900 text-sm group-hover:text-indigo-600 transition leading-snug">{{ head.head_name }}</h4>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider border shrink-0"
                                          :class="head.fees_configured ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800'">
                                        {{ head.fees_configured ? 'Fees Set' : 'Fee Pending' }}
                                    </span>
                                </div>

                                <!-- Schedule -->
                                <div class="mt-2.5 space-y-1 text-[11px] text-slate-500 bg-slate-50 p-2 rounded-lg border border-slate-100">
                                    <p v-if="head.registration_open || head.registration_close" class="flex items-center gap-1">
                                        <span>📝 Reg:</span>
                                        <span>{{ formatDateRange(head.registration_open, head.registration_close) }}</span>
                                    </p>
                                    <p v-if="head.event_start || head.event_end" class="flex items-center gap-1">
                                        <span>🏆 Comp:</span>
                                        <span>{{ formatDateRange(head.event_start, head.event_end) }}</span>
                                    </p>
                                </div>

                                <!-- Stats Pills -->
                                <div class="mt-3 flex flex-wrap gap-1.5 text-[10px] font-semibold text-slate-600">
                                    <span class="bg-slate-100 px-2 py-0.5 rounded-full border border-slate-200">{{ head.item_count }} items</span>
                                    <span class="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full border border-indigo-100">{{ head.schools_count ?? 0 }} schools</span>
                                    <span class="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full border border-emerald-100">{{ head.athletes_count ?? 0 }} athletes</span>
                                </div>
                            </div>

                            <div class="mt-4 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs font-bold">
                                <Link :href="`${sportEventUrl(head)}/items`" class="text-slate-600 hover:text-indigo-600">
                                    Items →
                                </Link>
                                <Link :href="sportEventUrl(head)" class="btn-primary text-xs !min-h-0 !py-1 !px-2.5">
                                    Open event →
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Right Sidebar: Single Clean Season & Masters Card -->
            <aside class="space-y-5">
                <section class="card space-y-4">
                    <h4 class="section-title !mb-0 text-xs font-bold uppercase tracking-wider text-slate-500">Season Summary &amp; Masters</h4>
                    
                    <dl class="grid grid-cols-2 gap-2 text-center bg-slate-50 p-3 rounded-xl border border-slate-100 text-xs">
                        <div class="p-1">
                            <dt class="text-slate-500 font-medium text-[11px]">Sport Events</dt>
                            <dd class="text-lg font-black text-slate-900 mt-0.5">{{ stats.heads ?? 0 }}</dd>
                        </div>
                        <div class="p-1 border-l border-slate-200">
                            <dt class="text-slate-500 font-medium text-[11px]">Enabled Items</dt>
                            <dd class="text-lg font-black text-indigo-600 mt-0.5">{{ stats.items ?? 0 }}</dd>
                        </div>
                    </dl>

                    <div class="space-y-2 pt-1 border-t border-slate-100">
                        <p class="text-xs font-bold text-slate-700 uppercase tracking-wider text-[10px]">Sahodaya Master Configurations</p>
                        <div class="divide-y divide-slate-100 text-xs">
                            <li v-for="master in tenantMasters" :key="master.href" class="list-none py-2">
                                <Link :href="master.href" class="group flex items-center justify-between">
                                    <div>
                                        <p class="font-bold text-slate-800 group-hover:text-indigo-600 transition">{{ master.label }}</p>
                                        <p class="text-[11px] text-slate-500 mt-0.5">{{ master.hint }}</p>
                                    </div>
                                    <span class="text-slate-400 group-hover:text-indigo-600 text-xs">→</span>
                                </Link>
                            </li>
                        </div>
                    </div>

                    <div v-if="ageRuleSummary" class="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-1.5 text-xs">
                        <p class="font-bold text-slate-800">Age Rules</p>
                        <p class="text-[11px] text-slate-600 leading-relaxed">{{ ageRuleSummary }}</p>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/settings/eligibility`"
                              class="inline-block text-[11px] font-bold text-indigo-600 hover:underline pt-1">
                            Age cutoff settings →
                        </Link>
                    </div>
                </section>
            </aside>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    checklist: { type: Array, default: () => [] },
    checklistProgress: { type: Object, default: () => ({ done: 0, total: 0 }) },
    tenantMasters: { type: Array, default: () => [] },
    headItemGroups: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    ageRuleSummary: { type: String, default: null },
    competitionUrl: { type: String, default: null },
    sportsHubUrl: { type: String, default: null },
    isSeason: { type: Boolean, default: false },
    canAddSport: { type: Boolean, default: false },
    addSportUrl: { type: String, default: null },
});

const showAllChecklist = ref(false);
const showAddSport = ref(false);

const addSportForm = useForm({
    name: '',
    sport_discipline: '',
    is_team_heading: true,
});

function submitAddSport() {
    if (!props.addSportUrl) return;
    addSportForm.post(props.addSportUrl, {
        preserveScroll: true,
        onSuccess: () => {
            addSportForm.reset();
            showAddSport.value = false;
        },
    });
}

const progressPct = computed(() => {
    const { done, total } = props.checklistProgress;
    if (!total) return 0;
    return Math.round((done / total) * 100);
});

const visibleChecklist = computed(() => {
    if (showAllChecklist.value) return props.checklist;
    const pending = props.checklist.filter((s) => !s.done);
    return pending.length > 0 ? pending : props.checklist;
});

function sportEventUrl(head) {
    if (head.href) return head.href;
    return `/sahodaya-admin/${props.sahodaya.id}/events/${head.head_id}`;
}

function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatDateRange(start, end) {
    if (!start && !end) return 'Not scheduled';
    if (start && end) {
        if (start === end) return formatDate(start);
        return `${formatDate(start)} – ${formatDate(end)}`;
    }
    return start ? `From ${formatDate(start)}` : `Until ${formatDate(end)}`;
}
</script>
