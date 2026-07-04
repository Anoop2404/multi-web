<template>
    <AdminLayout :title="pageTitle">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-700">{{ pageTitle }}</h3>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ tenantType === 'sahodaya'
                        ? 'Manage Sahodaya clusters, custom domains, and portal access.'
                        : 'Manage member schools, domains, and parent Sahodaya assignment.' }}
                </p>
            </div>
            <Link :href="createUrl"
                  class="btn-primary">
                + {{ tenantType === 'sahodaya' ? 'Add Sahodaya' : 'Add School' }}
            </Link>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4 p-4">
            <form @submit.prevent="applyFilters" class="flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="text-xs font-semibold text-gray-600">Search</label>
                    <input v-model="filterForm.search" type="search" placeholder="Name, domain, subdomain…"
                           class="field mt-1 text-sm">
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Status</label>
                    <select v-model="filterForm.status" class="field mt-1 text-sm">
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary text-sm">Apply</button>
                <button v-if="hasFilters" type="button" @click="clearFilters" class="btn-ghost text-sm">Clear</button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th v-if="tenantType === 'school'" class="px-4 py-3 text-left">Sahodaya</th>
                        <th v-else class="px-4 py-3 text-left">Schools</th>
                        <th class="px-4 py-3 text-left">Custom Domain</th>
                        <th class="px-4 py-3 text-left">Subdomain</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="!tenants.data.length">
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                            No {{ tenantType === 'sahodaya' ? 'Sahodaya clusters' : 'schools' }} match your filters.
                        </td>
                    </tr>
                    <tr v-for="tenant in tenants.data" :key="tenant.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ tenant.name }}</td>
                        <td v-if="tenantType === 'school'" class="px-4 py-3 text-gray-500 text-xs">
                            {{ tenant.parent?.name || '—' }}
                        </td>
                        <td v-else class="px-4 py-3 text-gray-500 text-xs">
                            {{ tenant.children_count ?? 0 }}
                        </td>
                        <td class="px-4 py-3">
                            <a v-if="tenant.domain" :href="`https://${tenant.domain}`" target="_blank" rel="noopener"
                               class="font-mono text-xs text-indigo-600 hover:underline">
                                {{ tenant.domain }}
                            </a>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="tenant.subdomain" class="font-mono text-xs text-gray-500">
                                {{ tenant.subdomain }}.{{ tenantBaseDomain }}
                            </span>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="tenant.is_active ? 'text-green-600' : 'text-gray-400'" class="text-xs font-medium">
                                {{ tenant.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <Link :href="`/admin/tenants/${tenant.id}/edit`" class="text-gray-500 hover:text-gray-700 text-xs mr-3">Edit</Link>
                            <Link :href="`/admin/tenants/${tenant.id}`" class="text-indigo-600 hover:underline text-xs">View</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="tenants.links?.length > 3" class="px-4 py-3 border-t border-gray-100 flex flex-wrap gap-1">
                <Link v-for="link in tenants.links" :key="link.label"
                      :href="link.url || '#'"
                      :class="['px-3 py-1 rounded-lg text-xs', link.active ? 'bg-indigo-100 text-indigo-800 font-semibold' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                      v-html="link.label" />
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    tenants: Object,
    tenantType: { type: String, required: true },
    pageTitle: { type: String, required: true },
    createUrl: { type: String, required: true },
    tenantBaseDomain: { type: String, default: 'sahodaya.test' },
    filters: { type: Object, default: () => ({ search: '', status: 'all' }) },
});

const filterForm = reactive({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? 'all',
});

watch(() => props.filters, (f) => {
    filterForm.search = f?.search ?? '';
    filterForm.status = f?.status ?? 'all';
}, { deep: true });

const hasFilters = computed(() => !!filterForm.search || filterForm.status !== 'all');

const listPath = computed(() =>
    props.tenantType === 'school' ? '/admin/schools' : '/admin/sahodayas',
);

function applyFilters() {
    router.get(listPath.value, {
        search: filterForm.search || undefined,
        status: filterForm.status !== 'all' ? filterForm.status : undefined,
    }, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    filterForm.search = '';
    filterForm.status = 'all';
    router.get(listPath.value, {}, { preserveState: true, preserveScroll: true });
}
</script>
