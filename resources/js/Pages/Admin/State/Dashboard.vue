<template>
    <AdminLayout title="State Dashboard">
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div v-for="card in statCards" :key="card.label" class="card">
                <p class="text-xs text-gray-500 uppercase tracking-wide">{{ card.label }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ card.value }}</p>
            </div>
        </div>

        <div v-if="clusterRollup" class="grid sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4">
                <p class="text-xs text-indigo-700 uppercase">Cluster events</p>
                <p class="text-2xl font-bold text-indigo-900">{{ clusterRollup.cluster_events }}</p>
            </div>
            <div class="bg-green-50 border border-green-100 rounded-xl p-4">
                <p class="text-xs text-green-700 uppercase">Results published</p>
                <p class="text-2xl font-bold text-green-900">{{ clusterRollup.results_published }}</p>
            </div>
            <div class="bg-amber-50 border border-amber-100 rounded-xl p-4">
                <p class="text-xs text-amber-700 uppercase">In progress</p>
                <p class="text-2xl font-bold text-amber-900">{{ clusterRollup.in_progress }}</p>
            </div>
        </div>

        <section v-if="participation" class="card mb-6">
            <h2 class="font-semibold text-sm mb-3">Cross-cluster participation</h2>
            <div class="grid sm:grid-cols-2 gap-4 mb-4">
                <div class="bg-slate-50 border rounded-xl p-4">
                    <p class="text-xs text-slate-600 uppercase">Total registrations</p>
                    <p class="text-2xl font-bold text-slate-900">{{ participation.total_registrations }}</p>
                </div>
                <div class="bg-green-50 border border-green-100 rounded-xl p-4">
                    <p class="text-xs text-green-700 uppercase">Approved registrations</p>
                    <p class="text-2xl font-bold text-green-900">{{ participation.approved_registrations }}</p>
                </div>
            </div>
            <ul class="text-sm divide-y">
                <li v-for="(row, i) in participation.clusters" :key="i" class="py-2 flex justify-between gap-2">
                    <a v-if="row.manage_url" :href="row.manage_url" class="truncate text-indigo-600 hover:underline">
                        {{ row.sahodaya_name }} — {{ row.event_title }}
                    </a>
                    <span v-else class="truncate">{{ row.sahodaya_name }} — {{ row.event_title }}</span>
                    <span class="text-xs shrink-0 text-gray-600">{{ row.approved }}/{{ row.total }} approved</span>
                </li>
                <li v-if="!participation.clusters?.length" class="py-4 text-gray-400 text-center">No participation data yet</li>
            </ul>
        </section>

        <div class="grid lg:grid-cols-2 gap-6">
            <section class="card">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold text-sm">Cluster results rollup</h2>
                </div>
                <ul class="text-sm divide-y">
                    <li v-for="(row, i) in clusterRollup?.rows ?? []" :key="i" class="py-2 flex justify-between gap-2">
                        <a v-if="row.manage_url" :href="row.manage_url" class="truncate text-indigo-600 hover:underline">{{ row.sahodaya_name }} — {{ row.event_title }}</a>
                        <span v-else class="truncate">{{ row.sahodaya_name }} — {{ row.event_title }}</span>
                        <span class="text-xs shrink-0" :class="row.results_published ? 'text-green-700' : 'text-gray-500'">
                            {{ row.results_published ? 'Published' : row.status }}
                        </span>
                    </li>
                    <li v-if="!clusterRollup?.rows?.length" class="py-4 text-gray-400 text-center">No cluster events yet</li>
                </ul>
            </section>

            <section class="card">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold text-sm">Program propagation</h2>
                    <Link href="/admin/state-programs" class="text-xs text-indigo-600 font-semibold">Manage →</Link>
                </div>
                <ul class="text-sm space-y-3">
                    <li v-for="prog in propagation" :key="prog.program_id" class="border rounded-lg p-3">
                        <div class="flex justify-between gap-2 font-medium">
                            <span>{{ prog.program_title }}</span>
                            <span class="text-xs text-gray-500">{{ prog.propagated_count }}/{{ prog.total_slots }}</span>
                        </div>
                        <ul class="text-xs text-gray-600 mt-2 space-y-1">
                            <li v-for="c in prog.clusters.slice(0, 4)" :key="`${c.sahodaya_id}-${c.level_round}`">
                                <a v-if="c.manage_url" :href="c.manage_url" class="text-indigo-600 hover:underline">{{ c.sahodaya_name }} ({{ c.level_round }})</a>
                                <span v-else>{{ c.sahodaya_name }} ({{ c.level_round }})</span>
                                <span v-if="c.event?.results_published" class="text-green-700">· results out</span>
                                <span v-else-if="c.event" class="text-gray-400">· {{ c.event.status }}</span>
                                <span v-else class="text-amber-600">· pending</span>
                            </li>
                        </ul>
                    </li>
                    <li v-if="!propagation?.length" class="py-4 text-gray-400 text-center">No propagated programs</li>
                </ul>
            </section>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <section class="card">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold text-sm">Recent remittances</h2>
                    <Link href="/admin/state-remittances" class="text-xs text-indigo-600 font-semibold">View all →</Link>
                </div>
                <ul class="text-sm divide-y">
                    <li v-for="r in recentRemittances" :key="r.id" class="py-2 flex justify-between gap-2">
                        <span>{{ r.title }}</span>
                        <span class="text-xs capitalize shrink-0" :class="statusClass(r.status)">{{ r.status }}</span>
                    </li>
                    <li v-if="!recentRemittances.length" class="py-4 text-gray-400 text-center">No remittances yet</li>
                </ul>
            </section>

            <section class="card">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold text-sm">State programs</h2>
                    <Link href="/admin/state-programs" class="text-xs text-indigo-600 font-semibold">Manage →</Link>
                </div>
                <div class="flex flex-wrap gap-2 mb-3">
                    <Link href="/admin/kalotsav" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-800 hover:bg-indigo-100">Kalotsav hub</Link>
                    <Link href="/admin/sports" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-green-50 text-green-800 hover:bg-green-100">Sports results</Link>
                </div>
                <ul class="text-sm divide-y">
                    <li v-for="p in recentPrograms" :key="p.id" class="py-2 flex justify-between gap-2">
                        <span>{{ p.title }}</span>
                        <span class="text-xs capitalize shrink-0 text-gray-500">{{ p.status }}</span>
                    </li>
                    <li v-if="!recentPrograms.length" class="py-4 text-gray-400 text-center">No programs yet</li>
                </ul>
            </section>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    stats: Object,
    recentRemittances: Array,
    recentPrograms: Array,
    propagation: Array,
    clusterRollup: Object,
    participation: Object,
});

const statCards = computed(() => [
    { label: 'State programs', value: props.stats?.total_programs ?? 0 },
    { label: 'Published programs', value: props.stats?.published_programs ?? 0 },
    { label: 'Pending remittances', value: props.stats?.pending_remittances ?? 0 },
    { label: 'Sahodaya clusters', value: props.stats?.sahodaya_clusters ?? 0 },
]);

function statusClass(status) {
    if (status === 'verified') return 'text-green-700';
    if (status === 'submitted') return 'text-amber-700';
    if (status === 'rejected') return 'text-red-600';
    return 'text-gray-500';
}
</script>
