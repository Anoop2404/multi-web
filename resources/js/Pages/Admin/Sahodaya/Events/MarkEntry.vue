<template>
    <SahodayaAdminLayout :title="`${event.title} — Mark Entry`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-4">
            <div v-for="reg in registrations" :key="reg.id" class="bg-white border rounded-xl p-4">
                <p class="font-semibold text-sm mb-2">{{ reg.item?.title }}</p>
                <div v-for="p in reg.participants" :key="p.id" class="flex flex-wrap items-end gap-2 border-t py-2">
                    <span class="text-sm flex-1">{{ p.student?.name ?? p.teacher?.name }}</span>
                    <form @submit.prevent="saveMark(p, reg.item.id)" class="flex gap-2">
                        <select v-model="markForms[p.id].grade" class="field w-20">
                            <option value="">—</option>
                            <option>A</option><option>B</option><option>C</option>
                        </select>
                        <input v-model.number="markForms[p.id].score" type="number" min="0" class="field w-20" placeholder="Pts">
                        <button class="px-2 py-1 bg-indigo-600 text-white rounded text-xs">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, registrations: Array, marks: Object,
});

const markForms = reactive({});
for (const reg of props.registrations) {
    for (const p of reg.participants ?? []) {
        const existing = props.marks[p.id];
        markForms[p.id] = { grade: existing?.grade ?? '', score: existing?.score ?? null };
    }
}

function saveMark(participant, itemId) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks`, {
        participant_id: participant.id,
        item_id: itemId,
        ...markForms[participant.id],
    }, { preserveScroll: true });
}
</script>

<style scoped>
@reference "../../../../../css/app.css";
.field { @apply border border-gray-200 rounded px-2 py-1 text-sm; }
</style>
