<template>
    <SahodayaEventsLayout :title="`${event.title} — Judges`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Judges`" eyebrow="Registration"
                    description="Assign judges to event items." />
        <form @submit.prevent="assign" class="card mb-4 flex flex-wrap gap-2">
            <SearchableSelect
                v-model="form.item_id"
                :options="itemOptions"
                placeholder="Select item"
                search-placeholder="Type item name to search…"
                :all-option="false"
                class="max-w-xs"
            />
            <SearchableSelect
                v-model="form.user_id"
                :options="judgeOptions"
                :all-option="true"
                all-label="Select judge"
                :required="true"
            />
            <button class="btn-primary">Assign</button>
        </form>

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr><th class="p-3">Item</th><th class="p-3">Judge</th><th class="p-3"></th></tr>
                </thead>
                <tbody>
                    <tr v-for="a in assignments" :key="a.id" class="border-t">
                        <td class="p-3">{{ a.item?.title }}</td>
                        <td class="p-3">{{ a.user?.name }} <span class="text-gray-400 text-xs">{{ a.user?.email }}</span></td>
                        <td class="p-3 text-right">
                            <button @click="remove(a.id)" class="text-red-600 text-xs">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="!assignments.length"><td colspan="3" class="p-6 text-center text-gray-400">No judges assigned</td></tr>
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-500 mt-4">Judges log in at <code>/portal/judge/{{ sahodaya.id }}</code></p>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, assignments: Array, judges: Array,
    activityLogs: { type: Array, default: () => [] },
    classGroupLabels: { type: Object, default: () => ({}) },
});

const form = useForm({ item_id: '', user_id: '' });

const itemOptions = computed(() => (props.event?.items ?? []).map(item => {
    const category = item.class_group && item.class_group !== 'open'
        ? (props.classGroupLabels?.[item.class_group] ?? String(item.class_group).toUpperCase())
        : null;
    return { id: item.id, name: category ? `${item.title} — ${category}` : item.title };
}));

const judgeOptions = computed(() => (props.judges ?? []).map(j => ({ value: j.id, label: `${j.name} (${j.email})` })));

function assign() {
    if (!form.item_id || !form.user_id) return;
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/judges`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function remove(id) {
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/judges/${id}`, { preserveScroll: true });
}
</script>

