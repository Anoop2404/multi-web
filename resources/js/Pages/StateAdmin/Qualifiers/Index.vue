<template>
    <AdminLayout title="State Qualifier Intakes">
        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Header Banner -->
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 text-xs font-semibold border border-indigo-400/20">
                        🏆 State Workspace · Scrutiny
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Qualifier Intakes</h1>
                    <p class="text-slate-300 text-xs sm:text-sm">Review, approve, and upload qualifier submissions from Sahodaya clusters and regional events.</p>
                </div>
                <button type="button" @click="showAddModal = true" class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs sm:text-sm shadow-lg shadow-indigo-600/30 transition">
                    ➕ Add Qualifier Data
                </button>
            </div>

            <!-- Intakes List -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <h2 class="text-base font-bold text-slate-900">Submitted Intakes</h2>
                    <span class="text-xs font-bold text-slate-500">{{ intakes.total || intakes.data?.length || 0 }} batch(es)</span>
                </div>

                <div class="grid gap-3">
                    <div v-for="intake in intakes.data" :key="intake.id" class="p-4 rounded-2xl border border-slate-200/80 hover:border-indigo-200 bg-slate-50/50 hover:bg-slate-50 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase border"
                                      :class="{
                                          'bg-amber-50 text-amber-800 border-amber-200': intake.status === 'received',
                                          'bg-emerald-50 text-emerald-800 border-emerald-200': intake.status === 'approved',
                                          'bg-rose-50 text-rose-800 border-rose-200': intake.status === 'rejected',
                                      }">
                                    ● {{ intake.status }}
                                </span>
                                <span class="font-mono text-xs font-bold text-slate-700 bg-white px-2 py-0.5 rounded border border-slate-200">
                                    {{ intake.source_tenant_id }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 font-medium">
                                Intake ID: <span class="font-mono">{{ intake.id.slice(0, 18) }}...</span> · {{ intake.entries_count }} qualifier entry/entries
                            </p>
                        </div>

                        <Link :href="intake.review_url" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-slate-900 hover:bg-indigo-600 text-white font-bold text-xs transition">
                            Review & Edit Entries →
                        </Link>
                    </div>

                    <div v-if="!intakes.data?.length" class="text-center py-12 text-slate-400 text-sm">
                        No qualifier intakes submitted yet. Click "+ Add Qualifier Data" above to enter qualifiers manually.
                    </div>
                </div>
            </div>

            <!-- ADD QUALIFIER DATA MODAL -->
            <div v-if="showAddModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-6 space-y-5 border border-slate-100 max-h-[90vh] overflow-y-auto" @click.stop>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Add Qualifier Data</h3>
                            <p class="text-xs text-slate-500">Create a new intake and add state qualifiers directly.</p>
                        </div>
                        <button type="button" @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">✕</button>
                    </div>

                    <form @submit.prevent="submitIntake" class="space-y-4">
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">State Program *</label>
                                <SearchableSelect v-model="form.state_program_id" class="w-full" :options="stateProgramOptions" :all-option="true" all-label="Select Program" :required="true" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Source Sahodaya / Cluster *</label>
                                <SearchableSelect v-model="form.source_tenant_id" class="w-full" :options="sahodayaOptions" :all-option="true" all-label="Select Sahodaya" :required="true" />
                            </div>
                        </div>

                        <!-- Qualifier Entries Rows -->
                        <div class="space-y-3 pt-2">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Qualifier Entries ({{ form.entries.length }})</h4>
                                <button type="button" @click="addEntryRow" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">+ Add Row</button>
                            </div>

                            <div v-for="(e, idx) in form.entries" :key="idx" class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2 relative">
                                <button type="button" v-if="form.entries.length > 1" @click="removeEntryRow(idx)" class="absolute top-2 right-2 text-red-500 hover:text-red-700 font-bold text-xs px-2 py-1">✕</button>
                                
                                <div class="grid sm:grid-cols-2 gap-2">
                                    <input v-model="e.student_name" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium" placeholder="Student Name *" required>
                                    <input v-model="e.school_name" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium" placeholder="School Name *" required>
                                </div>
                                <div class="grid sm:grid-cols-4 gap-2">
                                    <input v-model="e.item_code" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium" placeholder="Item Code (e.g. 101)">
                                    <input v-model="e.item_name" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium" placeholder="Item Name (e.g. Light Music)">
                                    <input v-model.number="e.position" type="number" min="1" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium" placeholder="Pos (1, 2...)" required>
                                    <input v-model="e.grade" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium" placeholder="Grade (A, B...)">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                            <button type="button" @click="showAddModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition">
                                Cancel
                            </button>
                            <button type="submit" :disabled="form.processing" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition shadow-md">
                                Save Qualifier Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    intakes: Object,
    statePrograms: Array,
    sahodayas: Array,
    actionUrls: Object,
});

const showAddModal = ref(false);

const stateProgramOptions = computed(() => (props.statePrograms || []).map(p => ({ value: p.id, label: p.title })));
const sahodayaOptions = computed(() => (props.sahodayas || []).map(s => ({ value: s.id, label: `${s.name} (${s.id})` })));

const form = useForm({
    state_program_id: '',
    source_tenant_id: '',
    entries: [
        { student_name: '', school_name: '', item_code: '', item_name: '', position: 1, grade: 'A' },
    ],
});

function addEntryRow() {
    form.entries.push({ student_name: '', school_name: '', item_code: '', item_name: '', position: 1, grade: 'A' });
}

function removeEntryRow(idx) {
    form.entries.splice(idx, 1);
}

function submitIntake() {
    form.post(props.actionUrls.storeIntake, {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
        },
    });
}
</script>
