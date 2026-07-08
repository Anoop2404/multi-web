<template>
    <SahodayaAdminLayout title="Document types" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Compliance document types" eyebrow="Membership"
                    description="Configure required documents schools must upload for membership compliance." />

        <div class="grid lg:grid-cols-2 gap-6">
            <form class="card space-y-3" @submit.prevent="submitCreate">
                <h2 class="font-semibold text-[#0f3d7a]">Add type</h2>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Code</label>
                    <input v-model="form.code" type="text" required class="input-field w-full text-sm"
                           placeholder="fire_safety" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                    <input v-model="form.name" type="text" required class="input-field w-full text-sm" />
                </div>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_required" type="checkbox" /> Required
                    </label>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Validity (months)</label>
                        <input v-model.number="form.validity_months" type="number" min="1" class="input-field w-24 text-sm" />
                    </div>
                </div>
                <button type="submit" class="btn-primary text-sm">Create</button>
            </form>

            <div class="space-y-3">
                <div v-for="type in types" :key="type.id" class="card !p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-xs text-slate-400">{{ type.code }}</p>
                            <p class="font-semibold text-[#0f3d7a]">{{ type.name }}</p>
                            <p class="text-xs text-slate-500 mt-1">
                                <span v-if="type.is_required" class="text-amber-700 font-semibold">Required</span>
                                <span v-else>Optional</span>
                                <span v-if="type.validity_months"> · {{ type.validity_months }} months</span>
                                <span v-if="!type.is_active" class="text-red-600"> · Inactive</span>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="text-xs font-semibold text-[#0f3d7a]" @click="toggleActive(type)">
                                {{ type.is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p class="mt-6 text-sm">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/documents/review`" class="text-[#0f3d7a] font-semibold hover:underline">
                → Review uploaded documents
            </Link>
        </p>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    types: Array,
});

const form = reactive({
    code: '',
    name: '',
    is_required: true,
    validity_months: 12,
});

function submitCreate() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/documents/types`, form, {
        preserveScroll: true,
        onSuccess: () => {
            form.code = '';
            form.name = '';
        },
    });
}

function toggleActive(type) {
    router.put(`/sahodaya-admin/${props.sahodaya.id}/documents/types/${type.id}`, {
        name: type.name,
        is_required: type.is_required,
        validity_months: type.validity_months,
        sort_order: type.sort_order,
        is_active: !type.is_active,
    }, { preserveScroll: true });
}
</script>
