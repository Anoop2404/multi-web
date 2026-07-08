<template>
    <SahodayaAdminLayout title="Setup wizard" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="max-w-3xl mx-auto space-y-6">
            <div class="text-center space-y-2">
                <p class="text-xs font-bold uppercase tracking-widest text-[#fbbf24]">First-time setup</p>
                <h1 class="text-2xl font-extrabold text-slate-900">Configure your Sahodaya</h1>
                <p class="text-sm text-slate-600">
                    Complete these {{ totalSteps }} steps so schools can register and pay membership fees.
                    <span class="font-semibold text-[#0f3d7a]">{{ completedCount }}/{{ totalSteps }}</span> done.
                </p>
            </div>

            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full bg-[#0f3d7a] transition-all duration-300"
                     :style="{ width: `${Math.round((completedCount / totalSteps) * 100)}%` }" />
            </div>

            <ol class="space-y-3">
                <li v-for="(step, index) in checklist" :key="step.key"
                    class="card flex items-start gap-4 !py-4"
                    :class="step.done ? 'border-emerald-200 bg-emerald-50/40' : ''">
                    <span class="step-badge shrink-0"
                          :class="step.done ? 'step-badge--done' : 'step-badge--active'">
                        {{ step.done ? '✓' : index + 1 }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-slate-900">{{ step.label }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ step.tabLabel }}</p>
                        <Link v-if="!step.done" :href="step.href" class="link-brand text-xs mt-2 inline-block">
                            Configure →
                        </Link>
                    </div>
                </li>
            </ol>

            <div v-if="setupComplete" class="card text-center space-y-4 border-emerald-200 bg-emerald-50/50">
                <p class="font-semibold text-emerald-900">All required settings are complete!</p>
                <p class="text-sm text-emerald-800">Your Sahodaya is ready for schools and events.</p>
                <form @submit.prevent="completeSetup">
                    <button type="submit" class="btn-primary" :disabled="completeForm.processing">
                        Finish setup →
                    </button>
                </form>
            </div>

            <div class="flex flex-wrap justify-end gap-3 pt-2">
                <Link :href="`/sahodaya-admin/${sahodaya.id}`" class="btn-secondary text-sm">← Back to dashboard</Link>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number,
    pendingPaymentsCount: Number,
    checklist: Array,
    completedCount: Number,
    totalSteps: Number,
    setupComplete: Boolean,
});

const completeForm = useForm({});

function completeSetup() {
    completeForm.post(`/sahodaya-admin/${props.sahodaya.id}/setup/complete`);
}
</script>
