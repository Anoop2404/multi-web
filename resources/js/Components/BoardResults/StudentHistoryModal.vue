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
            <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-end print:p-0 print:bg-white print:static print:block">
                <!-- Modal Backdrop Click -->
                <div class="fixed inset-0 print:hidden" @click="$emit('close')"></div>

                <!-- Slideover Panel -->
                <div class="relative w-full max-w-3xl min-h-screen bg-white shadow-2xl border-l border-slate-200 flex flex-col transform transition-all print:shadow-none print:border-0 print:w-full print:max-w-none print:min-h-0">
                    <!-- HEADER -->
                    <div class="bg-slate-900 text-white p-6 relative print:bg-none print:text-slate-900 print:p-0 print:border-b print:border-slate-300">
                        <button
                            type="button"
                            class="absolute top-5 right-5 text-slate-400 hover:text-white text-lg w-8 h-8 rounded-full flex items-center justify-center hover:bg-white/10 transition print:hidden"
                            @click="$emit('close')"
                        >
                            ✕
                        </button>

                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 print:bg-indigo-100 print:text-indigo-800">
                                📜 Student Result History
                            </span>
                            <span class="text-xs text-slate-400">Board Exam Trajectory & Achievements</span>
                        </div>

                        <h3 class="text-xl font-bold text-white print:text-slate-900">
                            {{ activeMatch ? activeMatch.student_name : 'Student History Lookup' }}
                        </h3>

                        <!-- Search Bar inside drawer header -->
                        <div class="mt-4 print:hidden">
                            <form @submit.prevent="executeSearch" class="relative">
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full bg-slate-800 text-white placeholder-slate-400 text-xs px-4 py-2.5 pr-20 rounded-xl border border-slate-700 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                    placeholder="Search by Roll No, Admission No, or Student Name..."
                                >
                                <button
                                    type="submit"
                                    class="absolute right-1.5 top-1.5 bottom-1.5 px-3 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg transition"
                                    :disabled="loading"
                                >
                                    {{ loading ? 'Searching...' : 'Search' }}
                                </button>
                            </form>
                        </div>

                        <!-- Active Student Header Pills -->
                        <div v-if="activeMatch" class="mt-4 pt-3 border-t border-slate-800/80 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-300 print:text-slate-700">
                            <div v-if="activeMatch.roll_no" class="flex items-center gap-1">
                                <span class="text-slate-400">CBSE Roll:</span>
                                <span class="font-mono font-bold text-white print:text-slate-900">{{ activeMatch.roll_no }}</span>
                            </div>
                            <div v-if="activeMatch.admission_no" class="flex items-center gap-1">
                                <span class="text-slate-400">Adm No:</span>
                                <span class="font-mono text-white print:text-slate-900">{{ activeMatch.admission_no }}</span>
                            </div>
                            <div v-if="activeMatch.school_name" class="flex items-center gap-1">
                                <span class="text-slate-400">School:</span>
                                <span class="font-semibold text-white print:text-slate-900">{{ activeMatch.school_name }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- BODY: RESULTS TIMELINE -->
                    <div class="flex-1 p-6 space-y-6 overflow-y-auto bg-slate-50/50 print:bg-white print:p-4">
                        <!-- Student Matches Selector if multiple -->
                        <div v-if="searchResults.length > 1" class="bg-indigo-50/80 border border-indigo-100 rounded-xl p-3 print:hidden">
                            <p class="text-[11px] font-bold uppercase text-indigo-700 mb-2">
                                Multiple Matching Students Found ({{ searchResults.length }})
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="(m, idx) in searchResults"
                                    :key="idx"
                                    type="button"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                                    :class="activeMatchIndex === idx ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-indigo-900 border border-indigo-200 hover:bg-indigo-100/50'"
                                    @click="activeMatchIndex = idx"
                                >
                                    {{ m.student_name }} ({{ m.roll_no || m.school_name }})
                                </button>
                            </div>
                        </div>

                        <!-- Timeline Records -->
                        <div v-if="activeMatch && activeMatch.history.length" class="space-y-6 relative before:absolute before:inset-0 before:left-3.5 before:w-0.5 before:bg-slate-200 print:before:hidden">
                            <div
                                v-for="(rec, idx) in activeMatch.history"
                                :key="idx"
                                class="relative pl-8 print:pl-0 space-y-3"
                            >
                                <!-- Timeline Bullet -->
                                <div class="absolute left-0 top-1 w-7 h-7 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center shadow-xs border-2 border-white print:hidden">
                                    {{ rec.class }}
                                </div>

                                <!-- Exam Card -->
                                <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs space-y-4">
                                    <!-- Card Header -->
                                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h4 class="font-extrabold text-slate-900 text-base">
                                                    Class {{ rec.class }} {{ rec.stream ? `— ${rec.stream}` : '' }}
                                                </h4>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                                    AY {{ rec.academic_year }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-500 mt-0.5 flex flex-wrap items-center gap-1.5">
                                                <span>Examination: {{ rec.examination_type || `Class ${rec.class} Board Exam` }}</span>
                                                <span class="text-slate-300">•</span>
                                                <span class="font-medium text-slate-700">🏫 {{ rec.school_name || activeMatch.school_name }}</span>
                                                <span v-if="rec.school_name && activeMatch.school_name && rec.school_name !== activeMatch.school_name" class="px-1.5 py-0.5 rounded text-[10px] bg-amber-100 text-amber-800 font-bold border border-amber-200">
                                                    Other School
                                                </span>
                                            </p>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span v-for="type in rec.entry_types" :key="type" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                                  :class="type === 'full_a1' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-indigo-100 text-indigo-800 border border-indigo-200'">
                                                {{ type === 'full_a1' ? 'Full A1 Achiever' : (type === 'overall' ? 'Overall Topper' : 'Subject Topper') }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Key Metrics -->
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center bg-slate-50/70 p-3 rounded-xl border border-slate-100">
                                        <div>
                                            <p class="text-[10px] font-bold uppercase text-slate-400">Total Marks</p>
                                            <p class="text-sm font-extrabold text-slate-900 font-mono mt-0.5">
                                                {{ rec.marks_obtained ? `${rec.marks_obtained}/${rec.total_marks || 500}` : '—' }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold uppercase text-slate-400">Percentage</p>
                                            <p class="text-sm font-extrabold text-indigo-600 font-mono mt-0.5">
                                                {{ rec.percentage !== null ? `${rec.percentage}%` : '—' }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold uppercase text-slate-400">Overall Rank</p>
                                            <p class="text-sm font-extrabold text-slate-900 mt-0.5">
                                                {{ rec.rank ? `#${rec.rank}` : '—' }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold uppercase text-slate-400">Status</p>
                                            <p class="text-xs font-bold capitalize mt-0.5"
                                               :class="rec.status === 'published' || rec.status === 'approved' ? 'text-emerald-600' : 'text-amber-600'">
                                                {{ rec.status || 'Verified' }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Subject Marks Breakdown -->
                                    <div v-if="rec.subject_marks && rec.subject_marks.length" class="space-y-2">
                                        <p class="text-[11px] font-bold uppercase text-slate-500">Subject Marks Breakdown</p>
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-xs text-left border border-slate-100 rounded-lg overflow-hidden">
                                                <thead>
                                                    <tr class="bg-slate-100/70 text-slate-600 font-semibold uppercase text-[10px]">
                                                        <th class="py-2 px-3">Code</th>
                                                        <th class="py-2 px-3">Subject Name</th>
                                                        <th class="py-2 px-3 text-right">Marks</th>
                                                        <th class="py-2 px-3 text-center">Grade</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    <tr v-for="(sub, sIdx) in rec.subject_marks" :key="sIdx" class="hover:bg-slate-50">
                                                        <td class="py-1.5 px-3 font-mono text-slate-500 font-semibold">{{ sub.subject_code || '—' }}</td>
                                                        <td class="py-1.5 px-3 font-medium text-slate-900">{{ sub.subject_label }}</td>
                                                        <td class="py-1.5 px-3 text-right font-mono font-bold text-indigo-700">{{ sub.marks }}</td>
                                                        <td class="py-1.5 px-3 text-center">
                                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                                                  :class="sub.grade === 'A1' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'">
                                                                {{ sub.grade }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty Search / No Results -->
                        <div v-else-if="!loading && searched && (!searchResults.length || !activeMatch)" class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200">
                            <span class="text-3xl block mb-2">🔍</span>
                            <h4 class="font-bold text-slate-800 text-sm">No Student History Records Found</h4>
                            <p class="text-xs text-slate-500 mt-1">No board exam records match "{{ searchQuery }}".</p>
                        </div>

                        <div v-else-if="loading" class="text-center py-12">
                            <div class="inline-block animate-spin text-2xl text-indigo-600">⏳</div>
                            <p class="text-xs text-slate-500 mt-2 font-medium">Fetching student history records...</p>
                        </div>
                    </div>

                    <!-- FOOTER ACTIONS -->
                    <div class="p-4 bg-white border-t border-slate-200 flex items-center justify-between gap-3 print:hidden">
                        <button
                            v-if="activeMatch"
                            type="button"
                            class="btn-secondary text-xs font-semibold flex items-center gap-1.5"
                            @click="printHistory"
                        >
                            <span>🖨</span> Print Student History
                        </button>
                        <div v-else></div>

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
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    initialStudent: Object,
    sahodayaId: String,
    schoolId: String,
});

defineEmits(['close']);

const searchQuery = ref('');
const searchResults = ref([]);
const activeMatchIndex = ref(0);
const activeMatch = ref(null);
const loading = ref(false);
const searched = ref(false);

watch(() => props.initialStudent, (newVal) => {
    if (newVal) {
        const query = newVal.roll_no || newVal.admission_no || newVal.student_name || '';
        if (query) {
            searchQuery.value = query;
            executeSearch();
        }
    }
}, { immediate: true });

watch(activeMatchIndex, (newIdx) => {
    if (searchResults.value && searchResults.value[newIdx]) {
        activeMatch.value = searchResults.value[newIdx];
    }
});

async function executeSearch() {
    const q = searchQuery.value.trim();
    if (q.length < 2) return;

    loading.value = true;
    searched.value = true;

    try {
        let endpoint = '';
        if (props.schoolId) {
            endpoint = `/school-admin/${props.schoolId}/board-results/student-history?query=${encodeURIComponent(q)}`;
        } else if (props.sahodayaId) {
            endpoint = `/sahodaya-admin/${props.sahodayaId}/board-results/student-history?query=${encodeURIComponent(q)}`;
        } else {
            endpoint = `/board-results/student-history?query=${encodeURIComponent(q)}`;
        }

        const res = await fetch(endpoint, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        searchResults.value = data.matches || [];
        activeMatchIndex.value = 0;
        activeMatch.value = searchResults.value[0] || null;
    } catch (e) {
        console.error('Failed to load student history', e);
        searchResults.value = [];
        activeMatch.value = null;
    } finally {
        loading.value = false;
    }
}

function printHistory() {
    window.print();
}
</script>
