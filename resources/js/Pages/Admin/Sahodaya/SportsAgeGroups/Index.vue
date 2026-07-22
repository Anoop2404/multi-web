<template>
    <SahodayaEventsLayout title="Age categories" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader
            title="Age categories"
            eyebrow="Sahodaya settings"
            description="Define Under-N bands (U14 = under 14 years, U17 = under 17, etc.) used for item eligibility and fees — shared across every program, not just Sports Meet."
        >
            <template #actions>
                <button type="button" class="btn-secondary text-sm" @click="confirmReset">Reset to defaults</button>
                <button type="button" class="btn-primary text-sm" @click="showAdd = !showAdd">+ Add category</button>
            </template>
        </PageHeader>

        <div class="max-w-4xl space-y-6">
            <div class="notice-banner notice-banner--info text-sm">
                <p class="font-semibold text-[#0f3d7a] mb-1">How this works</p>
                <ul class="list-disc pl-4 space-y-1 text-slate-700">
                    <li><strong>Under age</strong> is the maximum age on the reference date (U17 → under 17).</li>
                    <li>Students can register for any item whose band they qualify for (under-N rule).</li>
                    <li>A specific Sports Meet event can still override the reference date under its own Settings → Eligibility — otherwise the Sahodaya-wide date below applies everywhere, including the student roster's age-category column.</li>
                    <li v-if="activeAcademicYear">
                        Active academic year: <strong class="font-mono">{{ activeAcademicYear.label }}</strong>
                        — fest events use their assigned year to match student records.
                        <a :href="`/sahodaya-admin/${sahodaya.id}/academic-years`" class="link-brand font-semibold">Manage years →</a>
                    </li>
                </ul>
            </div>

            <form @submit.prevent="saveGlobalCutoff" class="card space-y-3">
                <div>
                    <p class="font-semibold text-sm">Sahodaya-wide age reference date</p>
                    <p class="text-xs text-slate-500 mt-1">
                        Single default used everywhere age category is computed (student lists, item eligibility) unless a specific event sets its own override. Leave blank to fall back to 31 Dec of the competition year.
                    </p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="label-xs">Reference date</label>
                        <input v-model="cutoffForm.sports_age_cutoff_date" type="date" class="field">
                    </div>
                    <button type="submit" class="btn-primary text-sm" :disabled="cutoffForm.processing">Save</button>
                    <button v-if="cutoffForm.sports_age_cutoff_date" type="button" class="btn-ghost text-sm"
                            @click="cutoffForm.sports_age_cutoff_date = ''; saveGlobalCutoff()">
                        Clear
                    </button>
                </div>
            </form>

            <form v-if="showAdd" @submit.prevent="createGroup"
                  class="card space-y-3">
                <p class="font-semibold text-sm">New age category</p>
                <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <div>
                        <label class="label-xs">Key</label>
                        <input v-model="addForm.group_key" class="field font-mono" placeholder="u16" required pattern="^(open|u\d{1,2})$">
                        <p class="text-[10px] text-gray-400 mt-0.5">e.g. u14, u17, open</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label-xs">Label</label>
                        <input v-model="addForm.label" class="field" placeholder="Under 16" required>
                    </div>
                    <div>
                        <label class="label-xs">Under age</label>
                        <input v-model.number="addForm.under_age" type="number" min="1" max="99" class="field" :disabled="addForm.group_key === 'open'">
                    </div>
                    <div>
                        <label class="label-xs">Default fee (₹)</label>
                        <input v-model.number="addForm.default_fee" type="number" min="0" step="0.01" class="field">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary text-sm" :disabled="addRouter.processing">Create</button>
                    <button type="button" class="btn-ghost text-sm" @click="showAdd = false">Cancel</button>
                </div>
            </form>

            <div class="rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Key</th>
                            <th class="p-3">Label</th>
                            <th class="p-3">Under age</th>
                            <th class="p-3">Order</th>
                            <th class="p-3">Default fee</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 w-28"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="group in groups" :key="group.id" class="bg-white">
                            <td class="p-3 font-mono font-semibold text-[#0f3d7a]">{{ group.group_key }}</td>
                            <td class="p-3">
                                <input v-if="editId === group.id" v-model="editForm.label" class="field !py-1 !text-xs">
                                <span v-else>{{ group.label }}</span>
                            </td>
                            <td class="p-3">
                                <input v-if="editId === group.id && group.group_key !== 'open'"
                                       v-model.number="editForm.under_age" type="number" min="1" max="99" class="field !py-1 !text-xs w-20">
                                <span v-else-if="group.under_age != null">Under {{ group.under_age }}</span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="p-3">
                                <input v-if="editId === group.id" v-model.number="editForm.sort_order" type="number" min="0" class="field !py-1 !text-xs w-16">
                                <span v-else>{{ group.sort_order }}</span>
                            </td>
                            <td class="p-3">
                                <input v-if="editId === group.id" v-model.number="editForm.default_fee" type="number" min="0" step="0.01" class="field !py-1 !text-xs w-24">
                                <span v-else>{{ group.default_fee != null ? `₹${group.default_fee}` : '—' }}</span>
                            </td>
                            <td class="p-3">
                                <label v-if="editId === group.id" class="flex items-center gap-1 text-xs">
                                    <input v-model="editForm.is_active" type="checkbox" class="rounded"> Active
                                </label>
                                <span v-else :class="group.is_active ? 'text-green-700' : 'text-gray-400'" class="text-xs font-semibold">
                                    {{ group.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <template v-if="editId === group.id">
                                    <button type="button" class="text-xs font-semibold text-[#0f3d7a] mr-2" @click="saveEdit(group)">Save</button>
                                    <button type="button" class="text-xs text-gray-500" @click="editId = null">Cancel</button>
                                </template>
                                <template v-else>
                                    <button type="button" class="text-xs font-semibold text-[#0f3d7a] mr-2" @click="startEdit(group)">Edit</button>
                                    <button type="button" class="text-xs text-red-600" @click="removeGroup(group)">Remove</button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    groups: Array,
    activeAcademicYear: Object,
    globalAgeCutoffDate: { type: String, default: null },
});

