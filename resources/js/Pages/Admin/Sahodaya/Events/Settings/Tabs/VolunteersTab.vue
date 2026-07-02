<template>
    <div class="space-y-6 max-w-2xl">
        <section class="card space-y-4">
            <div>
                <h3 class="section-title">Volunteers</h3>
                <p class="section-desc">Event-day volunteers and duty assignments.</p>
            </div>
            <form @submit.prevent="addVolunteer" class="grid gap-3 sm:grid-cols-2">
                <FormField label="Full name" class-extra="sm:col-span-2" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="volunteerForm.name" class="field" required>
                    </template>
                </FormField>
                <FormField label="Phone">
                    <template #default="{ id }">
                        <input :id="id" v-model="volunteerForm.phone" type="tel" class="field">
                    </template>
                </FormField>
                <FormField label="Duty">
                    <template #default="{ id }">
                        <input :id="id" v-model="volunteerForm.duty" class="field" placeholder="Registration desk">
                    </template>
                </FormField>
                <FormField label="Notes" class-extra="sm:col-span-2">
                    <template #default="{ id }">
                        <textarea :id="id" v-model="volunteerForm.notes" class="field" rows="2"></textarea>
                    </template>
                </FormField>
                <button type="submit" class="btn-primary sm:col-span-2">Add volunteer</button>
            </form>
        </section>

        <input v-if="volunteers.length" v-model="searchQuery" type="search" class="field max-w-md" placeholder="Search volunteers…">

        <section class="form-section overflow-hidden !p-0">
            <EmptyState v-if="!filteredVolunteers.length" title="No volunteers" description="Add volunteers using the form above." icon="🙋" class="p-8" />
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Duty</th>
                            <th>Phone</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="v in filteredVolunteers" :key="v.id">
                            <td class="font-medium">{{ v.name }}</td>
                            <td>{{ v.duty || '—' }}</td>
                            <td>{{ v.phone || '—' }}</td>
                            <td class="text-right">
                                <button type="button" @click="removeVolunteer(v.id)" class="text-red-600 text-xs">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, inject, ref } from 'vue';

const { volunteerForm, volunteers, addVolunteer, removeVolunteer } = inject('eventSettings');
const searchQuery = ref('');

const filteredVolunteers = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return volunteers;
    return volunteers.filter((v) => [v.name, v.duty, v.phone, v.notes].filter(Boolean).join(' ').toLowerCase().includes(q));
});
</script>
