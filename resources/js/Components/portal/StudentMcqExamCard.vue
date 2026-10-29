<template>
    <article class="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-sm transition hover:shadow-md hover:border-[#0f3d7a]/30">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-extrabold text-slate-900 text-sm sm:text-base leading-snug">
                        {{ exam.exam?.title ?? exam.title }}
                    </h3>
                    <span
                        v-if="exam.lifecycle_status?.label"
                        class="text-[10px] font-extrabold uppercase tracking-wide px-2.5 py-0.5 rounded-full"
                        :class="lifecycleClass(exam.lifecycle_status)"
                    >{{ exam.lifecycle_status.label }}</span>
                    <span
                        v-if="exam.delivery_mode"
                        class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full"
                        :class="exam.delivery_mode === 'online' ? 'bg-sky-50 text-sky-800 border border-sky-200' : 'bg-slate-100 text-slate-600'"
                    >{{ exam.delivery_mode }}</span>
                </div>

                <p v-if="exam.product_label" class="text-xs text-slate-500 font-medium">{{ exam.product_label }}</p>

                <div v-if="exam.venue_keycode" class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-700">
                    <span>Venue keycode:</span>
                    <span class="font-mono font-bold text-slate-900 tracking-wide">{{ exam.venue_keycode }}</span>
                </div>

                <p v-if="metaLine" class="text-xs text-slate-500 flex items-center gap-1.5 flex-wrap">
                    <span>🕒 {{ metaLine }}</span>
                </p>

                <!-- Published score / Result badge -->
                <div v-if="exam.show_results && exam.mark" class="pt-1 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-blue-50 border border-blue-200 text-xs font-bold text-[#0f3d7a]">
                        Score: {{ exam.mark.score }}
                    </span>
                    <span v-if="exam.mark.grade" class="inline-flex items-center px-2 py-1 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-bold text-emerald-800">
                        Grade {{ exam.mark.grade }}
                    </span>
                    <span v-if="exam.mark.rank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-amber-50 border border-amber-200 text-xs font-bold text-amber-800">
                        🏆 Rank #{{ exam.mark.rank }}
                    </span>
                </div>
                <p v-else-if="exam.result_state === 'held'" class="text-xs text-amber-700 font-medium">Results not released yet</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0 pt-2 sm:pt-0">
                <a
                    v-if="exam.can_take_online"
                    :href="examUrl"
                    class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-[#041525] px-4 py-2.5 text-xs font-extrabold shadow-sm transition"
                >
                    <span>Take exam</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
                <button
                    v-else-if="exam.can_reattempt"
                    type="button"
                    class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#0f3d7a] hover:bg-[#1e5aa8] text-white px-3.5 py-2 text-xs font-bold shadow-sm transition"
                    :disabled="reattempting"
                    @click="startReattempt"
                >{{ reattempting ? 'Starting…' : `Reattempt (${exam.attempt_number || 1}/${exam.max_attempts || 1})` }}</button>
                <a
                    v-else-if="exam.show_results"
                    :href="examUrl"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-800 hover:bg-slate-50 transition"
                >View result</a>
                <a
                    v-if="exam.show_hall_ticket"
                    :href="hallTicketUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center justify-center gap-1 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                >
                    <span>Hall ticket</span>
                    <svg class="w-3 h-3 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </a>
                <a
                    v-if="exam.certificate_url"
                    :href="exam.certificate_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center justify-center gap-1 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800 hover:bg-emerald-100 transition"
                >
                    <span>Certificate</span>
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </a>
                <a
                    v-if="exam.invoice_url"
                    :href="exam.invoice_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
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
