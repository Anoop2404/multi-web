<template>
    <SchoolAdminLayout title="Contact Information" :school="school" :show-header-title="false">
        <PageHeader title="Contact Information" eyebrow="Website"
            description="School website content and public pages." />


        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Contact Details -->
                <div class="card space-y-4">
                    <h3 class="font-bold text-gray-800">Contact Details</h3>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label mb-1.5">Phone *</label>
                            <input v-model="form.phone" type="tel" required
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Email *</label>
                            <input v-model="form.email" type="email" required
                                   class="field">
                        </div>
                    </div>

                    <div>
                        <label class="form-label mb-1.5">Address *</label>
                        <textarea v-model="form.address" rows="3" required
                                  class="field resize-none"></textarea>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label mb-1.5">City / Town</label>
                            <input v-model="form.city" type="text"
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Pincode</label>
                            <input v-model="form.pincode" type="text"
                                   class="field">
                        </div>
                    </div>

                    <div>
                        <label class="form-label mb-1.5">Google Maps Embed URL</label>
                        <input v-model="form.map_url" type="url" placeholder="https://maps.google.com/?q=..."
                               class="field font-mono">
                        <p class="text-xs text-gray-400 mt-1">Paste the full Google Maps embed src URL to show a map on the contact page.</p>
                    </div>
                </div>

                <!-- Working Hours -->
                <div class="card space-y-4">
                    <h3 class="font-bold text-gray-800">Working Hours</h3>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label mb-1.5">Weekdays</label>
                            <input v-model="form.weekday_hours" type="text" placeholder="Mon–Fri: 8:00 AM – 3:30 PM"
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Saturday</label>
                            <input v-model="form.saturday_hours" type="text" placeholder="Sat: 8:00 AM – 12:30 PM"
                                   class="field">
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button type="submit" :disabled="form.processing"
                            class="btn-primary disabled:opacity-50">
                        Save Contact Info
                    </button>
                    <span v-if="saved" class="text-sm text-green-600 font-medium">✓ Saved!</span>
                </div>
            </form>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    school:   Object,
    settings: { type: Object, default: () => ({}) },
});

const saved = ref(false);

const contact = props.settings.contact ?? {};

const form = useForm({
    phone:           contact.phone ?? '',
    email:           contact.email ?? '',
    address:         contact.address ?? '',
    city:            contact.city ?? '',
    pincode:         contact.pincode ?? '',
    map_url:         contact.map_url ?? '',
    weekday_hours:   contact.weekday_hours ?? '',
    saturday_hours:  contact.saturday_hours ?? '',
});

function submit() {
    saved.value = false;
    form.post(`/school-admin/${props.school.id}/contact`, {
        onSuccess: () => {
            saved.value = true;
            setTimeout(() => { saved.value = false; }, 3000);
        },
    });
}
</script>