<template>
    <SchoolAdminLayout title="Circulars" :school="school" :show-header-title="false">
        <PageHeader title="Circulars" eyebrow="Programs"
            description="Fest programs, exams, training, and Sahodaya circulars." />


        <div class="max-w-full overflow-x-hidden space-y-3">
            <p class="text-sm text-gray-500">Acknowledge circulars from your Sahodaya.</p>
            <div v-for="c in circulars" :key="c.id" class="card flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-semibold text-gray-900">{{ c.title }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ c.category || 'General' }}
                        <span v-if="c.issued_date"> · {{ c.issued_date }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <a :href="`/school-admin/${school.id}/circulars/${c.id}/download`"
                       target="_blank" rel="noopener"
                       class="px-3 py-1.5 border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50">
                        View / Download
                    </a>
                    <span v-if="c.acknowledged" class="text-xs font-semibold text-green-700 bg-green-50 px-2 py-1 rounded">Acknowledged</span>
                    <button v-else @click="acknowledge(c)"
                            class="btn-primary text-xs">
                        Acknowledge
                    </button>
                </div>
            </div>
            <p v-if="!circulars.length" class="text-center text-gray-400 py-8">No circulars published yet.</p>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({ school: Object, circulars: Array });

function acknowledge(circular) {
    router.post(`/school-admin/${props.school.id}/circulars/${circular.id}/acknowledge`, {}, { preserveScroll: true });
}
</script>
