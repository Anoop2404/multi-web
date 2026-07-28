<template>
    <SahodayaAdminLayout title="Board result reports" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Board result reports" eyebrow="Academic Results"
                    description="Merit registers, rankings, pass %, excellence awards, and historical comparisons." />

        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
            <div class="card !p-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Reports</p>
                <p class="text-2xl font-bold text-[#0f3d7a] mt-1">{{ reports.length }}</p>
                <p class="text-xs text-slate-500 mt-1">Available board-result reports</p>
            </div>
            <div class="card !p-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">School Summaries</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ reportSections.school.items.length }}</p>
                <p class="text-xs text-slate-500 mt-1">Result summaries by class</p>
            </div>
            <div class="card !p-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Merit & Rankings</p>
                <p class="text-2xl font-bold text-violet-600 mt-1">{{ reportSections.merit.items.length }}</p>
                <p class="text-xs text-slate-500 mt-1">Rank and topper registers</p>
            </div>
            <div class="card !p-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Year</p>
                <p class="text-sm font-bold text-slate-900 mt-1">{{ filters.academic_year }}</p>
                <p class="text-xs text-slate-500 mt-1">Active reporting year</p>
            </div>
        </div>

        <div class="space-y-6">
            <section v-for="section in reportSections" :key="section.key" class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">{{ section.title }}</h3>
                        <p class="text-xs text-slate-500">{{ section.description }}</p>
                    </div>
                    <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">
                        {{ section.items.length }} report(s)
                    </span>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <Link v-for="r in section.items" :key="r.key" :href="r.href" class="card !p-5 hover:border-[#0f3d7a] transition">
                        <p class="font-semibold text-[#0f3d7a]">{{ r.title }}</p>
                        <p v-if="r.description" class="text-xs text-slate-500 mt-1">{{ r.description }}</p>
                        <p class="text-xs text-slate-400 mt-2">Academic year {{ filters.academic_year }}</p>
                    </Link>
                </div>
            </section>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    reports: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const reportSections = computed(() => {
    const sections = {
        school: {
            key: 'school',
            title: 'School Performance',
            description: 'Class-wise result summaries by member school.',
            items: [],
        },
        merit: {
            key: 'merit',
            title: 'Merit and Rankings',
            description: 'Overall rankings, pass rate reports, and topper registers.',
            items: [],
        },
        excellence: {
            key: 'excellence',
            title: 'Excellence and Comparison',
            description: 'Awards and historical pass-percentage comparison.',
            items: [],
        },
    };

    (props.reports ?? []).forEach((report) => {
        const key = String(report.key || '').toLowerCase();
        if (key.includes('001') || key.includes('003')) {
            sections.school.items.push(report);
        } else if (key.includes('excellence')) {
            sections.excellence.items.push(report);
        } else {
            sections.merit.items.push(report);
        }
    });

    return sections;
});
</script>
