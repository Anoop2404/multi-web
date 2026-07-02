<template>
    <PortalLayout
        role-label="Exam Portal"
        :title="`Attendance — ${exam.title}`"
        :subtitle="sahodaya.name"
        accent="emerald"
        :nav-items="navItems"
    >
        <div class="card card--flush">
            <form @submit.prevent="importCsv" class="p-4 border-b border-slate-100 flex flex-wrap gap-3 items-end">
                <div>
                    <label class="text-xs font-semibold text-slate-600 block mb-1">Bulk import CSV</label>
                    <input type="file" accept=".csv,text/csv" @change="e => csvFile = e.target.files[0]" class="text-xs" required>
                </div>
                <button type="submit" class="btn-secondary text-xs">Import attendance</button>
                <p class="text-xs text-slate-500 w-full">Columns: registration_id or reg_no (hall_ticket_no) or student_id, attendance_status (present/absent)</p>
            </form>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Reg. no.</th>
                        <th class="p-3">Student</th>
                        <th class="p-3">School</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in registrations" :key="r.id" class="border-t">
                        <td class="p-3 font-mono text-xs">{{ r.hall_ticket_no || '—' }}</td>
                        <td class="p-3">{{ r.student?.name }}</td>
                        <td class="p-3 text-xs text-gray-500">{{ r.school?.name }}</td>
                        <td class="p-3">
                            <select v-model="forms[r.id].attendance_status" class="field">
                                <option value="pending">Pending</option>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                            </select>
                        </td>
                        <td class="p-3">
                            <button @click="save(r)" class="text-xs font-semibold text-indigo-600">Save</button>
                        </td>
                    </tr>
                    <tr v-if="!registrations.length">
                        <td colspan="5" class="p-6 text-center text-gray-400">No registrations.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({ sahodaya: Object, exam: Object, registrations: Array });
const csvFile = ref(null);
const forms = reactive({});
for (const r of props.registrations) {
    forms[r.id] = { attendance_status: r.attendance_status || 'pending' };
}

function save(r) {
    const status = forms[r.id].attendance_status;
    if (status === 'pending') return;
    router.post(`/portal/exam/${props.sahodaya.id}/exams/${props.exam.id}/attendance`, {
        registration_id: r.id,
        attendance_status: status,
    }, { preserveScroll: true });
}

function importCsv() {
    if (!csvFile.value) return;
    router.post(`/portal/exam/${props.sahodaya.id}/exams/${props.exam.id}/attendance/import`, {
        csv: csvFile.value,
    }, { forceFormData: true, preserveScroll: true });
}

const navItems = computed(() => [
    { href: `/portal/exam/${props.sahodaya.id}`, label: 'Exams' },
    { href: `/portal/exam/${props.sahodaya.id}/exams/${props.exam.id}/attendance`, label: 'Attendance' },
]);
</script>

