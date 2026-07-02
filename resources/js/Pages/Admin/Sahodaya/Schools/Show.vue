<template>
    <SahodayaAdminLayout :title="school.name" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="approvedSchoolsCount"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="max-w-3xl space-y-5">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/schools`"
                  class="inline-flex items-center gap-1.5 text-xs text-[#0f3d7a] hover:underline font-semibold">
                ← Back to Schools
            </Link>

            <div v-if="school.membership_status === 'pending'"
                 class="text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-xl px-4 py-3">
                This school is <strong>pending approval</strong>. Membership is approved when you
                <strong>verify their payment</strong> on the
                <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`" class="font-semibold text-[#0f3d7a] hover:underline">Payments</Link>
                page.
            </div>

            <!-- Header -->
            <div class="card">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-extrabold text-gray-900">{{ school.name }}</h2>
                            <StatusBadge :status="school.membership_status" />
                        </div>
                        <p class="text-sm text-gray-500 mt-1">
                            <span v-if="school.school_prefix" class="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-xs mr-2">{{ school.school_prefix }}</span>
                            Registered {{ formatDate(school.created_at) }}
                        </p>
                    </div>
                    <div class="text-right text-sm">
                        <p><strong class="text-[#0f3d7a]">{{ school.student_count }}</strong> students</p>
                        <p class="text-gray-500"><strong>{{ school.classes_count }}</strong> classes</p>
                    </div>
                </div>
            </div>

            <!-- Application details -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-900">Application Details</h3>
                </div>
                <dl v-if="detailFields.length" class="divide-y divide-gray-50">
                    <div v-for="field in detailFields" :key="field.label"
                         class="px-5 py-3 flex flex-col sm:flex-row sm:gap-4">
                        <dt class="text-xs font-semibold text-gray-400 uppercase sm:w-40 shrink-0">{{ field.label }}</dt>
                        <dd class="text-sm text-gray-800 break-words">{{ field.value }}</dd>
                    </div>
                </dl>
                <p v-else class="text-sm text-gray-400 text-center py-8">No application data on file.</p>
            </div>

            <!-- Login -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <h3 class="font-bold text-gray-900 mb-3">Portal Access</h3>
                <p class="text-sm text-gray-600">
                    Login account:
                    <span class="font-medium">{{ school.has_login ? (school.login_email || 'Yes') : 'Not created' }}</span>
                </p>
            </div>

            <!-- Fest registration -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-gray-900 mb-1">Fest Registration</h3>
                        <p class="text-sm text-gray-600">
                            Status:
                            <span :class="school.fest_registration_closed ? 'text-red-700 font-semibold' : 'text-emerald-700 font-semibold'">
                                {{ school.fest_registration_closed ? 'Closed for this school' : 'Open' }}
                            </span>
                        </p>
                    </div>
                    <button type="button" @click="toggleFestRegistration"
                            :class="school.fest_registration_closed ? 'btn-primary' : 'btn-secondary text-red-700 border-red-200 bg-red-50'">
                        {{ school.fest_registration_closed ? 'Reopen fest registration' : 'Close fest registration' }}
                    </button>
                </div>
            </div>

            <!-- Annual registration -->
            <div v-if="registration" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <h3 class="font-bold text-gray-900 mb-3">Annual Registration — {{ academicYear }}</h3>
                <div class="flex flex-wrap gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-400">Membership No.</p>
                        <p class="font-mono font-bold">{{ registration.reg_no }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Academic year</p>
                        <p class="font-mono">{{ academicYear }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Status</p>
                        <StatusBadge :status="registration.registration_status" />
                    </div>
                    <div v-if="registration.membership_fee_amount">
                        <p class="text-xs text-gray-400">Fee</p>
                        <p class="font-bold">₹{{ Number(registration.membership_fee_amount).toLocaleString('en-IN') }}</p>
                    </div>
                </div>
            </div>

            <!-- Recent payments -->
            <div v-if="recentPayments?.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900">Payment History</h3>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`"
                          class="text-xs font-semibold text-[#0f3d7a] hover:underline">Verify payments →</Link>
                </div>
                <div class="divide-y divide-gray-50">
                    <div v-for="p in recentPayments" :key="p.id" class="px-5 py-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ p.academic_year }} — ₹{{ Number(p.amount).toLocaleString('en-IN') }}</p>
                            <p class="text-xs text-gray-400">{{ formatDate(p.created_at) }} · {{ p.payment_method || '—' }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <StatusBadge :status="p.status" />
                            <a v-if="p.proof_url" :href="p.proof_url" target="_blank" rel="noopener"
                               class="text-xs font-semibold text-[#0f3d7a] hover:underline">View upload ↗</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { defineComponent, h } from 'vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String,
    approvedSchoolsCount: Number, pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number, pendingPaymentsCount: Number,
    school: Object, detailFields: Array, registration: Object,
    recentPayments: Array, academicYear: String,
});

function toggleFestRegistration() {
    const action = props.school.fest_registration_closed ? 'reopen' : 'close';
    if (!confirm(`${action.charAt(0).toUpperCase() + action.slice(1)} fest registration for this school?`)) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}/toggle-fest-registration`, {}, { preserveScroll: true });
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

const statusColors = {
    approved: 'bg-green-100 text-green-700',
    pending:  'bg-amber-100 text-amber-700',
    rejected: 'bg-red-100 text-red-700',
    submitted: 'bg-amber-100 text-amber-700',
    verified:  'bg-green-100 text-green-700',
    completed: 'bg-green-100 text-green-700',
    payment_pending: 'bg-blue-100 text-blue-700',
    payment_submitted: 'bg-amber-100 text-amber-700',
};

const StatusBadge = defineComponent({
    props: { status: String },
    setup(p) {
        return () => h('span', {
            class: ['inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold capitalize',
                    statusColors[p.status] || 'bg-gray-100 text-gray-600'],
        }, (p.status || '').replace(/_/g, ' '));
    },
});
</script>
