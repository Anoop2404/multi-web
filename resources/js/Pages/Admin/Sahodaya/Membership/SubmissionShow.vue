<template>
    <SahodayaAdminLayout title="Student Counts" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="max-w-2xl space-y-5">
            <div>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/submissions`"
                      class="inline-flex items-center gap-1.5 text-xs text-[#0f3d7a] hover:underline font-semibold mb-3">
                    ← Back to all schools
                </Link>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-5">
                    <h2 class="text-xl font-extrabold text-gray-900">{{ submission.school?.name }}</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Academic Year {{ submission.academic_year }}</p>
                    <div class="mt-4 flex items-end gap-2">
                        <p class="text-4xl font-bold text-[#0f3d7a]">{{ submission.student_total ?? 0 }}</p>
                        <p class="text-sm text-gray-500 pb-1">total students</p>
                    </div>
                    <p v-if="submission.registration_status" class="text-xs text-gray-400 mt-2 capitalize">
                        Registration: {{ submission.registration_status.replace(/_/g, ' ') }}
                    </p>
                </div>
            </div>

            <div v-if="submission.counts?.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-2">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">By category</p>
                <div v-for="c in submission.counts" :key="c.id"
                     class="flex items-center justify-between bg-gray-50 rounded-xl px-4 py-3">
                    <span class="text-sm font-semibold text-gray-800">{{ c.class_category?.label ?? 'Category' }}</span>
                    <div class="flex gap-4 text-sm">
                        <span class="text-blue-600 font-bold">M {{ c.male_count }}</span>
                        <span class="text-pink-600 font-bold">F {{ c.female_count }}</span>
                        <span class="text-gray-800 font-bold">Total {{ c.total_count }}</span>
                    </div>
                </div>
            </div>

            <p v-else class="text-sm text-gray-400 bg-white rounded-xl border border-dashed border-gray-200 p-6 text-center">
                No category breakdown submitted yet.
            </p>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    sahodaya:                Object,
    publicUrl:               { type: String, default: null },
    pendingSchoolsCount:     { type: Number, default: 0 },
    pendingSubmissionsCount: { type: Number, default: 0 },
    pendingPaymentsCount:    { type: Number, default: 0 },
    submission:              Object,
});
</script>
