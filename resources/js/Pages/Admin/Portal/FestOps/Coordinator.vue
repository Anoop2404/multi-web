<template>
    <PortalLayout
        role-label="Event coordinator"
        :title="event.title"
        :subtitle="sahodaya.name"
        accent="emerald"
        :nav-items="navItems"
    >
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
            <div class="card">
                <p class="text-xs text-gray-500 uppercase">Pending registrations</p>
                <p class="text-2xl font-bold text-amber-600">{{ stats.pending_registrations }}</p>
            </div>
            <div class="card">
                <p class="text-xs text-gray-500 uppercase">Approved</p>
                <p class="text-2xl font-bold text-green-600">{{ stats.approved_registrations }}</p>
            </div>
            <div class="card">
                <p class="text-xs text-gray-500 uppercase">Open appeals</p>
                <p class="text-2xl font-bold text-red-600">{{ stats.open_appeals }}</p>
            </div>
            <div class="card">
                <p class="text-xs text-gray-500 uppercase">Schedule entries</p>
                <p class="text-2xl font-bold">{{ stats.schedule_entries }}</p>
            </div>
            <div class="card">
                <p class="text-xs text-gray-500 uppercase">Staff assignments</p>
                <p class="text-2xl font-bold">{{ stats.staff_assignments }}</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <a v-for="link in quickLinks" :key="link.href"
               :href="link.href"
               class="bg-white border rounded-xl p-4 hover:border-indigo-300 block text-sm font-semibold">
                {{ link.label }} →
            </a>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { festOpsEventNav } from '@/support/festOpsPortalNav.js';
import { computed } from 'vue';

const props = defineProps({ sahodaya: Object, event: Object, stats: Object, duties: Array });

const base = computed(() => `/portal/fest-ops/${props.sahodaya.id}/events/${props.event.id}`);

const quickLinks = computed(() => {
    const links = [];
    const d = props.duties || [];
    if (d.includes('registration')) links.push({ href: `${base.value}/registrations`, label: 'Registration desk' });
    if (d.includes('stage')) links.push({ href: `${base.value}/stage`, label: 'Stage manager' });
    if (d.includes('appeals')) links.push({ href: `${base.value}/appeals`, label: 'Appeals' });
    if (d.includes('certificates')) links.push({ href: `${base.value}/certificates`, label: 'Certificates' });
    if (d.includes('food')) links.push({ href: `${base.value}/kitchen`, label: 'Kitchen board' });
    return links;
});

const navItems = computed(() => festOpsEventNav(props.sahodaya.id, props.event.id, props.duties));
</script>
