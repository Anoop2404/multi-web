<template>
    <PortalLayout role-label="State Judge" title="State Kalotsav" accent="amber" :nav-items="[]">
        <div class="space-y-4">
            <div class="card">
                <h2 class="text-sm font-semibold text-slate-800">Your panels</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    You score by chest number. Which Sahodaya or school a performance came from is not
                    shown here, and that is deliberate.
                </p>
            </div>

            <div v-if="!assignments.length" class="card text-sm text-slate-400">
                No items are assigned to you yet. The State office adds panels per item.
            </div>

            <div v-for="a in assignments" :key="a.assignment_id" class="card space-y-2">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3 class="truncate text-sm font-semibold text-slate-800">{{ a.item || a.item_code }}</h3>
                        <p class="text-xs text-slate-500">
                            {{ a.event }}
                            <span v-if="a.item_code" class="font-mono"> · {{ a.item_code }}</span>
                        </p>
                    </div>
                    <span v-if="a.submitted_at" class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700">Submitted</span>
                    <span v-else-if="a.scoring_locked" class="shrink-0 rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600">Locked</span>
                </div>

                <div class="h-2 overflow-hidden rounded-full bg-amber-100">
                    <div class="h-full rounded-full transition-all"
                         :class="a.scored >= a.total && a.total ? 'bg-emerald-500' : 'bg-amber-400'"
                         :style="{ width: `${a.total ? Math.min(100, Math.round((a.scored / a.total) * 100)) : 0}%` }" />
                </div>

                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold" :class="a.scored >= a.total && a.total ? 'text-emerald-700' : 'text-amber-700'">
                        {{ a.scored }} / {{ a.total }} scored
                    </span>
                    <Link :href="a.href" class="font-semibold text-indigo-600">
                        {{ a.submitted_at ? 'Review' : 'Score' }} →
                    </Link>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

defineProps({ assignments: Array, judge: Object });
</script>
