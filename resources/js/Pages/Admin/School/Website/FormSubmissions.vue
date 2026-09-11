<template>
    <SchoolAdminLayout title="Website messages" :school="school" :show-header-title="false">
        <PageHeader :title="`${form.name} — messages`" eyebrow="Public website">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/website/forms`" class="btn-secondary text-sm">Back to forms</Link>
            </template>
        </PageHeader>

        <div class="space-y-4 max-w-5xl">
            <article v-for="row in submissions" :key="row.id" class="card">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <time class="text-xs font-semibold text-slate-500">{{ formatDateTime(row.created_at) }}</time>
                    <span v-if="row.is_spam" class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">Possible spam</span>
                </div>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div v-for="field in form.fields_json" :key="field.key">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ field.label || field.key }}</dt>
                        <dd class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-800">{{ row.payload_json?.[field.key] || '—' }}</dd>
                    </div>
                </dl>
            </article>

            <div v-if="!submissions.length" class="card py-12 text-center text-slate-400">
                No messages received yet.
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatDateTime } from '@/support/calendarDates.js';

defineProps({
    school: Object,
    form: Object,
    submissions: { type: Array, default: () => [] },
});
</script>
