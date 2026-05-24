<template>
    <AdminLayout title="Dashboard">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-xl shadow-sm p-6">
                <p class="text-sm text-gray-500">{{ stat.label }}</p>
                <p class="text-3xl font-bold text-gray-800 mt-1">{{ stat.value }}</p>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    stats: {
        type: Array,
        default: () => [
            { label: 'Total Tenants', value: '—' },
            { label: 'Sahodaya Clusters', value: '—' },
            { label: 'Schools', value: '—' },
        ],
    },
});
</script>
