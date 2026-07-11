<template>
    <SahodayaAdminLayout title="Academic Excellence Report" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Academic Excellence + Historical Comparison" eyebrow="Academic Results"
                    :description="`Source: ${report.source}. Awards when available; otherwise rankings and pass-% trends.`" />

        <form class="flex flex-wrap gap-3 mb-4" @submit.prevent="apply">
            <input v-model="year" class="field w-40" placeholder="2024-25">
            <button type="submit" class="btn-primary text-sm">Apply</button>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/reports`" class="text-sm text-slate-500 self-center">← Reports</Link>
        </form>

        <div class="grid lg:grid-cols-2 gap-4 mb-4">
            <div class="card">
                <h3 class="font-semibold text-[#0f3d7a] mb-3">Awards ({{ report.academic_year }})</h3>
                <ul class="space-y-2 text-sm">
                    <li v-for="a in report.awards" :key="a.id" class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                        <span>
                            <span class="font-medium">{{ a.title }}</span>
                            <span class="text-slate-500"> — {{ a.school_name }}</span>
                        </span>
                        <span class="text-slate-600">{{ a.score ?? '—' }}</span>
                    </li>
                    <li v-if="!report.awards?.length" class="text-slate-400">No awards computed yet. Publish results to generate.</li>
                </ul>
            </div>
            <div class="card">
                <h3 class="font-semibold text-[#0f3d7a] mb-3">Top schools (pass %)</h3>
                <ul class="space-y-2 text-sm">
                    <li v-for="s in report.top_schools" :key="s.school_id + '-' + s.rank" class="flex justify-between gap-2">
                        <span>#{{ s.rank }} {{ s.school_name }}</span>
                        <span>{{ s.pass_percent ?? s.score }}%</span>
                    </li>
                    <li v-if="!report.top_schools?.length" class="text-slate-400">No rankings yet.</li>
                </ul>
            </div>
        </div>

        <div class="card !p-0 overflow-x-auto">
            <table class="data-table min-w-[640px]">
                <thead>
                    <tr>
                        <th>Academic year</th>
                        <th>Avg pass %</th>
                        <th>Schools reported</th>
                        <th>Published</th>
                        <th>Ranked</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in report.year_comparison" :key="row.academic_year">
                        <td>{{ row.academic_year }}</td>
                        <td>{{ row.avg_pass_percent }}%</td>
                        <td>{{ row.schools_reported }}</td>
                        <td>{{ row.published_count }}</td>
                        <td>{{ row.ranked_schools }}</td>
                    </tr>
                    <tr v-if="!report.year_comparison?.length">
                        <td colspan="5" class="p-8 text-center text-slate-400">No historical data yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    report: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const year = ref(props.filters.academic_year || '');

function apply() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/board-results/reports/excellence`, {
        academic_year: year.value,
    }, { preserveState: true });
}
</script>
