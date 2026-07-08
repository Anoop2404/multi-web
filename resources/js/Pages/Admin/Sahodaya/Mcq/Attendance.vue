<template>
    <SahodayaAdminLayout :title="`Attendance — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam" description="Mark hall attendance before entering scores." />
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode || 'offline'" :results-published="!!exam.results_published" active="attendance" />

        <div v-if="summary" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold">{{ summary.total }}</p><p class="text-[10px] uppercase text-slate-500">Total</p></div>
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold">{{ summary.pending }}</p><p class="text-[10px] uppercase text-slate-500">Pending</p></div>
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold text-emerald-700">{{ summary.present }}</p><p class="text-[10px] uppercase text-slate-500">Present</p></div>
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold text-red-700">{{ summary.absent }}</p><p class="text-[10px] uppercase text-slate-500">Absent</p></div>
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold">{{ summary.marks_entered }}</p><p class="text-[10px] uppercase text-slate-500">Marks entered</p></div>
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold">{{ summary.not_marked }}</p><p class="text-[10px] uppercase text-slate-500">Present, not marked</p></div>
        </div>

        <input v-model="searchQuery" type="search" class="field max-w-md mb-4" placeholder="Search ticket, student, or school…">

        <div class="flex flex-wrap gap-2 mb-4">
            <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/attendance/export`" class="btn-secondary text-sm">Export attendance CSV ↓</a>
            <form @submit.prevent="importAttendance" class="flex flex-wrap gap-2 items-center">
                <input ref="importFile" type="file" accept=".csv,.txt" class="text-sm" required>
                <button type="submit" class="btn-secondary text-sm">Import attendance CSV</button>
            </form>
            <p class="text-xs text-slate-500 w-full">CSV format: hall_ticket_no, present|absent</p>
        </div>

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

const props = defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, exam: Object, registrations: Array, summary: Object });
const searchQuery = ref('');
const importFile = ref(null);
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

function importAttendance() {
    const file = importFile.value?.files?.[0];
    if (!file) return;
    const form = new FormData();
    form.append('file', file);
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/attendance/import`, form, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { if (importFile.value) importFile.value.value = ''; },
    });
}
</script>
