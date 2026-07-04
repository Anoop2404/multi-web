<template>
    <SahodayaEventsLayout :title="`${event.title} — Reports`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Downloads`" eyebrow="Reports"
                    :description="phaseLabels[phase] + ' exports with optional filters.'" />

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" :active="phase" />

        <EmptyState v-if="!exports.length" title="No exports" :description="`No ${phase} exports available for this event phase.`" icon="📥" class="mb-6" />
        <div v-else class="space-y-4 mb-8">
            <div v-for="exp in exports" :key="exp.id" class="card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-sm text-slate-900">{{ exp.label }}</p>
                        <p class="text-xs uppercase tracking-wide text-slate-400">{{ exp.format }}</p>
                    </div>
                    <a :href="exportUrl(exp)" target="_blank" rel="noopener" class="btn-primary text-xs px-3 py-2">Download</a>
                </div>
                <div v-if="exp.params?.length" class="mt-4 flex flex-wrap gap-3">
                    <FormField v-if="exp.params.includes('school_id')" label="School" class-extra="min-w-[12rem]">
                        <template #default="{ id }">
                            <select :id="id" v-model="params[exp.id].school_id" class="field text-sm">
                                <option value="">All schools</option>
                                <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </template>
                    </FormField>
                    <FormField v-if="exp.params.includes('item_id')" label="Item" class-extra="min-w-[12rem]">
                        <template #default="{ id }">
                            <select :id="id" v-model="params[exp.id].item_id" class="field text-sm">
                                <option value="">Select item</option>
                                <option v-for="i in items" :key="i.id" :value="i.id">{{ i.title }}</option>
                            </select>
                        </template>
                    </FormField>
                    <FormField v-if="exp.params.includes('class_group')" label="Class" class-extra="min-w-[10rem]">
                        <template #default="{ id }">
                            <select :id="id" v-model="params[exp.id].class_group" class="field text-sm">
                                <option value="">All</option>
                                <option v-for="(label, key) in classGroups" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </template>
                    </FormField>
                    <FormField v-if="exp.params.includes('date')" label="Date">
                        <template #default="{ id }">
                            <input :id="id" v-model="params[exp.id].date" type="date" class="field text-sm">
                        </template>
                    </FormField>
                    <FormField v-if="exp.params.includes('top_n')" label="Top N">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="params[exp.id].top_n" type="number" min="1" max="50" class="field w-24 text-sm">
                        </template>
                    </FormField>
                    <FormField v-if="exp.params.includes('head_id')" label="Item head" class-extra="min-w-[12rem]">
                        <template #default="{ id }">
                            <select :id="id" v-model="params[exp.id].head_id" class="field text-sm">
                                <option value="">All heads</option>
                                <option v-for="h in heads" :key="h.id" :value="h.id">{{ h.name }}</option>
                            </select>
                        </template>
                    </FormField>
                    <FormField v-if="exp.params.includes('stage_id')" label="Stage" class-extra="min-w-[12rem]">
                        <template #default="{ id }">
                            <select :id="id" v-model="params[exp.id].stage_id" class="field text-sm">
                                <option value="">All stages</option>
                                <option v-for="st in stages" :key="st.id" :value="st.id">{{ st.name }}</option>
                            </select>
                        </template>
                    </FormField>
                    <FormField v-if="exp.params.includes('audience')" label="Audience" class-extra="min-w-[10rem]">
                        <template #default="{ id }">
                            <select :id="id" v-model="params[exp.id].audience" class="field text-sm">
                                <option value="">Staff</option>
                                <option value="public">Public</option>
                            </select>
                        </template>
                    </FormField>
                </div>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { reactive } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, phase: String, exports: Array,
    schools: Array, items: Array, heads: Array, stages: Array, classGroups: Object,
    activityLogs: { type: Array, default: () => [] },
});

const phaseLabels = { before: 'Before event', during: 'During event', after: 'After event' };

const params = reactive({});
for (const exp of props.exports ?? []) {
    params[exp.id] = {};
    for (const p of exp.params ?? []) params[exp.id][p] = '';
}

function exportUrl(exp) {
    const q = new URLSearchParams(Object.fromEntries(
        Object.entries(params[exp.id] ?? {}).filter(([, v]) => v !== '' && v != null)
    ));
    const qs = q.toString();
    return exp.href + (qs ? `?${qs}` : '');
}
</script>
