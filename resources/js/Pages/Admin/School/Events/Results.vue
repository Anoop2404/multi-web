<template>
    <SchoolAdminLayout :title="`${programLabel} Results`" :school="school">
        <div v-if="!events.length" class="text-sm text-gray-500">Results not published yet.</div>
        <div v-for="event in events" :key="event.id" class="bg-white border rounded-xl p-4 mb-4">
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

const props = defineProps({ school: Object, program: String, events: Array, scoreboards: Object });
const labels = { kalotsav: 'Kalotsav', 'sports-meet': 'Sports Meet', 'kids-fest': 'Kids Fest' };
const programLabel = labels[props.program] ?? props.program;
</script>
