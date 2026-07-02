<template>
    <div class="space-y-6 max-w-xl">
        <section class="card space-y-4">
            <div>
                <h3 class="section-title">Athletic records</h3>
                <p class="section-desc">When enabled, sports marks that beat standing records trigger automatic prize labels.</p>
            </div>
            <form @submit.prevent="saveSettings" class="space-y-4">
                <CheckboxField v-model="settingsForm.record_tracking_enabled" label="Enable athletic record tracking" />
                <FormField label="Default prize label">
                    <template #default="{ id }">
                        <input :id="id" v-model="settingsForm.default_record_prize_label" class="field" placeholder="Record Break Medal">
                    </template>
                </FormField>
                <FormActions>
                    <button type="submit" class="btn-primary">Save record settings</button>
                </FormActions>
            </form>
        </section>
        <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/athletic-records`" class="link-brand text-sm font-semibold">
            View records & prize log →
        </Link>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { inject } from 'vue';

const { settingsForm, saveSettings, sahodaya, event } = inject('eventSettings');
</script>
