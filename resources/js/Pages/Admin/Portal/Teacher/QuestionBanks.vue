<template>
    <PortalLayout
        role-label="Teacher Portal"
        :title="teacher.name"
        :subtitle="school.name"
        accent="indigo"
        :nav-items="navItems"
    >
        <form @submit.prevent="createBank" class="card mb-4 grid sm:grid-cols-2 gap-2">
            <h2 class="sm:col-span-2 font-semibold text-sm">New question bank</h2>
            <input v-model="form.title" class="field" placeholder="Bank title" required>
            <input v-model="form.subject" class="field" placeholder="Subject" required>
            <select v-model="form.class_group" class="field">
                <option value="">Class (optional)</option>
                <option v-for="(label, key) in classGroups" :key="key" :value="key">{{ label }}</option>
            </select>
            <textarea v-model="form.description" class="field sm:col-span-2" rows="2" placeholder="Notes (optional)"></textarea>
            <button class="px-4 py-2 text-white rounded-lg text-sm w-fit" :disabled="form.processing">Create bank</button>
        </form>

        <ul class="card-list">
            <li v-for="bank in banks" :key="bank.id" class="p-4 flex justify-between items-center">
                <div>
                    <p class="font-medium">{{ bank.title }}</p>
                    <p class="text-xs text-gray-500">{{ bank.subject }} · {{ bank.questions_count }} question(s)</p>
                </div>
                <a :href="`/portal/teacher/${school.id}/question-banks/${bank.id}`" class="text-indigo-600 text-sm font-medium">Open →</a>
            </li>
            <li v-if="!banks.length" class="p-6 text-center text-gray-400 text-sm">No question banks yet. Create one above.</li>
        </ul>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { teacherPortalNavItems } from '@/support/teacherPortalNav.js';

const props = defineProps({ school: Object, teacher: Object, banks: Array, classGroups: Object });
const form = useForm({ title: '', subject: props.teacher.subject || '', class_group: '', description: '' });

function createBank() {
    form.post(`/portal/teacher/${props.school.id}/question-banks`, { preserveScroll: true, onSuccess: () => form.reset('title', 'description') });
}

const navItems = computed(() => teacherPortalNavItems(props.school.id));
</script>

