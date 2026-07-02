<template>
    <SchoolAdminLayout title="School houses" :school="school" :show-header-title="false">
        <PageHeader title="School houses" eyebrow="Students"
            description="Student records, teachers, and portal access." />


        <div class="space-y-4">
            <p class="text-sm text-gray-600">
                Define intra-school houses and assign students. House admins see only their house in the portal.
                <span v-if="unassigned" class="text-amber-700 font-medium">{{ unassigned }} student(s) not assigned.</span>
            </p>

            <form @submit.prevent="createHouse" class="bg-white border rounded-xl p-4 grid sm:grid-cols-4 gap-2">
                <input v-model="houseForm.name" class="field sm:col-span-2" placeholder="House name" required>
                <input v-model="houseForm.color" class="field" placeholder="Color (e.g. red)">
                <input v-model.number="houseForm.sort_order" type="number" min="0" class="field" placeholder="Order">
                <input v-model="houseForm.motto" class="field sm:col-span-3" placeholder="Motto (optional)">
                <button class="btn-primary">Add house</button>
            </form>

            <div class="grid md:grid-cols-2 gap-4">
                <div v-for="house in houses" :key="house.id" class="card">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold flex items-center gap-2">
                                <span v-if="house.color" class="w-3 h-3 rounded-full" :style="{ background: house.color }"></span>
                                {{ house.name }}
                            </p>
                            <p v-if="house.motto" class="text-xs text-gray-500 mt-1">{{ house.motto }}</p>
                            <p class="text-sm text-gray-600 mt-2">{{ house.students_count }} student(s)</p>
                        </div>
                        <button @click="removeHouse(house.id)" class="text-red-600 text-xs font-semibold">Remove</button>
                    </div>
                </div>
            </div>

            <div v-if="ranking?.length" class="card">
                <h3 class="font-semibold text-sm mb-3">Fest points by house (cumulative)</h3>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr><th class="p-2">Rank</th><th class="p-2">House</th><th class="p-2">Points</th><th class="p-2">Participants</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in ranking" :key="row.house_id" class="border-t">
                            <td class="p-2">#{{ row.rank }}</td>
                            <td class="p-2 font-medium">{{ row.house_name }}</td>
                            <td class="p-2 font-mono">{{ row.total_points }}</td>
                            <td class="p-2 text-gray-500">{{ row.participants }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h3 class="font-semibold text-sm mb-3">Assign students to house</h3>
                <form @submit.prevent="assignStudents" class="space-y-3">
                    <select v-model="assignForm.school_house_id" class="field max-w-xs" required>
                        <option value="">Select house</option>
                        <option v-for="h in houses" :key="h.id" :value="h.id">{{ h.name }}</option>
                    </select>
                    <div class="max-h-64 overflow-y-auto border rounded-lg divide-y text-sm">
                        <label v-for="s in students" :key="s.id" class="flex items-center gap-2 p-2 hover:bg-gray-50">
                            <input type="checkbox" :value="s.id" v-model="assignForm.student_ids">
                            <span>{{ s.name }}</span>
                            <span class="text-gray-400 text-xs">{{ s.reg_no }} · {{ s.school_class?.name }}</span>
                            <span v-if="s.school_house_id" class="text-xs text-indigo-600 ml-auto">assigned</span>
                        </label>
                    </div>
                    <button class="btn-primary" :disabled="!assignForm.student_ids.length">Assign selected</button>
                </form>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({
    school: Object,
    houses: Array,
    unassigned: Number,
    ranking: Array,
    students: Array,
});

const tid = props.school.id;
const houseForm = useForm({ name: '', color: '', motto: '', sort_order: 0 });
const assignForm = useForm({ school_house_id: '', student_ids: [] });

function createHouse() {
    houseForm.post(`/school-admin/${tid}/houses`, { preserveScroll: true, onSuccess: () => houseForm.reset() });
}
function removeHouse(id) {
    if (confirm('Remove this house? Students will be unassigned.')) {
        router.delete(`/school-admin/${tid}/houses/${id}`, { preserveScroll: true });
    }
}
function assignStudents() {
    assignForm.post(`/school-admin/${tid}/houses/assign-students`, {
        preserveScroll: true,
        onSuccess: () => assignForm.reset('student_ids'),
    });
}
</script>

