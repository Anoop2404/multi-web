<template>
    <div class="space-y-6 max-w-3xl">
        <section class="card space-y-4">
            <div>
                <h3 class="section-title">Venues</h3>
                <p class="section-desc">Physical locations used in the schedule.</p>
            </div>
            <form @submit.prevent="addVenue" class="grid gap-3 sm:grid-cols-3">
                <FormField label="Venue name" class-extra="sm:col-span-3" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="venueForm.name" class="field" placeholder="Main auditorium" required>
                    </template>
                </FormField>
                <FormField label="Location" class-extra="sm:col-span-2">
                    <template #default="{ id }">
                        <input :id="id" v-model="venueForm.location" class="field" placeholder="Building / campus">
                    </template>
                </FormField>
                <FormField label="Capacity">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="venueForm.capacity" type="number" min="1" class="field" placeholder="500">
                    </template>
                </FormField>
                <button type="submit" class="btn-primary sm:col-span-3">Add venue</button>
            </form>
            <EmptyState v-if="!venues.length" title="No venues yet" description="Add a venue to link stages and schedule slots." icon="📍" class="py-6" />
            <ul v-else class="divide-y border border-slate-100 rounded-xl overflow-hidden">
                <li v-for="v in venues" :key="v.id" class="p-3 flex justify-between text-sm bg-white">
                    <span>{{ v.name }} <span class="text-slate-400">· {{ v.location || '—' }} · cap {{ v.capacity || '—' }}</span></span>
                    <button type="button" @click="removeVenue(v.id)" class="text-red-600 text-xs">Remove</button>
                </li>
            </ul>
        </section>

        <section class="card space-y-4">
            <div>
                <h3 class="section-title">Stages</h3>
                <p class="section-desc">Used in the schedule and for scoping stage managers in the ops portal.</p>
            </div>
            <form @submit.prevent="addStage" class="grid gap-3 sm:grid-cols-3">
                <FormField label="Stage name" class-extra="sm:col-span-2" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="stageForm.name" class="field" placeholder="Main Hall — Stage 1" required>
                    </template>
                </FormField>
                <FormField label="Venue">
                    <template #default="{ id }">
                        <select :id="id" v-model="stageForm.venue_id" class="field">
                            <option value="">No venue</option>
                            <option v-for="v in venues" :key="v.id" :value="v.id">{{ v.name }}</option>
                        </select>
                    </template>
                </FormField>
                <button type="submit" class="btn-primary sm:col-span-3">Add stage</button>
            </form>
            <EmptyState v-if="!stages.length" title="No stages yet" description="Add stages after creating venues." icon="🎭" class="py-6" />
            <ul v-else class="divide-y border border-slate-100 rounded-xl overflow-hidden">
                <li v-for="s in stages" :key="s.id" class="p-3 flex justify-between text-sm bg-white">
                    <span>{{ s.name }} <span class="text-slate-400">· {{ s.venue?.name || 'No venue' }}</span></span>
                    <button type="button" @click="removeStage(s.id)" class="text-red-600 text-xs">Remove</button>
                </li>
            </ul>
        </section>
    </div>
</template>

<script setup>
import { inject } from 'vue';

const { venueForm, stageForm, venues, stages, addVenue, removeVenue, addStage, removeStage } = inject('eventSettings');
</script>
