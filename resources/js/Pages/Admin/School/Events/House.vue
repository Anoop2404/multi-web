<template>
    <SchoolAdminLayout :title="`${event.title} — House`" :school="school" :show-header-title="false">
        <PageHeader :title="`${event.title} — House`" eyebrow="Fest"
            description="House standings for this event." />


        <div v-if="house" class="mb-4 p-4 bg-white border rounded-xl">
            <span class="inline-block w-5 h-5 rounded-full mr-2 align-middle" :style="{ background: house.color || '#ccc' }"></span>
            <strong>{{ house.name }}</strong>
            <p v-if="house.motto" class="text-sm text-gray-500 italic mt-1">{{ house.motto }}</p>
        </div>
        <p v-else class="text-gray-500 text-sm mb-4">Your school is not assigned to a house for this event.</p>
        <ol class="card-list">
            <li v-for="row in houseScoreboard" :key="row.house_id" class="p-4 flex justify-between text-sm">
                <span>#{{ row.rank }} {{ row.house_name }}</span>
                <span class="font-mono">{{ row.total_points }} pts</span>
            </li>
        </ol>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
defineProps({ school: Object, event: Object, house: Object, houseScoreboard: Array });
</script>
