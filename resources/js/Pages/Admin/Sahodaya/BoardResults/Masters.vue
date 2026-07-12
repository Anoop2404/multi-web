<template>
    <SahodayaAdminLayout title="Board result masters" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Board result masters" eyebrow="Academic Results"
                    description="Manage exam streams and Academic Performance Index weights for this Sahodaya.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/verification`" class="btn-secondary text-sm">← Verification</Link>
            </template>
        </PageHeader>

        <div class="grid lg:grid-cols-2 gap-6">
            <section class="card space-y-4">
                <h2 class="font-semibold text-[#0f3d7a]">API weights</h2>
                <p class="text-xs text-slate-500">Weights must sum to 100.</p>
                <form class="grid grid-cols-2 gap-3" @submit.prevent="saveApi">
                    <label class="text-xs">
                        Pass %
                        <input v-model.number="apiForm.weight_pass_percent" type="number" step="0.1" min="0" max="100" class="form-input mt-1" />
                    </label>
                    <label class="text-xs">
                        Distinctions
                        <input v-model.number="apiForm.weight_distinctions" type="number" step="0.1" min="0" max="100" class="form-input mt-1" />
                    </label>
                    <label class="text-xs">
                        Highest mark
                        <input v-model.number="apiForm.weight_highest_mark" type="number" step="0.1" min="0" max="100" class="form-input mt-1" />
                    </label>
                    <label class="text-xs">
                        Toppers
                        <input v-model.number="apiForm.weight_toppers" type="number" step="0.1" min="0" max="100" class="form-input mt-1" />
                    </label>
                    <div class="col-span-2 flex items-center justify-between">
                        <p class="text-xs" :class="apiSum === 100 ? 'text-emerald-700' : 'text-red-600'">Sum: {{ apiSum }}</p>
                        <button type="submit" class="btn-primary text-sm" :disabled="apiSum !== 100">Save weights</button>
                    </div>
                </form>
            </section>

            <section class="card space-y-4">
                <h2 class="font-semibold text-[#0f3d7a]">Add Sahodaya stream</h2>
                <form class="space-y-3" @submit.prevent="createStream">
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-xs">Code
                            <input v-model="streamForm.code" class="form-input mt-1" required maxlength="40" />
                        </label>
                        <label class="text-xs">Label
                            <input v-model="streamForm.label" class="form-input mt-1" required maxlength="120" />
                        </label>
                    </div>
                    <button type="submit" class="btn-primary text-sm">Create stream</button>
                </form>
            </section>
        </div>

        <section class="card mt-6">
            <h2 class="font-semibold text-[#0f3d7a] mb-3">Exam streams</h2>
            <div class="space-y-3">
                <div v-for="s in streams" :key="s.id" class="border border-slate-200 rounded-lg p-3 flex flex-wrap gap-3 items-end justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-sm">
                            {{ s.label }}
                            <span class="font-mono text-xs text-slate-500 ml-2">{{ s.code }}</span>
                            <span v-if="!s.sahodaya_id" class="ml-2 text-[10px] uppercase tracking-wide bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">Global</span>
                            <span v-else class="ml-2 text-[10px] uppercase tracking-wide bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded">Override</span>
                            <span v-if="!s.is_active" class="ml-2 text-[10px] uppercase text-red-600">Inactive</span>
                        </p>
                        <div class="grid grid-cols-2 gap-2 mt-2">
                            <input v-model="editForms[s.id].label" class="form-input text-sm" />
                            <input v-model.number="editForms[s.id].sort_order" type="number" class="form-input text-sm" />
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn-secondary text-xs" @click="saveStream(s)">Save</button>
                        <button v-if="s.sahodaya_id" type="button" class="text-xs text-red-700 px-2" @click="removeStream(s)">Remove</button>
                    </div>
                </div>
                <p v-if="!streams?.length" class="text-slate-400 text-sm py-6 text-center">No streams seeded yet.</p>
            </div>
        </section>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    streams: { type: Array, default: () => [] },
    apiConfig: { type: Object, default: () => ({}) },
    topperConfigs: { type: Array, default: () => [] },
});

const apiForm = reactive({
    weight_pass_percent: Number(props.apiConfig.weight_pass_percent ?? 40),
    weight_distinctions: Number(props.apiConfig.weight_distinctions ?? 20),
    weight_highest_mark: Number(props.apiConfig.weight_highest_mark ?? 20),
    weight_toppers: Number(props.apiConfig.weight_toppers ?? 20),
    is_active: props.apiConfig.is_active ?? true,
});

const apiSum = computed(() =>
    Math.round((
        Number(apiForm.weight_pass_percent || 0)
        + Number(apiForm.weight_distinctions || 0)
        + Number(apiForm.weight_highest_mark || 0)
        + Number(apiForm.weight_toppers || 0)
    ) * 10) / 10
);

const streamForm = reactive({ code: '', label: '' });

const editForms = reactive({});
for (const s of props.streams) {
    editForms[s.id] = {
        label: s.label,
        sort_order: s.sort_order ?? 0,
        is_active: s.is_active,
    };
}

function saveApi() {
    router.put(`/sahodaya-admin/${props.sahodaya.id}/board-results/masters/api-config`, { ...apiForm }, { preserveScroll: true });
}

function createStream() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/board-results/masters/streams`, { ...streamForm }, {
        preserveScroll: true,
        onSuccess: () => { streamForm.code = ''; streamForm.label = ''; },
    });
}

function saveStream(s) {
    router.put(`/sahodaya-admin/${props.sahodaya.id}/board-results/masters/streams/${s.id}`, {
        ...editForms[s.id],
    }, { preserveScroll: true });
}

function removeStream(s) {
    if (!window.confirm(`Remove or deactivate stream ${s.code}?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/board-results/masters/streams/${s.id}`, { preserveScroll: true });
}
</script>
