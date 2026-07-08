<template>
    <div class="card mb-6 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="section-title">Item heads</h3>
                <p class="section-desc text-xs">
                    Heads group sports items for ID cards, registration windows, and competition dates.
                    Sync from catalog or add a custom head, then open a section to set dates and fees.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <Link v-if="taxonomyMastersUrl" :href="taxonomyMastersUrl" class="btn-secondary text-sm">Category masters →</Link>
                <button type="button" class="btn-primary text-sm" @click="showAddHead = true">Add head</button>
                <button type="button" class="btn-secondary text-sm" :disabled="syncing" @click="syncHeads">
                    {{ syncing ? 'Syncing…' : 'Sync from catalog' }}
                </button>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
            Flow: create item heads first, then add/list items under each head, then schedule the head or individual items.
        </div>

        <div v-if="showAddHead" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeAddHead">
            <form @submit.prevent="createHead" class="card w-full max-w-lg shadow-xl space-y-4">
                <div>
                    <h3 class="section-title">Add item head</h3>
                    <p class="section-desc text-xs mt-1">Create a sports head such as Athletics, Chess, or Aquatics.</p>
                </div>

                <FormField label="Head name">
                    <input v-model="form.name" class="field" required placeholder="e.g. Chess">
                </FormField>
                <FormField label="Sport discipline">
                    <select v-model="form.sport_discipline" class="field">
                        <option value="">Any</option>
                        <option v-for="(label, key) in disciplines" :key="key" :value="key">{{ label }}</option>
                    </select>
                </FormField>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="form.is_team_heading"> Use as ID card heading
                </label>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="btn-secondary" @click="closeAddHead">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Add head</button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    disciplines: { type: Object, default: () => ({}) },
    taxonomyMastersUrl: { type: String, default: null },
});

const form = useForm({ name: '', sport_discipline: '', is_team_heading: true });
const syncing = ref(false);
const showAddHead = ref(false);

function createHead() {
    form.post(`/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}/item-heads`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name', 'sport_discipline');
            showAddHead.value = false;
        },
    });
}

function closeAddHead() {
    showAddHead.value = false;
    form.clearErrors();
}

function syncHeads() {
    syncing.value = true;
    router.post(`/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}/item-heads/sync`, {}, {
        preserveScroll: true,
        onFinish: () => { syncing.value = false; },
    });
}
</script>
