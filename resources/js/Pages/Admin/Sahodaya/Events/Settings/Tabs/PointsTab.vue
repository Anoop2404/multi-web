<template>
    <div class="space-y-6 max-w-3xl">
        <div v-if="event.event_type === 'sports'" class="notice-banner notice-banner--info text-sm">
            <p class="font-semibold text-[#0f3d7a] mb-1">Sports rank points master</p>
            <p class="text-slate-700">
                Set fixed championship points for each rank (1st, 2nd, 3rd…).
                <strong>Multiple athletes can share the same rank</strong> (ties, heats) — each receives the points for that rank.
                Points apply to the school scoreboard when marks are saved or auto-ranked.
            </p>
        </div>

        <section v-if="event.event_type === 'sports'" class="card space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="section-title">Individual rank points</h3>
                    <p class="section-desc">Track & field, individual events — rank → team points.</p>
                </div>
                <button type="button" class="btn-secondary text-sm shrink-0" :disabled="seeding" @click="seedAthletics">
                    {{ seeding ? 'Loading…' : 'Load athletics standard' }}
                </button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-100">
                <table class="data-table text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600 w-32">Rank</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600">Team points</th>
                            <th class="w-16"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="(row, index) in rankRows" :key="row._key">
                            <td class="px-3 py-2 font-medium text-slate-900">{{ rankLabel(row.rank) }}</td>
                            <td class="px-3 py-2">
                                <input v-model.number="row.points" type="number" min="0" class="field w-28 text-sm" required>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button v-if="rankRows.length > 1" type="button" class="text-red-600 text-xs" @click="removeRankRow(index)">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-secondary text-sm" @click="addRankRow">Add rank</button>
                <button type="button" class="btn-primary text-sm" :disabled="savingRanks" @click="saveRankPoints(false)">
                    {{ savingRanks ? 'Saving…' : 'Save rank master' }}
                </button>
            </div>
        </section>

        <section v-if="event.event_type === 'sports'" class="card space-y-4">
            <div>
                <h3 class="section-title">Team / relay rank points</h3>
                <p class="section-desc">Optional separate table for team games and relays. Leave empty to use individual ranks above.</p>
            </div>

            <div v-if="groupRankRows.length" class="overflow-x-auto rounded-xl border border-slate-100">
                <table class="data-table text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600 w-32">Rank</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600">Team points</th>
                            <th class="w-16"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="(row, index) in groupRankRows" :key="row._key">
                            <td class="px-3 py-2 font-medium text-slate-900">{{ rankLabel(row.rank) }}</td>
                            <td class="px-3 py-2">
                                <input v-model.number="row.points" type="number" min="0" class="field w-28 text-sm" required>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" class="text-red-600 text-xs" @click="groupRankRows.splice(index, 1)">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-slate-500">No team rank table yet — add rows if team events need different points.</p>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-secondary text-sm" @click="addGroupRankRow">Add team rank</button>
                <button v-if="groupRankRows.length" type="button" class="btn-primary text-sm" :disabled="savingGroupRanks" @click="saveRankPoints(true)">
                    {{ savingGroupRanks ? 'Saving…' : 'Save team ranks' }}
                </button>
            </div>
        </section>

        <section v-if="event.event_type !== 'sports'" class="card space-y-4">
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

        <section v-if="event.event_type !== 'sports'" class="form-section overflow-hidden !p-0">
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
import { inject, ref } from 'vue';

const {
    pointForm,
    pointRules,
    addPointRule,
    removePointRule,
    event,
    rankRows,
    groupRankRows,
    saveRankPoints,
    seedAthletics,
    addRankRow,
    removeRankRow,
    addGroupRankRow,
    rankLabel,
    savingRanks,
    savingGroupRanks,
    seeding,
} = inject('eventSettings');
</script>
