<template>
    <SahodayaAdminLayout title="Submission review" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="max-w-3xl space-y-5">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/submissions`"
                  class="inline-flex items-center gap-1.5 text-xs text-[#0f3d7a] hover:underline font-semibold">
                ← Back to all schools
            </Link>

            <div class="card px-6 py-5">
                <h2 class="text-xl font-extrabold text-gray-900">{{ submission.school?.name }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">Academic Year {{ submission.academic_year }}</p>
                <p v-if="submission.registration_status" class="text-xs text-gray-400 mt-2 capitalize">
                    Registration: {{ submission.registration_status.replace(/_/g, ' ') }}
                </p>
            </div>

            <div class="space-y-3">
                <TrackReviewCard v-if="profile?.student_data_mode === 'full_records'"
                                   label="Student records"
                                   :status="submission.full_records_status"
                                   :rejection-reason="submission.full_records_rejection_reason"
                                   track="full_records"
                                   :submission-id="submission.id"
                                   :sahodaya-id="sahodaya.id" />
                <TrackReviewCard v-if="profile?.student_data_mode === 'counts_only'"
                                   label="Student counts"
                                   :status="submission.counts_status"
                                   :rejection-reason="submission.counts_rejection_reason"
                                   track="counts"
                                   :submission-id="submission.id"
                                   :sahodaya-id="sahodaya.id" />
                <TrackReviewCard v-if="profile?.teacher_registration_enabled"
                                   label="Teachers"
                                   :status="submission.teacher_status"
                                   :rejection-reason="submission.teacher_rejection_reason"
                                   track="teachers"
                                   :submission-id="submission.id"
                                   :sahodaya-id="sahodaya.id" />
            </div>

            <div v-if="profile?.student_data_mode === 'full_records'" class="card space-y-3">
                <h3 class="section-title !mb-0">School student records</h3>
                <p class="text-xs text-slate-500">{{ schoolStudents.length }} active students (from main student list)</p>
                <div class="max-h-80 overflow-y-auto border border-slate-100 rounded-xl">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 sticky top-0 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="p-2 text-left">Name</th>
                                <th class="p-2 text-left">Reg no</th>
                                <th class="p-2 text-left">Class</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in schoolStudents" :key="s.id" class="border-t">
                                <td class="p-2 font-medium">{{ s.name }}</td>
                                <td class="p-2 font-mono text-xs">{{ s.reg_no || '—' }}</td>
                                <td class="p-2 text-xs">{{ s.class || '—' }} <span v-if="s.category" class="text-slate-400">· {{ s.category }}</span></td>
                            </tr>
                            <tr v-if="!schoolStudents.length">
                                <td colspan="3" class="p-6 text-center text-slate-400 text-sm">No active students</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="submission.counts?.length" class="card space-y-2">
                <h3 class="section-title !mb-2">Student counts by class</h3>
                <div v-for="c in submission.counts" :key="c.id"
                     class="flex items-center justify-between bg-gray-50 rounded-xl px-4 py-3 text-sm">
                    <span class="font-semibold">{{ c.school_class?.name ?? c.class_category?.label ?? 'Class' }}</span>
                    <div class="flex gap-4">
                        <span class="text-blue-600 font-bold">M {{ c.male_count }}</span>
                        <span class="text-pink-600 font-bold">F {{ c.female_count }}</span>
                        <span class="font-bold">Total {{ c.total_count }}</span>
                    </div>
                </div>
                <div class="flex items-center justify-between rounded-xl bg-[#0f3d7a]/5 px-4 py-3 text-sm border-t-2 border-[#0f3d7a]/10 mt-1">
                    <span class="font-bold text-[#0f3d7a]">Total</span>
                    <div class="flex gap-4">
                        <span class="text-blue-700 font-bold">M {{ countsTotal('male_count') }}</span>
                        <span class="text-pink-700 font-bold">F {{ countsTotal('female_count') }}</span>
                        <span class="font-bold text-[#0f3d7a]">Total {{ countsTotal('total_count') }}</span>
                    </div>
                </div>
            </div>

            <div v-if="submission.teachers?.length" class="card space-y-2">
                <h3 class="section-title !mb-2">Teachers ({{ submission.teachers.length }})</h3>
                <ul class="text-sm divide-y">
                    <li v-for="t in submission.teachers" :key="t.id" class="py-2">{{ t.name }} <span v-if="t.subject" class="text-slate-400">· {{ t.subject }}</span></li>
                </ul>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import TrackReviewCard from '@/Components/sahodaya/TrackReviewCard.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String,
    pendingSchoolsCount: Number, pendingSubmissionsCount: Number, pendingPaymentsCount: Number,
    submission: Object, profile: Object, schoolStudents: { type: Array, default: () => [] },
});

function countsTotal(field) {
    return (props.submission.counts ?? []).reduce((sum, c) => sum + (Number(c[field]) || 0), 0);
}
</script>
