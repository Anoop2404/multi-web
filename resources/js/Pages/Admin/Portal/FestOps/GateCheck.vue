<template>
    <PortalLayout
        role-label="Gate Check"
        title="QR Gate Check"
        :subtitle="sahodaya.name"
        accent="amber"
        :nav-items="navItems"
    >
        <div class="card space-y-4">
            <form @submit.prevent="scan" class="space-y-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Event</label>
                    <select v-model="eventId" class="field mt-1" required>
                        <option value="">Select event</option>
                        <option v-for="e in events" :key="e.id" :value="e.id">{{ e.title }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">QR payload</label>
                    <textarea v-model="payload" class="field mt-1 font-mono text-xs" rows="3" placeholder="Scan or paste FEST|..." required />
                </div>
                <label class="flex items-center gap-2 text-xs text-gray-600">
                    <input type="checkbox" v-model="markAttendance" class="rounded">
                    Mark participant present when valid
                </label>
                <button type="submit" class="btn-primary" :disabled="!eventId">Verify scan</button>
            </form>

            <div v-if="lastScan" class="border-t pt-4 space-y-2">
                <p class="text-sm font-semibold" :class="lastScan.valid ? 'text-green-700' : 'text-red-600'">
                    {{ lastScan.valid ? 'Valid scan' : 'Invalid scan' }}
                    <span v-if="lastScan.duplicate" class="text-amber-600 font-normal"> · Duplicate scan</span>
                </p>
                <dl v-if="lastScan.payload" class="text-sm grid gap-1">
                    <template v-for="(value, key) in flatPayload(lastScan.payload)" :key="key">
                        <div class="flex gap-2">
                            <dt class="text-gray-500 capitalize min-w-24">{{ key.replace(/_/g, ' ') }}</dt>
                            <dd class="font-medium">{{ value }}</dd>
                        </div>
                    </template>
                </dl>
            </div>
        </div>
    </PortalLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    sahodaya: Object,
    events: Array,
    selectedEventId: Number,
    lastScan: Object,
});

const eventId = ref(props.selectedEventId || '');
const payload = ref('');
const markAttendance = ref(true);

const navItems = computed(() => [
    { href: `/portal/fest-ops/${props.sahodaya.id}`, label: 'Dashboard' },
    { href: `/portal/fest-ops/${props.sahodaya.id}/gate-check`, label: 'Gate Check' },
]);

function flatPayload(data) {
    if (! data || typeof data !== 'object') return {};
    const out = {};
    for (const [k, v] of Object.entries(data)) {
        if (Array.isArray(v)) {
            out[k] = v.map(m => m.name ?? JSON.stringify(m)).join(', ');
        } else if (v && typeof v === 'object') {
            Object.assign(out, flatPayload(v));
        } else {
            out[k] = v;
        }
    }
    return out;
}

function scan() {
    router.post(`/portal/fest-ops/${props.sahodaya.id}/events/${eventId.value}/gate-check`, {
        payload,
        mark_attendance: markAttendance.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { payload.value = ''; },
    });
}
</script>
