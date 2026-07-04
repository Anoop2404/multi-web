<template>
    <SchoolAdminLayout :title="`${event.title} — Fest Day`" :school="school" :show-header-title="false">
        <PageHeader :title="`${event.title} — Fest Day`" :eyebrow="programLabel"
            description="Schedule, chest numbers, and stage call status for your participants." />


        <div class="max-w-4xl">
            <p class="text-sm text-gray-500 mb-4">{{ school.name }} · {{ event.status }}</p>
            <div v-if="verificationStatus?.verification_day" class="mb-4 p-3 rounded-xl border text-sm"
                 :class="verificationStatus.documents_verified ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-amber-50 border-amber-200 text-amber-900'">
                Document verification day: <strong>{{ verificationStatus.verification_day }}</strong>
                · Status: <strong>{{ verificationStatus.documents_verified ? 'Verified' : 'Pending verification' }}</strong>
            </div>
            <div v-if="!event.schedule_published" class="mb-4 p-3 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-900">
                Public schedule is not published yet. Times shown here are for your school coordination.
            </div>
            <div class="card card--flush">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Participant</th>
                            <th class="p-3">Item</th>
                            <th class="p-3">Level reg</th>
                            <th class="p-3">Chest</th>
                            <th class="p-3">Order</th>
                            <th class="p-3">Time</th>
                            <th class="p-3">Stage</th>
                            <th class="p-3">Called</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in rows" :key="i" class="border-t">
                            <td class="p-3 font-medium">{{ row.name }}</td>
                            <td class="p-3">{{ row.item }}</td>
                            <td class="p-3 font-mono text-xs">{{ row.level_reg || '—' }}</td>
                            <td class="p-3 font-mono text-xs">{{ row.chest_no || '—' }}</td>
                            <td class="p-3">{{ row.order ?? '—' }}</td>
                            <td class="p-3 text-xs">{{ row.scheduled_at ? new Date(row.scheduled_at).toLocaleString() : '—' }}</td>
                            <td class="p-3">{{ row.stage || '—' }}</td>
                            <td class="p-3">
                                <span class="text-xs px-2 py-0.5 rounded" :class="row.called ? 'bg-green-100 text-green-800' : 'bg-gray-100'">
                                    {{ row.called ? 'Yes' : 'No' }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!rows.length"><td colspan="8" class="p-6 text-center text-gray-400">No approved participants.</td></tr>
                    </tbody>
                </table>
            </div>
            <a :href="`${programBase}/registration`" class="inline-block mt-4 text-sm text-indigo-600">← Back to registrations</a>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({ school: Object, event: Object, program: [String, Object], programMeta: { type: Object, default: null }, rows: Array, verificationStatus: Object });
const { programLabel, programBase } = useSchoolProgramContext(props);
</script>
