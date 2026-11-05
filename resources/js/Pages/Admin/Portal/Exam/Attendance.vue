<template>
    <PortalLayout
        role-label="Exam Portal"
        :title="`Attendance — ${exam.title}`"
        :subtitle="sahodaya.name"
        accent="emerald"
        :nav-items="navItems"
    >
        <InlineAlert :message="alertMessage" type="error" @dismiss="alertMessage = ''" />
        <div class="card card--flush">
            <form @submit.prevent="importCsv" class="p-4 border-b border-slate-100 flex flex-wrap gap-3 items-end">
                <div>
                    <label class="text-xs font-semibold text-slate-600 block mb-1">Bulk import CSV</label>
                    <input type="file" accept=".csv,text/csv" @change="e => csvFile = e.target.files[0]" class="text-xs" required>
                </div>
                <button type="submit" class="btn-secondary text-xs">Import attendance</button>
                <p class="text-xs text-slate-500 w-full">Columns: registration_id or student_id (unambiguous), or reg_no (hall_ticket_no) + class (reg. numbers repeat across classes, so class is required when using reg_no alone), attendance_status (present/absent/malpractice/withheld)</p>
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
                    <tr v-for="r in registrations.data" :key="r.id" class="border-t">
                        <td class="p-3 font-mono text-xs">{{ r.hall_ticket_no || '—' }}</td>
                        <td class="p-3">{{ r.student?.name }}</td>
                        <td class="p-3 text-xs text-gray-500">{{ r.school?.name }}</td>
                        <td class="p-3">
                            <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-xs font-semibold" role="tablist">
                                <button
                                    v-for="opt in statusTabs"
                                    :key="opt.value"
                                    type="button"
                                    role="tab"
                                    :disabled="!!r.pending_correction_status || savingId === r.id"
                                    class="px-2.5 py-1.5 border-r border-slate-200 last:border-r-0 transition-colors disabled:opacity-50"
                                    :class="tabClass(forms[r.id].attendance_status, opt.value)"
                                    @click="setStatus(r, opt.value)"
                                >
                                    {{ opt.label }}
                                </button>
                            </div>
                        </td>
                        <td class="p-3">
                            <div v-if="['malpractice','withheld'].includes(forms[r.id].attendance_status)" class="flex items-center gap-2">
                                <input
                                    v-model="forms[r.id].attendance_note"
                                    type="text"
                                    class="field text-xs"
                                    :disabled="!!r.pending_correction_status"
                                    placeholder="Reason (required)"
                                    @keydown.enter.prevent="save(r)"
                                >
                                <button
                                    v-if="!r.pending_correction_status"
                                    type="button"
                                    class="text-xs font-semibold text-indigo-600"
                                    :disabled="savingId === r.id"
                                    @click="save(r)"
                                >
                                    Save note
                                </button>
                            </div>
                            <span v-else class="text-gray-300 text-xs">—</span>
                        </td>
                        <td class="p-3">
                            <span v-if="r.pending_correction_status" class="text-amber-700 text-xs font-semibold">
                                Pending approval → {{ r.pending_correction_status }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!registrations.data?.length">
                        <td colspan="6" class="p-6 text-center text-gray-400">No registrations.</td>
                    </tr>
                </tbody>
            </table>
            <PaginationLinks :links="registrations.links" :meta="{ from: registrations.from, to: registrations.to, total: registrations.total }" />
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import PaginationLinks from '@/Components/ui/PaginationLinks.vue';
import { examPortalNavItems } from '@/support/examPortalNav.js';
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import InlineAlert from '@/Components/ui/InlineAlert.vue';

const props = defineProps({ sahodaya: Object, exam: Object, registrations: Object, isTrustedReviewer: { type: Boolean, default: false } });
const csvFile = ref(null);
const alertMessage = ref('');
const savingId = ref(null);
const forms = reactive({});
const statusTabs = [
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
    { value: 'malpractice', label: 'Malpractice' },
    { value: 'withheld', label: 'Withheld' },
];

for (const r of props.registrations.data ?? []) {
    forms[r.id] = { attendance_status: r.attendance_status || 'present', attendance_note: r.attendance_note || '' };
}

function tabClass(current, value) {
    const active = current === value;
    if (!active) return 'bg-white text-slate-600 hover:bg-slate-50';
    if (value === 'present') return 'bg-emerald-600 text-white';
    if (value === 'absent') return 'bg-rose-600 text-white';
    return 'bg-amber-600 text-white';
}

function setStatus(r, status) {
    if (r.pending_correction_status || !forms[r.id]) return;
    forms[r.id].attendance_status = status;
    if (['malpractice', 'withheld'].includes(status) && !forms[r.id].attendance_note?.trim()) {
        return;
    }
    save(r);
}

function save(r) {
    const status = forms[r.id].attendance_status;
    if (status === 'pending') return;
    if (['malpractice', 'withheld'].includes(status) && !forms[r.id].attendance_note?.trim()) {
        alertMessage.value = 'A reason/note is required when marking malpractice or withheld.';
        return;
    }
    savingId.value = r.id;
    router.post(`/portal/exam/${props.sahodaya.id}/exams/${props.exam.id}/attendance`, {
        registration_id: r.id,
        attendance_status: status,
        attendance_note: forms[r.id].attendance_note || null,
    }, {
        preserveScroll: true,
        onFinish: () => { savingId.value = null; },
    });
}

function importCsv() {
    if (!csvFile.value) return;
    router.post(`/portal/exam/${props.sahodaya.id}/exams/${props.exam.id}/attendance/import`, {
        csv: csvFile.value,
    }, { forceFormData: true, preserveScroll: true });
}

const currentRole = computed(() => usePage().props.auth?.user?.roles?.includes('exam_staff') ? 'exam_staff' : null);
const navItems = computed(() => examPortalNavItems(props.sahodaya.id, props.exam.id, { role: currentRole.value }));
</script>
