<template>
    <SahodayaAdminLayout :title="`Attendance — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="MCQ exam" description="Mark hall attendance before entering scores." />
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode || 'offline'" :results-published="!!exam.results_published" active="attendance" />

        <input v-model="searchQuery" type="search" class="field max-w-md mb-4" placeholder="Search ticket, student, or school…">

        <div class="form-section overflow-hidden !p-0">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Reg. no.</th>
                            <th>Student</th>
                            <th>School</th>
                            <th>Attendance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in filteredRegistrations" :key="r.id">
                            <td class="font-mono text-xs">{{ r.hall_ticket_no || '—' }}</td>
                            <td>{{ r.student?.name }}</td>
                            <td class="text-xs">{{ r.school?.name }}</td>
                            <td>
                                <select v-model="forms[r.id].attendance_status" class="field" :aria-label="`Attendance for ${r.student?.name}`">
                                    <option value="pending">Pending</option>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                </select>
                            </td>
                            <td><button type="button" @click="save(r)" class="link-brand text-xs">Save</button></td>
                        </tr>
                        <tr v-if="!filteredRegistrations.length">
                            <td colspan="5" class="p-6 text-center text-slate-400">No matching registrations.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';

const props = defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, exam: Object, registrations: Array });
const searchQuery = ref('');
const forms = reactive({});
for (const r of props.registrations) {
    forms[r.id] = { attendance_status: r.attendance_status || 'pending' };
}

const filteredRegistrations = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.registrations;
    return props.registrations.filter((r) =>
        [r.hall_ticket_no, r.student?.name, r.school?.name].filter(Boolean).join(' ').toLowerCase().includes(q),
    );
});

function save(r) {
    const status = forms[r.id].attendance_status;
    if (status === 'pending') return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/attendance`, {
        registration_id: r.id,
        attendance_status: status,
    }, { preserveScroll: true });
}
</script>
