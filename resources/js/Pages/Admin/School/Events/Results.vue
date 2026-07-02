<template>
    <SchoolAdminLayout :title="`${programLabel} Results`" :school="school" :show-header-title="false">
        <PageHeader :title="`${programLabel} results`" :eyebrow="programLabel"
                    description="Published scoreboards for events where results are released." />


        <div v-if="!events.length" class="text-sm text-gray-500">Results not published yet.</div>
        <div v-for="event in events" :key="event.id" class="card mb-4">
            <h3 class="font-semibold mb-2">{{ event.title }}</h3>
            <ol class="text-sm space-y-1">
                <li v-for="row in scoreboards[event.id] ?? []" :key="row.school_id">
                    #{{ row.rank }} {{ row.school_name }} — {{ row.total_points }} pts
                </li>
            </ol>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({ school: Object, program: [String, Object], programMeta: { type: Object, default: null }, events: Array, scoreboards: Object });
const { programLabel } = useSchoolProgramContext(props);
</script>
