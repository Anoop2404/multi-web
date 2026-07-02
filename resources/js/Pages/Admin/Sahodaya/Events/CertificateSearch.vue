<template>
    <SahodayaEventsLayout title="Certificate Search" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Certificate search" eyebrow="Tools"
                    :description="searchScope" />

        <form @submit.prevent="search" class="flex flex-wrap gap-3 mb-4 max-w-xl">
            <FormField label="Search" class-extra="flex-1 min-w-[12rem]">
                <template #default="{ id }">
                    <input :id="id" v-model="q" class="field" placeholder="Name, reg no, or chest no" minlength="2">
                </template>
            </FormField>
            <div class="flex items-end">
                <button type="submit" class="btn-primary">Search</button>
            </div>
        </form>

        <div class="form-section overflow-hidden !p-0">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[720px]">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Reg / Chest</th>
                            <th>Event</th>
                            <th>Item</th>
                            <th>Certificate</th>
                        </tr>
                    </thead>
                    <tbody>
                    <tr v-for="row in results" :key="row.participant_id">
                        <td>{{ row.name }}<p class="text-xs text-slate-400">{{ row.school }}</p></td>
                        <td class="font-mono text-xs">{{ row.reg_no || '—' }} / {{ row.chest_no || '—' }}</td>
                        <td>{{ row.event }}</td>
                        <td>{{ row.item }}</td>
                        <td>
                            <template v-if="row.certificate">
                                <span :class="row.certificate.collected_at ? 'text-emerald-600' : 'text-amber-600'">
                                    {{ row.certificate.cert_type }}{{ row.certificate.collected_at ? ' ✓' : '' }}
                                </span>
                                <button v-if="!row.certificate.collected_at" type="button" @click="collect(row.certificate.id)"
                                        class="ml-2 link-brand text-xs">Mark collected</button>
                            </template>
                            <span v-else class="text-slate-400">None</span>
                        </td>
                    </tr>
                    <tr v-if="!results.length"><td colspan="5" class="p-6 text-center text-slate-400">Enter at least 2 characters to search</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    query: String, results: Array, event: { type: Object, default: null },
});

const page = usePage();
const scopedEvent = computed(() => props.event ?? page.props.event ?? null);
const searchScope = computed(() => scopedEvent.value
    ? `Searching certificates for ${scopedEvent.value.title}.`
    : 'Find certificates across events and mark collection status.');

const q = ref(props.query || '');

function search() {
    const params = { q: q.value };
    if (scopedEvent.value?.id) {
        params.event_id = scopedEvent.value.id;
    }
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/certificates/search`, params, { preserveState: true });
}

function collect(certificateId) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/certificates/${certificateId}/collect`, {}, { preserveScroll: true });
}
</script>

