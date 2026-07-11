<template>
    <SchoolAdminLayout title="Teachers" :school="school" :show-header-title="false">
        <PageHeader title="Teachers" eyebrow="Staff"
            description="Manage teachers, subjects, and portal access credentials." />

        <PortalCredentialsBanner />

        <div class="space-y-5">
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-secondary" :class="mode === 'single' ? 'ring-2 ring-[#041525]/20' : ''" @click="mode = 'single'">Add one</button>
                <button type="button" class="btn-secondary" :class="mode === 'bulk' ? 'ring-2 ring-[#041525]/20' : ''" @click="mode = 'bulk'">Add many</button>
                <button type="button" class="btn-secondary" :class="mode === 'import' ? 'ring-2 ring-[#041525]/20' : ''" @click="mode = 'import'">Import CSV/Excel</button>
                <button type="button" class="btn-secondary" :class="mode === 'photos' ? 'ring-2 ring-[#041525]/20' : ''" @click="mode = 'photos'">Update photos (ZIP)</button>
                <Link :href="`/school-admin/${school.id}/imports`" class="btn-secondary">Import history</Link>
                <a :href="exportUrl('xlsx')" class="btn-secondary ml-auto">↓ Export (.xlsx)</a>
                <a :href="exportUrl('csv')" class="btn-secondary">↓ Export (.csv)</a>
            </div>

            <!-- Bulk add -->
            <form v-if="mode === 'bulk'" @submit.prevent="addMany" class="card space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="section-title text-base">Add multiple teachers</h3>
                    <button type="button" class="text-xs font-semibold text-[#041525] hover:underline" @click="addRow">+ Add row</button>
                </div>
                <div v-for="(row, i) in bulkForm.teachers" :key="i" class="rounded-xl border border-slate-100 p-3 space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="flex-1 space-y-3">
                            <FormGrid>
                                <FormField :label="`Teacher ${i + 1} name`" required>
                                    <input v-model="row.name" class="field" placeholder="Teacher name" required>
                                </FormField>
                                <FormField label="Email" required>
                                    <input v-model="row.email" type="email" class="field" placeholder="name@school.edu" required>
                                </FormField>
                                <FormField label="Mobile" required>
                                    <input v-model="row.mobile" class="field" placeholder="10-digit mobile" required>
                                </FormField>
                                <FormField label="Teacher category" required>
                                    <select v-model="row.teaching_type_id" class="field" required>
                                        <option :value="null">Select category</option>
                                        <option v-for="t in teachingTypes" :key="t.id" :value="t.id">{{ t.label }}</option>
                                    </select>
                                </FormField>
                            </FormGrid>
                            <FormField label="Subjects" required>
                                <SubjectPicker v-model="row.subject_ids" :subjects="subjects" />
                            </FormField>
                        </div>
                        <button v-if="bulkForm.teachers.length > 1" type="button"
                                class="text-xs font-semibold text-red-600 hover:text-red-700 mt-6" @click="removeRow(i)">Remove</button>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary" :disabled="bulkForm.processing">Add {{ bulkForm.teachers.length }} teacher(s)</button>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="bulkForm.create_logins" type="checkbox" class="rounded">
                    Create portal logins (username + temp password shown once)
                </label>
            </form>

            <!-- Photo ZIP -->
            <form v-if="mode === 'photos'" @submit.prevent="submitPhotoZip" class="card space-y-4">
                <h3 class="section-title text-base">Bulk update teacher photos</h3>
                <p class="text-sm text-slate-600">
                    Upload a ZIP of photos named by <strong>login code</strong> (e.g. <code class="font-mono">T-26-0001.jpg</code> or <code class="font-mono">T_26_0001.jpg</code>) or teacher email.
                </p>
                <input type="file" accept=".zip,application/zip" required @change="photoZipForm.zip = $event.target.files[0]" class="field">
                <p v-if="photoZipForm.errors.zip" class="text-xs text-red-600">{{ photoZipForm.errors.zip }}</p>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary" :disabled="photoZipForm.processing || !photoZipForm.zip">Upload photos</button>
                </div>
            </form>

            <!-- Import CSV -->
            <form v-if="mode === 'import'" @submit.prevent="importCsv" class="card space-y-4">
                <h3 class="section-title text-base">Import teachers from CSV or Excel</h3>
                <div class="rounded-xl border border-[#dbeafe] bg-[#f0f9ff] p-4 text-sm text-[#041525] space-y-2">
                    <p class="text-xs text-gray-600">Download the template, fill it in Excel, Google Sheets, or any spreadsheet app, then upload the file directly — .csv and .xlsx are both accepted. No row limit.</p>
                    <ul class="list-disc list-inside text-xs text-gray-600 space-y-0.5">
                        <li><strong>name</strong> — required</li>
                        <li><strong>email</strong> — required, must be unique</li>
                        <li><strong>mobile</strong> — required</li>
                        <li><strong>teaching_type</strong> — required, must match a category configured by your Sahodaya</li>
                        <li><strong>subjects</strong> — required, separate multiple with <span class="font-mono">;</span> (e.g. <span class="font-mono">Mathematics; Physics</span>)</li>
                        <li><strong>gender, designation, qualification, date_of_joining</strong> — optional</li>
                    </ul>
                    <p class="text-xs text-gray-600">Portal logins are created automatically for each imported teacher.</p>
                    <p class="text-xs font-semibold text-amber-700">All rows must be valid — if any row has an error, nothing is imported. Fix the file and re-upload.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a :href="`/school-admin/${school.id}/teachers/import/template`" class="text-sm font-semibold text-[#041525] hover:underline">↓ Download Excel template (.xlsx)</a>
                    <a :href="`/school-admin/${school.id}/teachers/import/template?format=csv`" class="text-sm font-semibold text-[#041525] hover:underline">↓ Download CSV template</a>
                    <Link :href="`/school-admin/${school.id}/imports`" class="text-sm font-semibold text-[#041525] hover:underline">View import history</Link>
                </div>
                <input type="file" accept=".csv,.txt,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" @change="importForm.file = $event.target.files[0]" class="field">
                <p v-if="importForm.errors.file" class="text-xs text-red-600">{{ importForm.errors.file }}</p>
                <div v-if="importErrors.length" class="rounded-lg border border-red-100 bg-red-50 p-3 max-h-32 overflow-y-auto">
                    <p class="text-xs font-semibold text-red-700 mb-1">Import rejected — fix these and re-upload</p>
                    <ul class="text-xs text-red-600 space-y-0.5">
                        <li v-for="(err, i) in importErrors" :key="i">Row {{ err.row }}: {{ err.message }}</li>
                    </ul>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary" :disabled="importForm.processing || !importForm.file">Import</button>
                </div>
            </form>

            <form v-if="mode === 'single'" @submit.prevent="addTeacher" class="card space-y-4">
                <h3 class="section-title text-base">Add teacher</h3>
                <FormGrid>
                    <FormField label="Full name" required :error="form.errors.name">
                        <input v-model="form.name" class="field" placeholder="Teacher name" required>
                    </FormField>
                    <FormField label="Email" required :error="form.errors.email">
                        <input v-model="form.email" type="email" class="field" placeholder="name@school.edu" required>
                    </FormField>
                    <FormField label="Mobile" required :error="form.errors.mobile">
                        <input v-model="form.mobile" class="field" placeholder="10-digit mobile" required>
                    </FormField>
                    <FormField label="Photo (ID document)" required :error="form.errors.photo" class-extra="sm:col-span-2">
                        <input type="file" accept="image/*" required class="field" @change="form.photo = $event.target.files[0]">
                    </FormField>
                    <FormField label="Teacher category" required :error="form.errors.teaching_type_id">
                        <select v-model="form.teaching_type_id" class="field" required>
                            <option :value="null">Select category</option>
                            <option v-for="t in teachingTypes" :key="t.id" :value="t.id">{{ t.code }} — {{ t.label }}</option>
                        </select>
                    </FormField>
                    <FormField label="Designation" :error="form.errors.designation_id">
                        <select v-model="form.designation_id" class="field">
                            <option :value="null">Select designation</option>
                            <option v-for="d in designations" :key="d.id" :value="d.id">{{ d.label }}</option>
                        </select>
                    </FormField>
                    <FormField label="Gender" :error="form.errors.gender">
                        <select v-model="form.gender" class="field">
                            <option value="">Select gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </FormField>
                </FormGrid>
                <FormField label="Subjects" hint="Select at least one subject" required :error="form.errors.subject_ids">
                    <div class="flex flex-wrap gap-2 pt-1">
                        <label v-for="s in subjects" :key="s.id"
                               class="inline-flex items-center gap-1.5 text-xs border border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 hover:bg-white transition">
                            <input type="checkbox" :value="s.id" v-model="form.subject_ids" class="rounded"> {{ s.label }}
                        </label>
                    </div>
                </FormField>
                <label class="flex items-start gap-2 text-sm text-slate-600 rounded-xl border border-slate-100 bg-slate-50/80 p-3">
                    <input type="checkbox" v-model="form.create_login" class="rounded mt-0.5">
                    <span>
                        Create portal login
                        <span class="block text-xs text-slate-500 mt-0.5">
                            Username (login code) and temp password are shown once after save. You can view them again from the teacher list until the teacher changes their password.
                        </span>
                    </span>
                </label>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary" :disabled="form.processing">Add teacher</button>
                </div>
            </form>

            <form class="card !p-4 flex flex-wrap gap-3 items-end" @submit.prevent="applyFilters">
                <FormField label="Teacher category" class-extra="mb-0">
                    <select v-model="f.teaching_type_id" class="field text-sm">
                        <option value="">All categories</option>
                        <option v-for="t in teachingTypes" :key="t.id" :value="t.id">{{ t.label }}</option>
                    </select>
                </FormField>
                <FormField label="Status" class-extra="mb-0">
                    <select v-model="f.status" class="field text-sm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="all">All</option>
                    </select>
                </FormField>
                <FormField label="Verification" class-extra="mb-0">
                    <select v-model="f.verification" class="field text-sm">
                        <option value="all">All</option>
                        <option value="unverified">Pending</option>
                        <option value="verified">Verified</option>
                    </select>
                </FormField>
                <FormField label="Search" class-extra="mb-0">
                    <input v-model="f.search" type="search" class="field text-sm" placeholder="Name, email, mobile, login code">
                </FormField>
                <button type="submit" class="btn-secondary text-sm">Apply</button>
            </form>

            <div class="card card--flush overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Photo</th>
                            <th class="p-3">Name</th>
                            <th class="p-3">Category</th>
                            <th class="p-3">Subjects</th>
                            <th class="p-3">Verified</th>
                            <th class="p-3">Portal</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in teachers.data" :key="t.id" class="border-t">
                            <td class="p-3">
                                <img v-if="t.photo_url" :src="t.photo_url" :alt="t.name" class="h-10 w-10 rounded-full object-cover border">
                                <span v-else class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xs font-bold">{{ initials(t.name) }}</span>
                            </td>
                            <td class="p-3 font-medium">
                                {{ t.name }}
                                <p class="text-xs text-slate-500 font-mono">{{ t.login_code || t.email }}</p>
                                <p v-if="t.employee_code" class="text-xs text-slate-400 font-mono">{{ t.employee_code }}</p>
                            </td>
                            <td class="p-3 text-gray-600">{{ t.teaching_type || '—' }}</td>
                            <td class="p-3 text-xs text-gray-600">{{ (t.subject_labels || []).join(', ') || t.subject || '—' }}</td>
                            <td class="p-3"><span class="text-xs" :class="t.is_verified ? 'text-emerald-700' : 'text-amber-700'">{{ t.is_verified ? 'Verified' : 'Pending' }}</span></td>
                            <td class="p-3">
                                <button v-if="t.user_id" type="button" class="text-xs text-green-700 font-semibold" @click="openLogin(t)">Credentials</button>
                                <button v-else type="button" class="text-xs text-indigo-700 font-semibold" @click="openProvision(t)">Create login</button>
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <button type="button" class="text-indigo-600 text-xs font-semibold mr-3" @click="openEdit(t)">Edit</button>
                                <button type="button" @click="remove(t)" class="text-red-600 text-xs font-semibold">Deactivate</button>
                            </td>
                        </tr>
                        <tr v-if="!teachers.data.length"><td colspan="7" class="p-6 text-center text-gray-400">No teachers match the filters.</td></tr>
                    </tbody>
                </table>
            </div>

            <div v-if="teachers.links?.length > 3" class="flex justify-center gap-1">
                <Link v-for="link in teachers.links" :key="link.label"
                      :href="link.url || '#'"
                      class="px-3 py-1 rounded text-xs font-medium"
                      :class="link.active ? 'bg-[#041525] text-white' : (link.url ? 'text-[#041525] hover:bg-gray-100' : 'text-gray-300 pointer-events-none')"
                      v-html="link.label" />
            </div>
        </div>

        <div v-if="editingTeacher" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60" @click="closeEdit"></div>
            <form @submit.prevent="saveEdit" class="relative modal-shell max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <h3 class="font-bold">Edit — {{ editingTeacher.name }}</h3>
                <input v-model="editForm.name" class="field" required>
                <input v-model="editForm.email" type="email" class="field" required>
                <input v-model="editForm.mobile" class="field" placeholder="Mobile">
                <select v-model="editForm.teaching_type_id" class="field">
                    <option :value="null">Teacher category</option>
                    <option v-for="tt in teachingTypes" :key="tt.id" :value="tt.id">{{ tt.label }}</option>
                </select>
                <select v-model="editForm.designation_id" class="field">
                    <option :value="null">Designation</option>
                    <option v-for="d in designations" :key="d.id" :value="d.id">{{ d.label }}</option>
                </select>
                <div class="flex flex-wrap gap-2">
                    <label v-for="s in subjects" :key="s.id" class="text-xs border rounded px-2 py-1">
                        <input type="checkbox" :value="s.id" v-model="editForm.subject_ids"> {{ s.label }}
                    </label>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="closeEdit" class="text-sm text-gray-500">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="editForm.processing">Save</button>
                </div>
            </form>
        </div>

        <div v-if="provisionTeacher" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60" @click="closeProvision"></div>
            <form @submit.prevent="submitProvision" class="relative modal-shell max-w-md w-full p-6 space-y-4">
                <h3 class="font-bold">Portal login — {{ provisionTeacher.name }}</h3>
                <p class="text-sm text-gray-600">
                    Creates a portal account with an auto-generated temp password (shown once after you confirm).
                </p>
                <input v-model="provisionForm.email" type="email" class="field" required placeholder="Teacher email">
                <div class="flex justify-end gap-2">
                    <button type="button" @click="closeProvision" class="text-sm text-gray-500">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="provisionForm.processing">Create login</button>
                </div>
            </form>
        </div>

        <div v-if="loginTeacher" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60" @click="closeLogin"></div>
            <div class="relative modal-shell max-w-md w-full p-6 space-y-4">
                <h3 class="font-bold">Teacher portal login — {{ loginTeacher.name }}</h3>
                <p class="text-sm text-gray-600">Share these credentials with the teacher. They sign in at the participant portal.</p>
                <div class="rounded-lg border bg-gray-50 p-3 text-sm space-y-2">
                    <p><span class="text-gray-500">Username:</span> <span class="font-mono font-semibold">{{ loginTeacher.portal_username || loginTeacher.login_code || '—' }}</span></p>
                    <p v-if="loginTeacher.portal_email"><span class="text-gray-500">Email:</span> <span class="font-mono">{{ loginTeacher.portal_email }}</span></p>
                    <p v-if="visiblePassword">
                        <span class="text-gray-500">Password:</span>
                        <span class="font-mono font-bold text-emerald-800">{{ visiblePassword }}</span>
                    </p>
                    <p v-else class="text-xs text-gray-500">Password not stored — reset to generate a new temp password.</p>
                </div>
                <div class="flex justify-end gap-2 flex-wrap">
                    <button type="button" @click="closeLogin" class="text-sm text-gray-500">Close</button>
                    <button type="button" class="btn-secondary text-sm" :disabled="resetForm.processing" @click="resetPortalPassword">
                        Reset password
                    </button>
                    <a href="/portal/login" target="_blank" rel="noopener" class="btn-primary text-sm">Open portal login ↗</a>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FormField from '@/Components/ui/FormField.vue';
