<template>
    <SahodayaAdminLayout title="Board Result Reports Hub" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Board Result Reports Hub" eyebrow="Academic Results"
                    description="Merit registers, rankings, pass %, Full A1 achievers, excellence awards, and student history lookup.">
            <template #actions>
                <div class="flex items-center gap-2 print:hidden">
                    <button type="button" @click="showHistoryModal = true" class="btn-primary text-xs flex items-center gap-1.5 font-bold shadow-xs">
                        <span>📜</span> Student History Lookup
                    </button>
                </div>
            </template>
        </PageHeader>

        <BoardResultsReportSubNav :sahodayaId="sahodaya.id" active="reports-hub" />

        <!-- KPI OVERVIEW CARDS -->
        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Available Reports</p>
                <p class="text-3xl font-extrabold text-[#0f3d7a] mt-1">{{ reports.length }}</p>
                <p class="text-xs text-slate-500 mt-1">Official Sahodaya board reports</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">School Summaries</p>
                <p class="text-3xl font-extrabold text-emerald-600 mt-1">{{ reportSections.school.items.length }}</p>
                <p class="text-xs text-slate-500 mt-1">Result summaries by class</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Merit & Rankings</p>
                <p class="text-3xl font-extrabold text-violet-600 mt-1">{{ reportSections.merit.items.length }}</p>
                <p class="text-xs text-slate-500 mt-1">Rank and topper registers</p>
            </div>
            <div class="bg-white rounded-2xl border border-indigo-200 p-5 shadow-2xs ring-1 ring-indigo-100">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Academic Year</p>
                <select
                    :value="filters.academic_year"
                    @change="changeYear($event.target.value)"
                    class="w-full text-sm font-bold text-slate-900 font-mono bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 cursor-pointer"
                >
                    <option v-for="yr in availableYears" :key="yr" :value="yr">{{ yr }}</option>
                </select>
                <p class="text-xs text-slate-400 mt-1.5">Switch academic session</p>
            </div>
        </div>

        <!-- REPORT CATEGORY SECTIONS -->
        <div class="space-y-8">
            <section v-for="section in reportSections" :key="section.key" class="space-y-4">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span>📂</span> {{ section.title }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ section.description }}</p>
                    </div>
                    <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-3 py-1 rounded-full">
                        {{ section.items.length }} report(s)
                    </span>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="r in section.items" :key="r.key"
                         class="group bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs hover:shadow-md hover:border-indigo-300 transition-all flex flex-col justify-between space-y-4">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <p class="font-extrabold text-slate-900 text-sm">{{ r.title }}</p>
                                <span class="text-[10px] font-mono font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded">{{ filters.academic_year }}</span>
                            </div>
                            <p v-if="r.description" class="text-xs text-slate-500 leading-relaxed">{{ r.description }}</p>
                        </div>
                        <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <Link :href="r.href" class="font-bold text-indigo-600 hover:text-indigo-800 transition flex items-center gap-1">
                                🔍 View Report
                            </Link>
                            <div class="flex items-center gap-1.5">
                                <a v-if="r.pdfUrl" :href="r.pdfUrl" target="_blank"
                                   class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 transition flex items-center gap-1">
                                    📄 PDF
                                </a>
                                <a v-if="r.pdfUrlNoRank" :href="r.pdfUrlNoRank" target="_blank"
                                   class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 transition flex items-center gap-1"
                                   title="Download Mark-wise order without ranks">
                                    📄 Marks-Wise
                                </a>
                                <a v-if="r.excelUrl" :href="r.excelUrl" target="_blank"
                                   class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 transition flex items-center gap-1">
                                    📊 Excel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- STUDENT HISTORY MODAL -->
        <StudentHistoryModal
            :show="showHistoryModal"
            :initialStudent="null"
            :sahodayaId="sahodaya.id"
            @close="showHistoryModal = false"
        />
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StudentHistoryModal from '@/Components/BoardResults/StudentHistoryModal.vue';
import BoardResultsReportSubNav from '@/Components/BoardResults/BoardResultsReportSubNav.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    reports: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    availableYears: { type: Array, default: () => [] },
});

const showHistoryModal = ref(false);

function changeYear(year) {
    router.visit(window.location.pathname, {
        data: { academic_year: year },
        preserveScroll: true,
        replace: true,
    });
}

const reportSections = computed(() => {
    const sections = {
        school: {
            key: 'school',
            title: 'School Performance & Summaries',
            description: 'Class-wise result summaries and pass rates by member school.',
            items: [],
        },
        merit: {
            key: 'merit',
            title: 'Merit Registers & Toppers',
            description: 'Overall rankings, Full A1 achievers, and subject topper registers.',
            items: [],
        },
        excellence: {
            key: 'excellence',
            title: 'Excellence & Historical Trends',
            description: 'Awards, school recognitions, and historical pass-percentage comparisons.',
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