// General Sahodaya-level route (not scoped under any one program's prefix) —
// age categories are shared across every program, not just Sports Meet.
const base = `/sahodaya-admin/${props.sahodaya.id}/sports-age-groups`;

const cutoffForm = useForm({
    sports_age_cutoff_date: props.globalAgeCutoffDate ?? '',
});

function saveGlobalCutoff() {
    cutoffForm.put(`${base}/global-cutoff`, { preserveScroll: true });
}
const showAdd = ref(false);
const editId = ref(null);
const addRouter = reactive({ processing: false });

const addForm = reactive({
    group_key: '',
    label: '',
    under_age: null,
    default_fee: null,
    sort_order: 100,
});

const editForm = reactive({
    label: '',
    under_age: null,
    sort_order: 0,
    default_fee: null,
    is_active: true,
});

function createGroup() {
    addRouter.processing = true;
    router.post(base, { ...addForm }, {
        onSuccess: () => {
            showAdd.value = false;
            Object.assign(addForm, { group_key: '', label: '', under_age: null, default_fee: null, sort_order: 100 });
        },
        onFinish: () => { addRouter.processing = false; },
    });
}

function startEdit(group) {
    editId.value = group.id;
    Object.assign(editForm, {
        label: group.label,
        under_age: group.under_age,
        sort_order: group.sort_order,
        default_fee: group.default_fee,
        is_active: group.is_active,
    });
}

function saveEdit(group) {
    router.put(`${base}/${group.id}`, { ...editForm }, {
        preserveScroll: true,
        onSuccess: () => { editId.value = null; },
    });
}

function removeGroup(group) {
    if (!confirm(`Remove ${group.label}? In-use categories will be deactivated instead.`)) return;
    router.delete(`${base}/${group.id}`, { preserveScroll: true });
}

function confirmReset() {
    if (!confirm('Reset all age categories to system defaults? Custom categories will be removed.')) return;
    router.post(`${base}/reset-defaults`, {}, { preserveScroll: true });
}
</script>