import FormGrid from '@/Components/ui/FormGrid.vue';
import PortalCredentialsBanner from '@/Components/school/PortalCredentialsBanner.vue';
import SubjectPicker from '@/Components/school/SubjectPicker.vue';
import { useScrollToFirstError } from '@/composables/useScrollToFirstError.js';

const props = defineProps({
    school: Object,
    teachers: Object,
    filters: { type: Object, default: () => ({}) },
    teachingTypes: Array,
    designations: Array,
    subjects: Array,
});

const importErrors = computed(() => usePage().props.flash?.importErrors ?? []);

const f = reactive({
    teaching_type_id: '',
    status: 'active',
    verification: 'all',
    search: '',
    ...props.filters,
});

function applyFilters() {
    router.get(`/school-admin/${props.school.id}/teachers`, f, { preserveState: true, preserveScroll: true });
}

function exportUrl(format) {
    const params = new URLSearchParams({ ...f, format });
    return `/school-admin/${props.school.id}/teachers/export?${params.toString()}`;
}

const editingTeacher = ref(null);
const provisionTeacher = ref(null);
const loginTeacher = ref(null);
const displayPassword = ref(null);

const visiblePassword = computed(() => displayPassword.value ?? loginTeacher.value?.portal_password ?? null);
const mode = ref('single');

