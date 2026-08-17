<template>
    <SchoolAdminLayout :title="pageTitle" :school="school" :show-header-title="false">
        <!-- CONSOLIDATED WORKSPACE TAB STRIP — one page for the four board-results
             entry flows (Overview, Toppers, Subject-Wise Toppers, Full A1 Achievers).
             See docs/BOARD_RESULTS_UX_REDESIGN_PLAN.md §3.1. Each tab is a real link
             to its existing route/controller method, so switching tabs only fetches
             the data that tab needs (no upfront over-fetch of the larger listings). -->
        <div class="flex items-center gap-1.5 bg-white p-1.5 rounded-2xl shadow-xs border border-gray-200 mb-6 overflow-x-auto">
            <Link
                v-for="tab in visibleTabs"
                :key="tab.key"
                :href="tab.href"
                class="px-4 py-2 text-xs font-bold rounded-xl transition-all whitespace-nowrap flex items-center gap-1.5"
                :class="tab.key === activeTab ? 'bg-[#0f3d7a] text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
            >
                <span>{{ tab.icon }}</span> {{ tab.label }}
            </Link>
        </div>

        <OverviewPanel
            v-if="activeTab === 'overview'"
            :school="school"
            :results="results"
            :statuses="statuses"
            :audit-history="auditHistory"
            :topper-cap="topperCap"
            :selected-class="selectedClass"
            :academic-year-options="academicYearOptions"
            :selected-academic-year="selectedAcademicYear"
            :stream-options="streamOptions"
            :active-result="activeResult"
            :active-result-context="activeResultContext"
            :marks-config="marksConfig"
        />

        <template v-else-if="!currentBoardResult">
            <div class="p-12 text-center text-gray-400 text-sm card bg-white rounded-2xl border border-gray-200">
                Save a draft on the <button type="button" class="text-indigo-600 font-semibold hover:underline" @click="goToOverview">Overview</button> tab first — this section needs a board result to already exist for the selected class &amp; academic year.
            </div>
        </template>

        <ToppersPanel
            v-else-if="activeTab === 'toppers'"
            :school="school"
            :board-result="currentBoardResult"
            :is-class12="isClass12"
            :stream-options="streamOptions"
            :standard-subjects="standardSubjects"
            :subjects-by-stream="subjectsByStream"
            :subject-wise-leaders="subjectWiseLeaders"
            :can-edit="canEdit"
            :topper-cap="topperCap"
            :topper-count="topperCount"
            :marks-config="marksConfig"
        />

        <SubjectToppersPanel
            v-else-if="activeTab === 'subject-toppers'"
            :school="school"
            :board-result="currentBoardResult"
            :academic-year="currentYear"
            :academic-year-options="academicYearOptions"
            :standard-subjects="standardSubjects"
            :subject-wise-leaders="subjectWiseLeaders"
            :can-edit="canEdit"
        />

        <FullA1AchieversPanel
            v-else-if="activeTab === 'full-a1'"
            :school="school"
            :board-result="currentBoardResult"
            :academic-year="currentYear"
            :academic-year-options="academicYearOptions"
            :standard-subjects="standardSubjects"
            :subject-codes="subjectCodes"
            :stream-options="streamOptions"
            :can-edit="canEdit"
            :edit-lock-reason="editLockReason"
        />
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import OverviewPanel from './Workspace/OverviewPanel.vue';
import ToppersPanel from './Workspace/ToppersPanel.vue';
import SubjectToppersPanel from './Workspace/SubjectToppersPanel.vue';
import FullA1AchieversPanel from './Workspace/FullA1AchieversPanel.vue';

const props = defineProps({
    school: Object,
    activeTab: { type: String, default: 'overview' },

    // Overview (formerly Index.vue)
    results: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    auditHistory: { type: Array, default: () => [] },
    topperCap: { type: Number, default: 5 },
    selectedClass: { type: Number, default: null },
    selectedAcademicYear: { type: String, default: null },
    activeResult: { type: Object, default: null },
    activeResultContext: { type: Object, default: null },

    // Shared across Toppers / Subject-Wise Toppers / Full A1 Achievers
    boardResult: { type: Object, default: null },
    academicYear: { type: String, default: null },
    academicYearOptions: { type: Array, default: () => [] },
    streamOptions: { type: Object, default: () => ({}) },
    standardSubjects: { type: Array, default: () => [] },
    subjectsByStream: { type: Object, default: () => ({}) },
    subjectWiseLeaders: { type: Array, default: () => [] },
    subjectCodes: { type: Object, default: () => ({}) },
    canEdit: { type: Boolean, default: true },
    editLockReason: { type: String, default: null },
    isClass12: { type: Boolean, default: false },
    topperCount: { type: Number, default: 0 },
    marksConfig: { type: Object, default: () => ({ classX: 500, byStream: {} }) },
});

const pageTitle = computed(() => {
    const labels = {
        overview: 'Board Results',
        toppers: 'Manage Toppers',
        'subject-toppers': 'Subject-Wise Toppers',
        'full-a1': 'Full A1 Achievers',
    };
    return labels[props.activeTab] ?? 'Board Results';
});

// One board result backs every tab except Overview (which may still be in "create"
// mode with nothing saved yet) — Overview's activeResult and the other three tabs'
// boardResult are the same underlying row, just returned under different prop names
// by the controller methods each tab's route still hits. See BoardResultController.
const currentBoardResult = computed(() => props.boardResult ?? props.activeResult ?? null);

const currentClass = computed(() =>
    props.selectedClass ?? currentBoardResult.value?.class ?? 10
);

const currentYear = computed(() =>
    props.selectedAcademicYear ?? props.academicYear ?? currentBoardResult.value?.academic_year ?? ''
);

function qs(params) {
    const clean = Object.fromEntries(Object.entries(params).filter(([, v]) => v !== null && v !== undefined && v !== ''));
    const s = new URLSearchParams(clean).toString();
    return s ? `?${s}` : '';
}

const visibleTabs = computed(() => {
    const base = `/school-admin/${props.school.id}/board-results`;
    const tabs = [
        {
            key: 'overview',
            icon: '📋',
            label: 'Summary & Overall Toppers',
            href: `${base}${qs({ class: currentClass.value, academic_year: currentYear.value })}`,
        },
    ];

    if (currentBoardResult.value?.id) {
        tabs.push({
            key: 'toppers',
            icon: '🏆',
            label: 'Manage Toppers',
            href: `${base}/${currentBoardResult.value.id}/toppers`,
        });
    }

    if (currentClass.value === 12) {
        tabs.push({
            key: 'subject-toppers',
            icon: '🎯',
            label: 'Subject-Wise Toppers',
            href: `${base}/subject-toppers${qs({ academic_year: currentYear.value })}`,
        });
    }

    tabs.push({
        key: 'full-a1',
        icon: '🏅',
        label: 'Full A1 Achievers',
        href: `${base}/full-a1-achievers${qs({ class: currentClass.value, academic_year: currentYear.value })}`,
    });

    return tabs;
});

function goToOverview() {
    router.get(`/school-admin/${props.school.id}/board-results`, {
        class: currentClass.value,
        academic_year: currentYear.value,
    });
}
</script>
