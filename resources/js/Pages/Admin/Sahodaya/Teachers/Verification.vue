<template>
    <SahodayaAdminLayout title="Teacher verification" :sahodaya="sahodaya" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Teacher verification" eyebrow="Membership" description="Verify teachers before training nomination.">
            <template #actions>
                <button v-if="selectedIds.length" type="button" class="btn-primary text-sm" @click="bulkVerifySelected">
                    Verify selected ({{ selectedIds.length }})
                </button>
                <button v-else-if="f.school_id && selectedSchoolName" type="button" class="btn-primary text-sm" @click="bulkVerifyAllPending">
                    Verify all pending in {{ selectedSchoolName }}
                </button>
            </template>
        </PageHeader>

        <div class="grid sm:grid-cols-3 gap-4 mb-6">
            <div class="card !py-4 text-center"><p class="text-2xl font-bold">{{ counts.total }}</p><p class="text-xs text-slate-500">Active</p></div>
            <div class="card !py-4 text-center"><p class="text-2xl font-bold text-emerald-700">{{ counts.verified }}</p><p class="text-xs text-slate-500">Verified</p></div>
            <div class="card !py-4 text-center"><p class="text-2xl font-bold text-amber-700">{{ counts.unverified }}</p><p class="text-xs text-slate-500">Pending</p></div>
        </div>

        <form class="card !p-4 mb-4 flex flex-wrap gap-3 items-end" @submit.prevent="apply">
            <select v-model="f.school_id" class="field text-sm"><option value="">All schools</option><option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option></select>
            <select v-model="f.verification" class="field text-sm"><option value="all">All</option><option value="unverified">Pending</option><option value="verified">Verified</option></select>
            <select v-model="f.teaching_type_id" class="field text-sm">
                <option value="">All teaching types</option>
                <option v-for="tt in teachingTypes" :key="tt.id" :value="tt.id">{{ tt.label }}</option>
            </select>
            <input v-model="f.search" class="field text-sm" placeholder="Search">
            <button class="btn-secondary text-sm">Apply</button>
        </form>

        <div v-if="rejectionErrors.length" class="card !p-3 mb-4 border-red-200 bg-red-50 text-sm text-red-700">
            <p v-for="(msg, i) in rejectionErrors" :key="i">{{ msg }}</p>
        </div>

        <div class="card overflow-x-auto p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" :checked="allPendingOnPageSelected" :disabled="!pendingOnPage.length" @change="toggleSelectAll($event.target.checked)">
                        </th>
                        <th>School</th><th>Teacher</th><th>Category</th><th>Subjects</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in teachers.data" :key="t.id">
                        <td>
                            <input v-if="!t.is_verified" type="checkbox" :value="t.id" v-model="selectedIds">
                        </td>
                        <td>{{ t.school_name }}</td>
                        <td>
                            <strong>{{ t.name }}</strong>
                            <p class="text-xs text-slate-500">{{ t.email }}</p>
                            <p v-if="t.rejection_reason" class="text-xs text-red-600 mt-0.5">Rejected: {{ t.rejection_reason }}</p>
                        </td>
                        <td>{{ t.category || '—' }}</td>
                        <td class="text-xs">{{ (t.subjects || []).join(', ') || '—' }}</td>
                        <td><span class="status-pill text-xs" :class="t.is_verified ? 'status-pill--completed' : 'status-pill--open'">{{ t.is_verified ? 'Verified' : 'Pending' }}</span></td>
                        <td class="text-right whitespace-nowrap">
                            <button v-if="!t.is_verified" type="button" class="text-xs font-semibold text-emerald-700 mr-3" @click="verify(t)">Verify</button>
                            <button v-if="!t.is_verified" type="button" class="text-xs font-semibold text-red-600" @click="openReject(t)">Reject</button>
                        </td>
                    </tr>
                    <tr v-if="!teachers.data.length">
                        <td colspan="7" class="p-8 text-center text-slate-400">No teachers match the filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="teachers.links?.length > 3" class="flex justify-center gap-1 mt-4">
            <Link v-for="link in teachers.links" :key="link.label"
                  :href="link.url || '#'"
                  class="px-3 py-1 rounded text-xs font-medium"
                  :class="link.active ? 'bg-[#0f3d7a] text-white' : (link.url ? 'text-[#0f3d7a] hover:bg-gray-100' : 'text-gray-300 pointer-events-none')"
                  v-html="link.label" />
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
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    teachers: Object,
    counts: Object,
    filters: Object,
    schools: Array,
    teachingTypes: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}`;
const f = reactive({ ...props.filters });
const selectedIds = ref([]);
const rejectingTeacher = ref(null);
const rejectReason = ref('');
const rejectionErrors = ref([]);

const pendingOnPage = computed(() => (props.teachers.data ?? []).filter((t) => !t.is_verified));
const allPendingOnPageSelected = computed(() =>
    pendingOnPage.value.length > 0 && pendingOnPage.value.every((t) => selectedIds.value.includes(t.id))
);
const selectedSchoolName = computed(() => props.schools.find((s) => String(s.id) === String(f.school_id))?.name ?? '');

function apply() {
    router.get(`${base}/teachers/verification`, f, { preserveState: true });
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

function bulkVerifyAllPending() {
    if (!confirm(`Verify all pending teachers at ${selectedSchoolName.value}?`)) return;
    router.post(`${base}/teachers/verification/bulk-verify`, {
        verify_all_unverified: true,
        school_id: f.school_id,
    }, { preserveScroll: true });
}
</script>
