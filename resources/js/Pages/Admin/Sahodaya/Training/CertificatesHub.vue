<template>
    <SahodayaAdminLayout :title="`Certificates - ${program.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`Certificates: ${program.title}`" eyebrow="Teacher Training"
                    :description="`${stats.total} confirmed participant(s) · ${stats.issued} certificate(s) issued`">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}`" class="btn-secondary text-sm">
                    ← Back to Program
                </Link>
                <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/certificates/export`"
                   class="btn-secondary text-sm flex items-center gap-1 font-semibold">
                    📦 Download All (ZIP)
                </a>
            </template>
        </PageHeader>

        <InlineAlert :message="certAlert" type="success" @dismiss="certAlert = ''" />

        <!-- STATS OVERVIEW -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="card p-4 border-l-4 border-indigo-500">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Confirmed Participants</p>
                <p class="text-2xl font-black text-slate-900 mt-1">{{ stats.total }}</p>
            </div>
            <div class="card p-4 border-l-4 border-emerald-500">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Eligible for Certificate</p>
                <p class="text-2xl font-black text-emerald-600 mt-1">{{ stats.eligible }}</p>
            </div>
            <div class="card p-4 border-l-4 border-blue-500">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Certificates Issued</p>
                <p class="text-2xl font-black text-blue-600 mt-1">{{ stats.issued }}</p>
            </div>
            <div class="card p-4 border-l-4 border-purple-500">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Emails Dispatched</p>
                <p class="text-2xl font-black text-purple-600 mt-1">{{ stats.emailed }}</p>
            </div>
        </div>

        <!-- ACTIONS BAR: TEST EMAIL & BULK DISPATCH -->
        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <!-- TEST EMAIL PANEL -->
            <div class="card p-5 bg-gradient-to-br from-indigo-50/50 to-white border border-indigo-100 rounded-2xl shadow-xs">
                <h3 class="text-sm font-extrabold text-indigo-950 flex items-center gap-2">
                    <span>📧 Send Test Certificate Email</span>
                </h3>
                <p class="text-xs text-slate-600 mt-1">
                    Send a test certificate PDF email to your inbox to check design, layout, and PDF attachment formatting before broadcasting to all teachers.
                </p>
                <form @submit.prevent="sendTestEmail" class="mt-4 flex gap-2">
                    <input v-model="testEmailForm.test_email" type="email" required placeholder="Enter test email address..." class="field text-sm flex-1" />
                    <button type="submit" :disabled="testEmailForm.processing" class="btn-primary text-xs font-bold shrink-0">
                        {{ testEmailForm.processing ? 'Sending...' : 'Send Test Email' }}
                    </button>
                </form>
            </div>

            <!-- BULK DISPATCH PANEL -->
            <div class="card p-5 bg-gradient-to-br from-emerald-50/50 to-white border border-emerald-100 rounded-2xl shadow-xs">
                <h3 class="text-sm font-extrabold text-emerald-950 flex items-center gap-2">
                    <span>✉️ Bulk Email Certificates</span>
                </h3>
                <p class="text-xs text-slate-600 mt-1">
                    Email official certificate PDFs as attachments directly to all confirmed eligible teachers.
                </p>
                <div class="mt-4 flex flex-wrap gap-2 items-center">
                    <button type="button" @click="bulkSendAll" :disabled="isBulkSending || isBatchRunning" class="btn-primary bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2.5 rounded-xl">
                        {{ isBulkSending ? 'Sending Emails...' : '✉️ Email Certificates to All Teachers' }}
                    </button>
                    <button v-if="selectedIds.length" type="button" @click="bulkSendSelected" :disabled="isBulkSending || isBatchRunning" class="btn-secondary text-xs font-bold px-3 py-2.5 rounded-xl border border-indigo-200 text-indigo-700 bg-indigo-50">
                        Email Selected ({{ selectedIds.length }})
                    </button>
                </div>

                <!-- Bulk-send batch progress -->
                <div v-if="jobStatus" class="mt-4 pt-4 border-t border-emerald-100 text-sm">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <p class="font-semibold capitalize text-emerald-950">
                            Emailing certificates: {{ jobStatus.status.replace('_', ' ') }}
                        </p>
                        <span class="text-xs text-slate-500 tabular-nums">{{ jobStatus.processed_count }} / {{ jobStatus.total_count }}</span>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                        <div class="bg-emerald-600 h-2 rounded-full transition-all"
                             :style="{ width: (jobStatus.total_count ? (jobStatus.processed_count / jobStatus.total_count * 100) : 0) + '%' }"></div>
                    </div>
                    <p v-if="['completed', 'completed_with_errors'].includes(jobStatus.status)" class="mt-2 text-xs text-slate-600">
                        {{ jobStatus.succeeded_count }} succeeded<span v-if="jobStatus.failed_count"> · {{ jobStatus.failed_count }} failed</span>
                    </p>
                    <p v-if="jobStatus.error" class="mt-2 text-xs text-red-600">{{ jobStatus.error }}</p>
                </div>
            </div>
        </div>

        <!-- TABLE LISTING OF PARTICIPANTS AND CERTIFICATE LOG -->
        <div class="card p-4">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Teacher Certificate Log</h3>
                    <p class="text-xs text-slate-500">Review certificate issuance, verification codes, and email delivery status.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input v-model="searchQuery" type="text" placeholder="Search teacher or school..." class="field text-xs w-64">
                </div>
            </div>

            <div class="overflow-x-auto border border-slate-100 rounded-xl">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-3 w-10 text-center">
                                <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="rounded">
                            </th>
                            <th class="py-3 px-3">Teacher Name & Designation</th>
                            <th class="py-3 px-3">School Name</th>
                            <th class="py-3 px-3">Attendance</th>
                            <th class="py-3 px-3">Certificate Code</th>
                            <th class="py-3 px-3">Email Delivery Status</th>
                            <th class="py-3 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="r in filteredRows" :key="r.id" class="hover:bg-slate-50/60 transition">
                            <td class="py-2.5 px-3 text-center">
                                <input type="checkbox" :value="r.id" v-model="selectedIds" class="rounded">
                            </td>
                            <td class="py-2.5 px-3">
                                <p class="font-bold text-slate-900">{{ r.teacher_name }}</p>
                                <p class="text-[11px] text-slate-500">{{ r.teacher_designation }} · {{ r.teacher_email || 'No email' }}</p>
                            </td>
                            <td class="py-2.5 px-3 text-slate-700 font-medium">{{ r.school_name }}</td>
                            <td class="py-2.5 px-3">
                                <span class="font-bold px-2 py-0.5 rounded text-[11px]" :class="r.is_eligible ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                                    {{ r.present_days }} day(s) {{ r.is_eligible ? '✓' : '✗' }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-[11px]">
                                <span v-if="r.verification_uuid" class="text-indigo-700 font-semibold">{{ r.verification_uuid.substring(0, 8) }}...</span>
                                <span v-else class="text-slate-400">Not Issued</span>
                            </td>
                            <td class="py-2.5 px-3">
                                <span v-if="r.email_status === 'sent'" class="inline-flex items-center gap-1 font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full text-[10px]">
                                    Sent ✅ ({{ r.email_sent_at }})
                                </span>
                                <span v-else-if="r.email_status === 'pending'" class="inline-flex items-center gap-1 font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-full text-[10px]">
                                    Issued · Email Pending ⏳
                                </span>
                                <span v-else class="inline-flex items-center gap-1 font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full text-[10px]">
                                    Not Issued
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a v-if="r.certificate_id" :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/certificates/${r.id}/download-pdf`" target="_blank" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded text-[11px] border border-slate-200">
                                        📄 PDF
                                    </a>
                                    <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations/${r.id}/certificate/preview`" target="_blank" class="px-2 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold rounded text-[11px]">
                                        👁 Preview
                                    </a>
                                    <button type="button" @click="sendSingleEmail(r)" class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold rounded text-[11px]">
                                        ✉️ Email
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!filteredRows.length">
                            <td colspan="7" class="py-8 text-center text-slate-400">No matching participant records found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent bulk-send runs -->
        <div v-if="recentBatches.length" class="card p-4 mt-8">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Recent send runs</h3>
            <div class="divide-y divide-slate-100 text-xs">
                <div v-for="b in recentBatches" :key="b.id" class="py-2 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <span class="font-medium text-slate-800">Email</span>
                        <span class="text-slate-500"> · {{ b.scope_description ?? 'All confirmed teachers' }}</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-slate-500">{{ b.succeeded_count }}/{{ b.total_count }}<span v-if="b.failed_count" class="text-red-600"> ({{ b.failed_count }} failed)</span></span>
                        <span class="px-2 py-0.5 rounded font-semibold capitalize"
                              :class="{
                                  'bg-green-100 text-green-800': b.status === 'completed',
                                  'bg-amber-100 text-amber-800': b.status === 'completed_with_errors',
                                  'bg-red-100 text-red-800': b.status === 'failed',
                                  'bg-slate-100 text-slate-600': !['completed', 'completed_with_errors', 'failed'].includes(b.status),
                              }">
                            {{ b.status.replace('_', ' ') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import InlineAlert from '@/Components/ui/InlineAlert.vue';
import { useConfirm } from '@/composables/useConfirm';

const certAlert = ref('');

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    rows: Array,
    stats: Object,
    recentBatches: { type: Array, default: () => [] },
});

