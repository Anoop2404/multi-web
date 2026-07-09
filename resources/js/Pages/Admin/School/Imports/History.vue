<template>
    <SchoolAdminLayout title="Import history" :school="school" :show-header-title="false">
        <PageHeader title="Import history" eyebrow="Students &amp; Teachers"
            description="Every bulk upload is kept here for reference — download the original file to check or correct it and re-upload." />

        <div v-if="!imports.length" class="card text-sm text-gray-500">
            No bulk imports yet. Uploads from Students &rarr; Bulk upload and Teachers &rarr; Import CSV/Excel will show up here.
        </div>

        <div v-else class="space-y-3">
            <div v-for="item in imports" :key="item.id" class="card !p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
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
                        <div v-if="item.errors && item.errors.length" class="mt-2 max-h-28 overflow-y-auto text-xs text-red-600 space-y-0.5">
                            <p v-for="(err, i) in item.errors.slice(0, 10)" :key="i">Row {{ err.row }}: {{ err.message }}</p>
                            <p v-if="item.errors.length > 10" class="text-gray-400">…and {{ item.errors.length - 10 }} more</p>
                        </div>
                    </div>
                    <a :href="`/school-admin/${school.id}/imports/${item.id}/download`"
                       class="text-sm font-semibold text-[#0f3d7a] hover:underline whitespace-nowrap">
                        ↓ Download file
                    </a>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    school: Object,
    imports: Array,
});

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
</script>
