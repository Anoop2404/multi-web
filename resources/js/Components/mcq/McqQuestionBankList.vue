<template>
    <div class="space-y-6">
        <div class="grid grid-cols-3 gap-4">
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold">{{ stats.banks }}</p>
                <p class="mt-1 text-xs text-slate-500">Banks</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold text-emerald-700">{{ stats.ready }}</p>
                <p class="mt-1 text-xs text-slate-500">Ready for exams</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold">{{ stats.questions }}</p>
                <p class="mt-1 text-xs text-slate-500">Questions</p>
            </div>
        </div>

        <section class="card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="section-title !mb-1">New question bank</h3>
                    <p class="text-sm text-slate-500">A bank groups questions by subject and class. Exams pick their questions from Ready banks.</p>
                </div>
                <button v-if="!creating" type="button" class="btn-primary text-sm" @click="creating = true">+ New bank</button>
            </div>
            <form v-if="creating" class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="create">
                <div class="sm:col-span-2">
                    <label class="form-label">Title</label>
                    <input v-model="form.title" class="field" placeholder="e.g. Class 8 Science — Talent Search 2026">
                    <InputError :message="form.errors.title" />
                </div>
                <div>
                    <label class="form-label">Subject</label>
                    <input v-model="form.subject" class="field" list="mcq-subjects" placeholder="e.g. Science">
                    <datalist id="mcq-subjects"><option v-for="s in subjects" :key="s.value" :value="s.value" /></datalist>
                    <InputError :message="form.errors.subject" />
                </div>
                <div>
                    <label class="form-label">Class group</label>
                    <select v-model="form.class_group" class="field">
                        <option :value="null">Any</option>
                        <option v-for="g in classGroups" :key="g.value" :value="g.value">{{ g.label }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Description <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea v-model="form.description" rows="2" class="field" />
                </div>
                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" class="btn-primary text-sm" :disabled="form.processing">Create bank</button>
                    <button type="button" class="btn-secondary text-sm" @click="creating = false">Cancel</button>
                </div>
            </form>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <input v-model="search" type="search" class="field max-w-xs" placeholder="Search banks…" @keyup.enter="applyFilters">
            <select v-model="status" class="field max-w-[10rem]" @change="applyFilters">
                <option value="">All statuses</option>
                <option value="active">Ready</option>
                <option value="draft">Draft</option>
                <option value="archived">Archived</option>
            </select>
        </div>

        <div class="card card--flush overflow-hidden">
            <EmptyState v-if="!banks.length" title="No question banks yet"
                        description="Create a bank, then add questions or import them from Excel." icon="📚" class="py-10" />
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Status</th>
                        <th>Questions</th>
                        <th>Exams</th>
                        <th>By</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="bank in banks" :key="bank.id" class="cursor-pointer" @click="router.visit(`${baseUrl}/${bank.id}`)">
                        <td class="font-medium"><Link :href="`${baseUrl}/${bank.id}`" class="link-brand" @click.stop>{{ bank.title }}</Link></td>
                        <td>{{ bank.subject }}</td>
                        <td class="text-xs">{{ bank.class_group_label || '—' }}</td>
                        <td>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                  :class="{ active: 'bg-emerald-100 text-emerald-800', draft: 'bg-slate-100 text-slate-700', archived: 'bg-slate-200 text-slate-500' }[bank.status]">
                                {{ bank.status_label }}
                            </span>
                        </td>
                        <td>{{ bank.questions_count }}</td>
                        <td>{{ bank.exams_count }}</td>
                        <td class="text-xs">{{ bank.owner_label }}</td>
                        <td class="whitespace-nowrap text-xs">{{ formatDateTime(bank.updated_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { formatDateTime } from '@/support/calendarDates.js';

const props = defineProps({
    banks: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    subjects: { type: Array, default: () => [] },
    classGroups: { type: Array, default: () => [] },
    baseUrl: { type: String, required: true },
});

const creating = ref(false);
const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const form = useForm({ title: '', subject: '', class_group: null, description: '' });

function create() {
    form.post(props.baseUrl);
}

function applyFilters() {
    router.get(props.baseUrl, {
        search: search.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true });
}
</script>
