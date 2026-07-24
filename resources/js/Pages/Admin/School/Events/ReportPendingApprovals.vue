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
                    <tr v-for="row in rows.data" :key="row.registration_id">
                        <td class="text-xs text-slate-500">{{ row.head_name ?? '—' }}</td>
                        <td class="font-medium">{{ row.item }}</td>
                        <td>{{ row.participant_count }}</td>
                        <td class="text-sm">{{ (row.participants ?? []).join(', ') || '—' }}</td>
                    </tr>
                    <tr v-if="!rows.data.length"><td colspan="4" class="p-6 text-center text-slate-400">No pending registrations — all approved or none submitted.</td></tr>
                </tbody>
            </table>
            <div v-if="rows.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex flex-wrap justify-center gap-1">
                <Link v-for="link in rows.links" :key="link.label"
                      :href="link.url || '#'"
                      class="px-3 py-1 rounded text-xs font-medium"
                      :class="link.active ? 'bg-[#0f3d7a] text-white' : (link.url ? 'text-[#0f3d7a] hover:bg-gray-100' : 'text-gray-300 pointer-events-none')"
                      v-html="link.label" />
            </div>
            <div v-else-if="rows.total" class="px-4 py-2 border-t border-gray-100 text-center text-xs text-slate-400">
                Showing all {{ rows.total }} row{{ rows.total === 1 ? '' : 's' }}
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object, program: [String, Object], programMeta: Object, event: Object,
    rows: Object, xlsUrl: String,
});
const { programLabel, programBase } = useSchoolProgramContext(props);
</script>
