<template>
    <SahodayaEventsLayout :title="`${event.title} — Student-wise`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Student-wise browser`" eyebrow="Reports"
                    description="Browse students by school — view all item registrations and marks per student.">
            <template #actions>
                <a :href="xlsUrl" target="_blank" class="btn-secondary text-sm">Export Excel ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="student-wise" />

        <form class="card !p-4 mb-4 flex flex-wrap gap-3 items-end" @submit.prevent="applyFilters">
            <FormField label="School" class-extra="mb-0 min-w-[12rem]">
                <select v-model="schoolFilter" class="field text-sm">
                    <option value="">All schools</option>
                    <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </FormField>
            <FormField v-if="event.event_type === 'sports' && childEvents.length" label="Sport Event" class-extra="mb-0 min-w-[12rem]">
                <select v-model="sportEventFilter" class="field text-sm">
                    <option value="">All Sport Events</option>
                    <option v-for="ev in childEvents" :key="ev.id" :value="ev.id">
                        {{ ev.title }} {{ ev.parent_event_id === null ? '(Season Hub)' : '' }}
                    </option>
                </select>
            </FormField>
            <FormField label="Search student" class-extra="mb-0 flex-1 min-w-[10rem]">
                <input v-model="searchFilter" type="search" class="field text-sm" placeholder="Name or reg no…">
            </FormField>
            <button type="submit" class="btn-primary text-sm">Apply</button>
            <button v-if="schoolFilter || searchFilter || selectedStudentId || sportEventFilter" type="button" class="btn-secondary text-sm" @click="clearFilters">Clear</button>
        </form>

        <div v-if="selectedStudent" class="card mb-6 overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50/80 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="section-title text-sm">{{ selectedStudent.name }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ selectedStudent.school_name }} · {{ selectedStudent.reg_no }}
                        · {{ selectedStudent.item_count }} item{{ selectedStudent.item_count === 1 ? '' : 's' }}
                        · total score {{ selectedStudent.total_score }}
                    </p>
                </div>
                <Link :href="base" class="text-xs font-semibold text-indigo-600 hover:underline">← All students</Link>
            </div>
            <table class="data-table w-full text-sm">
                <thead>
                    <tr>
                        <th>Head</th><th>Item</th><th>Status</th><th>Fest ID</th><th>Item reg</th><th>Chest</th><th>Grade</th><th>Rank</th><th>Score</th>
                        <th v-if="event.event_type === 'sports'">Time / distance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, idx) in selectedStudent.items" :key="idx">
                        <td class="text-xs text-slate-500">{{ item.head_name ?? '—' }}</td>
                        <td>
                            {{ item.item_title }}
                            <span v-if="item.sport_event_title" class="block text-[10px] text-slate-400 font-semibold">{{ item.sport_event_title }}</span>
                        </td>
                        <td><span class="text-xs capitalize">{{ item.status }}</span></td>
                        <td class="font-mono text-xs">{{ item.fest_id ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ item.item_reg ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ item.chest_no ?? '—' }}</td>
                        <td>{{ item.grade ?? '—' }}</td>
                        <td>{{ item.position ?? '—' }}</td>
                        <td>{{ item.score ?? '—' }}</td>
                        <td v-if="event.event_type === 'sports'">
                            <span v-if="item.mark_value">{{ item.mark_value }} {{ item.mark_unit }}</span>
                            <span v-else>—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card card--flush overflow-hidden">
            <table class="data-table w-full text-sm">
                <thead>
                    <tr>
                        <th>Sl No</th>
                        <th>School</th>
                        <th>Student</th>
                        <th>Reg no</th>
                        <th>Items</th>
                        <th>Total score</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in filteredRows" :key="row.student_id" class="border-t">
                        <td>{{ idx + 1 }}</td>
                        <td class="text-xs">{{ (row.school_name || '').toUpperCase() }}</td>
                        <td class="font-medium">{{ row.name }}</td>
                        <td class="font-mono text-xs">{{ row.reg_no }}</td>
                        <td>{{ row.item_count }}</td>
                        <td class="font-mono">{{ row.total_score }}</td>
                        <td class="text-right">
                            <Link :href="studentUrl(row.student_id)" class="text-xs font-semibold text-indigo-600 hover:underline">View →</Link>
                        </td>
                    </tr>
                    <tr v-if="!filteredRows.length">
                        <td colspan="7" class="p-8 text-center text-slate-400">No students match filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, rows: Array, selectedStudent: Object,
    filters: Object, schools: Array, xlsUrl: String,
    childEvents: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/student-wise`;
const schoolFilter = ref(props.filters?.school_id ?? '');
const searchFilter = ref(props.filters?.search ?? '');
const selectedStudentId = ref(props.filters?.student_id ?? '');
const sportEventFilter = ref('');

function applyFilters() {
    router.get(base, {
        school_id: schoolFilter.value || undefined,
        search: searchFilter.value.trim() || undefined,
        student_id: selectedStudentId.value || undefined,
    }, { preserveScroll: true, preserveState: true });
}

function clearFilters() {
    schoolFilter.value = '';
    searchFilter.value = '';
    selectedStudentId.value = '';
    sportEventFilter.value = '';
    router.get(base, {}, { preserveScroll: true });
}

function studentUrl(studentId) {
    return `${base}?${new URLSearchParams({
        ...(schoolFilter.value ? { school_id: schoolFilter.value } : {}),
        ...(searchFilter.value.trim() ? { search: searchFilter.value.trim() } : {}),
        student_id: String(studentId),
    }).toString()}`;
}

const filteredRows = computed(() => {
    let list = props.rows ?? [];
    if (sportEventFilter.value) {
        list = list.filter(row =>
            row.items.some(item => String(item.sport_event_id) === String(sportEventFilter.value))
        );
    }
    return list;
});
</script>
