<template>
    <AdminLayout :title="title">
        <div class="mx-auto max-w-7xl space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <Link :href="backUrl" class="text-xs link-brand">← All reports</Link>
                    <h1 class="mt-1 text-xl font-bold text-slate-900">{{ title }}</h1>
                    <p class="text-xs text-slate-500">
                        {{ event.name }} · {{ total }} row{{ total === 1 ? '' : 's' }}
                        <span v-if="truncated"> · showing the first {{ rows.length }}, the download has them all</span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <a v-for="f in report.formats" :key="f" :href="downloadHref(f)"
                       class="rounded-xl bg-[color:var(--brand-navy)] px-3 py-2 text-xs font-bold uppercase text-white">
                        {{ f }}
                    </a>
                </div>
            </div>

            <!-- Sahodaya first, School second — the module's fixed hierarchy. Only the filters this
                 particular report declares are shown. -->
            <div v-if="report.filters.length" class="rounded-2xl border border-slate-200/80 bg-white p-3">
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-if="report.filters.includes('sahodaya_id')">
                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Sahodaya</label>
                        <select v-model="local.sahodaya_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" @change="apply">
                            <option :value="null">All Sahodayas</option>
                            <option v-for="s in sahodayas" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div v-if="report.filters.includes('origin')">
                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Source</label>
                        <select v-model="local.origin" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" @change="apply">
                            <option :value="null">Managed and outside</option>
                            <option value="managed">On the platform</option>
                            <option value="external">Arrived from outside</option>
                        </select>
                    </div>
                    <div v-if="report.filters.includes('search')">
                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Search</label>
                        <input v-model="local.search" type="search" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                               placeholder="Participant name" @keyup.enter="apply">
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-200/80 bg-white">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th v-for="h in headers" :key="h" class="px-3 py-2">{{ h }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in rows" :key="i" class="border-b border-slate-100">
                            <td v-for="(cell, j) in row" :key="j" class="px-3 py-2 text-slate-700">{{ cell }}</td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="headers.length" class="px-3 py-10 text-center text-sm text-slate-400">
                                No rows matched these filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    event: Object, permissions: Array, report: Object, title: String,
    headers: Array, rows: Array, total: Number, truncated: Boolean,
    filters: Object, sahodayas: Array, downloadUrl: String, backUrl: String,
});

const local = reactive({
    sahodaya_id: props.filters?.sahodaya_id ?? null,
    origin: props.filters?.origin ?? null,
    search: props.filters?.search ?? '',
});

function query() {
    return Object.fromEntries(Object.entries(local).filter(([, v]) => v !== null && v !== ''));
}

function apply() {
    router.get(window.location.pathname, query(), { preserveState: true, replace: true });
}

function downloadHref(format) {
    const params = new URLSearchParams({ ...query(), format });
    return `${props.downloadUrl}?${params.toString()}`;
}
</script>
