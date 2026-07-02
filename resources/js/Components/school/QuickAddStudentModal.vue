<template>
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="close"></div>
        <div class="relative modal-shell max-w-2xl max-h-[90vh] flex flex-col">
            <div class="modal-head shrink-0">
                <div>
                    <h3 class="font-bold text-[#041525]">{{ isLocked ? 'Request new student' : `Add student${mode === 'multiple' ? 's' : ''}` }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ lockedSubtitle }}</p>
                </div>
                <button type="button" @click="close" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>

            <div v-if="isLocked" class="notice-banner notice-banner--warning mx-6 mt-4 text-sm shrink-0">
                {{ studentEditLock.message }}
            </div>

            <div v-if="!school?.school_prefix" class="p-6 text-sm text-amber-800 shrink-0">
                Set your
                <a :href="`/school-admin/${school.id}/setup/code`" class="link-brand">school code</a>
                before adding students.
            </div>
            <div v-else-if="!schoolClasses.length" class="p-6 text-sm text-amber-800 shrink-0">
                No classes configured yet. Contact your Sahodaya admin or use
                <a :href="`/school-admin/${school.id}/students`" class="link-brand">Students</a>.
            </div>

            <template v-else-if="isLocked">
                <form @submit.prevent="submitCreateRequest" class="p-6 space-y-4 overflow-y-auto">
                    <ProfilePhotoCropper v-model="photoFile" required />
                    <FormField label="Class" required>
                        <select v-model="requestForm.school_class_id" class="field" required>
                            <option value="">Select class</option>
                            <option v-for="c in sortedClasses" :key="c.id" :value="c.id">{{ formatClassOption(c) }}</option>
                        </select>
                    </FormField>
                    <FormField label="Full name" required>
                        <input v-model="requestForm.name" type="text" class="field" required placeholder="Student name" autofocus>
                    </FormField>
                    <FormGrid>
                        <FormField label="Gender" required>
                            <select v-model="requestForm.gender" class="field" required>
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </FormField>
                        <FormField label="Date of birth" required>
                            <input v-model="requestForm.dob" type="date" class="field" required>
                        </FormField>
                    </FormGrid>
                    <FormField label="Reason for adding *">
                        <textarea v-model="requestForm.reason" rows="3" class="field" required
                                  placeholder="Why is this student being added after the lock date?"></textarea>
                    </FormField>
                    <FormActions>
                        <button type="button" @click="close" class="btn-ghost">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="requestForm.processing">Submit request</button>
                    </FormActions>
                </form>
            </template>

            <template v-else>
                <div class="px-6 pt-4 shrink-0 flex flex-wrap gap-2 border-b border-slate-100">
                    <button type="button" class="text-xs font-semibold px-3 py-1.5 rounded-full"
                            :class="mode === 'single' ? 'bg-[#0f3d7a] text-white' : 'bg-slate-100 text-slate-600'"
                            @click="mode = 'single'">
                        One student + photo
                    </button>
                    <button type="button" class="text-xs font-semibold px-3 py-1.5 rounded-full"
                            :class="mode === 'multiple' ? 'bg-[#0f3d7a] text-white' : 'bg-slate-100 text-slate-600'"
                            @click="mode = 'multiple'">
                        Multiple students
                    </button>
                </div>

                <form v-if="mode === 'single'" @submit.prevent="submitSingle" class="p-6 space-y-4 overflow-y-auto">
                    <ProfilePhotoCropper v-model="photoFile" required />
                    <FormField label="Class" required>
                        <select v-model="form.school_class_id" class="field" required>
                            <option value="">Select class</option>
                            <option v-for="c in sortedClasses" :key="c.id" :value="c.id">{{ formatClassOption(c) }}</option>
                        </select>
                        <p v-if="form.errors.school_class_id" class="form-error">{{ form.errors.school_class_id }}</p>
                    </FormField>
                    <FormField label="Full name" required>
                        <input v-model="form.name" type="text" class="field" required placeholder="Student name" autofocus>
                        <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                    </FormField>
                    <FormGrid>
                        <FormField label="Gender" required>
                            <select v-model="form.gender" class="field" required>
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </FormField>
                        <FormField label="Date of birth" required>
                            <input v-model="form.dob" type="date" class="field" required>
                        </FormField>
                    </FormGrid>
                    <FormActions>
                        <p v-if="singleError" class="form-error mr-auto">{{ singleError }}</p>
                        <button type="button" @click="close" class="btn-ghost">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">Save student</button>
                    </FormActions>
                </form>

                <form v-else @submit.prevent="submitMultiple" class="p-6 space-y-4 overflow-y-auto">
                    <p class="text-xs text-gray-500">
                        Photo, name, class, gender, and DOB required per row.
                        <a :href="`/school-admin/${school.id}/students/bulk`" class="link-brand font-semibold">Open bulk add page →</a>
                    </p>
                    <div class="rounded-xl border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th class="w-8 px-2 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase">#</th>
                                        <th class="w-24 px-2 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase">Photo *</th>
                                        <th class="px-2 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase min-w-[120px]">Name *</th>
                                        <th class="w-28 px-2 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase">Class *</th>
                                        <th class="w-24 px-2 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase">Gender *</th>
                                        <th class="w-32 px-2 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase">DOB *</th>
                                        <th class="w-10 px-2 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="(row, index) in bulkRows" :key="row.key">
                                        <td class="px-2 py-1.5 text-[10px] text-gray-400 font-mono align-middle">{{ index + 1 }}</td>
                                        <td class="px-2 py-1.5 align-middle">
                                            <BulkRowPhotoInput v-model="row.photo" />
                                        </td>
                                        <td class="px-2 py-1.5 align-middle">
                                            <input v-model="row.name" type="text" class="field !py-1.5 text-sm" required placeholder="Name">
                                        </td>
                                        <td class="px-2 py-1.5 align-middle">
                                            <select v-model="row.school_class_id" class="field !py-1.5 text-sm" required>
                                                <option value="">Class</option>
                                                <option v-for="c in sortedClasses" :key="c.id" :value="c.id">{{ classShortLabel(c) }}</option>
                                            </select>
                                        </td>
                                        <td class="px-2 py-1.5 align-middle">
                                            <select v-model="row.gender" class="field !py-1.5 text-sm" required>
                                                <option value="">—</option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </td>
                                        <td class="px-2 py-1.5 align-middle">
                                            <input v-model="row.dob" type="date" class="field !py-1.5 text-sm" required>
                                        </td>
                                        <td class="px-2 py-1.5 align-middle text-right">
                                            <button v-if="bulkRows.length > 1" type="button"
                                                    class="text-[10px] text-red-600 font-semibold hover:underline"
                                                    @click="removeBulkRow(index)">✕</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <button type="button" class="btn-secondary text-xs" @click="addBulkRow">+ Add row</button>
                    <p v-if="bulkError" class="form-error">{{ bulkError }}</p>
                    <FormActions>
                        <button type="button" @click="close" class="btn-ghost">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="bulkForm.processing">
                            Save {{ bulkRows.length }} student{{ bulkRows.length === 1 ? '' : 's' }}
                        </button>
                    </FormActions>
                </form>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ProfilePhotoCropper from '@/Components/school/ProfilePhotoCropper.vue';
