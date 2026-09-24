<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <Link :href="actionUrls.back" class="text-xs link-brand">← All submissions</Link>
                    <h2 class="mt-1 text-sm font-bold text-slate-900">{{ intake.sahodaya }}</h2>
                    <p class="text-xs text-slate-500">Submitted {{ intake.submitted_at }} · {{ entries.length }} entries · {{ intake.status }}</p>
                </div>
                <div class="flex gap-2">
                    <button v-if="!intake.is_final" type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white" @click="finalise">
                        Finalise submission
                    </button>
                    <button v-else type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold text-slate-600" @click="reopen">
                        Reopen
                    </button>
                </div>
            </div>

            <div v-if="!scrutinyOpen" class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                {{ scrutinyNote }}
            </div>
            <div v-if="intake.is_final" class="mb-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                This submission is finalised. Reopen it to change an entry.
            </div>

            <!-- Bulk bar: scrutiny is done in batches, and returning fifty entries one at a time is
                 how a deadline gets missed. -->
            <div v-if="selected.length && !intake.is_final" class="mb-3 flex flex-wrap items-center gap-2 rounded-xl border border-[color:var(--brand-blue)]/30 bg-[color:var(--brand-blue)]/5 p-3">
                <span class="text-xs font-bold text-slate-700">{{ selected.length }} selected</span>
                <input v-model="note" class="min-w-[14rem] flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                       placeholder="Note — required when returning or requesting documents">
                <button v-for="d in decisions" :key="d.value" type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-bold text-white" :class="d.class"
                        @click="decide(d.value)">
                    {{ d.label }}
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-2"><input type="checkbox" :checked="allSelected" @change="toggleAll" class="rounded border-slate-300"></th>
                            <th class="py-2 px-2">Participant</th>
                            <th class="py-2 px-2">School</th>
                            <th class="py-2 px-2">Item</th>
                            <th class="py-2 px-2 text-center">Slots</th>
                            <th class="py-2 px-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in entries" :key="e.id" class="border-b border-slate-100 align-top">
                            <td class="py-2 pr-2">
                                <input type="checkbox" :value="e.id" v-model="selected" :disabled="intake.is_final" class="rounded border-slate-300">
                            </td>
                            <td class="py-2 px-2">
                                <p class="font-medium text-slate-800">
                                    {{ e.student_name }}
                                    <span v-if="e.is_reserve" class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">reserve</span>
                                </p>
                                <p class="text-xs text-slate-400">
                                    <span v-if="e.class_name">{{ e.class_name }}</span>
                                    <span v-if="e.position"> · position {{ e.position }}</span>
                                    <span v-if="e.grade"> · {{ e.grade }}</span>
                                </p>
                            </td>
                            <!-- The School is on every row, beside the Sahodaya in the header. -->
                            <td class="py-2 px-2 text-slate-600">{{ e.school_name }}</td>
                            <td class="py-2 px-2">
                                <span class="font-mono text-xs">{{ e.item_code }}</span>
                                <span class="block text-xs text-slate-400">{{ e.item_name }}</span>
                            </td>
                            <td class="py-2 px-2 text-center text-xs text-slate-500">{{ e.slots ?? '∞' }}</td>
                            <td class="py-2 px-2">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                                      :class="{
                                          'bg-slate-100 text-slate-600': e.status === 'pending',
                                          'bg-emerald-50 text-emerald-700': e.status === 'approved',
                                          'bg-rose-50 text-rose-700': e.status === 'rejected',
                                          'bg-amber-50 text-amber-700': e.status === 'returned' || e.status === 'documents_requested',
                                      }">{{ e.status.replace('_', ' ') }}</span>
                                <p v-if="e.review_note" class="mt-1 text-xs italic text-slate-500">“{{ e.review_note }}”</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="history.length" class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h2 class="mb-3 text-sm font-bold text-slate-900">Decision history</h2>
            <ul class="space-y-1.5 text-xs">
                <li v-for="h in history" :key="h.id" class="flex flex-wrap gap-x-2 border-b border-slate-100 pb-1.5 text-slate-600">
                    <span class="font-mono text-slate-400">{{ h.at }}</span>
                    <span class="font-semibold text-slate-800">{{ h.decision.replace('_', ' ') }}</span>
                    <span v-if="h.by" class="text-slate-400">by {{ h.by }}</span>
                    <span v-if="h.note" class="italic text-slate-500">“{{ h.note }}”</span>
                </li>
            </ul>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    intake: Object, entries: Array, history: Array, scrutinyOpen: Boolean, scrutinyNote: String, actionUrls: Object,
});

const selected = ref([]);
const note = ref('');

const decisions = [
    { value: 'approved', label: 'Approve', class: 'bg-emerald-600' },
    { value: 'returned', label: 'Return', class: 'bg-amber-600' },
    { value: 'documents_requested', label: 'Request documents', class: 'bg-sky-600' },
    { value: 'rejected', label: 'Reject', class: 'bg-rose-600' },
];

const allSelected = computed(() => props.entries.length > 0 && selected.value.length === props.entries.length);
function toggleAll() {
    selected.value = allSelected.value ? [] : props.entries.map((e) => e.id);
}

function decide(decision) {
    router.post(props.actionUrls.decide, { entry_ids: selected.value, decision, note: note.value || null }, {
        preserveScroll: true,
        onSuccess: () => { selected.value = []; note.value = ''; },
    });
}

function finalise() {
    router.post(props.actionUrls.finalise, { note: note.value || null }, { preserveScroll: true });
}

function reopen() {
    router.post(props.actionUrls.reopen, {}, { preserveScroll: true });
}
</script>
