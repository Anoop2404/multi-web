<template>
    <SchoolAdminLayout title="Calendar" :school="school" :show-header-title="false">
        <PageHeader title="Calendar" eyebrow="Programs"
                    description="Upcoming Sahodaya deadlines and program dates relevant to your school.">
            <template #actions>
                <a v-if="icalUrl" :href="icalUrl" class="btn-secondary text-sm">Export iCal</a>
            </template>
        </PageHeader>

        <form class="flex flex-wrap gap-3 items-end mb-6" @submit.prevent="applyFilters">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">From</label>
                <input v-model="form.from" type="date" class="input-field text-sm" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">To</label>
                <input v-model="form.to" type="date" class="input-field text-sm" />
            </div>
            <button type="submit" class="btn-primary text-sm">Apply</button>
        </form>

        <div class="space-y-2">
            <div v-for="event in events" :key="event.id" class="card !p-4">
                <p class="text-xs uppercase tracking-wide text-gray-400">{{ event.module }} · {{ event.kind }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ event.title }}</p>
                <p class="text-sm text-gray-600 mt-1">
                    {{ event.start }}<span v-if="event.end"> → {{ event.end }}</span>
                </p>
            </div>
            <p v-if="!events.length" class="text-center text-gray-400 py-10">No events in this date range.</p>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    school: Object,
    events: Array,
    filters: Object,
    icalUrl: String,
});

const form = reactive({ ...props.filters });

function applyFilters() {
    router.get(`/school-admin/${props.school.id}/calendar`, form, { preserveState: true });
}
</script>
