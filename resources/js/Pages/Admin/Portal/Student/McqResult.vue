<template>
    <PortalLayout
        role-label="Student Portal"
        :title="registration.exam?.title ?? 'Exam result'"
        :subtitle="school.name"
        accent="indigo"
        :nav-items="navItems"
    >
        <div class="card space-y-3">
            <p class="text-sm text-gray-600">Your exam has been submitted.</p>
            <p v-if="showResults && mark" class="text-lg font-semibold text-indigo-700">
                Score: {{ mark.score }}
                <span v-if="mark.percentage != null"> ({{ mark.percentage }}%)</span>
                <span v-if="mark.grade"> · Grade {{ mark.grade }}</span>
            </p>
            <p v-else-if="!showResults" class="text-sm text-amber-700">Results will appear here once published by the exam coordinator.</p>
            <a v-if="certificateUrl" :href="certificateUrl" target="_blank" class="btn-secondary inline-block text-sm">Download certificate ↗</a>
            <ul v-if="showResults && mark?.answers_json?.length" class="text-sm divide-y">
                <li v-for="(a, i) in mark.answers_json" :key="i" class="py-2 flex justify-between gap-2">
                    <span>Question #{{ i + 1 }}</span>
                    <span :class="a.is_correct ? 'text-green-700' : 'text-red-600'">
                        {{ a.is_correct ? 'Correct' : (a.chosen ? 'Wrong' : 'Unanswered') }}
                    </span>
                </li>
            </ul>
            <a :href="`/portal/student/${school.id}`" class="btn-secondary inline-block text-sm">Back to dashboard</a>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';
import { computed } from 'vue';

const props = defineProps({
    school: Object,
    student: Object,
    registration: Object,
    mark: Object,
    showResults: Boolean,
    certificateUrl: { type: String, default: null },
});

const navItems = computed(() => studentPortalNavItems(props.school.id));
</script>
