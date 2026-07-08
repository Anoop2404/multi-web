<template>
    <SahodayaAdminLayout title="Email delivery" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Email delivery log" eyebrow="Finance"
                    description="RPT-EML-001 — transactional email delivery status across the tenant." />

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div v-for="(count, key) in summary" :key="key" class="card !p-3 text-center">
                <p class="text-xs uppercase text-slate-400">{{ key }}</p>
                <p class="text-xl font-bold text-[#0f3d7a]">{{ count }}</p>
            </div>
        </div>

        <div class="flex gap-2 mb-4 flex-wrap">
            <Link v-for="s in statuses" :key="s"
                  :href="`/sahodaya-admin/${sahodaya.id}/finance/email-delivery?status=${s}`"
                  class="px-3 py-1.5 rounded-lg text-sm font-semibold border capitalize"
                  :class="filters.status === s ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200'">
                {{ s }}
            </Link>
        </div>

        <div class="space-y-2">
            <div v-for="log in logs.data" :key="log.id" class="card !p-3 text-sm">
                <div class="flex flex-wrap justify-between gap-2">
                    <p class="font-semibold text-[#0f3d7a]">{{ log.subject }}</p>
                    <span class="text-xs uppercase font-semibold" :class="statusClass(log.status)">{{ log.status }}</span>
                </div>
                <p class="text-slate-600 mt-1">{{ log.to }} · {{ log.template_key || 'direct' }}</p>
                <p v-if="log.error" class="text-red-600 text-xs mt-1">{{ log.error }}</p>
                <div class="flex items-center justify-between mt-2">
                    <p class="text-xs text-slate-400">{{ log.created_at }}</p>
                    <button v-if="log.status === 'failed' || log.status === 'skipped'" type="button"
                            class="text-xs font-semibold text-[#0f3d7a] hover:underline"
                            @click="retryLog(log.id)">
                        Retry
                    </button>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    logs: Object,
    summary: Object,
    filters: Object,
});

const statuses = ['all', 'sent', 'failed', 'queued', 'skipped'];

function statusClass(status) {
    return {
        sent: 'text-green-700',
        failed: 'text-red-700',
        queued: 'text-amber-700',
        skipped: 'text-slate-500',
    }[status] ?? 'text-slate-600';
}

function retryLog(id) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/finance/email-delivery/${id}/retry`, {}, { preserveScroll: true });
}
</script>
