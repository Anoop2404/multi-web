<template>
    <SchoolAdminLayout title="Board Results" :school="school" :show-header-title="false">
        <PageHeader title="Board Results" eyebrow="Website"
            description="School website content and public pages." />


        <div class="space-y-6">
            <!-- Add result form -->
            <div class="card">
                <h3 class="font-bold text-gray-800 mb-4">Add / Update Board Result</h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid sm:grid-cols-3 gap-4">
                        <div>
                            <label class="form-label mb-1.5">Class *</label>
                            <select v-model="form.class" required
                                    class="field">
                                <option value="10">Class X (CBSE)</option>
                                <option value="12">Class XII (CBSE)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Academic Year *</label>
                            <input v-model="form.academic_year" type="text" required placeholder="2024-25"
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Pass % *</label>
                            <input v-model="form.pass_percent" type="number" required min="0" max="100" step="0.01"
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Total Appeared *</label>
                            <input v-model="form.total_appeared" type="number" required min="0"
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Passed *</label>
                            <input v-model="form.pass_count" type="number" required min="0"
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Distinctions</label>
                            <input v-model="form.distinctions" type="number" min="0"
                                   class="field">
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="form.processing"
                                class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold transition disabled:opacity-50">
                            Save & Add Toppers →
                        </button>
                        <p class="text-xs text-gray-400">If a result for this class + year already exists, it will be updated.</p>
                    </div>
                </form>
            </div>

            <!-- Existing results -->
            <div class="space-y-4">
                <div v-for="r in results" :key="r.id"
                     class="card card--flush">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-5 py-4 bg-gray-50 border-b border-gray-100">
                        <div>
                            <span class="font-bold text-gray-800">Class {{ r.class }} — {{ r.academic_year }}</span>
                            <div class="flex items-center gap-4 mt-1 text-xs text-gray-500">
                                <span>{{ r.total_appeared }} appeared</span>
                                <span>{{ r.pass_count }} passed</span>
                                <span class="font-semibold text-green-600">{{ r.pass_percent }}%</span>
                                <span v-if="r.distinctions">{{ r.distinctions }} distinctions</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Link :href="`/school-admin/${school.id}/board-results/${r.id}/toppers`"
                                  class="text-xs bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-lg font-semibold hover:bg-indigo-100 transition">
                                {{ r.class == 12 ? 'Manage toppers & subjects' : 'Manage toppers' }} ({{ r.toppers?.length ?? 0 }})
                            </Link>
                            <button @click="remove(r)" class="text-xs text-red-400 hover:underline">Delete</button>
                        </div>
                    </div>

                    <!-- Toppers preview -->
                    <div v-if="r.toppers?.length" class="px-5 py-3 flex flex-wrap gap-3">
                        <div v-for="t in r.toppers.slice(0, 6)" :key="t.id"
                             class="flex items-center gap-2 text-xs text-gray-600">
                            <img v-if="t.photo" :src="t.photo" class="w-7 h-7 rounded-full object-cover border border-gray-100">
                            <span class="text-gray-400 w-7 h-7 rounded-full bg-indigo-50 flex items-center justify-center font-bold text-indigo-600" v-else>
                                {{ t.name[0] }}
                            </span>
                            <span>{{ t.name }} <span class="text-indigo-600 font-semibold">{{ t.percentage }}%</span></span>
                        </div>
                        <span v-if="r.toppers.length > 6" class="text-xs text-gray-400 self-center">
                            +{{ r.toppers.length - 6 }} more
                        </span>
                    </div>
                </div>

                <div v-if="!results.length"
                     class="card card--dashed p-10 text-center text-slate-400">
                    No board results added yet.
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    school:  Object,
    results: { type: Array, default: () => [] },
});

const form = useForm({
    class:          '10',
    academic_year:  '',
    total_appeared: '',
    pass_count:     '',
    pass_percent:   '',
    distinctions:   '',
});

function submit() {
    form.post(`/school-admin/${props.school.id}/board-results`);
}

function remove(r) {
    if (!confirm(`Delete Class ${r.class} results for ${r.academic_year}?`)) return;
    router.delete(`/school-admin/${props.school.id}/board-results/${r.id}`);
}
</script>