const page = usePage();
const base = `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}`;

const searchQuery = ref('');
const selectedIds = ref([]);
const isBulkSending = ref(false);
const { confirm } = useConfirm();

// Bulk-send batch progress — same dispatch -> flash key -> poll /progress pattern as
// Events/Certificates.vue's render/cache batch UX.
const jobStatus = ref(null);
let pollTimer = null;

function startPolling(batchId) {
    if (!batchId) return;
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(async () => {
        const res = await fetch(`${base}/certificates/batches/${batchId}/progress`, { headers: { Accept: 'application/json' } });
        jobStatus.value = await res.json();
        if (['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(jobStatus.value?.status)) {
            clearInterval(pollTimer);
            if (['completed', 'completed_with_errors'].includes(jobStatus.value.status)) {
                router.reload({ only: ['rows', 'stats', 'recentBatches'] });
            }
        }
    }, 3000);
}

const isBatchRunning = computed(() => jobStatus.value && !['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(jobStatus.value.status));

onMounted(() => {
    const lastBatch = props.recentBatches?.[0];
    if (lastBatch && !['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(lastBatch.status)) {
        startPolling(lastBatch.id);
    }
});
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer); });

const testEmailForm = useForm({
    test_email: '',
});

const filteredRows = computed(() => {
    if (!searchQuery.value.trim()) return props.rows;
    const q = searchQuery.value.toLowerCase();
    return props.rows.filter(r =>
        r.teacher_name.toLowerCase().includes(q) ||
        r.school_name.toLowerCase().includes(q) ||
        (r.teacher_email && r.teacher_email.toLowerCase().includes(q))
    );
});

const isAllSelected = computed(() => {
    return filteredRows.value.length > 0 && selectedIds.value.length === filteredRows.value.length;
});

function toggleSelectAll() {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = filteredRows.value.map(r => r.id);
    }
}

