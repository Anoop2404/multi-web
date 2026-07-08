<template>
    <SahodayaAdminLayout title="Login audit" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Login audit" eyebrow="Security"
                    description="RPT-AUTH-005 — failed and successful login attempts.">
            <template #actions>
                <a :href="exportUrl" class="btn-secondary text-sm">Export CSV</a>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 gap-3 mb-6 max-w-md">
            <div class="card !p-3 text-center">
                <p class="text-xs uppercase text-slate-400">Failed</p>
                <p class="text-xl font-bold text-red-700">{{ summary.failed }}</p>
            </div>
            <div class="card !p-3 text-center">
                <p class="text-xs uppercase text-slate-400">Success</p>
                <p class="text-xl font-bold text-green-700">{{ summary.success }}</p>
            </div>
        </div>

        <form class="card !p-4 mb-4 flex flex-wrap gap-3 items-end" @submit.prevent="applyFilters">
            <FormField label="From">
                <input v-model="filterForm.from" type="date" class="field">
            </FormField>
            <FormField label="To">
                <input v-model="filterForm.to" type="date" class="field">
            </FormField>
            <FormField label="Action">
                <select v-model="filterForm.action" class="field">
                    <option value="all">All</option>
                    <option value="login.failed">Failed only</option>
                    <option value="login.success">Success only</option>
                </select>
            </FormField>
            <button type="submit" class="btn-primary text-sm">Apply</button>
        </form>

        <div class="space-y-2">
            <div v-for="log in logs.data" :key="log.id" class="card !p-3 text-sm">
                <div class="flex flex-wrap justify-between gap-2">
                    <p class="font-semibold">{{ log.properties?.username || log.properties?.email || 'Unknown' }}</p>
                    <span class="text-xs uppercase font-semibold"
                          :class="log.action === 'login.failed' ? 'text-red-700' : 'text-green-700'">
                        {{ log.action.replace('login.', '') }}
                    </span>
                </div>
                <p class="text-slate-600 mt-1">{{ log.ip_address }} · {{ log.description }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ log.created_at }}</p>
            </div>
        </div>

        <div v-if="logs.links?.length > 3" class="flex justify-center gap-1 mt-4">
            <Link v-for="link in logs.links" :key="link.label" :href="link.url || '#'"
                  class="px-3 py-1 text-sm rounded border"
                  :class="link.active ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200'"
                  v-html="link.label" />
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FormField from '@/Components/ui/FormField.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    logs: Object,
    summary: Object,
    filters: Object,
    exportUrl: String,
});

const filterForm = reactive({ ...props.filters });

function applyFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/auth/login-audit`, filterForm, { preserveState: true });
}
</script>
