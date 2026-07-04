<template>
    <SchoolAdminLayout :title="`Schedule clashes — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Schedule clashes — ${event.title}`" :eyebrow="programLabel"
                    description="Students from your school with overlapping item schedules.">
            <template #actions>
                <Link :href="`${programBase}/reports`" class="btn-secondary text-sm">← Reports</Link>
                <a v-if="csvUrl" :href="csvUrl" class="btn-primary text-sm">Export CSV ↓</a>
            </template>
        </PageHeader>

        <div v-if="totalClashes === 0" class="notice-banner notice-banner--success mb-6">
            No schedule clashes detected for your school.
        </div>
        <div v-else class="notice-banner notice-banner--warning mb-6">
            {{ totalClashes }} clash(es) found — contact Sahodaya to resolve before fest day.
        </div>

        <section v-if="participant.length" class="mb-8">
            <h3 class="section-title mb-3">Participant clashes</h3>
            <div class="card overflow-hidden p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Item 1</th>
                            <th>Item 2</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(c, i) in participant" :key="'p-'+i">
                            <td>{{ c.student_name }}</td>
                            <td>{{ c.event1 }}</td>
                            <td>{{ c.event2 }}</td>
                            <td class="text-xs">{{ c.time }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    participant: { type: Array, default: () => [] },
    stage: { type: Array, default: () => [] },
    csvUrl: String,
});
const { programLabel, programBase } = useSchoolProgramContext(props);
const totalClashes = computed(() => props.participant.length + props.stage.length);
</script>
