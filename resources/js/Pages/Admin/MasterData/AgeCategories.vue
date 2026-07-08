<template>
    <AdminLayout title="Age Categories">
        <div class="max-w-3xl mx-auto space-y-6">
            <PageHeader title="Global Age Categories" eyebrow="Master data" />
            <form @submit.prevent="add" class="card grid sm:grid-cols-2 gap-2">
                <input v-model="form.code" required class="field" placeholder="Code (U10)">
                <input v-model="form.label" required class="field" placeholder="Label">
                <input v-model.number="form.max_age" type="number" required class="field" placeholder="Max age">
                <input v-model="form.cutoff_date" required class="field" placeholder="Cutoff MM-DD">
                <button type="submit" class="btn-primary sm:col-span-2">Add</button>
            </form>
            <div v-for="c in categories" :key="c.id" class="card text-sm">{{ c.code }} — {{ c.label }} (max {{ c.max_age }}, cutoff {{ c.cutoff_date }})</div>
        </div>
    </AdminLayout>
</template>
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm } from '@inertiajs/vue3';
defineProps({ categories: Array });
const form = useForm({ code: '', label: '', max_age: 10, cutoff_date: '12-31' });
function add() { form.post('/admin/master-data/age-categories', { onSuccess: () => form.reset() }); }
</script>
