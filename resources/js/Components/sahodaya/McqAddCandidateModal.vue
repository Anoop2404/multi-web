<template>
    <Modal :show="show" title="Add Candidate (Admin Override)" size="lg" @close="closeModal">
        <div class="space-y-4">
            <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl text-xs text-amber-900 leading-relaxed">
                <p class="font-semibold flex items-center gap-1.5 mb-1">
                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Sahodaya Override Mode
                </p>
                This action bypasses registration deadlines and school window closure. Candidate is marked approved, school fees are automatically adjusted, portal credentials are provisioned, and hall ticket / seating can be assigned immediately.
            </div>

            <!-- Step 1: School Selection -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Select School <span class="text-rose-500">*</span></label>
                <SearchableSelect
                    v-model="selectedSchoolId"
                    :options="schools"
                    :all-option="false"
                    placeholder="Search member school..."
                    @update:model-value="onSchoolChange"
                />
            </div>

            <template v-if="selectedSchoolId">
                <!-- Candidate Type Toggle (Only if Exam Allows Teachers) -->
                <div v-if="allowsTeachers" class="flex items-center gap-2 pt-1 border-t border-slate-100">
                    <span class="text-xs font-semibold text-slate-600">Candidate Type:</span>
                    <div class="inline-flex rounded-lg bg-slate-100 p-0.5 text-xs">
                        <button
                            type="button"
                            class="px-3 py-1 rounded-md font-medium transition"
                            :class="candidateType === 'student' ? 'bg-white shadow text-slate-900 font-semibold' : 'text-slate-500 hover:text-slate-800'"
                            @click="switchCandidateType('student')"
                        >
                            Student
                        </button>
                        <button
                            type="button"
                            class="px-3 py-1 rounded-md font-medium transition"
                            :class="candidateType === 'teacher' ? 'bg-white shadow text-slate-900 font-semibold' : 'text-slate-500 hover:text-slate-800'"
                            @click="switchCandidateType('teacher')"
                        >
                            Teacher
                        </button>
                    </div>
                </div>

                <!-- Step 2: Search Candidate -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-semibold text-slate-700">
                            Find {{ candidateType === 'student' ? 'Student' : 'Teacher' }} <span class="text-rose-500">*</span>
                        </label>
                        <span v-if="loading" class="text-xs text-indigo-600 font-medium animate-pulse">Loading candidates...</span>
                    </div>

                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="field text-sm w-full pl-9"
                            :placeholder="candidateType === 'student' ? 'Search by name, admission no, or reg no...' : 'Search by name or phone...'"
                            @input="onSearchInput"
                        />
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    <!-- Selected candidate highlight card -->
                    <div v-if="selectedCandidate" class="p-3 bg-indigo-50 border border-indigo-200 rounded-xl flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900 text-sm">{{ selectedCandidate.name }}</span>
                                <span class="text-xs px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-semibold">
                                    {{ selectedCandidate.class_name }}
                                </span>
                                <span v-if="selectedCandidate.was_cancelled" class="text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-medium">
                                    Reactivating cancelled
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                <span v-if="selectedCandidate.admission_number">Adm No: {{ selectedCandidate.admission_number }} · </span>
                                <span v-if="selectedCandidate.reg_no">Reg No: {{ selectedCandidate.reg_no }}</span>
                            </p>
                            <p v-if="!selectedCandidate.is_eligible" class="text-xs text-amber-700 mt-1">
                                ⚠ Note: {{ selectedCandidate.ineligibility_reason || 'Candidate does not match default criteria' }} (Sahodaya override will apply)
                            </p>
                        </div>
                        <button type="button" class="text-xs text-indigo-700 hover:underline font-semibold" @click="selectedCandidate = null">
                            Change
                        </button>
                    </div>

                    <!-- Candidate Results List -->
                    <div v-else class="max-h-60 overflow-y-auto border border-slate-200 rounded-xl divide-y divide-slate-100 bg-white">
                        <div v-if="!candidates.length && !loading" class="p-4 text-center text-xs text-slate-400">
                            No candidates found for this school matching your search.
                        </div>

                        <div
                            v-for="c in candidates"
                            :key="c.id"
                            class="p-2.5 transition flex items-center justify-between gap-3"
                            :class="[
                                c.is_registered ? 'bg-slate-50 opacity-60 cursor-not-allowed' : 'hover:bg-slate-50 cursor-pointer',
                                selectedCandidate?.id === c.id ? 'bg-indigo-50/70 border-l-4 border-indigo-600' : ''
                            ]"
                            @click="!c.is_registered && selectCandidate(c)"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-slate-900 text-sm">{{ c.name }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium">
                                        {{ c.class_name }}
                                    </span>
                                    <span v-if="c.is_registered" class="text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">
                                        Already registered
                                    </span>
                                    <span v-else-if="c.was_cancelled" class="text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-medium">
                                        Was cancelled
                                    </span>
                                    <span v-else-if="!c.is_eligible" class="text-xs px-2 py-0.5 rounded bg-amber-50 text-amber-800 font-medium">
                                        {{ c.ineligibility_reason || 'Ineligible' }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    <span v-if="c.admission_number">Adm No: {{ c.admission_number }} </span>
                                    <span v-if="c.reg_no">· Reg No: {{ c.reg_no }}</span>
                                </p>
                            </div>
                            <button
                                v-if="!c.is_registered"
                                type="button"
                                class="btn-secondary !py-1 !px-2.5 text-xs shrink-0"
                                @click.stop="selectCandidate(c)"
                            >
                                Select
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Registration Options -->
                <div v-if="selectedCandidate" class="space-y-3 pt-3 border-t border-slate-100">
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input
                            v-model="issueHallTicket"
                            type="checkbox"
                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mt-0.5"
                        />
                        <div class="text-xs">
                            <span class="font-semibold text-slate-800">Issue roll number & hall ticket immediately</span>
                            <p class="text-slate-500 mt-0.5">
                                Assigns the next sequential roll number for class <strong>{{ selectedCandidate.class_name }}</strong>, generates an online exam passcode (if applicable), and allocates seating.
                            </p>
                        </div>
                    </label>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Override Note / Reason <span class="text-slate-400 font-normal">(optional, logged in audit trail)</span>
                        </label>
                        <input
                            v-model="reason"
                            type="text"
                            class="field text-sm w-full"
                            placeholder="e.g. Late addition requested by school principal"
                            maxlength="500"
                        />
                    </div>
                </div>

                <div v-if="errorMessage" class="text-xs text-rose-600 font-medium">
                    {{ errorMessage }}
                </div>
            </template>
        </div>

        <template #footer>
            <div class="flex items-center justify-between w-full">
                <button type="button" class="btn-ghost text-sm" @click="closeModal">Cancel</button>
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
                    <span>{{ submitting ? 'Adding candidate...' : 'Add Candidate' }}</span>
                </button>
            </div>
        </template>
    </Modal>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/ui/Modal.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    sahodayaId: { type: String, required: true },
    exam: { type: Object, required: true },
    schools: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const selectedSchoolId = ref(null);
const candidateType = ref('student');
const searchQuery = ref('');
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

watch(() => props.show, (newVal) => {
    if (newVal) {
        resetForm();
    }
});

function resetForm() {
    selectedSchoolId.value = null;
    candidateType.value = 'student';
    searchQuery.value = '';
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

function onSchoolChange() {
    selectedCandidate.value = null;
    searchQuery.value = '';
    if (selectedSchoolId.value) {
        fetchCandidates();
    } else {
        candidates.value = [];
    }
}

function switchCandidateType(type) {
    if (candidateType.value === type) return;
    candidateType.value = type;
    selectedCandidate.value = null;
    searchQuery.value = '';
    fetchCandidates();
}

function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        fetchCandidates();
    }, 300);
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
        ticketsAlreadyIssued.value = !!data.tickets_already_issued;
        allowsTeachers.value = !!data.allows_teachers;

        // Default issueHallTicket to true if exam already has tickets issued
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
