<template>
    <div class="space-y-6 max-w-3xl">
        <form @submit.prevent="saveNumberingSettings" class="card space-y-4">
            <div>
                <h3 class="section-title">Registration & chest numbers</h3>
                <p class="section-desc">Starting numbers for event registration IDs, chest numbers, and item registration IDs.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <FormField label="Event reg no. start">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="numberingSettingsForm.event_reg_start" type="number" min="1" class="field">
                    </template>
                </FormField>
                <FormField label="Event reg prefix">
                    <template #default="{ id }">
                        <input :id="id" v-model="numberingSettingsForm.event_reg_prefix" type="text" class="field" placeholder="S-">
                    </template>
                </FormField>
                <FormField label="Chest no. start">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="numberingSettingsForm.chest_no_start" type="number" min="1" class="field">
                    </template>
                </FormField>
                <FormField label="Chest no. prefix">
                    <template #default="{ id }">
                        <input :id="id" v-model="numberingSettingsForm.chest_no_prefix" type="text" class="field">
                    </template>
                </FormField>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" v-model="numberingSettingsForm.auto_assign_chest_on_create">
                Auto-assign chest numbers when registration is created
            </label>
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="numberingSettingsForm.processing">Save numbering settings</button>
            </FormActions>
        </form>

        <section class="card space-y-2 text-sm text-slate-600">
            <p>Use <strong>Chest numbers</strong> page actions to bulk-assign missing chest numbers and item registration IDs for late-added participants.</p>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/chest-numbers`" class="btn-secondary text-sm inline-flex">Open chest numbers →</Link>
        </section>
    </div>
</template>

<script setup>
import { inject } from 'vue';
import { Link } from '@inertiajs/vue3';

const { numberingSettingsForm, saveNumberingSettings, sahodaya, event } = inject('eventSettings');
</script>
