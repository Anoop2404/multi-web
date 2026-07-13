<template>
    <div class="card mb-6 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="section-title">Event Heads</h3>
                <p class="section-desc text-xs">
                    Each Event Head (Athletics, Chess…) owns its own fees, policy, and schedule — like a discipline event under this Sports Meet.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <Link v-if="taxonomyMastersUrl" :href="taxonomyMastersUrl" class="btn-secondary text-sm">Category masters →</Link>
                <button type="button" class="btn-primary text-sm" @click="showAddHead = true">Add Event Head</button>
                <button type="button" class="btn-secondary text-sm" :disabled="syncing" @click="syncHeads">
                    {{ syncing ? 'Syncing…' : 'Sync from catalog' }}
                </button>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 space-y-2">
            <p>
                Flow: create Event Heads with fees → add items under each head → set schedule → open registration.
            </p>
            <p v-if="promoteStatus?.can_promote && sportsHubUrl">
                Next:
                <Link :href="sportsHubUrl" class="font-semibold link-brand">
                    Promote {{ promoteStatus.pending_count }} Event Head(s) into discipline events →
                </Link>
            </p>
            <p v-else-if="sportsHubUrl">
                Manage the season list and promote heads from the
                <Link :href="sportsHubUrl" class="font-semibold link-brand">Sports hub</Link>.
            </p>
        </div>

        <div v-if="showAddHead" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 overflow-y-auto py-8" @click.self="closeAddHead">
            <form @submit.prevent="createHead" class="card w-full max-w-2xl shadow-xl space-y-4 my-auto">
                <div>
                    <h3 class="section-title">Add Event Head</h3>
                    <p class="section-desc text-xs mt-1">
                        Fees and policy are set here — the same form is used when editing a head later.
                    </p>
                </div>

                <FormField label="Event Head name">
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

                <div class="border-t border-slate-100 pt-4 space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fees &amp; policy</p>
                    <FestHeadFeeFields v-model="feeFields" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="btn-secondary" @click="closeAddHead">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Add Event Head</button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import FestHeadFeeFields from '@/Components/fest/FestHeadFeeFields.vue';
import { emptyHeadFeeFields } from '@/support/festHeadFeeFields';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    disciplines: { type: Object, default: () => ({}) },
    taxonomyMastersUrl: { type: String, default: null },
    sportsHubUrl: { type: String, default: null },
    promoteStatus: { type: Object, default: null },
});

const form = useForm({
    name: '',
    sport_discipline: '',
    is_team_heading: true,
});
const feeFields = reactive(emptyHeadFeeFields());
const syncing = ref(false);
const showAddHead = ref(false);

watch(feeFields, (fields) => {
    Object.assign(form, fields);
}, { deep: true });

function createHead() {
    Object.assign(form, feeFields);
    form.post(`/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}/item-heads`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            Object.assign(feeFields, emptyHeadFeeFields());
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
