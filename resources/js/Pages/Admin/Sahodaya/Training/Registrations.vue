<template>
    <SahodayaAdminLayout :title="`${program.title} — Registrations`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="program.title" eyebrow="Training registrations"
                    :description="`${registrations.length} registration(s) · ${program.status}`">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}`" class="btn-secondary text-sm">
                    ← Program
                </Link>
                <Link v-if="hasFee"
                      :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/payments`"
                      class="btn-secondary text-sm">
                    Fee approvals
                </Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/qr-reports`"
                      class="btn-secondary text-sm">
                    QR reports
                </Link>
                <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations/export-pdf`"
                   class="btn-primary text-sm">
                    Download PDF
                </a>
                <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations/export`"
                   class="btn-secondary text-sm">
                    Excel
                </a>
                <a v-if="confirmedCount"
                   :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/certificates/export`"
                   class="btn-secondary text-sm">
                    Certificates (ZIP)
                </a>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <div class="card text-center">
                <p class="text-2xl font-bold">{{ registrations.length }}</p>
                <p class="text-xs text-gray-500">Total</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-amber-600">{{ countByStatus('registered') }}</p>
                <p class="text-xs text-gray-500">Registered</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-green-700">{{ confirmedCount }}</p>
                <p class="text-xs text-gray-500">Confirmed</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-slate-600">{{ countByStatus('waitlisted') }}</p>
                <p class="text-xs text-gray-500">Waitlisted</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-indigo-700">{{ qrCount }}</p>
                <p class="text-xs text-gray-500">Via QR</p>
            </div>
        </div>

        <div class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[720px] text-sm">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>School</th>
                            <th>Category</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Fee</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in registrations" :key="r.id">
                            <td>
                                <div class="font-medium">{{ r.teacher?.name || `#${r.id}` }}</div>
                                <div class="text-xs text-gray-400">{{ r.teacher?.email || '' }}</div>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <span v-if="r.teacher_created"
                                          class="text-[10px] uppercase tracking-wide text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">
                                        New teacher
                                    </span>
                                    <span v-if="r.teacher && !r.teacher.verified_at"
                                          class="text-[10px] uppercase tracking-wide text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">
                                        Unverified
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div>{{ schoolName(r) }}</div>
                                <span v-if="r.pending_school_id"
                                      class="text-[10px] uppercase tracking-wide text-rose-700 bg-rose-50 px-1.5 py-0.5 rounded">
                                    Pending school
                                </span>
                            </td>
                            <td class="text-gray-600">{{ r.teacher?.teaching_type?.label || '—' }}</td>
                            <td>
                                <span v-if="r.registration_source === 'qr'"
                                      class="text-[10px] uppercase tracking-wide text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">
                                    QR
                                </span>
                                <span v-else class="text-xs text-gray-400 capitalize">{{ r.registration_source || 'portal' }}</span>
                            </td>
                            <td class="capitalize text-gray-600">
                                {{ r.status }}
                                <span v-if="r.status === 'waitlisted' && r.waitlist_position"
                                      class="block text-[10px] text-slate-500 normal-case">
                                    Position #{{ r.waitlist_position }}
                                </span>
                            </td>
                            <td>
                                <template v-if="hasFee">
                                    <span v-if="r.fee_receipt?.status === 'approved'" class="text-xs text-green-700 font-semibold">Approved</span>
                                    <span v-else-if="r.fee_receipt?.status === 'uploaded'" class="text-xs text-amber-700 font-semibold">Pending</span>
                                    <span v-else-if="r.fee_receipt?.status === 'rejected'" class="text-xs text-red-600 font-semibold">Rejected</span>
                                    <span v-else-if="r.fee_status === 'auto_approved'" class="text-xs text-indigo-700 font-semibold">Auto approved</span>
                                    <span v-else class="text-xs text-gray-400">No proof</span>
                                </template>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end items-center gap-2 flex-wrap">
                                    <Link v-if="hasFee && r.status === 'registered'"
                                          :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/payments`"
                                          class="text-xs text-indigo-600 font-semibold">
                                        Fee approvals →
                                    </Link>
                                    <a v-if="!['cancelled','rejected'].includes(r.status)"
                                       :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations/${r.id}/id-card`"
                                       target="_blank" rel="noopener"
                                       class="text-xs text-slate-600 font-semibold">ID card ↓</a>
                                    <a v-if="hasFee && program.fee_type === 'flat'"
                                       :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations/${r.id}/invoice`"
                                       target="_blank" rel="noopener"
                                       class="text-xs text-indigo-600 font-semibold">Invoice ↓</a>
                                    <template v-if="r.status === 'confirmed'">
                                        <button type="button" @click="issueCertificate(r)"
                                                class="text-xs text-purple-600 font-semibold">
                                            {{ r.certificate ? 'Reissue cert' : 'Issue cert' }}
                                        </button>
                                        <a v-if="r.certificate"
                                           :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations/${r.id}/certificate/print`"
                                           target="_blank" rel="noopener"
                                           class="text-xs text-indigo-600 font-semibold">Print ↗</a>
                                    </template>
                                    <button v-if="canCancel(r)"
                                            type="button"
                                            @click="cancelRegistration(r)"
                                            class="text-xs text-red-600 font-semibold">
                                        Cancel
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!registrations.length">
                            <td colspan="7" class="text-center text-gray-400 py-8">No registrations yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    registrations: { type: Array, default: () => [] },
});

const hasFee = computed(() => props.program.fee_type !== 'none' && Number(props.program.fee_amount) > 0);

const confirmedCount = computed(() =>
    props.registrations.filter(r => ['confirmed', 'completed'].includes(r.status)).length,
);

const qrCount = computed(() =>
    props.registrations.filter(r => r.registration_source === 'qr').length,
);

function countByStatus(status) {
    return props.registrations.filter(r => r.status === status).length;
}

function schoolName(r) {
    return r.school?.name || r.teacher?.school_name || r.pending_school?.school_name || '—';
}

function canCancel(r) {
    return !['cancelled', 'completed'].includes(r.status);
}

function cancelRegistration(registration) {
    if (!confirm(`Cancel registration for ${registration.teacher?.name || 'this teacher'}? A waitlisted participant may be promoted.`)) {
        return;
    }
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/registrations/${registration.id}/cancel`,
        {},
        { preserveScroll: true },
    );
}

function issueCertificate(registration) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/registrations/${registration.id}/certificate`, {}, { preserveScroll: true });
}
</script>
