<template>
    <SahodayaAdminLayout title="Board result reports" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Board result reports" eyebrow="Academic Results"
                    description="Merit registers, rankings, pass %, excellence awards, and historical comparisons." />

        <div class="grid sm:grid-cols-2 gap-4">
            <a v-for="r in reports" :key="r.key" :href="r.href" class="card !p-5 hover:border-[#0f3d7a] transition">
                <p class="font-semibold text-[#0f3d7a]">{{ r.title }}</p>
                <p v-if="r.description" class="text-xs text-slate-500 mt-1">{{ r.description }}</p>
                <p class="text-xs text-slate-400 mt-2">Academic year {{ filters.academic_year }}</p>
            </a>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    reports: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});
</script>
