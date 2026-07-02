<template>
    <SchoolAdminLayout :title="`Qualifiers — ${programLabel}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`${programLabel} qualifiers`"
            :eyebrow="programLabel"
            description="Students and teachers promoted to the next fest round."
        >
            <template #actions>
                <Link :href="`/school-admin/${school.id}/fest/reports`" class="btn-secondary text-sm">← Reports hub</Link>
                <a :href="exportUrl" class="btn-primary text-sm">Export CSV ↓</a>
            </template>
        </PageHeader>

        <div class="card card--flush">
            <table class="w-full text-sm data-table">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Item</th>
                        <th>From round</th>
                        <th>Next round</th>
                        <th>Promoted</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(q, i) in qualifications" :key="i">
                        <td>{{ q.participant }} <span class="text-xs text-gray-400">{{ q.reg_no }}</span></td>
                        <td>{{ q.item }}</td>
                        <td class="text-xs">{{ q.from_event }} ({{ q.from_level }})</td>
                        <td class="text-xs">{{ q.next_event || '—' }} <span v-if="q.next_level">({{ q.next_level }})</span></td>
                        <td class="text-xs">{{ q.promoted_at ? new Date(q.promoted_at).toLocaleDateString() : '—' }}</td>
                    </tr>
                    <tr v-if="!qualifications.length"><td colspan="5" class="p-8 text-center text-gray-400">No qualifiers yet.</td></tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({ school: Object, program: [String, Object], programMeta: { type: Object, default: null }, qualifications: Array });
const { programSlug, programLabel, programBase } = useSchoolProgramContext(props);
const exportUrl = computed(() => `${programBase.value}/qualifiers/export`);
</script>
