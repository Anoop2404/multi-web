<template>
    <div class="space-y-6 max-w-3xl">
        <div v-if="suggestedStatus" class="card card--accent text-sm text-indigo-900">
            Suggested status: <strong>{{ suggestedStatus }}</strong>
        </div>

        <form class="card space-y-3" @submit.prevent="saveLifecycle">
            <h3 class="font-semibold text-slate-900">Document verification day</h3>
            <p class="text-xs text-slate-500">Schools bring documents on this date. Mark each school verified below after checking.</p>
            <input v-model="lifecycleForm.verification_day" type="date" class="field text-sm max-w-xs" />
            <div>
                <label class="text-sm font-medium text-slate-700">Event manual (PDF)</label>
                <input type="file" accept="application/pdf" class="field text-sm mt-1" @change="onManualFile" />
                <label v-if="event.manual_pdf_path" class="flex items-center gap-2 text-xs text-slate-600 mt-2">
                    <input v-model="lifecycleForm.remove_manual" type="checkbox" /> Remove current manual
                </label>
            </div>
            <button type="submit" class="btn-primary text-sm" :disabled="lifecycleForm.processing">Save lifecycle settings</button>
        </form>

        <div v-if="schoolVerifications?.length" class="card overflow-hidden p-0">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">School document verification</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sl No</th>
                        <th>School</th>
                        <th>Verified</th>
                        <th>Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in schoolVerifications" :key="row.school_id">
                        <td>{{ idx + 1 }}</td>
                        <td>{{ (row.school_name || '').toUpperCase() }}</td>
                        <td>
                            <span class="text-xs px-2 py-0.5 rounded-full"
                                  :class="row.documents_verified ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                                {{ row.documents_verified ? 'Verified' : 'Pending' }}
                            </span>
                        </td>
                        <td class="text-xs">{{ row.notes || '—' }}</td>
                        <td class="text-right">
                            <button type="button" class="btn-secondary text-xs mr-1"
                                    @click="toggleVerification(row, true)">Mark verified</button>
                            <button type="button" class="btn-secondary text-xs"
                                    @click="toggleVerification(row, false)">Clear</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="mandatoryGaps?.length" class="notice-banner notice-banner--warning text-sm">
            <p class="font-semibold mb-1">Schools missing mandatory items</p>
            <ul class="list-disc pl-5 space-y-1">
                <li v-for="gap in mandatoryGaps" :key="gap.school_id">
                    {{ gap.school_name || gap.school_id }}: {{ gap.missing.join(', ') }}
                </li>
            </ul>
        </div>

        <ul class="space-y-2">
            <li v-for="step in lifecycle" :key="step.key"
                class="card flex items-start gap-3 text-sm"
                :class="step.done ? '!border-emerald-200 bg-emerald-50/50' : ''">
                <span class="text-lg shrink-0">{{ step.done ? '✓' : '○' }}</span>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-slate-900">{{ step.label }}</p>
                    <p v-if="step.hint" class="text-xs text-slate-500 mt-0.5">{{ step.hint }}</p>
                    <Link v-if="lifecycleLinks[step.key]" :href="lifecycleLinks[step.key]"
                          class="inline-block mt-2 text-xs font-semibold link-brand">
                        Open →
                    </Link>
                </div>
            </li>
        </ul>
        <p class="text-xs text-slate-500">Publish schedule from the Schedule page. Publish results from the Results page.</p>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { inject } from 'vue';

const { lifecycle, suggestedStatus, lifecycleLinks, lifecycleForm, saveLifecycle, schoolVerifications, mandatoryGaps, event, sahodaya } = inject('eventSettings');

function onManualFile(e) {
    lifecycleForm.manual_pdf = e.target.files?.[0] ?? null;
}

function toggleVerification(row, verified) {
    const notes = verified ? (window.prompt('Verification notes (optional)') || '') : '';
    router.post(`/sahodaya-admin/${sahodaya.id}/events/${event.id}/school-verifications/${row.school_id}`, {
        documents_verified: verified,
        notes,
    }, { preserveScroll: true });
}
</script>