function sendTestEmail() {
    testEmailForm.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/certificates/send-test-email`, {
        preserveScroll: true,
        onSuccess: () => {
            certAlert.value = `Test certificate email successfully sent to ${testEmailForm.test_email}! Check your inbox.`;
        },
    });
}

async function bulkSendAll() {
    if (!(await confirm({ message: `Send official certificate PDF emails to all ${props.stats.eligible} eligible teacher(s)?`, destructive: false }))) return;
    isBulkSending.value = true;
    router.post(`${base}/certificates/bulk-send-email`, {}, {
        preserveScroll: true,
        onSuccess: () => startPolling(page.props.flash?.certificate_batch_id),
        onFinish: () => { isBulkSending.value = false; },
    });
}

async function bulkSendSelected() {
    if (!selectedIds.value.length) return;
    if (!(await confirm({ message: `Send certificate PDF emails to ${selectedIds.value.length} selected teacher(s)?`, destructive: false }))) return;
    isBulkSending.value = true;
    router.post(`${base}/certificates/bulk-send-email`, {
        registration_ids: selectedIds.value,
    }, {
        preserveScroll: true,
        onSuccess: () => startPolling(page.props.flash?.certificate_batch_id),
        onFinish: () => { isBulkSending.value = false; },
    });
}

async function sendSingleEmail(row) {
    if (!(await confirm({ message: `Send certificate PDF email to ${row.teacher_name}?`, destructive: false }))) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/certificates/${row.id}/send-single-email`, {}, {
        preserveScroll: true,
    });
}
</script>
