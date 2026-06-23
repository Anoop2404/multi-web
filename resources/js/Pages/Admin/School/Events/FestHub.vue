<template>
    <SchoolAdminLayout :title="`${event.title} — Fest Hub`" :school="school">
        <div class="grid sm:grid-cols-3 gap-3 mb-6">
            <Link :href="`/school-admin/${school.id}/fest/${event.id}/house`" class="bg-white border rounded-xl p-4 hover:border-indigo-300 text-sm font-medium">🏠 House standings</Link>
            <Link :href="`/school-admin/${school.id}/fest/${event.id}/catering`" class="bg-white border rounded-xl p-4 hover:border-indigo-300 text-sm font-medium">🍽 Meal requests</Link>
            <Link :href="`/school-admin/${school.id}/programs/kalotsav/registration`" class="bg-white border rounded-xl p-4 hover:border-indigo-300 text-sm font-medium">📝 Registrations</Link>
        </div>
        <div class="bg-white border rounded-xl p-4">
            <h3 class="font-semibold text-sm mb-3">Submit an appeal</h3>
            <form @submit.prevent="submitAppeal" class="space-y-2">
                <select v-model="appealForm.participant_id" class="field" required>
                    <option value="">Select participant</option>
                    <option v-for="p in allParticipants" :key="p.id" :value="p.id">
                        {{ p.student?.name }} — {{ p.registration?.item?.title }}
                    </option>
                </select>
                <textarea v-model="appealForm.reason" class="field h-20" placeholder="Reason for appeal" required></textarea>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Submit appeal</button>
            </form>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({ school: Object, event: Object, registrations: Array });

const appealForm = useForm({ participant_id: '', reason: '' });

const allParticipants = computed(() =>
    props.registrations.flatMap(r => (r.participants ?? []).map(p => ({ ...p, registration: r })))
);

function submitAppeal() {
    appealForm.post(`/school-admin/${props.school.id}/fest/${props.event.id}/appeals`, {
        preserveScroll: true, onSuccess: () => appealForm.reset(),
    });
}
</script>

<style scoped>
@reference "../../../../../css/app.css";
.field { @apply w-full border border-gray-200 rounded-lg px-3 py-2 text-sm; }
</style>
