<template>
    <AdminLayout title="Qualifier Intake Scrutiny">
        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Header Banner -->
            <div class="bg-gradient-to-r from-[color:var(--brand-navy)] via-[color:var(--brand-navy-hover)] to-[color:var(--brand-navy)] rounded-3xl p-6 sm:p-8 text-white shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <Link href="/admin/state-workspace/qualifiers" class="text-xs font-bold text-slate-300 hover:text-white transition">← All Intakes</Link>
                        <span class="text-slate-600">/</span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase border"
                              :class="{
                                  'bg-amber-500/20 text-amber-300 border-amber-400/20': intake.status === 'received',
                                  'bg-emerald-500/20 text-emerald-300 border-emerald-400/20': intake.status === 'approved',
                                  'bg-rose-500/20 text-rose-300 border-rose-400/20': intake.status === 'rejected',
                              }">
                            ● {{ intake.status }}
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight">
                        Intake: <span class="font-mono text-slate-300">{{ intake.source_tenant_id }}</span>
                    </h1>
                    <p class="text-slate-300 text-xs sm:text-sm">
                        Program: <span class="font-bold text-white">{{ stateProgram?.title || intake.state_program_id }}</span> · {{ intake.entries?.length || 0 }} total entries
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" @click="openAddEntryModal" class="px-4 py-2.5 rounded-2xl bg-white hover:bg-slate-100 text-[color:var(--brand-navy)] font-bold text-xs shadow-md transition">
                        ➕ Add Entry
                    </button>
                    <button v-if="intake.status === 'received'" type="button" @click="approve" class="px-5 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-lg shadow-emerald-600/30 transition">
                        Finalize & Approve Intake
                    </button>
                </div>
            </div>

            <!-- Entries Table Card -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <h2 class="text-base font-bold text-slate-900">Qualifier Entries</h2>
                    <span class="text-xs font-bold text-slate-500">{{ intake.entries?.length || 0 }} entry/entries</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider font-semibold border-b border-slate-100">
                                <th class="py-3 px-4">Item</th>
                                <th class="py-3 px-4">Student</th>
                                <th class="py-3 px-4">School</th>
                                <th class="py-3 px-4 text-center">Position</th>
                                <th class="py-3 px-4 text-center">Grade</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr v-for="e in intake.entries" :key="e.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-3.5 px-4 font-bold text-slate-900">
                                    {{ e.item_name || e.item_code }}
                                    <span v-if="e.item_code" class="block text-xs font-mono font-normal text-slate-400">Code: {{ e.item_code }}</span>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-slate-800">
                                    {{ e.student_name }}
                                </td>
                                <td class="py-3.5 px-4 text-slate-600">
                                    {{ e.school_name || e.school_id }}
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-[color:var(--brand-blue)]/10 text-[color:var(--brand-blue)] border border-[color:var(--brand-blue)]/30">
                                        #{{ e.position }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono font-bold">
                                    {{ e.grade || '—' }}
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold capitalize border"
                                          :class="{
                                              'bg-emerald-50 text-emerald-700 border-emerald-200': e.status === 'approved',
                                              'bg-rose-50 text-rose-700 border-rose-200': e.status === 'rejected',
                                              'bg-amber-50 text-amber-700 border-amber-200': e.status === 'pending',
                                          }">
                                        {{ e.status }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" @click="openEditEntryModal(e)" class="px-2.5 py-1 rounded-lg bg-[color:var(--brand-blue)]/10 hover:bg-[color:var(--brand-blue)]/15 text-[color:var(--brand-blue)] font-bold text-xs border border-[color:var(--brand-blue)]/30 transition">
                                            ✏️ Edit
                                        </button>
                                        <button v-if="e.status !== 'approved'" type="button" @click="review(e, 'approved')" class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-xs border border-emerald-200/60 transition">
                                            Approve
                                        </button>
                                        <button v-if="e.status !== 'rejected'" type="button" @click="review(e, 'rejected')" class="px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs border border-rose-200/60 transition">
                                            Reject
                                        </button>
                                        <button type="button" @click="deleteEntry(e)" class="px-2 py-1 rounded-lg text-slate-400 hover:text-red-600 font-bold text-xs transition">
                                            🗑
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!intake.entries?.length">
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    No qualifier entries found in this intake. Click "+ Add Entry" above to add one manually.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- EDIT / ADD ENTRY MODAL -->
            <div v-if="showEntryModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 space-y-5 border border-slate-100" @click.stop>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Edit Qualifier Entry' : 'Add Qualifier Entry' }}</h3>
                            <p class="text-xs text-slate-500">Update qualifier student details, school name, and position.</p>
                        </div>
                        <button type="button" @click="closeEntryModal" class="text-slate-400 hover:text-slate-600 text-xl font-bold">✕</button>
                    </div>

                    <form @submit.prevent="submitEntry" class="space-y-4">
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Student Name *</label>
                                <input v-model="entryForm.student_name" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" required>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">School Name *</label>
                                <input v-model="entryForm.school_name" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" required>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Item Code</label>
                                <input v-model="entryForm.item_code" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" placeholder="e.g. 101">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Item Name</label>
                                <input v-model="entryForm.item_name" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" placeholder="e.g. Light Music">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Position *</label>
                                <input v-model.number="entryForm.position" type="number" min="1" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" required>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Grade</label>
                                <input v-model="entryForm.grade" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" placeholder="e.g. A">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Status</label>
                                <select v-model="entryForm.status" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                    <option value="pending">Pending Review</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                            <button type="button" @click="closeEntryModal" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition">
                                Cancel
                            </button>
                            <button type="submit" :disabled="entryForm.processing" class="btn-primary !min-h-0 text-xs font-bold">
                                {{ isEditing ? 'Save Changes' : 'Add Entry' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    intake: Object,
    stateProgram: Object,
    sahodaya: Object,
    actionUrls: { type: Object, required: true },
});

const showEntryModal = ref(false);
const isEditing = ref(false);
const editingEntry = ref(null);

const entryForm = useForm({
    student_name: '',
    school_name: '',
    item_code: '',
    item_name: '',
    position: 1,
    grade: 'A',
    status: 'approved',
});

function openAddEntryModal() {
    isEditing.value = false;
    editingEntry.value = null;
    entryForm.reset();
    showEntryModal.value = true;
}

function openEditEntryModal(entry) {
    isEditing.value = true;
    editingEntry.value = entry;
    entryForm.student_name = entry.student_name ?? '';
    entryForm.school_name = entry.school_name ?? entry.school_id ?? '';
    entryForm.item_code = entry.item_code ?? '';
    entryForm.item_name = entry.item_name ?? '';
    entryForm.position = entry.position ?? 1;
    entryForm.grade = entry.grade ?? 'A';
    entryForm.status = entry.status ?? 'approved';
    showEntryModal.value = true;
}

function closeEntryModal() {
    showEntryModal.value = false;
    editingEntry.value = null;
    entryForm.reset();
}

function submitEntry() {
    if (isEditing.value && editingEntry.value) {
        entryForm.put(`${props.actionUrls.reviewEntryBase}/${editingEntry.value.id}`, {
            preserveScroll: true,
            onSuccess: () => closeEntryModal(),
        });
    } else {
        entryForm.post(props.actionUrls.storeEntry, {
            preserveScroll: true,
            onSuccess: () => closeEntryModal(),
        });
    }
}

function approve() {
    router.post(props.actionUrls.approve, {}, { preserveScroll: true });
}

function review(entry, status) {
    router.post(`${props.actionUrls.reviewEntryBase}/${entry.id}/review`, { status }, { preserveScroll: true });
}

function deleteEntry(entry) {
    if (confirm(`Remove qualifier entry for "${entry.student_name}"?`)) {
        router.delete(`${props.actionUrls.reviewEntryBase}/${entry.id}`, { preserveScroll: true });
    }
}
</script>