const form = useForm({
    name: '', email: '', mobile: '', gender: '', teaching_type_id: null, designation_id: null,
    subject_ids: [], photo: null, create_login: true,
});

function newRow() {
    return { name: '', email: '', mobile: '', teaching_type_id: null, subject_ids: [] };
}
const bulkForm = useForm({ teachers: [newRow(), newRow(), newRow()], create_logins: true });
const importForm = useForm({ file: null });
const photoZipForm = useForm({ zip: null });

function addRow() { bulkForm.teachers.push(newRow()); }
function removeRow(i) { bulkForm.teachers.splice(i, 1); }

function addMany() {
    bulkForm
        .transform((data) => ({ teachers: data.teachers.filter((r) => r.name && r.name.trim() !== '') }))
        .post(`/school-admin/${props.school.id}/teachers/bulk`, {
            preserveScroll: true,
            onSuccess: () => { bulkForm.teachers = [newRow(), newRow(), newRow()]; },
        });
}

function importCsv() {
    importForm.post(`/school-admin/${props.school.id}/teachers/import`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { importForm.reset('file'); },
    });
}

function submitPhotoZip() {
    photoZipForm.post(`/school-admin/${props.school.id}/teachers/photos-zip`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => photoZipForm.reset('zip'),
    });
}

