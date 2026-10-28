<template>
    <SahodayaAdminLayout :title="`Paper — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam"
                    description="Build the paper from Ready question banks. Each bank is a section that takes all, the first N, or N random questions.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/paper-preview`" class="btn-secondary text-sm">Preview default paper</Link>
                <Link :href="banksUrl" class="btn-secondary text-sm">Question banks</Link>
            </template>
        </PageHeader>
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode || 'offline'"
                       :results-published="!!exam.results_published" :is-model-exam="!!exam.is_model_exam"
                       active="question-banks" :uses-engine="engine.enabled" />

        <div v-if="(exam.delivery_mode || 'offline') === 'offline'" class="card card--muted mb-4 !py-3 px-4 text-sm text-slate-700">
            This exam is <strong>offline</strong>. A paper is only used when delivery is switched to online on the Overview tab.
        </div>
        <div v-if="paper.locked" class="card mb-4 !border-amber-200 !bg-amber-50 !py-3 px-4 text-sm text-amber-900">
            Students have started this exam, so the paper is locked.
        </div>
        <div v-for="(warning, i) in paper.warnings" :key="i" class="card mb-3 !border-amber-200 !py-3 px-4 text-sm text-amber-900">
            {{ warning }}
        </div>

        <h3 class="mb-3 font-semibold">Default paper summary</h3>
        <!-- Totals -->
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-6">
            <div class="card card--muted !py-3 text-center">
                <p class="text-2xl font-bold">{{ paper.total }}</p>
                <p class="text-xs text-slate-500">Questions</p>
            </div>
            <div class="card card--muted !py-3 text-center">
                <p class="text-2xl font-bold">{{ paper.max_score }}</p>
                <p class="text-xs text-slate-500">Total marks</p>
            </div>
            <div v-for="(count, type) in paper.type_counts" :key="type" class="card card--muted !py-3 text-center">
                <p class="text-2xl font-bold">{{ count }}</p>
                <p class="text-xs text-slate-500">{{ typeLabels[type] ?? type }}</p>
            </div>
        </div>

        <!-- Sections -->
        <section class="card mb-6">
            <h3 class="section-title">Default paper sections</h3>
            <EmptyState v-if="!paper.sections.length" title="No sections yet" description="Add a Ready question bank below." icon="📚" class="py-6" />
            <div v-else class="divide-y divide-slate-100">
                <div v-for="(section, index) in paper.sections" :key="section.bank_id" class="flex flex-wrap items-start gap-4 py-4">
                    <div class="flex flex-col gap-1 pt-1">
                        <button type="button" class="btn-ghost !px-2 !py-0.5 text-xs" :disabled="paper.locked || index === 0" @click="moveSection(section, 'up')">↑</button>
                        <button type="button" class="btn-ghost !px-2 !py-0.5 text-xs" :disabled="paper.locked || index === paper.sections.length - 1" @click="moveSection(section, 'down')">↓</button>
                    </div>
                    <div class="min-w-[14rem] flex-1 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold text-slate-400">Section {{ index + 1 }}</span>
                            <Link :href="`${banksUrl}/${section.bank_id}`" class="font-semibold link-brand">{{ section.bank_title }}</Link>
                            <span class="text-xs text-slate-500">{{ section.subject }} · {{ section.bank_questions }} in bank</span>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_10rem_6rem]">
                            <input v-model="drafts[section.bank_id].section_title" class="field field--sm" placeholder="Section heading (optional)" :disabled="paper.locked">
                            <select v-model="drafts[section.bank_id].pick_mode" class="field field--sm" :disabled="paper.locked">
                                <option value="all">All questions</option>
                                <option value="first">First N</option>
                                <option value="random">Random N</option>
                            </select>
                            <input v-if="drafts[section.bank_id].pick_mode !== 'all'" v-model.number="drafts[section.bank_id].pick_count"
                                   type="number" min="1" :max="section.bank_questions" class="field field--sm" placeholder="N" :disabled="paper.locked">
                        </div>
                        <p class="text-xs text-slate-500">
                            Takes <strong>{{ section.picked }}</strong> question(s) · {{ section.marks }} marks
                            <span v-if="section.pick_mode === 'random'"> · the same random set for every student</span>
                        </p>
                    </div>
                    <div v-if="!paper.locked" class="flex gap-2 pt-1">
                        <button type="button" class="btn-secondary text-xs" :disabled="!isDirty(section)" @click="saveSection(section)">Save</button>
                        <button type="button" class="btn-ghost text-xs !text-red-600" @click="removeSection(section)">Remove</button>
                    </div>
                </div>
            </div>
        </section>

        <McqPaperTemplates :exam-url="examUrl" :templates="paperTemplates" :banks="templateBanks" :assignments="classPapers" :fallback="paperFallback" :classes="paperClasses" :locked="paper.locked" />

        <!-- Add bank -->
        <section v-if="!paper.locked" class="card mb-6">
            <h3 class="section-title">Add a section</h3>
            <EmptyState v-if="!available.length" title="No Ready banks to add"
                        description="Mark a question bank Ready (Question banks → open a bank → Mark Ready) to add it here." icon="📝" class="py-6" />
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="bank in available" :key="bank.id" class="flex items-center justify-between gap-3 py-3">
                    <div>
                        <p class="text-sm font-medium">{{ bank.title }}</p>
                        <p class="text-xs text-slate-500">
                            {{ bank.subject }} · {{ bank.questions_count }} question(s)
                            <span v-if="bank.owner_label"> · {{ bank.owner_label }}</span>
                        </p>
                    </div>
                    <button type="button" class="btn-secondary text-xs" @click="attach(bank)">Add</button>
                </li>
            </ul>
        </section>

        <!-- Online exam engine -->
        <section class="card mb-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="section-title !mb-1">Online exam engine</h3>
                    <p class="text-sm text-slate-500">
                        Runs the exam on the separate exam site: login with portal password or hall ticket + passcode, check-in and device test,
                        waiting room, offline-safe answers. Needed for multiple-correct and numeric questions and for large exams.
                    </p>
                </div>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="engineStateClass">{{ engineStateLabel }}</span>
            </div>

            <div v-if="!engine.configured" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
                The exam engine isn't connected on this server yet (EXAM_ENGINE_INTERNAL_URL / EXAM_ENGINE_INTERNAL_TOKEN). You can still prepare the settings.
            </div>
            <div v-if="$page.props.errors?.engine" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">
                <p v-for="(msg, i) in [].concat($page.props.errors.engine)" :key="i">{{ msg }}</p>
            </div>

            <form class="mt-4 space-y-4" @submit.prevent="saveEngine">
                <label class="flex items-start gap-2 text-sm font-medium">
                    <input v-model="engineForm.enabled" type="checkbox" class="mt-1" :disabled="!engine.online || engine.state === 'armed'">
                    <span>Use the online exam engine for this exam
                        <span v-if="!engine.online" class="block text-xs font-normal text-slate-500">Switch delivery to online on the Overview tab first.</span>
                    </span>
                </label>

                <div v-if="engineForm.enabled" class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="form-label">Students sign in with</label>
                        <select v-model="engineForm.entry_mode" class="field" :disabled="lockedAfterStart">
                            <option value="either">Portal password or exam passcode</option>
                            <option value="exam_passcode">Hall ticket + exam passcode only</option>
                            <option value="portal_password">Portal password only</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Where students write</label>
                        <select v-model="engineForm.supervision" class="field" :disabled="lockedAfterStart">
                            <option value="open">Anywhere (home or school)</option>
                            <option value="supervised">Supervised — invigilator gives a start PIN</option>
                        </select>
                    </div>
                    <div v-if="engineForm.supervision === 'supervised'">
                        <label class="form-label">Start PIN</label>
                        <div class="flex gap-2">
                            <input v-model="engineForm.start_pin" class="field font-mono" inputmode="numeric" maxlength="8" placeholder="4–8 digits">
                            <button type="button" class="btn-secondary text-xs" @click="engineForm.start_pin = String(Math.floor(1000 + Math.random() * 9000))">Generate</button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Timing</label>
                        <select v-model="engineForm.timing" class="field" :disabled="lockedAfterStart">
                            <option value="fixed">Everyone ends at the same time</option>
                            <option value="individual">Each student gets the full duration from their start</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Check-in opens (hours before)</label>
                        <input v-model.number="engineForm.checkin_hours" type="number" min="1" max="168" class="field" :disabled="lockedAfterStart">
                    </div>
                    <div>
                        <label class="form-label">Waiting room opens (minutes before)</label>
                        <input v-model.number="engineForm.waiting_room_minutes" type="number" min="10" max="240" class="field" :disabled="lockedAfterStart">
                    </div>
                    <div>
                        <label class="form-label">Late entry allowed (minutes)</label>
                        <input v-model.number="engineForm.late_entry_minutes" type="number" min="0" max="120" class="field">
                    </div>
                    <div>
                        <label class="form-label">Grace after the deadline (seconds)</label>
                        <input v-model.number="engineForm.grace_seconds" type="number" min="30" max="600" class="field" :disabled="lockedAfterStart">
                    </div>
                    <div>
                        <label class="form-label">New device allowed after idle (minutes)</label>
                        <input v-model.number="engineForm.device_idle_minutes" type="number" min="1" max="30" class="field" :disabled="lockedAfterStart">
                    </div>
                    <div>
                        <label class="form-label">School coordinators may add up to (minutes)</label>
                        <input v-model.number="engineForm.coordinator_extend_cap_minutes" type="number" min="0" max="60" class="field">
                    </div>
                    <label class="flex items-center gap-2 text-sm sm:col-span-2">
                        <input v-model="engineForm.show_score_on_submit" type="checkbox"> Show the score to students right after they submit
                    </label>
                </div>

                <div v-if="engine.force_message" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    {{ engine.force_message }}
                </div>
                <div v-if="engineForm.enabled && engine.capacity.window_start" class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    Capacity: {{ engine.capacity.expected.toLocaleString() }} candidates here + {{ engine.capacity.others_at_peak.toLocaleString() }}
                    from other exams at the same time, of {{ engine.capacity.limit.toLocaleString() }} platform capacity
                    ({{ formatDateTime(engine.capacity.window_start) }} – {{ formatDateTime(engine.capacity.window_end) }}).
                    <span v-if="engine.capacity.expected + engine.capacity.others_at_peak > engine.capacity.limit" class="font-semibold text-red-700">Over capacity — choose another time.</span>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="btn-primary text-sm" :disabled="engineForm.processing">Save engine settings</button>
                    <template v-if="engine.enabled">
                        <button v-if="engine.state !== 'armed' && engine.state !== 'closed'" type="button" class="btn-secondary text-sm" :disabled="!engine.configured || engineForm.isDirty" @click="armEngine">Arm now</button>
                        <button v-if="engine.state === 'armed' && !engine.started" type="button" class="btn-secondary text-sm" :disabled="!engine.configured || engineForm.isDirty" @click="armEngine">Re-send paper and roster</button>
                        <button v-if="engine.state === 'armed' && !engine.started" type="button" class="btn-ghost text-sm !text-red-600" @click="disarmEngine">Disarm</button>
                        <Link v-if="engine.state" :href="`${examUrl}/live`" class="link-brand text-sm">Live monitor</Link>
                        <a v-if="engine.state" :href="engine.exam_site_url" target="_blank" rel="noopener" class="link-brand text-sm">Open exam site ↗</a>
                    </template>
                </div>
                <p v-if="engine.enabled" class="text-xs text-slate-500">
                    Arming sends the paper (encrypted — answer keys stay here) and the candidate list to the exam engine.
                    It happens automatically when check-in opens; arm early to test. Students then use the exam site, or “Enter exam” in their portal.
                    <span v-if="engine.version"> Paper version {{ engine.version }}<span v-if="engine.armed_at">, armed {{ formatDateTime(engine.armed_at) }}</span>.</span>
                </p>
            </form>
        </section>

        <!-- Settings -->
        <section class="card">
            <h3 class="section-title">Paper settings</h3>
            <form class="space-y-3" @submit.prevent="saveSettings">
                <label class="flex items-start gap-2 text-sm">
                    <input v-model="settingsForm.shuffle_questions" type="checkbox" class="mt-1" :disabled="paper.locked">
                    <span>Shuffle question order for each student <span class="block text-xs text-slate-500">Each student keeps the same order if they reload.</span></span>
                </label>
                <label class="flex items-start gap-2 text-sm"><input v-model="settingsForm.shuffle_options" type="checkbox" class="mt-1" :disabled="paper.locked"><span>Shuffle answer options for each candidate<span class="block text-xs text-slate-500">Stable on reload. True/false options retain their order. Class templates have their own shuffle settings.</span></span></label>
                <div class="text-sm">
                    <p class="mb-1 font-medium">Marking multiple-correct questions</p>
                    <label class="mr-4 inline-flex items-center gap-2">
                        <input v-model="settingsForm.multi_marking" type="radio" value="all_or_nothing" :disabled="paper.locked"> All or nothing
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input v-model="settingsForm.multi_marking" type="radio" value="partial" :disabled="paper.locked"> Partial marks
                    </label>
                    <p class="mt-1 text-xs text-slate-500">
                        Partial: picking only correct options earns a share of the marks (2 of 3 correct → ⅔); any wrong option scores the minus mark.
                    </p>
                </div>
                <button v-if="!paper.locked" type="submit" class="btn-primary text-sm" :disabled="settingsForm.processing || !settingsForm.isDirty">Save settings</button>
            </form>
        </section>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import { formatDateTime } from '@/support/calendarDates.js';
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import McqPaperTemplates from '@/Components/mcq/McqPaperTemplates.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: { type: Object, required: true },
    paper: { type: Object, required: true },
    available: { type: Array, default: () => [] },
    settings: { type: Object, required: true },
    banksUrl: { type: String, required: true },
    engine: { type: Object, required: true },
    paperTemplates: { type: Array, default: () => [] },
    templateBanks: { type: Array, default: () => [] },
    classPapers: { type: Array, default: () => [] },
    paperClasses: { type: Array, default: () => [] },
    paperFallback: { type: String, default: 'require' },
});