import BulkRowPhotoInput from '@/Components/school/BulkRowPhotoInput.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    school: { type: Object, required: true },
    schoolClasses: { type: Array, default: () => [] },
    studentEditLock: { type: Object, default: () => ({ locked: false }) },
    subtitle: {
        type: String,
        default: 'Saved to your school records — use them when registering for events',
    },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const isLocked = computed(() => !!props.studentEditLock?.locked);

const lockedSubtitle = computed(() =>
    isLocked.value
        ? 'Sahodaya must approve before the student is created.'
        : props.subtitle,
);

const mode = ref('single');
const photoFile = ref(null);
const singleError = ref('');
const bulkError = ref('');
let bulkKey = 0;

const form = useForm({
    school_class_id: '',
    name: '',
    gender: '',
    dob: '',
    photo: null,
});

const requestForm = useForm({
    school_class_id: '',
    name: '',
    gender: '',
    dob: '',
    reason: '',
    photo: null,
});

const bulkForm = useForm({ students: [] });

function createBulkRow(copyFrom = null) {
    return {
        key: ++bulkKey,
        name: '',
        gender: '',
        dob: '',
        school_class_id: copyFrom?.school_class_id ?? '',
        photo: null,
    };
}

const bulkRows = ref([createBulkRow()]);

const sortedClasses = computed(() =>
    [...(props.schoolClasses ?? [])].sort((a, b) =>
        String(a.display_order ?? a.name).localeCompare(String(b.display_order ?? b.name), undefined, { numeric: true }),
    ),
);

watch(() => props.modelValue, (open) => {
    if (open) {
        form.clearErrors();
        requestForm.clearErrors();
        bulkForm.clearErrors();
        bulkError.value = '';
    }
});

function addBulkRow() {
    if (bulkRows.value.length >= 25) return;
    bulkRows.value.push(createBulkRow(bulkRows.value[bulkRows.value.length - 1]));
}

function removeBulkRow(index) {
    bulkRows.value.splice(index, 1);
}

function formatClassOption(c) {
    const cat = c.class_category?.label ?? c.class_category?.name;
    return cat ? `Class ${c.name} · ${cat}` : `Class ${c.name}`;
}

function classShortLabel(c) {
    const cat = c.class_category?.label ?? c.class_category?.name;
    return cat ? `${c.name} · ${cat}` : String(c.name);
}

function resetBulkRows() {
    bulkRows.value = [createBulkRow()];
}

function close() {
    emit('update:modelValue', false);
    form.reset();
    requestForm.reset();
    bulkForm.reset();
    photoFile.value = null;
    resetBulkRows();
    mode.value = 'single';
}

function submitCreateRequest() {
    if (!photoFile.value) {
        singleError.value = 'Profile photo is required.';
        return;
    }
    singleError.value = '';

    requestForm
        .transform((data) => ({ ...data, photo: photoFile.value }))
        .post(`/school-admin/${props.school.id}/students/change-request`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                close();
            },
        });
}

function submitSingle() {
    singleError.value = '';
    if (!photoFile.value) {
        singleError.value = 'Profile photo is required.';
        return;
    }

    form.transform((data) => ({ ...data, photo: photoFile.value }))
        .post(`/school-admin/${props.school.id}/students`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                close();
            },
        });
}

function submitMultiple() {
    bulkError.value = '';

    const students = bulkRows.value
        .filter((row) => row.name.trim() !== '')
        .map((row) => ({
            school_class_id: row.school_class_id,
            name: row.name.trim(),
            gender: row.gender,
            dob: row.dob,
            photo: row.photo,
        }));

    if (!students.length) {
        bulkError.value = 'Enter at least one student name.';
        return;
    }

    if (students.some((row) => !row.photo || !row.school_class_id || !row.gender || !row.dob)) {
        bulkError.value = 'Complete photo, class, gender, and DOB for each row.';
        return;
    }

    bulkForm.transform(() => ({ students })).post(`/school-admin/${props.school.id}/students/bulk`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            close();
        },
    });
}
</script>
