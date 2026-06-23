<template>
    <div class="min-h-screen bg-gray-50 p-6">
        <header class="mb-6">
            <p class="text-xs text-indigo-600 font-bold uppercase">Student Portal</p>
            <h1 class="text-2xl font-bold">{{ student.name }}</h1>
            <p class="text-sm text-gray-500">{{ school.name }} · {{ student.reg_no }}</p>
        </header>
        <section class="bg-white border rounded-xl p-4 mb-4">
            <h2 class="font-semibold text-sm mb-2">Event Registrations</h2>
            <ul class="text-sm divide-y">
                <li v-for="r in registrations" :key="r.id" class="py-2">{{ r.event?.title }} — {{ r.item?.title }} ({{ r.status }})</li>
                <li v-if="!registrations.length" class="text-gray-400 py-2">No registrations yet</li>
            </ul>
        </section>
        <section class="bg-white border rounded-xl p-4">
            <h2 class="font-semibold text-sm mb-2">Notifications</h2>
            <ul class="text-sm divide-y">
                <li v-for="n in notifications" :key="n.id" class="py-2">{{ n.title }}</li>
            </ul>
        </section>
    </div>
</template>

<script setup>
defineProps({ school: Object, student: Object, registrations: Array, mcqExams: Array, notifications: Array, logoUrl: String });
</script>
