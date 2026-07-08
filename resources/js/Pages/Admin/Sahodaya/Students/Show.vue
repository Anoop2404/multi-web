<template>
    <SahodayaAdminLayout :title="student.name" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <div class="max-w-4xl space-y-5">
            <Link :href="schoolStudentsUrl"
                  class="inline-flex items-center gap-1 text-xs font-semibold text-[#0f3d7a] hover:text-[#041525]">
                ← Back to {{ school.name }} students
            </Link>

            <PageHeader :title="student.name" eyebrow="Student profile"
                        :description="student.reg_no ? `Reg no ${student.reg_no}` : 'Student record'">
                <template #actions>
                    <span class="status-pill text-xs capitalize"
                          :class="student.is_verified ? 'status-pill--completed' : 'status-pill--open'">
                        {{ student.is_verified ? 'Verified' : 'Pending verification' }}
                    </span>
                    <button v-if="!student.is_verified" type="button" class="btn-primary text-sm"
                            @click="verifyStudent">
                        Verify student
                    </button>
                </template>
            </PageHeader>

            <div class="grid lg:grid-cols-[12rem,1fr] gap-6">
                <div class="card flex flex-col items-center text-center !py-6">
                    <img v-if="student.photo_url" :src="student.photo_url" :alt="student.name"
                         class="w-36 h-36 rounded-2xl object-cover border border-slate-100 shadow-sm">
                    <div v-else class="w-36 h-36 rounded-2xl bg-slate-100 flex items-center justify-center text-4xl text-slate-400">
                        👤
                    </div>
                    <p class="mt-4 font-bold text-slate-900">{{ student.name }}</p>
                    <p v-if="student.class_name" class="text-sm text-slate-600 mt-1">
                        Class {{ student.class_name }}
                        <span v-if="student.category_label" class="text-slate-400">· {{ student.category_label }}</span>
                    </p>
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
                        <h3 class="section-title !mb-3">Contact & guardian</h3>
                        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div v-for="field in contactFields" :key="field.label">
                                <dt class="text-xs uppercase tracking-wide text-slate-400">{{ field.label }}</dt>
                                <dd class="font-medium text-slate-900 mt-0.5 break-words">{{ field.value }}</dd>
                            </div>
                        </dl>
                    </section>

                    <StudentPortalLoginCard
                        :student="student"
                        :provision-url="`${base}/students/${student.id}/portal-login`"
                        :reset-url="`${base}/students/${student.id}/reset-portal-password`"
                        :portal-login-url="portalLoginUrl"
                    />

                    <section class="card">
                        <h3 class="section-title !mb-3">Verification</h3>
                        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-slate-400">Verified</dt>
                                <dd class="font-medium text-slate-900 mt-0.5">
                                    {{ student.is_verified ? formatDate(student.verified_at) : 'Not yet verified' }}
                                </dd>
                            </div>
                            <div v-if="student.verified_by">
                                <dt class="text-xs uppercase tracking-wide text-slate-400">Verified by</dt>
                                <dd class="font-medium text-slate-900 mt-0.5">{{ student.verified_by }}</dd>
                            </div>
                        </dl>
                    </section>

                    <StudentSportsProfileSection :sports-profile="sportsProfile" />

                    <section v-if="student.notes" class="card">
                        <h3 class="section-title !mb-2">Notes</h3>
                        <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ student.notes }}</p>
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
import StudentPortalLoginCard from '@/Components/students/StudentPortalLoginCard.vue';
import StudentSportsProfileSection from '@/Components/students/StudentSportsProfileSection.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    student: Object,
    school: Object,
    sportsProfile: { type: Object, default: () => ({ sports_events: [], other_fest: [] }) },
    portalLoginUrl: { type: String, default: '/portal/login' },
});

const base = computed(() => `/sahodaya-admin/${props.sahodaya.id}`);
const schoolStudentsUrl = computed(() => `${base.value}/schools/${props.school.id}/students`);
const profileUrl = computed(() => `${base.value}/students/${props.student.id}`);

const identityFields = computed(() => {
    const s = props.student;
    return [
        { label: 'Student ID', value: s.reg_no || '—' },
        { label: 'Roll no', value: s.roll_number || '—' },
        { label: 'Gender', value: s.gender ? capitalize(s.gender) : '—' },
        { label: 'Date of birth', value: s.dob ? formatDate(s.dob) : '—' },
        { label: 'Blood group', value: s.blood_group || '—' },
        { label: 'Class', value: s.class_name || '—' },
        { label: 'Category', value: s.category_label || '—' },
        { label: 'House', value: s.house_name || '—' },
        { label: 'Status', value: capitalize(s.status || '—') },
        { label: 'Admission date', value: s.admission_date ? formatDate(s.admission_date) : '—' },
    ].filter((f) => f.value !== '—' || ['Student ID', 'Gender', 'Class', 'Status'].includes(f.label));
});

const contactFields = computed(() => {
    const s = props.student;
    return [
        { label: 'Student email', value: s.email },
        { label: 'Parent / guardian', value: s.parent_name },
        { label: 'Parent phone', value: s.parent_phone },
        { label: 'Parent email', value: s.parent_email },
        { label: 'Address', value: s.address },
    ].filter((f) => f.value);
});

function capitalize(value) {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function verifyStudent() {
    router.post(`${base.value}/students/${props.student.id}/verify`, {}, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['student'] }),
    });
}
</script>
