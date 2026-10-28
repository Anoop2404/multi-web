<template>
    <div class="border-b border-slate-200 pb-4 mb-4">
        <!-- Desktop / wide: wrap all tabs -->
        <nav class="hidden md:flex flex-wrap gap-2" aria-label="Exam sections">
            <Link v-for="tab in tabs" :key="tab.key"
                  :href="tab.href"
                  :class="active === tab.key ? 'subnav-link subnav-link--active' : 'subnav-link'">
                {{ tab.label }}
            </Link>
        </nav>

        <!-- Mobile: primary tabs + More -->
        <nav class="md:hidden flex flex-wrap items-center gap-2" aria-label="Exam sections">
            <Link v-for="tab in primaryTabs" :key="tab.key"
                  :href="tab.href"
                  :class="active === tab.key ? 'subnav-link subnav-link--active' : 'subnav-link'">
                {{ tab.label }}
            </Link>
            <div v-if="moreTabs.length" class="relative">
                <button
                    type="button"
                    class="subnav-link"
                    :class="moreOpen || moreContainsActive ? 'subnav-link--active' : ''"
                    :aria-expanded="moreOpen"
                    aria-haspopup="true"
                    @click="moreOpen = !moreOpen"
                >
                    More{{ moreContainsActive ? ' ·' : '' }}
                </button>
                <div
                    v-if="moreOpen"
                    class="absolute left-0 top-full mt-1 z-30 min-w-[11rem] rounded-lg border border-slate-200 bg-white shadow-lg py-1"
                    role="menu"
                >
                    <Link
                        v-for="tab in moreTabs"
                        :key="tab.key"
                        :href="tab.href"
                        role="menuitem"
                        class="block px-3 py-2 text-sm hover:bg-slate-50"
                        :class="active === tab.key ? 'font-semibold text-indigo-800 bg-indigo-50' : 'text-slate-700'"
                        @click="moreOpen = false"
                    >
                        {{ tab.label }}
                    </Link>
                </div>
            </div>
        </nav>

        <p v-if="deliveryMode === 'online' && usesEngine === null" class="mt-2 text-[11px] text-slate-500">
            Live session is the portal proctor view. Open Live monitor from Paper if this exam uses the online exam engine.
        </p>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    examId: { type: [String, Number], required: true },
    active: { type: String, required: true },
    deliveryMode: { type: String, default: 'offline' },
    resultsPublished: { type: Boolean, default: false },
    // true → Live monitor only; false/null → Live session only (never both).
    usesEngine: { type: [Boolean, null], default: null },
    /** When true, hide Talent Search fee/hall-ticket ceremony tabs. */
    isModelExam: { type: Boolean, default: false },
});

const moreOpen = ref(false);
const PRIMARY_KEYS = ['overview', 'question-banks', 'attendance', 'results', 'reports', 'session', 'live'];

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/mcq-exams/${props.examId}`);

const questionBankLabel = computed(() =>
    props.deliveryMode === 'online'
        ? 'Paper'
        : 'Paper (online only)',
);

const tabs = computed(() => {
    const list = [
        { key: 'overview', label: 'Overview', href: base.value },
    ];

    if (! props.isModelExam) {
        list.push(
            { key: 'payments', label: 'Payments', href: `${base.value}/payments` },
            { key: 'ledger', label: 'Ledger', href: `${base.value}/ledger` },
        );
    }

    list.push({ key: 'question-banks', label: questionBankLabel.value, href: `${base.value}/question-banks` });

    if (! props.isModelExam) {
        list.push(
            { key: 'centre-codes', label: 'Centre codes', href: `${base.value}/centre-codes` },
            { key: 'hall-tickets', label: 'Hall tickets', href: `${base.value}/hall-tickets` },
        );
    }

    list.push(
        { key: 'attendance', label: 'Attendance', href: `${base.value}/attendance` },
        { key: 'attendance-corrections', label: 'Corrections', href: `${base.value}/attendance-corrections` },
        { key: 'results', label: 'Results & marks', href: `${base.value}/results` },
        { key: 'reports', label: 'Reports', href: `${base.value}/reports` },
    );

    if (props.deliveryMode === 'online') {
        if (props.usesEngine === true) {
            list.push({ key: 'live', label: 'Live monitor', href: `${base.value}/live` });
        } else {
            list.push({ key: 'session', label: 'Live session', href: `${base.value}/session` });
        }
    }

    if (props.resultsPublished) {
        list.push({ key: 'leaderboard', label: 'Leaderboard', href: `${base.value}/leaderboard` });
    }

    list.push(
        { key: 'staff', label: 'Exam staff', href: `${base.value}/staff` },
        { key: 'activity', label: 'Activity', href: `${base.value}/activity` },
    );

    return list;
});

const primaryTabs = computed(() => {
    const all = tabs.value;
    const preferred = all.filter((t) => PRIMARY_KEYS.includes(t.key));
    // Always include the active tab in the primary row when it would otherwise be in More.
    const activeTab = all.find((t) => t.key === props.active);
    const keys = new Set(preferred.map((t) => t.key));
    if (activeTab && ! keys.has(activeTab.key)) {
        preferred.push(activeTab);
    }
    return preferred.slice(0, 6);
});

const moreTabs = computed(() => {
    const primaryKeys = new Set(primaryTabs.value.map((t) => t.key));
    return tabs.value.filter((t) => ! primaryKeys.has(t.key));
});

const moreContainsActive = computed(() => moreTabs.value.some((t) => t.key === props.active));

function onDocClick(e) {
    if (! moreOpen.value) return;
    // Close when clicking outside the More control.
    const el = e.target;
    if (el instanceof Element && ! el.closest('[aria-haspopup="true"]') && ! el.closest('[role="menu"]')) {
        moreOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocClick));
onUnmounted(() => document.removeEventListener('click', onDocClick));
</script>
