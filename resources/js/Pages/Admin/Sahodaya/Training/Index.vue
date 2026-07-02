<template>
    <SahodayaAdminLayout title="Teacher Training" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Teacher training" eyebrow="Programs"
                    description="Manage training programs, registrations, sessions, and certificates." />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.programs }}</p>
                <p class="text-xs text-slate-500 mt-1">Programs</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ stats.open }}</p>
                <p class="text-xs text-slate-500 mt-1">Registration open</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.registrations }}</p>
                <p class="text-xs text-slate-500 mt-1">Registrations</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.sessions }}</p>
                <p class="text-xs text-slate-500 mt-1">Sessions</p>
            </div>
        </div>

        <form @submit.prevent="createProgram" class="card mb-4 flex flex-wrap gap-2">
            <input v-model="form.title" class="field flex-1 min-w-[12rem]" placeholder="Program title" required>
            <button class="btn-primary">Create program</button>
        </form>

        <div class="card overflow-hidden p-0">
            <EmptyState v-if="!programs.length" title="No training programs yet" description="Create your first program using the form above." icon="🎓" />
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Registrations</th>
                        <th>Sessions</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in programs" :key="p.id">
                        <td class="font-medium text-slate-900">{{ p.title }}</td>
                        <td>{{ p.registrations_count ?? 0 }}</td>
                        <td>{{ p.sessions_count ?? 0 }}</td>
                        <td class="text-right">
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${p.id}`" class="link-brand">Manage →</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    programs: Array,
    stats: { type: Object, default: () => ({ programs: 0, open: 0, registrations: 0, sessions: 0 }) },
});

const form = useForm({ title: '' });

function createProgram() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/training`, { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>
