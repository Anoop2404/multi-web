<template>
    <AdminLayout title="State Remittances">
        <!-- Summary -->
        <div class="grid sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-yellow-50 border border-yellow-100 rounded-xl p-4">
                <p class="text-xs text-yellow-700 font-bold uppercase">Pending</p>
                <p class="text-2xl font-bold text-yellow-900">{{ summary.pending }}</p>
            </div>
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                <p class="text-xs text-blue-700 font-bold uppercase">Submitted</p>
                <p class="text-2xl font-bold text-blue-900">{{ summary.submitted }}</p>
            </div>
            <div class="bg-green-50 border border-green-100 rounded-xl p-4">
                <p class="text-xs text-green-700 font-bold uppercase">Verified</p>
                <p class="text-2xl font-bold text-green-900">{{ summary.verified }}</p>
            </div>
            <div class="bg-green-50 border border-green-100 rounded-xl p-4">
                <p class="text-xs text-green-700 font-bold uppercase">Total Collected</p>
                <p class="text-2xl font-bold text-green-900">₹{{ fmt(summary.amount) }}</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-4">
            <!-- Create demand form -->
            <div class="card space-y-3">
                <h3 class="font-semibold text-sm">Create Remittance Demand</h3>
                <form @submit.prevent="createDemand" class="space-y-2">
                    <select v-model="form.sahodaya_id" class="field" required>
                        <option value="">Select Sahodaya</option>
                        <option v-for="s in sahodayas" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <input v-model="form.title" class="field" placeholder="Title (e.g. Annual contribution)" required>
                    <textarea v-model="form.description" class="field" rows="2" placeholder="Description (optional)"></textarea>
                    <div class="grid grid-cols-2 gap-2">
                        <input v-model="form.amount" type="number" min="0.01" step="0.01" class="field" placeholder="Amount (₹)" required>
                        <input v-model="form.academic_year" class="field" placeholder="Academic year">
                    </div>
                    <input v-model="form.due_date" type="date" class="field">
                    <button class="w-full px-3 py-2 text-white rounded-lg text-sm font-semibold">Create demand</button>
                </form>
            </div>

            <!-- Remittances table -->
            <div class="lg:col-span-2 bg-white border rounded-xl overflow-hidden">
                <div class="p-4 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-sm">All Remittances</h3>
                    <div class="flex gap-2">
                        <select v-model="filterStatus" @change="applyFilter" class="field text-xs py-1">
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="submitted">Submitted</option>
                            <option value="verified">Verified</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="p-3">Sahodaya</th>
                            <th class="p-3">Title</th>
                            <th class="p-3">Amount</th>
                            <th class="p-3">Due</th>
                            <th class="p-3">Status</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in remittances.data" :key="r.id" class="border-t align-top">
                            <td class="p-3 text-xs">{{ r.sahodaya?.name }}</td>
                            <td class="p-3">
                                <p>{{ r.title }}</p>
                                <p v-if="r.academic_year" class="text-xs text-gray-400">{{ r.academic_year }}</p>
                            </td>
                            <td class="p-3 font-mono">₹{{ fmt(r.amount) }}</td>
                            <td class="p-3 text-xs text-gray-500">{{ r.due_date || '—' }}</td>
                            <td class="p-3">
                                <span :class="statusClass(r.status)" class="text-xs font-semibold px-2 py-0.5 rounded">{{ r.status }}</span>
                                <p v-if="r.rejection_reason" class="text-xs text-red-500 mt-1">{{ r.rejection_reason }}</p>
                            </td>
                            <td class="p-3 text-right space-y-1">
                                <template v-if="r.status === 'submitted'">
                                    <div class="flex gap-2 justify-end">
                                        <a :href="`/admin/state-remittances/${r.id}/proof`" target="_blank" rel="noopener"
                                           class="text-xs text-indigo-600 font-semibold">Proof ↗</a>
                                        <button @click="verify(r)" class="text-xs text-green-600 font-semibold">Verify</button>
                                        <button @click="reject(r)" class="text-xs text-red-600 font-semibold">Reject</button>
                                    </div>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!remittances.data.length">
                            <td colspan="6" class="p-8 text-center text-gray-400">No remittances yet</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="remittances.links?.length > 3" class="px-4 py-3 border-t flex flex-wrap gap-1">
                    <Link v-for="link in remittances.links" :key="link.label"
                          :href="link.url || '#'"
                          :class="['px-3 py-1 rounded-lg text-xs', link.active ? 'bg-indigo-100 text-indigo-800 font-semibold' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                          v-html="link.label" />
                </div>
            </div>
        </div>

        <!-- Reject modal -->
        <div v-if="rejectTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="rejectTarget = null">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl space-y-3">
                <h3 class="font-semibold">Reject remittance payment</h3>
                <textarea v-model="rejectReason" class="w-full border rounded-lg px-3 py-2 text-sm" rows="3" placeholder="Reason (optional)"></textarea>
                <div class="flex gap-2 justify-end">
                    <button @click="rejectTarget = null" class="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                    <button @click="confirmReject" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold">Reject</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ remittances: Object, sahodayas: Array, summary: Object, filters: Object });

const form = useForm({ sahodaya_id: '', title: '', description: '', amount: '', academic_year: '', due_date: '' });
const filterStatus = ref(props.filters?.status ?? '');
const rejectTarget = ref(null);
const rejectReason = ref('');

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function statusClass(s) {
    return { pending: 'bg-yellow-50 text-yellow-700', submitted: 'bg-blue-50 text-blue-700', verified: 'bg-green-50 text-green-700', rejected: 'bg-red-50 text-red-600' }[s] ?? 'bg-gray-100 text-gray-600';
}

function createDemand() {
    form.post('/admin/state-remittances', { preserveScroll: true, onSuccess: () => form.reset() });
}

function applyFilter() {
    router.get('/admin/state-remittances', { status: filterStatus.value || undefined }, { preserveScroll: true });
}

function verify(r) {
    router.post(`/admin/state-remittances/${r.id}/verify`, {}, { preserveScroll: true });
}

function reject(r) {
    rejectTarget.value = r;
    rejectReason.value = '';
}

function confirmReject() {
    router.post(`/admin/state-remittances/${rejectTarget.value.id}/reject`, { rejection_reason: rejectReason.value }, {
        preserveScroll: true,
        onSuccess: () => { rejectTarget.value = null; },
    });
}
</script>

