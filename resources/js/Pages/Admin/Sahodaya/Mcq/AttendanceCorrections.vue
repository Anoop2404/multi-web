<template>
    <SahodayaAdminLayout :title="`Attendance corrections — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam"
                    description="Review attendance changes submitted by schools and exam-day staff after attendance was already marked." />
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode || 'offline'" :results-published="!!exam.results_published" active="attendance-corrections" />

        <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold text-amber-700">{{ pendingCount }}</p><p class="text-[10px] uppercase text-slate-500">Pending</p></div>
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold text-emerald-700">{{ approvedCount }}</p><p class="text-[10px] uppercase text-slate-500">Approved</p></div>
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold text-rose-700">{{ rejectedCount }}</p><p class="text-[10px] uppercase text-slate-500">Rejected</p></div>
        </div>

        <div class="form-section overflow-hidden !p-0">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>School</th>
                            <th>Change</th>
                            <th>Reason</th>
                            <th>Requested by</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in requests" :key="r.id">
                            <td>{{ r.student_name || '—' }} <span class="text-slate-400 text-xs font-mono">{{ r.hall_ticket_no }}</span></td>
                            <td class="text-xs">{{ r.school_name || '—' }}</td>
                            <td class="text-xs whitespace-nowrap capitalize">{{ r.previous_status || 'pending' }} → <span class="font-semibold">{{ r.requested_status }}</span></td>
                            <td class="text-xs max-w-xs">{{ r.requested_note || '—' }}</td>
                            <td class="text-xs">{{ r.requested_by || '—' }}<br><span class="text-slate-400">{{ r.created_at }}</span></td>
                            <td class="text-xs">
                                <span :class="statusClass(r.status)">{{ r.status_label }}</span>
                                <div v-if="r.status !== 'pending'" class="text-slate-400 mt-0.5">
                                    {{ r.reviewed_by }} · {{ r.reviewed_at }}
                                    <div v-if="r.review_note">{{ r.review_note }}</div>
                                </div>
                            </td>
                            <td class="text-xs whitespace-nowrap">
                                <template v-if="r.status === 'pending'">
                                    <button type="button" @click="approve(r)" class="text-green-600 font-semibold">Approve</button>
                                    <button type="button" @click="reject(r)" class="text-red-600 font-semibold ml-2">Reject</button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!requests.length">
                            <td colspan="7" class="p-6 text-center text-slate-400">No correction requests for this exam.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';

const props = defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, exam: Object, requests: { type: Array, default: () => [] } });

const pendingCount = computed(() => props.requests.filter(r => r.status === 'pending').length);
const approvedCount = computed(() => props.requests.filter(r => r.status === 'approved').length);
const rejectedCount = computed(() => props.requests.filter(r => r.status === 'rejected').length);

function statusClass(status) {
    if (status === 'approved') return 'text-emerald-700 font-semibold';
    if (status === 'rejected') return 'text-rose-700 font-semibold';
    return 'text-amber-700 font-semibold';
}

function approve(r) {
    const note = prompt('Optional note for this approval:') ?? '';
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/attendance-corrections/${r.id}/approve`, { review_note: note || null }, { preserveScroll: true });
}

function reject(r) {
    const note = prompt('Reason for rejecting (optional):') ?? '';
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/attendance-corrections/${r.id}/reject`, { review_note: note || null }, { preserveScroll: true });
}
</script>
