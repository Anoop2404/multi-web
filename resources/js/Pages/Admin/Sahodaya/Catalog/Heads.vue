<template>
    <SahodayaEventsLayout :title="pageTitle" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :program="program"
                         :program-events="events" :show-header-title="false">
        <PageHeader
            :title="pageTitle"
            eyebrow="Event Heads"
            description="Main heads (Chess, Athletics…) group sub-items. One ID card per head — use Sample ID card to demo the layout to clients."
        >
            <template #actions>
                <Link :href="masterUrl" class="btn-secondary text-sm">Items & fees →</Link>
                <button type="button" class="btn-secondary text-sm" @click="syncHeads">Sync & link items</button>
            </template>
        </PageHeader>

        <CatalogSubNav :sahodaya-id="sahodaya.id" :program-slug="program.slug" :event-type="program.eventType" active="heads" />

        <form @submit.prevent="createHead" class="card mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">
            <FormField label="Head name">
                <input v-model="form.name" class="field" required placeholder="e.g. Chess">
            </FormField>
            <FormField label="Sport discipline">
                <select v-model="form.sport_discipline" class="field">
                    <option value="">Any</option>
                    <option v-for="(label, key) in disciplines" :key="key" :value="key">{{ label }}</option>
                </select>
            </FormField>
            <label class="flex items-center gap-2 text-sm pb-2">
                <input type="checkbox" v-model="form.is_team_heading"> ID card heading
            </label>
            <div>
                <button type="submit" class="btn-primary" :disabled="form.processing">Add head</button>
            </div>
        </form>

        <div v-if="unassignedItems.length" class="notice-banner notice-banner--warning mb-4 text-sm">
            {{ unassignedItems.length }} catalog item(s) have no head assigned.
            <Link :href="masterUrl" class="link-brand ml-1">Assign on Items & fees →</Link>
        </div>

        <div class="space-y-4">
            <div v-for="head in heads" :key="head.id" class="card">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ head.name }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ head.items?.length ?? 0 }} item(s)</p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <a :href="`${catalogBase}/heads/${head.id}/sample-id-card`"
                           target="_blank" rel="noopener"
                           class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                            Sample ID card ↗
                        </a>
                        <span v-if="head.is_team_heading" class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">ID card heading</span>
                    </div>
                </div>
                <div v-if="head.items?.length" class="overflow-x-auto">
                    <table class="data-table text-sm">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="w-24">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in head.items" :key="item.id">
                                <td>{{ item.title }}</td>
                                <td>
                                    <span :class="item.is_enabled ? 'text-emerald-700 text-xs font-medium' : 'text-slate-400 text-xs'">
                                        {{ item.is_enabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-slate-400 italic">No items linked — sync CKSC items or assign head when adding items.</p>
            </div>
            <EmptyState v-if="!heads.length" title="No Event Heads" description="Sync from CKSC definitions or add a head above." icon="📂" />
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import CatalogSubNav from '@/Components/sahodaya/CatalogSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import { sahodayaCatalogHref } from '@/support/sahodayaPrograms.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    heads: { type: Array, default: () => [] },
    unassignedItems: { type: Array, default: () => [] },
    disciplines: { type: Object, default: () => ({}) },
    events: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const catalogBase = computed(() => sahodayaCatalogHref(props.sahodaya.id, props.program.slug));
const masterUrl = computed(() => `${catalogBase.value}/master`);
const pageTitle = computed(() => `${props.program.label} — Event Heads`);

const form = useForm({ name: '', sport_discipline: '', is_team_heading: true });

function createHead() {
    form.post(`${catalogBase.value}/heads`, {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'sport_discipline'),
    });
}

function syncHeads() {
    router.post(`${catalogBase.value}/heads/sync`, {}, { preserveScroll: true });
}
</script>
