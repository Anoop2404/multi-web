<template>
    <div class="border-b border-slate-200 pb-4 mb-4">
        <nav class="hidden md:flex flex-wrap gap-2" aria-label="Exam sections">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="tab.href"
                :class="active === tab.key ? 'subnav-link subnav-link--active' : 'subnav-link'"
            >
                {{ tab.label }}
            </Link>
        </nav>

        <nav class="md:hidden flex flex-wrap items-center gap-2" aria-label="Exam sections">
            <Link
                v-for="tab in primaryTabs"
                :key="tab.key"
                :href="tab.href"
                :class="active === tab.key ? 'subnav-link subnav-link--active' : 'subnav-link'"
            >
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
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    schoolId: { type: [String, Number], required: true },
    examId: { type: [String, Number], required: true },
    active: { type: String, required: true },
    resultsPublished: { type: Boolean, default: false },
    schoolLevelAwardsEnabled: { type: Boolean, default: false },
});

const moreOpen = ref(false);
const PRIMARY_KEYS = ['register', 'fee', 'hall-tickets', 'attendance', 'mark-entry', 'results'];

const base = computed(() => `/school-admin/${props.schoolId}/mcq/${props.examId}`);

const tabs = computed(() => {
    // Workflow order: Register → Fee → Hall tickets → Attendance → (Mark entry) → Students → Reports → Results
    const list = [
        { key: 'register', label: 'Register', href: `${base.value}/register` },
        { key: 'fee', label: 'Fee & payment', href: `${base.value}/fee` },
        { key: 'hall-tickets', label: 'Hall tickets', href: `${base.value}/hall-tickets` },
        { key: 'attendance', label: 'Attendance', href: `${base.value}/attendance` },
    ];

    if (props.schoolLevelAwardsEnabled) {
        list.push({ key: 'mark-entry', label: 'Mark entry', href: `${base.value}/mark-entry` });
    }

    list.push(
        { key: 'students', label: 'Students', href: `${base.value}/students` },
        { key: 'reports', label: 'Reports', href: `${base.value}/reports` },
    );

    if (props.resultsPublished) {
        list.push(
            { key: 'results', label: 'Results', href: `${base.value}/results` },
            { key: 'toppers', label: 'Toppers', href: `${base.value}/toppers` },
        );
    }

    return list;
});

const primaryTabs = computed(() => {
    const primary = tabs.value.filter((t) => PRIMARY_KEYS.includes(t.key));
    if (tabs.value.some((t) => t.key === props.active) && !primary.some((t) => t.key === props.active)) {
        const activeTab = tabs.value.find((t) => t.key === props.active);
        if (activeTab) primary.push(activeTab);
    }
    return primary;
});

const moreTabs = computed(() => tabs.value.filter((t) => !primaryTabs.value.some((p) => p.key === t.key)));
const moreContainsActive = computed(() => moreTabs.value.some((t) => t.key === props.active));

function onDocClick(e) {
    if (!moreOpen.value) return;
    if (!(e.target instanceof Element)) return;
    if (!e.target.closest('[aria-haspopup="true"]') && !e.target.closest('[role="menu"]')) {
        moreOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocClick));
onUnmounted(() => document.removeEventListener('click', onDocClick));
</script>
