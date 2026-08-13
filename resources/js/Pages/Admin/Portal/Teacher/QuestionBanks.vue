<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Question Banks"
        :subtitle="school.name"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="teacher?.photo_url"
        show-avatar-placeholder
    >
        <!-- Creation Form Card -->
        <form @submit.prevent="createBank" class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm mb-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                    🗂️ Create New Question Bank
                </h2>
            </div>
            <FormGrid>
                <FormField label="Bank Title" required :error="form.errors.title">
                    <input v-model="form.title" class="field" placeholder="e.g. Class 10 Science Practice MCQs" required>
                </FormField>
                <FormField label="Subject" required :error="form.errors.subject">
                    <input v-model="form.subject" class="field" placeholder="e.g. Science / Mathematics" required>
                </FormField>
                <FormField label="Class / Standard (optional)" :error="form.errors.class_group">
                    <select v-model="form.class_group" class="field">
                        <option value="">Select class group</option>
                        <option v-for="(label, key) in classGroups" :key="key" :value="key">{{ label }}</option>
                    </select>
                </FormField>
                <FormField label="Description & Notes (optional)" class-extra="sm:col-span-2" :error="form.errors.description">
                    <textarea v-model="form.description" class="field text-xs" rows="2" placeholder="Brief notes about topics covered in this bank"></textarea>
                </FormField>
            </FormGrid>
            <FormActions>
                <button class="btn-primary text-xs !min-h-0 !py-2.5 px-5 shadow-sm" :disabled="form.processing">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ form.processing ? 'Creating…' : 'Create Question Bank' }}
                </button>
            </FormActions>
        </form>

        <!-- Question Banks Grid -->
        <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                    📚 My Question Banks
                </h2>
                <span class="text-xs font-semibold text-slate-500">Total: {{ banks?.length ?? 0 }}</span>
            </div>

            <div v-if="banks?.length" class="grid gap-3 sm:grid-cols-2">
                <div v-for="bank in banks" :key="bank.id" class="flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm transition hover:border-[#0f3d7a]/30">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-bold text-slate-900 text-base leading-snug">{{ bank.title }}</h3>
                            <span class="bg-blue-50 text-[#0f3d7a] font-bold text-xs px-2.5 py-0.5 rounded-full border border-blue-200 shrink-0">
                                {{ bank.questions_count }} Question(s)
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 font-medium">{{ bank.subject }}</p>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100 flex justify-end">
                        <a :href="`/portal/teacher/${school.id}/question-banks/${bank.id}`"
                           class="inline-flex items-center gap-1.5 text-xs font-bold text-white bg-[#0f3d7a] hover:bg-[#041525] px-3.5 py-1.5 rounded-xl transition shadow-sm">
                            Open Bank
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
            <EmptyState v-else title="No question banks yet" description="Create one above to start adding Talent Search questions." icon="🗂️" />
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

