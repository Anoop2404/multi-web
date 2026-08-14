<template>
    <section class="rounded-2xl border bg-white shadow-sm p-6" aria-labelledby="readiness-heading">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><p class="text-xs font-bold uppercase tracking-wider text-gray-400">Pre-publish review</p><h2 id="readiness-heading" class="text-xl font-bold text-gray-950 mt-1">Website readiness</h2></div>
            <div class="flex items-center gap-3"><span class="text-2xl font-black" :class="report.ready ? 'text-emerald-600' : 'text-amber-600'">{{ report.score ?? 0 }}%</span><span class="text-xs font-bold rounded-full px-3 py-1.5" :class="report.ready ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">{{ report.ready ? 'Ready' : 'Needs attention' }}</span></div>
        </div>
        <div v-if="report.errors?.length" class="mt-5 rounded-xl bg-red-50 border border-red-100 p-4"><p class="text-sm font-bold text-red-800 mb-2">Blocking issues</p><ul class="space-y-1 text-sm text-red-700 list-disc pl-5"><li v-for="item in report.errors" :key="item">{{ item }}</li></ul></div>
        <div v-if="report.warnings?.length" class="mt-3 rounded-xl bg-amber-50 border border-amber-100 p-4"><p class="text-sm font-bold text-amber-800 mb-2">Recommended improvements</p><ul class="space-y-1 text-sm text-amber-700 list-disc pl-5"><li v-for="item in report.warnings" :key="item">{{ item }}</li></ul></div>
        <p v-if="report.ready && !report.warnings?.length" class="mt-5 text-sm text-emerald-700">All required content and accessibility checks pass.</p>
    </section>
</template>
<script setup>defineProps({ report: { type: Object, default: () => ({ ready: false, errors: [], warnings: [], score: 0 }) } });</script>
