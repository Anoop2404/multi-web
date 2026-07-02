<template>
    <SchoolAdminLayout :title="`Participation — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Participation — ${event.title}`"
            :eyebrow="programLabel"
            description="On-stage, off-stage, and group limits vs your school's usage."
        >
            <template #actions>
                <Link :href="`${programBase}/reports`" class="btn-secondary text-sm">← Reports</Link>
                <a :href="exportUrl" class="btn-primary text-sm">Export CSV ↓</a>
            </template>
        </PageHeader>

        <div class="grid sm:grid-cols-3 gap-3">
            <div v-for="(count, type) in used" :key="type" class="card text-center">
                <p class="text-2xl font-bold">{{ count }}</p>
                <p class="text-xs text-gray-500 capitalize">{{ type.replace('_', ' ') }}</p>
                <p v-if="limits[type]" class="text-xs text-indigo-600 mt-1">Limit: {{ limits[type] }}</p>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({ school: Object, program: [String, Object], programMeta: { type: Object, default: null }, event: Object, used: Object, limits: Object });
const { programLabel, programBase } = useSchoolProgramContext(props);
const exportUrl = computed(() => `${programBase.value}/reports/${props.event.id}/participation/export`);
</script>
