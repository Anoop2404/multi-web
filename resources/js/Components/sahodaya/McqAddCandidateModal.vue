<template>
    <Modal :show="show" title="Add Candidate (Admin Override)" size="xl" @close="closeModal">
        <div class="space-y-4">
            <!-- Alert Banner -->
            <div class="p-3 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200/80 rounded-xl text-xs text-amber-900 leading-relaxed">
                <div class="flex items-start gap-2.5">
                    <div class="w-5 h-5 rounded-full bg-amber-500/10 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <span class="font-semibold text-amber-950">Sahodaya Override Mode:</span>
                        Allows adding students after registration deadlines have closed or after hall tickets have been generated.
                        The candidate is marked <strong>Approved</strong>, school billing is synchronized, portal credentials are created, and hall ticket / seating can be assigned immediately.
                    </div>
                </div>
            </div>

            <!-- Workflow Stepper -->
            <div class="grid grid-cols-3 gap-2 p-1.5 bg-slate-100/80 rounded-xl text-xs">
                <div
                    class="flex items-center gap-2 px-3 py-1.5 rounded-lg font-medium transition"
                    :class="selectedSchoolId ? 'bg-white text-emerald-700 shadow-xs' : 'bg-white text-indigo-700 shadow-xs font-semibold'"
                >
                    <span
                        class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold"
                        :class="selectedSchoolId ? 'bg-emerald-100 text-emerald-800' : 'bg-indigo-100 text-indigo-800'"
                    >
                        <template v-if="selectedSchoolId">✓</template>
                        <template v-else>1</template>
                    </span>
                    <span class="truncate">1. Select School</span>
                </div>

                <div
                    class="flex items-center gap-2 px-3 py-1.5 rounded-lg font-medium transition"
                    :class="[
                        !selectedSchoolId ? 'text-slate-400 opacity-60' : '',
                        selectedCandidate ? 'bg-white text-emerald-700 shadow-xs' : (selectedSchoolId ? 'bg-white text-indigo-700 shadow-xs font-semibold' : '')
                    ]"
                >
                    <span
                        class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold"
                        :class="selectedCandidate ? 'bg-emerald-100 text-emerald-800' : (selectedSchoolId ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-600')"
                    >
                        <template v-if="selectedCandidate">✓</template>
                        <template v-else>2</template>
                    </span>
                    <span class="truncate">2. Pick Candidate</span>
                </div>

                <div
                    class="flex items-center gap-2 px-3 py-1.5 rounded-lg font-medium transition"
                    :class="selectedCandidate ? 'bg-white text-indigo-700 shadow-xs font-semibold' : 'text-slate-400 opacity-60'"
                >
                    <span
                        class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold"
                        :class="selectedCandidate ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-600'"
                    >
                        3
                    </span>
                    <span class="truncate">3. Options & Add</span>
                </div>
            </div>

            <!-- STEP 1: SCHOOL SELECTION -->
            <div v-if="!selectedSchoolId" class="space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                        Select Member School <span class="text-rose-500">*</span>
                    </label>
                    <span class="text-xs text-slate-400">
                        {{ filteredSchools.length }} of {{ normalizedSchools.length }} schools
                    </span>
                </div>

                <div class="relative">
                    <input
                        v-model="schoolSearch"
                        type="text"
                        class="field text-sm w-full pl-9 pr-8"
                        placeholder="Type school name or keyword..."
                        autofocus
                    />
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <button
                        v-if="schoolSearch"
                        type="button"
                        class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 p-0.5"
                        @click="schoolSearch = ''"
                    >
                        ✕
                    </button>
                </div>

                <!-- School List Container -->
                <div class="max-h-72 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100 bg-white shadow-xs">
                    <div v-if="filteredSchools.length === 0" class="p-8 text-center text-xs text-slate-400">
                        No member schools found matching "{{ schoolSearch }}".
                    </div>

                    <button
                        v-for="s in filteredSchools"
                        :key="s.id"
                        type="button"
                        class="w-full text-left p-3 hover:bg-indigo-50/50 transition flex items-center justify-between gap-3 group"
                        @click="chooseSchool(s.id)"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-slate-100 group-hover:bg-indigo-100 text-slate-600 group-hover:text-indigo-600 flex items-center justify-center shrink-0 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800 group-hover:text-indigo-900 truncate">
                                    {{ s.name }}
                                </p>
                            </div>
                        </div>
                        <span class="text-xs font-semibold text-indigo-600 group-hover:translate-x-0.5 transition shrink-0 flex items-center gap-1">
                            Select
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </span>
                    </button>
                </div>
            </div>

            <!-- STEP 2 & 3: SCHOOL IS SELECTED -->
            <template v-else>
                <!-- Selected School Summary Header -->
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Target School</div>
                            <div class="text-sm font-bold text-slate-900 truncate">{{ selectedSchoolName }}</div>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="btn-secondary !py-1 !px-2.5 text-xs shrink-0"
                        @click="changeSchool"
                    >
                        Change School
                    </button>
                </div>

                <!-- Candidate Search & Type Filters -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <!-- Candidate Type Switch (Only if Exam Allows Teachers) -->
                        <div v-if="allowsTeachers" class="inline-flex rounded-lg bg-slate-100 p-0.5 text-xs">
                            <button
                                type="button"
                                class="px-3 py-1 rounded-md font-medium transition"
                                :class="candidateType === 'student' ? 'bg-white shadow-xs text-slate-900 font-semibold' : 'text-slate-500 hover:text-slate-800'"
                                @click="switchCandidateType('student')"
                            >
                                Students
                            </button>
                            <button
                                type="button"
                                class="px-3 py-1 rounded-md font-medium transition"
                                :class="candidateType === 'teacher' ? 'bg-white shadow-xs text-slate-900 font-semibold' : 'text-slate-500 hover:text-slate-800'"
                                @click="switchCandidateType('teacher')"
                            >
                                Teachers
                            </button>
                        </div>

                        <!-- Class Filter (For Students) -->
                        <div v-if="candidateType === 'student' && availableClasses.length" class="flex items-center gap-1.5 ml-auto">
                            <span class="text-xs text-slate-500 font-medium">Class:</span>
                            <select
                                v-model="selectedClassId"
                                class="field !py-1 !text-xs !pr-7 max-w-[140px]"
                                @change="fetchCandidates"
                            >
                                <option :value="null">All Classes</option>
                                <option v-for="c in availableClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Search Input -->
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="field text-sm w-full pl-9 pr-8"
                            :placeholder="candidateType === 'student' ? 'Search student by name, admission no, reg no, or class...' : 'Search teacher by name or phone...'"
                            @input="onSearchInput"
                        />
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 p-0.5"
                            @click="clearCandidateSearch"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Loading Indicator -->
                    <div v-if="loading" class="py-8 flex flex-col items-center justify-center gap-2 text-indigo-600">
                        <svg class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span class="text-xs font-medium">Loading candidate roster...</span>
                    </div>

                    <!-- Candidate Selection List -->
                    <div v-else class="max-h-56 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100 bg-white shadow-xs">
                        <div v-if="!candidates.length" class="p-8 text-center text-xs text-slate-400">
                            No candidates found matching your search.
                        </div>

                        <div
                            v-for="c in candidates"
                            :key="c.id"
                            class="p-2.5 transition flex items-center justify-between gap-3"
                            :class="[
                                c.is_registered ? 'bg-slate-50/70 opacity-60 cursor-not-allowed' : 'hover:bg-slate-50 cursor-pointer',
                                selectedCandidate?.id === c.id ? 'bg-indigo-50/80 border-l-4 border-indigo-600' : ''
                            ]"
                            @click="!c.is_registered && selectCandidate(c)"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <div
                                    class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shrink-0"
                                    :class="selectedCandidate?.id === c.id ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ c.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-semibold text-slate-900 text-sm truncate">{{ c.name }}</span>
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-medium">
                                            {{ c.class_name }}
                                        </span>
                                        <span v-if="c.is_registered" class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-semibold">
                                            ✓ Registered
                                        </span>
                                        <span v-else-if="c.was_cancelled" class="text-[10px] px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 font-semibold">
                                            Reactivate cancelled
                                        </span>
                                        <span v-else-if="!c.is_eligible" class="text-[10px] px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200 font-medium">
                                            {{ c.ineligibility_reason || 'Ineligible' }} (Override)
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5 flex items-center gap-2 flex-wrap">
                                        <span v-if="c.admission_number">Adm: <strong class="text-slate-700">{{ c.admission_number }}</strong></span>
                                        <span v-if="c.reg_no">· Reg No: <strong class="text-slate-700">{{ c.reg_no }}</strong></span>
                                    </p>
                                </div>
                            </div>

                            <div class="shrink-0">
                                <span v-if="selectedCandidate?.id === c.id" class="text-xs font-bold text-indigo-600 flex items-center gap-1">
                                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Selected
                                </span>
                                <button
                                    v-else-if="!c.is_registered"
                                    type="button"
                                    class="btn-secondary !py-1 !px-2.5 text-xs"
                                    @click.stop="selectCandidate(c)"
                                >
                                    Select
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: SELECTED CANDIDATE & OPTIONS -->
                <div v-if="selectedCandidate" class="space-y-3 pt-3 border-t border-slate-100">
                    <!-- Selected Candidate Confirmation Card -->
                    <div class="p-3 bg-gradient-to-r from-indigo-50/90 to-blue-50/60 border border-indigo-200 rounded-xl flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shrink-0 shadow-xs">
                                {{ selectedCandidate.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-slate-900 text-sm">{{ selectedCandidate.name }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 font-semibold">
                                        {{ selectedCandidate.class_name }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    <span v-if="selectedCandidate.admission_number">Adm No: {{ selectedCandidate.admission_number }} · </span>
                                    <span v-if="selectedCandidate.reg_no">Reg No: {{ selectedCandidate.reg_no }} · </span>
                                    <span>{{ selectedSchoolName }}</span>
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="text-xs text-indigo-700 hover:text-indigo-900 font-semibold shrink-0"
                            @click="selectedCandidate = null"
                        >
                            Change Candidate
                        </button>
                    </div>

                    <!-- Hall Ticket Option Checkbox -->
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50/60 hover:bg-slate-50 cursor-pointer transition">
                        <input
                            v-model="issueHallTicket"
                            type="checkbox"
                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mt-0.5"
                        />
                        <div class="text-xs">
                            <span class="font-semibold text-slate-900">Issue roll number & hall ticket immediately</span>
                            <p class="text-slate-500 mt-0.5">
                                Automatically assigns the next sequential roll number for class <strong>{{ selectedCandidate.class_name }}</strong>, provisions exam credentials, and includes candidate in hall seating.
                            </p>
                        </div>
                    </label>

                    <!-- Override Reason / Note Input -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Override Note / Reason <span class="text-slate-400 font-normal">(optional — stored in audit trail)</span>
                        </label>
                        <input
                            v-model="reason"
                            type="text"
                            class="field text-sm w-full"
                            placeholder="e.g. Late addition approved by Sahodaya convenor"
                            maxlength="500"
                        />
                    </div>
                </div>

                <!-- Error Message Banner -->
                <div v-if="errorMessage" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 font-medium flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ errorMessage }}</span>
                </div>
            </template>
        </div>

        <template #footer>
            <div class="flex items-center justify-between w-full">
                <button type="button" class="btn-ghost text-sm" @click="closeModal">Cancel</button>

                <div class="flex items-center gap-2">
                    <span v-if="!selectedCandidate && selectedSchoolId" class="text-xs text-slate-400 hidden sm:inline">
                        Select a candidate above to proceed
                    </span>
                    <button
                        type="button"
                        class="btn-primary text-sm flex items-center gap-1.5"
                        :disabled="!selectedCandidate || submitting"
                        @click="submit"
                    >
                        <svg v-if="submitting" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ submitting ? 'Adding candidate...' : 'Add Candidate (Override)' }}</span>
                    </button>
                </div>
            </div>
        </template>
    </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    sahodayaId: { type: String, required: true },
    exam: { type: Object, required: true },
    schools: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const selectedSchoolId = ref(null);
