<template>
    <SchoolAdminLayout title="Admission Enquiries" :school="school">
        <div class="space-y-4">
            <!-- Status counts -->
            <div class="grid grid-cols-3 gap-4">
                <div v-for="(count, status) in counts" :key="status"
                     class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center cursor-pointer"
                     :class="filter === status ? 'ring-2 ring-blue-500' : ''"
                     @click="filter = filter === status ? '' : status">
                    <p class="text-2xl font-bold text-gray-800">{{ count }}</p>
                    <p class="text-xs text-gray-500 capitalize mt-0.5">{{ status }}</p>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Student</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Class</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Parent / Phone</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Received</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template v-for="enq in filtered" :key="enq.id">
                            <tr class="hover:bg-gray-50 cursor-pointer" @click="expand = expand === enq.id ? null : enq.id">
                                <td class="px-5 py-3 font-medium text-gray-800">{{ enq.student_name }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ enq.class_applying }}</td>
                                <td class="px-5 py-3 text-gray-500">
                                    <span class="block">{{ enq.parent_name }}</span>
                                    <span class="text-xs text-gray-400">{{ enq.phone }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <select :value="enq.status"
                                            @change="updateStatus(enq, $event.target.value)"
                                            @click.stop
                                            class="text-xs border border-gray-200 rounded-lg px-2 py-1 focus:outline-none bg-white"
                                            :class="statusClass(enq.status)">
                                        <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                                    </select>
                                </td>
                                <td class="px-5 py-3 text-gray-400 text-xs">
                                    {{ new Date(enq.created_at).toLocaleDateString('en-IN') }}
                                </td>
                                <td class="px-5 py-3 text-gray-300 text-xs">{{ expand === enq.id ? '▲' : '▼' }}</td>
                            </tr>
                            <!-- Expanded details -->
                            <tr v-if="expand === enq.id">
                                <td colspan="6" class="bg-blue-50 px-5 py-4 text-sm text-gray-600">
                                    <div class="grid sm:grid-cols-3 gap-3">
                                        <div><span class="font-semibold">DOB:</span> {{ enq.dob }}</div>
                                        <div><span class="font-semibold">Email:</span> {{ enq.email || '—' }}</div>
                                        <div><span class="font-semibold">Year:</span> {{ enq.academic_year }}</div>
                                    </div>
                                    <div v-if="enq.address" class="mt-2"><span class="font-semibold">Address:</span> {{ enq.address }}</div>
                                    <div v-if="enq.message" class="mt-2"><span class="font-semibold">Message:</span> {{ enq.message }}</div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!filtered.length">
                            <td colspan="6" class="px-5 py-10 text-center text-gray-400">No enquiries.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    school:    Object,
    enquiries: Object,
    counts:    { type: Object, default: () => ({}) },
});

const filter = ref('');
const expand = ref(null);
const statuses = ['new', 'reviewed', 'shortlisted', 'rejected'];

const filtered = computed(() =>
    filter.value
        ? props.enquiries.data.filter(e => e.status === filter.value)
        : props.enquiries.data
);

function statusClass(status) {
    return {
        new:        'text-blue-600',
        reviewed:   'text-amber-600',
        shortlisted:'text-green-600',
        rejected:   'text-red-500',
    }[status] ?? '';
}

function updateStatus(enq, status) {
    router.patch(`/school-admin/${props.school.id}/enquiries/${enq.id}`, { status }, { preserveScroll: true });
}
</script>
