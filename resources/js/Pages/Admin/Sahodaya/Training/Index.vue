<template>
    <SahodayaAdminLayout title="Teacher Training" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Teacher training" eyebrow="Programs"
                    description="Manage training programs, registrations, sessions, and certificates." />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.programs }}</p>
                <p class="text-xs text-slate-500 mt-1">Programs</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ stats.open }}</p>
                <p class="text-xs text-slate-500 mt-1">Registration open</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.registrations }}</p>
                <p class="text-xs text-slate-500 mt-1">Registrations</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.sessions }}</p>
                <p class="text-xs text-slate-500 mt-1">Sessions</p>
            </div>
        </div>

        <form @submit.prevent="createProgram" class="card mb-4 flex flex-wrap gap-2 items-end">
            <div class="flex-1 min-w-[12rem]">
                <label class="block text-xs font-medium text-slate-600 mb-1">Program title</label>
                <input v-model="form.title" class="field w-full" placeholder="Program title" required>
            </div>
            <div class="min-w-[10rem]">
                <label class="block text-xs font-medium text-slate-600 mb-1">Category</label>
                <select v-model="form.category_id" class="field w-full">
                    <option value="">— None —</option>
                    <option v-for="c in activeCategories" :key="c.id" :value="c.id">{{ c.label }}</option>
                </select>
            </div>
            <button class="btn-primary">Create program</button>
        </form>

        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div class="min-w-[12rem]">
                <label class="block text-xs font-medium text-slate-600 mb-1">Filter by category</label>
                <select :value="filters.category_id ?? ''" class="field w-full" @change="filterCategory">
                    <option value="">All categories</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.label }}{{ c.is_active ? '' : ' (inactive)' }}</option>
                </select>
            </div>
            <form @submit.prevent="createCategory" class="flex flex-wrap gap-2 items-end flex-1">
                <div class="flex-1 min-w-[10rem]">
                    <label class="block text-xs font-medium text-slate-600 mb-1">New category</label>
                    <input v-model="categoryForm.label" class="field w-full" placeholder="Category label" required>
                </div>
                <button type="submit" class="btn-secondary" :disabled="categoryForm.processing">Add category</button>
            </form>
        </div>

        <div class="card overflow-hidden p-0">
            <EmptyState v-if="!programs.length" title="No training programs yet" description="Create your first program using the form above." icon="🎓" />
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Code</th>
                        <th>Category</th>
                        <th>Registrations</th>
                        <th>Sessions</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in programs" :key="p.id">
                        <td class="font-medium text-slate-900">{{ p.title }}</td>
                        <td class="text-slate-600 font-mono text-xs">{{ p.code || '—' }}</td>
                        <td class="text-slate-600">{{ p.category?.label ?? '—' }}</td>
                        <td>{{ p.registrations_count ?? 0 }}</td>
                        <td>{{ p.sessions_count ?? 0 }}</td>
                        <td class="text-right">
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${p.id}`" class="link-brand">Manage →</Link>
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
