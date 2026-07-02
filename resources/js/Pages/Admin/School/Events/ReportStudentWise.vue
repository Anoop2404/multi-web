<template>
    <SchoolAdminLayout :title="`Student-wise — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Student-wise — ${event.title}`"
            :eyebrow="programLabel"
            description="Per-student registrations and scores for this event."
        >
            <template #actions>
                <Link :href="`${programBase}/reports`" class="btn-secondary text-sm">← Reports</Link>
                <a :href="exportUrl" class="btn-primary text-sm">Export CSV ↓</a>
            </template>
        </PageHeader>

        <div class="mt-4 space-y-2">
            <div v-for="row in rows" :key="row.student.id" class="card text-sm">
                <p class="font-medium">{{ row.student.name }} <span class="text-gray-400 text-xs">{{ row.student.reg_no }}</span></p>
                <p class="text-xs text-gray-500 mt-1">Items: {{ row.registrations.join(', ') || '—' }}</p>
                <p v-if="row.results.length" class="text-xs mt-1">Results: <span v-for="(r, i) in row.results" :key="i">{{ r.item }} (#{{ r.position }}) </span></p>
                <p class="text-xs font-mono mt-1">Total score: {{ row.total_score }}</p>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({ school: Object, program: [String, Object], programMeta: { type: Object, default: null }, event: Object, rows: Array });
const { programLabel, programBase } = useSchoolProgramContext(props);
const exportUrl = computed(() => `${programBase.value}/reports/${props.event.id}/student-wise/export`);
</script>
