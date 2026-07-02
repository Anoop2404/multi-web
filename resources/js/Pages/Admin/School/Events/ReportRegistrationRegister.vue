<template>
    <SchoolAdminLayout :title="`Registration Register — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Registration & Fees — ${event.title}`"
            :eyebrow="programLabel"
            description="Fest ID per student, item registrations, chest numbers, and your school's fee status."
        >
            <template #actions>
                <Link :href="`${programBase}/reports`" class="btn-secondary text-sm">← Reports</Link>
                <a :href="exportUrl" class="btn-primary text-sm">Export CSV ↓</a>
                <Link :href="paymentsUrl" class="btn-secondary text-sm">Payments →</Link>
            </template>
        </PageHeader>

        <div class="notice-banner notice-banner--info mb-4 text-sm max-w-3xl">
            <p class="font-semibold text-[#0f3d7a] mb-1">Your fest IDs</p>
            <p class="text-slate-700">
                Each student gets one <strong>Fest ID</strong> for this event (shown below). That same ID applies to every item they register for.
                <strong>Chest numbers</strong> are per item and appear after Sahodaya approval.
                Event fees are one total per school — upload proof under Payments once all items are registered.
            </p>
        </div>

        <div v-if="schoolSummary && totals.fee_required" class="grid sm:grid-cols-4 gap-3 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ schoolSummary.item_count }}</p>
                <p class="text-xs text-slate-500 mt-1">Items registered</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">₹{{ schoolSummary.total_due }}</p>
                <p class="text-xs text-slate-500 mt-1">Total due</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold capitalize">{{ schoolSummary.fee_status }}</p>
                <p class="text-xs text-slate-500 mt-1">Fee status</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold font-mono text-sm">{{ schoolSummary.receipt_no ?? '—' }}</p>
                <p class="text-xs text-slate-500 mt-1">Receipt #</p>
            </div>
        </div>

        <div class="card card--flush overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Participant</th>
                        <th class="p-3">Fest ID</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Chest</th>
                        <th class="p-3">Item fee</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.participant_id" class="border-t align-top">
                        <td class="p-3">
                            <span class="font-medium">{{ row.participant_name }}</span>
                            <p class="text-xs font-mono text-[#0f3d7a]">{{ row.participant_reg_no }}</p>
                        </td>
                        <td class="p-3 font-mono text-xs font-semibold text-[#0f3d7a]">{{ row.level_reg }}</td>
                        <td class="p-3 text-xs">{{ row.item_title }}</td>
                        <td class="p-3 text-xs capitalize">
                            {{ row.registration_status }}
                            <span v-if="row.participant_role === 'standby'" class="text-slate-500"> · standby</span>
                        </td>
                        <td class="p-3 font-mono text-xs">{{ row.chest_no }}</td>
                        <td class="p-3 text-xs">{{ row.item_fee != null ? `₹${row.item_fee}` : '—' }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="6" class="p-8 text-center text-gray-400">No registrations yet.</td>
                    </tr>
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

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    rows: Array,
    schoolSummary: Object,
    totals: Object,
    paymentsUrl: String,
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const exportUrl = computed(() => `${programBase.value}/reports/${props.event.id}/registration-register/export`);
</script>
