<template>
    <SahodayaEventsLayout :title="`${event.title} — State Nomination`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — State Nomination`" eyebrow="Maker / checker committee workflow"
                    description="Select primary + reserve nominees per item from the certified result pool. A different user must certify the batch before it's used for State submission." />

        <div class="mb-4">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/levels`" class="link-brand text-sm">&larr; Back to Rounds & Levels</Link>
        </div>

        <div class="mb-6 p-4 rounded-xl border" :class="statusBoxClass">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h4 class="text-sm font-bold" :class="statusTextClass">
                        Batch status: {{ batch.status }}
                    </h4>
                    <p class="text-xs mt-1" :class="statusTextClass">
                        <span v-if="batch.status === 'certified'">
                            Certified{{ batch.certified_at ? ` on ${formatDate(batch.certified_at)}` : '' }}.
                            FestStateQualifierPayloadBuilder will use this batch, not raw marks, the next time qualifiers are submitted.
                        </span>
                        <span v-else-if="selections.length === 0">
                            No nominees selected yet — pick candidates from the pool below.
                        </span>
                        <span v-else>
                            {{ primaryCount }} primary, {{ reserveCount }} reserve selected. Certify below once ready
                            (requires a different logged-in user than whoever made the selections).
                        </span>
                    </p>
                </div>
                <form v-if="batch.status !== 'certified'" @submit.prevent="certify" class="flex items-center gap-2">
                    <button type="submit" class="btn-primary text-sm" :disabled="certifyForm.processing || primaryCount === 0">
                        Certify batch
                    </button>
                </form>
            </div>
            <p v-if="certifyForm.errors.certify" class="text-xs text-red-600 mt-2">{{ certifyForm.errors.certify }}</p>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <div class="card space-y-3">
                <h4 class="section-title">Certified selections ({{ selections.length }})</h4>
                <div v-if="selections.length === 0" class="text-sm text-slate-400">Nothing selected yet.</div>
                <ul v-else class="divide-y divide-slate-100 text-sm">
                    <li v-for="sel in selections" :key="sel.id" class="py-2.5 flex items-start justify-between gap-2">
                        <div>
                            <div class="font-semibold text-slate-700">
                                {{ sel.student_name }}
                                <span class="ml-1.5 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold"
                                      :class="sel.nomination_type === 'primary' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'">
                                    {{ sel.nomination_type }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-500">
                                {{ sel.item_title }} <span v-if="sel.item_code" class="font-mono">({{ sel.item_code }})</span>
                                — {{ sel.school_name || sel.school_id }}
                            </div>
                            <div v-if="sel.skip_reason" class="text-[11px] text-amber-700 mt-0.5">Skip note: {{ sel.skip_reason }}</div>
                        </div>
                        <button v-if="batch.status !== 'certified'" type="button" class="text-xs text-red-600 shrink-0" @click="unselect(sel)">
                            Withdraw
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card space-y-3">
                <h4 class="section-title">Candidate pool ({{ candidates.length }})</h4>
                <p class="section-desc">Certified results not yet selected. Pick primary (goes to State) or reserve (used only if the primary withdraws).</p>
                <div v-if="candidates.length === 0" class="text-sm text-slate-400">
                    No remaining eligible candidates — either everything is selected, or results aren't published/certified for this event yet.
                </div>
                <ul v-else class="divide-y divide-slate-100 text-sm max-h-[32rem] overflow-y-auto">
                    <li v-for="c in candidates" :key="c.mark_id" class="py-2.5 space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <span class="font-semibold text-slate-700">{{ c.student_name }}</span>
                                <span class="ml-2 text-xs text-slate-500">{{ c.item_title }} <span v-if="c.item_code" class="font-mono">({{ c.item_code }})</span></span>
                            </div>
                            <span class="text-xs text-slate-400 shrink-0">Pos {{ c.source_position }} · {{ c.grade || '—' }}</span>
                        </div>
                        <div v-if="batch.status !== 'certified'" class="flex items-center gap-2">
                            <button type="button" class="btn-secondary text-xs !py-1" :disabled="selectForm.processing" @click="select(c, 'primary')">
                                Select primary
                            </button>
                            <button type="button" class="btn-ghost text-xs !py-1" :disabled="selectForm.processing" @click="select(c, 'reserve')">
                                Select reserve
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    batch: Object,
    candidates: { type: Array, default: () => [] },
    selections: { type: Array, default: () => [] },
    currentUserId: [String, Number],
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;

const { confirm, prompt } = useConfirm();

const selectForm = useForm({});
const certifyForm = useForm({ certification_notes: '' });

const primaryCount = computed(() => props.selections.filter((s) => s.nomination_type === 'primary').length);
const reserveCount = computed(() => props.selections.filter((s) => s.nomination_type === 'reserve').length);

const statusBoxClass = computed(() =>
    props.batch.status === 'certified' ? 'border-emerald-200 bg-emerald-50/70' : 'border-amber-200 bg-amber-50/70'
);
const statusTextClass = computed(() => (props.batch.status === 'certified' ? 'text-emerald-900' : 'text-amber-900'));

function formatDate(value) {
    if (!value) return '';
    return new Date(value).toLocaleString();
}

async function select(candidate, nominationType) {
    if (nominationType === 'reserve') {
        const skip_reason = (await prompt({ message: 'Optional: why is this a reserve rather than primary? (leave blank to skip)', inputMultiline: true, inputRequired: false })) || null;
        selectForm.transform(() => ({ ...candidate, nomination_type: nominationType, skip_reason }))
            .post(`${base}/state-nomination/select`, { preserveScroll: true });
        return;
    }
    if (!(await confirm({ message: `Select ${candidate.student_name} as the primary nominee for ${candidate.item_title}? This locks in their state-round nomination and can't be trivially undone once the batch is certified.`, destructive: true }))) return;
    selectForm.transform(() => ({ ...candidate, nomination_type: nominationType }))
        .post(`${base}/state-nomination/select`, { preserveScroll: true });
}

async function unselect(selection) {
    if (!(await confirm({ message: `Withdraw ${selection.student_name} from the nomination?`, destructive: true }))) return;
    selectForm.delete(`${base}/state-nomination/selections/${selection.id}`, { preserveScroll: true });
}

async function certify() {
    if (!(await confirm({ message: 'Certify this batch? Once certified, State submission will use these selections instead of raw results, and selections can no longer be changed.', destructive: false }))) return;
    certifyForm.post(`${base}/state-nomination/certify`, { preserveScroll: true });
}
</script>
