<template>
    <SahodayaAdminLayout :title="`Feedback · ${program.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="program.title" eyebrow="Training feedback"
                    :description="`${stats.submitted} response(s) · avg ${stats.avg_rating ?? '—'} · ${program.status}`">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}`" class="btn-secondary text-sm">
                    ← Program
                </Link>
            </template>
        </PageHeader>

        <TrainingProgramSubNav :sahodaya-id="sahodaya.id" :program-id="program.id" active="feedback" />

        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
            <div class="card text-center">
                <p class="text-2xl font-bold">{{ stats.submitted }}</p>
                <p class="text-xs text-gray-500">Submitted</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-green-700">{{ stats.reviewed }}</p>
                <p class="text-xs text-gray-500">Reviewed</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-indigo-700">{{ stats.avg_rating ?? '—' }}</p>
                <p class="text-xs text-gray-500">Avg rating</p>
            </div>
        </div>

        <div class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[720px] text-sm">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>School</th>
                            <th>Overall</th>
                            <th>Content</th>
                            <th>Trainer</th>
                            <th>Venue</th>
                            <th>Comments</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in feedback" :key="row.id">
                            <td>
                                <div class="font-medium">{{ row.teacher_name || '—' }}</div>
                                <div class="text-xs text-gray-400">{{ row.teacher_email || '' }}</div>
                            </td>
                            <td>{{ row.school_name || '—' }}</td>
                            <td class="font-semibold">{{ row.rating }}/5</td>
                            <td>{{ row.content_rating ?? '—' }}</td>
                            <td>{{ row.trainer_rating ?? '—' }}</td>
                            <td>{{ row.venue_rating ?? '—' }}</td>
                            <td class="max-w-xs text-gray-600">
                                <span class="line-clamp-2">{{ row.comments || '—' }}</span>
                            </td>
                            <td class="capitalize">{{ row.status }}</td>
                            <td class="text-right">
                                <button v-if="row.status === 'submitted'"
                                        type="button"
                                        class="btn-primary text-xs !min-h-0 !py-1.5"
                                        :disabled="reviewingId === row.id"
                                        @click="markReviewed(row)">
                                    {{ reviewingId === row.id ? '…' : 'Mark reviewed' }}
                                </button>
                                <span v-else class="text-xs text-gray-400">Reviewed</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!feedback?.length" class="text-sm text-gray-400 py-8 text-center">No feedback submitted yet.</p>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import TrainingProgramSubNav from '@/Components/sahodaya/TrainingProgramSubNav.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    feedback: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ submitted: 0, reviewed: 0, avg_rating: null }) },
});

const reviewingId = ref(null);

function markReviewed(row) {
    reviewingId.value = row.id;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/feedback/${row.id}/review`, {}, {
        preserveScroll: true,
        onFinish: () => { reviewingId.value = null; },
    });
}
</script>
