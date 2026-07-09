<template>
    <div class="card max-w-2xl space-y-4">
<form @submit.prevent="saveSettings" class="space-y-4">
                <FormField label="Scoring locked">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="settingsForm.scoring_locked"> Prevent mark changes after lock
                    </label>
                </FormField>
                <FormField label="Appeals open">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="settingsForm.appeals_open"> Schools can submit appeals
                    </label>
                </FormField>
                <FormField label="Registration locked">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="settingsForm.registration_locked"> Block new registrations
                    </label>
                </FormField>
                <FormField label="Student verification for item registration">
                    <select v-model="settingsForm.student_verification_mode" class="field">
                        <option value="inherit">
                            Use cluster default — {{ clusterRequireStudentVerification ? 'verified students only' : 'unverified allowed' }}
                        </option>
                        <option value="required">Require verified students only</option>
                        <option value="optional">Allow unverified students</option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">
                        Applies to all items in this event (Kalotsav, Sports, Kids Fest, Custom, etc.).
                        Cluster default is set under Membership → Settings.
                    </p>
                </FormField>
                <FormField label="Certificate collection">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="settingsForm.certificate_collection_open"> Allow certificate pickup
                    </label>
                </FormField>
                <FormField label="Publish gates">
                    <div class="space-y-2">
                        <label v-if="!isSports" class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" v-model="settingsForm.require_judge_scores_before_publish"> Require judge scores before publish
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" v-model="settingsForm.require_all_marks_before_publish"> Require all marks before publish
                        </label>
                    </div>
                </FormField>
                <FormField label="Chest reveal mode">
                    <template #default="{ id }">
                        <select :id="id" v-model="settingsForm.chest_reveal_mode" class="field">
                            <option value="immediate">Immediate</option>
                            <option value="stage_entry">At stage entry</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Appeal fee (₹)">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="settingsForm.appeal_fee_amount" type="number" min="0" step="0.01" class="field" placeholder="0">
                    </template>
                </FormField>
                <div class="rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    {{ isSports ? 'Mark entry progress' : 'Judge gate' }}: {{ judgeGate.complete }}/{{ judgeGate.total }} items complete
                </div>
                <button type="submit" class="btn-primary">Save locks & gates</button>
            </form>
            <div class="border-t border-slate-100 pt-4">
                <button type="button" @click="backfillRegs" class="btn-secondary">Backfill level registration numbers</button>
            </div>
    </div>
</template>

<script setup>
import { computed, inject } from 'vue';

const { settingsForm, judgeGate, saveSettings, backfillRegs, event, clusterRequireStudentVerification } = inject('eventSettings');
const isSports = computed(() => event?.event_type === 'sports');
</script>
