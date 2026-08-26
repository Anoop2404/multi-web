<template>
    <div class="space-y-6">
        <!-- Section 1: Venues & Grounds -->
        <section class="card !p-5 space-y-4 border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="section-title !mb-0 flex items-center gap-2 text-base">
                        <span>📍</span> Venues &amp; Regional Grounds
                    </h3>
                    <p class="section-desc mt-0.5">Physical locations, grounds, and regional preliminary venues used in the competition schedule.</p>
                </div>
                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
                    {{ venues.length }} venue{{ venues.length === 1 ? '' : 's' }}
                </span>
            </div>

            <!-- Inline Form for Adding New Venue -->
            <form @submit.prevent="addVenue" class="bg-slate-50/80 p-4 rounded-xl border border-slate-200/80 space-y-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">+ Add New Venue</h4>
                <div class="grid gap-3 sm:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label class="form-label text-xs">Venue Name *</label>
                        <input v-model="venueForm.name" class="field text-xs" placeholder="e.g. MES Central School, Tirur" required>
                    </div>
                    <div>
                        <label class="form-label text-xs">Location / Campus</label>
                        <input v-model="venueForm.location" class="field text-xs" placeholder="e.g. Tirur Campus">
                    </div>
                    <div>
                        <label class="form-label text-xs">Capacity (approx.)</label>
                        <input v-model.number="venueForm.capacity" type="number" min="1" class="field text-xs" placeholder="e.g. 500">
                    </div>
                    <div v-if="regions?.length" class="sm:col-span-4">
                        <label class="form-label text-xs">Assigned Region (Optional — for Region-Wise Preliminaries)</label>
                        <SearchableSelect
                            v-model="venueForm.region_id"
                            :options="regions"
                            :all-option="true"
                            all-label="— All Regions / Central Venue —"
                        />
                    </div>
                </div>
                <div class="flex justify-end pt-1">
                    <button type="submit" class="btn-primary text-xs !py-1.5 !px-4">Add Venue</button>
                </div>
            </form>

            <!-- Venues List -->
            <div v-if="venues.length" class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                <ul class="divide-y divide-slate-100 text-xs">
                    <li v-for="v in venues" :key="v.id" class="p-3.5">
                        <!-- Edit mode -->
                        <form v-if="editingVenueId === v.id" @submit.prevent="saveVenueEdit" class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-4">
                                <div class="sm:col-span-2">
                                    <label class="form-label text-xs">Venue Name *</label>
                                    <input v-model="venueEditForm.name" class="field text-xs" required>
                                </div>
                                <div>
                                    <label class="form-label text-xs">Location / Campus</label>
                                    <input v-model="venueEditForm.location" class="field text-xs">
                                </div>
                                <div>
                                    <label class="form-label text-xs">Capacity (approx.)</label>
                                    <input v-model.number="venueEditForm.capacity" type="number" min="1" class="field text-xs">
                                </div>
                                <div v-if="regions?.length" class="sm:col-span-4">
                                    <label class="form-label text-xs">Assigned Region</label>
                                    <SearchableSelect
                                        v-model="venueEditForm.region_id"
                                        :options="regions"
                                        :all-option="true"
                                        all-label="— All Regions / Central Venue —"
                                    />
                                </div>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="cancelEditVenue" class="btn-secondary text-xs !py-1.5 !px-4">Cancel</button>
                                <button type="submit" :disabled="venueEditForm.processing" class="btn-primary text-xs !py-1.5 !px-4">Save</button>
                            </div>
                        </form>

                        <!-- Display mode -->
                        <div v-else class="flex items-center justify-between hover:bg-slate-50/70 transition -m-3.5 p-3.5">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <p class="font-bold text-slate-900 text-sm">{{ v.name }}</p>
                                    <span v-if="v.region" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                        📍 {{ v.region.name }}
                                    </span>
                                    <span v-else class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-600">
                                        Central / All Regions
                                    </span>
                                </div>
                                <p class="text-slate-500 text-[11px] flex items-center gap-3">
                                    <span>📍 {{ v.location || 'No location specified' }}</span>
                                    <span v-if="v.capacity">👥 Capacity: {{ v.capacity }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button" @click="startEditVenue(v)" class="btn-secondary text-xs !py-1 !px-2.5">
                                    Edit
                                </button>
                                <button type="button" @click="removeVenue(v.id)" class="btn-secondary text-xs !text-rose-700 hover:!bg-rose-50 !py-1 !px-2.5">
                                    Remove
                                </button>
                            </div>
                        </div>
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
                        <label class="form-label text-xs">Stage Name *</label>
                        <input v-model="stageForm.name" class="field text-xs" placeholder="e.g. Stage 1 — Main Hall, Track A" required>
                    </div>
                    <div>
                        <label class="form-label text-xs">Link to Venue</label>
                        <SearchableSelect
                            v-model="stageForm.venue_id"
                            :options="venueStageOptions"
                            :all-option="true"
                            all-label="No venue link"
                        />
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
import { computed, inject } from 'vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const {
    venueForm, stageForm, venues, stages, regions, addVenue, removeVenue, addStage, removeStage,
    editingVenueId, venueEditForm, startEditVenue, cancelEditVenue, saveVenueEdit,
} = inject('eventSettings');

const venueStageOptions = computed(() => venues.value.map((v) => ({
    value: v.id,
    label: v.region ? `${v.name} (${v.region.name})` : v.name,
})));
</script>