const typeLabels = { single: 'Single correct', multiple: 'Multiple correct', true_false: 'True / False', numeric: 'Numeric' };
const examUrl = `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}`;
const { confirm } = useConfirm();

const drafts = reactive({});
watch(() => props.paper.sections, (sections) => {
    for (const s of sections) {
        drafts[s.bank_id] = { section_title: s.section_title ?? '', pick_mode: s.pick_mode, pick_count: s.pick_count };
    }
}, { immediate: true });

function isDirty(section) {
    const d = drafts[section.bank_id];
    return (d.section_title ?? '') !== (section.section_title ?? '')
        || d.pick_mode !== section.pick_mode
        || (d.pick_mode !== 'all' && Number(d.pick_count) !== Number(section.pick_count));
}

function saveSection(section) {
    router.put(`${examUrl}/question-banks/${section.bank_id}`, drafts[section.bank_id], { preserveScroll: true });
}

function moveSection(section, direction) {
    router.post(`${examUrl}/question-banks/${section.bank_id}/move`, { direction }, { preserveScroll: true });
}

async function removeSection(section) {
    if (!(await confirm({ title: 'Remove this section?', message: `"${section.bank_title}" will no longer be on the paper. The bank itself is kept.`, confirmLabel: 'Remove', destructive: true }))) return;
    router.delete(`${examUrl}/question-banks/${section.bank_id}`, { preserveScroll: true });
}

