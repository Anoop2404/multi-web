<template>
    <SchoolAdminLayout title="Students" :school="school" :show-header-title="false">
        <PageHeader
            title="Students"
            eyebrow="Records"
            :description="`${students.total ?? 0} ${(students.total ?? 0) === 1 ? 'student' : 'students'}${hasActiveFilters ? ' · filtered' : ''}`"
        >
            <template #actions>
                <a :href="exportUrl('xlsx')" class="btn-secondary">↓ Export (.xlsx)</a>
                <a :href="exportUrl('csv')" class="btn-secondary">↓ Export (.csv)</a>
                <a :href="exportPdfUrl()" class="btn-secondary">↓ Print / PDF</a>
                <button v-if="canBulkRemove && selectedIds.length" type="button"
                        class="btn-secondary text-sm !border-red-200 !text-red-700 hover:!bg-red-50"
                        :disabled="bulkDeleteForm.processing"
                        @click="bulkRemoveSelected">
                    Remove selected ({{ selectedIds.length }})
                </button>
                <button v-if="canBulkRemove" type="button" class="btn-secondary text-sm"
                        @click="showClassDelete = true">
                    Remove by class…
                </button>
                <Link v-if="pendingChangeRequests > 0"
                      :href="`/school-admin/${school.id}/students/pending-change-requests`"
                      class="btn-secondary text-sm">
                    Change requests ({{ pendingChangeRequests }})
                </Link>
                <Link :href="`/school-admin/${school.id}/users/profile-change-requests`"
                      class="btn-secondary text-sm">
                    Profile requests
                </Link>
                <button v-if="needsChangeRequest" type="button" @click="openCreateRequestModal" class="btn-primary">
                    Request new student
                </button>
                <button v-if="canBulkUpload" type="button" @click="openBulkUpload('zip')" class="btn-secondary">
                    Update photos (ZIP)
                </button>
                <button v-if="canBulkUpload" type="button" @click="openBulkUpload" class="btn-secondary">
                    Bulk upload
                </button>
                <Link v-if="canBulkUpload" :href="`/school-admin/${school.id}/imports`" class="btn-secondary text-sm">
                    Import history
                </Link>
                <Link v-if="canBulkUpload" :href="`/school-admin/${school.id}/students/create`"
                      :class="['btn-primary', !schoolClasses.length ? 'pointer-events-none opacity-50' : '']"
                      :title="!schoolClasses.length ? 'Classes are configured by your Sahodaya' : ''">
                    + Add student
                </Link>
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

            <div v-if="missingRegNoCount > 0 && school.school_prefix" class="notice-banner notice-banner--warning text-sm flex flex-wrap items-center justify-between gap-3">
                <span>
                    <strong>{{ missingRegNoCount }}</strong> student(s) need a Student ID (e.g. STU/26/0001).
                </span>
                <button type="button" class="btn-primary text-xs !min-h-0 shrink-0" :disabled="backfillForm.processing"
                        @click="backfillRegNumbers">
                    {{ backfillForm.processing ? 'Assigning…' : 'Assign student IDs' }}
                </button>
            </div>

            <div v-if="isLocked && canManageDirectly" class="notice-banner notice-banner--warning">
                Student edit window is closed for staff. As school admin you can still add and edit records.
                <Link v-if="pendingChangeRequests" :href="`/school-admin/${school.id}/students/pending-change-requests`" class="link-brand font-semibold ml-1">
                    Review staff requests ({{ pendingChangeRequests }}) →
                </Link>
            </div>

            <div v-else-if="needsChangeRequest" class="notice-banner notice-banner--warning">
                {{ studentEditLock.message }}
                <Link :href="`/school-admin/${school.id}/students/change-requests`" class="link-brand font-semibold ml-1">
                    View change requests{{ pendingChangeRequests ? ` (${pendingChangeRequests} pending)` : '' }} →
                </Link>
            </div>

            <div v-else-if="unverifiedCount > 0" class="notice-banner notice-banner--info">
                <span class="font-semibold">{{ unverifiedCount }} student{{ unverifiedCount === 1 ? '' : 's' }} awaiting Sahodaya verification.</span>
                Your Sahodaya admin verifies student records before fest and Talent Search registration.
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
                            <FormField label="Verification">
                                <select v-model="filterForm.verification" class="field">
                                    <option value="all">All</option>
                                    <option value="verified">Verified</option>
                                    <option value="unverified">Pending Sahodaya verification</option>
                                </select>
                            </FormField>
                            <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-1">
                                <button v-if="hasActiveFilters" type="button" @click="clearFilters" class="btn-ghost">Clear</button>
                            </div>
                        </FormGrid>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                            <FormField label="Search" class-extra="flex-1 max-w-md">
                                <input v-model="filterForm.search" type="search" placeholder="Name, reg no, email, roll no…"
                                       class="field">
                            </FormField>
                        </div>
                        <div v-if="canBulkRemove && students.data?.length"
                             class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 text-sm">
                            <label class="inline-flex items-center gap-2 text-gray-700 cursor-pointer">
                                <input type="checkbox" class="rounded"
                                       :checked="allOnPageSelected"
                                       @change="toggleSelectAll($event.target.checked)">
                                Select all on page
                            </label>
                            <span v-if="selectedIds.length" class="text-xs text-gray-500">
                                {{ selectedIds.length }} selected
                            </span>
                            <button v-if="selectedIds.length" type="button"
                                    class="text-xs font-semibold text-red-600 hover:underline ml-auto"
                                    :disabled="bulkDeleteForm.processing"
                                    @click="bulkRemoveSelected">
                                Remove selected
                            </button>
                            <button v-if="filterForm.school_class_id" type="button"
                                    class="text-xs font-semibold text-red-600 hover:underline"
                                    :class="{ 'ml-auto': !selectedIds.length }"
                                    :disabled="bulkDeleteForm.processing"
                                    @click="bulkRemoveFilteredClass">
                                Remove all in this class
                            </button>
                        </div>
                    </div>
                </template>

                <tr v-for="student in students.data" :key="student.id" class="hover:bg-gray-50/80">
                    <td v-if="canBulkRemove" class="px-4 py-3 w-10">
                        <input type="checkbox" class="rounded"
                               :checked="selectedIds.includes(student.id)"
                               @change="toggleSelect(student.id, $event.target.checked)">
                    </td>
                    <td class="px-4 py-3 w-14">
                        <button
                            v-if="canUpdatePhoto"
                            type="button"
                            class="group relative w-10 h-10 rounded-full overflow-hidden border border-gray-200 bg-gray-100 flex items-center justify-center hover:ring-2 hover:ring-[#0f3d7a]/30 transition cursor-pointer"
                            :title="student.photo_url ? `Change photo — ${student.name}` : `Add photo — ${student.name}`"
                            @click.stop="openPhotoModal(student)"
                        >
                            <img v-if="student.photo_url && !photoBroken[student.id]" :key="student.photo_url"
                                 :src="student.photo_url" :alt="student.name"
                                 class="w-full h-full object-cover" @error="photoBroken[student.id] = true">
                            <span v-else class="text-xs text-gray-400 font-semibold">{{ initials(student.name) }}</span>
                            <span class="absolute inset-0 flex items-center justify-center bg-[#041525]/45 text-white text-[10px] font-bold uppercase tracking-wide opacity-0 group-hover:opacity-100 transition">
                                Photo
                            </span>
                        </button>
                        <Link v-else :href="profileUrl(student)"
                              class="relative w-10 h-10 rounded-full overflow-hidden border border-gray-200 bg-gray-100 flex items-center justify-center hover:ring-2 hover:ring-[#0f3d7a]/20 transition"
                              title="View profile">
                            <img v-if="student.photo_url && !photoBroken[student.id]" :key="student.photo_url"
                                 :src="student.photo_url" :alt="student.name"
                                 class="w-full h-full object-cover" @error="photoBroken[student.id] = true">
                            <span v-else class="text-xs text-gray-400 font-semibold">{{ initials(student.name) }}</span>
                        </Link>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-900">
                        <Link :href="profileUrl(student)" class="hover:text-[#0f3d7a] hover:underline">
                            {{ student.name }}
                        </Link>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">
                        {{ student.reg_no || '—' }}
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600 capitalize">{{ formatGender(student.gender) }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ formatStudentDob(student.dob) }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ student.parent_email || '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ student.school_class?.name || '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium capitalize"
                              :class="statusClass(student.status)">{{ student.status }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span v-if="student.is_verified"
                              class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700">
                            ✓ Verified
                        </span>
                        <span v-else class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700">
                            Pending
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <Link :href="profileUrl(student)" class="link-brand text-xs mr-3">Profile</Link>
                        <Link :href="`${profileUrl(student)}?edit=1`" class="link-brand text-xs mr-3">
                            {{ needsChangeRequest ? 'Request change' : 'Edit' }}
                        </Link>
                        <button v-if="canManageDirectly || !needsChangeRequest" type="button" @click="remove(student)"
                                class="text-xs text-red-400 hover:text-red-600 hover:underline">Remove</button>
                    </td>
                </tr>
            </SahodayaDataTable>

            <p v-if="!students.data?.length && school.school_prefix && schoolClasses.length && canBulkUpload"
               class="text-center text-sm text-gray-500 -mt-2 space-x-3">
                <button type="button" @click="openBulkUpload" class="link-brand font-semibold hover:underline">
                    Bulk upload students
                </button>
                <span class="text-gray-300">·</span>
                <Link :href="`/school-admin/${school.id}/students/create`" class="link-brand font-semibold hover:underline">
                    Add one student
                </Link>
            </p>
        </div>

        <StudentBulkUploadModal
            v-model="showBulkUpload"
            :school-id="school.id"
            :class-names="classNames"
            :initial-tab="bulkUploadTab"
        />

        <!-- Edit student modal -->
        <div v-if="showEdit && editingStudent" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="closeEditModal"></div>
            <div class="relative modal-shell max-w-md">
                <div class="modal-head">
                    <div>
                        <h3 class="font-bold text-[#041525]">{{ needsChangeRequest ? 'Request student change' : 'Edit Student' }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ needsChangeRequest
                                ? 'Proposed changes are sent for school leadership review before they take effect.'
                                : 'Update profile, class, gender, and contact details. Edits may reset Sahodaya verification until the record is reviewed again.' }}
                        </p>
                    </div>
                    <button type="button" @click="closeEditModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>

                <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                    <ProfilePhotoCropper v-model="editPhotoFile" :existing-url="editingStudent.photo_url" />

                    <div>
                        <label class="form-label mb-1.5">Student ID</label>
                        <input :value="editingStudent.reg_no || '—'" type="text" readonly
                               class="field bg-gray-50 text-gray-500 font-mono cursor-not-allowed">
                    </div>

                    <div>
                        <label class="form-label mb-1.5">School admission no.</label>
                        <input v-model="editForm.admission_number" type="text" maxlength="50"
                               class="field" placeholder="Optional">
                        <p v-if="editForm.errors.admission_number" class="text-xs text-red-500 mt-1">{{ editForm.errors.admission_number }}</p>
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

                    <div v-if="needsChangeRequest">
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
                            {{ needsChangeRequest ? 'Submit change request' : 'Save changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Request new student when locked -->
        <div v-if="showCreateRequest && needsChangeRequest" class="fixed inset-0 z-50 flex items-center justify-center p-4">
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

        <!-- Remove all students in a class -->
        <div v-if="showClassDelete && canBulkRemove" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="showClassDelete = false"></div>
            <div class="relative modal-shell max-w-md w-full">
                <div class="modal-head">
                    <div>
                        <h3 class="font-bold text-[#041525]">Remove students by class</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Withdraws all <strong>active</strong> students in the class (soft-delete). Use this if a class was uploaded wrongly.
                        </p>
                    </div>
                    <button type="button" @click="showClassDelete = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form @submit.prevent="bulkRemoveByClassModal" class="p-6 space-y-4">
                    <div>
                        <label class="form-label mb-1.5">Class *</label>
                        <select v-model="classDeleteId" required class="field">
                            <option value="">Select class</option>
                            <option v-for="c in schoolClassesSorted" :key="c.id" :value="c.id">
                                {{ formatClassOption(c) }}
                            </option>
                        </select>
                    </div>
                    <p class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                        This cannot be undone from the active list. Withdrawn records stay soft-deleted for audit.
                    </p>
                    <div class="flex justify-end gap-3 pt-1">
                        <button type="button" class="text-sm text-gray-500" @click="showClassDelete = false">Cancel</button>
                        <button type="submit" class="btn-primary !bg-red-600 hover:!bg-red-700"
                                :disabled="bulkDeleteForm.processing || !classDeleteId">
                            {{ bulkDeleteForm.processing ? 'Removing…' : 'Remove all in class' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick photo update (click avatar in list) -->
        <StudentPhotoEditModal
            v-model="showPhotoEdit"
            :student="photoEditStudent"
            :school-id="school.id"
            @saved="onPhotoSaved"
        />

        <!-- Import CSV modal removed — use StudentBulkUploadModal -->
    </SchoolAdminLayout>
</template>

<script setup>
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ProfilePhotoCropper from '@/Components/school/ProfilePhotoCropper.vue';
import StudentPhotoEditModal from '@/Components/school/StudentPhotoEditModal.vue';
import StudentBulkUploadModal from '@/Components/school/StudentBulkUploadModal.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';
import { calendarDateInputValue, formatDobDetail } from '@/support/calendarDates.js';

const props = defineProps({
    school:     Object,
    students:   Object,
    filters:    Object,
    categories: { type: Array, default: () => [] },
    classes:    { type: Array, default: () => [] },
    classNames: { type: Array, default: () => [] },
    studentEditLock: { type: Object, default: () => ({ locked: false }) },
    canManageDirectly: { type: Boolean, default: false },
    unverifiedCount: { type: Number, default: 0 },
    missingRegNoCount: { type: Number, default: 0 },
    pendingChangeRequests: { type: Number, default: 0 },
});

const isLocked = computed(() => !!props.studentEditLock?.locked);
const needsChangeRequest = computed(() => isLocked.value && !props.canManageDirectly);
const canUpdatePhoto = computed(() => props.canManageDirectly || !needsChangeRequest.value);
const canBulkUpload = computed(() =>
    props.school?.school_prefix
    && schoolClasses.value.length > 0
    && (props.canManageDirectly || !needsChangeRequest.value)
);
const canBulkRemove = computed(() => props.canManageDirectly || !needsChangeRequest.value);

const showBulkUpload = ref(false);
const bulkUploadTab = ref('csv');
const showEdit = ref(false);
const showCreateRequest = ref(false);
const showPhotoEdit = ref(false);
const showClassDelete = ref(false);
const classDeleteId = ref('');
const selectedIds = ref([]);
const editingStudent = ref(null);
const photoEditStudent = ref(null);
const editPhotoFile = ref(null);
const createPhotoFile = ref(null);
const photoBroken = reactive({});
const bulkDeleteForm = useForm({
    scope: 'selected',
    student_ids: [],
    school_class_id: null,
});

watch(() => props.students?.data, (rows) => {
    if (!rows?.length) return;
    for (const student of rows) {
        if (student.photo_url) {
            delete photoBroken[student.id];
        }
    }
}, { deep: true });

const pageIds = computed(() => (props.students?.data ?? []).map((s) => s.id));
const allOnPageSelected = computed(() =>
    pageIds.value.length > 0 && pageIds.value.every((id) => selectedIds.value.includes(id))
);

const columns = computed(() => {
    const base = [
        ...(canBulkRemove.value ? [{ key: 'select', label: '', sortable: false, class: 'w-10' }] : []),
        { key: 'photo',        label: 'Photo',  sortable: false, class: 'w-14' },
        { key: 'name',         label: 'Name',   sortable: true },
        { key: 'reg_no',       label: 'Student ID', sortable: false },
        { key: 'gender',       label: 'Gender', sortable: false },
        { key: 'dob',          label: 'DOB',    sortable: false },
        { key: 'parent_email', label: 'Email',  sortable: true },
        { key: 'class',        label: 'Class',  sortable: true },
        { key: 'status',       label: 'Status', sortable: true },
        { key: 'verified',     label: 'Verification', sortable: false },
        { key: 'actions',      label: '', sortable: false, align: 'right' },
    ];
    return base;
});

const filterForm = reactive({
    class_category_id: props.filters?.class_category_id ?? null,
    school_class_id:   props.filters?.school_class_id ?? null,
    status:            props.filters?.status ?? 'active',
    verification:      props.filters?.verification ?? 'all',
    search:            props.filters?.search ?? '',
});

const editForm = useForm({
    school_class_id: '',
    name:            '',
    gender:          '',
    dob:             '',
    parent_email:    '',
    admission_number: '',
    reason:          '',
});

const backfillForm = useForm({});

function backfillRegNumbers() {
    if (!confirm(`Assign formatted student IDs to ${props.missingRegNoCount} record(s)?`)) return;
    backfillForm.post(`/school-admin/${props.school.id}/students/backfill-reg-numbers`, {
        preserveScroll: true,
    });
}

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
    || filterForm.verification !== 'all'
    || !!filterForm.search
);

watch(() => props.filters, (f) => {
    if (!f) return;
    filterForm.class_category_id = f.class_category_id ?? null;
    filterForm.school_class_id   = f.school_class_id ?? null;
    filterForm.status            = f.status ?? 'active';
    filterForm.verification      = f.verification ?? 'all';
    filterForm.search            = f.search ?? '';
    selectedIds.value = [];
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
        verification:      props.filters?.verification ?? 'all',
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
        verification:      filterForm.verification,
        search:            filterForm.search,
        sort:              props.filters?.sort ?? 'name',
        dir:               props.filters?.dir ?? 'asc',
    }, { preserveState: true, preserveScroll: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function clearFilters() {
    filterForm.class_category_id = null;
    filterForm.school_class_id   = null;
    filterForm.status            = 'active';
    filterForm.verification      = 'all';
    filterForm.search            = '';
    router.get(`/school-admin/${props.school.id}/students`, listParams({
        class_category_id: null,
        school_class_id:   null,
        status:            'active',
        verification:      'all',
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
        verification:      filterForm.verification,
        search:            filterForm.search,
        sort: sortKey,
        dir:  nextDir,
    }), { preserveState: true, preserveScroll: true });
}

function clearModalQuery() {
    const url = new URL(window.location.href);
    if (url.searchParams.has('import') || url.searchParams.has('bulk') || url.searchParams.has('edit')) {
        url.searchParams.delete('import');
        url.searchParams.delete('bulk');
        url.searchParams.delete('edit');
        window.history.replaceState({}, '', url.pathname + url.search);
    }
}

function bulkUploadTabFromParams(params) {
    const tab = params.get('tab') ?? params.get('bulk');
    if (tab === 'grid' || tab === 'zip') return tab;
    return 'csv';
}

function openBulkUpload(tab = 'csv') {
    bulkUploadTab.value = tab;
    showBulkUpload.value = true;
}

watch(showBulkUpload, (open) => {
    if (!open) clearModalQuery();
});

watch(showPhotoEdit, (open) => {
    if (!open) photoEditStudent.value = null;
});

function openEditModal(student) {
    editingStudent.value = student;
    editPhotoFile.value = null;
    editForm.clearErrors();
    editForm.school_class_id = student.school_class_id ?? student.school_class?.id ?? '';
    editForm.name = student.name ?? '';
    editForm.gender = student.gender ?? '';
    editForm.dob = dobInputValue(student.dob);
    editForm.parent_email = student.parent_email ?? '';
    editForm.admission_number = student.admission_number ?? '';
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

function openPhotoModal(student) {
    photoEditStudent.value = student;
    showPhotoEdit.value = true;
}

function onPhotoSaved() {
    photoEditStudent.value = null;
    router.reload({ only: ['students', 'unverifiedCount'], preserveScroll: true });
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

function buildEditPayload(data, { changeRequest = false } = {}) {
    const payload = { ...data };
    if (editPhotoFile.value instanceof File) {
        payload.photo = editPhotoFile.value;
    }
    if (!changeRequest) {
        payload._method = 'put';
    }
    return payload;
}

function submitEdit() {
    if (needsChangeRequest.value) {
        editForm
            .transform((data) => buildEditPayload(data, { changeRequest: true }))
            .post(`/school-admin/${props.school.id}/students/${editingStudent.value.id}/change-request`, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => closeEditModal(),
            });
        return;
    }

    editForm
        .transform((data) => buildEditPayload(data))
        .post(`/school-admin/${props.school.id}/students/${editingStudent.value.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                closeEditModal();
                router.reload({ only: ['students', 'unverifiedCount'], preserveScroll: true });
            },
        });
}

function onCategoryChange() {
    const stillValid = filteredClasses.value.some(c => c.id === filterForm.school_class_id);
    if (!stillValid) filterForm.school_class_id = null;
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('register') === '1' && props.canManageDirectly) {
        router.visit(`/school-admin/${props.school.id}/students/create`);
        return;
    }
    if (params.get('bulk') || params.get('import') === '1') {
        openBulkUpload(bulkUploadTabFromParams(params));
    }
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
    if (!confirm(`Withdraw student "${student.name}"? The record will be soft-deleted.`)) return;
    router.delete(`/school-admin/${props.school.id}/students/${student.id}`);
}

function toggleSelect(id, checked) {
    if (checked) {
        if (!selectedIds.value.includes(id)) selectedIds.value = [...selectedIds.value, id];
    } else {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    }
}

function toggleSelectAll(checked) {
    if (checked) {
        selectedIds.value = [...new Set([...selectedIds.value, ...pageIds.value])];
    } else {
        const drop = new Set(pageIds.value);
        selectedIds.value = selectedIds.value.filter((id) => !drop.has(id));
    }
}

function submitBulkDelete(payload) {
    bulkDeleteForm.scope = payload.scope;
    bulkDeleteForm.student_ids = payload.student_ids ?? [];
    bulkDeleteForm.school_class_id = payload.school_class_id ?? null;
    bulkDeleteForm.post(`/school-admin/${props.school.id}/students/bulk-delete`, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            showClassDelete.value = false;
            classDeleteId.value = '';
            bulkDeleteForm.reset();
        },
    });
}

function bulkRemoveSelected() {
    if (!selectedIds.value.length) return;
    const n = selectedIds.value.length;
    if (!confirm(`Withdraw ${n} selected student${n === 1 ? '' : 's'}? Records will be soft-deleted.`)) return;
    submitBulkDelete({ scope: 'selected', student_ids: selectedIds.value });
}

function bulkRemoveFilteredClass() {
    const classId = filterForm.school_class_id;
    if (!classId) return;
    const label = schoolClasses.value.find((c) => String(c.id) === String(classId))?.name ?? 'this class';
    if (!confirm(`Withdraw ALL active students in Class ${label}? This cannot be undone from the active list.`)) return;
    submitBulkDelete({ scope: 'class', school_class_id: Number(classId) });
}

function bulkRemoveByClassModal() {
    if (!classDeleteId.value) return;
    const label = schoolClassesSorted.value.find((c) => String(c.id) === String(classDeleteId.value))?.name
        ?? schoolClasses.value.find((c) => String(c.id) === String(classDeleteId.value))?.name
        ?? 'this class';
    if (!confirm(`Withdraw ALL active students in Class ${label}? This cannot be undone from the active list.`)) return;
    submitBulkDelete({ scope: 'class', school_class_id: Number(classDeleteId.value) });
}

function exportUrl(format) {
    const params = new URLSearchParams();
    const p = {
        class_category_id: filterForm.class_category_id,
        school_class_id: filterForm.school_class_id,
        status: filterForm.status,
        verification: filterForm.verification,
        search: filterForm.search,
        format,
    };
    Object.entries(p).forEach(([k, v]) => {
        if (v != null && v !== '') params.set(k, v);
    });
    return `/school-admin/${props.school.id}/students/export?${params.toString()}`;
}

function exportPdfUrl() {
    const params = new URLSearchParams();
    const p = {
        class_category_id: filterForm.class_category_id,
        school_class_id: filterForm.school_class_id,
        status: filterForm.status,
        verification: filterForm.verification,
        search: filterForm.search,
    };
    Object.entries(p).forEach(([k, v]) => {
        if (v != null && v !== '') params.set(k, v);
    });
    return `/school-admin/${props.school.id}/students/export-pdf?${params.toString()}`;
}

function initials(name) {
    return (name || '?').split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

function formatGender(gender) {
    if (!gender) return '—';
    return gender.charAt(0).toUpperCase() + gender.slice(1);
}

function formatStudentDob(value) {
    if (! value) return '—';
    return formatDobDetail(value);
}

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function profileUrl(student) {
    return `/school-admin/${props.school.id}/students/${student.id}`;
}

function dobInputValue(value) {
    return calendarDateInputValue(value);
}
</script>
