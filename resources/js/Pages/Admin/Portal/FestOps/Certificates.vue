<template>
    <PortalLayout
        role-label="Certificates"
        :title="event.title"
        :subtitle="sahodaya.name"
        accent="emerald"
        :nav-items="navItems"
    >
        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Participant</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Grade</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in certificates" :key="c.id" class="border-t">
                        <td class="p-3">{{ c.student?.name ?? c.participant?.teacher?.name ?? c.teacher?.name ?? '—' }}</td>
                        <td class="p-3 text-xs">{{ c.item?.title ?? '—' }}</td>
                        <td class="p-3">{{ c.mark?.grade ?? c.mark?.position ?? '—' }}</td>
                        <td class="p-3 text-right">
                            <a v-if="c.uuid"
                               :href="`/certificates/print/${c.uuid}`"
                               target="_blank"
                               rel="noopener"
                               class="text-indigo-600 text-xs font-semibold">Print ↗</a>
                        </td>
                    </tr>
                    <tr v-if="!certificates.length">
                        <td colspan="4" class="p-8 text-center text-gray-400">No certificates generated yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import { computed } from 'vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({ sahodaya: Object, event: Object, certificates: Array, duties: Array });

const base = computed(() => `/portal/fest-ops/${props.sahodaya.id}/events/${props.event.id}`);

const navItems = computed(() => [
    { href: `/portal/fest-ops/${props.sahodaya.id}`, label: 'Dashboard' },
    { href: base.value, label: 'Event' },
    { href: `${base.value}/certificates`, label: 'Certificates' },
]);
</script>
