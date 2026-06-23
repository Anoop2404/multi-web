<template>
    <SahodayaAdminLayout :title="program.title" :sahodaya="sahodaya" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount">
        <form @submit.prevent="save" class="bg-white border rounded-xl p-4 mb-4 space-y-2">
            <input v-model="form.title" class="field" required>
            <textarea v-model="form.description" class="field" rows="2"></textarea>
            <select v-model="form.status" class="field">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="completed">Completed</option>
            </select>
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Save</button>
        </form>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white border rounded-xl p-4">
                <h4 class="font-semibold text-sm mb-2">Sessions ({{ program.sessions?.length ?? 0 }})</h4>
                <form @submit.prevent="addSession" class="flex gap-2">
                    <input v-model="sessionForm.title" class="field flex-1" placeholder="Session title" required>
                    <button class="px-2 py-1 bg-gray-900 text-white rounded text-xs">Add</button>
                </form>
            </div>
            <div class="bg-white border rounded-xl p-4">
                <h4 class="font-semibold text-sm mb-2">Registrations</h4>
                <ul class="text-sm divide-y">
                    <li v-for="r in program.registrations" :key="r.id" class="py-2 flex justify-between">
                        <span>{{ r.teacher?.name }}</span>
                        <span class="text-gray-400">{{ r.status }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, program: Object });
const form = useForm({ title: props.program.title, description: props.program.description ?? '', status: props.program.status });
const sessionForm = useForm({ title: '' });
function save() { form.put(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}`, { preserveScroll: true }); }
function addSession() {
    sessionForm.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/sessions`, { preserveScroll: true, onSuccess: () => sessionForm.reset() });
}
</script>
<style scoped>
@reference "../../../../../css/app.css";
.field { @apply w-full border border-gray-200 rounded-lg px-3 py-2 text-sm; }
</style>
