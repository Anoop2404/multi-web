<template>
    <SahodayaAdminLayout title="Teacher Training" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Teacher training" eyebrow="Programs"
                    description="Manage training programs, registrations, sessions, and certificates.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/resource-persons`" class="btn-secondary text-sm flex items-center gap-1.5">
                    <span>👩‍🏫 Resource Persons</span>
                </Link>
            </template>
        </PageHeader>

        <!-- Executive Stat Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card !p-4 border border-indigo-200/80 bg-gradient-to-br from-indigo-50/80 to-blue-50/40 shadow-sm hover:shadow transition">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-800">Total Programs</p>
                    <span class="w-7 h-7 rounded-md bg-indigo-100/80 flex items-center justify-center text-indigo-700 text-xs">🎓</span>
                </div>
                <p class="text-2xl font-black text-indigo-950 mt-2 tabular-nums">{{ stats.programs }}</p>
                <p class="text-[11px] text-indigo-700 mt-0.5 font-medium">Training modules</p>
            </div>

            <div class="card !p-4 border border-emerald-200/80 bg-gradient-to-br from-emerald-50/80 to-teal-50/40 shadow-sm hover:shadow transition">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Registration Open</p>
                    <span class="w-7 h-7 rounded-md bg-emerald-100/80 flex items-center justify-center text-emerald-700 text-xs">🟢</span>
                </div>
                <p class="text-2xl font-black text-emerald-950 mt-2 tabular-nums">{{ stats.open }}</p>
                <p class="text-[11px] text-emerald-700 mt-0.5 font-medium">Active for teachers</p>
            </div>

            <div class="card !p-4 border border-blue-200/80 bg-gradient-to-br from-blue-50/80 to-cyan-50/40 shadow-sm hover:shadow transition">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-blue-800">Total Enrolled</p>
                    <span class="w-7 h-7 rounded-md bg-blue-100/80 flex items-center justify-center text-blue-700 text-xs">👥</span>
                </div>
                <p class="text-2xl font-black text-blue-950 mt-2 tabular-nums">{{ Number(stats.registrations).toLocaleString('en-IN') }}</p>
                <p class="text-[11px] text-blue-700 mt-0.5 font-medium">Teacher registrations</p>
            </div>

            <div class="card !p-4 border border-amber-200/80 bg-gradient-to-br from-amber-50/80 to-yellow-50/40 shadow-sm hover:shadow transition">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-amber-800">Sessions</p>
                    <span class="w-7 h-7 rounded-md bg-amber-100/80 flex items-center justify-center text-amber-700 text-xs">📅</span>
                </div>
                <p class="text-2xl font-black text-amber-950 mt-2 tabular-nums">{{ stats.sessions }}</p>
                <p class="text-[11px] text-amber-700 mt-0.5 font-medium">CPD training sessions</p>
            </div>
        </div>

        <!-- Creation & Category Toolbar -->
        <div class="grid lg:grid-cols-2 gap-4 mb-6">
            <form @submit.prevent="createProgram" class="card !p-5 bg-white border border-slate-200 shadow-xs rounded-xl flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-[14rem]">
                    <label class="block text-xs font-bold text-slate-700 mb-1">New Program Title</label>
                    <input v-model="form.title" class="form-input text-sm w-full bg-slate-50 border-slate-200 rounded-lg" placeholder="e.g. Synergy for Class 9&10 Teachers" required>
                </div>
                <div class="min-w-[10rem]">
                    <label class="block text-xs font-bold text-slate-700 mb-1">Category</label>
                    <select v-model="form.category_id" class="form-input text-sm w-full bg-slate-50 border-slate-200 rounded-lg">
                        <option value="">— None —</option>
                        <option v-for="c in activeCategories" :key="c.id" :value="c.id">{{ c.label }}</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary text-sm px-4 !py-2 rounded-lg shadow-sm" :disabled="form.processing">
                    Create Program
                </button>
            </form>

            <div class="card !p-5 bg-white border border-slate-200 shadow-xs rounded-xl flex flex-wrap gap-3 items-end">
                <div class="min-w-[10rem]">
                    <label class="block text-xs font-bold text-slate-700 mb-1">Filter by Category</label>
                    <select :value="filters.category_id ?? ''" class="form-input text-sm w-full bg-slate-50 border-slate-200 rounded-lg" @change="filterCategory">
                        <option value="">All Categories</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.label }}{{ c.is_active ? '' : ' (inactive)' }}</option>
                    </select>
                </div>
                <form @submit.prevent="createCategory" class="flex flex-wrap gap-2 items-end flex-1 min-w-[14rem]">
                    <div class="flex-1 min-w-[8rem]">
                        <label class="block text-xs font-bold text-slate-700 mb-1">New Category</label>
                        <input v-model="categoryForm.label" class="form-input text-sm w-full bg-slate-50 border-slate-200 rounded-lg" placeholder="Category label" required>
                    </div>
                    <button type="submit" class="btn-secondary text-sm px-3 !py-2 rounded-lg" :disabled="categoryForm.processing">Add Category</button>
                </form>
            </div>
        </div>

        <!-- Programs Directory -->
        <div class="card overflow-hidden !p-0 bg-white border border-slate-200 rounded-xl shadow-xs">
            <EmptyState v-if="!programs.length" title="No training programs yet" description="Create your first program using the form above." icon="🎓" />
            <table v-else class="w-full text-sm">
                <thead class="bg-slate-50/80 border-b border-slate-200/80 text-xs font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Program Title</th>
                        <th class="px-4 py-3 text-left">Code</th>
                        <th class="px-4 py-3 text-left">Category</th>
                        <th class="px-4 py-3 text-center">Registrations</th>
                        <th class="px-4 py-3 text-center">Sessions</th>
                        <th class="px-4 py-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="p in programs" :key="p.id" class="hover:bg-slate-50/70 transition-colors">
                        <td class="px-4 py-3 font-bold text-slate-900">{{ p.title }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 font-semibold">{{ p.code || '—' }}</td>
                        <td class="px-4 py-3">
                            <span v-if="p.category?.label" class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-800 border border-indigo-100">
                                {{ p.category.label }}
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-center font-semibold text-slate-800">{{ p.registrations_count ?? 0 }}</td>
                        <td class="px-4 py-3 text-center font-semibold text-slate-800">{{ p.sessions_count ?? 0 }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${p.id}`" 
                                  class="inline-flex items-center gap-1 text-xs font-bold text-[#0f3d7a] hover:text-indigo-900 bg-slate-100 hover:bg-slate-200/80 px-3 py-1.5 rounded-lg transition-colors">
                                <span>Manage Workspace</span>
                                <span>→</span>
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    programs: Array,
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ category_id: null }) },
    stats: { type: Object, default: () => ({ programs: 0, open: 0, registrations: 0, sessions: 0 }) },
});

const activeCategories = computed(() => props.categories.filter(c => c.is_active));

const form = useForm({ title: '', category_id: '' });
const categoryForm = useForm({ label: '' });

function createProgram() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/training`, { preserveScroll: true, onSuccess: () => form.reset() });
}

function createCategory() {
    categoryForm.post(`/sahodaya-admin/${props.sahodaya.id}/training/categories`, {
        preserveScroll: true,
        onSuccess: () => categoryForm.reset(),
    });
}

function filterCategory(e) {
    const value = e.target.value;
    router.get(`/sahodaya-admin/${props.sahodaya.id}/training`, {
        category_id: value || undefined,
    }, { preserveState: true, replace: true });
}
</script>
