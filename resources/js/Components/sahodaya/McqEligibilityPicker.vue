<template>
    <div class="space-y-4">
        <div>
            <p class="text-xs font-semibold text-slate-600 mb-2">Audience</p>
            <div class="flex flex-wrap gap-2">
                <button v-for="opt in audienceOptions" :key="opt.value" type="button"
                        class="px-3 py-1.5 rounded-lg text-sm font-semibold border transition-colors"
                        :class="local.audience === opt.value
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-800'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                        @click="setAudience(opt.value)">
                    {{ opt.label }}
                </button>
            </div>
            <p class="text-xs text-slate-500 mt-2">{{ audienceHint }}</p>
        </div>

        <div v-if="showsStudentFilters">
            <p class="text-xs font-semibold text-slate-600 mb-2">Who can register (students)</p>
            <div class="flex flex-wrap gap-2">
                <button v-for="opt in assignmentOptions" :key="opt.value" type="button"
                        class="px-3 py-1.5 rounded-lg text-sm font-semibold border transition-colors"
                        :class="local.assignment_type === opt.value
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-800'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                        @click="setAssignmentType(opt.value)">
                    {{ opt.label }}
                </button>
            </div>
            <p class="text-xs text-slate-500 mt-2">{{ assignmentHint }}</p>
        </div>

        <div v-if="showsStudentFilters && local.assignment_type === 'category' && classCategories?.length" class="rounded-lg border border-slate-200 p-3">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <p class="text-xs font-semibold text-slate-700">Select class categories</p>
                <button v-if="local.class_category_ids.length" type="button" class="text-xs text-slate-500 hover:text-slate-700" @click="clearCategories">Clear</button>
            </div>
            <div class="flex flex-wrap gap-2">
                <label v-for="cat in classCategories" :key="cat.id"
                       class="inline-flex items-center gap-1.5 text-sm border rounded-lg px-2.5 py-1.5 cursor-pointer select-none"
                       :class="local.class_category_ids.includes(cat.id) ? 'border-indigo-400 bg-indigo-50 text-indigo-900' : 'border-slate-200 hover:border-slate-300'">
                    <input type="checkbox" class="sr-only" :checked="local.class_category_ids.includes(cat.id)" @change="toggleCategory(cat.id)">
                    {{ cat.label }}
                </label>
            </div>
            <p v-if="!local.class_category_ids.length" class="text-xs text-amber-700 mt-2">Select at least one category.</p>
        </div>

        <div v-else-if="showsStudentFilters && local.assignment_type === 'class' && masterClasses?.length" class="rounded-lg border border-slate-200 p-3">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <p class="text-xs font-semibold text-slate-700">Select specific classes</p>
                <button v-if="local.master_class_ids.length" type="button" class="text-xs text-slate-500 hover:text-slate-700" @click="clearClasses">Clear</button>
            </div>
            <div v-for="group in masterClassesByCategory" :key="group.categoryId" class="mb-3 last:mb-0">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 mb-1.5">{{ group.label }}</p>
                <div class="flex flex-wrap gap-2">
                    <label v-for="cls in group.classes" :key="cls.id"
                           class="inline-flex items-center gap-1.5 text-sm border rounded-lg px-2.5 py-1.5 cursor-pointer select-none"
                           :class="local.master_class_ids.includes(cls.id) ? 'border-indigo-400 bg-indigo-50 text-indigo-900' : 'border-slate-200 hover:border-slate-300'">
                        <input type="checkbox" class="sr-only" :checked="local.master_class_ids.includes(cls.id)" @change="toggleMasterClass(cls.id)">
                        {{ cls.name }}
                    </label>
                </div>
            </div>
            <p v-if="!local.master_class_ids.length" class="text-xs text-amber-700 mt-2">Select at least one class.</p>
        </div>

        <FormField v-if="showsStudentFilters" label="Gender">
            <template #default="{ id }">
                <select :id="id" v-model="local.gender" class="field max-w-xs" @change="emitUpdate">
                    <option value="open">All</option>
                    <option value="male">Boys only</option>
                    <option value="female">Girls only</option>
                </select>
            </template>
        </FormField>

        <div v-if="showsTeacherFilters" class="rounded-lg border border-slate-200 p-3 space-y-3">
            <p class="text-xs font-semibold text-slate-700">Teacher eligibility</p>
            <FormField label="Minimum experience (years)">
                <template #default="{ id }">
                    <input :id="id" v-model.number="local.min_experience_years" type="number" min="0" max="60"
                           class="field max-w-xs" placeholder="Optional" @change="emitUpdate">
                </template>
            </FormField>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="local.allow_teacher_self_registration" type="checkbox" @change="emitUpdate">
                Allow teacher self-registration in portal
            </label>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import FormField from '@/Components/ui/FormField.vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({
            audience: 'students',
            scope: 'all',
            assignment_type: 'all',
            class_category_ids: [],
            master_class_ids: [],
            class_groups: [],
            gender: 'open',
            min_experience_years: null,
            allow_teacher_self_registration: true,
            teaching_type_ids: [],
            subject_ids: [],
            excluded_designation_ids: [],
        }),
    },
    classCategories: { type: Array, default: () => [] },
    masterClasses: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const audienceOptions = [
    { value: 'students', label: 'Students' },
    { value: 'teachers', label: 'Teachers' },
    { value: 'both', label: 'Students & teachers' },
];

