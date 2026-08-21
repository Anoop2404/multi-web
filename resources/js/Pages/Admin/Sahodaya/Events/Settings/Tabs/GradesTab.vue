<template>
    <div class="space-y-6 max-w-3xl">
        <section class="card space-y-4">
            <div>
                <h3 class="section-title">Grade Master</h3>
                <p class="section-desc">
                    Map score ranges to grades for mark entry and results. Give an item a "Total Marks" value
                    (Items page) to switch it to a percentage range here — one set of bands then applies
                    consistently across items with different maximums.
                </p>
            </div>
            <form @submit.prevent="saveGradeConfig" class="grid gap-3 sm:grid-cols-2">
                <FormField label="Item" class-extra="sm:col-span-2">
                    <template #default="{ id }">
                        <select :id="id" v-model="gradeForm.item_id" class="field">
                            <option value="">Event-wide (all items)</option>
                            <option v-for="item in event.items" :key="item.id" :value="item.id">
                                {{ item.title }}{{ item.total_marks ? ` — /${item.total_marks}` : '' }}
                            </option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Grade" required hint="Pick an existing grade or type a new label — this event's grade set isn't limited to A+/A/B/C.">
                    <template #default="{ id }">
                        <input :id="id" v-model="gradeForm.grade" list="grade-key-options" class="field" required
                               placeholder="A+, A, B, C, or a custom label">
                        <datalist id="grade-key-options">
                            <option v-for="g in existingGradeKeys" :key="g" :value="g" />
                        </datalist>
                    </template>
                </FormField>
                <FormField v-if="selectedItemUsesPercentage" label="Percentage range" :error="gradeForm.errors.min_percent || gradeForm.errors.max_percent">
                    <div class="flex items-center gap-2">
                        <input v-model.number="gradeForm.min_percent" type="number" min="0" max="100" step="0.01" class="field" placeholder="Min %" aria-label="Minimum percentage">
                        <span class="text-slate-400">–</span>
                        <input v-model.number="gradeForm.max_percent" type="number" min="0" max="100" step="0.01" class="field" placeholder="Max %" aria-label="Maximum percentage">
                    </div>
                </FormField>
                <FormField v-else label="Score range" :error="gradeForm.errors.min_score || gradeForm.errors.max_score">
                    <div class="flex items-center gap-2">
                        <input v-model.number="gradeForm.min_score" type="number" min="0" class="field" placeholder="Min" aria-label="Minimum score">
                        <span class="text-slate-400">–</span>
                        <input v-model.number="gradeForm.max_score" type="number" min="0" class="field" placeholder="Max" aria-label="Maximum score">
                    </div>
                </FormField>
                <div class="sm:col-span-2 flex gap-2">
                    <button type="submit" class="btn-primary flex-1" :disabled="gradeForm.processing">
                        {{ editingGradeConfigId ? 'Save changes' : 'Add grade band' }}
                    </button>
                    <button v-if="editingGradeConfigId" type="button" class="btn-secondary" @click="cancelEditGradeConfig">Cancel</button>
                </div>
            </form>
        </section>

        <input v-if="gradeConfigs.length" v-model="searchQuery" type="search" class="field max-w-md" placeholder="Search item or grade…">

        <section class="form-section overflow-hidden !p-0">
            <EmptyState v-if="!filteredGrades.length" title="No grade bands" description="Add bands above or adjust your search." icon="📊" class="p-8" />
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Grade</th>
                            <th>Range</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="g in filteredGrades" :key="g.id">
                            <td>{{ g.item?.title || 'Event-wide' }}</td>
                            <td class="font-semibold">{{ g.grade }}</td>
                            <td>
                                <span v-if="g.min_percent !== null && g.min_percent !== undefined">{{ g.min_percent }}% – {{ g.max_percent }}%</span>
                                <span v-else>{{ g.min_score }} – {{ g.max_score }}</span>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <button type="button" @click="startEditGradeConfig(g)" class="text-indigo-600 text-xs mr-3">Edit</button>
                                <button type="button" @click="removeGradeConfig(g.id)" class="text-red-600 text-xs">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, inject, ref } from 'vue';

const {
    gradeForm, gradeConfigs, event,
    addGradeConfig, removeGradeConfig,
    editingGradeConfigId, startEditGradeConfig, cancelEditGradeConfig, saveGradeConfig,
} = inject('eventSettings');
const searchQuery = ref('');

const selectedItemUsesPercentage = computed(() => {
    if (!gradeForm.item_id) return false;
    const item = (event.value?.items ?? []).find((i) => i.id === gradeForm.item_id);
    return !!item?.total_marks;
});

const existingGradeKeys = computed(() => {
    const used = gradeConfigs.value.map((g) => g.grade).filter(Boolean);
    return [...new Set([...used, 'A_plus', 'A', 'B', 'C'])];
});

const filteredGrades = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return gradeConfigs.value;
    return gradeConfigs.value.filter((g) =>
        [g.item?.title, g.grade, String(g.min_score), String(g.max_score)].filter(Boolean).join(' ').toLowerCase().includes(q),
    );
});
</script>
