<template>
    <PortalLayout
        role-label="Stage manager"
        :title="event.title"
        :subtitle="sahodaya.name"
        accent="emerald"
        :nav-items="navItems"
    >
        <p v-if="assignedStages?.length" class="text-xs text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2 mb-3">
            Showing queue for:
            <strong>{{ assignedStages.map(s => s.name).join(', ') }}</strong>
        </p>

        <div class="flex gap-2 mb-3">
            <button @click="saveOrder" class="px-3 py-1.5 text-white rounded-lg text-xs font-semibold" :disabled="!orderDirty">
                Save order
            </button>
            <button @click="moveUp" class="px-3 py-1.5 border rounded-lg text-xs" :disabled="!selectedId">Move up</button>
            <button @click="moveDown" class="px-3 py-1.5 border rounded-lg text-xs" :disabled="!selectedId">Move down</button>
        </div>

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3 w-8">#</th>
                        <th class="p-3">Time</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Stage</th>
                        <th class="p-3">Participant</th>
                        <th class="p-3">Called</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(s, idx) in ordered" :key="s.id"
                        class="border-t cursor-pointer"
                        :class="selectedId === s.id ? 'bg-indigo-50' : ''"
                        @click="selectedId = s.id">
                        <td class="p-3 text-xs text-gray-400">{{ idx + 1 }}</td>
                        <td class="p-3 text-xs">{{ s.scheduled_at ? new Date(s.scheduled_at).toLocaleString() : '—' }}</td>
                        <td class="p-3">{{ s.item?.title }}</td>
                        <td class="p-3">{{ s.fest_stage?.name || s.stage || '—' }}</td>
                        <td class="p-3">
                            <template v-if="s.public">
                                <span class="font-mono text-xs">#{{ s.public.reference }}</span>
                                <span v-if="s.public.show_name && s.public.name"> {{ s.public.name }}</span>
                            </template>
                            <span v-else>—</span>
                        </td>
                        <td class="p-3">
                            <button @click.stop="toggleCalled(s)"
                                    class="text-xs font-semibold px-2 py-0.5 rounded"
                                    :class="s.called_at ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'">
                                {{ s.called_at ? 'Called' : 'Call' }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!ordered.length">
                        <td colspan="6" class="p-6 text-center text-gray-400">No schedule entries yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { festOpsEventNav } from '@/support/festOpsPortalNav.js';

const props = defineProps({ sahodaya: Object, event: Object, schedules: Array, duties: Array, assignedStages: Array });

const base = computed(() => `/portal/fest-ops/${props.sahodaya.id}/events/${props.event.id}`);
const ordered = ref([...(props.schedules || [])]);
const selectedId = ref(null);
const orderDirty = ref(false);

const navItems = computed(() => festOpsEventNav(props.sahodaya.id, props.event.id, props.duties));

function toggleCalled(schedule) {
    router.post(`${base.value}/stage/${schedule.id}/called`, {
        called: !schedule.called_at,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            schedule.called_at = schedule.called_at ? null : new Date().toISOString();
        },
    });
}

function moveUp() {
    const idx = ordered.value.findIndex(s => s.id === selectedId.value);
    if (idx <= 0) return;
    const copy = [...ordered.value];
    [copy[idx - 1], copy[idx]] = [copy[idx], copy[idx - 1]];
    ordered.value = copy;
    orderDirty.value = true;
}

function moveDown() {
    const idx = ordered.value.findIndex(s => s.id === selectedId.value);
    if (idx < 0 || idx >= ordered.value.length - 1) return;
    const copy = [...ordered.value];
    [copy[idx], copy[idx + 1]] = [copy[idx + 1], copy[idx]];
    ordered.value = copy;
    orderDirty.value = true;
}

function saveOrder() {
    router.post(`${base.value}/stage/reorder`, {
        order: ordered.value.map(s => s.id),
    }, {
        preserveScroll: true,
        onSuccess: () => { orderDirty.value = false; },
    });
}
</script>
