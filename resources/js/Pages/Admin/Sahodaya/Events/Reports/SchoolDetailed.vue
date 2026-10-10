<template>
    <SahodayaEventsLayout :title="`${event.title} — School Results`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — School Results`" eyebrow="Reports"
                    description="Choose full results or published results with grades and ranks.">
            <template #actions>
                <a v-if="pdfUrl" :href="pdfUrl" target="_blank" class="btn-primary text-sm">Download PDF ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="school-detailed" />

        <form @submit.prevent="filter" class="flex flex-wrap gap-2 my-4">
            <SearchableSelect v-model="f.school_id" :options="schools" :all-option="true" all-label="Select school" :required="true" />
            <SearchableSelect v-model="f.class_group" :options="classGroupOptions" :all-option="true" all-label="All classes" />
            <select v-model="f.result_mode" class="rounded-lg border-gray-300 text-sm" aria-label="Result report format">
                <option value="full">Full results with marks</option>
                <option value="published">Published results — grades and ranks</option>
            </select>
            <button class="btn-primary">Show</button>
        </form>
        <section v-for="school in schoolResults" :key="school.school_name" class="mb-6 bg-white border rounded-xl overflow-hidden">
            <h2 class="p-4 bg-slate-50 border-b font-bold text-lg">{{ school.school_name }}</h2>
            <table class="w-full text-sm">
                <thead class="text-left bg-slate-50"><tr><th class="p-3 w-16">Sl No</th><th class="p-3">Student</th><th class="p-3">Item</th><th class="p-3 text-right">Rank</th><th class="p-3 text-right">Grade</th></tr></thead>
                <tbody v-for="(student, studentIndex) in school.students" :key="student.key" class="border-t">
                    <tr v-for="(result, index) in student.results" :key="index">
                        <td v-if="index === 0" :rowspan="student.results.length" class="p-3 align-top">{{ studentIndex + 1 }}</td>
                        <td v-if="index === 0" :rowspan="student.results.length" class="p-3 align-top font-semibold">{{ student.name }}</td>
                        <td class="p-3">{{ result.item }}<span v-if="result.category" class="block text-xs text-slate-500">{{ result.category }}</span></td>
                        <td class="p-3 text-right">{{ result.rank ? `#${result.rank}` : '—' }}</td>
                        <td class="p-3 text-right">{{ result.grade ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
        <div v-for="(rows, item) in grouped" :key="item" class="mb-4 bg-white border rounded-xl p-4">
            <h3 class="font-semibold text-sm mb-2">{{ item }}</h3>
            <ul class="text-sm divide-y">
                <li v-for="(row, i) in rows" :key="i" class="py-1 flex justify-between gap-x-2 flex-wrap">
                    <span class="min-w-0">{{ row.students }}</span>
                    <span class="text-gray-500"><template v-if="row.position">#{{ row.position }} · </template>{{ row.grade ?? '—' }}<template v-if="filters?.result_mode !== 'published'"> · {{ row.score }}</template></span>
                </li>
            </ul>
        </div>
        <p v-if="!Object.keys(grouped).length && !schoolResults.length" class="text-gray-400 text-sm">Select a school to view results.</p>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { reactive, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, schools: Array, classGroups: Object, filters: Object, grouped: Object, pdfUrl: String, schoolResults: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const f = reactive({ school_id: props.filters?.school_id ?? '', class_group: props.filters?.class_group ?? '', result_mode: props.filters?.result_mode ?? 'full' });

const classGroupOptions = computed(() => Object.entries(props.classGroups ?? {}).map(([value, label]) => ({ value, label })));

function filter() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/school-detailed`, { ...f }, { preserveState: true });
}
</script>

