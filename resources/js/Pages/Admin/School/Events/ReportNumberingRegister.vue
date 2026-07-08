<template>
    <SchoolAdminLayout :title="`Numbering register — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Numbering register — ${event.title}`" :eyebrow="programLabel"
                    description="Fest ID, item reg and chest numbers assigned to your athletes.">
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Reports</Link>
                <a :href="xlsUrl" class="btn-secondary text-sm">Export Excel ↓</a>
            </template>
        </PageHeader>

        <div class="card overflow-hidden p-0">
            <table class="data-table text-sm">
                <thead>
                    <tr><th>Head</th><th>Item</th><th>Participant</th><th>Reg no</th><th>Status</th><th>Fest ID</th><th>Item reg</th><th>Chest</th></tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.participant_id">
                        <td class="text-xs text-slate-500">{{ row.head_name ?? '—' }}</td>
                        <td>{{ row.item }}</td>
                        <td class="font-medium">{{ row.name }}</td>
                        <td>{{ row.reg_no ?? '—' }}</td>
                        <td><span :class="row.reg_status === 'approved' ? 'text-emerald-700' : 'text-amber-700'">{{ row.reg_status }}</span></td>
                        <td class="font-mono text-xs">{{ row.fest_id ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ row.item_reg ?? '—' }}</td>
                        <td class="font-mono font-bold">{{ row.chest_no ?? '—' }}</td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="8" class="p-6 text-center text-slate-400">No registrations yet.</td></tr>
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
