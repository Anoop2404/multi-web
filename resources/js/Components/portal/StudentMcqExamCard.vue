<template>
    <article class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1 space-y-1.5">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-bold text-slate-900 text-sm sm:text-base leading-snug">
                        {{ exam.exam?.title ?? exam.title }}
                    </h3>
                    <span
                        v-if="exam.lifecycle_status?.label"
                        class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full"
                        :class="lifecycleClass(exam.lifecycle_status)"
                    >{{ exam.lifecycle_status.label }}</span>
                    <span
                        v-if="exam.delivery_mode"
                        class="text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full"
                        :class="exam.delivery_mode === 'online' ? 'bg-sky-50 text-sky-800' : 'bg-slate-100 text-slate-600'"
                    >{{ exam.delivery_mode }}</span>
                </div>
                <p v-if="exam.product_label" class="text-xs text-slate-500">{{ exam.product_label }}</p>
                <p v-if="exam.venue_keycode" class="text-xs text-slate-600">
                    Venue keycode <span class="font-mono font-semibold tracking-wide">{{ exam.venue_keycode }}</span>
                </p>
                <p v-if="metaLine" class="text-xs text-slate-500">{{ metaLine }}</p>
                <p v-if="exam.show_results && exam.mark" class="text-xs font-semibold text-[#0f3d7a]">
                    Score {{ exam.mark.score }}
                    <span v-if="exam.mark.grade"> · Grade {{ exam.mark.grade }}</span>
                    <span v-if="exam.mark.rank"> · Rank {{ exam.mark.rank }}</span>
                </p>
                <p v-else-if="exam.result_state === 'held'" class="text-xs text-amber-700">Results not released yet</p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <a
                    v-if="exam.can_take_online"
                    :href="examUrl"
                    class="inline-flex items-center justify-center rounded-xl bg-[#0f3d7a] px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-[#1e5aa8]"
                >Take exam</a>
                <button
                    v-else-if="exam.can_reattempt"
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl bg-[#0f3d7a] px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-[#1e5aa8]"
                    :disabled="reattempting"
                    @click="startReattempt"
                >{{ reattempting ? 'Starting…' : `Reattempt (${exam.attempt_number || 1}/${exam.max_attempts || 1})` }}</button>
                <a
                    v-else-if="exam.show_results"
                    :href="examUrl"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-800 hover:bg-slate-50"
                >View result</a>
                <a
                    v-if="exam.show_hall_ticket"
                    :href="hallTicketUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                >Hall ticket</a>
                <a
                    v-if="exam.certificate_url"
                    :href="exam.certificate_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-100"
                >Certificate</a>
                <a
                    v-if="exam.invoice_url"
                    :href="exam.invoice_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                >Invoice</a>
            </div>
        </div>
    </article>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    schoolId: { type: [String, Number], required: true },
    exam: { type: Object, required: true },
});

const reattempting = ref(false);
const regId = computed(() => props.exam.registration_route_id ?? props.exam.id);
const examUrl = computed(() => `/portal/student/${props.schoolId}/mcq/${regId.value}/exam`);
const hallTicketUrl = computed(() => `/portal/student/${props.schoolId}/mcq/${regId.value}/hall-ticket`);

function startReattempt() {
    if (reattempting.value) return;
    reattempting.value = true;
    router.post(`/portal/student/${props.schoolId}/mcq/${regId.value}/reattempt`, {}, {
        onFinish: () => { reattempting.value = false; },
    });
}

const metaLine = computed(() => {
    const bits = [];
    if (props.exam.scheduled_at) {
        try {
            bits.push(new Date(props.exam.scheduled_at).toLocaleString());
        } catch {
            bits.push(props.exam.scheduled_at);
        }
    }
    if (props.exam.duration_minutes) {
        bits.push(`${props.exam.duration_minutes} min`);
    }
    if (props.exam.uses_exam_engine) {
        bits.push('Exam site');
    }
    return bits.join(' · ');
});

function lifecycleClass(status) {
    const tone = status?.tone ?? 'neutral';
    if (tone === 'success') return 'bg-emerald-50 text-emerald-800';
    if (tone === 'warning') return 'bg-amber-50 text-amber-800';
    if (tone === 'danger') return 'bg-red-50 text-red-800';
    if (tone === 'info') return 'bg-sky-50 text-sky-800';
    return 'bg-slate-100 text-slate-700';
}
</script>
