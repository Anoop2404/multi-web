<template>
    <SahodayaAdminLayout title="Kalotsav Events" :sahodaya="sahodaya">
        <div class="space-y-6">
            <!-- Create event form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-bold text-gray-800 mb-4">Create New Kalotsav Event</h3>
                <form @submit.prevent="create" class="space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Event Name *</label>
                            <input v-model="form.name" type="text" required
                                   placeholder="Sahodaya Kalotsav 2024-25"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Type</label>
                            <select v-model="form.type"
                                    class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300 bg-white">
                                <option value="kalotsav">Kalotsav</option>
                                <option value="science_fair">Science Fair</option>
                                <option value="sports">Sports Meet</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Academic Year</label>
                            <input v-model="form.academic_year" type="text" placeholder="2024-25"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Event Date</label>
                            <input v-model="form.event_date" type="date"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Venue</label>
                            <input v-model="form.venue" type="text" placeholder="Sahodaya Bhavan, Thrissur"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                    </div>
                    <button type="submit" :disabled="form.processing"
                            class="bg-purple-600 text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-purple-700 transition disabled:opacity-50">
                        Create Event →
                    </button>
                </form>
            </div>

            <!-- Events list -->
            <div class="space-y-3">
                <div v-for="event in events" :key="event.id"
                     class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h4 class="font-bold text-gray-800">{{ event.name }}</h4>
                            <span :class="event.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-400'"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ event.is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <span v-if="event.results_published"
                                  class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full font-medium">
                                Results Published
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                            <span v-if="event.academic_year">{{ event.academic_year }}</span>
                            <span v-if="event.venue">📍 {{ event.venue }}</span>
                            <span v-if="event.event_date">
                                {{ new Date(event.event_date).toLocaleDateString('en-IN') }}
                            </span>
                            <span>{{ event.categories_count }} categories</span>
                            <span>{{ event.results_count }} results</span>
                        </div>
                    </div>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/kalotsav/${event.id}`"
                          class="shrink-0 bg-purple-50 text-purple-700 text-xs font-semibold px-4 py-2 rounded-lg hover:bg-purple-100 transition">
                        Manage →
                    </Link>
                </div>

                <div v-if="!events.length"
                     class="bg-white rounded-xl border border-dashed border-gray-200 p-12 text-center text-gray-400">
                    No Kalotsav events yet. Create one above.
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    sahodaya: Object,
    events:   { type: Array, default: () => [] },
});

const form = useForm({
    name:          '',
    type:          'kalotsav',
    academic_year: '',
    event_date:    '',
    venue:         '',
});

function create() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/kalotsav`);
}
</script>
