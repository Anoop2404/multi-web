<template>
    <div v-for="(reports, module) in modules" :key="module" class="mb-8">
        <h2 class="section-title capitalize mb-3">{{ module.replace(/-/g, ' ') }}</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <component :is="r.href ? Link : 'div'" v-for="r in reports" :key="r.id"
                       :href="r.href"
                       class="card transition !p-4 block"
                       :class="r.href ? 'hover:border-[#0f3d7a]/30 cursor-pointer' : 'opacity-75'">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-xs font-mono text-slate-400">{{ r.id }}</p>
                    <span v-if="r.runnable" class="text-[10px] font-bold uppercase tracking-wide text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">Live</span>
                    <span v-else-if="r.scope === 'event'" class="text-[10px] font-bold uppercase tracking-wide text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded">Event</span>
                </div>
                <p class="font-semibold text-[#0f3d7a] mt-1">{{ r.label }}</p>
                <p class="text-xs text-slate-500 mt-1 capitalize">{{ r.classification }}</p>
                <p v-if="r.note" class="text-xs text-slate-400 mt-2">{{ r.note }}</p>
            </component>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    modules: { type: Object, default: () => ({}) },
});
</script>
