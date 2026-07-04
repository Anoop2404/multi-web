<template>
    <SahodayaAdminLayout title="Annual submissions" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader
            title="Annual submissions"
            eyebrow="Membership"
            description="Review student records, counts, and teachers submitted by schools before membership payment."
        >
            <template #actions>
                <button v-if="pendingReviewCount" type="button" @click="approveAllPending" :disabled="approveAllForm.processing"
                        class="btn-secondary text-sm">
                    Approve all pending ({{ pendingReviewCount }})
                </button>
                <button v-if="selectedIds.length" type="button" @click="bulkApprove" :disabled="bulkForm.processing"
                        class="btn-primary text-sm">
                    Approve selected ({{ selectedIds.length }})
                </button>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`" class="btn-secondary text-sm">Payments →</Link>
            </template>
        </PageHeader>

        <div v-if="submissions.length" class="space-y-3">
            <div v-for="s in submissions" :key="s.id"
                 class="flex items-center justify-between gap-4 bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 hover:border-[#0f3d7a]/30 hover:shadow transition group">
                <label v-if="s.pending_tracks?.length" class="flex items-center gap-3 shrink-0 cursor-pointer">
                    <input v-model="selectedIds" type="checkbox" :value="s.id" class="rounded border-gray-300">
                </label>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/submissions/${s.id}`"
                      class="flex items-center gap-4 flex-1 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-[#eff6ff] flex items-center justify-center font-bold text-[#0f3d7a] text-base shrink-0">
                        {{ s.school?.name?.charAt(0) }}
                    </div>
                    <div>
                        <p class="font-bold text-gray-900">{{ s.school?.name }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ s.academic_year }}</p>
                    </div>
                </Link>
                <div class="flex items-center gap-4 shrink-0 text-right">
                    <div>
                        <p class="text-2xl font-bold text-[#0f3d7a]">{{ s.student_total ?? 0 }}</p>
                        <p class="text-[11px] text-gray-400 uppercase tracking-wide">students</p>
                    </div>
                    <span v-if="s.pending_tracks?.length"
                          class="text-xs px-2 py-1 rounded-full font-semibold bg-amber-100 text-amber-800">
                        {{ s.pending_tracks.length }} pending review
                    </span>
                    <span v-if="s.registration_status"
                          class="text-xs px-2 py-1 rounded-full font-medium capitalize bg-gray-100 text-gray-600">
                        {{ s.registration_status.replace(/_/g, ' ') }}
                    </span>
                    <span class="text-gray-300 group-hover:text-[#0f3d7a] transition">→</span>
                </div>
            </div>
        </div>

        <div v-else class="bg-white rounded-2xl border border-dashed border-gray-200 p-16 text-center">
            <div class="text-5xl mb-3">👨‍🎓</div>
            <p class="text-gray-600 font-semibold">No registration data yet</p>
            <p class="text-sm text-gray-400 mt-1">Submissions appear when schools begin annual registration.</p>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object, publicUrl: String,
    pendingSchoolsCount: Number, pendingSubmissionsCount: Number, pendingPaymentsCount: Number,
    submissions: Array,
});

const { confirm } = useConfirm();
const selectedIds = ref([]);
const bulkForm = useForm({ submission_ids: [] });
const approveAllForm = useForm({});

const pendingReviewCount = computed(() =>
    (props.submissions ?? []).reduce((sum, s) => sum + (s.pending_tracks?.length ?? 0), 0)
);

async function bulkApprove() {
    if (!selectedIds.value.length) return;
    if (!await confirm({ title: 'Approve selected', message: `Approve all pending tracks for ${selectedIds.value.length} submission(s)?`, confirmLabel: 'Approve' })) return;
    bulkForm.submission_ids = [...selectedIds.value];
    bulkForm.post(`/sahodaya-admin/${props.sahodaya.id}/membership/submissions/bulk-approve`, {
        onSuccess: () => { selectedIds.value = []; },
    });
}

async function approveAllPending() {
    if (!await confirm({ title: 'Approve all pending', message: `Approve all ${pendingReviewCount.value} pending track(s) across every school?`, confirmLabel: 'Approve all' })) return;
    approveAllForm.post(`/sahodaya-admin/${props.sahodaya.id}/membership/submissions/approve-all-pending`);
}
</script>
