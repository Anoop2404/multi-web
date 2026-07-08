<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Training"
        :subtitle="school.name"
        accent="indigo"
        :nav-items="navItems"
        :avatar-url="teacher?.photo_url"
        show-avatar-placeholder
    >
        <section v-if="openPrograms?.length" class="card mb-4">
            <h2 class="font-semibold text-sm mb-1 text-slate-900">Open programmes</h2>
            <p class="text-xs text-slate-500 mb-3">Register for Sahodaya teacher training events.</p>
            <div v-for="p in openPrograms" :key="p.id" class="border-t first:border-0 pt-4 first:pt-0 mb-4 last:mb-0">
                <p class="font-medium text-sm text-slate-900">{{ p.title }}</p>
                <p v-if="p.description" class="text-xs text-slate-500 mt-0.5">{{ p.description }}</p>
                <p class="text-xs text-slate-500 mt-1">
                    <span v-if="p.venue">{{ p.venue }}</span>
                    <span v-if="p.start_date"> · {{ formatDate(p.start_date) }}<span v-if="p.end_date && p.end_date !== p.start_date"> – {{ formatDate(p.end_date) }}</span></span>
                    <span v-if="p.has_fee"> · Fee ₹{{ p.fee_amount }}</span>
                </p>
                <button v-if="p.can_register"
                        type="button"
                        class="btn-primary text-xs mt-2 !min-h-0 !py-1.5 !px-3"
                        :disabled="registering === p.id"
                        @click="register(p)">
                    {{ registering === p.id ? 'Registering…' : 'Register' }}
                </button>
                <p v-else-if="p.ineligibility_reason" class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded px-2 py-1.5 mt-2">
                    {{ p.ineligibility_reason }}
                </p>
            </div>
        </section>

        <section v-else class="card mb-4">
            <h2 class="font-semibold text-sm mb-1 text-slate-900">Open programmes</h2>
            <p class="text-sm text-slate-400 py-3">No training programmes are open for registration right now. Check back later or contact your school admin.</p>
        </section>

        <section class="card">
            <h2 class="font-semibold text-sm mb-2 text-slate-900">My registrations</h2>
            <div v-for="t in training" :key="t.id" class="border-t first:border-0 pt-4 first:pt-0 mb-4 last:mb-0">
                <p class="font-medium text-sm text-slate-900">{{ t.program?.title }}</p>
                <p class="text-xs text-slate-500 capitalize">{{ t.status }}
                    <span v-if="t.fee_status"> · fee {{ t.fee_status.replace('_', ' ') }}</span>
                </p>
                <p v-if="t.program?.venue" class="text-xs text-slate-500">{{ t.program.venue }}</p>

                <ul v-if="t.sessions?.length" class="mt-2 text-xs space-y-1">
                    <li v-for="s in t.sessions" :key="s.id" class="text-slate-600">
                        {{ s.title }} · {{ s.scheduled_at ? new Date(s.scheduled_at).toLocaleString() : 'TBA' }}
                        <span v-if="s.venue"> · {{ s.venue }}</span>
                        <span v-if="s.attendance" class="ml-1 capitalize font-medium text-indigo-700">({{ s.attendance }})</span>
                    </li>
                </ul>

                <form v-if="needsPayment(t)" @submit.prevent="uploadPayment(t)" class="mt-3 space-y-2 border rounded-lg p-3 bg-slate-50">
                    <p class="text-xs font-semibold text-slate-700">
                        Upload payment proof
                        <span v-if="t.fee_total"> · Balance ₹{{ balance(t) }}</span>
                    </p>
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" required class="field text-xs"
                           @change="e => paymentForms[t.id].payment_proof = e.target.files[0]">
                    <input v-model="paymentForms[t.id].transaction_ref" class="field text-xs" placeholder="Transaction ref (optional)">
                    <input v-if="t.fee_total > 0" v-model="paymentForms[t.id].amount" type="number" min="1" :max="balance(t)"
                           step="0.01" class="field text-xs" :placeholder="`Amount (max ₹${balance(t)})`">
                    <button type="submit" class="btn-primary text-xs !min-h-0 !py-1.5" :disabled="paymentForms[t.id]?.processing">
                        {{ paymentForms[t.id]?.processing ? 'Uploading…' : 'Submit proof' }}
                    </button>
                </form>

                <a v-if="t.certificate_uuid" :href="`/portal/teacher/${school.id}/training/${t.id}/certificate`" target="_blank"
                   class="text-xs font-semibold text-indigo-600 mt-2 inline-block">Download certificate ↗</a>
            </div>
            <p v-if="!training?.length" class="text-sm text-slate-400 py-3">You have not registered for any training yet.</p>
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { teacherPortalNavItems } from '@/support/teacherPortalNav.js';

const props = defineProps({
    school: Object,
    teacher: Object,
    training: { type: Array, default: () => [] },
    openPrograms: { type: Array, default: () => [] },
});

const navItems = computed(() => teacherPortalNavItems(props.school.id));
const registering = ref(null);

const paymentForms = reactive({});

for (const t of props.training ?? []) {
    paymentForms[t.id] = { payment_proof: null, transaction_ref: '', amount: '', processing: false };
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function needsPayment(t) {
    if (!t.program?.fee_type || t.program.fee_type === 'none') return false;
    if (t.status === 'confirmed' && t.fee_status === 'approved') return false;
    if (t.feeReceipt?.status === 'uploaded') return false;
    return (t.fee_total ?? 0) > 0 && balance(t) > 0;
}

function balance(t) {
    const paid = parseFloat(t.amount_paid ?? 0);
    const total = parseFloat(t.fee_total ?? t.program?.fee_amount ?? 0);
    return Math.max(0, Math.round((total - paid) * 100) / 100);
}

function register(program) {
    registering.value = program.id;
    router.post(`/portal/teacher/${props.school.id}/training/programs/${program.id}/register`, {}, {
        preserveScroll: true,
        onFinish: () => { registering.value = null; },
    });
}

function uploadPayment(registration) {
    const form = paymentForms[registration.id];
    if (!form?.payment_proof) return;
    form.processing = true;

    const data = new FormData();
    data.append('payment_proof', form.payment_proof);
    if (form.transaction_ref) data.append('transaction_ref', form.transaction_ref);
    if (form.amount) data.append('amount', form.amount);

    router.post(`/portal/teacher/${props.school.id}/training/registrations/${registration.id}/payment`, data, {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => { form.processing = false; },
        onSuccess: () => {
            form.payment_proof = null;
            form.transaction_ref = '';
            form.amount = '';
        },
    });
}
</script>
