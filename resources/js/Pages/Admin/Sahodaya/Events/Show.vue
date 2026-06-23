<template>
    <SahodayaAdminLayout :title="event.title" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <form @submit.prevent="saveEvent" class="bg-white border rounded-xl p-4 space-y-3">
                    <input v-model="form.title" class="field" required>
                    <select v-model="form.status" class="field">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="registration_open">Registration Open</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                    </select>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.results_published"> Results published
                    </label>
                    <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Save</button>
                </form>

                <div class="bg-white border rounded-xl p-4">
                    <h3 class="font-semibold mb-3">Event Items</h3>
                    <form @submit.prevent="addItem" class="flex flex-wrap gap-2 mb-4">
                        <input v-model="itemForm.title" class="field flex-1 min-w-[140px]" placeholder="Item name" required>
                        <select v-model="itemForm.participant_type" class="field w-auto">
                            <option value="individual">Individual</option>
                            <option value="group">Group</option>
                            <option value="team">Team</option>
                        </select>
                        <button class="px-3 py-2 bg-gray-900 text-white rounded-lg text-sm">Add</button>
                    </form>
                    <ul class="divide-y">
                        <li v-for="item in event.items" :key="item.id" class="py-2 flex justify-between text-sm">
                            <span>{{ item.title }} <span class="text-gray-400">({{ item.participant_type }})</span></span>
                            <button @click="removeItem(item.id)" class="text-red-600 text-xs">Remove</button>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="space-y-3">
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/registrations`" class="block bg-white border rounded-xl p-4 hover:border-indigo-300">Registrations</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/attendance`" class="block bg-white border rounded-xl p-4 hover:border-indigo-300">Attendance</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/schedule`" class="block bg-white border rounded-xl p-4 hover:border-indigo-300">Schedule</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/judges`" class="block bg-white border rounded-xl p-4 hover:border-indigo-300">Judges</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/marks`" class="block bg-white border rounded-xl p-4 hover:border-indigo-300">Mark Entry</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/chest-numbers`" class="block bg-white border rounded-xl p-4 hover:border-indigo-300">Chest Numbers</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/fees`" class="block bg-white border rounded-xl p-4 hover:border-indigo-300">Event Fees</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/results`" class="block bg-white border rounded-xl p-4 hover:border-indigo-300">Results & Exports</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates`" class="block bg-white border rounded-xl p-4 hover:border-indigo-300">Certificates</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/houses`" class="block bg-white border rounded-xl p-4 hover:border-indigo-300">Houses</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/appeals`" class="block bg-white border rounded-xl p-4 hover:border-indigo-300">Appeals</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/catering`" class="block bg-white border rounded-xl p-4 hover:border-indigo-300">Catering</Link>

                <div class="bg-white border rounded-xl p-4">
                    <h4 class="font-semibold text-sm mb-2">Multi-level</h4>
                    <form @submit.prevent="spawnChild" class="flex gap-2">
                        <input v-model="cascadeForm.title" class="field flex-1" placeholder="Child event title" required>
                        <button class="px-3 py-2 bg-amber-600 text-white rounded-lg text-xs whitespace-nowrap">Spawn child</button>
                    </form>
                    <ul v-if="event.child_events?.length" class="text-xs mt-2 space-y-1">
                        <li v-for="c in event.child_events" :key="c.id">
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${c.id}`" class="text-indigo-600">{{ c.title }}</Link>
                        </li>
                    </ul>
                    <p v-if="event.parent_event" class="text-xs text-gray-500 mt-2">
                        Parent: <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.parent_event.id}`" class="text-indigo-600">{{ event.parent_event.title }}</Link>
                    </p>
                    <a :href="`/fest/${event.id}`" target="_blank" rel="noopener" class="block text-xs text-indigo-600 mt-3">Public fest portal ↗</a>
                </div>

                <div class="bg-white border rounded-xl p-4">
                    <h4 class="font-semibold text-sm mb-2">Scoreboard</h4>
                    <ol class="text-sm space-y-1">
                        <li v-for="row in scoreboard" :key="row.school_id">
                            #{{ row.rank }} {{ row.school_name }} — {{ row.total_points }} pts
                        </li>
                        <li v-if="!scoreboard.length" class="text-gray-400">No results yet</li>
                    </ol>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, useForm, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, scoreboard: Array,
});

const form = useForm({ ...props.event });
const itemForm = useForm({ title: '', participant_type: 'individual' });
const cascadeForm = useForm({ title: '' });

function saveEvent() {
    form.put(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`, { preserveScroll: true });
}
function addItem() {
    itemForm.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/items`, {
        preserveScroll: true, onSuccess: () => itemForm.reset(),
    });
}
function removeItem(id) {
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/items/${id}`, { preserveScroll: true });
}
function spawnChild() {
    cascadeForm.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/spawn`, {
        preserveScroll: true,
        onSuccess: () => cascadeForm.reset(),
    });
}
</script>

<style scoped>
@reference "../../../../../css/app.css";
.field { @apply w-full border border-gray-200 rounded-lg px-3 py-2 text-sm; }
</style>
