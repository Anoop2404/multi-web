<template>
    <div class="space-y-6">
        <!-- Header banner -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white rounded-2xl p-6 border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)]">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
                    <span>Platform Rules</span>
                    <span>•</span>
                    <span class="text-indigo-600 font-bold">Global Master Data</span>
                </div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-3">
                    <span>{{ title }}</span>
                    <span v-if="count !== undefined" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                        {{ count }} items
                    </span>
                </h1>
                <p class="text-sm text-slate-500 mt-1 max-w-2xl">
                    {{ description || 'Global master datasets shared across all Sahodaya clusters and member schools for standardized registration, competition grouping, and teacher management.' }}
                </p>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <!-- Master Data Tab Bar -->
        <div class="bg-white rounded-2xl p-1.5 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.02)] overflow-x-auto">
            <nav class="flex items-center gap-1.5 min-w-max">
                <Link
                    v-for="tab in tabs"
                    :key="tab.href"
                    :href="tab.href"
                    class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                    :class="activeTab === tab.id
                        ? 'bg-indigo-600 text-white shadow-xs'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'"
                >
                    <span class="text-base leading-none">{{ tab.icon }}</span>
                    <span>{{ tab.label }}</span>
                </Link>
            </nav>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    activeTab: { type: String, required: true }, // 'class-categories' | 'teaching-types' | 'subjects' | 'designations' | 'age-categories'
    count: { type: [Number, String], default: undefined },
});

const tabs = [
    { id: 'class-categories', label: 'Class Categories', href: '/admin/master-data/class-categories', icon: '📚' },
    { id: 'teaching-types', label: 'Teaching Types', href: '/admin/master-data/teaching-types', icon: '🎓' },
    { id: 'subjects', label: 'Subjects', href: '/admin/master-data/subjects', icon: '📖' },
    { id: 'designations', label: 'Designations', href: '/admin/master-data/designations', icon: '🏷️' },
    { id: 'age-categories', label: 'Age Categories', href: '/admin/master-data/age-categories', icon: '⏱️' },
];
</script>
