<template>
    <AdminLayout title="Online Exam Concurrency & Rate Limits">
        <PageHeader
            title="Online Exam Concurrency & Rate Limits"
            eyebrow="Module Settings"
            description="Control how many students can simultaneously write online exams per school or per Sahodaya cluster. Prevent server overload and local school network bottlenecks."
        />

        <!-- Live KPI Telemetry Cards -->
        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="card card--muted !py-4 text-center">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold"
                      :class="globalSettings.enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'">
                    {{ globalSettings.enabled ? '● Active' : '○ Disabled' }}
                </span>
                <p class="text-xs text-slate-500 mt-2">Rate Limiting System</p>
            </div>

            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-black text-indigo-700 tabular-nums">
                    {{ liveStats.total_students_writing_now }}
                </p>
                <p class="text-xs text-slate-500 mt-1">Students Writing Now</p>
            </div>

            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold tabular-nums text-slate-800">
                    {{ globalSettings.max_concurrent_per_school ? `${globalSettings.max_concurrent_per_school}` : 'Unlimited' }}
                </p>
                <p class="text-xs text-slate-500 mt-1">Default School Cap</p>
            </div>

            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold tabular-nums text-slate-800">
                    {{ globalSettings.max_concurrent_per_sahodaya ? `${globalSettings.max_concurrent_per_sahodaya}` : 'Unlimited' }}
                </p>
                <p class="text-xs text-slate-500 mt-1">Default Sahodaya Cap</p>
            </div>
        </div>

        <!-- Global Platform Settings Card -->
        <div class="card mb-8">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Platform Global Concurrency Limits</h2>
                    <p class="text-xs text-slate-500 mt-0.5">These defaults apply to all online MCQ exams unless a specific cluster or school override is set.</p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-md bg-slate-100 text-slate-700">
                    Superadmin Only
                </span>
            </div>

            <form @submit.prevent="submitGlobal" class="space-y-5">
                <div class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 bg-slate-50/70">
                    <input
                        id="enable-limits"
                        type="checkbox"
                        v-model="globalForm.enabled"
                        class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label for="enable-limits" class="cursor-pointer select-none">
                        <span class="font-bold text-sm text-slate-900 block">Enforce Exam Concurrency Rate Limiting</span>
                        <span class="text-xs text-slate-500 block mt-0.5">
                            When enabled, students starting an online exam will be held if their school or cluster currently exceeds the configured active writing limits.
                        </span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                            Max Concurrent Students Per School
                        </label>
                        <input
                            type="number"
                            v-model="globalForm.max_concurrent_per_school"
                            placeholder="e.g. 100 (leave blank for unlimited)"
                            min="1"
                            class="input w-full"
                        />
                        <p class="text-[11px] text-slate-400 mt-1">
                            Maximum simultaneous candidates writing an exam from a single school.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                            Max Concurrent Students Per Sahodaya Cluster
                        </label>
                        <input
                            type="number"
                            v-model="globalForm.max_concurrent_per_sahodaya"
                            placeholder="e.g. 1000 (leave blank for unlimited)"
                            min="1"
                            class="input w-full"
                        />
                        <p class="text-[11px] text-slate-400 mt-1">
                            Maximum simultaneous candidates writing an exam across all schools in a Sahodaya.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                            School Limit Notice Message
                        </label>
                        <input
                            type="text"
                            v-model="globalForm.queue_message_school"
                            class="input w-full text-xs font-mono"
                        />
                        <p class="text-[11px] text-slate-400 mt-1">
                            Shown to students when school limit is reached. Available tokens: <code class="bg-slate-100 px-1 py-0.5 rounded">:current</code>, <code class="bg-slate-100 px-1 py-0.5 rounded">:max</code>
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                            Sahodaya Limit Notice Message
                        </label>
                        <input
                            type="text"
                            v-model="globalForm.queue_message_sahodaya"
                            class="input w-full text-xs font-mono"
                        />
                        <p class="text-[11px] text-slate-400 mt-1">
                            Shown to students when Sahodaya limit is reached. Available tokens: <code class="bg-slate-100 px-1 py-0.5 rounded">:current</code>, <code class="bg-slate-100 px-1 py-0.5 rounded">:max</code>
                        </p>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        :disabled="globalForm.processing"
                        class="btn-primary text-sm px-5 py-2.5"
                    >
                        {{ globalForm.processing ? 'Saving...' : 'Save Global Limits' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Sahodaya Cluster Limits & Custom Overrides -->
        <div class="card mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Sahodaya Cluster Limits & Overrides</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Customize limits for high-capacity clusters or view current active test takers per cluster.</p>
                </div>
                <div class="w-full sm:w-64">
                    <input
                        type="search"
                        v-model="sahodayaFilter"
                        placeholder="Search Sahodayas..."
                        class="input input--sm w-full text-xs"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider bg-slate-50">
                            <th class="p-3">Sahodaya Cluster</th>
                            <th class="p-3 text-center">Active Writing</th>
                            <th class="p-3">Effective School Cap</th>
                            <th class="p-3">Effective Cluster Cap</th>
                            <th class="p-3">Setting Source</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="sahodaya in filteredSahodayas" :key="sahodaya.id" class="hover:bg-slate-50/60 transition">
                            <td class="p-3">
                                <span class="font-bold text-slate-900 text-sm block">{{ sahodaya.name }}</span>
                                <span v-if="sahodaya.code" class="text-[11px] text-slate-400 font-mono">{{ sahodaya.code }}</span>
                            </td>
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center gap-1 font-bold px-2 py-0.5 rounded-full"
                                      :class="sahodaya.active_writing_count > 0 ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-500'">
                                    {{ sahodaya.active_writing_count }}
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="font-semibold text-slate-800">
                                    {{ sahodaya.effective_school_limit ? `${sahodaya.effective_school_limit} / school` : 'Unlimited' }}
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="font-semibold text-slate-800">
                                    {{ sahodaya.effective_sahodaya_limit ? `${sahodaya.effective_sahodaya_limit} total` : 'Unlimited' }}
                                </span>
                            </td>
                            <td class="p-3">
                                <span v-if="sahodaya.has_override" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">
                                    Custom Override
                                </span>
                                <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600">
                                    Global Inherited
                                </span>
                            </td>
                            <td class="p-3 text-right space-x-2">
                                <button
                                    type="button"
                                    @click="openOverrideModal(sahodaya)"
                                    class="btn-secondary !text-xs !py-1 !px-2.5"
                                >
                                    {{ sahodaya.has_override ? 'Edit Override' : 'Customize' }}
                                </button>
                                <button
                                    v-if="sahodaya.has_override"
                                    type="button"
                                    @click="resetOverride(sahodaya)"
                                    class="text-xs text-red-600 hover:text-red-800 font-semibold underline px-1"
                                >
                                    Reset
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!filteredSahodayas.length">
                            <td colspan="6" class="p-6 text-center text-slate-400 text-xs">
                                No Sahodayas matched your search filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- School Overrides Card -->
        <div v-if="schoolOverrides.length" class="card">
            <div class="pb-3 border-b border-slate-100 mb-4">
                <h2 class="text-base font-bold text-slate-900">Schools with Custom Overrides</h2>
                <p class="text-xs text-slate-500 mt-0.5">Individual schools where a custom limit has been configured.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider bg-slate-50">
                            <th class="p-3">School Name</th>
                            <th class="p-3">Parent Sahodaya</th>
                            <th class="p-3">Custom School Cap</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="school in schoolOverrides" :key="school.id">
                            <td class="p-3 font-bold text-slate-800">{{ school.name }}</td>
                            <td class="p-3 text-slate-600">{{ school.parent_name }}</td>
                            <td class="p-3 font-semibold text-slate-800">
                                {{ school.override?.max_concurrent_per_school ? `${school.override.max_concurrent_per_school} students` : 'Inherited' }}
                            </td>
                            <td class="p-3 text-right space-x-2">
                                <button
                                    type="button"
                                    @click="openOverrideModal(school, true)"
                                    class="btn-secondary !text-xs !py-1 !px-2.5"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    @click="resetOverride(school)"
                                    class="text-xs text-red-600 hover:text-red-800 font-semibold underline px-1"
                                >
                                    Remove Override
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal: Edit Tenant Override -->
        <div v-if="editingTenant" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Customize Limits: {{ editingTenant.name }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Set specific limits for this {{ isEditingSchool ? 'school' : 'cluster' }}. Empty values inherit the global default.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="editingTenant = null"
                        class="text-slate-400 hover:text-slate-600 text-lg leading-none"
                    >
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitOverride" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Max Concurrent Per School
                        </label>
                        <input
                            type="number"
                            v-model="overrideForm.max_concurrent_per_school"
                            placeholder="Leave empty to inherit default"
                            min="1"
                            class="input w-full text-sm"
                        />
                    </div>

                    <div v-if="!isEditingSchool">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Max Concurrent Per Sahodaya Cluster
                        </label>
                        <input
                            type="number"
                            v-model="overrideForm.max_concurrent_per_sahodaya"
                            placeholder="Leave empty to inherit default"
                            min="1"
                            class="input w-full text-sm"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="editingTenant = null"
                            class="btn-secondary text-xs"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="overrideForm.processing"
                            class="btn-primary text-xs"
                        >
                            {{ overrideForm.processing ? 'Saving...' : 'Save Custom Limit' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    globalSettings: { type: Object, required: true },
    liveStats: { type: Object, required: true },
    sahodayas: { type: Array, default: () => [] },
    schoolOverrides: { type: Array, default: () => [] },
});

const sahodayaFilter = ref('');

const globalForm = useForm({
    enabled: !!props.globalSettings.enabled,
    max_concurrent_per_school: props.globalSettings.max_concurrent_per_school ?? '',
    max_concurrent_per_sahodaya: props.globalSettings.max_concurrent_per_sahodaya ?? '',
    queue_message_school: props.globalSettings.queue_message_school ?? '',
    queue_message_sahodaya: props.globalSettings.queue_message_sahodaya ?? '',
});

function submitGlobal() {
    globalForm.put('/admin/mcq/concurrency-settings', {
        preserveScroll: true,
    });
}

const filteredSahodayas = computed(() => {
    if (!sahodayaFilter.value.trim()) {
        return props.sahodayas;
    }
    const q = sahodayaFilter.value.toLowerCase().trim();
    return props.sahodayas.filter(s =>
        s.name.toLowerCase().includes(q) || (s.code && s.code.toLowerCase().includes(q))
    );
});

const editingTenant = ref(null);
const isEditingSchool = ref(false);

const overrideForm = useForm({
    max_concurrent_per_school: '',
    max_concurrent_per_sahodaya: '',
});

function openOverrideModal(tenant, isSchool = false) {
    editingTenant.value = tenant;
    isEditingSchool.value = isSchool;
    overrideForm.max_concurrent_per_school = tenant.override?.max_concurrent_per_school ?? '';
    overrideForm.max_concurrent_per_sahodaya = tenant.override?.max_concurrent_per_sahodaya ?? '';
}

function submitOverride() {
    if (!editingTenant.value) return;

    overrideForm.put(`/admin/mcq/concurrency-settings/override/${editingTenant.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingTenant.value = null;
        },
    });
}

function resetOverride(tenant) {
    if (!confirm(`Reset concurrency limits for ${tenant.name} back to global platform defaults?`)) {
        return;
    }

    router.delete(`/admin/mcq/concurrency-settings/override/${tenant.id}`, {
        preserveScroll: true,
    });
}
</script>
