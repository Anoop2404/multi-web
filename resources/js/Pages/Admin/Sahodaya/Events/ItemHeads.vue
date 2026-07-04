<template>
    <SahodayaEventsLayout :title="`${event.title} — Item heads`" :sahodaya="sahodaya" :event="event" :show-header-title="false">
        <PageHeader :title="`${event.title} — Item heads`" eyebrow="Sports catalog" description="Main heads (Chess, Athletics…) group sub-items for admins, ID cards, and discipline staff.">
            <template #actions>
                <Link :href="taxonomyMastersUrl" class="btn-secondary text-sm">Category masters →</Link>
                <button type="button" class="btn-secondary text-sm" @click="syncHeads">Sync from catalog</button>
            </template>
        </PageHeader>

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

        <div class="space-y-4">
            <div v-for="head in heads" :key="head.id" class="card">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h3 class="font-semibold text-slate-900">{{ head.name }}</h3>
                    <span v-if="head.is_team_heading" class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">ID card heading</span>
                </div>
                <ul class="text-sm text-slate-600 space-y-1">
                    <li v-for="item in head.items" :key="item.id">{{ item.title }}</li>
                    <li v-if="!head.items?.length" class="text-slate-400 italic">No items linked yet — assign head on item edit</li>
                </ul>
            </div>
            <EmptyState v-if="!heads.length" title="No item heads" description="Sync from catalog or add a head above." icon="📂" />
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    heads: { type: Array, default: () => [] },
    disciplines: { type: Object, default: () => ({}) },
    taxonomyMastersUrl: String,
});

const form = useForm({ name: '', sport_discipline: '', is_team_heading: true });

function createHead() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/item-heads`, {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'sport_discipline'),
    });
}

function syncHeads() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/item-heads/sync`, {}, { preserveScroll: true });
}
</script>
