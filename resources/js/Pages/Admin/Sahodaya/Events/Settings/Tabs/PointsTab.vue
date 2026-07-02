<template>
    <div class="space-y-6 max-w-3xl">
        <div v-if="event.event_type === 'sports'" class="notice-banner notice-banner--info text-sm">
            <p class="font-semibold text-[#0f3d7a] mb-1">Sports Meet scoring</p>
            <p class="text-slate-700">
                Points are applied to the <strong>school championship scoreboard</strong> when marks are saved.
                Configure position → points rules below. Individual Championship (IC) pages are not used for sports —
                ranks and measurements drive the scoreboard instead.
            </p>
        </div>

        <section class="card space-y-4">
            <div>
                <h3 class="section-title">Championship point rules</h3>
                <p class="section-desc">Points awarded by grade and position for leaderboard calculations.</p>
            </div>
            <form @submit.prevent="addPointRule" class="grid gap-3 sm:grid-cols-2">
                <FormField label="Grade">
                    <template #default="{ id }">
                        <select :id="id" v-model="pointForm.grade" class="field">
                            <option value="">Any grade</option>
                            <option value="A_plus">A+</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Position">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="pointForm.position" type="number" min="1" class="field" placeholder="1, 2, 3…">
                    </template>
                </FormField>
                <FormField label="Points" required>
                    <template #default="{ id }">
                        <input :id="id" v-model.number="pointForm.points" type="number" min="0" class="field" required>
                    </template>
                </FormField>
                <FormField label="Item type">
                    <CheckboxField v-model="pointForm.is_group" label="Group item rule" />
                </FormField>
                <button type="submit" class="btn-primary sm:col-span-2">Add point rule</button>
            </form>
        </section>

        <section class="form-section overflow-hidden !p-0">
            <EmptyState v-if="!pointRules.length" title="No point rules" description="Add rules above for championship scoring." icon="🏆" class="p-8" />
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Grade</th>
                            <th>Position</th>
                            <th>Points</th>
                            <th>Type</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in pointRules" :key="p.id">
                            <td>{{ p.grade || 'Any' }}</td>
                            <td>{{ p.position || 'Any' }}</td>
                            <td class="font-semibold">{{ p.points }}</td>
                            <td>{{ p.is_group ? 'Group' : 'Individual' }}</td>
                            <td class="text-right">
                                <button type="button" @click="removePointRule(p.id)" class="text-red-600 text-xs">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { inject } from 'vue';

const { pointForm, pointRules, addPointRule, removePointRule, event } = inject('eventSettings');
</script>
