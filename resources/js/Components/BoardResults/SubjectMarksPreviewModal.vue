<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 print:p-0 print:bg-white print:static print:block">
                <!-- Modal Backdrop Click -->
                <div class="fixed inset-0 print:hidden" @click="$emit('close')"></div>

                <!-- Modal Panel -->
                <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all print:shadow-none print:border-0 print:w-full print:max-w-none">
                    <!-- HEADER -->
                    <div class="bg-gradient-to-r from-indigo-900 via-slate-900 to-indigo-950 p-6 text-white relative print:bg-none print:text-slate-900 print:p-0 print:border-b print:border-slate-300">
                        <button
                            type="button"
                            class="absolute top-4 right-4 text-slate-400 hover:text-white text-lg w-8 h-8 rounded-full flex items-center justify-center hover:bg-white/10 transition print:hidden"
                            @click="$emit('close')"
                        >
                            ✕
                        </button>

                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 print:bg-emerald-100 print:text-emerald-800">
                                Full A1 Achiever
                            </span>
                            <span class="text-xs text-indigo-200 print:text-slate-600 font-medium">
                                Class {{ student?.class }} {{ student?.stream ? `• ${student.stream}` : '' }} (AY {{ student?.academic_year }})
                            </span>
                        </div>

                        <h3 class="text-xl font-bold text-white print:text-slate-900 tracking-tight">
                            {{ student?.student_name }}
                        </h3>

                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-indigo-100/80 print:text-slate-600">
                            <div v-if="student?.roll_no" class="flex items-center gap-1">
                                <span class="text-indigo-300 print:text-slate-400 font-medium">CBSE Roll:</span>
                                <span class="font-mono font-bold text-white print:text-slate-900">{{ student.roll_no }}</span>
                            </div>
                            <div v-if="student?.admission_no" class="flex items-center gap-1">
                                <span class="text-indigo-300 print:text-slate-400 font-medium">Adm No:</span>
                                <span class="font-mono text-white print:text-slate-900">{{ student.admission_no }}</span>
                            </div>
                            <div v-if="student?.school_name" class="flex items-center gap-1">
                                <span class="text-indigo-300 print:text-slate-400 font-medium">School:</span>
                                <span class="font-semibold text-white print:text-slate-900">{{ student.school_name }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- BODY: SUBJECT MARKS GRID -->
                    <div class="p-6 space-y-5 bg-slate-50/50 print:bg-white print:p-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                Subject-Wise Marks Breakdown ({{ studentMarks.length }} subjects)
                            </h4>
                            <span v-if="student?.lowest_mark" class="text-xs text-slate-500 font-medium">
                                Lowest Mark: <strong class="text-emerald-600 font-bold">{{ student.lowest_mark }}</strong>
                            </span>
                        </div>

                        <!-- Subjects Grid -->
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div
                                v-for="(sub, idx) in studentMarks"
                                :key="idx"
                                class="bg-white rounded-xl p-4 border border-slate-200/80 shadow-2xs flex items-center justify-between gap-3 hover:border-indigo-200 transition"
                            >
                                <div class="space-y-0.5 min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span v-if="sub.subject_code" class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                            {{ sub.subject_code }}
                                        </span>
                                        <span class="text-xs font-bold text-slate-900 truncate">
                                            {{ sub.subject_label }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-400">Standard Board Evaluation</p>
                                </div>

                                <div class="text-right shrink-0">
                                    <div class="text-base font-extrabold text-indigo-700 font-mono">
                                        {{ sub.marks }} <span class="text-xs text-slate-400 font-normal">/ 100</span>
                                    </div>
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Grade {{ sub.grade || 'A1' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-if="!studentMarks.length" class="text-center py-8 bg-white rounded-xl border border-dashed border-slate-200">
                            <p class="text-xs text-slate-400">No subject-level marks details recorded for this student.</p>
                        </div>
                    </div>

                    <!-- FOOTER ACTIONS -->
                    <div class="p-4 bg-white border-t border-slate-200 flex flex-wrap items-center justify-between gap-3 print:hidden">
                        <button
                            type="button"
                            class="btn-secondary text-xs font-semibold flex items-center gap-1.5"
                            @click="$emit('viewHistory', student)"
                        >
                            <span>📜</span> View Full Student History
                        </button>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="btn-secondary text-xs font-semibold flex items-center gap-1.5"
                                @click="printStudentReport"
                            >
                                <span>🖨</span> Print Subject Marks Sheet
                            </button>

                            <button
                                type="button"
                                class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition"
                                @click="$emit('close')"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: Boolean,
    student: Object,
});

defineEmits(['close', 'viewHistory']);

const studentMarks = computed(() => {
    return props.student?.subject_marks || [];
});

function printStudentReport() {
    window.print();
}
</script>
