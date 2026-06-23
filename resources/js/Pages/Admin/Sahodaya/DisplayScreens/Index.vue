<template>
    <SahodayaAdminLayout title="Display Screens" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <form @submit.prevent="create" class="bg-white border rounded-xl p-4 mb-4 grid md:grid-cols-4 gap-2">
            <input v-model="form.title" class="field" placeholder="Screen title" required>
            <input v-model="form.slug" class="field" placeholder="slug (optional)">
            <select v-model="form.event_id" class="field">
                <option value="">No event linked</option>
                <option v-for="e in events" :key="e.id" :value="e.id">{{ e.title }}</option>
            </select>
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Create screen</button>
        </form>

        <ul class="bg-white border rounded-xl divide-y">
            <li v-for="screen in screens" :key="screen.id" class="p-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-medium">{{ screen.title }}</p>
                    <p class="text-xs text-gray-500">
                        /display/{{ sahodaya.id }}/{{ screen.slug }}
                        <span v-if="!screen.is_active" class="text-amber-600"> · inactive</span>
                    </p>
                </div>
                <div class="flex gap-2 items-center">
                    <a :href="`/display/${sahodaya.id}/${screen.slug}`" target="_blank" rel="noopener"
                       class="text-indigo-600 text-sm">Open ↗</a>
                    <button @click="toggle(screen)" class="text-xs text-gray-600">{{ screen.is_active ? 'Disable' : 'Enable' }}</button>
                    <button @click="remove(screen.id)" class="text-xs text-red-600">Delete</button>
                </div>
            </li>
            <li v-if="!screens.length" class="p-6 text-center text-gray-400 text-sm">No display screens yet</li>
        </ul>
    </SahodayaAdminLayout>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    screens: Array, events: Array,
});

const form = useForm({ title: '', slug: '', event_id: '' });

function create() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/display-screens`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function toggle(screen) {
    router.put(`/sahodaya-admin/${props.sahodaya.id}/display-screens/${screen.id}`, {
        title: screen.title,
        event_id: screen.config_json?.event_id ?? null,
        is_active: !screen.is_active,
    }, { preserveScroll: true });
}

function remove(id) {
    if (!confirm('Delete this display screen?')) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/display-screens/${id}`, { preserveScroll: true });
}
</script>

<style scoped>
@reference "../../../../../css/app.css";
.field { @apply border border-gray-200 rounded-lg px-3 py-2 text-sm; }
</style>
