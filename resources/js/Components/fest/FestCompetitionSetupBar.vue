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

        <div v-if="showAddHead" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 overflow-y-auto py-8" @click.self="closeAddHead">
            <form @submit.prevent="createHead" class="card w-full max-w-2xl shadow-xl space-y-4 my-auto">
                <div>
                    <h3 class="section-title">Add item head</h3>
                    <p class="section-desc text-xs mt-1">
                        Create a sports head such as Athletics, Chess, or Aquatics — each head runs like its own
                        independent event, so its fees and policy are set right here, not later.
                    </p>
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

                <div class="border-t border-slate-100 pt-4 space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fees &amp; policy for this head</p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <FormField label="School fee (₹)" hint="Once per school">
                            <input v-model.number="form.school_registration_fee" type="number" min="0" class="field" placeholder="0">
                        </FormField>
                        <FormField label="Student fee (₹)" hint="Per student under this head">
                            <input v-model.number="form.student_registration_fee" type="number" min="0" class="field" placeholder="0">
                        </FormField>
                        <FormField label="Team fee (₹)" hint="Per team entry">
                            <input v-model.number="form.team_registration_fee" type="number" min="0" class="field" placeholder="0">
                        </FormField>
                        <FormField label="Free quota (items/student)" hint="0 = no free items">
                            <input v-model.number="form.included_items_per_student" type="number" min="0" class="field" placeholder="0">
                        </FormField>
                        <FormField label="Free quota (teams/student)" hint="0 = no free teams">
                            <input v-model.number="form.included_teams" type="number" min="0" class="field" placeholder="0">
                        </FormField>
                        <FormField label="Max participants" hint="Leave blank for no cap">
                            <input v-model.number="form.max_participants" type="number" min="0" class="field" placeholder="—">
                        </FormField>
                        <FormField label="Max teams" hint="Leave blank for no cap">
                            <input v-model.number="form.max_teams" type="number" min="0" class="field" placeholder="—">
                        </FormField>
                        <FormField label="Students eligible">
                            <select v-model="form.verification_policy" class="field">
                                <option value="all_students">All students</option>
                                <option value="verified_only">Verified students only</option>
                            </select>
                        </FormField>
                        <FormField label="Approval">
                            <select v-model="form.approval_policy" class="field">
                                <option value="auto">Auto (on full payment)</option>
                                <option value="manual">Manual review</option>
                            </select>
                        </FormField>
                    </div>
                </div>

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

const form = useForm({
    name: '',
    sport_discipline: '',
    is_team_heading: true,
    school_registration_fee: '',
    student_registration_fee: '',
    team_registration_fee: '',
    included_items_per_student: 0,
    included_teams: 0,
    verification_policy: 'all_students',
    approval_policy: 'auto',
    max_participants: '',
    max_teams: '',
});
const syncing = ref(false);
const showAddHead = ref(false);

function createHead() {
    form.post(`/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}/item-heads`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
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
