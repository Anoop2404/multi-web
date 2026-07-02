<template>
    <div class="space-y-6 max-w-3xl">
        <section class="card space-y-4">
            <div>
                <h3 class="section-title">Combination rules</h3>
                <p class="section-desc">Limit how many arts/sports or on/off-stage items a student can enter per school or class.</p>
            </div>
            <form @submit.prevent="addComboRule" class="grid gap-3 sm:grid-cols-2">
                <FormField label="School">
                    <template #default="{ id }">
                        <select :id="id" v-model="comboForm.school_id" class="field">
                            <option value="">All schools</option>
                            <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Class category">
                    <template #default="{ id }">
                        <select :id="id" v-model="comboForm.class_group" class="field">
                            <option value="">All classes</option>
                            <option v-for="(label, key) in classGroups" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Max arts events">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="comboForm.max_arts_events" type="number" min="0" class="field" placeholder="—">
                    </template>
                </FormField>
                <FormField label="Max sports events">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="comboForm.max_sports_events" type="number" min="0" class="field" placeholder="—">
                    </template>
                </FormField>
                <FormField label="Max on-stage">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="comboForm.max_on_stage" type="number" min="0" class="field" placeholder="—">
                    </template>
                </FormField>
                <FormField label="Max off-stage">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="comboForm.max_off_stage" type="number" min="0" class="field" placeholder="—">
                    </template>
                </FormField>
                <button type="submit" class="btn-primary sm:col-span-2">Add rule</button>
            </form>
        </section>

        <section class="form-section overflow-hidden !p-0">
            <EmptyState v-if="!comboRules.length" title="No combination rules" description="Add a rule above to restrict item combinations." icon="🔗" class="p-8" />
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Scope</th>
                            <th>Arts</th>
                            <th>Sports</th>
                            <th>On-stage</th>
                            <th>Off-stage</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in comboRules" :key="r.id">
                            <td class="text-sm">{{ r.school_name || 'All schools' }} · {{ r.class_group || 'all classes' }}</td>
                            <td>{{ r.max_arts_events ?? '—' }}</td>
                            <td>{{ r.max_sports_events ?? '—' }}</td>
                            <td>{{ r.max_on_stage ?? '—' }}</td>
                            <td>{{ r.max_off_stage ?? '—' }}</td>
                            <td class="text-right">
                                <button type="button" @click="removeComboRule(r.id)" class="text-red-600 text-xs">Remove</button>
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

const { comboForm, comboRules, schools, classGroups, addComboRule, removeComboRule } = inject('eventSettings');
</script>
