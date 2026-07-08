<template>
    <PortalLayout
        role-label="Student Portal"
        :title="`${student.name} — Sports`"
        :subtitle="school.name"
        accent="indigo"
        :nav-items="navItems"
    >
        <section class="card mb-4">
            <h2 class="font-semibold text-sm mb-2">Sports meet results</h2>
            <p class="text-xs text-slate-500 mb-4">Your positions and measurements from published sports events.</p>

            <p v-if="!results.length" class="text-sm text-gray-400 py-4">No sports results published yet.</p>

            <div v-else class="space-y-6">
                <section v-for="group in groupedResults" :key="group.key">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">{{ group.label }}</h3>
                    <ul class="text-sm divide-y border border-slate-100 rounded-xl overflow-hidden bg-white">
                        <li v-for="(r, i) in group.items" :key="i" class="px-4 py-3">
                            <p class="font-medium">{{ r.event_title }}</p>
                            <p class="text-xs text-slate-600 mt-0.5">
                                <span v-if="r.head_name" class="text-slate-500">{{ r.head_name }} · </span>
                                {{ r.item_title }}
                            </p>
                            <p class="text-xs text-indigo-700 mt-1">
                                <span v-if="r.position">Position: {{ r.position }}</span>
                                <span v-if="r.grade"> · Grade: {{ r.grade }}</span>
                                <span v-if="r.score"> · Score: {{ r.score }}</span>
                                <span v-if="r.measurement"> · {{ r.measurement }}</span>
                            </p>
                            <p v-if="r.record_label" class="text-xs text-amber-700 mt-1 font-medium">{{ r.record_label }}</p>
                        </li>
                    </ul>
                </section>
            </div>
        </section>

        <Link :href="`/portal/student/${school.id}`" class="text-sm text-indigo-600 hover:underline">← Dashboard</Link>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';

const props = defineProps({
    school: Object,
    student: Object,
    results: { type: Array, default: () => [] },
});

const navItems = computed(() => studentPortalNavItems(props.school.id));

const groupedResults = computed(() => {
    const map = new Map();

    for (const r of props.results) {
        const key = r.head_name || 'Other items';
        if (!map.has(key)) {
            map.set(key, []);
        }
        map.get(key).push(r);
    }

    return [...map.entries()].map(([label, items]) => ({ key: label, label, items }));
});
</script>
