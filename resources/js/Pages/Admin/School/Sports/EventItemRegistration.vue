<template>
    <SchoolAdminLayout :title="pageTitle" :school="school" :show-header-title="false">
        <PageHeader
            :title="pageTitle"
            eyebrow="Sports Meet · Step 2 · Register by Sport Event"
            :description="pageDescription"
        >
            <template #actions>
                <Link :href="eventAthletesHref" class="btn-secondary text-sm">← Step 1: Event registration</Link>
                <button type="button" class="btn-secondary text-sm" :disabled="!headRegistrationOpen" @click="showAddStudent = true">+ Add student</button>
                <a :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">Reports & ID cards</a>
            </template>
        </PageHeader>

        <SportsEventHeadRegistrationTabs
            :head-item-groups="headItemGroups"
            :items-base-url="itemsBaseUrl"
            :selected-head-id="selectedHeadId"
        />

        <div v-if="activeHead && headRegistrationOpen === false"
             class="notice-banner notice-banner--warning text-sm mb-4">
            <p class="font-semibold">Registration closed for {{ activeHead.head_name }}</p>
            <p class="mt-0.5">
                Window: {{ formatHeadWindow(activeHead) }}.
                You can view entries below but cannot add or change registrations until the window opens.
            </p>
        </div>

        <SportsEventItemRegistrationPanel
            :event="event"
            :students="students"
            :registrations="registrations"
            :program-base="programBase"
            :event-athletes-href="eventAthletesHref"
            :initial-head-id="initialHeadId"
            :selected-head-id="selectedHeadId"
            :head-registration-open="headRegistrationOpen"
            :active-head="activeHead"
            @add-student="showAddStudent = true"
        />

        <QuickAddStudentModal
            v-model="showAddStudent"
            :school="school"
            :school-classes="schoolClasses"
            :student-edit-lock="studentEditLock"
        />
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import QuickAddStudentModal from '@/Components/school/QuickAddStudentModal.vue';
import SportsEventItemRegistrationPanel from '@/Components/school/SportsEventItemRegistrationPanel.vue';
import SportsEventHeadRegistrationTabs from '@/Components/school/SportsEventHeadRegistrationTabs.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: { type: Object, required: true },
    headItemGroups: { type: Array, default: () => [] },
    hasItemHeads: { type: Boolean, default: false },
    registrations: { type: Array, default: () => [] },
    students: { type: Array, default: () => [] },
    eventType: { type: String, default: 'sports' },
    initialHeadId: { type: [Number, String], default: null },
    selectedHeadId: { type: [Number, String], default: null },
    schoolClasses: { type: Array, default: () => [] },
    studentEditLock: { type: Object, default: () => ({ locked: false }) },
});

const { programBase } = useSchoolProgramContext(props);
const showAddStudent = ref(false);

const headItemGroups = computed(() =>
    props.headItemGroups?.length
        ? props.headItemGroups
        : (props.event.head_navigation?.headItemGroups ?? []),
);

const itemsBaseUrl = computed(() => `${programBase.value}/events/${props.event.id}/items`);

const activeHead = computed(() => {
    if (props.selectedHeadId === 'other') {
        return headItemGroups.value.find((h) => h.head_id == null) ?? null;
    }
    if (props.selectedHeadId != null && props.selectedHeadId !== '') {
        return headItemGroups.value.find((h) => Number(h.head_id) === Number(props.selectedHeadId)) ?? null;
    }
    return headItemGroups.value[0] ?? null;
});

const headRegistrationOpen = computed(() => activeHead.value?.registration_open !== false);

const pageTitle = computed(() => {
    if (activeHead.value) {
        return `${activeHead.value.head_name} — ${props.event.title}`;
    }
    return `Register by Sport Event — ${props.event.title}`;
});

const pageDescription = computed(() => {
    if (!activeHead.value) {
        return 'Register athletes for sports events by Sport Event.';
    }
    const parts = [
        `${activeHead.value.item_count ?? 0} items`,
        `${activeHead.value.participant_count ?? 0} registered`,
    ];
    const reg = formatHeadWindow(activeHead.value);
    if (reg) parts.push(`Reg ${reg}`);
    return parts.join(' · ');
});

const eventAthletesHref = computed(() =>
    `${programBase.value}/registration?event=${props.event.id}`,
);

function formatHeadWindow(head) {
    const start = head?.reg_start;
    const end = head?.reg_end;
    if (start && end) return `${start} – ${end}`;
    if (start) return `from ${start}`;
    if (end) return `until ${end}`;
    return '';
}
</script>
