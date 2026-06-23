<template>
    <AdminLayout title="Subscription & Billing">
        <div class="space-y-6 max-w-6xl">

            <!-- Stats strip -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <StatCard :value="stats.active"           label="Active"          color="green"  />
                <StatCard :value="stats.grace"            label="In Grace"        color="amber"  />
                <StatCard :value="stats.readonly"         label="Read-only"       color="red"    />
                <StatCard :value="stats.pending_receipts" label="Pending Receipts" color="indigo" />
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-200 gap-1">
                <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
                        :class="['px-4 py-2.5 text-sm font-semibold border-b-2 transition',
                                 activeTab === t.key ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700']">
                    {{ t.label }}
                    <span v-if="t.key === 'receipts' && stats.pending_receipts > 0"
                          class="ml-1.5 px-1.5 py-0.5 bg-red-500 text-white text-xs rounded-full">
                        {{ stats.pending_receipts }}
                    </span>
                </button>
            </div>

            <!-- Tab: Pending Receipts -->
            <div v-show="activeTab === 'receipts'">
                <h3 class="font-semibold text-gray-900 mb-3">Pending Subscription Receipts</h3>
                <div v-if="!pendingReceipts.length" class="py-10 text-center text-sm text-gray-400">
                    No pending receipts.
                </div>
                <div v-else class="divide-y divide-gray-100 rounded-xl border border-gray-200 overflow-hidden">
                    <div v-for="r in pendingReceipts" :key="r.id"
                         class="flex flex-col sm:flex-row sm:items-center gap-3 px-4 py-3 bg-white">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ r.invoice?.tenant?.name }}</p>
                            <p class="text-xs text-gray-500">Invoice {{ r.invoice?.invoice_number }} · ₹{{ formatAmount(r.invoice?.amount) }} · Due {{ r.invoice?.due_date }}</p>
                            <p v-if="r.transaction_ref" class="text-xs text-gray-400 mt-0.5">Ref: {{ r.transaction_ref }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a :href="`/admin/billing/receipts/${r.id}/file`" target="_blank"
                               class="text-xs px-2.5 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition">
                                View Receipt
                            </a>
                            <button @click="approveReceipt(r)"
                                    class="text-xs px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium transition">
                                Approve
                            </button>
                            <button @click="openRejectModal(r)"
                                    class="text-xs px-3 py-1.5 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 font-medium transition">
                                Reject
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Subscriptions -->
            <div v-show="activeTab === 'subscriptions'">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900">Tenant Subscriptions</h3>
                    <button @click="showSubForm = !showSubForm"
                            class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                        + Add / Update
                    </button>
                </div>

                <form v-if="showSubForm" @submit.prevent="saveSubscription"
                      class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-xl space-y-3">
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="label-xs">Tenant <span class="text-red-500">*</span></label>
                            <select v-model="subForm.tenant_id" class="field" required>
                                <option value="">Select tenant…</option>
                                <option v-for="t in tenantsForSelect" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label-xs">Plan</label>
                            <select v-model="subForm.plan_id" class="field">
                                <option value="">No plan</option>
                                <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label-xs">Period Start <span class="text-red-500">*</span></label>
                            <input v-model="subForm.period_start" type="date" class="field" required>
                        </div>
                        <div>
                            <label class="label-xs">Period End <span class="text-red-500">*</span></label>
                            <input v-model="subForm.period_end" type="date" class="field" required>
                        </div>
                        <div>
                            <label class="label-xs">Status <span class="text-red-500">*</span></label>
                            <select v-model="subForm.status" class="field" required>
                                <option value="active">Active</option>
                                <option value="grace">Grace Period</option>
                                <option value="readonly">Read-only</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary text-sm px-4 py-2">Save</button>
                        <button type="button" @click="showSubForm = false" class="btn-ghost text-sm px-4 py-2">Cancel</button>
                    </div>
                </form>

                <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 overflow-hidden">
                    <div v-if="!subscriptions.data?.length" class="py-10 text-center text-sm text-gray-400">
                        No subscriptions recorded.
                    </div>
                    <div v-for="s in subscriptions.data" :key="s.id"
                         class="flex items-center gap-4 px-4 py-3 bg-white">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ s.tenant?.name }}</p>
                            <p class="text-xs text-gray-500">{{ s.plan?.name ?? 'No plan' }} · {{ s.period_start }} – {{ s.period_end }}</p>
                        </div>
                        <span :class="subStatusBadge(s.status)" class="text-xs font-semibold px-2.5 py-0.5 rounded-full">
                            {{ s.status }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tab: Plans -->
            <div v-show="activeTab === 'plans'">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900">Subscription Plans</h3>
                    <button @click="showPlanForm = !showPlanForm"
                            class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                        + Add Plan
                    </button>
                </div>

                <form v-if="showPlanForm" @submit.prevent="savePlan"
                      class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-xl space-y-3">
                    <div class="grid sm:grid-cols-3 gap-3">
                        <div>
                            <label class="label-xs">Plan Name <span class="text-red-500">*</span></label>
                            <input v-model="planForm.name" class="field" placeholder="Basic" required>
                        </div>
                        <div>
                            <label class="label-xs">Slug <span class="text-red-500">*</span></label>
                            <input v-model="planForm.slug" class="field font-mono" placeholder="basic" required>
                        </div>
                        <div>
                            <label class="label-xs">Price (₹) <span class="text-red-500">*</span></label>
                            <input v-model="planForm.price_inr" type="number" step="0.01" class="field" required>
                        </div>
                        <div>
                            <label class="label-xs">Billing Period</label>
                            <select v-model="planForm.billing_period" class="field">
                                <option value="annual">Annual</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary text-sm px-4 py-2">Create Plan</button>
                        <button type="button" @click="showPlanForm = false" class="btn-ghost text-sm px-4 py-2">Cancel</button>
                    </div>
                </form>

                <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 overflow-hidden">
                    <div v-if="!plans.length" class="py-10 text-center text-sm text-gray-400">
                        No plans created.
                    </div>
                    <div v-for="p in plans" :key="p.id"
                         class="flex items-center gap-4 px-4 py-3 bg-white">
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-gray-900">{{ p.name }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ p.slug }}</p>
                        </div>
                        <p class="text-sm font-semibold text-gray-700">₹{{ formatAmount(p.price_inr) }} / {{ p.billing_period }}</p>
                        <span :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                              class="text-xs font-semibold px-2.5 py-0.5 rounded-full">
                            {{ p.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Reject Modal -->
        <div v-if="rejectModal.open" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl">
                <h3 class="font-semibold text-gray-900 mb-3">Reject Receipt</h3>
                <textarea v-model="rejectModal.reason" rows="3" class="field w-full mb-4"
                          placeholder="Reason for rejection…"></textarea>
                <div class="flex gap-2 justify-end">
                    <button @click="rejectModal.open = false" class="btn-ghost px-4 py-2 text-sm">Cancel</button>
                    <button @click="confirmReject" class="px-4 py-2 bg-red-600 text-white rounded-xl text-sm font-medium hover:bg-red-700">
                        Reject
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, reactive, computed, defineComponent, h } from 'vue';

const StatCard = defineComponent({
    props: { value: [String, Number], label: String, color: String },
    setup(props) {
        const colors = {
            green:  'bg-green-50 border-green-200 text-green-800',
            amber:  'bg-amber-50 border-amber-200 text-amber-800',
            red:    'bg-red-50 border-red-200 text-red-800',
            indigo: 'bg-indigo-50 border-indigo-200 text-indigo-800',
            blue:   'bg-blue-50 border-blue-200 text-blue-800',
        };
        return () => h('div', { class: `rounded-xl border p-4 ${colors[props.color] ?? colors.indigo}` }, [
            h('p', { class: 'text-xs font-semibold uppercase tracking-widest opacity-70 mb-1' }, props.label),
            h('p', { class: 'text-3xl font-extrabold' }, props.value ?? '—'),
        ]);
    },
});

const props = defineProps({
    plans:          Array,
    subscriptions:  Object,
    pendingReceipts:Array,
    stats:          Object,
});

const activeTab = ref(props.stats.pending_receipts > 0 ? 'receipts' : 'subscriptions');
const tabs = [
    { key: 'receipts',      label: 'Pending Receipts' },
    { key: 'subscriptions', label: 'Subscriptions' },
    { key: 'plans',         label: 'Plans' },
];

// Tenants from subscriptions for select (limited to what's loaded)
const tenantsForSelect = computed(() =>
    (props.subscriptions.data ?? []).map(s => s.tenant).filter(Boolean)
);

// ─── Plan form ─────────────────────────────────────────────────────────────
const showPlanForm = ref(false);
const planForm = reactive({ name: '', slug: '', price_inr: '', billing_period: 'annual' });

function savePlan() {
    router.post('/admin/billing/plans', planForm, {
        onSuccess: () => { showPlanForm.value = false; Object.assign(planForm, { name: '', slug: '', price_inr: '', billing_period: 'annual' }); },
    });
}

// ─── Subscription form ─────────────────────────────────────────────────────
const showSubForm = ref(false);
const subForm = reactive({ tenant_id: '', plan_id: '', period_start: '', period_end: '', status: 'active' });

function saveSubscription() {
    router.post('/admin/billing/subscriptions', subForm, {
        onSuccess: () => { showSubForm.value = false; },
    });
}

// ─── Receipt actions ───────────────────────────────────────────────────────
function approveReceipt(r) {
    if (!confirm('Approve this receipt and activate the subscription?')) return;
    router.post(`/admin/billing/receipts/${r.id}/approve`);
}

const rejectModal = reactive({ open: false, receipt: null, reason: '' });

function openRejectModal(r) {
    rejectModal.receipt = r;
    rejectModal.reason  = '';
    rejectModal.open    = true;
}

function confirmReject() {
    router.post(`/admin/billing/receipts/${rejectModal.receipt.id}/reject`, { rejection_reason: rejectModal.reason }, {
        onSuccess: () => { rejectModal.open = false; },
    });
}

// ─── Helpers ───────────────────────────────────────────────────────────────
function formatAmount(v) {
    return Number(v ?? 0).toLocaleString('en-IN');
}

function subStatusBadge(status) {
    const map = {
        active:    'bg-green-100 text-green-700',
        grace:     'bg-amber-100 text-amber-700',
        readonly:  'bg-red-100 text-red-700',
        suspended: 'bg-gray-200 text-gray-600',
    };
    return map[status] ?? 'bg-gray-100 text-gray-500';
}
</script>
