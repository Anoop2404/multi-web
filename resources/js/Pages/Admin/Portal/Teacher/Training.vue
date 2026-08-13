<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Training"
        :subtitle="school.name"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="teacher?.photo_url"
        show-avatar-placeholder
    >
        <section class="card mb-5">
            <h2 class="section-title text-base mb-1">Open programmes</h2>
            <p class="text-xs text-slate-500 mb-4">Register for Sahodaya teacher training events.</p>

            <div v-if="openPrograms?.length" class="grid gap-3 sm:grid-cols-2">
                <div v-for="p in openPrograms" :key="p.id" class="program-card">
                    <div class="flex items-center gap-3">
                        <span class="program-card-icon">📚</span>
                        <p class="font-semibold text-slate-900">{{ p.title }}</p>
                    </div>
                    <p v-if="p.description" class="text-xs text-slate-500">{{ p.description }}</p>
                    <p class="text-xs text-slate-500">
                        <span v-if="p.venue">{{ p.venue }}</span>
                        <span v-if="p.start_date"> · {{ formatDate(p.start_date) }}<span v-if="p.end_date && p.end_date !== p.start_date"> – {{ formatDate(p.end_date) }}</span></span>
                        <span v-if="p.has_fee"> · Fee ₹{{ p.fee_amount }}</span>
                    </p>
                    <button v-if="p.can_register"
                            type="button"
                            class="btn-primary text-xs mt-1 !min-h-0 !py-1.5 !px-3 w-fit"
                            :disabled="registering === p.id"
                            @click="register(p)">
                        {{ registering === p.id ? 'Registering…' : 'Register' }}
                    </button>
                    <p v-else-if="p.ineligibility_reason" class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-2.5 py-1.5">
                        {{ p.ineligibility_reason }}
                    </p>
                </div>
            </div>
            <EmptyState v-else title="No open training programmes" description="Check back later or contact your school admin about upcoming Sahodaya training." icon="📚" />
        </section>

        <section class="card">
            <h2 class="section-title text-base mb-3">My registrations</h2>

            <div v-if="training?.length" class="space-y-4">
                <div v-for="t in training" :key="t.id" class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900">{{ t.program?.title }}</p>
                            <p v-if="t.program?.venue" class="text-xs text-slate-500 mt-0.5">{{ t.program.venue }}</p>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <span class="track-status-pill" :class="pillClass(t.status)">{{ t.status }}</span>
                            <span v-if="t.status === 'waitlisted' && t.waitlist_position" class="text-xs text-slate-500">#{{ t.waitlist_position }}</span>
                            <span v-if="t.fee_status" class="status-pill status-pill--draft capitalize">{{ t.fee_status.replace('_', ' ') }}</span>
                        </div>
                    </div>

                    <ul v-if="t.sessions?.length" class="mt-3 text-xs space-y-1 border-t border-slate-100 pt-3">
                        <li v-for="s in t.sessions" :key="s.id" class="text-slate-600">
                            <span class="font-medium text-slate-800">{{ s.title }}</span>
                            · {{ s.scheduled_at ? new Date(s.scheduled_at).toLocaleString() : 'TBA' }}
                            <span v-if="s.venue"> · {{ s.venue }}</span>
                            <span v-if="s.attendance" class="ml-1 capitalize font-semibold text-[#0f3d7a]">({{ s.attendance }})</span>
                        </li>
                    </ul>

                    <form v-if="needsPayment(t)" @submit.prevent="uploadPayment(t)" class="mt-3 space-y-2 rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                        <p class="text-xs font-semibold text-slate-700">
                            Upload payment proof
                            <span v-if="t.fee_total"> · Balance ₹{{ balance(t) }}</span>
                        </p>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" required class="field field--sm"
                               @change="e => paymentForms[t.id].payment_proof = e.target.files[0]">
                        <input v-model="paymentForms[t.id].transaction_ref" class="field field--sm" placeholder="Transaction ref (optional)">
                        <input v-if="t.fee_total > 0" v-model="paymentForms[t.id].amount" type="number" min="1" :max="balance(t)"
                               step="0.01" class="field field--sm" :placeholder="`Amount (max ₹${balance(t)})`">
                        <button type="submit" class="btn-primary text-xs !min-h-0 !py-1.5" :disabled="paymentForms[t.id]?.processing">
                            {{ paymentForms[t.id]?.processing ? 'Uploading…' : 'Submit proof' }}
                        </button>
                    </form>

                    <a v-if="t.certificate_uuid" :href="`${base}/training/${t.id}/certificate`" target="_blank"
                       class="text-xs font-semibold text-[#0f3d7a] mt-3 inline-block">Download certificate ↗</a>

                    <p v-if="t.feedback" class="text-xs text-slate-500 mt-3">
                        Feedback submitted · {{ t.feedback.rating }}/5
                        <span class="capitalize">({{ t.feedback.status }})</span>
                    </p>

                    <form v-if="t.can_submit_feedback" @submit.prevent="submitFeedback(t)"
                          class="mt-3 space-y-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                        <p class="text-xs font-semibold text-slate-700">Share your feedback</p>
                        <FormField label="Overall rating (1–5)">
                            <select v-model="feedbackForms[t.id].rating" required class="field field--sm">
                                <option value="" disabled>Select…</option>
                                <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </FormField>
                        <div class="grid grid-cols-3 gap-2">
                            <FormField label="Content">
                                <select v-model="feedbackForms[t.id].content_rating" class="field field--sm">
                                    <option value="">—</option>
                                    <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                                </select>
                            </FormField>
                            <FormField label="Trainer">
                                <select v-model="feedbackForms[t.id].trainer_rating" class="field field--sm">
                                    <option value="">—</option>
                                    <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                                </select>
                            </FormField>
                            <FormField label="Venue">
                                <select v-model="feedbackForms[t.id].venue_rating" class="field field--sm">
                                    <option value="">—</option>
                                    <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                                </select>
                            </FormField>
                        </div>
                        <textarea v-model="feedbackForms[t.id].comments" rows="2" class="field text-xs"
                                  placeholder="Comments (optional)"></textarea>
                        <button type="submit" class="btn-primary text-xs !min-h-0 !py-1.5"
                                :disabled="!feedbackForms[t.id]?.rating || feedbackForms[t.id]?.processing">
                            {{ feedbackForms[t.id]?.processing ? 'Submitting…' : 'Submit feedback' }}
                        </button>
                    </form>
                </div>
            </div>
            <EmptyState v-else title="No training registrations yet" description="Register for an open programme above to see it here." icon="🎓" />
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import FormField from '@/Components/ui/FormField.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
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
const base = computed(() => `/portal/teacher/${props.school.id}`);
const registering = ref(null);

