<template>
    <div class="min-h-screen bg-gray-50 p-6">
        <header class="mb-6">
            <p class="text-xs text-indigo-600 font-bold uppercase">Teacher Portal</p>
            <h1 class="text-2xl font-bold">{{ teacher.name }}</h1>
            <p class="text-sm text-gray-500">{{ school.name }}</p>
        </header>
        <section class="bg-white border rounded-xl p-4">
            <h2 class="font-semibold text-sm mb-2">Training Programs</h2>
            <ul class="text-sm divide-y">
                <li v-for="t in training" :key="t.id" class="py-2">{{ t.program?.title }} ({{ t.status }})</li>
            </ul>
        </section>
    </div>
</template>

<script setup>
defineProps({ school: Object, teacher: Object, training: Array, notifications: Array });
</script>
