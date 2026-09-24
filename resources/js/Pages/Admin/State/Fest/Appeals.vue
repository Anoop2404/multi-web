<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Appeals</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        The only sanctioned way a published result changes. Upholding one takes the
                        item off public view and marks its certificates stale.
                    </p>
                </div>
                <button type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white" @click="adding = !adding">
                    Record appeal
                </button>
            </div>

            <form v-if="adding" @submit.prevent="submit" class="mb-4 grid gap-3 rounded-xl border border-[color:var(--brand-blue)]/30 p-3 sm:grid-cols-2 lg:grid-cols-4">
                <label><span class="lbl">Sahodaya</span>
                    <select v-model="form.sahodaya_id" class="inp">
                        <option :value="null">—</option>
                        <option v-for="s in sahodayas" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </label>
                <label><span class="lbl">Item</span>
                    <select v-model="form.item_id" class="inp" @change="syncItemCode">
                        <option :value="null">—</option>
                        <option v-for="i in items" :key="i.id" :value="i.id">{{ i.item_code }} — {{ i.title }}</option>
                    </select>
                </label>
                <label><span class="lbl">Participant</span><input v-model="form.participant_name" class="inp"></label>
                <label><span class="lbl">School</span><input v-model="form.school_name" class="inp"></label>
                <label class="lg:col-span-3"><span class="lbl">Grounds</span><input v-model="form.grounds" class="inp" required></label>
                <label><span class="lbl">Fee (₹)</span><input v-model.number="form.fee_amount" type="number" min="0" class="inp"></label>
                <div class="flex items-end gap-2 lg:col-span-4">
                    <button type="submit" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white">Save</button>
                    <button type="button" class="text-xs text-slate-500 underline" @click="adding = false">Cancel</button>
                </div>
            </form>

            <p v-if="!appeals.length" class="py-10 text-center text-sm text-slate-400">No appeals recorded.</p>

            <ul v-else class="space-y-2">
                <li v-for="a in appeals" :key="a.id" class="rounded-xl border border-slate-200 p-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800">
                                {{ a.participant || 'Unnamed' }}
                                <span v-if="a.item_code" class="ml-1 font-mono text-xs text-slate-400">{{ a.item_code }}</span>
                            </p>
                            <p class="text-xs text-slate-500">{{ a.school }} · submitted {{ a.submitted_at }}</p>
                            <p class="mt-1 text-xs italic text-slate-600">“{{ a.grounds }}”</p>
                            <p v-if="a.review_notes" class="mt-1 text-xs text-slate-700">Decision: {{ a.review_notes }}</p>
                            <p class="mt-0.5 text-[11px] text-slate-400">
                                Fee ₹{{ a.fee_amount }} · {{ a.fee_status }}
                                <span v-if="a.decided_at"> · decided {{ a.decided_at }} by {{ a.decided_by }}</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase"
                                  :class="{
                                      'bg-amber-50 text-amber-700': a.status === 'submitted' || a.status === 'under_review',
                                      'bg-emerald-50 text-emerald-700': a.status === 'upheld',
                                      'bg-rose-50 text-rose-700': a.status === 'dismissed',
                                  }">{{ a.status.replace('_', ' ') }}</span>
                            <div v-if="a.status === 'submitted' || a.status === 'under_review'" class="flex gap-2">
                                <button type="button" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white" @click="decide(a, 'upheld')">Uphold</button>
                                <button type="button" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white" @click="decide(a, 'dismissed')">Dismiss</button>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({ event: Object, events: Array, sahodayas: Array, permissions: Array, appeals: Array, items: Array, actionUrls: Object });

const adding = ref(false);
const form = useForm({ sahodaya_id: null, item_id: null, item_code: null, participant_name: '', school_name: '', grounds: '', fee_amount: 0 });

function syncItemCode() {
    form.item_code = props.items.find((i) => i.id === form.item_id)?.item_code ?? null;
}
function submit() {
    form.post(props.actionUrls.submit, { preserveScroll: true, onSuccess: () => { adding.value = false; form.reset(); } });
}
function decide(appeal, outcome) {
    // A decision without reasons cannot be defended later.
    const notes = window.prompt(`Reasons for ${outcome === 'upheld' ? 'upholding' : 'dismissing'} this appeal:`);
    if (!notes) return;
    router.post(props.actionUrls.decide, { appeal_id: appeal.id, outcome, notes }, { preserveScroll: true });
}
</script>

<style scoped>
.lbl { display: block; margin-bottom: 0.25rem; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: rgb(100 116 139); }
.inp { width: 100%; border-radius: 0.75rem; border: 1px solid rgb(203 213 225); padding: 0.5rem 0.75rem; font-size: 0.875rem; }
</style>
