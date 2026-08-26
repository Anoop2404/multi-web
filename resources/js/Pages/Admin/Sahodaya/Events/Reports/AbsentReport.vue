<template>
    <SahodayaEventsLayout :title="`${event.title} — Absent report`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Absent report`" eyebrow="Reports"
                    description="Every participant marked absent, item by item.">
            <template #actions>
                <a :href="csvUrl" target="_blank" rel="noopener" class="btn-secondary text-sm">Download CSV ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="absent-report" />

        <div class="card mb-4 !py-3">
            <div class="grid gap-3 md:grid-cols-3">
                <label v-if="hasAnyPhase" class="text-xs font-semibold text-slate-600">Phase
                    <SearchableSelect v-model="phaseFilter" class="mt-1 w-full" :options="phaseOptions" :all-option="true" all-label="All phases" />
                </label>
                <label v-if="hasAnyRegion" class="text-xs font-semibold text-slate-600">Region
                    <SearchableSelect v-model="regionFilter" class="mt-1 w-full" :options="regionOptions" :all-option="true" all-label="All regions" />
                </label>
                <label class="text-xs font-semibold text-slate-600 md:col-span-1">Search
                    <input v-model="search" type="text" placeholder="Item, school, or participant…" class="field mt-1 text-sm w-full" />
                </label>
            </div>
        </div>

        <div class="card card--flush overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50/80">
                <h3 class="section-title text-sm">{{ filteredRows.length }} absentee{{ filteredRows.length === 1 ? '' : 's' }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full text-sm">
                    <thead>
                        <tr>
                            <th>Sl No</th>
                            <th>Category</th>
                            <th>Item</th>
                            <th v-if="hasAnyPhase">Phase</th>
                            <th v-if="hasAnyRegion">Region</th>
                            <th>School</th>
                            <th>Participant</th>
                            <th>Reg no</th>
                            <th>Chest</th>
                            <th>Marked by</th>
                            <th>Marked at</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, idx) in filteredRows" :key="`${r.item_id}-${r.reg_no}-${idx}`">
                            <td class="text-xs text-slate-400">{{ idx + 1 }}</td>
                            <td class="text-xs">{{ r.category_label }}</td>
                            <td class="font-medium">
                                {{ r.item_title }}
                                <span v-if="r.item_code" class="block font-mono text-xs text-slate-400">{{ r.item_code }}</span>
                            </td>
                            <td v-if="hasAnyPhase" class="text-xs">{{ r.phase_name ?? '—' }}</td>
                            <td v-if="hasAnyRegion" class="text-xs">{{ r.region_name ?? '—' }}</td>
                            <td class="text-xs">{{ (r.school_name || '').toUpperCase() }}</td>
                            <td>{{ r.participant }}</td>
                            <td class="font-mono text-xs">{{ r.reg_no ?? '—' }}</td>
                            <td class="font-mono text-xs">{{ r.chest_no ?? '—' }}</td>
                            <td class="text-xs">{{ r.marked_by ?? '—' }}</td>
                            <td class="text-xs whitespace-nowrap">{{ r.marked_at ?? '—' }}</td>
                        </tr>
                        <tr v-if="!filteredRows.length">
                            <td colspan="11" class="p-8 text-center text-slate-400">No absentees match the current filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    rows: { type: Array, default: () => [] },
    csvUrl: String,
});

const phaseFilter = ref('');
const regionFilter = ref('');
const search = ref('');

const phaseOptions = computed(() => [...new Set(props.rows.map((r) => r.phase_name).filter(Boolean))]);
const regionOptions = computed(() => [...new Set(props.rows.map((r) => r.region_name).filter(Boolean))]);
const hasAnyPhase = computed(() => phaseOptions.value.length > 0);
const hasAnyRegion = computed(() => regionOptions.value.length > 0);

const filteredRows = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.rows.filter((r) => {
        if (phaseFilter.value && r.phase_name !== phaseFilter.value) return false;
        if (regionFilter.value && r.region_name !== regionFilter.value) return false;
        if (!term) return true;

        return [r.item_title, r.school_name, r.participant].filter(Boolean).some((v) => v.toLowerCase().includes(term));
    });
});
</script>
