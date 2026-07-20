<template>
    <section class="card !p-5 space-y-4 border border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="section-title !mb-0 flex items-center gap-2 text-base">
                    <span>🙋</span> Volunteers &amp; Staff
                </h3>
                <p class="section-desc mt-0.5">Event-day volunteers, staff rosters, and duty assignments.</p>
            </div>
            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
                {{ volunteers.length }} volunteer{{ volunteers.length === 1 ? '' : 's' }}
            </span>
        </div>

        <!-- Inline Add Volunteer Form -->
        <form @submit.prevent="addVolunteer" class="bg-slate-50/80 p-4 rounded-xl border border-slate-200/80 space-y-3">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">+ Add New Volunteer / Staff</h4>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="form-label text-xs">Full name *</label>
                    <input v-model="volunteerForm.name" class="field text-xs" placeholder="e.g. John Doe" required>
                </div>
                <div>
                    <label class="form-label text-xs">Phone number</label>
                    <input v-model="volunteerForm.phone" type="tel" class="field text-xs" placeholder="e.g. +91 9876543210">
                </div>
                <div>
                    <label class="form-label text-xs">Duty / Assignment</label>
                    <input v-model="volunteerForm.duty" class="field text-xs" placeholder="e.g. Registration Desk, Stage 1 Manager">
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label text-xs">Notes / Special Instructions</label>
                    <input v-model="volunteerForm.notes" class="field text-xs" placeholder="e.g. Available morning shift only">
                </div>
            </div>
            <div class="flex justify-end pt-1">
                <button type="submit" class="btn-primary text-xs !py-1.5 !px-4">Add Volunteer</button>
            </div>
        </form>

        <!-- Volunteer Search & Roster List -->
        <div v-if="volunteers.length" class="space-y-3 pt-2">
            <div class="flex items-center justify-between gap-3">
                <input v-model="searchQuery" type="search" class="field text-xs max-w-xs" placeholder="Search volunteers by name, duty, phone…">
                <span class="text-xs text-slate-500 font-medium tabular-nums">Showing {{ filteredVolunteers.length }} of {{ volunteers.length }}</span>
            </div>

            <div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-slate-500 border-b border-slate-200 uppercase tracking-wider text-[10px] font-bold">
                        <tr>
                            <th class="p-3.5">Name</th>
                            <th class="p-3.5">Duty / Role</th>
                            <th class="p-3.5">Phone</th>
                            <th class="p-3.5">Notes</th>
                            <th class="p-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="v in filteredVolunteers" :key="v.id" class="hover:bg-slate-50/70 transition">
                            <td class="p-3.5 font-bold text-slate-900">{{ v.name }}</td>
                            <td class="p-3.5">
                                <span v-if="v.duty" class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-bold text-indigo-700 border border-indigo-100">
                                    {{ v.duty }}
                                </span>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="p-3.5 font-mono text-slate-600">{{ v.phone || '—' }}</td>
                            <td class="p-3.5 text-slate-500 max-w-xs truncate">{{ v.notes || '—' }}</td>
                            <td class="p-3.5 text-right">
                                <button type="button" @click="removeVolunteer(v.id)" class="btn-secondary text-xs !text-rose-700 hover:!bg-rose-50 !py-1 !px-2.5">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div v-else class="rounded-xl border border-dashed border-slate-200 p-8 text-center text-slate-400 text-xs">
            No volunteers or duty assignments added yet. Use the form above to add staff for event day operations.
        </div>
    </section>
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