const schoolSearch = ref('');
const candidateType = ref('student');
const searchQuery = ref('');
const selectedClassId = ref(null);
const availableClasses = ref([]);
const candidates = ref([]);
const loading = ref(false);
const selectedCandidate = ref(null);
const issueHallTicket = ref(true);
const ticketsAlreadyIssued = ref(false);
const allowsTeachers = ref(false);
const reason = ref('');
const submitting = ref(false);
const errorMessage = ref('');

let searchTimer = null;

// Normalize schools array
const normalizedSchools = computed(() => {
    return (props.schools || []).map(s => {
        if (typeof s === 'string') return { id: s, name: s };
        return {
            id: s.value ?? s.id,
            name: s.label ?? s.name ?? '',
        };
    }).filter(s => !!s.id && !!s.name);
});

const filteredSchools = computed(() => {
    const q = (schoolSearch.value || '').trim().toLowerCase();
    if (!q) return normalizedSchools.value;
    return normalizedSchools.value.filter(s => s.name.toLowerCase().includes(q));
});

const selectedSchoolName = computed(() => {
    const found = normalizedSchools.value.find(s => s.id === selectedSchoolId.value);
    return found?.name || 'Selected School';
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        resetForm();
    }
});

function resetForm() {
    selectedSchoolId.value = null;
    schoolSearch.value = '';
    candidateType.value = 'student';
    searchQuery.value = '';
    selectedClassId.value = null;
    availableClasses.value = [];
    candidates.value = [];
    selectedCandidate.value = null;
    issueHallTicket.value = true;
    ticketsAlreadyIssued.value = false;
    allowsTeachers.value = false;
    reason.value = '';
    errorMessage.value = '';
    submitting.value = false;
}

