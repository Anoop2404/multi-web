<template>
    <SahodayaAdminLayout title="Teacher Training" :sahodaya="sahodaya" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount">
        <form @submit.prevent="createProgram" class="bg-white border rounded-xl p-4 mb-4">
            <input v-model="form.title" class="field mb-2" placeholder="Program title" required>
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Create</button>
        </form>
        <ul class="bg-white border rounded-xl divide-y">
            <li v-for="p in programs" :key="p.id" class="p-4 flex justify-between">
                <span>{{ p.title }}</span>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${p.id}`" class="text-indigo-600 text-sm">Manage</Link>
            </li>
        </ul>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, programs: Array });
const form = useForm({ title: '' });
function createProgram() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/training`, { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>
<style scoped>
@reference "../../../../../css/app.css";
.field { @apply w-full border border-gray-200 rounded-lg px-3 py-2 text-sm; }
</style>
