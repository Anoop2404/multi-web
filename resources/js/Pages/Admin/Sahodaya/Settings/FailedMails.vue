<template>
    <Head title="Failed Email Queue" />

    <SahodayaAdminLayout>
        <div class="space-y-6">
            <!-- Top Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <div>
                    <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Failed Email Queue
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        View, monitor, and retry emails that failed delivery due to mail service provider issues (ZeptoMail/SMTP).
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        v-if="summary.pending_count > 0"
                        type="button"
                        class="px-4 py-2 bg-indigo-600 text-white font-medium text-xs rounded-lg hover:bg-indigo-700 transition flex items-center gap-2 shadow-sm disabled:opacity-50"
                        :disabled="processingBulk"
                        @click="handleBulkRetry"
                    >
                        <svg class="w-4 h-4" :class="{ 'animate-spin': processingBulk }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Retry All Pending ({{ summary.pending_count }})
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-amber-50/70 border border-amber-200 p-4 rounded-xl">
                    <p class="text-xs font-semibold text-amber-800 uppercase tracking-wide">Pending / Failed</p>
                    <p class="text-2xl font-bold text-amber-900 mt-1">{{ summary.pending_count }}</p>
                </div>

                <div class="bg-emerald-50/70 border border-emerald-200 p-4 rounded-xl">
                    <p class="text-xs font-semibold text-emerald-800 uppercase tracking-wide">Retried & Sent</p>
                    <p class="text-2xl font-bold text-emerald-900 mt-1">{{ summary.success_count }}</p>
                </div>

                <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl">
                    <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Cancelled</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1">{{ summary.cancelled_count }}</p>
                </div>

                <div class="bg-indigo-50/70 border border-indigo-200 p-4 rounded-xl">
                    <p class="text-xs font-semibold text-indigo-800 uppercase tracking-wide">Total Queue Logs</p>
                    <p class="text-2xl font-bold text-indigo-900 mt-1">{{ summary.total_count }}</p>
                </div>
            </div>

            <!-- Filters & Search -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <!-- Status Filter Tabs -->
                <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-lg text-xs font-medium">
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-md transition"
                        :class="filters.status === 'all' ? 'bg-white text-slate-900 shadow-xs font-semibold' : 'text-slate-600 hover:text-slate-900'"
                        @click="changeStatus('all')"
                    >
                        All
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-md transition flex items-center gap-1.5"
                        :class="filters.status === 'pending' ? 'bg-white text-slate-900 shadow-xs font-semibold' : 'text-slate-600 hover:text-slate-900'"
                        @click="changeStatus('pending')"
                    >
                        Pending / Failed
                        <span v-if="summary.pending_count > 0" class="px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold">
                            {{ summary.pending_count }}
                        </span>
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-md transition"
                        :class="filters.status === 'retry_success' ? 'bg-white text-slate-900 shadow-xs font-semibold' : 'text-slate-600 hover:text-slate-900'"
                        @click="changeStatus('retry_success')"
                    >
                        Sent Successfully
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-md transition"
                        :class="filters.status === 'cancelled' ? 'bg-white text-slate-900 shadow-xs font-semibold' : 'text-slate-600 hover:text-slate-900'"
                        @click="changeStatus('cancelled')"
                    >
                        Cancelled
                    </button>
                </div>

                <!-- Search Box -->
                <div class="relative min-w-[260px]">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search recipient, subject, or error..."
                        class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white"
                        @keydown.enter="applySearch"
                    />
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <!-- Main Data Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-semibold uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Recipient</th>
                                <th class="px-4 py-3">Subject & Type</th>
                                <th class="px-4 py-3">Last Error Reason</th>
                                <th class="px-4 py-3 text-center">Attempts</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Date Logged</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr v-if="logs.data.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                                    <svg class="w-12 h-12 mx-auto mb-2 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    No failed emails found in this filter view.
                                </td>
                            </tr>

                            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-50/70 transition-colors">
                                <!-- Recipient -->
                                <td class="px-4 py-3 font-medium text-slate-900">
                                    <div>{{ log.recipient_name || '—' }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono">{{ log.recipient_email }}</div>
                                </td>

                                <!-- Subject & Type -->
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900 truncate max-w-xs" :title="log.subject">
                                        {{ log.subject }}
                                    </div>
                                    <div class="inline-flex items-center gap-1 mt-0.5 text-[10px] font-semibold text-slate-500 uppercase tracking-wide">
                                        <span class="px-1.5 py-0.2 rounded bg-slate-100 border border-slate-200">
                                            {{ log.mail_type }}
                                        </span>
                                        <span v-if="log.mail_view" class="text-slate-400 font-mono text-[9px]">
                                            ({{ log.mail_view }})
                                        </span>
                                    </div>
                                </td>

                                <!-- Error Reason -->
                                <td class="px-4 py-3 max-w-xs">
                                    <div v-if="log.error_message" class="text-[11px] text-red-600 bg-red-50/70 p-2 rounded border border-red-100 font-mono line-clamp-2" :title="log.error_message">
                                        {{ log.error_message }}
                                    </div>
                                    <span v-else class="text-slate-400">—</span>
                                </td>

                                <!-- Attempts -->
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-700">
                                        {{ log.attempts }}
                                    </span>
                                </td>

                                <!-- Status Badge -->
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border"
                                        :class="statusBadgeClass(log.status)"
                                    >
                                        {{ statusLabel(log.status) }}
                                    </span>
                                </td>

                                <!-- Date Logged -->
                                <td class="px-4 py-3 text-slate-500 text-[11px] whitespace-nowrap">
                                    <div>{{ formatDate(log.created_at) }}</div>
                                    <div v-if="log.sent_at" class="text-emerald-600 font-medium text-[10px]">
                                        Sent: {{ formatDate(log.sent_at) }}
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            v-if="['pending', 'retry_failed'].includes(log.status)"
                                            type="button"
                                            class="px-2.5 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 rounded text-[11px] font-semibold transition"
                                            :disabled="activeId === log.id"
                                            @click="handleRetry(log)"
                                        >
                                            <span v-if="activeId === log.id">Retrying...</span>
                                            <span v-else>Retry</span>
                                        </button>

                                        <button
                                            v-if="['pending', 'retry_failed'].includes(log.status)"
                                            type="button"
                                            class="px-2 py-1 text-slate-500 hover:text-slate-700 text-[11px] font-medium transition"
                                            @click="handleCancel(log)"
                                        >
                                            Cancel
                                        </button>

                                        <button
                                            type="button"
                                            class="px-2 py-1 text-red-500 hover:text-red-700 text-[11px] font-medium transition"
                                            @click="handleDelete(log)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logs.links && logs.links.length > 3" class="p-4 border-t border-slate-200 flex items-center justify-between">
                    <span class="text-xs text-slate-500">
                        Showing {{ logs.from }} to {{ logs.to }} of {{ logs.total }} logs
                    </span>
                    <div class="flex gap-1">
                        <Link
                            v-for="(link, i) in logs.links"
                            :key="i"
                            :href="link.url || '#'"
                            class="px-2.5 py-1 text-xs rounded border transition"
                            :class="link.active ? 'bg-indigo-600 text-white border-indigo-600 font-bold' : link.url ? 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200' : 'bg-slate-50 text-slate-400 border-slate-200 cursor-not-allowed'"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const { confirm } = useConfirm();
const searchQuery = ref(props.filters.search || '');
const processingBulk = ref(false);
const activeId = ref(null);

function changeStatus(newStatus) {
    router.get(window.location.pathname, {
        status: newStatus,
        search: searchQuery.value || undefined,
    }, { preserveState: true });
}

function applySearch() {
    router.get(window.location.pathname, {
        status: props.filters.status,
        search: searchQuery.value || undefined,
    }, { preserveState: true });
}

function handleRetry(log) {
    activeId.value = log.id;
    router.post(`${window.location.pathname}/${log.id}/retry`, {}, {
        preserveScroll: true,
        onFinish: () => {
            activeId.value = null;
        },
    });
}

async function handleBulkRetry() {
    if (!(await confirm({ message: `Are you sure you want to retry sending all ${props.summary.pending_count} pending failed emails?`, destructive: false }))) {
        return;
    }
    processingBulk.value = true;
    router.post(`${window.location.pathname}/bulk-retry`, {}, {
        preserveScroll: true,
        onFinish: () => {
            processingBulk.value = false;
        },
    });
}

async function handleCancel(log) {
    if (!(await confirm({ message: `Cancel email delivery attempt to ${log.recipient_email}?` }))) {
        return;
    }
    router.post(`${window.location.pathname}/${log.id}/cancel`, {}, { preserveScroll: true });
}

async function handleDelete(log) {
    if (!(await confirm({ message: 'Permanently remove this failed email log entry?' }))) {
        return;
    }
    router.delete(`${window.location.pathname}/${log.id}`, {}, { preserveScroll: true });
}

function statusBadgeClass(status) {
    switch (status) {
        case 'retry_success':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'retry_failed':
            return 'bg-red-100 text-red-800 border-red-200';
        case 'cancelled':
            return 'bg-slate-100 text-slate-700 border-slate-200';
        case 'pending':
        default:
            return 'bg-amber-100 text-amber-800 border-amber-200';
    }
}

function statusLabel(status) {
    switch (status) {
        case 'retry_success':
            return 'Sent Successfully';
        case 'retry_failed':
            return 'Retry Failed';
        case 'cancelled':
            return 'Cancelled';
        case 'pending':
        default:
            return 'Pending Retry';
    }
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleString('en-IN', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>
