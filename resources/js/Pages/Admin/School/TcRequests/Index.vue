<template>
    <SchoolAdminLayout title="TC Requests" :school="school" :show-header-title="false">
        <PageHeader title="TC Requests" eyebrow="Website"
            description="School website content and public pages." />


        <div class="space-y-4">
            <!-- Status counts -->
            <div class="grid grid-cols-3 gap-4">
                <div v-for="(count, status) in counts" :key="status"
                     class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                    <p class="text-2xl font-bold text-gray-800">{{ count }}</p>
                    <p class="text-xs text-gray-500 capitalize mt-0.5">{{ status }}</p>
                </div>
            </div>

            <div class="card card--flush">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Student</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Class / Adm.No</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Parent / Phone</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Requested</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template v-for="req in requests.data" :key="req.id">
                            <tr class="hover:bg-gray-50 cursor-pointer" @click="expand = expand === req.id ? null : req.id">
                                <td class="px-5 py-3 font-medium text-gray-800">{{ req.student_name }}</td>
                                <td class="px-5 py-3 text-gray-500">
                                    <span class="block">Class {{ req.class }} {{ req.division }}</span>
                                    <span class="text-xs text-gray-400">{{ req.admission_number }}</span>
                                </td>
                                <td class="px-5 py-3 text-gray-500">
                                    <span class="block">{{ req.parent_name }}</span>
                                    <span class="text-xs text-gray-400">{{ req.phone }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <select :value="req.status"
                                            @change="updateStatus(req, $event.target.value)"
                                            @click.stop
                                            class="text-xs border border-gray-200 rounded-lg px-2 py-1 focus:outline-none bg-white">
                                        <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                                    </select>
                                </td>
                                <td class="px-5 py-3 text-gray-400 text-xs">
                                    {{ new Date(req.created_at).toLocaleDateString('en-IN') }}
                                </td>
                            </tr>
                            <tr v-if="expand === req.id">
                                <td colspan="5" class="bg-amber-50 px-5 py-4 text-sm text-gray-600">
                                    <div class="grid sm:grid-cols-3 gap-3">
                                        <div><span class="font-semibold">DOB:</span> {{ req.dob }}</div>
                                        <div><span class="font-semibold">Year:</span> {{ req.academic_year }}</div>
                                        <div v-if="req.issued_date"><span class="font-semibold">Issued:</span> {{ req.issued_date }}</div>
                                    </div>
                                    <div v-if="req.reason" class="mt-2"><span class="font-semibold">Reason:</span> {{ req.reason }}</div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!requests.data.length">
                            <td colspan="5" class="px-5 py-10 text-center text-gray-400">No TC requests yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    school:   Object,
    requests: Object,
    counts:   { type: Object, default: () => ({}) },
});

const expand  = ref(null);
const statuses = ['pending', 'processing', 'ready', 'issued'];

function updateStatus(req, status) {
    router.patch(`/school-admin/${props.school.id}/tc-requests/${req.id}`, { status }, { preserveScroll: true });
}
</script>
