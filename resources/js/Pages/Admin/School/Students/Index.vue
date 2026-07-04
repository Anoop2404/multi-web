<template>
    <SchoolAdminLayout title="Students" :school="school" :show-header-title="false">
        <PageHeader
            title="Students"
            eyebrow="Records"
            :description="`${students.total ?? 0} ${(students.total ?? 0) === 1 ? 'student' : 'students'}${hasActiveFilters ? ' · filtered' : ''}`"
        >
            <template #actions>
                <Link v-if="isLocked" :href="`/school-admin/${school.id}/students/change-requests`"
                      class="btn-secondary">
                    Change requests{{ pendingChangeRequests ? ` (${pendingChangeRequests})` : '' }}
                </Link>
                <button v-if="isLocked" type="button" @click="openCreateRequestModal" class="btn-primary">
                    Request new student
                </button>
                <button v-if="!isLocked" type="button" @click="openImportModal" class="btn-secondary">
                    Import CSV
                </button>
                <Link v-if="!isLocked" :href="`/school-admin/${school.id}/students/bulk`"
                      :class="['btn-secondary', !schoolClasses.length ? 'pointer-events-none opacity-50' : '']"
                      :title="!schoolClasses.length ? 'Classes are configured by your Sahodaya' : ''">
                    Bulk add
                </Link>
                <Link v-if="!isLocked" :href="`/school-admin/${school.id}/students/create`"
                      :class="['btn-primary', !schoolClasses.length ? 'pointer-events-none opacity-50' : '']"
                      :title="!schoolClasses.length ? 'Classes are configured by your Sahodaya' : ''">
                    + Add student
                </Link>
                <button v-if="studentsWithoutPortal > 0" type="button"
                        :disabled="bulkProvisionForm.processing"
                        class="btn-secondary text-sm"
                        @click="confirmBulkProvision">
                    {{ bulkProvisionForm.processing ? 'Creating…' : `Bulk create logins (${studentsWithoutPortal})` }}
                </button>
            </template>
        </PageHeader>

        <div class="space-y-4">
            <div v-if="!school.school_prefix" class="notice-banner notice-banner--warning">
                Set your
                <Link :href="`/school-admin/${school.id}/setup/code`" class="link-brand">school code</Link>
                before managing students.
            </div>

            <div v-else-if="!schoolClasses.length" class="notice-banner notice-banner--warning">
                No classes are configured for your Sahodaya yet. Please contact your Sahodaya admin to set up the class master under Configuration.
            </div>

            <div v-if="isLocked" class="notice-banner notice-banner--warning">
                {{ studentEditLock.message }}
                <Link :href="`/school-admin/${school.id}/students/change-requests`" class="link-brand font-semibold ml-1">
                    View change requests{{ pendingChangeRequests ? ` (${pendingChangeRequests} pending)` : '' }} →
                </Link>
            </div>

            <SahodayaDataTable
                :columns="columns"
                :links="students.links"
                :meta="{ from: students.from, to: students.to, total: students.total }"
                :sort="filters.sort"
                :dir="filters.dir"
                :has-rows="!!students.data?.length"
                empty="No students found."
                @sort="toggleSort"
            >
                <template #toolbar>
                    <div class="space-y-3">
                        <FormGrid class-extra="sm:grid-cols-2 lg:grid-cols-4 items-end">
                            <FormField label="Category">
                                <select v-model="filterForm.class_category_id" @change="onCategoryChange" class="field">
                                    <option :value="null">All categories</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.label }}</option>
                                </select>
                            </FormField>
                            <FormField label="Class">
                                <select v-model="filterForm.school_class_id" class="field">
                                    <option :value="null">All classes</option>
                                    <option v-for="c in filteredClasses" :key="c.id" :value="c.id">Class {{ c.name }}</option>
                                </select>
                            </FormField>
                            <FormField label="Status">
                                <select v-model="filterForm.status" class="field">
                                    <option value="active">Active</option>
                                    <option value="all">All statuses</option>
                                    <option value="transferred">Transferred</option>
                                    <option value="graduated">Graduated</option>
                                    <option value="withdrawn">Withdrawn</option>
                                </select>
                            </FormField>
                            <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-1">
                                <button type="button" @click="applyFilters" class="btn-primary flex-1">Apply</button>
                                <button v-if="hasActiveFilters" type="button" @click="clearFilters" class="btn-ghost">Clear</button>
                            </div>
                        </FormGrid>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                            <FormField label="Search" class-extra="flex-1 max-w-md">
                                <input v-model="filterForm.search" type="search" placeholder="Name, reg no, email, roll no…"
                                       class="field" @keyup.enter="applyFilters">
                            </FormField>
                            <button type="button" @click="applyFilters" class="btn-secondary sm:mb-0.5">Search</button>
                        </div>
                    </div>
                </template>

                <tr v-for="student in students.data" :key="student.id" class="hover:bg-gray-50/80">
                    <td class="px-4 py-3 w-14">
                        <button type="button" @click="openEditModal(student)"
                                class="relative w-10 h-10 rounded-full overflow-hidden border border-gray-200 bg-gray-100 flex items-center justify-center hover:ring-2 hover:ring-[#0f3d7a]/20 transition"
                                title="Edit student">
                            <img v-if="student.photo_url" :src="student.photo_url" :alt="student.name"
                                 class="w-full h-full object-cover">
                            <span v-else class="text-xs text-gray-400 font-semibold">{{ initials(student.name) }}</span>
                        </button>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ student.name }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ student.reg_no || student.admission_number || '—' }}</td>
                    <td class="px-4 py-3 text-xs text-gray-600 capitalize">{{ formatGender(student.gender) }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ formatDate(student.dob) }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ student.parent_email || '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ student.school_class?.name || '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium capitalize"
                              :class="statusClass(student.status)">{{ student.status }}</span>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <button type="button" @click="openEditModal(student)"
                                class="link-brand text-xs mr-3">{{ isLocked ? 'Request change' : 'Edit' }}</button>
                        <button v-if="!student.user_id" type="button" @click="openPortalModal(student)"
                                class="link-brand text-xs mr-3">Portal</button>
                        <button v-else type="button" @click="openLoginModal(student)"
                                class="link-brand text-xs mr-3">Login</button>
                        <button v-if="!isLocked" type="button" @click="remove(student)"
                                class="text-xs text-red-400 hover:text-red-600 hover:underline">Remove</button>
                    </td>
                </tr>
            </SahodayaDataTable>

            <p v-if="!students.data?.length && school.school_prefix && schoolClasses.length && !isLocked"
               class="text-center text-sm text-gray-500 -mt-2">
                <Link :href="`/school-admin/${school.id}/students/create`" class="link-brand font-semibold hover:underline">
                    Add your first student
                </Link>
            </p>
        </div>

        <!-- Edit student modal -->
        <div v-if="showEdit && editingStudent" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="closeEditModal"></div>
            <div class="relative modal-shell max-w-md">
                <div class="modal-head">
                    <div>
                        <h3 class="font-bold text-[#041525]">{{ isLocked ? 'Request student change' : 'Edit Student' }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ isLocked
                                ? 'Proposed changes are sent to Sahodaya for approval before they take effect.'
                                : 'Update profile, class, gender, and contact details' }}
                        </p>
                    </div>
                    <button type="button" @click="closeEditModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>

                <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                    <ProfilePhotoCropper v-model="editPhotoFile" :existing-url="editingStudent.photo_url" />

                    <div>
                        <label class="form-label mb-1.5">Reg. No.</label>
                        <input :value="editingStudent.admission_number || '—'" type="text" readonly
                               class="field bg-gray-50 text-gray-500 font-mono cursor-not-allowed">
                    </div>

                    <div>
                        <label class="form-label mb-1.5">Full name *</label>
                        <input v-model="editForm.name" type="text" required
                               class="field">
                        <p v-if="editForm.errors.name" class="text-xs text-red-500 mt-1">{{ editForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="form-label mb-1.5">Class *</label>
                        <select v-model="editForm.school_class_id" required
                                class="field">
                            <option value="">Select class</option>
                            <option v-for="c in schoolClassesSorted" :key="c.id" :value="c.id">
                                {{ formatClassOption(c) }}
                            </option>
                        </select>
                        <p v-if="editForm.errors.school_class_id" class="text-xs text-red-500 mt-1">{{ editForm.errors.school_class_id }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label mb-1.5">Gender *</label>
                            <select v-model="editForm.gender" required
                                    class="field">
                                <option value="">Select gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                            <p v-if="editForm.errors.gender" class="text-xs text-red-500 mt-1">{{ editForm.errors.gender }}</p>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Date of birth *</label>
                            <input v-model="editForm.dob" type="date" required
                                   class="field">
                            <p v-if="editForm.errors.dob" class="text-xs text-red-500 mt-1">{{ editForm.errors.dob }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="form-label mb-1.5">Email <span class="font-normal text-gray-400">(optional)</span></label>
                        <input v-model="editForm.parent_email" type="email" placeholder="student@example.com"
                               class="field">
                        <p v-if="editForm.errors.parent_email" class="text-xs text-red-500 mt-1">{{ editForm.errors.parent_email }}</p>
                    </div>

                    <div v-if="isLocked">
                        <label class="form-label mb-1.5">Reason for change *</label>
                        <textarea v-model="editForm.reason" rows="3" required
                                  placeholder="Explain why this update is needed (e.g. typo in name, class correction)…"
                                  class="field"></textarea>
                        <p v-if="editForm.errors.reason" class="text-xs text-red-500 mt-1">{{ editForm.errors.reason }}</p>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="closeEditModal" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                        <button type="submit" :disabled="editForm.processing"
                                class="btn-primary disabled:opacity-50">
                            {{ isLocked ? 'Submit change request' : 'Save changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Request new student when locked -->
        <div v-if="showCreateRequest && isLocked" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="closeCreateRequestModal"></div>
            <div class="relative modal-shell max-w-md">
                <div class="modal-head">
                    <div>
                        <h3 class="font-bold text-[#041525]">Request new student</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Sahodaya will review and create the record if approved.</p>
                    </div>
                    <button type="button" @click="closeCreateRequestModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form @submit.prevent="submitCreateRequest" class="p-6 space-y-4">
                    <ProfilePhotoCropper v-model="createPhotoFile" />
                    <div>
                        <label class="form-label mb-1.5">Full name *</label>
                        <input v-model="createForm.name" type="text" required class="field">
                    </div>
                    <div>
                        <label class="form-label mb-1.5">Class *</label>
                        <select v-model="createForm.school_class_id" required class="field">
                            <option value="">Select class</option>
                            <option v-for="c in schoolClassesSorted" :key="c.id" :value="c.id">{{ formatClassOption(c) }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label mb-1.5">Gender *</label>
                            <select v-model="createForm.gender" required class="field">
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Date of birth *</label>
                            <input v-model="createForm.dob" type="date" required class="field">
                        </div>
                    </div>
                    <div>
                        <label class="form-label mb-1.5">Reason for adding *</label>
                        <textarea v-model="createForm.reason" rows="3" required class="field"
                                  placeholder="Explain why this student must be added after the lock date…"></textarea>
                        <p v-if="createForm.errors.reason" class="text-xs text-red-500 mt-1">{{ createForm.errors.reason }}</p>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="closeCreateRequestModal" class="text-sm text-gray-500">Cancel</button>
                        <button type="submit" :disabled="createForm.processing" class="btn-primary">Submit request</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Portal login modal -->
        <div v-if="showPortal && portalStudent" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="closePortalModal"></div>
            <div class="relative modal-shell max-w-md p-6 space-y-4">
                <h3 class="font-bold">Portal login — {{ portalStudent.name }}</h3>
                <input v-model="portalForm.email" type="email" placeholder="Email" class="w-full border rounded-lg px-3 py-2 text-sm" required>
                <input v-model="portalForm.password" type="password" placeholder="Password (min 8)" class="w-full border rounded-lg px-3 py-2 text-sm" required>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="closePortalModal" class="text-sm text-gray-500">Cancel</button>
                    <button type="button" @click="submitPortal" :disabled="portalForm.processing"
                            class="btn-primary">Create login</button>
                </div>
            </div>
        </div>

        <!-- Existing portal login info -->
        <div v-if="showLogin && loginStudent" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="closeLoginModal"></div>
            <div class="relative modal-shell max-w-md p-6 space-y-4">
                <h3 class="font-bold">Student portal login — {{ loginStudent.name }}</h3>
                <p class="text-sm text-gray-600">Share these credentials with the student. They sign in at the participant portal.</p>
                <div class="rounded-lg border bg-gray-50 p-3 text-sm space-y-2">
                    <p><span class="text-gray-500">Username / email:</span> <span class="font-mono font-semibold">{{ loginStudent.portal_email || '—' }}</span></p>
                    <p class="text-xs text-gray-500">Password was set when the portal account was created. Use reset flow if forgotten.</p>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="closeLoginModal" class="text-sm text-gray-500">Close</button>
                    <a href="/portal/login" target="_blank" rel="noopener" class="btn-primary text-sm">Open portal login ↗</a>
                </div>
            </div>
        </div>

        <!-- Import CSV modal -->
        <div v-if="showImport" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="closeImportModal"></div>
            <div class="relative modal-shell max-w-lg max-h-[90vh] flex flex-col">
                <div class="modal-head shrink-0">
                    <div>
                        <h3 class="font-bold text-[#041525]">Import Students</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Bulk upload from CSV (opens in Excel)</p>
                    </div>
                    <button type="button" @click="closeImportModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>

                <div class="p-6 space-y-4 overflow-y-auto">
                    <div class="bg-[#f0f9ff] border border-[#dbeafe] rounded-xl p-4 text-sm text-[#041525] space-y-2">
                        <p class="font-semibold">Required columns</p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1 text-xs">
                            <li><strong>full_name</strong> — student’s full name</li>
                            <li><strong>class_name</strong> — must match your Sahodaya class list exactly</li>
                            <li><strong>email</strong> — optional contact email</li>
                        </ul>
                    </div>

                    <div v-if="classNames.length" class="text-xs text-gray-500">
                        <span class="font-semibold text-gray-600">Your class names:</span>
                        {{ classNames.join(', ') }}
                    </div>
                    <div v-else class="text-sm text-amber-800">
                        Contact your Sahodaya admin to configure classes before importing.
                    </div>

                    <a :href="`/school-admin/${school.id}/students/import/template`"
                       class="inline-flex items-center gap-2 text-sm font-semibold text-[#0f3d7a] hover:underline">
                        ↓ Download sample CSV (Excel compatible)
                    </a>

                    <form @submit.prevent="submitImport" class="space-y-4">
                        <div>
                            <label class="form-label mb-1.5">CSV file *</label>
                            <input type="file" accept=".csv,.txt,text/csv" required @change="onImportFile"
                                   class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#f0f9ff] file:text-[#0f3d7a]">
                            <p class="text-xs text-gray-400 mt-1">Save your Excel sheet as CSV before uploading.</p>
                            <p v-if="importForm.errors.file" class="text-xs text-red-500 mt-1">{{ importForm.errors.file }}</p>
                        </div>

                        <div v-if="importResult?.errors?.length" class="bg-red-50 border border-red-100 rounded-lg p-3 space-y-1 max-h-36 overflow-y-auto">
                            <p class="text-xs font-semibold text-red-700">Import issues</p>
                            <ul class="text-xs text-red-600 space-y-0.5">
                                <li v-for="(err, i) in importResult.errors" :key="i">
                                    Row {{ err.row }}: {{ err.message }}
                                </li>
                            </ul>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-1">
                            <button type="button" @click="closeImportModal" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                            <button type="submit" :disabled="importForm.processing || !classNames.length"
                                    class="btn-primary disabled:opacity-50">
                                Import students
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ProfilePhotoCropper from '@/Components/school/ProfilePhotoCropper.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    school:     Object,
    students:   Object,
    filters:    Object,
    categories: { type: Array, default: () => [] },
    classes:    { type: Array, default: () => [] },
    classNames: { type: Array, default: () => [] },
    studentEditLock: { type: Object, default: () => ({ locked: false }) },
    pendingChangeRequests: { type: Number, default: 0 },
    studentsWithoutPortal: { type: Number, default: 0 },
});

const isLocked = computed(() => !!props.studentEditLock?.locked);

const page = usePage();
const showImport = ref(false);
const showEdit = ref(false);
const showCreateRequest = ref(false);
const showPortal = ref(false);
const showLogin = ref(false);
const portalStudent = ref(null);
const loginStudent = ref(null);
const editingStudent = ref(null);
const editPhotoFile = ref(null);
const createPhotoFile = ref(null);

const importResult = computed(() => page.props.flash?.importResult ?? null);

const columns = [
    { key: 'photo',        label: 'Photo',  sortable: false, class: 'w-14' },
    { key: 'name',         label: 'Name',   sortable: true },
    { key: 'reg_no',       label: 'Reg No', sortable: false },
    { key: 'gender',       label: 'Gender', sortable: false },
    { key: 'dob',          label: 'DOB',    sortable: false },
    { key: 'parent_email', label: 'Email',  sortable: true },
    { key: 'class',        label: 'Class',  sortable: true },
    { key: 'status',       label: 'Status', sortable: true },
    { key: 'actions',      label: '',       sortable: false, align: 'right' },
];

const filterForm = reactive({
    class_category_id: props.filters?.class_category_id ?? null,
    school_class_id:   props.filters?.school_class_id ?? null,
    status:            props.filters?.status ?? 'active',
    search:            props.filters?.search ?? '',
});

const portalForm = useForm({ email: '', password: '' });

const importForm = useForm({ file: null });

const editForm = useForm({
    school_class_id: '',
    name:            '',
    gender:          '',
    dob:             '',
    parent_email:    '',
    photo:           null,
    reason:          '',
});

const createForm = useForm({
    school_class_id: '',
    name:            '',
    gender:          '',
    dob:             '',
    reason:          '',
    photo:           null,
});

const schoolClasses = computed(() =>
    props.classes.filter(c => c.is_active !== false),
);

const schoolClassesSorted = computed(() =>
    [...schoolClasses.value].sort((a, b) =>
        (a.display_order ?? 0) - (b.display_order ?? 0)
        || String(a.name).localeCompare(String(b.name), undefined, { numeric: true }),
    ),
);

const filteredClasses = computed(() => {
    if (!filterForm.class_category_id) return schoolClasses.value;
    return schoolClasses.value.filter(c => Number(c.class_category_id) === Number(filterForm.class_category_id));
});

const hasActiveFilters = computed(() =>
    filterForm.class_category_id != null
    || filterForm.school_class_id != null
    || filterForm.status !== 'active'
    || !!filterForm.search
);

watch(() => props.filters, (f) => {
    if (!f) return;
    filterForm.class_category_id = f.class_category_id ?? null;
    filterForm.school_class_id   = f.school_class_id ?? null;
    filterForm.status            = f.status ?? 'active';
    filterForm.search            = f.search ?? '';
}, { deep: true });

function formatClassOption(schoolClass) {
    const cat = props.categories.find(c => Number(c.id) === Number(schoolClass.class_category_id));
    return cat ? `Class ${schoolClass.name} (${cat.label})` : `Class ${schoolClass.name}`;
}

function classesInCategory(categoryId) {
    return schoolClasses.value.filter(c => Number(c.class_category_id) === Number(categoryId));
}

function listParams(overrides = {}) {
    return {
        class_category_id: props.filters?.class_category_id ?? null,
        school_class_id:   props.filters?.school_class_id ?? null,
        status:            props.filters?.status ?? 'active',
        search:            props.filters?.search ?? '',
        sort:              props.filters?.sort ?? 'name',
        dir:               props.filters?.dir ?? 'asc',
        ...overrides,
    };
}

function applyFilters() {
    router.get(`/school-admin/${props.school.id}/students`, {
        class_category_id: filterForm.class_category_id,
        school_class_id:   filterForm.school_class_id,
        status:            filterForm.status,
        search:            filterForm.search,
        sort:              props.filters?.sort ?? 'name',
        dir:               props.filters?.dir ?? 'asc',
    }, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    filterForm.class_category_id = null;
    filterForm.school_class_id   = null;
    filterForm.status            = 'active';
    filterForm.search            = '';
    router.get(`/school-admin/${props.school.id}/students`, listParams({
        class_category_id: null,
        school_class_id:   null,
        status:            'active',
        search:            '',
    }), { preserveState: true, preserveScroll: true });
}

function toggleSort(key) {
    const sortable = { name: 'name', parent_email: 'parent_email', class: 'class', status: 'status' };
    const sortKey = sortable[key];
    if (!sortKey) return;

    const nextDir = props.filters?.sort === sortKey && props.filters?.dir === 'asc' ? 'desc' : 'asc';
    router.get(`/school-admin/${props.school.id}/students`, listParams({
        class_category_id: filterForm.class_category_id,
        school_class_id:   filterForm.school_class_id,
        status:            filterForm.status,
        search:            filterForm.search,
        sort: sortKey,
        dir:  nextDir,
    }), { preserveState: true, preserveScroll: true });
}

function clearModalQuery() {
    const url = new URL(window.location.href);
    if (url.searchParams.has('import') || url.searchParams.has('edit')) {
        url.searchParams.delete('import');
        url.searchParams.delete('edit');
        window.history.replaceState({}, '', url.pathname + url.search);
    }
}

function openImportModal() {
    importForm.reset();
    importForm.clearErrors();
    showImport.value = true;
}

function closeImportModal() {
    showImport.value = false;
    clearModalQuery();
}

function openEditModal(student) {
    editingStudent.value = student;
    editPhotoFile.value = null;
    editForm.clearErrors();
    editForm.school_class_id = student.school_class_id ?? student.school_class?.id ?? '';
    editForm.name = student.name ?? '';
    editForm.gender = student.gender ?? '';
    editForm.dob = dobInputValue(student.dob);
    editForm.parent_email = student.parent_email ?? '';
    editForm.reason = '';
    showEdit.value = true;
}

function closeEditModal() {
    showEdit.value = false;
    editingStudent.value = null;
    editPhotoFile.value = null;
    editForm.reset();
    clearModalQuery();
}

function openCreateRequestModal() {
    createForm.clearErrors();
    createPhotoFile.value = null;
    showCreateRequest.value = true;
}

function closeCreateRequestModal() {
    showCreateRequest.value = false;
    createPhotoFile.value = null;
    createForm.reset();
}

function submitCreateRequest() {
    createForm
        .transform(data => ({ ...data, photo: createPhotoFile.value }))
        .post(`/school-admin/${props.school.id}/students/change-request`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => closeCreateRequestModal(),
        });
}

function submitEdit() {
    if (isLocked.value) {
        editForm
            .transform(data => ({ ...data, photo: editPhotoFile.value }))
            .post(`/school-admin/${props.school.id}/students/${editingStudent.value.id}/change-request`, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => closeEditModal(),
            });
        return;
    }

    editForm
        .transform(data => ({ ...data, photo: editPhotoFile.value, _method: 'put' }))
        .post(`/school-admin/${props.school.id}/students/${editingStudent.value.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => closeEditModal(),
        });
}

const csrfToken = computed(() => page.props.csrf_token ?? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '');

const bulkProvisionForm = useForm({});

function confirmBulkProvision() {
    if (!confirm(`Create portal logins for all ${props.studentsWithoutPortal} student(s) without one? Each student will receive a welcome email.`)) return;
    bulkProvisionForm.post(`/school-admin/${props.school.id}/students/bulk-portal-provision`, {
        preserveScroll: true,
    });
}

function openPortalModal(student) {
    portalStudent.value = student;
    portalForm.email = student.email || student.parent_email || '';
    portalForm.password = '';
    portalForm.clearErrors();
    showPortal.value = true;
}

function closePortalModal() {
    showPortal.value = false;
    portalStudent.value = null;
    portalForm.reset();
}

function openLoginModal(student) {
    loginStudent.value = student;
    showLogin.value = true;
}

function closeLoginModal() {
    showLogin.value = false;
    loginStudent.value = null;
}

function submitPortal() {
    portalForm.post(`/school-admin/${props.school.id}/students/${portalStudent.value.id}/portal-login`, {
        preserveScroll: true,
        onSuccess: () => closePortalModal(),
    });
}

function onImportFile(event) {
    importForm.file = event.target.files[0] ?? null;
}

function submitImport() {
    importForm.post(`/school-admin/${props.school.id}/students/import`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            if (!usePage().props.flash?.error) {
                closeImportModal();
                importForm.reset();
            }
        },
    });
}

function onCategoryChange() {
    const stillValid = filteredClasses.value.some(c => c.id === filterForm.school_class_id);
    if (!stillValid) filterForm.school_class_id = null;
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('register') === '1' && !isLocked.value) {
        router.visit(`/school-admin/${props.school.id}/students/create`);
        return;
    }
    if (!isLocked.value && (params.get('import') === '1' || importResult.value)) openImportModal();
    const editId = params.get('edit');
    if (editId) {
        const student = props.students?.data?.find(s => String(s.id) === editId);
        if (student) openEditModal(student);
    }
});

function statusClass(status) {
    return {
        active:      'bg-green-100 text-green-700',
        transferred: 'bg-amber-100 text-amber-700',
        graduated:   'bg-blue-100 text-blue-700',
        withdrawn:   'bg-gray-100 text-gray-600',
    }[status] ?? 'bg-gray-100 text-gray-600';
}

function remove(student) {
    if (!confirm(`Remove student "${student.name}"?`)) return;
    router.delete(`/school-admin/${props.school.id}/students/${student.id}`);
}

function initials(name) {
    return (name || '?').split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

function formatGender(gender) {
    if (!gender) return '—';
    return gender.charAt(0).toUpperCase() + gender.slice(1);
}

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function dobInputValue(value) {
    if (!value) return '';
    const str = String(value);
    return str.length >= 10 ? str.slice(0, 10) : str;
}
</script>
