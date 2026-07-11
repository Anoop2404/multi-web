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
                    <tr v-for="r in registrations" :key="r.id" class="border-t">
                        <td class="p-3">{{ r.student?.name }}</td>
                        <td class="p-3"><input v-model.number="forms[r.id].correct_count" type="number" min="0" class="w-14 field" :disabled="exam.results_published"></td>
                        <td class="p-3"><input v-model.number="forms[r.id].wrong_count" type="number" min="0" class="w-14 field" :disabled="exam.results_published"></td>
                        <td class="p-3"><input v-model.number="forms[r.id].unanswered_count" type="number" min="0" class="w-14 field" :disabled="exam.results_published"></td>
                        <td class="p-3"><input v-model.number="forms[r.id].score" type="number" min="0" step="0.01" class="w-16 field" :disabled="exam.results_published"></td>
                        <td class="p-3">
                            <select v-model="forms[r.id].grade" class="field w-14" :disabled="exam.results_published">
                                <option value="">—</option>
                                <option v-for="g in gradeOptions" :key="g" :value="g">{{ g }}</option>
                            </select>
                        </td>
                        <td class="p-3">
                            <button v-if="!exam.results_published" @click="save(r)" class="text-xs font-semibold text-indigo-600">Save</button>
                        </td>
                    </tr>
                    <tr v-if="!registrations.length">
                        <td colspan="7" class="p-6 text-center text-gray-400">No present students to mark.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { examPortalNavItems } from '@/support/examPortalNav.js';
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({ sahodaya: Object, exam: Object, registrations: Array, gradeBands: { type: Array, default: () => [] } });
const gradeOptions = computed(() => props.gradeBands?.length ? props.gradeBands.map((b) => b.label) : ['A+', 'A', 'B', 'C', 'D', 'F']);
const forms = reactive({});
for (const r of props.registrations) {
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

const navItems = computed(() => examPortalNavItems(props.sahodaya.id, props.exam.id));
</script>

