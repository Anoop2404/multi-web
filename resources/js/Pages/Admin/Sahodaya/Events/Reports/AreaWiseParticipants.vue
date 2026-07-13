<template>
    <SahodayaEventsLayout :title="`${event.title} — Area-wise participants`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Area-wise participants`" eyebrow="Reports"
                    description="Participants grouped by competition area (custom / new competition types).">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/reports`" class="btn-secondary text-sm">
                    All report types
                </Link>
                <a :href="xlsUrl" class="btn-secondary text-sm">Export spreadsheet ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="area-wise-participants" />

        <form @submit.prevent="applyFilter" class="card !p-4 mb-4 flex flex-wrap gap-3 items-end">
            <FormField label="Area" classExtra="mb-0">
                <select v-model="areaFilter" class="field text-sm w-56">
                    <option value="">All areas</option>
                    <option v-for="a in areas" :key="a.id" :value="String(a.id)">{{ a.name }}</option>
                    <option value="other">Unassigned items</option>
                </select>
            </FormField>
            <FormField label="School" classExtra="mb-0">
                <select v-model="schoolFilter" class="field text-sm w-56">
                    <option value="">All schools</option>
                    <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </FormField>
            <button type="submit" class="btn-primary text-sm">Apply</button>
        </form>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.areas }}</p>
                <p class="text-xs text-slate-500 mt-1">Areas</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.registrations }}</p>
                <p class="text-xs text-slate-500 mt-1">Registrations</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ totals.participants }}</p>
                <p class="text-xs text-slate-500 mt-1">Participants</p>
            </div>
        </div>

        <section class="mb-8">
            <h3 class="section-title mb-3">Summary by area</h3>
            <div class="card overflow-hidden p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Items</th>
                            <th>Regs</th>
                            <th>Approved</th>
                            <th>Pending</th>
                            <th>Participants</th>
                            <th>Default fee ₹</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in summary" :key="row.area_id">
                            <td class="font-medium">{{ row.area_name }}</td>
                            <td>{{ row.item_count }}</td>
                            <td>{{ row.registration_count ?? 0 }}</td>
                            <td>{{ row.approved_count ?? 0 }}</td>
                            <td>{{ row.pending_count ?? 0 }}</td>
                            <td>{{ row.participant_count }}</td>
                            <td>{{ row.default_item_fee != null ? Number(row.default_item_fee).toLocaleString('en-IN') : '—' }}</td>
                        </tr>
                        <tr v-if="!summary.length">
                            <td colspan="7" class="p-6 text-center text-slate-400">
                                No competition areas yet. Create areas under Event home → Competition areas.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            <h3 class="section-title mb-3">Participant list</h3>
            <div class="card overflow-hidden p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>School</th>
                            <th>Participant</th>
                            <th>Reg no</th>
                            <th>Item</th>
                            <th>Fest ID</th>
                            <th>Item reg</th>
                            <th>Chest</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in rows" :key="`${row.area_id}-${row.item_id}-${idx}`">
                            <td>{{ row.area_name }}</td>
                            <td>{{ row.school }}</td>
                            <td class="font-medium">{{ row.student }}</td>
                            <td>{{ row.reg_no }}</td>
                            <td>{{ row.item }}</td>
                            <td>{{ row.fest_id ?? '—' }}</td>
                            <td class="font-mono text-xs">{{ row.item_reg ?? '—' }}</td>
                            <td>{{ row.chest_no ?? '—' }}</td>
                        </tr>
                        <tr v-if="!rows.length && summary.length">
                            <td colspan="8" class="p-6 text-center text-slate-400">No participants for the selected filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    summary: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    areas: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    filterAreaId: { type: [String, Number], default: null },
    filterSchoolId: { type: String, default: null },
    xlsUrl: String,
});

const areaFilter = ref(props.filterAreaId ? String(props.filterAreaId) : '');
const schoolFilter = ref(props.filterSchoolId || '');

const totals = computed(() => ({
    areas: props.summary.length,
    items: props.summary.reduce((n, r) => n + (r.item_count || 0), 0),
    registrations: props.summary.reduce((n, r) => n + (r.registration_count || 0), 0),
    participants: props.summary.reduce((n, r) => n + (r.participant_count || 0), 0),
}));

function applyFilter() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/area-wise-participants`, {
        area_id: areaFilter.value || undefined,
        school_id: schoolFilter.value || undefined,
    }, { preserveState: true, replace: true });
}
</script>
