<template>
    <div class="space-y-6 max-w-3xl">
        <section class="card space-y-4">
            <div>
                <h3 class="section-title">Grade bands</h3>
                <p class="section-desc">Map score ranges to grades for mark entry and results.</p>
            </div>
            <form @submit.prevent="addGradeConfig" class="grid gap-3 sm:grid-cols-2">
                <FormField label="Item" class-extra="sm:col-span-2">
                    <template #default="{ id }">
                        <select :id="id" v-model="gradeForm.item_id" class="field">
                            <option value="">Event-wide (all items)</option>
                            <option v-for="item in event.items" :key="item.id" :value="item.id">{{ item.title }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Grade" required>
                    <template #default="{ id }">
                        <select :id="id" v-model="gradeForm.grade" class="field" required>
                            <option value="A_plus">A+</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Score range">
                    <div class="flex gap-2">
                        <input v-model.number="gradeForm.min_score" type="number" min="0" class="field" placeholder="Min" aria-label="Minimum score">
                        <input v-model.number="gradeForm.max_score" type="number" min="0" class="field" placeholder="Max" aria-label="Maximum score">
                    </div>
                </FormField>
                <button type="submit" class="btn-primary sm:col-span-2">Add grade band</button>
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
                            <th>Score range</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="g in filteredGrades" :key="g.id">
                            <td>{{ g.item?.title || 'Event-wide' }}</td>
                            <td class="font-semibold">{{ g.grade }}</td>
                            <td>{{ g.min_score }} – {{ g.max_score }}</td>
                            <td class="text-right">
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

const { gradeForm, gradeConfigs, event, addGradeConfig, removeGradeConfig } = inject('eventSettings');
const searchQuery = ref('');

const filteredGrades = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return gradeConfigs;
    return gradeConfigs.filter((g) =>
        [g.item?.title, g.grade, String(g.min_score), String(g.max_score)].filter(Boolean).join(' ').toLowerCase().includes(q),
    );
});
</script>
