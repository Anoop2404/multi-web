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
                <p class="text-xs text-slate-500 w-full">Columns: registration_id or reg_no (hall_ticket_no) or student_id, attendance_status (present/absent/malpractice/withheld)</p>
            </form>
            <p v-if="!isTrustedReviewer" class="text-xs text-slate-500 px-4 pt-3">
                Changing attendance that has already been marked will be sent to the Sahodaya for approval.
            </p>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Reg. no.</th>
                        <th class="p-3">Student</th>
                        <th class="p-3">School</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Note</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in registrations" :key="r.id" class="border-t">
                        <td class="p-3 font-mono text-xs">{{ r.hall_ticket_no || '—' }}</td>
                        <td class="p-3">{{ r.student?.name }}</td>
                        <td class="p-3 text-xs text-gray-500">{{ r.school?.name }}</td>
                        <td class="p-3">
                            <select v-model="forms[r.id].attendance_status" class="field" :disabled="!!r.pending_correction_status">
                                <option value="pending">Pending</option>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="malpractice">Malpractice</option>
                                <option value="withheld">Withheld</option>
                            </select>
                        </td>
                        <td class="p-3">
                            <input v-if="['malpractice','withheld'].includes(forms[r.id].attendance_status)"
                                   v-model="forms[r.id].attendance_note" type="text" class="field text-xs"
                                   :disabled="!!r.pending_correction_status"
                                   placeholder="Reason (required)">
                            <span v-else class="text-gray-300 text-xs">—</span>
                        </td>
                        <td class="p-3">
                            <span v-if="r.pending_correction_status" class="text-amber-700 text-xs font-semibold">
                                Pending approval → {{ r.pending_correction_status }}
                            </span>
                            <button v-else @click="save(r)" class="text-xs font-semibold text-indigo-600">Save</button>
                        </td>
                    </tr>
                    <tr v-if="!registrations.length">
                        <td colspan="6" class="p-6 text-center text-gray-400">No registrations.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { examPortalNavItems } from '@/support/examPortalNav.js';
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({ sahodaya: Object, exam: Object, registrations: Array, isTrustedReviewer: { type: Boolean, default: false } });
const csvFile = ref(null);
const forms = reactive({});
for (const r of props.registrations) {
    forms[r.id] = { attendance_status: r.attendance_status || 'pending', attendance_note: r.attendance_note || '' };
}

function save(r) {
    const status = forms[r.id].attendance_status;
    if (status === 'pending') return;
    if (['malpractice', 'withheld'].includes(status) && !forms[r.id].attendance_note?.trim()) {
        alert('A reason/note is required when marking malpractice or withheld.');
        return;
    }
    router.post(`/portal/exam/${props.sahodaya.id}/exams/${props.exam.id}/attendance`, {
        registration_id: r.id,
        attendance_status: status,
        attendance_note: forms[r.id].attendance_note || null,
    }, { preserveScroll: true });
}

function importCsv() {
    if (!csvFile.value) return;
    router.post(`/portal/exam/${props.sahodaya.id}/exams/${props.exam.id}/attendance/import`, {
        csv: csvFile.value,
    }, { forceFormData: true, preserveScroll: true });
}

const navItems = computed(() => examPortalNavItems(props.sahodaya.id, props.exam.id));
</script>