const paymentForms = reactive({});
const feedbackForms = reactive({});

for (const t of props.training ?? []) {
    paymentForms[t.id] = { payment_proof: null, transaction_ref: '', amount: '', processing: false };
    feedbackForms[t.id] = {
        rating: '',
        content_rating: '',
        trainer_rating: '',
        venue_rating: '',
        comments: '',
        processing: false,
    };
}

function pillClass(status) {
    const key = String(status ?? '').toLowerCase();
    if (['confirmed', 'attended', 'completed'].includes(key)) return 'track-status-pill--approved';
    if (['cancelled', 'rejected'].includes(key)) return 'track-status-pill--rejected';
    if (['waitlisted', 'pending'].includes(key)) return 'track-status-pill--submitted';
    return 'track-status-pill--pending';
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function needsPayment(t) {
    if (!t.program?.fee_type || t.program.fee_type === 'none' || t.program.fee_type === 'school') return false;
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
    router.post(`${base.value}/training/programs/${program.id}/register`, {}, {
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

    router.post(`${base.value}/training/registrations/${registration.id}/payment`, data, {
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

function submitFeedback(registration) {
    const form = feedbackForms[registration.id];
    if (!form?.rating) return;
    form.processing = true;

    router.post(`${base.value}/training/registrations/${registration.id}/feedback`, {
        rating: form.rating,
        content_rating: form.content_rating || null,
        trainer_rating: form.trainer_rating || null,
        venue_rating: form.venue_rating || null,
        comments: form.comments || null,
    }, {
        preserveScroll: true,
        onFinish: () => { form.processing = false; },
    });
}
</script>