function attach(bank) {
    router.post(`${examUrl}/question-banks`, { bank_id: bank.id }, { preserveScroll: true });
}

const engineForm = useForm({ enabled: props.engine.enabled, ...props.engine.settings });
const lockedAfterStart = computed(() => props.engine.state === 'armed' && props.engine.started);
const engineStateLabel = computed(() => ({ armed: props.engine.started ? 'Live on the engine' : 'Armed', closed: 'Closed' }[props.engine.state] ?? (props.engine.enabled ? 'Not armed' : 'Off')));
const engineStateClass = computed(() => ({ armed: 'bg-emerald-100 text-emerald-800', closed: 'bg-slate-200 text-slate-600' }[props.engine.state] ?? 'bg-slate-100 text-slate-600'));

function saveEngine() {
    engineForm.put(`${examUrl}/engine`, { preserveScroll: true, onSuccess: () => engineForm.defaults() });
}

async function armEngine() {
    if (!(await confirm({ title: 'Arm on the exam engine?', message: 'The encrypted paper and the candidate list are sent to the exam engine. Students can check in once check-in opens.', confirmLabel: 'Arm' }))) return;
    router.post(`${examUrl}/engine/arm`, {}, { preserveScroll: true });
}

async function disarmEngine() {
    if (!(await confirm({ title: 'Disarm?', message: 'The exam is removed from the exam engine. Students who checked in will need to check in again after you re-arm.', confirmLabel: 'Disarm', destructive: true }))) return;
    router.post(`${examUrl}/engine/disarm`, {}, { preserveScroll: true });
}

const settingsForm = useForm({ ...props.settings });
function saveSettings() {
    settingsForm.put(`${examUrl}/paper-settings`, { preserveScroll: true, onSuccess: () => settingsForm.defaults() });
}
</script>
