<template>
    <SahodayaAdminLayout title="Regions" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="approvedSchoolsCount"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-6">
            <PageHeader
                title="Regions"
                eyebrow="Membership · Kalotsav"
                :description="`Group member schools into regions for Kalotsav (State → Sahodaya → Region → School). Academic year ${academicYear}.`"
            >
                <template #actions>
                    <button type="button" class="btn-primary text-sm" @click="showAdd = !showAdd">+ Add region</button>
                </template>
            </PageHeader>

            <div class="notice-banner notice-banner--info text-sm">
                <p class="font-semibold text-[#0f3d7a] mb-1">How regions work</p>
                <ul class="list-disc pl-4 space-y-1 text-slate-700">
                    <li>Regions are optional. Use them only for Kalotsav when your Sahodaya runs regional rounds.</li>
                    <li>Assign each school to a region below, or let schools pick their region during annual registration.</li>
                    <li>Assignments are per academic year ({{ academicYear }}).</li>
                </ul>
            </div>

            <!-- Add / edit region form -->
            <form v-if="showAdd" @submit.prevent="createRegion" class="card space-y-3">
                <p class="font-semibold text-sm">New region</p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-1">
                        <label class="label-xs">Name</label>
                        <input v-model="addForm.name" class="field" placeholder="Tirur Region" required>
                    </div>
                    <div>
                        <label class="label-xs">Code (optional)</label>
                        <input v-model="addForm.code" class="field font-mono" placeholder="tirur">
                        <p class="text-[10px] text-gray-400 mt-0.5">Auto-generated from name if blank.</p>
                    </div>
                    <div class="sm:col-span-1">
                        <label class="label-xs">Description (optional)</label>
                        <input v-model="addForm.description" class="field" placeholder="Schools around Tirur">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary text-sm" :disabled="processing">Create</button>
                    <button type="button" class="btn-ghost text-sm" @click="showAdd = false">Cancel</button>
                </div>
            </form>

            <!-- Regions table -->
            <div>
                <h3 class="text-sm font-semibold text-slate-700 mb-2">Regions ({{ regions.length }})</h3>
                <div v-if="regions.length === 0" class="card text-sm text-slate-500">
                    No regions yet. Add one to start grouping schools.
                </div>
                <div v-else class="rounded-xl border border-gray-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="p-3">Name</th>
                                <th class="p-3">Code</th>
                                <th class="p-3">Description</th>
                                <th class="p-3">Schools</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 w-28"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="region in regions" :key="region.id" class="bg-white">
                                <td class="p-3">
                                    <input v-if="editId === region.id" v-model="editForm.name" class="field !py-1 !text-xs">
                                    <span v-else class="font-semibold text-[#0f3d7a]">{{ region.name }}</span>
                                </td>
                                <td class="p-3 font-mono text-xs text-slate-500">{{ region.code }}</td>
                                <td class="p-3">
                                    <input v-if="editId === region.id" v-model="editForm.description" class="field !py-1 !text-xs">
                                    <span v-else class="text-slate-600">{{ region.description || '—' }}</span>
                                </td>
                                <td class="p-3">{{ countForRegion(region.id) }}</td>
                                <td class="p-3">
                                    <label v-if="editId === region.id" class="flex items-center gap-1 text-xs">
                                        <input v-model="editForm.is_active" type="checkbox" class="rounded"> Active
                                    </label>
                                    <span v-else :class="region.is_active ? 'text-green-700' : 'text-gray-400'" class="text-xs font-semibold">
                                        {{ region.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    <template v-if="editId === region.id">
                                        <button type="button" class="text-xs font-semibold text-[#0f3d7a] mr-2" @click="saveEdit(region)">Save</button>
                                        <button type="button" class="text-xs text-gray-500" @click="editId = null">Cancel</button>
                                    </template>
                                    <template v-else>
                                        <button type="button" class="text-xs font-semibold text-[#0f3d7a] mr-2" @click="startEdit(region)">Edit</button>
                                        <button type="button" class="text-xs text-red-600" @click="removeRegion(region)">Remove</button>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Assign schools -->
            <div v-if="regions.length">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-semibold text-slate-700">Assign schools to regions</h3>
                    <button type="button" class="btn-primary text-sm" :disabled="processing || !dirty" @click="saveAssignments">
                        Save assignments
                    </button>
                </div>
                <div v-if="schools.length === 0" class="card text-sm text-slate-500">
                    No approved schools yet.
                </div>
                <div v-else class="rounded-xl border border-gray-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="p-3">School</th>
                                <th class="p-3">Code</th>
                                <th class="p-3 w-64">Region</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="school in schools" :key="school.id" class="bg-white">
                                <td class="p-3 font-medium text-slate-700">{{ school.name }}</td>
                                <td class="p-3 font-mono text-xs text-slate-500">{{ school.school_prefix || '—' }}</td>
                                <td class="p-3">
                                    <select v-model="assignMap[school.id]" class="field !py-1 !text-xs" @change="dirty = true">
                                        <option :value="null">— Unassigned —</option>
                                        <option v-for="region in activeRegions" :key="region.id" :value="region.id">
                                            {{ region.name }}
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    approvedSchoolsCount: Number,
    pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number,
    pendingPaymentsCount: Number,
    regions: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    academicYear: String,
});

const base = `/sahodaya-admin/${props.sahodaya.id}/regions`;
const showAdd = ref(false);
const editId = ref(null);
const processing = ref(false);
const dirty = ref(false);

const addForm = reactive({ name: '', code: '', description: '' });
const editForm = reactive({ name: '', code: '', description: '', is_active: true });

const assignMap = reactive(
    Object.fromEntries(props.schools.map((s) => [s.id, s.region_id ?? null])),
);

const activeRegions = computed(() => props.regions.filter((r) => r.is_active));

function countForRegion(regionId) {
    return Object.values(assignMap).filter((v) => v === regionId).length;
}

function createRegion() {
    processing.value = true;
    router.post(base, { ...addForm }, {
        preserveScroll: true,
        onSuccess: () => {
            showAdd.value = false;
            Object.assign(addForm, { name: '', code: '', description: '' });
        },
        onFinish: () => { processing.value = false; },
    });
}

function startEdit(region) {
    editId.value = region.id;
    Object.assign(editForm, {
        name: region.name,
        code: region.code,
        description: region.description,
        is_active: region.is_active,
    });
}

function saveEdit(region) {
    router.put(`${base}/${region.id}`, { ...editForm }, {
        preserveScroll: true,
        onSuccess: () => { editId.value = null; },
    });
}

function removeRegion(region) {
    if (!confirm(`Remove "${region.name}"? Schools in it will become unassigned.`)) return;
    router.delete(`${base}/${region.id}`, { preserveScroll: true });
}

function saveAssignments() {
    processing.value = true;
    const assignments = props.schools.map((s) => ({
        school_id: s.id,
        region_id: assignMap[s.id] ?? null,
    }));
    router.post(`${base}/assign`, { assignments }, {
        preserveScroll: true,
        onSuccess: () => { dirty.value = false; },
        onFinish: () => { processing.value = false; },
    });
}
</script>
