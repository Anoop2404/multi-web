<template>
    <SahodayaAdminLayout :title="pageTitle" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Academic Results"
                    description="Review CBSE board results submitted by member schools — verify, approve, reject, or publish.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/masters`" class="btn-secondary text-sm">Masters</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/reports`" class="btn-secondary text-sm">Reports</Link>
            </template>
        </PageHeader>

        <p v-if="selectedClass" class="text-sm -mt-2 mb-4">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/verification?class=${selectedClass === 12 ? 10 : 12}&status=${filters.status}`" class="text-indigo-600 hover:underline font-medium">
                Switch to {{ selectedClass === 12 ? 'Class X' : 'Class XII' }} →
            </Link>
        </p>

        <div class="flex flex-wrap gap-2 mb-4">
            <Link v-for="(label, value) in statusOptions" :key="value"
                  :href="statusHref(value)"
                  class="px-3 py-1.5 rounded-lg text-sm font-semibold border"
                  :class="filters.status === value ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200 text-slate-600'">
                {{ label }}
            </Link>
        </div>

        <div class="card !p-4 mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Sahodaya-wide toppers</h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Default cap: {{ defaultTopN }} per school submission. The auto-computed Sahodaya-wide list
                    (Top-N + tie handling) now lives on its own page.
                </p>
            </div>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers`" class="btn-secondary text-sm shrink-0">
                Sahodaya Toppers →
            </Link>
        </div>

        <div class="space-y-3">
            <div v-for="r in results.data" :key="r.id" class="card !p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-[#0f3d7a]">{{ schoolNames[r.tenant_id] || r.tenant_id }}</p>
                        <p class="text-sm text-slate-700 mt-1">
                            Class {{ r.class }} · {{ r.examination_type }} · {{ r.academic_year }}
                        </p>
                        <p class="text-xs text-slate-500 mt-1">
                            <span class="capitalize">{{ r.status }}</span>
                            · {{ r.pass_percent }}% pass
                            · {{ r.total_appeared }} appeared
                            <span v-if="r.highest_mark"> · high {{ r.highest_mark }}</span>
                            <span v-if="r.toppers?.length"> · {{ r.toppers.length }} toppers</span>
                        </p>
                        <p v-if="r.rejection_reason" class="text-xs text-red-600 mt-1">{{ r.rejection_reason }}</p>
                        <p v-if="r.uploads?.length" class="text-xs text-slate-500 mt-1 flex flex-wrap gap-2 items-center">
                            <span class="text-slate-400">PDF versions:</span>
                            <a v-for="u in r.uploads" :key="u.id"
                               :href="`/sahodaya-admin/${sahodaya.id}/board-results/${r.id}/pdf?version=${u.version}`"
                               class="underline text-indigo-700 hover:text-indigo-900">
                                v{{ u.version }}
                            </a>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 items-center">
                        <a v-if="r.result_pdf_path"
                           :href="`/sahodaya-admin/${sahodaya.id}/board-results/${r.id}/pdf`"
                           class="px-3 py-1.5 border border-slate-300 text-xs font-semibold rounded-lg hover:bg-slate-50">
                            Latest PDF
                        </a>
                        <template v-if="r.status === 'submitted'">
                            <button type="button" class="btn-secondary text-xs" @click="act(r, 'verify')">Verify</button>
                            <button type="button" class="px-3 py-1.5 border border-red-300 text-red-700 text-xs font-semibold rounded-lg"
                                    @click="reject(r)">Reject</button>
                        </template>
                        <template v-else-if="r.status === 'verified'">
                            <button type="button" class="btn-primary text-xs" @click="act(r, 'approve')">Approve</button>
                            <button type="button" class="px-3 py-1.5 border border-red-300 text-red-700 text-xs font-semibold rounded-lg"
                                    @click="reject(r)">Reject</button>
                        </template>
                        <template v-else-if="r.status === 'approved'">
                            <button type="button" class="btn-primary text-xs" @click="act(r, 'publish')">Publish</button>
                            <button type="button" class="px-3 py-1.5 border border-red-300 text-red-700 text-xs font-semibold rounded-lg"
                                    @click="reject(r)">Reject</button>
                        </template>
                    </div>
                </div>
            </div>
            <p v-if="!results.data.length" class="text-center text-slate-400 py-10">No board results in this queue.</p>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    results: Object,
    schoolNames: Object,
    filters: Object,
    statusOptions: Object,
    topperConfigs: { type: Array, default: () => [] },
    defaultTopN: { type: Number, default: 5 },
    selectedClass: { type: Number, default: null },
});

const pageTitle = computed(() => {
    if (props.selectedClass === 12) return 'Class XII Board Result Verification';
    if (props.selectedClass === 10) return 'Class X Board Result Verification';
    return 'Board Result Verification';
});

function statusHref(status) {
    const params = new URLSearchParams({ status });
    if (props.selectedClass) params.set('class', props.selectedClass);
    return `/sahodaya-admin/${props.sahodaya.id}/board-results/verification?${params.toString()}`;
}

function act(r, action) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/board-results/${r.id}/${action}`, {}, { preserveScroll: true });
}

function reject(r) {
    const reason = window.prompt('Rejection reason (required):');
    if (!reason) return;
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/board-results/${r.id}/reject`,
        { rejection_reason: reason },
        { preserveScroll: true },
    );
}
</script>
