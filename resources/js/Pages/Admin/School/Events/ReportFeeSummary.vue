<template>
    <SchoolAdminLayout :title="`Fee summary — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Fee summary — ${event.title}`" :eyebrow="programLabel"
                    description="Your school's event fee status and receipt.">
            <template #actions>
                <Link :href="`${programBase}/reports`" class="btn-secondary text-sm">← Reports</Link>
                <Link v-if="paymentsUrl" :href="paymentsUrl" class="btn-primary text-sm">Payments →</Link>
            </template>
        </PageHeader>

        <EmptyState v-if="!fee" title="No fee record" description="Fees may not be configured for this event, or billing has not started yet." icon="💳" />
        <div v-else class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="card text-center">
                <p class="text-2xl font-bold">₹{{ fee.total_due }}</p>
                <p class="text-xs text-gray-500 mt-1">Total due</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-emerald-700">₹{{ fee.paid }}</p>
                <p class="text-xs text-gray-500 mt-1">Paid</p>
            </div>
            <div class="card text-center">
                <p class="text-lg font-bold capitalize">{{ fee.status }}</p>
                <p class="text-xs text-gray-500 mt-1">Status</p>
            </div>
            <div class="card text-center">
                <p class="text-sm font-mono">{{ fee.receipt_no ?? '—' }}</p>
                <p class="text-xs text-gray-500 mt-1">Receipt no</p>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    fee: Object,
    paymentsUrl: String,
});
const { programLabel, programBase } = useSchoolProgramContext(props);
</script>
