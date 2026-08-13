<template>
    <PortalLayout
        role-label="Teacher Portal"
        :title="teacher.name"
        :subtitle="school.name"
        accent="navy"
        :nav-items="navItems"
    >
        <form @submit.prevent="createBank" class="card mb-5">
            <h2 class="section-title text-base mb-3">New question bank</h2>
            <FormGrid>
                <FormField label="Bank title" required :error="form.errors.title">
                    <input v-model="form.title" class="field" required>
                </FormField>
                <FormField label="Subject" required :error="form.errors.subject">
                    <input v-model="form.subject" class="field" required>
                </FormField>
                <FormField label="Class (optional)" :error="form.errors.class_group">
                    <select v-model="form.class_group" class="field">
                        <option value="">Select class</option>
                        <option v-for="(label, key) in classGroups" :key="key" :value="key">{{ label }}</option>
                    </select>
                </FormField>
                <FormField label="Notes (optional)" class-extra="sm:col-span-2" :error="form.errors.description">
                    <textarea v-model="form.description" class="field" rows="2"></textarea>
                </FormField>
            </FormGrid>
            <FormActions>
                <button class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Creating…' : 'Create bank' }}
                </button>
            </FormActions>
        </form>

        <section class="card-list">
            <div v-for="bank in banks" :key="bank.id" class="card-list-row justify-between">
                <div class="min-w-0">
                    <p class="font-medium text-slate-900 truncate">{{ bank.title }}</p>
                    <p class="text-xs text-slate-500">{{ bank.subject }} · {{ bank.questions_count }} question(s)</p>
                </div>
                <a :href="`/portal/teacher/${school.id}/question-banks/${bank.id}`" class="link-brand text-sm shrink-0">Open →</a>
            </div>
            <EmptyState v-if="!banks.length" title="No question banks yet" description="Create one above to start adding Talent Search questions." icon="🗂️" class="!border-0 !shadow-none" />
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import FormField from '@/Components/ui/FormField.vue';
import FormGrid from '@/Components/ui/FormGrid.vue';
import FormActions from '@/Components/ui/FormActions.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
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
