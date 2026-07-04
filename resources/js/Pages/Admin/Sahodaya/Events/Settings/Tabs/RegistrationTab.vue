<template>
    <div class="space-y-6 max-w-3xl">
        <form @submit.prevent="saveRegistrationSettings" class="card space-y-4">
            <div>
                <h3 class="section-title">Event vs item registration</h3>
                <p class="section-desc">Require students to register for the event (₹300 student fee) before item entries.</p>
            </div>
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" v-model="registrationSettingsForm.require_event_registration" class="mt-0.5">
                <span>Require event registration before item registration</span>
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <FormField label="Event registration opens">
                    <template #default="{ id }">
                        <input :id="id" v-model="registrationSettingsForm.event_reg_start" type="date" class="field">
                    </template>
                </FormField>
                <FormField label="Event registration closes">
                    <template #default="{ id }">
                        <input :id="id" v-model="registrationSettingsForm.event_reg_end" type="date" class="field">
                    </template>
                </FormField>
            </div>
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" v-model="registrationSettingsForm.allow_student_self_register" class="mt-0.5">
                <span>Allow students to self-register from their portal profile</span>
            </label>
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="registrationSettingsForm.processing">Save registration settings</button>
            </FormActions>
        </form>

        <section class="card space-y-3">
            <h3 class="section-title">Per-item windows</h3>
            <p class="section-desc text-xs">Set registration open/close and result publish per item from the item list or import catalog with heads synced.</p>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/item-heads`" class="btn-secondary text-sm inline-flex">Manage item heads →</Link>
        </section>
    </div>
</template>

<script setup>
import { inject } from 'vue';
import { Link } from '@inertiajs/vue3';

const { registrationSettingsForm, saveRegistrationSettings, sahodaya, event } = inject('eventSettings');
</script>
