<template>
    <div class="min-h-screen bg-gray-50 p-6">
        <header class="mb-6">
            <a :href="`/portal/judge/${sahodaya.id}`" class="text-xs text-indigo-600">← Back</a>
            <h1 class="text-2xl font-bold mt-1">{{ event.title }}</h1>
            <p class="text-sm text-gray-500">Mark entry</p>
        </header>

        <div v-for="reg in registrations" :key="reg.id" class="bg-white border rounded-xl p-4 mb-4">
            <h3 class="font-semibold text-sm mb-3">{{ reg.item?.title }}</h3>
            <div v-for="p in reg.participants" :key="p.id" class="grid md:grid-cols-6 gap-2 items-end border-t py-3">
                <div class="md:col-span-2 text-sm">
                    <span class="font-mono text-xs text-gray-400 mr-2">#{{ p.chest_no ?? '—' }}</span>
                    {{ p.student?.name ?? p.teacher?.name }}
                </div>
                <input v-model="forms[p.id].grade" placeholder="Grade A/B/C" class="field">
                <input v-model.number="forms[p.id].position" type="number" min="1" placeholder="Position" class="field">
                <input v-model.number="forms[p.id].score" type="number" min="0" step="0.01" placeholder="Score" class="field">
                <button @click="save(p, reg.item_id)" class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm">Save</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    sahodaya: Object, event: Object, registrations: Array, marks: Object,
});

const forms = reactive({});

onMounted(() => {
    for (const reg of props.registrations) {
        for (const p of reg.participants) {
            const existing = props.marks?.[p.id];
            forms[p.id] = {
                grade: existing?.grade ?? '',
                position: existing?.position ?? '',
                score: existing?.score ?? '',
            };
        }
    }
});

function save(participant, itemId) {
    router.post(`/portal/judge/${props.sahodaya.id}/events/${props.event.id}/marks`, {
        participant_id: participant.id,
        item_id: itemId,
        ...forms[participant.id],
    }, { preserveScroll: true });
}
</script>

<style scoped>
@reference "../../../../../css/app.css";
.field { @apply border border-gray-200 rounded-lg px-3 py-2 text-sm; }
</style>
