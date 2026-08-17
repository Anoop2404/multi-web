<template>
    <SahodayaAdminLayout :title="teacher.name" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <div class="max-w-4xl space-y-5">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}`"
                  class="inline-flex items-center gap-1 text-xs font-semibold text-[#0f3d7a] hover:text-[#041525]">
                ← Back to {{ school.name }}
            </Link>

            <PageHeader :title="teacher.name" eyebrow="Teacher profile"
                        :description="teacher.designation || 'Teacher record'">
                <template #actions>
                    <span class="status-pill text-xs capitalize"
                          :class="teacher.is_verified ? 'status-pill--completed' : 'status-pill--open'">
                        {{ teacher.is_verified ? 'Verified' : 'Pending verification' }}
                    </span>
                    <button v-if="!teacher.is_verified" type="button" class="btn-primary text-sm"
                            @click="verifyTeacher">
                        Verify teacher
                    </button>
                </template>
            </PageHeader>

            <div class="grid lg:grid-cols-[12rem,1fr] gap-6">
                <div class="card flex flex-col items-center text-center !py-6">
                    <img v-if="teacher.photo_url" :src="teacher.photo_url" :alt="teacher.name"
                         class="w-36 h-36 rounded-2xl object-cover border border-slate-100 shadow-sm">
                    <div v-else class="w-36 h-36 rounded-2xl bg-slate-100 flex items-center justify-center text-4xl text-slate-400">
                        👤
                    </div>
                    <p class="mt-4 font-bold text-slate-900">{{ teacher.name }}</p>
                    <p v-if="teacher.designation" class="text-sm text-slate-600 mt-1">{{ teacher.designation }}</p>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}`"
                          class="text-xs font-semibold text-indigo-600 hover:underline mt-3">
                        {{ school.name }}
                    </Link>
                </div>

                <div class="space-y-5">
                    <section class="card">
                        <h3 class="section-title !mb-3">Identity</h3>
                        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div v-for="field in identityFields" :key="field.label">
                                <dt class="text-xs uppercase tracking-wide text-slate-400">{{ field.label }}</dt>
                                <dd class="font-medium text-slate-900 mt-0.5">{{ field.value }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section v-if="contactFields.length" class="card">
                        <h3 class="section-title !mb-3">Contact</h3>
                        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div v-for="field in contactFields" :key="field.label">
                                <dt class="text-xs uppercase tracking-wide text-slate-400">{{ field.label }}</dt>
                                <dd class="font-medium text-slate-900 mt-0.5 break-words">{{ field.value }}</dd>
                            </div>
                        </dl>
                    </section>

                    <TeacherPortalLoginCard
                        :teacher="teacher"
                        :provision-url="`${base}/teachers/${teacher.id}/portal-login`"
                        :reset-url="`${base}/teachers/${teacher.id}/reset-portal-password`"
                        :reveal-url="`${base}/teachers/${teacher.id}/reveal-portal-password`"
                        :portal-login-url="portalLoginUrl"
                    />

                    <section class="card">
                        <h3 class="section-title !mb-3">Verification</h3>
                        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-slate-400">Verified</dt>
                                <dd class="font-medium text-slate-900 mt-0.5">
                                    {{ teacher.is_verified ? formatDate(teacher.verified_at) : 'Not yet verified' }}
                                </dd>
                            </div>
                            <div v-if="teacher.verified_by">
                                <dt class="text-xs uppercase tracking-wide text-slate-400">Verified by</dt>
                                <dd class="font-medium text-slate-900 mt-0.5">{{ teacher.verified_by }}</dd>
                            </div>
                            <div v-if="teacher.rejection_reason">
                                <dt class="text-xs uppercase tracking-wide text-slate-400">Rejection reason</dt>
                                <dd class="font-medium text-slate-900 mt-0.5">{{ teacher.rejection_reason }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import TeacherPortalLoginCard from '@/Components/teachers/TeacherPortalLoginCard.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    teacher: Object,
    school: Object,
    portalLoginUrl: { type: String, default: '/portal/login' },
});

const base = computed(() => `/sahodaya-admin/${props.sahodaya.id}`);

const identityFields = computed(() => {
    const t = props.teacher;
    return [
        { label: 'Employee code', value: t.employee_code || '—' },
        { label: 'Reg no', value: t.reg_no || '—' },
        { label: 'Gender', value: t.gender ? capitalize(t.gender) : '—' },
        { label: 'Date of birth', value: t.dob ? formatDate(t.dob) : '—' },
        { label: 'Designation', value: t.designation || '—' },
        { label: 'Subjects', value: (t.subjects || []).join(', ') || '—' },
        { label: 'Qualification', value: t.qualification || '—' },
        { label: 'Experience (yrs)', value: t.experience_years ?? '—' },
        { label: 'Date of joining', value: t.date_of_joining ? formatDate(t.date_of_joining) : '—' },
        { label: 'Status', value: capitalize(t.status || '—') },
    ].filter((f) => f.value !== '—' || ['Designation', 'Status'].includes(f.label));
});

const contactFields = computed(() => {
    const t = props.teacher;
    return [
        { label: 'Email', value: t.email },
        { label: 'Mobile', value: t.mobile },
        { label: 'Address', value: t.address },
    ].filter((f) => f.value);
});

function capitalize(value) {
    return String(value).charAt(0).toUpperCase() + String(value).slice(1);
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function verifyTeacher() {
    router.post(`${base.value}/teachers/${props.teacher.id}/verify`, {}, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['teacher'] }),
    });
}
</script>
