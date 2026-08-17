<template>
    <section v-if="event?.uses_registration_batch_billing" class="space-y-5 mb-6">
        <div v-if="event.phase_region_options?.length" class="card border-indigo-200">
            <h3 class="section-title">Choose regions by competition phase</h3>
            <p class="section-desc mb-4">Off Stage and Sargadhara are independent. A choice locks when your first registration in that phase is submitted.</p>
            <div class="grid gap-4 md:grid-cols-2">
                <form v-for="phase in event.phase_region_options" :key="phase.phase_id" class="rounded-xl border border-slate-200 p-4" @submit.prevent="saveRegion(phase)">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <p class="font-semibold text-slate-800">{{ phase.phase_name }}</p>
                        <span v-if="phase.selection?.locked" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">Locked</span>
                    </div>
                    <select v-model="regionChoices[phase.phase_id]" class="field text-sm" :disabled="phase.selection?.locked" required>
                        <option value="" disabled>Select a region…</option>
                        <option v-for="region in phase.regions" :key="region.id" :value="region.id">
                            {{ region.name }}{{ region.venue ? ` — ${region.venue}` : '' }}
                        </option>
                    </select>
                    <button v-if="!phase.selection?.locked" type="submit" class="btn-secondary text-xs mt-3">Save {{ phase.phase_name }} region</button>
                </form>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <article v-for="fee in event.school_registration_batch_fees" :key="fee.registration_batch_id" class="card border-emerald-200">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">{{ fee.batch_code }}</p>
                        <h3 class="text-lg font-bold text-slate-900">{{ fee.batch_name }}</h3>
                        <p v-if="fee.registration_close" class="text-xs text-slate-500">Registration closes {{ formatDate(fee.registration_close) }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(fee.status)">{{ fee.status }}</span>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
                    <div><dt class="text-slate-500">School fee</dt><dd class="font-semibold">₹{{ money(fee.school_registration_fee) }}</dd></div>
                    <div><dt class="text-slate-500">Participation</dt><dd class="font-semibold">₹{{ money(fee.participation_fee) }}</dd></div>
                    <div><dt class="text-slate-500">Total</dt><dd class="font-bold">₹{{ money(fee.total_due) }}</dd></div>
                    <div><dt class="text-slate-500">Outstanding</dt><dd class="font-bold text-amber-700">₹{{ money(fee.outstanding) }}</dd></div>
                </dl>

                <details v-if="fee.lines?.length" class="mt-3 text-xs">
                    <summary class="cursor-pointer font-semibold text-slate-600">Invoice lines</summary>
                    <ul class="mt-2 divide-y divide-slate-100">
                        <li v-for="(line, index) in fee.lines" :key="index" class="flex justify-between gap-3 py-1.5">
                            <span>{{ line.label }}</span><span>₹{{ money(line.amount) }}</span>
                        </li>
                    </ul>
                </details>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a :href="`${eventBase}/invoice?registration_batch_id=${fee.registration_batch_id}`" class="btn-secondary text-xs">Invoice</a>
                    <a v-if="fee.status === 'approved'" :href="`${eventBase}/receipt?registration_batch_id=${fee.registration_batch_id}`" class="btn-secondary text-xs">Receipt</a>
                    <button v-if="Number(fee.outstanding) > 0" type="button" class="btn-primary text-xs" @click="openPayment(fee)">Upload payment</button>
                </div>
            </article>
        </div>

        <div v-if="paymentBatch" class="card border-amber-200">
            <div class="flex items-center justify-between gap-3">
                <h3 class="section-title">Upload {{ paymentBatch.batch_name }} payment</h3>
                <button type="button" class="text-slate-500" @click="paymentBatch = null">×</button>
            </div>
            <form class="grid gap-3 md:grid-cols-2 mt-3" @submit.prevent="submitPayment">
                <input v-model="paymentForm.transaction_ref" class="field text-sm" placeholder="Transaction reference" required>
                <input v-model="paymentForm.bank_name" class="field text-sm" placeholder="Bank name" required>
                <input v-model.number="paymentForm.amount" type="number" min="0.01" step="0.01" class="field text-sm" placeholder="Amount" required>
                <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="field text-sm" multiple required @change="paymentFiles = Array.from($event.target.files || [])">
                <div class="md:col-span-2"><button type="submit" class="btn-primary text-sm" :disabled="paymentForm.processing">Upload payment proof</button></div>
            </form>
        </div>
    </section>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: { type: Object, required: true },
    schoolId: { type: String, required: true },
    programPrefix: { type: String, required: true },
});

const eventBase = `/school-admin/${props.schoolId}/${props.programPrefix}/events/${props.event.id}`;
const regionChoices = reactive(Object.fromEntries(
    (props.event.phase_region_options || []).map((phase) => [phase.phase_id, phase.selection?.region_id || '']),
));
const paymentBatch = ref(null);
const paymentFiles = ref([]);
const paymentForm = useForm({ transaction_ref: '', bank_name: '', amount: null });

function saveRegion(phase) {
    router.post(`${eventBase}/phase-region`, {
        phase_id: phase.phase_id,
        region_id: regionChoices[phase.phase_id],
    }, { preserveScroll: true });
}

function openPayment(fee) {
    paymentBatch.value = fee;
    paymentForm.amount = Number(fee.outstanding || 0);
}

function submitPayment() {
    const payload = new FormData();
    payload.append('registration_batch_id', paymentBatch.value.registration_batch_id);
    payload.append('transaction_ref', paymentForm.transaction_ref);
    payload.append('bank_name', paymentForm.bank_name);
    payload.append('amount', paymentForm.amount);
    paymentFiles.value.forEach((file) => payload.append('payment_proof[]', file));
    router.post(`${eventBase}/payment`, payload, {
        preserveScroll: true,
        onSuccess: () => {
            paymentBatch.value = null;
            paymentForm.reset();
            paymentFiles.value = [];
        },
    });
}

function money(value) { return Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function formatDate(value) { return new Date(value).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }); }
function statusClass(status) {
    if (status === 'approved') return 'bg-emerald-100 text-emerald-800';
    if (status === 'proof_uploaded' || status === 'partial') return 'bg-blue-100 text-blue-800';
    if (status === 'rejected') return 'bg-red-100 text-red-800';
    return 'bg-amber-100 text-amber-800';
}
</script>
