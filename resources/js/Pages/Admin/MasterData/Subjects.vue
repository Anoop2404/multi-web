<template>
    <AdminLayout title="Subjects">
        <div class="max-w-3xl mx-auto space-y-6">
            <PageHeader title="Global Subjects" eyebrow="Master data" description="Shared subject master for teachers, Talent Search, and training." />
            <form @submit.prevent="add" class="card flex flex-wrap gap-2">
                <FormField label="Code" class-extra="w-24"><template #default="{ id }"><input :id="id" v-model="form.code" required class="field"></template></FormField>
                <FormField label="Label" class-extra="flex-1"><template #default="{ id }"><input :id="id" v-model="form.label" required class="field"></template></FormField>
                <div class="flex items-end"><button type="submit" class="btn-primary">Add</button></div>
            </form>
            <div v-for="s in subjects" :key="s.id" class="card flex justify-between text-sm"><span>{{ s.code }} — {{ s.label }}</span></div>
        </div>
    </AdminLayout>
</template>
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FormField from '@/Components/ui/FormField.vue';
import { useForm } from '@inertiajs/vue3';
defineProps({ subjects: Array });
const form = useForm({ code: '', label: '' });
function add() { form.post('/admin/master-data/subjects', { onSuccess: () => form.reset() }); }
</script>
