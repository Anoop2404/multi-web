<template>
    <SchoolAdminLayout title="Compliance documents" :school="school" :show-header-title="false">
        <PageHeader title="Compliance documents" eyebrow="Membership"
                    description="Upload required documents for Sahodaya review. Renew before expiry." />

        <div class="space-y-4">
            <div v-for="type in types" :key="type.id" class="card !p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-gray-900">{{ type.name }}</p>
                        <p class="text-xs text-gray-500 mt-1">
                            <span v-if="type.is_required" class="text-amber-700 font-semibold">Required</span>
                            <span v-else>Optional</span>
                            <span v-if="type.validity_months"> · Valid {{ type.validity_months }} months</span>
                        </p>
                        <template v-if="documents[type.id]">
                            <p class="text-sm mt-2">
                                Status:
                                <span class="font-semibold capitalize">{{ documents[type.id].status }}</span>
                                <span v-if="documents[type.id].valid_to"> · expires {{ documents[type.id].valid_to }}</span>
                            </p>
                            <p v-if="documents[type.id].rejection_reason" class="text-sm text-red-600 mt-1">
                                {{ documents[type.id].rejection_reason }}
                            </p>
                            <a :href="`/school-admin/${school.id}/documents/${documents[type.id].id}/download`"
                               class="inline-block mt-2 text-sm font-semibold text-[#0f3d7a] hover:underline">
                                Download current file
                            </a>
                        </template>
                    </div>
                    <form class="flex flex-col gap-2 min-w-[240px]" @submit.prevent="upload(type.id)">
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" required
                               class="text-sm" @change="onFile(type.id, $event)" />
                        <button type="submit" class="btn-primary text-sm" :disabled="uploading === type.id">
                            {{ documents[type.id] ? 'Replace & resubmit' : 'Upload' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    school: Object,
    types: Array,
    documents: Object,
});

const files = ref({});
const uploading = ref(null);

function onFile(typeId, event) {
    files.value[typeId] = event.target.files[0];
}

function upload(typeId) {
    const file = files.value[typeId];
    if (!file) return;

    uploading.value = typeId;
    router.post(
        `/school-admin/${props.school.id}/documents`,
        { document_type_id: typeId, file },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => { uploading.value = null; },
        },
    );
}
</script>