function closeModal() {
    emit('close');
}

function chooseSchool(schoolId) {
    selectedSchoolId.value = schoolId;
    selectedCandidate.value = null;
    searchQuery.value = '';
    selectedClassId.value = null;
    fetchCandidates();
}

function changeSchool() {
    selectedSchoolId.value = null;
    selectedCandidate.value = null;
    searchQuery.value = '';
    selectedClassId.value = null;
    candidates.value = [];
    availableClasses.value = [];
}

function switchCandidateType(type) {
    if (candidateType.value === type) return;
    candidateType.value = type;
    selectedCandidate.value = null;
    searchQuery.value = '';
    selectedClassId.value = null;
    fetchCandidates();
}

function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        fetchCandidates();
    }, 250);
}

function clearCandidateSearch() {
    searchQuery.value = '';
    fetchCandidates();
}

async function fetchCandidates() {
    if (!selectedSchoolId.value) return;
    loading.value = true;
    errorMessage.value = '';

    try {
        const params = new URLSearchParams({
            school_id: selectedSchoolId.value,
            type: candidateType.value,
        });
        if (searchQuery.value.trim()) {
            params.append('search', searchQuery.value.trim());
        }
        if (selectedClassId.value) {
            params.append('class_id', selectedClassId.value);
        }

        const res = await fetch(`/sahodaya-admin/${props.sahodayaId}/mcq-exams/${props.exam.id}/eligible-candidates?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!res.ok) {
            throw new Error(`Failed to load candidates (${res.status})`);
        }

        const data = await res.json();
        candidates.value = data.candidates || [];
        availableClasses.value = data.classes || [];
        ticketsAlreadyIssued.value = !!data.tickets_already_issued;
        allowsTeachers.value = !!data.allows_teachers;

        if (data.tickets_already_issued) {
            issueHallTicket.value = true;
        }
    } catch (e) {
        errorMessage.value = e.message || 'Error loading candidates.';
    } finally {
        loading.value = false;
    }
}

function selectCandidate(candidate) {
    selectedCandidate.value = candidate;
}

function submit() {
    if (!selectedSchoolId.value || !selectedCandidate.value) return;

    submitting.value = true;
    errorMessage.value = '';

    router.post(`/sahodaya-admin/${props.sahodayaId}/mcq-exams/${props.exam.id}/registrations`, {
        school_id: selectedSchoolId.value,
        candidate_type: candidateType.value,
        student_id: candidateType.value === 'student' ? selectedCandidate.value.id : null,
        teacher_id: candidateType.value === 'teacher' ? selectedCandidate.value.id : null,
        issue_hall_ticket: issueHallTicket.value,
        reason: reason.value ? reason.value.trim() : null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            submitting.value = false;
            emit('saved');
            emit('close');
        },
        onError: (errors) => {
            submitting.value = false;
            errorMessage.value = Object.values(errors)[0] || 'Failed to add candidate.';
        },
    });
}
</script>
