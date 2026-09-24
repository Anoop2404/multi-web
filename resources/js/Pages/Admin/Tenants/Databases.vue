<template>
    <AdminLayout title="Sahodaya databases">
        <div class="max-w-5xl space-y-4">
            <div class="card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">Sahodaya databases</h3>
                        <p class="text-xs text-gray-500 mt-0.5 max-w-2xl">
                            Each Sahodaya runs on its own Postgres database; its member schools share it.
                            A Sahodaya promoted from the State roster has a tenant and a login before it
                            has a database — this is where the database gets created and migrated.
                        </p>
                    </div>
                </div>

                <!-- Nothing to do at all is worth saying outright, rather than showing a table of
                     "not configured" rows that look like a fault. -->
                <div v-if="!enabled" class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    <strong>Dedicated databases are turned off</strong> on this installation
                    (<code>TENANCY_DATABASE_PER_SAHODAYA</code>), so every Sahodaya shares one database and
                    there is nothing to create. Turn it on in the environment first.
                </div>

                <template v-else>
                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-lg border border-slate-200 px-3 py-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Sahodayas</p>
                            <p class="text-lg font-bold text-slate-900">{{ counts.total }}</p>
                        </div>
                        <div class="rounded-lg border px-3 py-2" :class="counts.ready === counts.total ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200'">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Ready</p>
                            <p class="text-lg font-bold" :class="counts.ready === counts.total ? 'text-emerald-700' : 'text-slate-900'">{{ counts.ready }}</p>
                        </div>
                        <div class="rounded-lg border px-3 py-2" :class="counts.missing ? 'border-amber-300 bg-amber-50' : 'border-slate-200'">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">No database</p>
                            <p class="text-lg font-bold" :class="counts.missing ? 'text-amber-700' : 'text-slate-900'">{{ counts.missing }}</p>
                        </div>
                        <div class="rounded-lg border px-3 py-2" :class="counts.unmigrated ? 'border-indigo-200 bg-indigo-50' : 'border-slate-200'">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Not migrated</p>
                            <p class="text-lg font-bold" :class="counts.unmigrated ? 'text-indigo-700' : 'text-slate-900'">{{ counts.unmigrated }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-1.5 text-xs text-gray-600">
                            <input v-model="seed" type="checkbox">
                            Seed the Sahodaya profile and site template
                        </label>
                        <button type="button" class="btn-primary text-xs" :disabled="!pending || busy" @click="provisionAll">
                            {{ busy ? 'Working…' : `Create the ${Math.min(pending, bulkLimit)} missing` }}
                        </button>
                        <span v-if="pending > bulkLimit" class="text-xs text-amber-700">
                            {{ pending }} pending — this does {{ bulkLimit }} at a time.
                        </span>
                    </div>

                    <p class="mt-2 text-[11px] text-gray-500">
                        Each one is created, migrated and seeded before the next starts, so this takes a
                        while and the page will sit waiting. Nothing is destroyed: an existing database is
                        migrated, never dropped.
                    </p>
                </template>
            </div>

            <div v-if="enabled" class="card !p-0 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] font-bold uppercase tracking-wider text-gray-400 border-b">
                            <th class="py-2 px-3">Sahodaya</th>
                            <th class="py-2 px-3">Database</th>
                            <th class="py-2 px-3 w-32">State</th>
                            <th class="py-2 px-3 w-20 text-right">Schools</th>
                            <th class="py-2 px-3 w-28"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in sahodayas" :key="s.id" class="border-b last:border-0"
                            :class="{ 'opacity-60': !s.is_active }">
                            <td class="py-2 px-3">
                                <Link :href="s.href" class="font-medium link-brand">{{ s.name }}</Link>
                                <span v-if="s.subdomain" class="block text-[11px] text-gray-400">{{ s.subdomain }}</span>
                            </td>
                            <td class="py-2 px-3">
                                <span v-if="s.status?.name" class="font-mono text-[11px] text-gray-600">{{ s.status.name }}</span>
                                <span v-else class="text-[11px] text-gray-400">not configured</span>
                                <span v-if="s.status?.error" class="block text-[11px] text-red-600">{{ s.status.error }}</span>
                            </td>
                            <td class="py-2 px-3">
                                <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                      :class="badge(s.status)">{{ label(s.status) }}</span>
                            </td>
                            <td class="py-2 px-3 text-right text-gray-500">{{ s.schools }}</td>
                            <td class="py-2 px-3 text-right">
                                <button v-if="!s.status?.ready" type="button" class="text-xs font-semibold link-brand"
                                        :disabled="busy" @click="provision(s)">
                                    {{ s.status?.exists ? 'Migrate' : 'Create' }}
                                </button>
                                <span v-else class="text-[11px] text-emerald-700">✓</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    enabled: Boolean, sahodayas: Array, counts: Object, bulkLimit: Number, actionUrls: Object,
});

const seed = ref(true);
const busy = ref(false);

const pending = computed(() => props.counts.missing + props.counts.unmigrated);

function label(status) {
    if (status?.error) return 'unreachable';
    if (status?.ready) return 'ready';
    if (status?.exists) return 'not migrated';
    if (status?.configured) return 'no database';
    return 'not configured';
}
function badge(status) {
    if (status?.error) return 'bg-red-100 text-red-700';
    if (status?.ready) return 'bg-emerald-100 text-emerald-700';
    if (status?.exists) return 'bg-indigo-100 text-indigo-700';
    return 'bg-amber-100 text-amber-800';
}

function provision(sahodaya) {
    const verb = sahodaya.status?.exists ? 'Migrate' : 'Create and migrate';
    if (!confirm(`${verb} the database for ${sahodaya.name}?`)) return;

    busy.value = true;
    router.post(props.actionUrls.provision, { sahodaya_id: sahodaya.id, seed: seed.value }, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function provisionAll() {
    if (!confirm(`Create and migrate up to ${props.bulkLimit} Sahodaya database(s)? This runs one at a time and may take several minutes.`)) return;

    busy.value = true;
    router.post(props.actionUrls.provisionAll, { seed: seed.value }, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}
</script>
