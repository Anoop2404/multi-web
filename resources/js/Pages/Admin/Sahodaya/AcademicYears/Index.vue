<template>
    <SahodayaAdminLayout title="Academic Years" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <PageHeader
            title="Academic years"
            eyebrow="Membership"
            description="Manage academic and financial year lifecycle. The active year scopes registrations, fees, and reports."
        />

        <div class="max-w-4xl space-y-6">

            <!-- Info banner -->
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-900">
                <p class="font-semibold mb-1">About Academic Years</p>
                <p class="text-xs text-blue-700/80">
                    The active academic year controls which year all new registrations, event entries,
                    and exam registrations are scoped to. Only one year can be active at a time.
                    Fest events can be assigned a specific academic year — schools may only register students enrolled in that year.
                </p>
            </div>

            <!-- Current status strip -->
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="rounded-xl border p-4" :class="currentAy ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200'">
                    <p class="text-xs font-bold uppercase tracking-widest mb-1" :class="currentAy ? 'text-green-700' : 'text-amber-700'">Academic Year</p>
                    <p class="text-2xl font-extrabold" :class="currentAy ? 'text-green-900' : 'text-amber-800'">
                        {{ currentAy?.label ?? 'Not set' }}
                    </p>
                    <p class="text-xs mt-1" :class="currentAy ? 'text-green-700' : 'text-amber-600'">
                        <template v-if="currentAy">Active · {{ formatDate(currentAy.start_date) }} – {{ formatDate(currentAy.end_date) }}</template>
                        <template v-else>No academic year is currently active</template>
                    </p>
                </div>
                <div class="rounded-xl border p-4" :class="currentFy ? 'bg-indigo-50 border-indigo-200' : 'bg-gray-50 border-gray-200'">
                    <p class="text-xs font-bold uppercase tracking-widest mb-1" :class="currentFy ? 'text-indigo-700' : 'text-gray-500'">Financial Year</p>
                    <p class="text-2xl font-extrabold" :class="currentFy ? 'text-indigo-900' : 'text-gray-500'">
                        {{ currentFy?.label ?? 'Not set' }}
                    </p>
                    <p class="text-xs mt-1" :class="currentFy ? 'text-indigo-700' : 'text-gray-400'">
                        <template v-if="currentFy">Current · April–March</template>
                        <template v-else>No financial year set as current</template>
                    </p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-200 gap-1">
                <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
                        :class="['px-4 py-2.5 text-sm font-semibold border-b-2 transition',
                                 activeTab === t.key ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700']">
                    {{ t.label }}
                </button>
            </div>

            <!-- Tab: Academic Years -->
            <div v-show="activeTab === 'academic'">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900">Academic Years</h3>
                    <button @click="showAyForm = !showAyForm"
                            class="btn-primary">
                        + Add Year
                    </button>
                </div>

                <!-- Add form -->
                <form v-if="showAyForm" @submit.prevent="createAcademicYear"
                      class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-xl space-y-3">
                    <p class="text-sm font-semibold text-gray-700">New Academic Year</p>
                    <div class="grid sm:grid-cols-3 gap-3">
                        <div>
                            <label class="label-xs">Label <span class="text-red-500">*</span></label>
                            <input v-model="ayForm.label" class="field font-mono" placeholder="2026-27"
                                   pattern="\d{4}-\d{2}" required>
                        </div>
                        <div>
                            <label class="label-xs">Start Date <span class="text-red-500">*</span></label>
                            <input v-model="ayForm.start_date" type="date" class="field" required>
                        </div>
                        <div>
                            <label class="label-xs">End Date <span class="text-red-500">*</span></label>
                            <input v-model="ayForm.end_date" type="date" class="field" required>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <p class="text-xs text-gray-500 self-center mr-2">Quick fill:</p>
                        <button v-for="s in suggestedYears" :key="s.label" type="button"
                                @click="fillAySuggestion(s)"
                                class="px-2.5 py-1 text-xs rounded-full border border-indigo-200 text-indigo-700 hover:bg-indigo-50 font-mono">
                            {{ s.label }}
                        </button>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" :disabled="ayRouter.processing"
                                class="btn-primary text-sm px-4 py-2">Create</button>
                        <button type="button" @click="showAyForm = false"
                                class="btn-ghost text-sm px-4 py-2">Cancel</button>
                    </div>
                </form>

                <!-- Year list -->
                <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 overflow-hidden">
                    <div v-if="!academicYears.length" class="py-8 text-center text-sm text-gray-400">
                        No academic years created yet.
                    </div>
                    <div v-for="ay in academicYears" :key="ay.id"
                         class="flex items-center gap-4 px-4 py-3 bg-white hover:bg-gray-50">
                        <div class="flex-1 min-w-0">
                            <span class="font-mono font-semibold text-gray-900">{{ ay.label }}</span>
                            <span class="ml-2 text-xs text-gray-400">{{ formatDate(ay.start_date) }} – {{ formatDate(ay.end_date) }}</span>
                        </div>
                        <span :class="statusBadge(ay.status)" class="text-xs font-semibold px-2.5 py-0.5 rounded-full">
                            {{ ay.status }}
                        </span>
                        <div class="flex gap-2 shrink-0">
                            <button v-if="ay.status === 'upcoming'"
                                    @click="activateYear(ay)"
                                    class="btn-primary text-xs px-3 py-1.5 rounded-lg font-medium transition">
                                Activate
                            </button>
                            <button v-if="ay.status === 'active'"
                                    @click="closeYear(ay)"
                                    class="text-xs px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium transition">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Financial Years -->
            <div v-show="activeTab === 'financial'">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900">Financial Years</h3>
                    <button @click="showFyForm = !showFyForm"
                            class="btn-primary">
                        + Add Year
                    </button>
                </div>

                <!-- Add form -->
                <form v-if="showFyForm" @submit.prevent="createFinancialYear"
                      class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-xl space-y-3">
                    <p class="text-sm font-semibold text-gray-700">New Financial Year (April–March)</p>
                    <div class="grid sm:grid-cols-3 gap-3">
                        <div>
                            <label class="label-xs">Label <span class="text-red-500">*</span></label>
                            <input v-model="fyForm.label" class="field font-mono" placeholder="2026-27"
                                   pattern="\d{4}-\d{2}" required>
                        </div>
                        <div>
                            <label class="label-xs">Start Date</label>
                            <input v-model="fyForm.start_date" type="date" class="field" required>
                        </div>
                        <div>
                            <label class="label-xs">End Date</label>
                            <input v-model="fyForm.end_date" type="date" class="field" required>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="fyForm.set_current" type="checkbox" class="rounded">
                        Set as current financial year
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" :disabled="fyRouter.processing"
                                class="btn-primary text-sm px-4 py-2">Create</button>
                        <button type="button" @click="showFyForm = false"
                                class="btn-ghost text-sm px-4 py-2">Cancel</button>
                    </div>
                </form>

                <!-- FY list -->
                <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 overflow-hidden">
                    <div v-if="!financialYears.length" class="py-8 text-center text-sm text-gray-400">
                        No financial years created yet.
                    </div>
                    <div v-for="fy in financialYears" :key="fy.id"
                         class="flex items-center gap-4 px-4 py-3 bg-white hover:bg-gray-50">
                        <div class="flex-1 min-w-0">
                            <span class="font-mono font-semibold text-gray-900">{{ fy.label }}</span>
                            <span class="ml-2 text-xs text-gray-400">{{ formatDate(fy.start_date) }} – {{ formatDate(fy.end_date) }}</span>
                        </div>
                        <span v-if="fy.is_current"
                              class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700">
                            Current
                        </span>
                        <button v-else @click="setCurrentFy(fy)"
                                class="text-xs px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-indigo-100 hover:text-indigo-700 font-medium transition">
                            Set Current
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';

