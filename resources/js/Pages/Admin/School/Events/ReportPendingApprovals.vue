<template>
    <SchoolAdminLayout :title="`Pending approvals — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Pending approvals — ${event.title}`" :eyebrow="programLabel"
                    description="Your submitted registrations still awaiting Sahodaya approval.">
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Reports</Link>
                <a :href="xlsUrl" class="btn-secondary text-sm">Export Excel ↓</a>
            </template>
        </PageHeader>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr><th>Head</th><th>Item</th><th>Participants</th><th>Names</th></tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.registration_id">
                        <td class="text-xs text-slate-500">{{ row.head_name ?? '—' }}</td>
                        <td class="font-medium">{{ row.item }}</td>
                        <td>{{ row.participant_count }}</td>
                        <td class="text-sm">{{ (row.participants ?? []).join(', ') || '—' }}</td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="4" class="p-6 text-center text-slate-400">No pending registrations — all approved or none submitted.</td></tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object, program: [String, Object], programMeta: Object, event: Object,
    rows: Array, xlsUrl: String,
});
const { programLabel, programBase } = useSchoolProgramContext(props);
</script>
