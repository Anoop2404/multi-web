<template>
    <SchoolAdminLayout :title="`Toppers — Class ${boardResult.class} (${boardResult.academic_year})`" :school="school">
        <div class="max-w-3xl space-y-6">
            <!-- Add topper form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-bold text-gray-800 mb-4">Add Topper</h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Student Name *</label>
                            <input v-model="form.name" type="text" required
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Percentage *</label>
                            <input v-model="form.percentage" type="number" required min="0" max="100" step="0.01"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Rank</label>
                            <input v-model="form.rank" type="number" min="1" placeholder="1"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Stream (Class XII only)</label>
                            <input v-model="form.stream" type="text" placeholder="Science / Commerce / Arts"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Total Marks</label>
                            <input v-model="form.total_marks" type="number" min="0"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Marks Obtained</label>
                            <input v-model="form.marks_obtained" type="number" min="0"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Photo</label>
                            <input type="file" accept="image/*" @change="form.photo = $event.target.files[0]"
                                   class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        </div>
                        <div class="flex items-center gap-2 pt-5">
                            <input type="checkbox" id="is_perfect" v-model="form.is_perfect_scorer" class="rounded">
                            <label for="is_perfect" class="text-sm text-gray-700">Perfect scorer (100%)</label>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="form.processing"
                                class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition disabled:opacity-50">
                            Add Topper
                        </button>
                        <Link :href="`/school-admin/${school.id}/board-results`"
                              class="text-sm text-gray-500 hover:text-gray-700">← Back to Results</Link>
                    </div>
                </form>
            </div>

            <!-- Toppers list -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
                    <h3 class="font-bold text-gray-800">Toppers ({{ boardResult.toppers?.length ?? 0 }})</h3>
                </div>
                <div v-if="boardResult.toppers?.length" class="divide-y divide-gray-50">
                    <div v-for="t in sortedToppers" :key="t.id"
                         class="flex items-center gap-4 px-5 py-3">
                        <img v-if="t.photo" :src="t.photo" class="w-12 h-12 rounded-full object-cover border border-gray-100 shrink-0">
                        <div class="w-12 h-12 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold shrink-0" v-else>
                            {{ t.name[0] }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-800">
                                {{ t.name }}
                                <span v-if="t.is_perfect_scorer" class="ml-1 text-xs bg-yellow-50 text-yellow-700 px-1.5 py-0.5 rounded">Perfect Score</span>
                            </p>
                            <p class="text-xs text-gray-400">
                                <span v-if="t.stream">{{ t.stream }} · </span>
                                <span v-if="t.marks_obtained && t.total_marks">{{ t.marks_obtained }}/{{ t.total_marks }} · </span>
                                Rank {{ t.rank ?? '—' }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-lg font-bold text-indigo-600">{{ t.percentage }}%</p>
                            <button @click="remove(t)" class="text-xs text-red-400 hover:underline">Remove</button>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-8 text-center text-gray-400 text-sm">
                    No toppers added yet.
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    school:      Object,
    boardResult: Object,
});

const sortedToppers = computed(() =>
    [...(props.boardResult.toppers ?? [])].sort((a, b) => (a.rank ?? 999) - (b.rank ?? 999))
);

const form = useForm({
    name:              '',
    percentage:        '',
    rank:              '',
    stream:            '',
    total_marks:       '',
    marks_obtained:    '',
    is_perfect_scorer: false,
    photo:             null,
});

function submit() {
    form.post(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers`, {
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
}

function remove(t) {
    if (!confirm(`Remove topper "${t.name}"?`)) return;
    router.delete(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/${t.id}`);
}
</script>
