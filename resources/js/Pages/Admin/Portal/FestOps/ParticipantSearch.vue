<template>
    <PortalLayout role-label="Participant lookup" :title="event.title" :subtitle="sahodaya.name" accent="emerald" :nav-items="navItems">
        <form @submit.prevent="search" class="flex flex-wrap gap-2 mb-4">
            <input v-model="queryLocal" type="search" class="field flex-1 min-w-[12rem]" placeholder="Reg no, chest no, or name…">
            <button type="submit" class="btn-primary text-sm">Search</button>
        </form>
        <div v-if="results.length" class="card card--flush">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Reg</th><th>Chest</th><th>Item</th><th>School</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="r in results" :key="r.participant_id">
                        <td>{{ r.name }}</td>
                        <td class="text-xs">{{ r.reg_no }}</td>
                        <td class="text-xs">{{ r.chest_no ?? '—' }}</td>
                        <td class="text-xs">{{ r.item }}</td>
                        <td class="text-xs">{{ r.school }}</td>
                        <td class="text-right">
                            <a :href="`${base}/admit-card?participant_id=${r.participant_id}`" target="_blank" class="btn-ghost text-xs">Admit card</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else-if="query" class="text-sm text-gray-400">No participants found.</p>
    </PortalLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({ sahodaya: Object, event: Object, query: String, results: Array, duties: Array });
const queryLocal = ref(props.query ?? '');
const base = computed(() => `/portal/fest-ops/${props.sahodaya.id}/events/${props.event.id}`);
const navItems = computed(() => [
    { href: `/portal/fest-ops/${props.sahodaya.id}`, label: 'Dashboard' },
    { href: base.value, label: 'Event' },
    { href: `${base.value}/participants/search`, label: 'Search' },
]);

function search() {
    router.get(`${base.value}/participants/search`, { q: queryLocal.value }, { preserveState: true });
}
</script>
