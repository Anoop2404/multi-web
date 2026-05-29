<template>
    <SchoolAdminLayout title="Alumni" :school="school">
        <div class="space-y-4">
            <!-- Filter tabs -->
            <div class="flex gap-2">
                <button v-for="tab in tabs" :key="tab.value"
                        @click="activeTab = tab.value"
                        :class="activeTab === tab.value ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-200'"
                        class="px-4 py-1.5 rounded-lg text-xs font-semibold transition">
                    {{ tab.label }} ({{ counts[tab.value] }})
                </button>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Alumni</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Batch</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Current Role</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="a in filtered" :key="a.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <img v-if="a.photo" :src="a.photo" class="w-9 h-9 rounded-full object-cover border border-gray-100 shrink-0">
                                    <div class="w-9 h-9 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold text-sm shrink-0" v-else>
                                        {{ a.name[0] }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800">{{ a.name }}</p>
                                        <p v-if="a.email" class="text-xs text-gray-400">{{ a.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ a.batch_year }}</td>
                            <td class="px-5 py-3 text-gray-500 text-xs">
                                <span v-if="a.current_role">{{ a.current_role }}</span>
                                <span v-if="a.current_organisation" class="block text-gray-400">{{ a.current_organisation }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-col gap-1">
                                    <span :class="a.is_approved ? 'text-green-600' : 'text-amber-500'"
                                          class="text-xs font-medium">
                                        {{ a.is_approved ? '✓ Approved' : '⏳ Pending' }}
                                    </span>
                                    <span v-if="a.is_featured" class="text-xs text-purple-600 font-medium">★ Featured</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="approve(a)"
                                            :class="a.is_approved ? 'text-amber-500' : 'text-green-600'"
                                            class="text-xs hover:underline">
                                        {{ a.is_approved ? 'Hide' : 'Approve' }}
                                    </button>
                                    <button @click="feature(a)"
                                            class="text-xs text-purple-500 hover:underline">
                                        {{ a.is_featured ? 'Unfeature' : 'Feature' }}
                                    </button>
                                    <button @click="remove(a)" class="text-xs text-red-400 hover:underline">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!filtered.length">
                            <td colspan="5" class="px-5 py-10 text-center text-gray-400">No alumni found.</td>
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
    school: Object,
    alumni: { type: Array, default: () => [] },
});

const activeTab = ref('all');

const tabs = [
    { value: 'all',      label: 'All' },
    { value: 'pending',  label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'featured', label: 'Featured' },
];

const counts = computed(() => ({
    all:      props.alumni.length,
    pending:  props.alumni.filter(a => !a.is_approved).length,
    approved: props.alumni.filter(a => a.is_approved).length,
    featured: props.alumni.filter(a => a.is_featured).length,
}));

const filtered = computed(() => {
    switch (activeTab.value) {
        case 'pending':  return props.alumni.filter(a => !a.is_approved);
        case 'approved': return props.alumni.filter(a => a.is_approved);
        case 'featured': return props.alumni.filter(a => a.is_featured);
        default:         return props.alumni;
    }
});

function approve(a) {
    router.patch(`/school-admin/${props.school.id}/alumni/${a.id}/approve`, {}, { preserveScroll: true });
}

function feature(a) {
    router.patch(`/school-admin/${props.school.id}/alumni/${a.id}/feature`, {}, { preserveScroll: true });
}

function remove(a) {
    if (!confirm(`Remove alumni "${a.name}"?`)) return;
    router.delete(`/school-admin/${props.school.id}/alumni/${a.id}`);
}
</script>
