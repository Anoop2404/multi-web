<template>
    <AdminLayout title="Subjects">
        <div class="max-w-7xl mx-auto space-y-6">
            <MasterDataNav
                title="Global Subjects"
                description="Manage standard academic subjects across CBSE curriculum, teacher allocations, talent search, and examination masters."
                active-tab="subjects"
                :count="subjects.length"
            >
                <template #actions>
                    <button
                        type="button"
                        @click="openAddModal"
                        class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700 active:bg-indigo-800 transition shadow-xs hover:shadow"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Add Subject</span>
                    </button>
                </template>
            </MasterDataNav>

            <!-- Metrics bar -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-extrabold text-lg">
                        📖
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Subjects</p>
                        <p class="text-xl font-black text-slate-900 leading-tight">{{ subjects.length }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 font-extrabold text-lg">
                        ✓
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Active</p>
                        <p class="text-xl font-black text-emerald-600 leading-tight">{{ activeCount }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] flex items-center gap-3 col-span-2 sm:col-span-1">
                    <div class="w-10 h-10 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-400 font-extrabold text-lg">
                        ⏸
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Inactive</p>
                        <p class="text-xl font-black text-slate-500 leading-tight">{{ subjects.length - activeCount }}</p>
                    </div>
                </div>
            </div>

            <!-- Search & Filter bar -->
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
                <div class="relative flex-1 max-w-md">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search by code or subject name..."
                        class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-slate-500">Status:</label>
                    <select
                        v-model="statusFilter"
                        class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                    >
                        <option value="all">All Statuses</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                </div>
            </div>

            <!-- Table Card -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/75 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                <th class="py-3 px-4 w-16">Sort</th>
                                <th class="py-3 px-4">Subject Code</th>
                                <th class="py-3 px-4">Subject Name</th>
                                <th class="py-3 px-4">Category</th>
                                <th class="py-3 px-4">Scope</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <tr
                                v-for="s in filteredSubjects"
                                :key="s.id"
                                class="hover:bg-slate-50/70 transition-colors"
                            >
                                <td class="py-3.5 px-4 text-xs font-mono text-slate-400">
                                    {{ s.sort_order ?? 0 }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-mono font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                        {{ s.code }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-slate-900">
                                    {{ s.label }}
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-500 capitalize">
                                    <span v-if="s.category" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 border border-purple-100">
                                        {{ s.category }}
                                    </span>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md">
                                        🌐 Global
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <button
                                        type="button"
                                        @click="toggleActive(s)"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold transition border"
                                        :class="s.is_active
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'
                                            : 'bg-slate-100 text-slate-500 border-slate-200 hover:bg-slate-200'"
                                        :title="s.is_active ? 'Click to deactivate' : 'Click to activate'"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="s.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                        <span>{{ s.is_active ? 'Active' : 'Inactive' }}</span>
                                    </button>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <button
                                        type="button"
                                        @click="openEditModal(s)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition"
                                    >
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <span>Edit</span>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredSubjects.length === 0">
                                <td colspan="7" class="py-12 text-center">
                                    <div class="max-w-xs mx-auto text-slate-400">
                                        <p class="text-3xl mb-2">🔍</p>
                                        <p class="font-bold text-slate-700">No subjects found</p>
                                        <p class="text-xs mt-1">Try adjusting your search or add a new global subject.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Modal -->
            <Modal :show="showAddModal" title="Add Subject" subtitle="Register a new academic subject code for CBSE." @close="showAddModal = false">
                <form @submit.prevent="submitAdd" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Subject Code *</label>
                            <input
                                v-model="addForm.code"
                                required
                                maxlength="30"
                                placeholder="e.g. 041, PHY, ENG"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono uppercase focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Sort Order</label>
                            <input
                                v-model.number="addForm.sort_order"
                                type="number"
                                min="0"
                                placeholder="0"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Subject Name *</label>
                        <input
                            v-model="addForm.label"
                            required
                            maxlength="100"
                            placeholder="e.g. Mathematics Standard, Physics, English Core"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                        />
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="showAddModal = false"
                            class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="addForm.processing"
                            class="px-4 py-2 rounded-xl bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {{ addForm.processing ? 'Saving...' : 'Create Subject' }}
                        </button>
                    </div>
                </form>
            </Modal>

            <!-- Edit Modal -->
            <Modal :show="showEditModal" title="Edit Subject" subtitle="Update subject name, order, and status." @close="showEditModal = false">
                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Subject Code *</label>
                            <input
                                v-model="editForm.code"
                                required
                                maxlength="30"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono uppercase focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Sort Order</label>
                            <input
                                v-model.number="editForm.sort_order"
                                type="number"
                                min="0"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Subject Name *</label>
                        <input
                            v-model="editForm.label"
                            required
                            maxlength="100"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                        />
                    </div>

                    <div class="pt-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="editForm.is_active"
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                            />
                            <span class="text-sm font-semibold text-slate-700">Active (Available for school curricula and teacher mapping)</span>
                        </label>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="showEditModal = false"
                            class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="px-4 py-2 rounded-xl bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {{ editForm.processing ? 'Saving...' : 'Update Subject' }}
                        </button>
                    </div>
                </form>
            </Modal>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MasterDataNav from '@/Pages/Admin/MasterData/MasterDataNav.vue';
import Modal from '@/Components/ui/Modal.vue';

const props = defineProps({
    subjects: { type: Array, default: () => [] },
});

const search = ref('');
const statusFilter = ref('all');

const activeCount = computed(() => props.subjects.filter(s => s.is_active).length);

const filteredSubjects = computed(() => {
    return props.subjects.filter(s => {
        const matchesSearch = !search.value
            || s.code?.toLowerCase().includes(search.value.toLowerCase())
            || s.label?.toLowerCase().includes(search.value.toLowerCase());

        const matchesStatus = statusFilter.value === 'all'
            || (statusFilter.value === 'active' && s.is_active)
            || (statusFilter.value === 'inactive' && !s.is_active);

        return matchesSearch && matchesStatus;
    });
});

// Add Modal
const showAddModal = ref(false);
const addForm = useForm({
    code: '',
    label: '',
    sort_order: props.subjects.length,
});

function openAddModal() {
    addForm.reset();
    addForm.sort_order = props.subjects.length;
    showAddModal.value = true;
}

function submitAdd() {
    addForm.post('/admin/master-data/subjects', {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
}

// Edit Modal
const showEditModal = ref(false);
const editingSubject = ref(null);
const editForm = useForm({
    code: '',
    label: '',
    sort_order: 0,
    is_active: true,
});

function openEditModal(s) {
    editingSubject.value = s;
    editForm.code = s.code;
    editForm.label = s.label;
    editForm.sort_order = s.sort_order ?? 0;
    editForm.is_active = Boolean(s.is_active);
    showEditModal.value = true;
}

function submitEdit() {
    if (!editingSubject.value) return;
    editForm.put(`/admin/master-data/subjects/${editingSubject.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingSubject.value = null;
        },
    });
}

function toggleActive(s) {
    router.put(`/admin/master-data/subjects/${s.id}`, {
        code: s.code,
        label: s.label,
        sort_order: s.sort_order ?? 0,
        is_active: !s.is_active,
    }, {
        preserveScroll: true,
    });
}
</script>
