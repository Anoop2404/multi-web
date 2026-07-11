<template>
    <SahodayaAdminLayout :title="`QR teachers · ${program.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="program.title" eyebrow="Teachers created via QR"
                    description="New teacher records created from the public QR registration form">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/qr-reports`" class="btn-secondary text-sm">
                    Back to QR reports
                </Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}`" class="btn-secondary text-sm">
                    Back to program
                </Link>
            </template>
        </PageHeader>

        <div class="card overflow-hidden p-0">
            <EmptyState v-if="!teachers.length" title="No QR-created teachers"
                        description="Teachers created through the registration QR will appear here." />
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Designation</th>
                        <th>School</th>
                        <th>Verification</th>
                        <th>Registration</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in teachers" :key="row.id">
                        <td>
                            <div class="font-medium text-slate-900">{{ row.teacher_name }}</div>
                            <div class="text-xs text-gray-400">{{ row.email }} · {{ row.mobile || '—' }}</div>
                        </td>
                        <td>{{ row.designation || '—' }}</td>
                        <td>
                            <div>{{ row.school || '—' }}</div>
                            <div v-if="row.school_code" class="text-xs text-gray-400">{{ row.school_code }}</div>
                        </td>
                        <td>
                            <span v-if="row.is_verified"
                                  class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">
                                Verified
                            </span>
                            <span v-else
                                  class="text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded">
                                Unverified
                            </span>
                            <div v-if="row.verified_at" class="text-[10px] text-gray-400 mt-0.5">{{ row.verified_at }}</div>
                        </td>
                        <td class="capitalize text-sm">{{ row.status }}</td>
                        <td class="text-xs text-gray-500">{{ row.created_at || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    teachers: { type: Array, default: () => [] },
});
</script>
