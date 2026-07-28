<template>
    <div class="card card--flush overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Scope</th>
                    <th class="px-4 py-3">Origin</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Description</th>
                    <th class="px-4 py-3">Actor</th>
                    <th class="px-4 py-3">IP</th>
                    <th class="px-4 py-3 text-right">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <template v-for="log in rows" :key="log.id">
                    <tr class="cursor-pointer hover:bg-slate-50/70" @click="toggle(log.id)">
                        <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-500">
                            {{ formatDateTime(log.created_at) }}
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-900">{{ log.scope_label || '—' }}</p>
                            <p class="mt-0.5 font-mono text-[11px] text-slate-400">{{ log.scope_key || '—' }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            <p class="font-medium">{{ log.origin_label || '—' }}</p>
                            <p v-if="log.origin_sub_label" class="mt-0.5 text-xs text-slate-400">{{ log.origin_sub_label }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-700">
                                {{ log.action }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900">{{ log.description }}</p>
                            <p class="mt-0.5 text-xs text-slate-400">ID {{ log.id }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900">{{ log.actor?.name || '—' }}</p>
                            <p class="mt-0.5 text-xs text-slate-400">{{ log.actor?.email || log.actor_email || '—' }}</p>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ log.ip_address || '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button"
                                    class="text-xs font-semibold text-[#0f3d7a] hover:underline"
                                    @click.stop="toggle(log.id)">
                                {{ expandedId === log.id ? 'Hide' : 'View' }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="expandedId === log.id">
                        <td colspan="8" class="bg-slate-50 px-4 py-4">
                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="space-y-3">
                                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Subject</p>
                                        <p class="mt-1 text-sm font-medium text-slate-900">{{ log.subject_type || '—' }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">ID: {{ log.subject_id || '—' }}</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Actor</p>
                                        <p class="mt-1 text-sm font-medium text-slate-900">{{ log.actor?.name || '—' }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">{{ log.actor?.email || log.actor_email || '—' }}</p>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Changes</p>
                                        <pre class="mt-2 max-h-56 overflow-auto whitespace-pre-wrap break-words text-xs text-slate-700">{{ prettyJson(log.changes) }}</pre>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Properties</p>
                                        <pre class="mt-2 max-h-56 overflow-auto whitespace-pre-wrap break-words text-xs text-slate-700">{{ prettyJson(log.properties) }}</pre>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </template>
                <tr v-if="!rows.length">
                    <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-400">
                        No log entries for these filters.
                    </td>
                </tr>
            </tbody>
        </table>

        <PaginationLinks :links="logs.links" :meta="paginationMeta" />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import PaginationLinks from '@/Components/ui/PaginationLinks.vue';

const props = defineProps({
    logs: { type: Object, required: true },
});

const expandedId = ref(null);
const rows = computed(() => props.logs?.data ?? []);
const paginationMeta = computed(() => ({
    from: props.logs?.from ?? 0,
    to: props.logs?.to ?? 0,
    total: props.logs?.total ?? 0,
}));

function toggle(id) {
    expandedId.value = expandedId.value === id ? null : id;
}

function prettyJson(value) {
    if (value == null) {
        return '—';
    }

    if (typeof value === 'string') {
        return value;
    }

    try {
        return JSON.stringify(value, null, 2);
    } catch (error) {
        return String(value);
    }
}

function formatDateTime(value) {
    if (!value) return '—';

    const d = new Date(value);
    if (Number.isNaN(d.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-IN', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
        timeZone: 'Asia/Kolkata',
    }).format(d);
}
</script>
