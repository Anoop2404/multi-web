<template>
    <AdminLayout title="Class Categories">
        <div class="max-w-3xl mx-auto space-y-6">
            <PageHeader title="Global CBSE Class Categories" eyebrow="Master data"
                        description="Manage class category codes used across all Sahodaya tenants." />
            <form @submit.prevent="add" class="card flex flex-wrap gap-2">
                <FormField label="Code" class-extra="w-24">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.code" required class="field">
                    </template>
                </FormField>
                <FormField label="Label" class-extra="flex-1 min-w-[12rem]">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.label" required class="field">
                    </template>
                </FormField>
                <div class="flex items-end">
                    <button type="submit" class="btn-primary" :disabled="form.processing">Add</button>
                </div>
            </form>
            <div v-for="c in categories" :key="c.id" class="card flex justify-between text-sm">
                <span>{{ c.code }} — {{ c.label }} ({{ c.min_class }}-{{ c.max_class }})</span>
                <span :class="c.is_active ? 'text-green-600' : 'text-gray-400'">{{ c.is_active ? 'Active' : 'Inactive' }}</span>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FormField from '@/Components/ui/FormField.vue';
import { useForm } from '@inertiajs/vue3';

defineProps({ categories: Array });
const form = useForm({ code: '', label: '', min_class: null, max_class: null, sort_order: 0 });
function add() { form.post('/admin/master-data/class-categories', { onSuccess: () => form.reset() }); }
</script>
