<template>
    <div class="space-y-5">
        <PageHeader :title="title" :eyebrow="eyebrow" :description="description">
            <template v-if="$slots.actions" #actions>
                <slot name="actions" />
            </template>
        </PageHeader>

        <div v-if="stats.length" class="grid gap-3" :class="statsGridClass">
            <div v-for="stat in stats" :key="stat.label" class="card py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ stat.label }}</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ stat.value }}</p>
                <p v-if="stat.hint" class="text-xs text-slate-400 mt-0.5">{{ stat.hint }}</p>
            </div>
        </div>

        <div v-if="!assignments.length" class="card text-sm text-slate-500">
            {{ emptyMessage }}
        </div>

        <div v-else class="space-y-3">
            <div v-for="(item, index) in assignments" :key="item.key ?? index" class="card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <h2 class="font-semibold text-slate-900">{{ item.title }}</h2>
                        <p v-if="item.subtitle" class="text-xs text-slate-500 mt-0.5">{{ item.subtitle }}</p>
                        <ul v-if="item.details?.length" class="text-sm mt-2 space-y-1 text-slate-600">
                            <li v-for="(detail, di) in item.details" :key="di">· {{ detail }}</li>
                        </ul>
                        <p v-if="item.meta" class="text-xs text-indigo-700 mt-2">{{ item.meta }}</p>
                    </div>
                    <div v-if="item.badge" class="text-right text-xs shrink-0">
                        <p class="font-semibold text-slate-900">{{ item.badge }}</p>
                        <p v-if="item.badgeHint" class="text-amber-700 mt-0.5">{{ item.badgeHint }}</p>
                    </div>
                </div>
                <div v-if="item.actions?.length" class="flex flex-wrap gap-3 mt-3 pt-3 border-t border-slate-100">
                    <a v-for="action in item.actions" :key="action.href"
                       :href="action.href"
                       class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                        {{ action.label }} →
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: 'My assignments' },
    eyebrow: { type: String, default: 'Portal' },
    description: { type: String, default: 'Events and duties assigned to your account.' },
    emptyMessage: { type: String, default: 'No assignments yet. Contact your administrator.' },
    stats: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
});

const statsGridClass = computed(() => {
    const n = props.stats.length;
    if (n <= 1) return 'sm:grid-cols-1';
    if (n === 2) return 'sm:grid-cols-2';
    if (n === 3) return 'sm:grid-cols-3';
    return 'sm:grid-cols-2 lg:grid-cols-4';
});
</script>
