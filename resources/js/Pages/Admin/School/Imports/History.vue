<template>
    <SchoolAdminLayout title="Import history" :school="school" :show-header-title="false">
        <PageHeader title="Import history" eyebrow="Students &amp; Teachers"
            description="Every bulk upload is kept here — preview or download the original file to check or correct it and re-upload." />

        <div v-if="!imports.length" class="card text-sm text-gray-500">
            No bulk imports yet. Uploads from Students &rarr; Bulk upload and Teachers &rarr; Import CSV/Excel will show up here.
        </div>

        <div v-else class="space-y-3">
            <div v-for="item in imports" :key="item.id" class="card !p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ item.type }}</span>
                            <span :class="statusBadgeClass(item.status)" class="text-xs font-semibold px-2 py-0.5 rounded-full">
                                {{ statusLabel(item.status) }}
                            </span>
                        </div>
                        <p class="font-semibold text-gray-900 mt-1">{{ item.original_name }}</p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ formatDate(item.created_at) }}
                            <span v-if="item.uploaded_by"> · by {{ item.uploaded_by }}</span>
                        </p>
                        <p class="text-sm mt-2">
                            <span v-if="item.status === 'success'">{{ item.imported_count }} imported</span>
                            <span v-else-if="item.status === 'failed'">{{ item.error_count }} error(s), nothing imported</span>
                            <span v-else class="text-gray-400">Processing…</span>
                        </p>
                        <div v-if="item.errors && item.errors.length && previewId !== item.id" class="mt-2 max-h-28 overflow-y-auto text-xs text-red-600 space-y-0.5">
                            <p v-for="(err, i) in item.errors.slice(0, 10)" :key="i">Row {{ err.row }}: {{ err.message }}</p>
                            <p v-if="item.errors.length > 10" class="text-gray-400">…and {{ item.errors.length - 10 }} more</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 shrink-0">
                        <button v-if="item.can_preview" type="button"
                                class="text-sm font-semibold text-[#0f3d7a] hover:underline whitespace-nowrap"
                                :disabled="previewLoading === item.id"
                                @click="togglePreview(item)">
                            {{ previewLoading === item.id ? 'Loading…' : (previewId === item.id ? 'Hide preview' : 'Preview file') }}
                        </button>
                        <a :href="`/school-admin/${school.id}/imports/${item.id}/download`"
                           class="text-sm font-semibold text-[#0f3d7a] hover:underline whitespace-nowrap">
                            ↓ Download
                        </a>
                    </div>
                </div>

                <div v-if="previewId === item.id" class="mt-4 pt-4 border-t border-slate-100 space-y-3">
                    <p v-if="previewError" class="text-sm text-red-600">{{ previewError }}</p>

                    <template v-else-if="previewData">
                        <p class="text-xs font-semibold text-slate-700">
                            {{ previewData.original_name }}
                            · {{ previewData.total_rows }} row(s)
                            <template v-if="previewData.type === 'students'">
                                · {{ previewData.valid?.length ?? 0 }} valid shown
                                · {{ previewData.errors?.length ?? 0 }} validation error(s)
                            </template>
                            <template v-else>
                                · showing first {{ previewData.rows?.length ?? 0 }}
                            </template>
                        </p>

                        <div v-if="combinedErrors(previewData).length" class="max-h-32 overflow-y-auto rounded-lg border border-red-100 bg-red-50 p-3 text-xs text-red-700 space-y-0.5">
                            <p v-for="(err, i) in combinedErrors(previewData).slice(0, 25)" :key="i">
                                Row {{ err.row }}: {{ err.message }}
                            </p>
                            <p v-if="combinedErrors(previewData).length > 25" class="text-red-500">
                                …and {{ combinedErrors(previewData).length - 25 }} more
                            </p>
                        </div>

                        <div v-if="previewData.type === 'students' && previewData.valid?.length" class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="text-left px-3 py-2">Row</th>
                                        <th class="text-left px-3 py-2">Name</th>
                                        <th class="text-left px-3 py-2">Class</th>
                                        <th class="text-left px-3 py-2">Gender</th>
                                        <th class="text-left px-3 py-2">DOB</th>
                                        <th class="text-left px-3 py-2">Email</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="row in previewData.valid" :key="row.row">
                                        <td class="px-3 py-2 text-slate-500">{{ row.row }}</td>
                                        <td class="px-3 py-2">{{ row.name }}</td>
                                        <td class="px-3 py-2">{{ row.class }}</td>
                                        <td class="px-3 py-2">{{ row.gender || '—' }}</td>
                                        <td class="px-3 py-2">{{ row.dob || '—' }}</td>
                                        <td class="px-3 py-2">{{ row.email || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else-if="previewData.type === 'teachers' && previewData.rows?.length" class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="text-left px-3 py-2">Row</th>
                                        <th v-for="col in previewData.columns" :key="col" class="text-left px-3 py-2">{{ col }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="row in previewData.rows" :key="row.row">
                                        <td class="px-3 py-2 text-slate-500">{{ row.row }}</td>
                                        <td v-for="col in previewData.columns" :key="col" class="px-3 py-2">
                                            {{ row.values[col] || '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p v-else class="text-sm text-slate-500">No data rows found in this file.</p>
                    </template>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    school: Object,
    imports: Array,
});

const previewId = ref(null);
const previewData = ref(null);
const previewLoading = ref(null);
const previewError = ref('');

function statusLabel(status) {
    return { success: 'Success', failed: 'Failed' }[status] ?? 'Pending';
}

function statusBadgeClass(status) {
    return {
        success: 'bg-emerald-100 text-emerald-700',
        failed: 'bg-red-100 text-red-700',
    }[status] ?? 'bg-slate-100 text-slate-600';
}

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function combinedErrors(data) {
    const live = data.errors ?? [];
    const stored = data.stored_errors ?? [];
    if (!stored.length) return live;
    if (!live.length) return stored;

    const seen = new Set(live.map((e) => `${e.row}:${e.message}`));
    return [...live, ...stored.filter((e) => !seen.has(`${e.row}:${e.message}`))];
}

async function togglePreview(item) {
    if (previewId.value === item.id) {
        previewId.value = null;
        previewData.value = null;
        previewError.value = '';
        return;
    }

    previewId.value = item.id;
    previewData.value = null;
    previewError.value = '';
    previewLoading.value = item.id;

    try {
        const response = await fetch(`/school-admin/${props.school.id}/imports/${item.id}/preview`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(body.message || 'Could not load preview.');
        }

        previewData.value = await response.json();
    } catch (error) {
        previewError.value = error.message || 'Could not load preview.';
    } finally {
        previewLoading.value = null;
    }
}
</script>
