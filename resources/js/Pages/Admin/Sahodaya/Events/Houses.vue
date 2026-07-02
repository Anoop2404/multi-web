<template>
    <SahodayaEventsLayout :title="`${event.title} — Houses`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Houses`" eyebrow="Operations"
                    description="Configure houses and assign schools for house scoring." />
        <form @submit.prevent="createHouse" class="card mb-4 flex flex-wrap gap-2">
            <input v-model="houseForm.name" class="field" placeholder="House name" required>
            <input v-model="houseForm.color" class="field w-24" placeholder="#color">
            <input v-model="houseForm.motto" class="field flex-1" placeholder="Motto (optional)">
            <button class="btn-primary">Add house</button>
        </form>

        <div class="grid lg:grid-cols-2 gap-4">
            <div v-for="house in houses" :key="house.id" class="card">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <span class="inline-block w-4 h-4 rounded-full mr-2 align-middle" :style="{ background: house.color || '#ccc' }"></span>
                        <strong>{{ house.name }}</strong>
                        <p v-if="house.motto" class="text-xs text-gray-500 italic">{{ house.motto }}</p>
                    </div>
                    <button @click="removeHouse(house.id)" class="text-red-600 text-xs">Remove</button>
                </div>
                <form @submit.prevent="assign(house.id)" class="flex gap-2 mb-2">
                    <select v-model="assignForms[house.id]" class="field flex-1">
                        <option value="">Assign school…</option>
                        <option v-for="s in availableSchools(house.id)" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <button class="btn-primary px-3 py-2 rounded-lg text-xs">Assign</button>
                </form>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li v-for="a in house.school_assignments" :key="a.id">· {{ schoolName(a.school_id) }}</li>
                </ul>
            </div>
        </div>

        <div v-if="houseScoreboard.length" class="mt-6 bg-white border rounded-xl p-4">
            <h3 class="font-semibold text-sm mb-3">House standings</h3>
            <ol class="text-sm space-y-1">
                <li v-for="row in houseScoreboard" :key="row.house_id">
                    #{{ row.rank }} {{ row.house_name }} — {{ row.total_points }} pts
                </li>
            </ol>
        </div>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, houses: Array, schools: Array, houseScoreboard: Array, assignedSchoolIds: Array,
    activityLogs: { type: Array, default: () => [] },
});

const houseForm = useForm({ name: '', color: '', motto: '' });
const assignForms = reactive({});

function schoolName(id) {
    return props.schools.find(s => s.id === id)?.name ?? id;
}

function availableSchools(houseId) {
    const inHouse = new Set(
        props.houses.find(h => h.id === houseId)?.school_assignments?.map(a => a.school_id) ?? [],
    );
    return props.schools.filter(s => inHouse.has(s.id) || !props.assignedSchoolIds.includes(s.id));
}

function createHouse() {
    houseForm.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/houses`, {
        preserveScroll: true, onSuccess: () => houseForm.reset(),
    });
}

function assign(houseId) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/houses/${houseId}/assign`, {
        school_id: assignForms[houseId],
    }, { preserveScroll: true, onSuccess: () => { assignForms[houseId] = ''; } });
}

function removeHouse(id) {
    if (!confirm('Remove this house?')) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/houses/${id}`, { preserveScroll: true });
}
</script>

