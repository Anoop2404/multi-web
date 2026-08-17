<template>
    <SahodayaAdminLayout title="Credentials" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="approvedSchoolsCount"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-6 max-w-4xl">
            <PageHeader
                title="Credentials"
                eyebrow="Membership"
                description="Everywhere a login or password for this Sahodaya's own staff, schools, students, or teachers can be viewed or reset."
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="card space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="section-title !mb-0">Sahodaya staff</h3>
                        <span class="status-pill status-pill--completed text-xs">{{ ownStaffCount }}</span>
                    </div>
                    <p class="text-sm text-slate-500">Your own team's accounts — judges, exam staff, mark-entry coordinators, office staff. Each can be reset individually.</p>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/users`" class="btn-secondary text-sm inline-flex">Manage staff logins →</Link>
                </div>

                <div class="card space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="section-title !mb-0">Schools</h3>
                        <span class="status-pill text-xs" :class="schoolsWithoutLoginCount ? 'status-pill--open' : 'status-pill--completed'">
                            {{ schoolsWithoutLoginCount }} without login
                        </span>
                    </div>
                    <p class="text-sm text-slate-500">
                        {{ approvedSchoolsCount }} verified school{{ approvedSchoolsCount === 1 ? '' : 's' }}. Reset or resend a school's primary login individually, or select multiple to reset/resend in bulk.
                    </p>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/schools`" class="btn-secondary text-sm inline-flex">Manage school logins →</Link>
                </div>

                <div class="card space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="section-title !mb-0">Students</h3>
                        <span class="status-pill text-xs" :class="studentsWithoutLoginCount ? 'status-pill--open' : 'status-pill--completed'">
                            {{ studentsWithoutLoginCount }} without login
                        </span>
                    </div>
                    <p class="text-sm text-slate-500">View or reset a student's portal password from their profile page, reached via their school's roster.</p>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/schools`" class="btn-secondary text-sm inline-flex">Browse schools →</Link>
                </div>

                <div class="card space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="section-title !mb-0">Teachers</h3>
                        <span class="status-pill text-xs" :class="teachersWithoutLoginCount ? 'status-pill--open' : 'status-pill--completed'">
                            {{ teachersWithoutLoginCount }} without login
                        </span>
                    </div>
                    <p class="text-sm text-slate-500">
                        {{ unverifiedTeachersCount }} pending verification. Create or reset a teacher's portal login from their profile page.
                    </p>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/teachers/verification`" class="btn-secondary text-sm inline-flex">Browse teachers →</Link>
                </div>
            </div>

            <div class="card bg-slate-50/80 border-slate-200">
                <p class="text-xs text-slate-500">
                    Password resets generate a new temporary password and email it (schools, teachers) or show it once on-screen for you to relay (staff, students).
                    A record of every reset is kept in the <Link :href="`/sahodaya-admin/${sahodaya.id}/audit-logs`" class="link-brand">activity log</Link>.
                </p>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

defineProps({
    sahodaya: Object, publicUrl: String,
    approvedSchoolsCount: Number, pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number, pendingPaymentsCount: Number,
    ownStaffCount: { type: Number, default: 0 },
    schoolsWithoutLoginCount: { type: Number, default: 0 },
    studentsWithoutLoginCount: { type: Number, default: 0 },
    teachersWithoutLoginCount: { type: Number, default: 0 },
    unverifiedTeachersCount: { type: Number, default: 0 },
});
</script>
