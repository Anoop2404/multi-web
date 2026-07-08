<template>
    <SahodayaAdminLayout title="Document review" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="School document review" eyebrow="Membership"
                    description="Review compliance documents uploaded by member schools." />

        <div class="flex gap-2 mb-4">
            <Link v-for="s in statusOptions" :key="s.value"
                  :href="`/sahodaya-admin/${sahodaya.id}/documents/review?status=${s.value}`"
                  class="px-3 py-1.5 rounded-lg text-sm font-semibold border"
                  :class="filters.status === s.value ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200 text-slate-600'">
                {{ s.label }}
            </Link>
        </div>

        <div class="space-y-3">
            <div v-for="doc in documents.data" :key="doc.id" class="card !p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-[#0f3d7a]">{{ schoolNames[doc.school_id] || doc.school_id }}</p>
                        <p class="text-sm text-slate-700 mt-1">{{ doc.document_type?.name }}</p>
                        <p class="text-xs text-slate-500 mt-1 capitalize">
                            {{ doc.status }}
                            <span v-if="doc.valid_to"> · valid to {{ doc.valid_to }}</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 items-center">
                        <a :href="`/sahodaya-admin/${sahodaya.id}/documents/${doc.id}/download`"
                           class="px-3 py-1.5 border border-slate-300 text-xs font-semibold rounded-lg hover:bg-slate-50">
                            Download
                        </a>
                        <template v-if="doc.status === 'pending'">
                            <button type="button" class="btn-primary text-xs" @click="approve(doc)">Approve</button>
                            <button type="button" class="px-3 py-1.5 border border-red-300 text-red-700 text-xs font-semibold rounded-lg"
                                    @click="reject(doc)">
                                Reject
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            <p v-if="!documents.data.length" class="text-center text-slate-400 py-10">No documents in this queue.</p>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    documents: Object,
    schoolNames: Object,
    filters: Object,
});

const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'all', label: 'All' },
];

function approve(doc) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/documents/${doc.id}/approve`, {}, { preserveScroll: true });
}

function reject(doc) {
    const reason = window.prompt('Rejection reason (required):');
    if (!reason) return;
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/documents/${doc.id}/reject`,
        { rejection_reason: reason },
        { preserveScroll: true },
    );
}
</script>