const assignmentOptions = [
    { value: 'all', label: 'All classes' },
    { value: 'category', label: 'Class categories' },
    { value: 'class', label: 'Specific classes' },
];

function inferAssignmentType(val) {
    if (val?.assignment_type && ['all', 'category', 'class'].includes(val.assignment_type)) {
        return val.assignment_type;
    }
    const catIds = val?.class_category_ids ?? [];
    const classIds = val?.master_class_ids ?? [];
    if (catIds.length && !classIds.length) return 'category';
    if (classIds.length && !catIds.length) return 'class';
    if (catIds.length) return 'category';
    return val?.scope === 'filtered' ? 'category' : 'all';
}

const local = reactive({
    audience: props.modelValue?.audience ?? 'students',
    scope: props.modelValue?.scope ?? 'all',
    assignment_type: inferAssignmentType(props.modelValue),
    class_category_ids: [...(props.modelValue?.class_category_ids ?? [])],
    master_class_ids: [...(props.modelValue?.master_class_ids ?? [])],
    class_groups: [...(props.modelValue?.class_groups ?? [])],
    gender: props.modelValue?.gender ?? 'open',
    min_experience_years: props.modelValue?.min_experience_years ?? null,
    allow_teacher_self_registration: props.modelValue?.allow_teacher_self_registration ?? true,
    teaching_type_ids: [...(props.modelValue?.teaching_type_ids ?? [])],
    subject_ids: [...(props.modelValue?.subject_ids ?? [])],
    excluded_designation_ids: [...(props.modelValue?.excluded_designation_ids ?? [])],
});

watch(() => props.modelValue, (val) => {
    if (!val) return;
    local.audience = val.audience ?? 'students';
    local.scope = val.scope ?? 'all';
    local.assignment_type = inferAssignmentType(val);
    local.class_category_ids = [...(val.class_category_ids ?? [])];
    local.master_class_ids = [...(val.master_class_ids ?? [])];
    local.class_groups = [...(val.class_groups ?? [])];
    local.gender = val.gender ?? 'open';
    local.min_experience_years = val.min_experience_years ?? null;
    local.allow_teacher_self_registration = val.allow_teacher_self_registration ?? true;
    local.teaching_type_ids = [...(val.teaching_type_ids ?? [])];
    local.subject_ids = [...(val.subject_ids ?? [])];
    local.excluded_designation_ids = [...(val.excluded_designation_ids ?? [])];
}, { deep: true });

const showsStudentFilters = computed(() => ['students', 'both'].includes(local.audience));
const showsTeacherFilters = computed(() => ['teachers', 'both'].includes(local.audience));

const masterClassesByCategory = computed(() => {
    const map = new Map();
    for (const cls of props.masterClasses) {
        const key = cls.class_category_id ?? 'other';
        if (!map.has(key)) {
            map.set(key, {
                categoryId: key,
                label: cls.class_category_label || 'Other',
                classes: [],
            });
        }
        map.get(key).classes.push(cls);
    }
    return [...map.values()];
});

const audienceHint = computed(() => {
    if (local.audience === 'teachers') return 'Schools nominate teachers; teachers may also self-register if enabled.';
    if (local.audience === 'both') return 'Both students and teachers can be registered for this exam.';
    return 'Standard student Talent Search registration.';
});

const assignmentHint = computed(() => {
    if (local.assignment_type === 'all') return 'Every student in member schools can register (subject to gender).';
    if (local.assignment_type === 'category') return 'Students in any selected category (e.g. Primary, Secondary) can register.';
    return 'Only students in the selected classes can register — pick individual standards.';
});

function setAudience(value) {
    local.audience = value;
    emitUpdate();
}

function setAssignmentType(type) {
    local.assignment_type = type;
    if (type === 'all') {
        local.class_category_ids = [];
        local.master_class_ids = [];
        local.scope = 'all';
    } else if (type === 'category') {
        local.master_class_ids = [];
        local.scope = 'filtered';
    } else {
        local.class_category_ids = [];
        local.scope = 'filtered';
    }
    emitUpdate();
}

function toggleCategory(id) {
    const ids = local.class_category_ids;
    const idx = ids.indexOf(id);
    if (idx >= 0) ids.splice(idx, 1);
    else ids.push(id);
    emitUpdate();
}

function toggleMasterClass(id) {
    const ids = local.master_class_ids;
    const idx = ids.indexOf(id);
    if (idx >= 0) ids.splice(idx, 1);
    else ids.push(id);
    emitUpdate();
}

function clearCategories() {
    local.class_category_ids = [];
    emitUpdate();
}

function clearClasses() {
    local.master_class_ids = [];
    emitUpdate();
}

function emitUpdate() {
    const assignment_type = local.assignment_type;
    emit('update:modelValue', {
        audience: local.audience,
        scope: assignment_type === 'all' ? 'all' : 'filtered',
        assignment_type,
        class_category_ids: assignment_type === 'category' ? [...local.class_category_ids] : [],
        master_class_ids: assignment_type === 'class' ? [...local.master_class_ids] : [],
        class_groups: [...local.class_groups],
        gender: local.gender,
        min_experience_years: local.min_experience_years || null,
        allow_teacher_self_registration: !!local.allow_teacher_self_registration,
        teaching_type_ids: [...local.teaching_type_ids],
        subject_ids: [...local.subject_ids],
        excluded_designation_ids: [...local.excluded_designation_ids],
    });
}
</script>
