<template>
    <AdminLayout title="Designations">
        <div class="max-w-3xl mx-auto space-y-6">
            <PageHeader title="Global Designations" eyebrow="Master data" />
            <form @submit.prevent="add" class="card flex flex-wrap gap-2">
                <input v-model="form.code" required class="field w-24" placeholder="Code">
                <input v-model="form.label" required class="field flex-1" placeholder="Label">
                <button type="submit" class="btn-primary">Add</button>
            </form>
            <div v-for="d in designations" :key="d.id" class="card text-sm">{{ d.code }} — {{ d.label }}</div>
        </div>
    </AdminLayout>
</template>
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm } from '@inertiajs/vue3';
defineProps({ designations: Array });
const form = useForm({ code: '', label: '' });
function add() { form.post('/admin/master-data/designations', { onSuccess: () => form.reset() }); }
</script>