const editForm = useForm({
    name: '', email: '', mobile: '', teaching_type_id: null, designation_id: null, subject_ids: [],
});

const provisionForm = useForm({ email: '' });
const resetForm = useForm({});

const { scrollToFirstError } = useScrollToFirstError();

function addTeacher() {
    form.post(`/school-admin/${props.school.id}/teachers`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset('name', 'email', 'mobile', 'gender', 'teaching_type_id', 'designation_id', 'subject_ids', 'photo'),
        onError: () => scrollToFirstError(form.errors),
    });
}

function openEdit(teacher) {
    editingTeacher.value = teacher;
    editForm.name = teacher.name;
    editForm.email = teacher.email ?? '';
    editForm.mobile = teacher.mobile ?? '';
    editForm.teaching_type_id = teacher.teaching_type_id ?? null;
    editForm.designation_id = teacher.designation_id ?? null;
    editForm.subject_ids = [...(teacher.subject_ids || [])];
}

function closeEdit() { editingTeacher.value = null; }
function saveEdit() {
    editForm.put(`/school-admin/${props.school.id}/teachers/${editingTeacher.value.id}`, {
        preserveScroll: true, onSuccess: () => { editingTeacher.value = null; },
    });
}

function openProvision(t) {
    provisionTeacher.value = t;
    provisionForm.email = t.email ?? '';
    provisionForm.clearErrors();
}
function closeProvision() { provisionTeacher.value = null; }
function submitProvision() {
    provisionForm.post(`/school-admin/${props.school.id}/teachers/${provisionTeacher.value.id}/provision-portal`, {
        preserveScroll: true,
        onSuccess: () => { provisionTeacher.value = null; },
    });
}

function openLogin(t) {
    loginTeacher.value = t;
    displayPassword.value = t.portal_password ?? null;
}
function closeLogin() {
    loginTeacher.value = null;
    displayPassword.value = null;
}
function resetPortalPassword() {
    if (!loginTeacher.value || !confirm(`Reset portal password for ${loginTeacher.value.name}?`)) return;
    resetForm.post(`/school-admin/${props.school.id}/teachers/${loginTeacher.value.id}/reset-portal-password`, {
        preserveScroll: true,
        onSuccess: () => {
            const creds = usePage().props.flash?.newCredentials;
            if (creds?.password) {
                displayPassword.value = creds.password;
            }
            router.reload({ only: ['teachers'], preserveScroll: true });
        },
    });
}

function remove(teacher) {
    if (!confirm(`Deactivate ${teacher.name}?`)) return;
    router.delete(`/school-admin/${props.school.id}/teachers/${teacher.id}`, { preserveScroll: true });
}

function initials(name) {
    return (name || '?').split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase() ?? '').join('') || '?';
}
</script>
