<template>
    <SahodayaAdminLayout
        title="School Codes"
        :sahodaya="sahodaya"
        :publicUrl="publicUrl"
        :approvedSchoolsCount="stats.total_schools"
        :pendingPaymentsCount="pendingPaymentsCount"
    >
        <div class="space-y-6">
            <PageHeader
                title="School Code Bulk Assignment"
                eyebrow="Membership Management"
                :description="`Assign, manage, and download sequential codes for all ${stats.total_schools} approved member schools (Sahodaya Prefix: ${sahodayaPrefix}).`"
            >
                <template #actions>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/schools`" class="btn-secondary text-sm">
                        ← Member schools
                    </Link>
                    <a :href="`/sahodaya-admin/${sahodaya.id}/schools/code-assignment/export-pdf`"
                       target="_blank"
                       class="btn-secondary text-sm font-semibold text-rose-700 bg-rose-50 border-rose-200 hover:bg-rose-100">
                        📄 Download PDF Register
                    </a>
                    <a :href="`/sahodaya-admin/${sahodaya.id}/schools/code-assignment/export-excel`"
                       class="btn-secondary text-sm font-semibold text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100">
                        📊 Download Excel
                    </a>
                    <a :href="`/sahodaya-admin/${sahodaya.id}/schools/code-assignment/export-csv`"
                       class="btn-secondary text-sm">
                        CSV
                    </a>
                    <button type="button" class="btn-primary text-sm shadow-sm" :disabled="saving" @click="saveAll">
                        {{ saving ? 'Saving…' : '💾 Save Assignments' }}
                    </button>
                </template>
            </PageHeader>

            <!-- Summary KPI Stats -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <DashboardStatCard label="Member schools" :value="stats.total_schools" icon="🏫" tone="navy" />
                <DashboardStatCard label="Codes assigned" :value="stats.assigned_count" icon="✓" tone="green" />
                <DashboardStatCard label="Unassigned schools" :value="stats.unassigned_count" icon="⚠️" tone="amber" />
                <DashboardStatCard label="Registered students" :value="stats.total_students" icon="👨‍🎓" tone="indigo" />
            </div>

            <!-- Bulk Auto-Assign Tool Panel -->
            <div class="rounded-2xl border border-indigo-100 bg-gradient-to-r from-indigo-50/70 to-blue-50/50 p-5 shadow-xs space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-[#0f3d7a] flex items-center gap-2">
                            <span>⚡ Bulk Auto-Assign Sequential Codes (1 to N)</span>
                        </h3>
                        <p class="text-xs text-slate-600 mt-0.5">
                            Automatically number schools sequentially from 1 to N under this Sahodaya. Unique school codes will be generated as <strong class="font-mono text-indigo-900">{{ sahodayaPrefix }}-001, {{ sahodayaPrefix }}-002...</strong>
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-4 items-end bg-white/80 p-3.5 rounded-xl border border-indigo-100">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Starting Number</label>
                        <input v-model.number="autoForm.start_no" type="number" min="1" class="field text-sm font-mono w-full" placeholder="1" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Order By</label>
                        <select v-model="autoForm.order_by" class="field text-sm w-full">
                            <option value="name_asc">School Name (A to Z)</option>
                            <option value="affiliation_asc">CBSE Affiliation No.</option>
                            <option value="created_asc">Join Date (Oldest first)</option>
                            <option value="name_desc">School Name (Z to A)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Scope</label>
                        <select v-model="autoForm.scope" class="field text-sm w-full">
                            <option value="all">All Member Schools (Renumber 1…N)</option>
                            <option value="unassigned">Only Unassigned Schools</option>
                        </select>
                    </div>
                    <div>
                        <button type="button"
                                class="btn-primary text-sm w-full bg-[#0f3d7a] hover:bg-[#0c3162]"
                                :disabled="autoAssigning"
                                @click="runAutoAssign">
                            {{ autoAssigning ? 'Processing…' : '⚡ Run Auto-Assign' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3 flex-1 max-w-xl">
                    <div class="relative flex-1 min-w-[200px]">
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search by school name, code, or affiliation…"
                            class="field text-xs pl-8 w-full"
                        />
                        <span class="absolute left-2.5 top-2 text-slate-400 text-xs">🔍</span>
                    </div>
                    <div class="flex items-center gap-1.5 bg-slate-100 p-1 rounded-lg text-xs">
                        <button type="button"
                                @click="statusFilter = 'all'"
                                :class="['px-2.5 py-1 rounded-md font-medium transition', statusFilter === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900']">
                            All ({{ schoolsList.length }})
                        </button>
                        <button type="button"
                                @click="statusFilter = 'assigned'"
                                :class="['px-2.5 py-1 rounded-md font-medium transition', statusFilter === 'assigned' ? 'bg-white text-emerald-800 shadow-xs' : 'text-slate-600 hover:text-slate-900']">
                            Assigned ({{ assignedListCount }})
                        </button>
                        <button type="button"
                                @click="statusFilter = 'unassigned'"
                                :class="['px-2.5 py-1 rounded-md font-medium transition', statusFilter === 'unassigned' ? 'bg-white text-amber-800 shadow-xs' : 'text-slate-600 hover:text-slate-900']">
                            Unassigned ({{ unassignedListCount }})
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span v-if="hasUnsavedChanges" class="text-xs font-semibold text-amber-600 animate-pulse">
                        ● Unsaved edits
                    </span>
                    <button type="button" class="btn-primary text-xs !py-2 !px-4" :disabled="saving || !hasUnsavedChanges" @click="saveAll">
                        {{ saving ? 'Saving…' : 'Save Changes' }}
                    </button>
                </div>
            </div>

            <!-- Schools Table -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase tracking-wider font-semibold">
                                <th class="py-3 px-3 w-10 text-center">#</th>
                                <th class="py-3 px-4 min-w-[16rem]">School Name</th>
                                <th class="py-3 px-3 w-32">Affiliation No.</th>
                                <th class="py-3 px-3 w-28">Short Code</th>
                                <th class="py-3 px-3 w-28 text-center">School No (1..N)</th>
                                <th class="py-3 px-3 w-36">Generated Code</th>
                                <th class="py-3 px-3 w-24 text-center">Students</th>
                                <th class="py-3 px-3 w-36">Student ID Sample</th>
                                <th class="py-3 px-3 w-16 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(school, index) in filteredSchools" :key="school.id"
                                :class="['hover:bg-slate-50/80 transition', school.school_no ? 'bg-white' : 'bg-amber-50/20']">
                                <td class="py-3 px-3 text-center text-slate-400 font-mono">
                                    {{ index + 1 }}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900 text-sm leading-tight">{{ school.name }}</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5 flex items-center gap-2">
                                        <span v-if="school.email">✉ {{ school.email }}</span>
                                        <span v-if="school.phone">📞 {{ school.phone }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 font-mono text-slate-600">
                                    {{ school.affiliation || '—' }}
                                </td>
                                <td class="py-3 px-3">
                                    <input
                                        v-model="school.school_prefix"
                                        type="text"
                                        placeholder="AMU"
                                        maxlength="10"
                                        @input="onFieldChange(school)"
                                        class="field !py-1 !px-2 font-mono uppercase text-xs w-24"
                                    />
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <input
                                        v-model.number="school.school_no"
                                        type="number"
                                        min="1"
                                        placeholder="No."
                                        @input="onFieldChange(school)"
                                        class="field !py-1 !px-2 text-center font-mono font-bold text-xs w-20 mx-auto"
                                    />
                                </td>
                                <td class="py-3 px-3">
                                    <span v-if="school.school_no"
                                          class="inline-flex items-center px-2 py-0.5 rounded font-mono font-bold text-xs bg-indigo-50 text-indigo-800 border border-indigo-200 shadow-2xs">
                                        {{ sahodayaPrefix }}-{{ String(school.school_no).padStart(3, '0') }}
                                    </span>
                                    <span v-else class="text-xs text-slate-400 italic">
                                        Not assigned
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-center font-bold text-slate-700">
                                    {{ school.students_count?.toLocaleString() || 0 }}
                                </td>
                                <td class="py-3 px-3">
                                    <span v-if="school.sample_student_id"
                                          class="font-mono text-[11px] font-semibold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">
                                        {{ school.sample_student_id }}
                                    </span>
                                    <span v-else class="text-slate-400 italic text-[11px]">—</span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <button
                                        v-if="school.school_no"
                                        type="button"
                                        title="Clear assignment"
                                        class="text-xs text-rose-500 hover:text-rose-700 font-bold px-1.5 py-0.5 rounded hover:bg-rose-50"
                                        @click="clearSchool(school)"
                                    >
                                        ✕
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredSchools.length === 0">
                                <td colspan="9" class="py-8 text-center text-slate-400">
                                    No schools match your search or filter criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Summary Bar -->
                <div class="bg-slate-50 border-t border-slate-200 px-4 py-3 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-600">
                    <div>
                        Showing <strong class="text-slate-900">{{ filteredSchools.length }}</strong> of {{ schoolsList.length }} schools
                        · <strong class="text-emerald-700">{{ assignedListCount }} assigned</strong>
                        · <strong class="text-amber-700">{{ unassignedListCount }} unassigned</strong>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn-primary text-xs !py-1.5 !px-3" :disabled="saving || !hasUnsavedChanges" @click="saveAll">
                            {{ saving ? 'Saving…' : 'Save Assignments' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import DashboardStatCard from '@/Components/ui/DashboardStatCard.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    schools: Array,
    sahodayaPrefix: String,
    stats: Object,
});

const schoolsList = ref(props.schools.map((s) => ({ ...s })));
const search = ref('');
const statusFilter = ref('all');
const saving = ref(false);
const autoAssigning = ref(false);
const hasUnsavedChanges = ref(false);

const autoForm = ref({
    start_no: 1,
    order_by: 'name_asc',
    scope: 'all',
});

const assignedListCount = computed(() => schoolsList.value.filter((s) => s.school_no !== null && s.school_no !== '').length);
const unassignedListCount = computed(() => schoolsList.value.length - assignedListCount.value);

const filteredSchools = computed(() => {
    let list = schoolsList.value;

    if (statusFilter.value === 'assigned') {
        list = list.filter((s) => s.school_no !== null && s.school_no !== '');
    } else if (statusFilter.value === 'unassigned') {
        list = list.filter((s) => s.school_no === null || s.school_no === '');
    }

    if (!search.value.trim()) {
        return list;
    }

    const q = search.value.trim().toLowerCase();
    return list.filter((s) =>
        (s.name || '').toLowerCase().includes(q)
        || (s.school_prefix || '').toLowerCase().includes(q)
        || (s.affiliation || '').toLowerCase().includes(q)
        || (s.school_code || '').toLowerCase().includes(q)
        || String(s.school_no || '').includes(q)
    );
});

function onFieldChange(school) {
    if (school.school_prefix) {
        school.school_prefix = school.school_prefix.toUpperCase().replace(/[^A-Z0-9]/g, '');
    }
    hasUnsavedChanges.value = true;
}

function clearSchool(school) {
    school.school_no = null;
    hasUnsavedChanges.value = true;
}

function saveAll() {
    if (saving.value) return;
    saving.value = true;

    const payload = schoolsList.value.map((s) => ({
        id: s.id,
        school_no: s.school_no ? Number(s.school_no) : null,
        school_prefix: s.school_prefix || null,
    }));

    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/code-assignment/save`, {
        assignments: payload,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            hasUnsavedChanges.value = false;
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}

function runAutoAssign() {
    if (autoAssigning.value) return;

    if (autoForm.value.scope === 'all' && !confirm(`This will renumber all ${schoolsList.value.length} schools sequentially from ${autoForm.value.start_no}. Existing school numbers will be replaced. Proceed?`)) {
        return;
    }

    autoAssigning.value = true;

    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/code-assignment/auto-assign`, autoForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            hasUnsavedChanges.value = false;
        },
        onFinish: () => {
            autoAssigning.value = false;
        },
    });
}
</script>
