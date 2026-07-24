<template>
    <SchoolAdminLayout title="Teacher Training" :school="school" :show-header-title="false">
        <PageHeader title="Teacher Training" eyebrow="Teacher training"
            description="Register teachers (including unverified), upload fees, and mark session attendance." />

        <div class="space-y-4">
            <div v-for="program in programs" :key="program.id" class="card">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                    <div>
                        <h3 class="font-semibold">{{ program.title }}</h3>
                        <p v-if="program.description" class="text-sm text-gray-600 mt-1">{{ program.description }}</p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ program.status }}
                            <template v-if="program.fee_type !== 'none' && program.fee_amount">
                                <span class="mx-1">·</span>
                                <span class="font-semibold text-indigo-700">
                                    <template v-if="program.fee_type === 'school'">
                                        School batch fee: ₹{{ program.fee_amount }} / teacher
                                    </template>
                                    <template v-else>
                                        Fee: ₹{{ program.fee_amount }}
                                    </template>
                                </span>
                            </template>
                            <template v-if="program.sessions_count">
                                <span class="mx-1">·</span>
                                {{ program.sessions_count }} session(s)
                            </template>
                            <template v-if="!program.require_verified_teachers">
                                <span class="mx-1">·</span>
                                <span class="text-amber-700">Unverified teachers allowed</span>
                            </template>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a v-if="registrations[program.id]?.length"
                           :href="`/school-admin/${school.id}/training/${program.id}/export`"
                           class="btn-secondary text-sm">Export (.xlsx)</a>
                        <a v-if="registrations[program.id]?.length"
                           :href="`/school-admin/${school.id}/training/${program.id}/export?format=csv`"
                           class="btn-secondary text-sm">Export (.csv)</a>
                        <Link v-if="program.allow_school_attendance !== false && ['published','ongoing','completed'].includes(program.status)"
                              :href="`/school-admin/${school.id}/training/${program.id}/attendance`"
                              class="btn-secondary text-sm">
                            Mark attendance
                        </Link>
                    </div>
                </div>

                <!-- School batch fee upload -->
                <div v-if="program.fee_type === 'school' && program.fee_amount && registrations[program.id]?.length"
                     class="rounded-lg border border-indigo-100 bg-indigo-50/40 p-3 mb-3 space-y-2 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="font-semibold text-indigo-900">School batch fee</p>
                            <p class="text-xs text-indigo-800 mt-0.5">
                                {{ schoolFeeFor(program)?.teacher_count || registrations[program.id].length }} teacher(s)
                                · Due ₹{{ fmt(schoolFeeFor(program)?.total_due ?? (registrations[program.id].length * Number(program.fee_amount))) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <a :href="`/school-admin/${school.id}/training/${program.id}/school-fee/invoice`"
                               target="_blank" rel="noopener"
                               class="text-xs font-semibold text-indigo-700 hover:underline">Invoice ↓</a>
                            <span v-if="schoolFeeFor(program)?.status === 'approved'" class="text-xs font-semibold text-green-700 bg-green-50 px-2 py-0.5 rounded">Fee paid</span>
                            <span v-else-if="schoolFeeFor(program)?.status === 'proof_uploaded' || schoolFeeFor(program)?.fee_receipt?.status === 'uploaded'" class="text-xs text-amber-700 font-semibold">Pending approval</span>
                            <span v-else-if="schoolFeeFor(program)?.fee_receipt?.status === 'rejected'" class="text-xs text-red-600 font-semibold">Rejected — re-upload</span>
                            <span v-else-if="schoolFeeFor(program)?.status === 'partial'" class="text-xs text-amber-700 font-semibold">Partial — balance due</span>
                            <span v-else class="text-xs text-slate-500">Payment required</span>
                        </div>
                    </div>
                    <form v-if="needsSchoolFeeUpload(program)"
                          @submit.prevent="uploadSchoolFee(program)" class="flex flex-wrap gap-2 items-center">
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" multiple
                               @change="e => schoolFeeFiles[program.id] = Array.from(e.target.files ?? [])"
                               class="text-xs" required>
                        <input v-model="schoolFeeRefs[program.id]" class="field text-xs max-w-xs" placeholder="Transaction ref (optional)">
                        <button class="btn-primary text-xs !min-h-0 !px-2 !py-1">Upload batch proof</button>
                    </form>
                </div>

                <ul v-if="registrations[program.id]?.length" class="text-sm divide-y mb-3">
                    <li v-for="r in registrations[program.id]" :key="r.id" class="py-2">
                        <div class="flex justify-between items-start gap-2 flex-wrap">
                            <div>
                                <span>{{ r.teacher?.name }}</span>
                                <span v-if="r.teacher && !r.teacher.verified_at"
                                      class="ml-2 text-[10px] uppercase tracking-wide text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">
                                    Unverified
                                </span>
                                <span v-if="r.registration_source === 'qr'"
                                      class="ml-1 text-[10px] uppercase tracking-wide text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded">
                                    QR
                                </span>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-gray-400 text-xs capitalize">{{ r.status }}
                                    <span v-if="r.status === 'waitlisted' && r.waitlist_position"> #{{ r.waitlist_position }}</span>
                                </span>
                                <a v-if="!['cancelled','rejected'].includes(r.status)"
                                   :href="`/school-admin/${school.id}/training/${r.id}/id-card`"
                                   target="_blank" rel="noopener"
                                   class="text-xs font-semibold text-slate-600 hover:underline">ID card ↓</a>
                                <template v-if="program.fee_type === 'school' && program.fee_amount">
                                    <span class="text-xs text-indigo-700">Covered by school fee</span>
                                </template>
                                <template v-else-if="program.fee_type !== 'none' && program.fee_amount">
                                    <a :href="`/school-admin/${school.id}/training/${r.id}/invoice`"
                                       target="_blank" rel="noopener"
                                       class="text-xs font-semibold text-indigo-600 hover:underline">Invoice ↓</a>
                                    <span v-if="r.fee_receipt?.status === 'approved'" class="text-xs font-semibold text-green-700 bg-green-50 px-2 py-0.5 rounded">Fee paid</span>
                                    <span v-else-if="r.fee_receipt?.status === 'uploaded'" class="text-xs text-yellow-700">Fee pending approval</span>
                                    <span v-else-if="r.fee_receipt?.status === 'rejected'" class="text-xs text-red-600">Fee rejected — re-upload</span>
                                </template>
                            </div>
                        </div>
                        <form v-if="program.fee_type === 'flat' && program.fee_amount && needsFeeUpload(r)"
                              @submit.prevent="uploadFee(r)" class="flex flex-wrap gap-2 items-center mt-2">
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png" multiple
                                   @change="e => feeFiles[r.id] = Array.from(e.target.files ?? [])"
                                   class="text-xs" required>
                            <input v-model="feeRefs[r.id]" class="field text-xs max-w-xs" placeholder="Transaction ref (optional)">
                            <button class="btn-primary text-xs !min-h-0 !px-2 !py-1">Upload proof</button>
                        </form>
                    </li>
                </ul>

                <div v-if="['published','ongoing'].includes(program.status) && program.allow_school_nomination !== false" class="space-y-3">
                    <!-- Bulk nominate -->
                    <div v-if="availableTeachers(program).length" class="rounded-lg border border-slate-100 bg-slate-50/60 p-3 space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-slate-700">Nominate teachers</p>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <button type="button" class="text-indigo-700 hover:underline" @click="selectAll(program)">Select all</button>
                                <button type="button" class="text-slate-500 hover:underline" @click="clearSelection(program)">Clear</button>
                            </div>
                        </div>
                        <div class="max-h-40 overflow-y-auto space-y-1">
                            <label v-for="t in availableTeachers(program)" :key="t.id"
                                   class="flex items-center gap-2 text-sm py-0.5 cursor-pointer">
                                <input type="checkbox" :value="t.id" v-model="selections[program.id]" class="rounded border-slate-300">
                                <span>{{ t.name }}</span>
                                <span v-if="t.category" class="text-xs text-slate-400">({{ t.category }})</span>
                                <span v-if="!t.is_verified" class="text-[10px] uppercase text-amber-700">unverified</span>
                            </label>
                        </div>
                        <div class="flex flex-wrap gap-2 items-center">
                            <button type="button" class="btn-primary text-sm"
                                    :disabled="!(selections[program.id]?.length)"
                                    @click="bulkRegister(program)">
                                Register selected ({{ selections[program.id]?.length || 0 }})
                            </button>
                            <button type="button" class="btn-secondary text-sm"
                                    @click="importOpen[program.id] = !importOpen[program.id]">
                                Import CSV/Excel
                            </button>
                        </div>
                    </div>
                    <p v-else class="text-xs text-gray-400">All eligible teachers are already nominated.</p>

                    <!-- Import panel -->
                    <div v-if="importOpen[program.id]" class="rounded-lg border border-indigo-100 bg-indigo-50/40 p-3 space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-slate-800">Import nominations</p>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    Match teachers by email, login_code, employee_code, or exact name. Already nominated rows are skipped.
                                </p>
                            </div>
                            <button type="button" class="text-slate-400 hover:text-slate-600 text-lg leading-none"
                                    @click="importOpen[program.id] = false">×</button>
                        </div>
                        <div class="flex flex-wrap gap-3 text-xs">
                            <a :href="`/school-admin/${school.id}/training/import/template`"
                               class="font-semibold text-indigo-800 hover:underline">↓ Excel template</a>
                            <a :href="`/school-admin/${school.id}/training/import/template?format=csv`"
                               class="font-semibold text-indigo-800 hover:underline">↓ CSV template</a>
                        </div>
                        <input type="file"
                               accept=".csv,.txt,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                               class="field text-xs"
                               @change="e => importFiles[program.id] = e.target.files[0]">
                        <div v-if="importResult?.errors?.length && lastImportProgramId === program.id"
                             class="rounded border border-red-100 bg-red-50 p-2 max-h-28 overflow-y-auto">
                            <p class="text-xs font-semibold text-red-700 mb-1">Row errors</p>
                            <ul class="text-xs text-red-600 space-y-0.5">
                                <li v-for="(err, i) in importResult.errors" :key="i">
                                    Row {{ err.row }}: {{ err.message }}
                                </li>
                            </ul>
                        </div>
                        <button type="button" class="btn-primary text-sm"
                                :disabled="!importFiles[program.id] || importing"
                                @click="submitImport(program)">
                            {{ importing ? 'Importing…' : 'Upload & nominate' }}
                        </button>
                    </div>
                </div>
                <p v-else-if="['published','ongoing'].includes(program.status) && program.allow_school_nomination === false"
                   class="text-xs text-amber-700 bg-amber-50 rounded px-2 py-1.5">
                    School nomination is disabled for this programme. Teachers may still register via QR or portal if enabled.
                </p>
            </div>
            <p v-if="!programs.length" class="text-center text-gray-400 py-8">No training programs available.</p>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({
    school: Object,
    programs: Array,
    registrations: Object,
    schoolFees: { type: Object, default: () => ({}) },
    eligibleByProgram: Object,
});
const feeFiles = reactive({});
const feeRefs  = reactive({});
const schoolFeeFiles = reactive({});
const schoolFeeRefs = reactive({});
const selections = reactive({});
const importOpen = reactive({});
const importFiles = reactive({});
const importing = ref(false);
const lastImportProgramId = ref(null);

for (const p of props.programs) {
    selections[p.id] = [];
    importOpen[p.id] = false;
}

const importResult = computed(() => usePage().props.flash?.importResult ?? null);

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function schoolFeeFor(program) {
    return props.schoolFees?.[program.id] ?? null;
}

function registeredIds(program) {
    return new Set((props.registrations[program.id] || []).map(r => r.teacher?.id).filter(Boolean));
}

function availableTeachers(program) {
    const taken = registeredIds(program);
    return (props.eligibleByProgram[program.id] || []).filter(t => !taken.has(t.id));
}

function selectAll(program) {
    selections[program.id] = availableTeachers(program).map(t => t.id);
}

function clearSelection(program) {
    selections[program.id] = [];
}

function needsFeeUpload(registration) {
    const s = registration.fee_receipt?.status;
    return !s || s === 'rejected';
}

function needsSchoolFeeUpload(program) {
    const sf = schoolFeeFor(program);
    if (!sf) return true;
    if (sf.status === 'approved' || (Number(sf.outstanding ?? 0) <= 0 && Number(sf.amount_paid ?? 0) > 0)) {
        return false;
    }
    const receiptStatus = sf.fee_receipt?.status;
    return !receiptStatus || receiptStatus === 'rejected' || sf.status === 'partial' || sf.status === 'pending' || sf.status === 'rejected';
}

function bulkRegister(program) {
    const ids = selections[program.id] || [];
    if (!ids.length) return;

    router.post(`/school-admin/${props.school.id}/training/bulk`, {
        program_id: program.id,
        teacher_ids: ids,
    }, {
        preserveScroll: true,
        onSuccess: () => { selections[program.id] = []; },
    });
}

function submitImport(program) {
    const file = importFiles[program.id];
    if (!file) return;

    const fd = new FormData();
    fd.append('program_id', program.id);
    fd.append('file', file);

    importing.value = true;
    lastImportProgramId.value = program.id;

    router.post(`/school-admin/${props.school.id}/training/import`, fd, {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => { importing.value = false; },
        onSuccess: () => { importFiles[program.id] = null; },
    });
}

function uploadFee(registration) {
    // feeFiles[registration.id] is now an array — up to 5 images for one payment,
    // submitted together as one receipt. See docs/FLOW_GAP_FIX_PLAN.md multi-image
    // upload feature.
    const files = feeFiles[registration.id];
    if (!files || !files.length) return;

    const fd = new FormData();
    files.forEach(f => fd.append('payment_proof[]', f));
    if (feeRefs[registration.id]) fd.append('transaction_ref', feeRefs[registration.id]);

    router.post(`/school-admin/${props.school.id}/training/${registration.id}/payment`, fd, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { feeFiles[registration.id] = null; feeRefs[registration.id] = ''; },
    });
}

function uploadSchoolFee(program) {
    // schoolFeeFiles[program.id] is now an array — see uploadFee() above.
    const files = schoolFeeFiles[program.id];
    if (!files || !files.length) return;

    const fd = new FormData();
    files.forEach(f => fd.append('payment_proof[]', f));
    if (schoolFeeRefs[program.id]) fd.append('transaction_ref', schoolFeeRefs[program.id]);

    router.post(`/school-admin/${props.school.id}/training/${program.id}/school-payment`, fd, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { schoolFeeFiles[program.id] = null; schoolFeeRefs[program.id] = ''; },
    });
}
</script>