const props = defineProps({
    sahodaya:           Object,
    publicUrl:          String,
    pendingSchoolsCount:   Number,
    pendingSubmissionsCount: Number,
    pendingPaymentsCount:  Number,
    academicYears:      Array,
    financialYears:     Array,
    currentAy:          Object,
    currentFy:          Object,
    suggestedYears:     Array,
});

const activeTab = ref('academic');
const tabs = [
    { key: 'academic',  label: 'Academic Years' },
    { key: 'financial', label: 'Financial Years' },
];

// ─── Academic Year form ────────────────────────────────────────────────────
const showAyForm = ref(false);
const ayForm = reactive({ label: '', start_date: '', end_date: '' });
const ayRouter = reactive({ processing: false });

function fillAySuggestion(s) {
    ayForm.label      = s.label;
    ayForm.start_date = s.start_date;
    ayForm.end_date   = s.end_date;
}

function createAcademicYear() {
    ayRouter.processing = true;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/academic-years`, ayForm, {
        onSuccess: () => { showAyForm.value = false; Object.assign(ayForm, { label: '', start_date: '', end_date: '' }); },
        onFinish:  () => { ayRouter.processing = false; },
    });
}

function activateYear(ay) {
    if (!confirm(`Activate academic year ${ay.label}? The currently active year will be closed.`)) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/academic-years/${ay.id}/activate`);
}

function closeYear(ay) {
    if (!confirm(`Close academic year ${ay.label}? This cannot be undone.`)) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/academic-years/${ay.id}/close`);
}

// ─── Financial Year form ───────────────────────────────────────────────────
const showFyForm = ref(false);
const fyForm = reactive({ label: '', start_date: '', end_date: '', set_current: false });
const fyRouter = reactive({ processing: false });

function createFinancialYear() {
    fyRouter.processing = true;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/academic-years/financial-years`, fyForm, {
        onSuccess: () => { showFyForm.value = false; Object.assign(fyForm, { label: '', start_date: '', end_date: '', set_current: false }); },
        onFinish:  () => { fyRouter.processing = false; },
    });
}

function setCurrentFy(fy) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/academic-years/financial-years/${fy.id}/current`);
}

// ─── Helpers ───────────────────────────────────────────────────────────────
function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function statusBadge(status) {
    const map = {
        upcoming: 'bg-amber-100 text-amber-700',
        active:   'bg-green-100 text-green-700',
        closed:   'bg-gray-100 text-gray-500',
    };
    return map[status] ?? 'bg-gray-100 text-gray-500';
}
</script>
