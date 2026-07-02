<template>
    <div class="space-y-4 max-w-2xl">
        <div v-if="suggestedStatus" class="card card--accent text-sm text-indigo-900">
            Suggested status: <strong>{{ suggestedStatus }}</strong>
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
import { Link } from '@inertiajs/vue3';
import { inject } from 'vue';

const { lifecycle, suggestedStatus, lifecycleLinks } = inject('eventSettings');
</script>
