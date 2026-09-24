<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <div v-if="!windowOpen" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
            {{ windowNote }} Substitutions close with scrutiny — after that a change of participant is an appeal, not a correction.
        </div>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h2 class="text-sm font-bold text-slate-900">Substitutions</h2>
            <p class="mb-4 mt-0.5 text-xs text-slate-500">
                Each one is a decision with a reason and an approver, not an edit — the history stays
                whatever happens to the team afterwards.
            </p>

            <p v-if="!substitutions.length" class="py-10 text-center text-sm text-slate-400">No substitutions requested.</p>

            <ul v-else class="space-y-2">
                <li v-for="s in substitutions" :key="s.id" class="rounded-xl border border-slate-200 p-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm text-slate-800">
                                <span class="font-semibold line-through decoration-slate-400">{{ s.original_name }}</span>
                                <span class="mx-1 text-slate-400">→</span>
                                <span class="font-semibold">{{ s.substitute_name }}</span>
                                <span v-if="s.substitute_class" class="text-xs text-slate-400"> · {{ s.substitute_class }}</span>
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ s.sahodaya }} · {{ s.school }} · <span class="font-mono">{{ s.item_code }}</span>
                            </p>
                            <p class="mt-1 text-xs italic text-slate-600">“{{ s.reason }}”</p>
                            <p class="mt-0.5 text-[11px] text-slate-400">
                                requested {{ s.requested_at }}<span v-if="s.requested_by"> by {{ s.requested_by }}</span>
                                <span v-if="s.decided_at"> · decided {{ s.decided_at }} by {{ s.decided_by }}</span>
                            </p>
                            <p v-if="s.decision_note" class="mt-0.5 text-xs text-slate-600">{{ s.decision_note }}</p>
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase"
                                  :class="{
                                      'bg-amber-50 text-amber-700': s.status === 'requested',
                                      'bg-emerald-50 text-emerald-700': s.status === 'approved',
                                      'bg-rose-50 text-rose-700': s.status === 'rejected',
                                  }">{{ s.status }}</span>
                            <div v-if="s.status === 'requested'" class="flex gap-2">
                                <button type="button" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white" @click="decide(s, 'approved')">Approve</button>
                                <button type="button" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white" @click="decide(s, 'rejected')">Reject</button>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    substitutions: Array, windowOpen: Boolean, windowNote: String, actionUrls: Object,
});

function decide(s, decision) {
    // A refusal has to say why — the Sahodaya sees it.
    const note = decision === 'rejected'
        ? window.prompt(`Why is the substitution of ${s.original_name} refused?`)
        : window.prompt('Note (optional)') || null;

    if (decision === 'rejected' && !note) return;

    router.post(props.actionUrls.decide, { substitution_id: s.id, decision, note }, { preserveScroll: true });
}
</script>
