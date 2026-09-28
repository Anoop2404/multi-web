<template>
    <AdminLayout title="Class Categories">
        <div class="max-w-7xl mx-auto space-y-6">
            <MasterDataNav
                title="CBSE Class Categories"
                description="Manage global class categories (e.g., Primary, Middle, Secondary, Senior Secondary) used across all Sahodaya clusters and schools."
                active-tab="class-categories"
                :count="categories.length"
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
                        <span>Add Category</span>
                    </button>
                </template>
            </MasterDataNav>

            <!-- Metrics bar -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-extrabold text-lg">
                        📚
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total</p>
                        <p class="text-xl font-black text-slate-900 leading-tight">{{ categories.length }}</p>
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
                        <p class="text-xl font-black text-slate-500 leading-tight">{{ categories.length - activeCount }}</p>
                    </div>
                </div>
            </div>

            <!-- Search and Filter bar -->
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
                        placeholder="Filter by code or category name..."
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

            <!-- Categories Table Card -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/75 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                <th class="py-3 px-4 w-16">Sort</th>
                                <th class="py-3 px-4">Code</th>
                                <th class="py-3 px-4">Category Label</th>
                                <th class="py-3 px-4">Class Range</th>
                                <th class="py-3 px-4">Scope</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <tr
                                v-for="c in filteredCategories"
                                :key="c.id"
                                class="hover:bg-slate-50/70 transition-colors"
                            >
                                <td class="py-3.5 px-4 text-xs font-mono text-slate-400">
                                    {{ c.sort_order ?? 0 }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-mono font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                        {{ c.code }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-slate-900">
                                    {{ c.label }}
                                </td>
                                <td class="py-3.5 px-4 text-slate-600">
                                    <span v-if="c.min_class && c.max_class" class="inline-flex items-center gap-1.5 text-xs font-medium bg-indigo-50/80 text-indigo-800 px-2.5 py-0.5 rounded-full border border-indigo-100">
                                        Class {{ c.min_class }} to {{ c.max_class }}
                                    </span>
                                    <span v-else-if="c.min_class" class="text-xs text-slate-500">From Class {{ c.min_class }}</span>
                                    <span v-else class="text-xs text-slate-400 italic">Not constrained</span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md">
                                        🌐 Global
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <button
                                        type="button"
                                        @click="toggleActive(c)"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold transition border"
                                        :class="c.is_active
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'
                                            : 'bg-slate-100 text-slate-500 border-slate-200 hover:bg-slate-200'"
                                        :title="c.is_active ? 'Click to deactivate' : 'Click to activate'"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="c.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                        <span>{{ c.is_active ? 'Active' : 'Inactive' }}</span>
                                    </button>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <button
                                        type="button"
                                        @click="openEditModal(c)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition"
                                    >
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <span>Edit</span>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredCategories.length === 0">
                                <td colspan="7" class="py-12 text-center">
                                    <div class="max-w-xs mx-auto text-slate-400">
                                        <p class="text-3xl mb-2">🔍</p>
                                        <p class="font-bold text-slate-700">No categories found</p>
                                        <p class="text-xs mt-1">Try adjusting your search or add a new global class category.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Modal -->
            <Modal :show="showAddModal" title="Add Class Category" subtitle="Define a global category across all school levels." @close="showAddModal = false">
                <form @submit.prevent="submitAdd" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Code *</label>
                            <input
                                v-model="addForm.code"
                                required
                                maxlength="20"
                                placeholder="e.g. PRI, MID, SEC"
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
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Category Label *</label>
                        <input
                            v-model="addForm.label"
                            required
                            maxlength="100"
                            placeholder="e.g. Primary Section (Classes 1 to 5)"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Min Class (1-12)</label>
                            <input
                                v-model.number="addForm.min_class"
                                type="number"
                                min="1"
                                max="12"
                                placeholder="1"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Max Class (1-12)</label>
                            <input
                                v-model.number="addForm.max_class"
                                type="number"
                                min="1"
                                max="12"
                                placeholder="5"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                            />
                        </div>
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
                            {{ addForm.processing ? 'Saving...' : 'Create Category' }}
                        </button>
                    </div>
                </form>
            </Modal>

            <!-- Edit Modal -->
            <Modal :show="showEditModal" title="Edit Class Category" subtitle="Update category configuration and status." @close="showEditModal = false">
                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Code *</label>
                            <input
                                v-model="editForm.code"
                                required
                                maxlength="20"
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
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Category Label *</label>
                        <input
                            v-model="editForm.label"
                            required
                            maxlength="100"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Min Class (1-12)</label>
                            <input
                                v-model.number="editForm.min_class"
                                type="number"
                                min="1"
                                max="12"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Max Class (1-12)</label>
                            <input
                                v-model.number="editForm.max_class"
                                type="number"
                                min="1"
                                max="12"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                            />
                        </div>
                    </div>

                    <div class="pt-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="editForm.is_active"
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                            />
                            <span class="text-sm font-semibold text-slate-700">Active (Available for school registration and grouping)</span>
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
                            {{ editForm.processing ? 'Saving...' : 'Update Category' }}
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
    categories: { type: Array, default: () => [] },
});

const search = ref('');
const statusFilter = ref('all');

const activeCount = computed(() => props.categories.filter(c => c.is_active).length);

const filteredCategories = computed(() => {
    return props.categories.filter(c => {
        const matchesSearch = !search.value
            || c.code?.toLowerCase().includes(search.value.toLowerCase())
            || c.label?.toLowerCase().includes(search.value.toLowerCase());

        const matchesStatus = statusFilter.value === 'all'
            || (statusFilter.value === 'active' && c.is_active)
            || (statusFilter.value === 'inactive' && !c.is_active);

        return matchesSearch && matchesStatus;
    });
});

// Add Modal
const showAddModal = ref(false);
const addForm = useForm({
    code: '',
    label: '',
    min_class: null,
    max_class: null,
    sort_order: props.categories.length,
});

function openAddModal() {
    addForm.reset();
    addForm.sort_order = props.categories.length;
    showAddModal.value = true;
}

function submitAdd() {
    addForm.post('/admin/master-data/class-categories', {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
}

// Edit Modal
const showEditModal = ref(false);
const editingCategory = ref(null);
const editForm = useForm({
    code: '',
    label: '',
    min_class: null,
    max_class: null,
    sort_order: 0,
    is_active: true,
});

function openEditModal(c) {
    editingCategory.value = c;
    editForm.code = c.code;
    editForm.label = c.label;
    editForm.min_class = c.min_class;
    editForm.max_class = c.max_class;
    editForm.sort_order = c.sort_order ?? 0;
    editForm.is_active = Boolean(c.is_active);
    showEditModal.value = true;
}

function submitEdit() {
    if (!editingCategory.value) return;
    editForm.put(`/admin/master-data/class-categories/${editingCategory.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingCategory.value = null;
        },
    });
}

function toggleActive(c) {
    router.put(`/admin/master-data/class-categories/${c.id}`, {
        code: c.code,
        label: c.label,
        min_class: c.min_class,
        max_class: c.max_class,
        sort_order: c.sort_order ?? 0,
        is_active: !c.is_active,
    }, {
        preserveScroll: true,
    });
}
</script>
