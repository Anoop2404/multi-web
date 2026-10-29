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
                <span v-if="mark.rank"> · Rank {{ mark.rank }}</span>
            </p>
            <p v-else-if="!showResults" class="text-sm text-amber-700">
                Results not released yet. Your score will appear here once the exam coordinator publishes results.
            </p>
            <p v-else class="text-sm text-slate-500">Submitted — mark details are not available.</p>

            <a v-if="certificateUrl" :href="certificateUrl" target="_blank" class="btn-secondary inline-block text-sm">Download certificate ↗</a>
            <p v-else-if="certificatesEnabled && certificateUnavailableReason" class="text-xs text-slate-500">
                {{ certificateUnavailableReason }}
            </p>

            <div v-if="canReattempt && reattemptUrl" class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-3 space-y-2">
                <p class="text-sm text-sky-900 font-medium">
                    You can take this exam again
                    <span v-if="attemptsRemaining"> · {{ attemptsRemaining }} attempt{{ attemptsRemaining === 1 ? '' : 's' }} left</span>
                </p>
                <button type="button" class="btn-primary text-sm" :disabled="reattempting" @click="startReattempt">
                    {{ reattempting ? 'Starting…' : 'Start reattempt' }}
                </button>
            </div>

            <ul v-if="showResults && mark?.answers_json?.length" class="text-sm divide-y border-t border-slate-100 pt-2">
                <li class="py-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">Question review</li>
                <li v-for="(a, i) in mark.answers_json" :key="i" class="py-2 flex justify-between gap-2">
                    <span>
                        Question #{{ a.index ?? (i + 1) }}
                        <span v-if="a.correct != null" class="text-xs text-slate-500 ml-1">
                            (correct: {{ formatKey(a.correct) }})
                        </span>
                    </span>
                    <span :class="a.is_correct ? 'text-green-700' : 'text-red-600'">
                        {{ a.is_correct ? 'Correct' : (a.chosen ? 'Wrong' : 'Unanswered') }}
                        <span v-if="a.chosen" class="text-slate-500"> · you: {{ formatKey(a.chosen) }}</span>
                    </span>
                </li>
            </ul>

            <a :href="`/portal/student/${school.id}/mcq`" class="btn-secondary inline-block text-sm">Back to online exams</a>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    school: Object,
    student: Object,
    registration: Object,
    mark: Object,
    showResults: Boolean,
    resultVisibility: { type: String, default: 'score_grade' },
    certificateUrl: { type: String, default: null },
    certificateUnavailableReason: { type: String, default: null },
    certificatesEnabled: { type: Boolean, default: false },
    canReattempt: { type: Boolean, default: false },
    reattemptUrl: { type: String, default: null },
    attemptsRemaining: { type: Number, default: 0 },
});

const reattempting = ref(false);
const navItems = computed(() => studentPortalNavItems(props.school.id));

function startReattempt() {
    if (!props.reattemptUrl || reattempting.value) return;
    reattempting.value = true;
    router.post(props.reattemptUrl, {}, {
        onFinish: () => { reattempting.value = false; },
    });
}

function formatKey(value) {
    if (Array.isArray(value)) return value.join(', ');
    return value == null ? '—' : String(value);
}
</script>
