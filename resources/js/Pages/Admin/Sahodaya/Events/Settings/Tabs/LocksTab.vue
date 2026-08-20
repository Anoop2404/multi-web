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
                <FormField label="Approval policy" hint="Auto-approve submitted registrations, or hold every registration for manual Sahodaya review. Falls back to this event-level setting for any item with no Event Head.">
                    <select v-model="settingsForm.approval_policy" class="field">
                        <option value="auto">Auto (on submission / full payment)</option>
                        <option value="manual">Manual review</option>
                    </select>
                </FormField>
                <FormField label="Capacity caps" hint="Maximum total participants / team entries per school for this event (leave blank for no limit).">
                    <div class="grid grid-cols-2 gap-3">
                        <input v-model.number="settingsForm.max_participants" type="number" min="0" class="field" placeholder="Max participants">
                        <input v-model.number="settingsForm.max_teams" type="number" min="0" class="field" placeholder="Max teams">
                    </div>
                </FormField>
                <FormField label="Certificate collection">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="settingsForm.certificate_collection_open"> Allow certificate pickup
                    </label>
                </FormField>
                <FormField label="Strict item-level payment gating">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="settingsForm.strict_item_payment_gating"> Require this specific item's fee to be covered before approval, not just the school's overall balance
                    </label>
                    <p class="text-xs text-slate-500 mt-1">
                        Only takes effect for "Item catalog" / "Per item" fee models — no effect on tiered, flat, per-student, or sports composite billing. Off by default; review the per-item payment breakdown on the Fees page before turning this on.
                    </p>
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
                <button type="button" @click="backfillRegs" class="btn-secondary">Backfill Missing Fest IDs</button>
                <p class="text-xs text-slate-400 mt-1">Same action as the button under Numbering — assigns event registration IDs to any student missing one.</p>
            </div>
    </div>

    <div v-if="notificationTriggers.length" class="card max-w-2xl space-y-4 mt-6">
        <div>
            <h3 class="section-title">Notifications</h3>
            <p class="section-desc text-xs mt-1">
                Untick anything this event shouldn't send. Everything is on by default.
                Only applies to items with no Event Head — a head's own notification
                settings (Competition hub) still take priority when one exists.
            </p>
        </div>

        <div class="grid gap-2 sm:grid-cols-2">
            <label v-for="trigger in notificationTriggers" :key="trigger"
                   class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" class="rounded border-slate-300" v-model="eventNotifForm.enabled[trigger]">
                {{ triggerLabel(trigger) }}
            </label>
        </div>

        <div v-if="eligibleNotificationUsers.length">
            <p class="text-xs font-semibold text-slate-700 mb-1">Also notify these platform users</p>
            <select v-model="eventNotifForm.extra_recipient_user_ids" multiple class="field text-sm h-28">
                <option v-for="user in eligibleNotificationUsers" :key="user.id" :value="user.id">
                    {{ user.name }}{{ user.email ? ` — ${user.email}` : '' }}
                </option>
            </select>
            <p class="text-[11px] text-slate-500 mt-1">Ctrl/Cmd-click to select more than one.</p>
        </div>

        <button type="button" class="btn-secondary text-sm" :disabled="savingEventNotifications" @click="saveEventNotifications">
            {{ savingEventNotifications ? 'Saving…' : 'Save notification settings' }}
        </button>
    </div>
</template>

<script setup>
import { computed, inject } from 'vue';

const {
    settingsForm, judgeGate, saveSettings, backfillRegs, event, clusterRequireStudentVerification,
    eventNotifForm, savingEventNotifications, saveEventNotifications,
    notificationTriggers, eligibleNotificationUsers,
} = inject('eventSettings');
const isSports = computed(() => event?.event_type === 'sports');

function triggerLabel(trigger) {
    return trigger.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());
}
</script>
