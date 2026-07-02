<template>
    <AdminLayout title="State Programs">
        <div class="space-y-6 max-w-5xl">
            <p class="text-sm text-gray-600">
                Create state-level programs and choose which levels they conduct at.
                Kalolsav and arts programs can include state, Sahodaya, and school rounds.
                Sports meets are limited to school and Sahodaya cluster rounds only.
            </p>

            <form @submit.prevent="createProgram" class="card space-y-3">
                <h3 class="font-semibold text-gray-900">New state program</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    <input v-model="form.title" class="field" placeholder="Program title" required>
                    <select v-model="form.event_type" class="field">
                        <option v-for="(label, key) in eventTypes" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-600 mb-2">Conducts at</p>
                    <p v-if="form.event_type === 'sports'" class="text-xs text-gray-500 mb-2">
                        Sports: school and Sahodaya cluster only.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <label v-for="(label, key) in selectableLevelLabels" :key="key" class="flex items-center gap-2 text-sm">
                            <input type="checkbox" :value="key" v-model="form.conduct_levels">
                            {{ label }}
                        </label>
                    </div>
                </div>
                <button class="btn-primary"
                        :disabled="form.processing || !form.conduct_levels.length">
                    Create draft
                </button>
            </form>

            <div class="card card--flush">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="p-3">Title</th>
                            <th class="p-3">Type</th>
                            <th class="p-3">Levels</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Propagations</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="program in programs" :key="program.id" class="border-t">
                            <td class="p-3 font-medium">{{ program.title }}</td>
                            <td class="p-3">{{ eventTypes[program.event_type] ?? program.event_type }}</td>
                            <td class="p-3">
                                <span v-for="lvl in program.conduct_levels" :key="lvl"
                                      class="inline-block mr-1 px-2 py-0.5 rounded bg-indigo-50 text-indigo-800 text-xs">
                                    {{ levelLabels[lvl] ?? lvl }}
                                </span>
                            </td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded bg-gray-100 text-xs">{{ program.status }}</span></td>
                            <td class="p-3">{{ program.propagations_count }}</td>
                            <td class="p-3 text-right">
                                <Link :href="`/admin/state-programs/${program.id}`" class="text-indigo-600 font-medium">Manage →</Link>
                            </td>
                        </tr>
                        <tr v-if="!programs.length">
                            <td colspan="6" class="p-6 text-center text-gray-400">No state programs yet</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    programs: Array,
    eventTypes: Object,
    levelLabels: Object,
});

const form = useForm({
    title: '',
    event_type: 'kalolsavam',
    conduct_levels: ['state', 'sahodaya'],
});

const selectableLevelLabels = computed(() => {
    const keys = form.event_type === 'sports' ? ['school', 'sahodaya'] : Object.keys(props.levelLabels ?? {});
    return Object.fromEntries(keys.map((k) => [k, props.levelLabels[k]]));
});

watch(() => form.event_type, (type) => {
    if (type === 'sports') {
        form.conduct_levels = form.conduct_levels.filter((l) => l !== 'state');
        if (!form.conduct_levels.length) {
            form.conduct_levels = ['school', 'sahodaya'];
        }
    }
});

function createProgram() {
    form.post('/admin/state-programs', { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

