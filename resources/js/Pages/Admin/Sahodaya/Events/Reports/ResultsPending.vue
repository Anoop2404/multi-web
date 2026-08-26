<template>
    <SahodayaEventsLayout :title="`${event.title} — Results pending`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Results pending`" eyebrow="Reports"
                    description="Items where every performer already has marks entered, but results haven't been published yet.">
            <template #actions>
                <a :href="csvUrl" target="_blank" rel="noopener" class="btn-secondary text-sm">Download CSV ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="results-pending" />

        <div class="card mb-4 !py-3">
            <label class="text-xs font-semibold text-slate-600">Search
                <input v-model="search" type="text" placeholder="Item or head name…" class="field mt-1 text-sm w-full max-w-sm" />
            </label>
        </div>

        <div class="card card--flush overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50/80">
                <h3 class="section-title text-sm">{{ filteredRows.length }} item{{ filteredRows.length === 1 ? '' : 's' }} ready to publish</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full text-sm">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Head</th>
                            <th>Category</th>
                            <th>Performers</th>
                            <th>Marks entered</th>
                            <th>Judges assigned</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in filteredRows" :key="row.item_id">
                            <td class="font-medium">
                                {{ row.title }}
                                <span v-if="row.item_code" class="block font-mono text-xs text-slate-400">{{ row.item_code }}</span>
                            </td>
                            <td class="text-xs">{{ row.head_name ?? '—' }}</td>
                            <td class="text-xs">{{ row.category_label ?? '—' }}</td>
                            <td>{{ row.performers }}</td>
                            <td class="text-emerald-700 font-medium">{{ row.marks_entered }}</td>
                            <td>{{ row.judges_assigned }}</td>
                            <td>
                                <Link :href="resultsPageHref" class="btn-primary text-xs">Publish →</Link>
                            </td>
                        </tr>
                        <tr v-if="!filteredRows.length">
                            <td colspan="7" class="p-8 text-center text-slate-400">Nothing pending — every marked item is either not yet ready or already published.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    rows: { type: Array, default: () => [] },
    csvUrl: String,
});

const search = ref('');

const filteredRows = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.rows;

    return props.rows.filter((r) => [r.title, r.head_name].filter(Boolean).some((v) => v.toLowerCase().includes(term)));
});

const resultsPageHref = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results`;
</script>
