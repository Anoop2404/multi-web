<template>
    <SahodayaAdminLayout :title="pageTitle" :sahodaya="sahodaya" :publicUrl="publicUrl" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Academic Results"
                    description="Review and verify marksheets for Full A1 Achievers submitted by member schools.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/masters`" class="btn-secondary text-sm">Masters</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/reports`" class="btn-secondary text-sm">Reports</Link>
            </template>
        </PageHeader>

        <BoardResultsVerificationSubNav :sahodayaId="sahodaya.id" active="full-a1" :currentClass="selectedClass" />

        <p v-if="selectedClass" class="text-sm -mt-2 mb-4">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/verification/a1?class=${selectedClass === 12 ? 10 : 12}&status=${filters.status}`" class="text-indigo-600 hover:underline font-medium">
                Switch to {{ selectedClass === 12 ? 'Class X' : 'Class XII' }} →
            </Link>
        </p>

        <div class="flex flex-wrap gap-2 mb-4">
            <Link v-for="(label, value) in statusOptions" :key="value"
                  :href="`/sahodaya-admin/${sahodaya.id}/board-results/verification/a1?class=${selectedClass}&status=${value}`"
                  class="px-3 py-1.5 rounded-lg text-sm font-semibold border"
                  :class="filters.status === value ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200 text-slate-600 hover:bg-slate-50'">
                {{ label }}
            </Link>
        </div>

        <div class="card p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4 w-1/5">School</th>
                            <th class="py-3 px-4 w-1/5">Student</th>
                            <th class="py-3 px-4 w-1/4">Subjects &amp; Marks</th>
                            <th class="py-3 px-4 text-center">Proof / Marksheet</th>
                            <th class="py-3 px-4">Verification</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="t in toppers.data" :key="t.id" class="hover:bg-slate-50/50">
                            <td class="py-3 px-4 text-xs font-semibold text-slate-700 max-w-[200px] truncate" :title="schoolNames[t.tenant_id]">
                                {{ schoolNames[t.tenant_id] || t.tenant_id }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900">{{ t.name }}</div>
                                <div class="text-xs text-slate-500 font-mono mt-0.5">{{ t.roll_no || 'No Roll No' }}</div>
                                <div v-if="selectedClass === 12 && t.stream" class="text-xs font-semibold text-indigo-600 mt-1">
                                    {{ t.stream }}
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex flex-wrap gap-1.5">
                                    <div v-for="(marks, subject) in (t.subject_marks || {})" :key="subject"
                                         class="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-slate-100 border border-slate-200">
                                        <span class="text-[10px] font-bold text-slate-700 truncate max-w-[120px]" :title="subject">{{ subject }}</span>
                                        <span class="text-[10px] font-extrabold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">{{ marks }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a v-if="t.marksheet_url" :href="t.marksheet_url" target="_blank" class="font-bold text-[11px] text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1.5 rounded-lg border border-emerald-200 inline-flex items-center gap-1 transition">
                                    📄 View PDF ↗
                                </a>
                                <span v-else class="text-xs text-slate-400 italic">No File</span>
                            </td>
                            <td class="py-3 px-4">
                                <span v-if="t.verification_status === 'verified'" class="font-bold px-2 py-0.5 rounded-full text-[11px] bg-green-100 text-green-700">Verified ✅</span>
                                <span v-else-if="t.verification_status === 'rejected'" class="font-bold px-2 py-0.5 rounded-full text-[11px] bg-red-100 text-red-700" :title="t.rejection_reason">Rejected ❌</span>
                                <span v-else class="font-bold px-2 py-0.5 rounded-full text-[11px] bg-amber-100 text-amber-800">Pending ⏳</span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button v-if="t.verification_status !== 'verified'" type="button" @click="verifyTopper(t)" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] rounded-lg transition shadow-sm">
                                        Approve
                                    </button>
                                    <button v-if="t.verification_status !== 'rejected'" type="button" @click="rejectTopper(t)" class="px-3 py-1.5 border border-red-300 text-red-700 hover:bg-red-50 font-bold text-[11px] rounded-lg transition">
                                        Reject
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!toppers.data.length">
                            <td colspan="6" class="text-center py-12 text-slate-400 text-sm">
                                No Full A1 Achievers found for this view.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div v-if="toppers.links && toppers.links.length > 3" class="px-4 py-3 border-t border-slate-100 bg-slate-50 flex items-center justify-center gap-1">
                <Link v-for="link in toppers.links" :key="link.label" :href="link.url || '#'" 
                      class="px-3 py-1 rounded text-sm"
                      :class="link.active ? 'bg-indigo-600 text-white font-bold' : 'text-slate-600 hover:bg-slate-200'"
                      v-html="link.label" />
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import BoardResultsVerificationSubNav from '@/Components/BoardResults/BoardResultsVerificationSubNav.vue';
import { useConfirm } from '@/composables/useConfirm';

const { prompt } = useConfirm();

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    toppers: Object,
    schoolNames: Object,
    filters: Object,
    selectedClass: Number,
    statusOptions: Object,
});

const pageTitle = computed(() => `Verify Full A1 Achievers - Class ${props.selectedClass === 12 ? 'XII' : 'X'}`);

function verifyTopper(t) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/board-results/${t.board_result_id}/toppers/${t.id}/verify-marksheet`, {}, { preserveScroll: true });
}

async function rejectTopper(t) {
    const reason = await prompt({ message: `Rejection reason for ${t.name}:`, inputMultiline: true });
    if (reason === null) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/board-results/${t.board_result_id}/toppers/${t.id}/reject-marksheet`, {
        reason: reason || 'Marksheet verification failed.'
    });
}
</script>
