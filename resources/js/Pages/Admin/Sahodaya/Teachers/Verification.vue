<template>
    <SahodayaAdminLayout title="Teacher verification" :sahodaya="sahodaya" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="selectedSchool ? selectedSchool.name : 'Teacher verification'"
                    eyebrow="Membership"
                    :description="selectedSchool
                        ? 'Review teacher details, then verify or reject — use checkboxes for bulk actions.'
                        : 'Verify teachers before training nomination. Use All teachers for pagination and bulk verify, or By school to drill in.'">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/schools`" class="btn-secondary text-sm">
                    Schools list
                </Link>
                <button v-if="selectedIds.length" type="button" class="btn-primary text-sm" @click="bulkVerifySelected">
                    Verify selected ({{ selectedIds.length }})
                </button>
                <button v-else-if="selectedSchool && schoolPendingCount > 0" type="button"
                        class="btn-primary text-sm" @click="bulkVerifySchool">
                    Verify all pending ({{ schoolPendingCount }})
                </button>
                <button v-else-if="showTeacherList && counts.unverified > 0 && !selectedSchool" type="button"
                        class="btn-primary text-sm" @click="bulkVerifyAllPending">
                    Verify all pending ({{ counts.unverified }})
                </button>
                <button v-else-if="showTeacherList && pendingOnPage.length" type="button"
                        class="btn-secondary text-sm" @click="bulkVerifyPage">
                    Verify all on this page ({{ pendingOnPage.length }})
                </button>
            </template>
        </PageHeader>

        <div class="grid sm:grid-cols-3 gap-4 mb-6">
            <button type="button" class="card card--muted !py-4 text-center hover:ring-2 hover:ring-[#0f3d7a]/20 transition"
                    @click="setVerification('all')">
                <p class="text-2xl font-bold">{{ counts.total }}</p>
                <p class="text-xs text-slate-500 mt-1">Active teachers</p>
            </button>
            <button type="button" class="card card--muted !py-4 text-center hover:ring-2 hover:ring-emerald-500/20 transition"
                    @click="setVerification('verified')">
                <p class="text-2xl font-bold text-emerald-700">{{ counts.verified }}</p>
                <p class="text-xs text-slate-500 mt-1">Verified</p>
            </button>
            <button type="button" class="card card--muted !py-4 text-center hover:ring-2 hover:ring-amber-500/20 transition"
                    @click="setVerification('unverified')">
                <p class="text-2xl font-bold text-amber-700">{{ counts.unverified }}</p>
                <p class="text-xs text-slate-500 mt-1">Pending verification</p>
            </button>
        </div>

        <form class="card !p-4 mb-4 flex flex-wrap gap-3 items-end" @submit.prevent="apply">
            <template v-if="selectedSchool">
                <div class="field text-sm flex items-center justify-between gap-2 bg-gray-50 min-w-[12rem]">
                    <span class="truncate font-medium text-gray-800">{{ selectedSchool.name }}</span>
                    <button type="button" class="text-xs font-semibold text-[#0f3d7a] hover:underline shrink-0" @click="clearSchool">
                        Change
                    </button>
                </div>
            </template>
            <select v-else v-model="f.school_id" class="field text-sm min-w-[12rem]">
                <option value="">All schools</option>
                <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <select v-model="f.verification" class="field text-sm">
                <option value="all">All</option>
                <option value="unverified">Pending</option>
                <option value="verified">Verified</option>
            </select>
            <select v-model="f.teaching_type_id" class="field text-sm">
                <option value="">All teaching types</option>
                <option v-for="tt in teachingTypes" :key="tt.id" :value="tt.id">{{ tt.label }}</option>
            </select>
            <input v-model="f.search" class="field text-sm"
                   :placeholder="showTeacherList ? 'Name, email, school' : 'School name'">
            <button class="btn-secondary text-sm">Apply</button>
        </form>

        <div v-if="!selectedSchool" class="mb-4 flex flex-wrap gap-2">
            <button type="button" class="px-3 py-1.5 rounded-md text-sm font-semibold border"
                    :class="showTeacherList ? 'bg-white text-slate-600 border-slate-200' : 'bg-[#0f3d7a] text-white border-[#0f3d7a]'"
                    @click="setView('schools')">
                By school
            </button>
            <button type="button" class="px-3 py-1.5 rounded-md text-sm font-semibold border"
                    :class="showTeacherList ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'bg-white text-slate-600 border-slate-200'"
                    @click="setView('list')">
                All teachers
            </button>
        </div>

        <div v-if="rejectionErrors.length" class="card !p-3 mb-4 border-red-200 bg-red-50 text-sm text-red-700">
            <p v-for="(msg, i) in rejectionErrors" :key="i">{{ msg }}</p>
        </div>

        <!-- Schools summary -->
        <div v-if="!selectedSchool && !showTeacherList" class="card overflow-hidden p-0">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="font-bold text-gray-900">Schools</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ schoolSummaries.length }} school{{ schoolSummaries.length === 1 ? '' : 's' }}
                        <template v-if="f.verification === 'unverified'">with pending teachers</template>
                        <template v-else-if="f.verification === 'verified'">fully verified</template>
                        · open a school or switch to All teachers for pagination / bulk verify
                    </p>
                </div>
                <button v-if="counts.unverified > 0" type="button" class="btn-primary text-sm" @click="setView('list')">
                    Browse pending teachers
                </button>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>School</th>
                        <th class="text-right">Active</th>
                        <th class="text-right">Verified</th>
                        <th class="text-right">Pending</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in schoolSummaries" :key="row.id" class="hover:bg-gray-50/80">
                        <td>
                            <button type="button" class="font-medium text-[#0f3d7a] hover:underline text-left" @click="openSchool(row)">
                                {{ row.name }}
                            </button>
                        </td>
                        <td class="text-right text-sm tabular-nums">{{ row.total }}</td>
                        <td class="text-right text-sm tabular-nums text-emerald-700">{{ row.verified }}</td>
                        <td class="text-right text-sm tabular-nums" :class="row.unverified ? 'text-amber-700 font-semibold' : 'text-gray-400'">
                            {{ row.unverified }}
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" class="text-xs font-semibold text-[#0f3d7a] hover:underline mr-3" @click="openSchool(row)">
                                Open
                            </button>
                            <button v-if="row.unverified > 0" type="button" class="btn-primary text-xs py-1.5 px-2.5" @click="bulkVerifySchoolRow(row)">
                                Verify all ({{ row.unverified }})
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!schoolSummaries.length">
                        <td colspan="5" class="p-8 text-center text-slate-400">No schools match the filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Teachers list (one school or all schools) -->
        <div v-else class="card overflow-hidden p-0">
            <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                <template v-if="selectedSchool">
                    <button type="button" class="font-semibold text-[#0f3d7a] hover:underline" @click="clearSchool">
                        ← All schools
                    </button>
                    <span>/</span>
                    <span class="font-medium text-gray-800">{{ selectedSchool.name }}</span>
                </template>
                <template v-else>
                    <span class="font-medium text-gray-800">All teachers</span>
                    <button type="button" class="font-semibold text-[#0f3d7a] hover:underline ml-1" @click="setView('schools')">
                        · By school
                    </button>
                </template>
                <span v-if="teachers?.total != null" class="ml-auto tabular-nums">
                    {{ teachers.from ?? 0 }}–{{ teachers.to ?? 0 }} of {{ teachers.total }}
                    · page {{ teachers.current_page }}/{{ teachers.last_page }}
                </span>
            </div>
            <div v-if="pendingOnPage.length" class="px-5 py-2 border-b border-gray-100 bg-slate-50 flex flex-wrap items-center gap-3 text-xs">
                <label class="inline-flex items-center gap-2 font-semibold text-slate-700 cursor-pointer">
                    <input type="checkbox" :checked="allPendingOnPageSelected" @change="toggleSelectAll($event.target.checked)">
                    Select all pending on this page ({{ pendingOnPage.length }})
                </label>
                <button v-if="selectedIds.length" type="button" class="btn-primary text-xs py-1 px-2.5" @click="bulkVerifySelected">
                    Verify selected ({{ selectedIds.length }})
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table min-w-[56rem]">
                    <thead>
                        <tr>
                            <th class="w-10">
                                <input type="checkbox" :checked="allPendingOnPageSelected" :disabled="!pendingOnPage.length" @change="toggleSelectAll($event.target.checked)">
                            </th>
                            <th>Sl No</th>
                            <th class="w-14">Photo</th>
                            <th v-if="!selectedSchool">School</th>
                            <th>Teacher</th>
                            <th>Category</th>
                            <th>Subjects</th>
                            <th>Status</th>
                            <th class="text-right w-36"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(t, idx) in teachers?.data ?? []" :key="t.id" class="align-top">
                            <td class="py-3">
                                <input v-if="!t.is_verified" type="checkbox" :value="t.id" v-model="selectedIds">
                            </td>
                            <td class="py-3">{{ idx + 1 }}</td>
                            <td class="py-3">
                                <div class="w-10 h-10 rounded-full overflow-hidden border border-gray-200 bg-gray-100 flex items-center justify-center shrink-0">
                                    <img v-if="t.photo_url" :src="t.photo_url" :alt="t.name" class="w-full h-full object-cover">
                                    <span v-else class="text-xs text-gray-400 font-semibold">{{ initials(t.name) }}</span>
                                </div>
                            </td>
                            <td v-if="!selectedSchool" class="py-3 text-sm">
                                <button type="button" class="text-[#0f3d7a] hover:underline text-left font-medium" @click="openSchoolById(t.school_id, t.school_name)">
                                    {{ (t.school_name || '').toUpperCase() || '—' }}
                                </button>
                            </td>
                            <td class="py-3">
                                <p class="font-semibold text-gray-900">{{ t.name }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">{{ t.email }}</p>
                                <p v-if="t.employee_code" class="text-xs font-mono text-slate-500">{{ t.employee_code }}</p>
                                <p v-if="t.rejection_reason" class="text-xs text-red-600 mt-0.5">Rejected: {{ t.rejection_reason }}</p>
                            </td>
                            <td class="py-3 text-sm">{{ t.category || '—' }}</td>
                            <td class="py-3 text-xs">{{ (t.subjects || []).join(', ') || '—' }}</td>
                            <td class="py-3">
                                <span class="status-pill text-xs" :class="t.is_verified ? 'status-pill--completed' : 'status-pill--open'">{{ t.is_verified ? 'Verified' : 'Pending' }}</span>
                                <p v-if="t.is_verified && t.verified_at_display" class="text-[11px] text-slate-500 mt-1">
                                    {{ t.verified_at_display }}
                                    <template v-if="t.verified_by"> · {{ t.verified_by }}</template>
                                </p>
                            </td>
                            <td class="py-3 text-right whitespace-nowrap">
                                <button v-if="!t.is_verified" type="button" class="text-xs font-semibold text-emerald-700 mr-3" @click="verify(t)">Verify</button>
                                <button v-if="!t.is_verified" type="button" class="text-xs font-semibold text-red-600" @click="openReject(t)">Reject</button>
                            </td>
                        </tr>
                        <tr v-if="!(teachers?.data?.length)">
                            <td :colspan="selectedSchool ? 8 : 9" class="p-8 text-center text-slate-400">No teachers match the filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="teachers?.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex flex-wrap justify-center gap-1">
                <Link v-for="link in teachers.links" :key="link.label"
                      :href="link.url || '#'"
                      class="px-3 py-1 rounded text-xs font-medium"
                      :class="link.active ? 'bg-[#0f3d7a] text-white' : (link.url ? 'text-[#0f3d7a] hover:bg-gray-100' : 'text-gray-300 pointer-events-none')"
                      v-html="link.label"
                      @click="selectedIds = []" />
            </div>
            <div v-else-if="teachers?.total" class="px-4 py-2 border-t border-gray-100 text-center text-xs text-slate-400">
                Showing all {{ teachers.total }} matching teacher{{ teachers.total === 1 ? '' : 's' }} on this page
            </div>
        </div>

        <div v-if="rejectingTeacher" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeReject">
            <form class="card w-full max-w-md shadow-xl space-y-3" @submit.prevent="submitReject">
                <h3 class="section-title">Reject verification</h3>
                <p class="text-sm text-slate-600">{{ rejectingTeacher.name }} — {{ rejectingTeacher.school_name }}</p>
                <div>
                    <label class="text-xs font-semibold text-slate-600 block mb-1">Reason <span class="text-red-500">*</span></label>
                    <textarea v-model="rejectReason" rows="3" class="field !py-2 !text-sm w-full" required maxlength="500" placeholder="Explain what needs to be corrected"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" class="btn-secondary text-sm" @click="closeReject">Cancel</button>
                    <button type="submit" class="btn-primary text-sm bg-red-600 hover:bg-red-700" :disabled="!rejectReason.trim()">Reject</button>
                </div>
            </form>
        </div>
    </SahodayaAdminLayout>
</template>
<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    teachers: { type: Object, default: null },
    schoolSummaries: { type: Array, default: () => [] },
    selectedSchool: { type: Object, default: null },
    counts: Object,
    filters: Object,
    schools: Array,
    teachingTypes: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}`;
const f = reactive({
    school_id: props.filters?.school_id ?? '',
    verification: props.filters?.verification ?? 'all',
    search: props.filters?.search ?? '',
    teaching_type_id: props.filters?.teaching_type_id ?? '',
    view: props.filters?.view ?? 'schools',
});
const selectedIds = ref([]);
const rejectingTeacher = ref(null);
const rejectReason = ref('');
const rejectionErrors = ref([]);

const showTeacherList = computed(() => !!props.selectedSchool || f.view === 'list' || props.filters?.view === 'list');
const schoolPendingCount = computed(() => props.selectedSchool?.unverified ?? 0);
const pendingOnPage = computed(() => (props.teachers?.data ?? []).filter((t) => !t.is_verified));
const allPendingOnPageSelected = computed(() =>
    pendingOnPage.value.length > 0 && pendingOnPage.value.every((t) => selectedIds.value.includes(t.id))
);

watch(() => props.teachers?.current_page, () => {
    selectedIds.value = [];
});

function apply() {
    const params = { ...f };
    if (params.school_id) {
        params.view = 'list';
    }
    router.get(`${base}/teachers/verification`, params, { preserveState: true, preserveScroll: true });
}

function setVerification(value) {
    f.verification = value;
    if (!f.school_id) {
        f.view = 'list';
    }
    selectedIds.value = [];
    apply();
}

function setView(view) {
    f.view = view;
    f.school_id = '';
    selectedIds.value = [];
    apply();
}

function openSchool(row) {
    f.school_id = row.id;
    f.view = 'list';
    f.search = '';
    selectedIds.value = [];
    apply();
}

function openSchoolById(id, name) {
    openSchool({ id, name });
}

function clearSchool() {
    f.school_id = '';
    f.view = 'list';
    selectedIds.value = [];
    apply();
}

function verify(t) {
    router.post(`${base}/teachers/${t.id}/verify`, {}, { preserveScroll: true });
}

function openReject(t) {
    rejectingTeacher.value = t;
    rejectReason.value = '';
    rejectionErrors.value = [];
}

function closeReject() {
    rejectingTeacher.value = null;
    rejectReason.value = '';
}

function submitReject() {
    if (!rejectReason.value.trim()) return;
    const teacher = rejectingTeacher.value;
    router.post(`${base}/teachers/${teacher.id}/reject`, { reason: rejectReason.value.trim() }, {
        preserveScroll: true,
        onSuccess: () => closeReject(),
        onError: (errors) => { rejectionErrors.value = Object.values(errors); },
    });
}

function toggleSelectAll(checked) {
    const pendingIds = pendingOnPage.value.map((t) => t.id);
    if (checked) {
        selectedIds.value = [...new Set([...selectedIds.value, ...pendingIds])];
    } else {
        selectedIds.value = selectedIds.value.filter((id) => !pendingIds.includes(id));
    }
}

function bulkVerifySelected() {
    if (!selectedIds.value.length) return;
    router.post(`${base}/teachers/verification/bulk-verify`, { teacher_ids: selectedIds.value }, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; },
    });
}

function bulkVerifyPage() {
    const ids = pendingOnPage.value.map((t) => t.id);
    if (!ids.length) return;
    router.post(`${base}/teachers/verification/bulk-verify`, { teacher_ids: ids }, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; },
    });
}

function bulkVerifySchool() {
    if (!props.selectedSchool || !schoolPendingCount.value) return;
    if (!confirm(`Verify all ${schoolPendingCount.value} pending teacher(s) at ${props.selectedSchool.name}?`)) return;
    router.post(`${base}/teachers/verification/bulk-verify`, {
        verify_all_unverified: true,
        school_id: props.selectedSchool.id,
    }, { preserveScroll: true });
}

function bulkVerifyAllPending() {
    if (!props.counts?.unverified) return;
    if (!confirm(`Verify all ${props.counts.unverified} pending teacher(s) across all schools?`)) return;
    router.post(`${base}/teachers/verification/bulk-verify`, {
        verify_all_unverified: true,
    }, { preserveScroll: true });
}

function bulkVerifySchoolRow(row) {
    if (!row.unverified) return;
    if (!confirm(`Verify all ${row.unverified} pending teacher(s) at ${row.name}?`)) return;
    router.post(`${base}/teachers/verification/bulk-verify`, {
        verify_all_unverified: true,
        school_id: row.id,
    }, { preserveScroll: true });
}

function initials(name) {
    return (name || '?').trim().charAt(0).toUpperCase();
}
</script>
