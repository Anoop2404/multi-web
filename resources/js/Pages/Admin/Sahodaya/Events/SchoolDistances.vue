<template>
    <SahodayaEventsLayout :title="`${event.title} — School Distances`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — School Distances`" eyebrow="Schedule"
                    description="Set each school's distance (km) from the venue. Reporting batch listings sort schools closest-first when a distance is set — schools with no distance keep the usual alphabetical order, after the ones that do.">
            <template #actions>
                <Link :href="`${base}/reporting-batches`" class="btn-secondary text-sm">&larr; Back to Reporting Batches</Link>
                <button type="button" class="btn-primary text-sm" :disabled="saving" @click="saveAll">
                    {{ saving ? 'Saving…' : '💾 Save distances' }}
                </button>
            </template>
        </PageHeader>

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="reporting-batches" class="mb-4" />

        <div class="card">
            <input v-model="search" type="search" class="field text-sm mb-3" placeholder="Search by school name…">

            <div class="max-h-[32rem] overflow-y-auto rounded-xl border border-slate-200">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 sticky top-0">
                        <tr>
                            <th class="p-2">School</th>
                            <th class="p-2 w-40">Distance (km)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in filteredRows" :key="row.id" class="bg-white">
                            <td class="p-2">{{ row.name }}</td>
                            <td class="p-2">
                                <input v-model.number="values[row.id]" type="number" min="0" step="0.1" class="field !py-1 !text-xs w-32" placeholder="—">
                            </td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="2" class="p-4 text-center text-sm text-slate-400">No schools match "{{ search }}".</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    schools: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const search = ref('');
const saving = ref(false);

const values = reactive(Object.fromEntries(props.schools.map((s) => [s.id, s.distance_km])));

const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.schools;
    return props.schools.filter((s) => s.name?.toLowerCase().includes(q));
});

function saveAll() {
    saving.value = true;
    router.post(`${base}/school-distances`, {
        distances: props.schools.map((s) => ({
            school_id: s.id,
            distance_km: values[s.id] === '' || values[s.id] === undefined ? null : values[s.id],
        })),
    }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
}
</script>
