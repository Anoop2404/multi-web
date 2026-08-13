<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Training & Workshops"
        :subtitle="school.name"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="teacher?.photo_url"
        show-avatar-placeholder
    >
        <!-- Hero Header -->
        <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-r from-[#041525] via-[#0f3d7a] to-[#1e5aa8] p-6 text-white shadow-md">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-white/10 flex items-center justify-center text-2xl shrink-0 backdrop-blur-sm border border-white/20">
                    🎓
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight">Professional Development & Training</h1>
                    <p class="text-xs sm:text-sm text-white/80 mt-1">Register for Sahodaya accredited teacher workshops, track attendance, and download certified credentials.</p>
                </div>
            </div>
        </div>

        <!-- Open Programmes Section -->
        <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-500" />
                        Open Training Programmes
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Direct self-registration open for eligible Sahodaya teachers.</p>
                </div>
                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                    {{ openPrograms?.length ?? 0 }} Available
                </span>
            </div>

            <div v-if="openPrograms?.length" class="grid gap-4 sm:grid-cols-2">
                <div v-for="p in openPrograms" :key="p.id" class="program-card group flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-[#0f3d7a]/30 hover:shadow-md">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="h-10 w-10 rounded-xl bg-[#eff6ff] text-[#0f3d7a] flex items-center justify-center font-bold text-lg shrink-0">
                                    📚
                                </span>
                                <h3 class="font-bold text-slate-900 text-base group-hover:text-[#0f3d7a] transition">{{ p.title }}</h3>
                            </div>
                        </div>

                        <p v-if="p.description" class="text-xs text-slate-600 mt-2.5 leading-relaxed">{{ p.description }}</p>

                        <div class="mt-4 space-y-2 text-xs text-slate-500 border-t border-slate-100 pt-3">
                            <p v-if="p.venue" class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                </svg>
                                <span>{{ p.venue }}</span>
                            </p>
                            <p v-if="p.start_date" class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span>{{ formatDate(p.start_date) }}<span v-if="p.end_date && p.end_date !== p.start_date"> – {{ formatDate(p.end_date) }}</span></span>
                            </p>
                            <p v-if="p.has_fee" class="flex items-center gap-2 font-semibold text-slate-700">
                                <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span>Fee: ₹{{ p.fee_amount }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <button v-if="p.can_register"
                                type="button"
                                class="btn-primary text-xs w-full justify-center !min-h-0 !py-2.5 shadow-sm"
                                :disabled="registering === p.id"
                                @click="register(p)">
                            <svg v-if="registering !== p.id" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ registering === p.id ? 'Registering…' : 'Register Now' }}
                        </button>
                        <div v-else-if="p.ineligibility_reason" class="text-xs text-amber-800 bg-amber-50 border border-amber-200/80 rounded-xl px-3 py-2 leading-relaxed">
                            ⚠️ {{ p.ineligibility_reason }}
                        </div>
                    </div>
                </div>
            </div>
            <EmptyState v-else title="No open training programmes" description="Check back later or contact your school admin about upcoming Sahodaya training." icon="📚" />
        </section>

        <!-- My Registrations Section -->
        <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                    🎓 My Training Registrations
                </h2>
                <span class="text-xs font-semibold text-slate-500">Total: {{ training?.length ?? 0 }}</span>
            </div>

            <div v-if="training?.length" class="space-y-4">
                <div v-for="t in training" :key="t.id" class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm space-y-4 transition hover:border-[#0f3d7a]/20">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <h3 class="font-bold text-slate-900 text-base">{{ t.program?.title }}</h3>
                            <p v-if="t.program?.venue" class="text-xs text-slate-500 mt-1 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                </svg>
                                {{ t.program.venue }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="track-status-pill font-bold" :class="pillClass(t.status)">{{ t.status }}</span>
                            <span v-if="t.status === 'waitlisted' && t.waitlist_position" class="text-xs font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                                Waitlist #{{ t.waitlist_position }}
                            </span>
                            <span v-if="t.fee_status" class="status-pill status-pill--draft capitalize font-semibold">
                                Fee: {{ t.fee_status.replace('_', ' ') }}
                            </span>
                        </div>
                    </div>

                    <!-- Sessions Timeline -->
                    <div v-if="t.sessions?.length" class="bg-slate-50/80 rounded-xl p-3.5 border border-slate-100 space-y-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Program Sessions & Attendance</p>
                        <ul class="text-xs space-y-2 divide-y divide-slate-200/50">
                            <li v-for="s in t.sessions" :key="s.id" class="pt-2 first:pt-0 flex items-center justify-between text-slate-700">
                                <div>
                                    <span class="font-bold text-slate-900">{{ s.title }}</span>
                                    <span class="text-slate-500 ml-2">· {{ s.scheduled_at ? new Date(s.scheduled_at).toLocaleString() : 'TBA' }}</span>
                                    <span v-if="s.venue" class="text-slate-500"> · {{ s.venue }}</span>
                                </div>
                                <span v-if="s.attendance" class="ml-2 font-bold px-2 py-0.5 rounded-md text-[11px] uppercase"
                                      :class="s.attendance === 'present' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'">
                                    {{ s.attendance }}
                                </span>
                            </li>
                        </ul>
                    </div>

                    <!-- Payment Proof Form -->
                    <form v-if="needsPayment(t)" @submit.prevent="uploadPayment(t)" class="space-y-3 rounded-2xl border border-amber-200/90 bg-amber-50/50 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-bold text-amber-900 flex items-center gap-1.5">
                                💳 Upload Payment Receipt
                                <span v-if="t.fee_total" class="font-normal text-amber-800">· Balance ₹{{ balance(t) }}</span>
                            </p>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png" required class="field field--sm"
                                   @change="e => paymentForms[t.id].payment_proof = e.target.files[0]">
                            <input v-model="paymentForms[t.id].transaction_ref" class="field field--sm" placeholder="Transaction Reference No.">
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-if="t.fee_total > 0" v-model="paymentForms[t.id].amount" type="number" min="1" :max="balance(t)"
                                   step="0.01" class="field field--sm w-48" :placeholder="`Amount (max ₹${balance(t)})`">
                            <button type="submit" class="btn-primary text-xs !min-h-0 !py-2 px-4 shadow-sm" :disabled="paymentForms[t.id]?.processing">
                                {{ paymentForms[t.id]?.processing ? 'Uploading…' : 'Submit Receipt' }}
                            </button>
                        </div>
                    </form>

                    <!-- Certificate Download -->
                    <div v-if="t.certificate_uuid" class="pt-1">
                        <a :href="`${base}/training/${t.id}/certificate`" target="_blank"
                           class="inline-flex items-center gap-2 text-xs font-bold text-white bg-[#0f3d7a] hover:bg-[#041525] px-3.5 py-2 rounded-xl transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Download Training Certificate PDF
                        </a>
                    </div>

                    <!-- Submitted Feedback Summary -->
                    <p v-if="t.feedback" class="text-xs font-medium text-emerald-800 bg-emerald-50 border border-emerald-200/80 rounded-xl px-3 py-2 flex items-center justify-between">
                        <span>Feedback Submitted: Overall {{ t.feedback.rating }}/5 stars</span>
                        <span class="capitalize text-emerald-600 font-bold">({{ t.feedback.status }})</span>
                    </p>

                    <!-- Submit Feedback Form -->
                    <form v-if="t.can_submit_feedback" @submit.prevent="submitFeedback(t)"
                          class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                        <p class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                            ⭐ Share Training Feedback
                        </p>
                        <FormField label="Overall Rating (1–5 Stars)">
                            <select v-model="feedbackForms[t.id].rating" required class="field field--sm">
                                <option value="" disabled>Select Rating…</option>
                                <option v-for="n in 5" :key="n" :value="n">⭐ {{ n }} / 5</option>
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
                                  placeholder="Additional comments or recommendations (optional)"></textarea>
                        <button type="submit" class="btn-primary text-xs !min-h-0 !py-2 px-4"
                                :disabled="!feedbackForms[t.id]?.rating || feedbackForms[t.id]?.processing">
                            {{ feedbackForms[t.id]?.processing ? 'Submitting…' : 'Submit Feedback' }}
                        </button>
                    </form>
                </div>
            </div>
            <EmptyState v-else title="No training registrations yet" description="Register for an open programme above to track status here." icon="🎓" />
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
