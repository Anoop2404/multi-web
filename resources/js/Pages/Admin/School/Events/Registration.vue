<template>
    <SchoolAdminLayout :title="`${programLabel} Registration`" :school="school">
        <div v-if="registrations?.length" class="bg-white border rounded-xl p-4 mb-4">
            <h3 class="font-semibold text-sm mb-2">Your submissions</h3>
            <ul class="text-sm divide-y">
                <li v-for="reg in registrations" :key="reg.id" class="py-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ reg.event?.title }} — {{ reg.item?.title }}</p>
                        <p class="text-xs text-gray-500">{{ reg.status }} · {{ reg.participants?.length ?? 0 }} participant(s)
                            <span v-if="reg.participants?.[0]?.group?.team_name"> · Team: {{ reg.participants[0].group.team_name }}</span>
                        </p>
                    </div>
                    <form v-if="reg.event?.fee_type !== 'none' && !reg.fee_receipt_id && reg.status === 'submitted'"
                          @submit.prevent="uploadPayment(reg)" class="flex gap-2 items-center">
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="e => paymentFiles[reg.id] = e.target.files[0]" class="text-xs" required>
                        <button class="px-2 py-1 bg-indigo-600 text-white rounded text-xs">Upload fee</button>
                    </form>
                </li>
            </ul>
        </div>

        <div v-if="!events.length" class="bg-amber-50 border border-amber-100 rounded-xl p-6 text-sm text-amber-800">
            No events are open for registration right now.
        </div>
        <div v-else class="space-y-4">
            <div v-for="event in events" :key="event.id" class="bg-white border rounded-xl p-4">
                <h3 class="font-semibold">{{ event.title }}</h3>
                <p v-if="event.fee_type !== 'none'" class="text-xs text-gray-500 mt-1">Fee: ₹{{ event.fee_amount }} ({{ event.fee_type.replace('_', ' ') }})</p>
                <form @submit.prevent="submit(event)" class="mt-3 space-y-2">
                    <select v-model="forms[event.id].item_id" class="field" required @change="onItemChange(event.id)">
                        <option value="">Select item</option>
                        <option v-for="item in event.items" :key="item.id" :value="item.id">
                            {{ item.title }} ({{ item.participant_type }})
                        </option>
                    </select>
                    <input v-if="selectedItem(event.id)?.participant_type !== 'individual'"
                           v-model="forms[event.id].team_name" class="field" placeholder="Team / group name" required>
                    <select v-model="forms[event.id].student_ids" multiple class="field h-28" required>
                        <option v-for="s in students" :key="s.id" :value="s.id">{{ s.name }} ({{ s.reg_no || 'no reg' }})</option>
                    </select>
                    <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Submit Registration</button>
                </form>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({ school: Object, program: String, events: Array, registrations: Array, students: Array });

const labels = { kalotsav: 'Kalotsav', 'sports-meet': 'Sports Meet', 'kids-fest': 'Kids Fest' };
const programLabel = labels[props.program] ?? props.program;

const forms = reactive({});
const paymentFiles = reactive({});

for (const e of props.events) {
    forms[e.id] = { item_id: '', team_name: '', student_ids: [] };
}

function selectedItem(eventId) {
    const event = props.events.find(e => e.id === eventId);
    return event?.items?.find(i => i.id === forms[eventId]?.item_id);
}

function onItemChange(eventId) {
    forms[eventId].student_ids = [];
    forms[eventId].team_name = '';
}

function submit(event) {
    router.post(`/school-admin/${props.school.id}/programs/${props.program}/register`, {
        event_id: event.id,
        ...forms[event.id],
    }, { preserveScroll: true });
}

function uploadPayment(reg) {
    const file = paymentFiles[reg.id];
    if (!file) return;
    router.post(`/school-admin/${props.school.id}/programs/${props.program}/registrations/${reg.id}/payment`, {
        payment_proof: file,
    }, { forceFormData: true, preserveScroll: true });
}
</script>
<style scoped>
@reference "../../../../../css/app.css";
.field { @apply w-full border border-gray-200 rounded-lg px-3 py-2 text-sm; }
</style>
