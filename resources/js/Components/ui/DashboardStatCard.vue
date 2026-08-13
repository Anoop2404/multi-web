<template>
    <component :is="href ? Link : 'div'"
               :href="href"
               class="dash-stat group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-4 shadow-[0_2px_8px_rgba(15,23,42,0.04)] transition duration-200"
               :class="[`dash-stat--${tone}`, href ? 'dash-stat--link hover:-translate-y-1 hover:shadow-[0_8px_24px_rgba(15,23,42,0.08)]' : '']">
        <div class="dash-stat-icon shrink-0" aria-hidden="true">
            <span v-if="renderIconSvg" class="w-5 h-5 inline-block" v-html="renderIconSvg" />
            <span v-else>{{ icon }}</span>
        </div>
        <div class="dash-stat-body min-w-0 flex-1">
            <p class="dash-stat-value text-2xl font-extrabold text-slate-900 tracking-tight leading-none">{{ value }}</p>
            <p class="dash-stat-label text-[11px] font-bold uppercase tracking-wider text-slate-500 mt-1.5 truncate">{{ label }}</p>
            <p v-if="hint" class="dash-stat-hint text-xs font-semibold mt-1">{{ hint }}</p>
        </div>
        <span v-if="href" class="dash-stat-arrow text-slate-300 transition duration-200 group-hover:translate-x-1 group-hover:text-[#0f3d7a]" aria-hidden="true">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </span>
    </component>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, default: '📊' },
    hint: { type: String, default: null },
    href: { type: String, default: null },
    tone: { type: String, default: 'navy' },
});

const renderIconSvg = computed(() => {
    const map = {
        '📚': `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>`,
        '🎓': `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>`,
        '🎭': `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>`,
        '🔔': `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 01-6 0v-1m6 0H9"/></svg>`,
        '📊': `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>`,
    };
    return map[props.icon] || null;
});
</script>

