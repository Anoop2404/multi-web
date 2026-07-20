<template>
    <SahodayaAdminLayout title="Subject-wise Merit Register" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Subject-wise Merit Register" eyebrow="Academic Results"
                    description="Highest scorers by subject across published board results." />

        <form class="flex flex-wrap gap-3 mb-4" @submit.prevent="apply">
            <input v-model="year" class="field w-40" placeholder="2024-25">
            <select v-model="klass" class="field w-36">
                <option :value="null">All classes</option>
                <option v-for="c in classOptions" :key="c" :value="c">Class {{ c }}</option>
            </select>
            <button type="submit" class="btn-primary text-sm">Apply</button>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/reports`" class="text-sm text-slate-500 self-center">← Reports</Link>
        </form>

        <div class="card !p-0 overflow-x-auto">
            <table class="data-table min-w-[720px]">
                <thead>
                    <tr>
                        <th>Sl No</th>
                        <th>Subject</th>
                        <th>Student</th>
                        <th>School</th>
                        <th>Marks</th>
                        <th>Stream</th>
                        <th>Class</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in rows" :key="i">
                        <td>{{ i + 1 }}</td>
                        <td class="font-medium">{{ row.subject }}</td>
                        <td>{{ row.student_name }}</td>
                        <td>{{ (row.school_name || '').toUpperCase() }}</td>
                        <td>{{ row.marks }}</td>
                        <td>{{ row.stream || '—' }}</td>
                        <td>{{ row.class || '—' }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="7" class="p-8 text-center text-slate-400">No subject marks found for this year.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    rows: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    classOptions: { type: Array, default: () => [10, 12] },
});

const year = ref(props.filters.academic_year || '');
const klass = ref(props.filters.class ?? null);

function apply() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/board-results/reports/subject-merit`, {
        academic_year: year.value,
        class: klass.value || undefined,
    }, { preserveState: true });
}
</script>
