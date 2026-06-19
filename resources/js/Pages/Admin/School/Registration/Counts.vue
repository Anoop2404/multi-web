<template>
    <SchoolAdminLayout title="Student Counts" :school="school">
        <div class="max-w-3xl space-y-4">
            <Link :href="`/school-admin/${school.id}/registration`" class="text-sm text-blue-600">← Registration</Link>
            <p class="text-sm text-gray-500">Status: {{ submission.counts_status }}</p>

            <form @submit.prevent="save" class="bg-white border rounded-xl p-4 space-y-3">
                <div v-for="cat in categories" :key="cat.id" class="grid grid-cols-4 gap-2 items-center text-sm">
                    <span class="font-medium">{{ cat.label }}</span>
                    <input v-model.number="rows[cat.id].male_count" type="number" min="0" placeholder="Male" class="border rounded px-2 py-1">
                    <input v-model.number="rows[cat.id].female_count" type="number" min="0" placeholder="Female" class="border rounded px-2 py-1">
                    <input v-model.number="rows[cat.id].total_count" type="number" min="0" placeholder="Total" class="border rounded px-2 py-1">
                    <p v-if="mismatch(cat.id)" class="col-span-4 text-xs text-amber-600">Total does not match male + female</p>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">Save Counts</button>
            </form>

            <button v-if="['pending','rejected'].includes(submission.counts_status)" @click="submit"
                    class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm">Submit counts &amp; continue</button>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({ school: Object, registration: Object, submission: Object, categories: Array, counts: Object });

const rows = reactive(Object.fromEntries(props.categories.map(c => [c.id, {
    class_category_id: c.id,
    male_count: props.counts[c.id]?.male_count ?? 0,
    female_count: props.counts[c.id]?.female_count ?? 0,
    total_count: props.counts[c.id]?.total_count ?? 0,
}])));

const form = useForm({ counts: [] });
function mismatch(id) {
    const r = rows[id];
    return r.total_count !== r.male_count + r.female_count;
}
function save() {
    form.counts = Object.values(rows);
    form.post(`/school-admin/${props.school.id}/registration/counts`);
}
function submit() { router.post(`/school-admin/${props.school.id}/registration/submit-track`, { track: 'counts' }); }
</script>
