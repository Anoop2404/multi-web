<template>
    <PortalLayout
        role-label="Exam Portal"
        :title="`Mark entry — ${exam.title}`"
        subtitle="Only students marked present"
        accent="emerald"
        :nav-items="navItems"
    >
        <div v-if="exam.results_published" class="card p-3 mb-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm">
            Results are published for this exam. Marks are locked. Ask the Sahodaya admin to unpublish results to reopen for correction.
        </div>
        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Student</th>
                        <th class="p-3">Correct</th>
                        <th class="p-3">Wrong</th>
                        <th class="p-3">Unanswered</th>
                        <th class="p-3">Score</th>
                        <th class="p-3">Grade</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in registrations.data" :key="r.id" class="border-t">
                        <td class="p-3">{{ r.student?.name }}</td>
                        <td class="p-3"><input v-model.number="forms[r.id].correct_count" type="number" min="0" class="w-14 field" :disabled="exam.results_published"></td>
                        <td class="p-3"><input v-model.number="forms[r.id].wrong_count" type="number" min="0" class="w-14 field" :disabled="exam.results_published"></td>
                        <td class="p-3"><input v-model.number="forms[r.id].unanswered_count" type="number" min="0" class="w-14 field" :disabled="exam.results_published"></td>
                        <td class="p-3"><input v-model.number="forms[r.id].score" type="number" min="0" step="0.01" class="w-16 field" :disabled="exam.results_published"></td>
                        <td class="p-3">
                            <SearchableSelect
                                v-model="forms[r.id].grade"
                                class="w-14"
                                :options="gradeOptions"
                                :all-option="true"
                                all-label="—"
                                :disabled="exam.results_published"
                            />
                        </td>
                        <td class="p-3">
                            <button v-if="!exam.results_published" @click="save(r)" class="text-xs font-semibold text-indigo-600">Save</button>
                        </td>
                    </tr>
                    <tr v-if="!registrations.data?.length">
                        <td colspan="7" class="p-6 text-center text-gray-400">No present students to mark.</td>
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
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { examPortalNavItems } from '@/support/examPortalNav.js';
import { computed, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({ sahodaya: Object, exam: Object, registrations: Object, gradeBands: { type: Array, default: () => [] } });
const gradeOptions = computed(() => props.gradeBands?.length ? props.gradeBands.map((b) => b.label) : ['A+', 'A', 'B', 'C', 'D', 'F']);
const forms = reactive({});
for (const r of props.registrations.data ?? []) {
    forms[r.id] = {
        correct_count: r.mark?.correct_count ?? 0,
        wrong_count: r.mark?.wrong_count ?? 0,
        unanswered_count: r.mark?.unanswered_count ?? 0,
        score: r.mark?.score ?? 0,
        grade: r.mark?.grade ?? '',
    };
}

function save(r) {
    router.post(`/portal/exam/${props.sahodaya.id}/exams/${props.exam.id}/registrations/${r.id}/marks`, forms[r.id], { preserveScroll: true });
}

// Role passed through so exam_staff (view-only for marks) doesn't get shown the
// "Mark entry" link only to hit a 403 on the server — see Documents/Path_breaks.md.
const currentRole = computed(() => usePage().props.auth?.user?.roles?.includes('exam_staff') ? 'exam_staff' : null);
const navItems = computed(() => examPortalNavItems(props.sahodaya.id, props.exam.id, { role: currentRole.value }));
</script>
