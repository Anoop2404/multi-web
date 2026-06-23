<template>
    <div class="min-h-screen bg-gray-50 p-6">
        <header class="mb-6">
            <p class="text-xs text-amber-600 font-bold uppercase">Judge Portal</p>
            <h1 class="text-2xl font-bold">{{ sahodaya.name }}</h1>
        </header>

        <div v-if="!events.length" class="bg-white border rounded-xl p-6 text-sm text-gray-500">
            No active events with assignments for your account.
        </div>

        <div v-else class="space-y-4">
            <div v-for="event in events" :key="event.id" class="bg-white border rounded-xl p-4">
                <h2 class="font-semibold">{{ event.title }}</h2>
                <ul class="text-sm mt-2 space-y-1 text-gray-600">
                    <li v-for="a in assignments[event.id] ?? []" :key="a.id">· {{ a.item?.title }}</li>
                </ul>
                <a :href="`/portal/judge/${sahodaya.id}/events/${event.id}/marks`"
                   class="inline-block mt-3 text-indigo-600 text-sm font-medium">Enter marks →</a>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({ sahodaya: Object, events: Array, assignments: Object });
</script>
