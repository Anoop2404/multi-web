<template>
    <div class="space-y-6 max-w-xl">
        <section class="card card--muted space-y-3">
            <h3 class="section-title">Clone event structure</h3>
            <p class="section-desc">Copy items, policies, and rules to a new event without registrations or marks.</p>
            <form @submit.prevent="cloneEvent" class="flex flex-wrap gap-3 items-end">
                <FormField label="New event title" class-extra="flex-1 min-w-[12rem]" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="cloneForm.title" class="field" placeholder="Copy of {{ event?.title }}" required>
                    </template>
                </FormField>
                <button type="submit" class="btn-primary" :disabled="cloneForm.processing">Clone event</button>
            </form>
        </section>
    </div>
</template>

<script setup>
import { inject } from 'vue';

const { cloneForm, cloneEvent, event } = inject('eventSettings');
</script>
