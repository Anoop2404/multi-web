<template>
    <SahodayaAdminLayout title="Student Counts" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <p class="text-sm text-gray-500 mb-4">
            Read-only view of student totals submitted by schools. Membership is completed after payment is verified under
            <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`" class="text-[#0f3d7a] font-semibold hover:underline">Payments</Link>.
        </p>

        <div v-if="submissions.length" class="space-y-3">
            <Link v-for="s in submissions" :key="s.id"
                  :href="`/sahodaya-admin/${sahodaya.id}/membership/submissions/${s.id}`"
                  class="flex items-center justify-between gap-4 bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 hover:border-[#0f3d7a]/30 hover:shadow transition group">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-[#eff6ff] flex items-center justify-center font-bold text-[#0f3d7a] text-base shrink-0">
                        {{ s.school?.name?.charAt(0) }}
                    </div>
                    <div>
                        <p class="font-bold text-gray-900">{{ s.school?.name }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ s.academic_year }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 shrink-0 text-right">
                    <div>
                        <p class="text-2xl font-bold text-[#0f3d7a]">{{ s.student_total ?? 0 }}</p>
                        <p class="text-[11px] text-gray-400 uppercase tracking-wide">students</p>
                    </div>
                    <span v-if="s.registration_status"
                          class="text-xs px-2 py-1 rounded-full font-medium capitalize bg-gray-100 text-gray-600">
                        {{ s.registration_status.replace(/_/g, ' ') }}
                    </span>
                    <span class="text-gray-300 group-hover:text-[#0f3d7a] transition">→</span>
                </div>
            </Link>
        </div>

        <div v-else class="bg-white rounded-2xl border border-dashed border-gray-200 p-16 text-center">
            <div class="text-5xl mb-3">👨‍🎓</div>
            <p class="text-gray-600 font-semibold">No registration data yet</p>
            <p class="text-sm text-gray-400 mt-1">Counts appear when schools begin annual registration.</p>
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
    submissions:             { type: Array, default: () => [] },
});
</script>
