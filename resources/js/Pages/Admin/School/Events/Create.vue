<template>
    <SchoolAdminLayout title="New Event" :school="school">
        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="space-y-5">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Event Title *</label>
                        <input v-model="form.title" type="text" required
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Start Date *</label>
                            <input v-model="form.start_date" type="date" required
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">End Date</label>
                            <input v-model="form.end_date" type="date"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Venue</label>
                        <input v-model="form.venue" type="text" placeholder="School Auditorium, Thrissur, etc."
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Description</label>
                        <textarea v-model="form.description" rows="5"
                                  class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Event Image</label>
                        <input type="file" accept="image/*" @change="form.image = $event.target.files[0]"
                               class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button type="submit" :disabled="form.processing"
                            class="bg-green-600 text-white px-6 py-2.5 rounded-lg font-semibold text-sm hover:bg-green-700 transition disabled:opacity-50">
                        Create Event
                    </button>
                    <Link :href="`/school-admin/${school.id}/events`" class="text-sm text-gray-500 hover:text-gray-700">Cancel</Link>
                </div>
            </form>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ school: Object });

const form = useForm({
    title:       '',
    start_date:  '',
    end_date:    '',
    venue:       '',
    description: '',
    image:       null,
});

function submit() {
    form.post(`/school-admin/${props.school.id}/events`, { forceFormData: true });
}
</script>
