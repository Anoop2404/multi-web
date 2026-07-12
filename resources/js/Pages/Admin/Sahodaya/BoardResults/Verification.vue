<template>
    <SahodayaAdminLayout title="Board result verification" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Board result verification" eyebrow="Academic Results"
                    description="Review CBSE board results submitted by member schools — verify, approve, reject, or publish.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/masters`" class="btn-secondary text-sm">Masters</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/reports`" class="btn-secondary text-sm">Reports</Link>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-2 mb-4">
            <Link v-for="(label, value) in statusOptions" :key="value"
                  :href="`/sahodaya-admin/${sahodaya.id}/board-results/verification?status=${value}`"
                  class="px-3 py-1.5 rounded-lg text-sm font-semibold border"
                  :class="filters.status === value ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200 text-slate-600'">
                {{ label }}
            </Link>
        </div>

        <div class="card !p-4 mb-4">
            <h3 class="text-sm font-semibold text-slate-800 mb-2">Top-N toppers (Sahodaya-wide)</h3>
            <p class="text-xs text-slate-500 mb-3">
                Default cap: {{ defaultTopN }}. Schools cannot add more overall toppers than this limit.
            </p>
            <form class="flex flex-wrap gap-3 items-end" @submit.prevent="saveTopN">
                <div>
                    <label class="form-label mb-1 text-xs">Class</label>
                    <select v-model="topNForm.class" class="field text-sm">
                        <option :value="null">All</option>
                        <option :value="10">Class X</option>
                        <option :value="12">Class XII</option>
                    </select>
                </div>
                <div>
                    <label class="form-label mb-1 text-xs">Top N</label>
                    <input v-model.number="topNForm.top_n" type="number" min="1" max="50" class="field text-sm w-24" required>
                </div>
                <button type="submit" class="btn-secondary text-xs">Save Top-N</button>
            </form>
            <ul v-if="topperConfigs?.length" class="mt-3 text-xs text-slate-600 space-y-1">
                <li v-for="c in topperConfigs" :key="c.id">
                    Class {{ c.class ?? 'all' }} · {{ c.scope }} → <strong>{{ c.top_n }}</strong>
                </li>
            </ul>
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
import { reactive } from 'vue';
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
});

const topNForm = reactive({
    class: null,
    scope: 'overall',
    top_n: props.defaultTopN,
});

function saveTopN() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/board-results/topper-cap`, {
        class: topNForm.class,
        scope: topNForm.scope,
        top_n: topNForm.top_n,
    }, { preserveScroll: true });
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
