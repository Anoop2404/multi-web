<template>
    <SahodayaEventsLayout :title="`${event.title} — School Results`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — School Results`" eyebrow="Reports"
                    description="School-wise results by item and class group.">
            <template #actions>
                <a v-if="pdfUrl" :href="pdfUrl" target="_blank" class="btn-primary text-sm">Download PDF ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="school-detailed" />

        <form @submit.prevent="filter" class="flex flex-wrap gap-2 my-4">
            <SearchableSelect v-model="f.school_id" :options="schools" :all-option="true" all-label="Select school" :required="true" />
            <SearchableSelect v-model="f.class_group" :options="classGroupOptions" :all-option="true" all-label="All classes" />
            <button class="btn-primary">Show</button>
        </form>
        <div v-for="(rows, item) in grouped" :key="item" class="mb-4 bg-white border rounded-xl p-4">
            <h3 class="font-semibold text-sm mb-2">{{ item }}</h3>
            <ul class="text-sm divide-y">
                <li v-for="(row, i) in rows" :key="i" class="py-1 flex justify-between gap-x-2 flex-wrap">
                    <span class="min-w-0">{{ row.students }}</span>
                    <span class="text-gray-500">#{{ row.position ?? '—' }} · {{ row.grade ?? '—' }} · {{ row.score }}</span>
                </li>
            </ul>
        </div>
        <p v-if="!Object.keys(grouped).length" class="text-gray-400 text-sm">Select a school to view results.</p>
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
    event: Object, schools: Array, classGroups: Object, filters: Object, grouped: Object, pdfUrl: String,
    activityLogs: { type: Array, default: () => [] },
});

const f = reactive({ school_id: props.filters?.school_id ?? '', class_group: props.filters?.class_group ?? '' });

const classGroupOptions = computed(() => Object.entries(props.classGroups ?? {}).map(([value, label]) => ({ value, label })));

function filter() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/school-detailed`, { ...f }, { preserveState: true });
}
</script>

