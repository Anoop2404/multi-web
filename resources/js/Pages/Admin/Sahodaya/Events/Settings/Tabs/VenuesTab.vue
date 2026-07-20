<template>
    <div class="space-y-6">
        <!-- Section 1: Venues & Grounds -->
        <section class="card !p-5 space-y-4 border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="section-title !mb-0 flex items-center gap-2 text-base">
                        <span>📍</span> Venues &amp; Grounds
                    </h3>
                    <p class="section-desc mt-0.5">Physical locations and grounds used in the competition schedule.</p>
                </div>
                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
                    {{ venues.length }} venue{{ venues.length === 1 ? '' : 's' }}
                </span>
            </div>

            <!-- Inline Form -->
            <form @submit.prevent="addVenue" class="bg-slate-50/80 p-4 rounded-xl border border-slate-200/80 space-y-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">+ Add New Venue</h4>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-3">
                        <label class="form-label text-xs">Venue name *</label>
                        <input v-model="venueForm.name" class="field text-xs" placeholder="e.g. Main Auditorium, Indoor Stadium, Ground A" required>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label text-xs">Location / Campus</label>
                        <input v-model="venueForm.location" class="field text-xs" placeholder="e.g. Block A, Main Campus">
                    </div>
                    <div>
                        <label class="form-label text-xs">Capacity (approx.)</label>
                        <input v-model.number="venueForm.capacity" type="number" min="1" class="field text-xs" placeholder="e.g. 500">
                    </div>
                </div>
                <div class="flex justify-end pt-1">
                    <button type="submit" class="btn-primary text-xs !py-1.5 !px-4">Add Venue</button>
                </div>
            </form>

            <!-- Venues List -->
            <div v-if="venues.length" class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                <ul class="divide-y divide-slate-100 text-xs">
                    <li v-for="v in venues" :key="v.id" class="p-3.5 flex items-center justify-between hover:bg-slate-50/70 transition">
                        <div class="space-y-0.5">
                            <p class="font-bold text-slate-900 text-sm">{{ v.name }}</p>
                            <p class="text-slate-500 text-[11px] flex items-center gap-2">
                                <span>📍 {{ v.location || 'No location specified' }}</span>
                                <span v-if="v.capacity">👥 Capacity: {{ v.capacity }}</span>
                            </p>
                        </div>
                        <button type="button" @click="removeVenue(v.id)" class="btn-secondary text-xs !text-rose-700 hover:!bg-rose-50 !py-1 !px-2.5">
                            Remove
                        </button>
                    </li>
                </ul>
            </div>
            <div v-else class="rounded-xl border border-dashed border-slate-200 p-8 text-center text-slate-400 text-xs">
                No venues configured yet. Use the form above to add physical event venues.
            </div>
        </section>

        <!-- Section 2: Stages & Performance Arenas -->
        <section class="card !p-5 space-y-4 border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="section-title !mb-0 flex items-center gap-2 text-base">
                        <span>🎭</span> Stages &amp; Performance Arenas
                    </h3>
                    <p class="section-desc mt-0.5">Used for scheduling and assigning stage managers in the ops portal.</p>
                </div>
                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
                    {{ stages.length }} stage{{ stages.length === 1 ? '' : 's' }}
                </span>
            </div>

            <!-- Inline Form -->
            <form @submit.prevent="addStage" class="bg-slate-50/80 p-4 rounded-xl border border-slate-200/80 space-y-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">+ Add New Stage</h4>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="form-label text-xs">Stage name *</label>
                        <input v-model="stageForm.name" class="field text-xs" placeholder="e.g. Stage 1 — Main Hall, Track A" required>
                    </div>
                    <div>
                        <label class="form-label text-xs">Link to Venue</label>
                        <select v-model="stageForm.venue_id" class="field text-xs">
                            <option value="">No venue link</option>
                            <option v-for="v in venues" :key="v.id" :value="v.id">{{ v.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end pt-1">
                    <button type="submit" class="btn-primary text-xs !py-1.5 !px-4">Add Stage</button>
                </div>
            </form>

            <!-- Stages List -->
            <div v-if="stages.length" class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                <ul class="divide-y divide-slate-100 text-xs">
                    <li v-for="s in stages" :key="s.id" class="p-3.5 flex items-center justify-between hover:bg-slate-50/70 transition">
                        <div class="space-y-0.5">
                            <p class="font-bold text-slate-900 text-sm">{{ s.name }}</p>
                            <p class="text-slate-500 text-[11px]">
                                🏢 Linked Venue: <span class="font-medium text-slate-700">{{ s.venue?.name || 'Unlinked' }}</span>
                            </p>
                        </div>
                        <button type="button" @click="removeStage(s.id)" class="btn-secondary text-xs !text-rose-700 hover:!bg-rose-50 !py-1 !px-2.5">
                            Remove
                        </button>
                    </li>
                </ul>
            </div>
            <div v-else class="rounded-xl border border-dashed border-slate-200 p-8 text-center text-slate-400 text-xs">
                No stages configured yet. Add physical venues first, then create stages above.
            </div>
        </section>
    </div>
</template>

<script setup>
import { inject } from 'vue';

const { venueForm, stageForm, venues, stages, addVenue, removeVenue, addStage, removeStage } = inject('eventSettings');
</script>
