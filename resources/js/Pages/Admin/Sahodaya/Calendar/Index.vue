<template>
    <SahodayaAdminLayout title="Calendar" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Calendar" eyebrow="Programs"
                    description="Aggregated deadlines and event dates across membership, fest, Talent Search, and training.">
            <template #actions>
                <a v-if="icalUrl" :href="icalUrl" class="btn-secondary text-sm">Export iCal</a>
            </template>
        </PageHeader>

        <form class="flex flex-wrap gap-3 items-end mb-6" @submit.prevent="applyFilters">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">From</label>
                <input v-model="form.from" type="date" class="input-field text-sm" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">To</label>
                <input v-model="form.to" type="date" class="input-field text-sm" />
            </div>
            <button type="submit" class="btn-primary text-sm">Apply</button>
        </form>

        <div class="space-y-2">
            <div v-for="event in events" :key="event.id"
                 class="card !p-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ event.module }} · {{ event.kind }}</p>
                    <p class="font-semibold text-[#0f3d7a] mt-1">{{ event.title }}</p>
                    <p class="text-sm text-slate-600 mt-1">
                        {{ event.start }}<span v-if="event.end"> → {{ event.end }}</span>
                    </p>
                </div>
                <Link v-if="event.href" :href="event.href" class="text-sm font-semibold text-[#0f3d7a] hover:underline">
                    Open
                </Link>
            </div>
            <p v-if="!events.length" class="text-center text-slate-400 py-10">No events in this date range.</p>
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
    events: Array,
    filters: Object,
    icalUrl: String,
});

const form = reactive({ ...props.filters });

function applyFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/calendar`, form, { preserveState: true });
}
</script>
