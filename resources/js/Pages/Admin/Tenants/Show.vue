<template>
    <AdminLayout :title="tenant.name">
        <div class="mb-4">
            <Link :href="listUrl" class="text-sm text-gray-400 hover:text-gray-600">
                ← Back to {{ tenant.type === 'sahodaya' ? 'Sahodayas' : 'Schools' }}
            </Link>
        </div>
        <div class="space-y-6">
            <!-- Header card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <h2 class="text-xl font-bold text-gray-900">{{ tenant.name }}</h2>
                        <span :class="tenant.type === 'sahodaya' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'"
                              class="px-2 py-0.5 rounded-full text-xs font-semibold capitalize">
                            {{ tenant.type }}
                        </span>
                        <span :class="tenant.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                              class="px-2 py-0.5 rounded-full text-xs font-medium">
                            {{ tenant.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p v-if="publicUrl" class="text-sm text-indigo-600 font-mono mt-1">
                        <a :href="publicUrl" target="_blank" rel="noopener">{{ publicUrl }}</a>
                    </p>
                    <p v-else-if="subdomainUrl" class="text-sm text-indigo-600 font-mono mt-1">
                        <a :href="subdomainUrl" target="_blank" rel="noopener">{{ subdomainUrl }}</a>
                    </p>
                    <p v-else class="text-sm text-gray-500 font-mono">No public URL — set custom domain or subdomain</p>
                    <div v-if="tenant.domain && subdomainUrl" class="text-xs text-gray-400 font-mono mt-1">
                        Subdomain: <a :href="subdomainUrl" target="_blank" rel="noopener" class="hover:text-indigo-600">{{ subdomainUrl }}</a>
                    </div>
                    <div v-if="tenant.domains?.length" class="mt-2 flex flex-wrap gap-2">
                        <span v-for="d in tenant.domains" :key="d.id"
                              class="text-[10px] font-mono bg-gray-100 text-gray-600 px-2 py-0.5 rounded">
                            {{ d.domain }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap justify-end">
                    <Link :href="`/admin/tenants/${tenant.id}/edit`"
                          class="px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                        Edit
                    </Link>
                    <Link :href="`/admin/builder/sections?tenant=${tenant.id}`"
                          class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition">
                        Site Builder →
                    </Link>
                    <Link v-if="tenant.type === 'sahodaya'"
                          :href="`/sahodaya-admin/${tenant.id}`"
                          class="px-4 py-2 rounded-lg bg-purple-600 text-white text-sm font-medium hover:bg-purple-700 transition">
                        Sahodaya Admin →
                    </Link>
                </div>
            </div>

            <!-- Branding -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-bold text-gray-900 mb-1">Logo</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Shown on the registration portal, login page, and admin sidebar.
                    <span v-if="tenant.type === 'sahodaya'"> Sahodaya admins can also update this under Membership Settings.</span>
                </p>
                <div class="flex flex-wrap items-center gap-5">
                    <div v-if="logoUrl" class="w-20 h-20 rounded-full border border-gray-200 overflow-hidden shrink-0 bg-white">
                        <img :src="logoUrl" :alt="tenant.name" class="w-full h-full object-cover scale-[1.18]">
                    </div>
                    <div v-else class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center text-2xl font-bold text-gray-400 shrink-0">
                        {{ tenant.name?.charAt(0) }}
                    </div>
                    <form @submit.prevent="uploadLogo" class="flex flex-wrap items-center gap-3">
                        <input type="file" accept="image/*" @change="onLogoSelected"
                               class="text-sm text-gray-600 max-w-xs">
                        <button type="submit" :disabled="!logoForm.logo || logoForm.processing"
                                class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                            Upload
                        </button>
                    </form>
                </div>
            </div>

            <!-- Database (Sahodaya only) -->
            <div v-if="tenant.type === 'sahodaya' && database" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-bold text-gray-900 mb-1">Database</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Create the PostgreSQL database manually, enter its name here, then run migrations.
                </p>

                <div class="flex flex-wrap gap-2 mb-4">
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold"
                          :class="database.ready ? 'bg-green-100 text-green-700' : database.exists ? 'bg-amber-100 text-amber-700' : database.configured ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600'">
                        {{ database.ready ? 'Ready' : database.exists ? 'Needs migrations' : database.configured ? 'Database not found' : 'Not configured' }}
                    </span>
                    <span v-if="database.name" class="text-xs font-mono text-gray-500 px-2 py-1 bg-gray-50 rounded">{{ database.name }}</span>
                </div>

                <form @submit.prevent="saveDatabase" class="flex flex-wrap items-end gap-3 mb-4">
                    <div class="grow min-w-[16rem]">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">PostgreSQL database name</label>
                        <input v-model="databaseForm.database_name" type="text" required
                               :placeholder="database.suggested_name"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        <p class="text-xs text-gray-400 mt-1">Lowercase letters, numbers, underscores. Suggested: {{ database.suggested_name }}</p>
                        <p v-if="databaseForm.errors.database_name" class="text-xs text-red-500 mt-1">{{ databaseForm.errors.database_name }}</p>
                    </div>
                    <button type="submit" :disabled="databaseForm.processing"
                            class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                        Save name
                    </button>
                </form>

                <form @submit.prevent="runMigrations" class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input v-model="migrateForm.seed" type="checkbox" class="rounded">
                        Seed default profile & site template
                    </label>
                    <button type="submit" :disabled="migrateForm.processing || !database.configured"
                            class="px-4 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                        Run migrations
                    </button>
                </form>

                <p class="text-xs text-gray-400 mt-3 font-mono">
                    CLI: php artisan sahodaya:provision-databases --tenant={{ tenant.id }} --create
                </p>
            </div>

            <!-- Portal admin login (Sahodaya or school) -->
            <div v-if="tenant.type === 'sahodaya' || tenant.type === 'school'" class="bg-white rounded-xl shadow-sm border p-6"
                 :class="tenant.type === 'sahodaya' ? 'border-purple-100' : 'border-blue-100'">
                <h3 class="font-bold text-gray-900 mb-1">{{ portalAdminTitle }}</h3>
                <p class="text-sm text-gray-500 mb-4">{{ portalAdminHint }}</p>

                <p v-if="loginUrl" class="text-sm mb-4">
                    <span class="text-gray-500">Login URL:</span>
                    <a :href="loginUrl" target="_blank" rel="noopener" class="ml-1 font-mono text-indigo-600 hover:underline">{{ loginUrl }}</a>
                </p>
                <p v-else class="text-sm text-amber-600 mb-4">Set a custom domain or subdomain on the parent Sahodaya first.</p>

                <div v-if="portalAdmins.length" class="mb-5 overflow-hidden rounded-lg border border-gray-100">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Name</th>
                                <th class="px-4 py-2.5 font-semibold">Email</th>
                                <th class="px-4 py-2.5 font-semibold">Password</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="admin in portalAdmins" :key="admin.id">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ admin.name }}</td>
                                <td class="px-4 py-3 font-mono text-gray-600 text-xs">{{ admin.email }}</td>
                                <td class="px-4 py-3 font-mono text-gray-800 text-xs">{{ admin.plain_password || '—' }}</td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <button type="button" @click="editAdmin(admin)"
                                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                        Edit
                                    </button>
                                    <button type="button" @click="removeAdmin(admin)"
                                            class="text-xs font-semibold text-red-600 hover:text-red-800">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form @submit.prevent="saveAdmin" class="space-y-4 max-w-lg">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        {{ adminForm.user_id ? 'Update login' : 'Create login' }}
                    </p>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Full name</label>
                        <input v-model="adminForm.name" type="text" required
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        <p v-if="adminForm.errors.name" class="text-xs text-red-500 mt-1">{{ adminForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Email (username)</label>
                        <input v-model="adminForm.email" type="email" required autocomplete="off"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        <p v-if="adminForm.errors.email" class="text-xs text-red-500 mt-1">{{ adminForm.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Password</label>
                        <input v-model="adminForm.password" type="text" required autocomplete="off"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        <p class="text-xs text-gray-400 mt-1">Stored in plain text for superadmin reference only.</p>
                        <p v-if="adminForm.errors.password" class="text-xs text-red-500 mt-1">{{ adminForm.errors.password }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" :disabled="adminForm.processing"
                                class="px-4 py-2.5 rounded-lg text-white text-sm font-semibold disabled:opacity-50"
                                :class="tenant.type === 'sahodaya' ? 'bg-purple-600 hover:bg-purple-700' : 'bg-blue-600 hover:bg-blue-700'">
                            {{ adminForm.user_id ? 'Save changes' : 'Create login' }}
                        </button>
                        <button v-if="adminForm.user_id" type="button" @click="resetAdminForm"
                                class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                            Cancel edit
                        </button>
                    </div>
                </form>
            </div>

            <!-- Sahodaya control center -->
            <div v-if="tenant.type === 'sahodaya'" class="bg-white rounded-xl shadow-sm border border-purple-100 p-6">
                <h3 class="font-bold text-gray-900 mb-1">Sahodaya control</h3>
                <p class="text-sm text-gray-500 mb-4">Website data, registration process, fees, and school rules for this cluster.</p>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <Link v-for="item in sahodayaLinks" :key="item.href" :href="item.href"
                          class="flex items-start gap-3 p-4 rounded-lg border border-gray-100 hover:border-purple-200 hover:bg-purple-50 transition">
                        <span class="text-xl leading-none">{{ item.icon }}</span>
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ item.label }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ item.hint }}</p>
                        </div>
                    </Link>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <!-- Sections overview -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Sections ({{ tenantOverview.sections?.length ?? 0 }})</h3>
                    <div v-if="tenantOverview.sections?.length" class="space-y-2">
                        <div v-for="section in tenantOverview.sections" :key="section.id"
                             class="flex items-center justify-between text-sm py-2 border-b border-gray-50 last:border-0">
                            <span class="font-mono text-gray-600 text-xs">{{ section.section_type }}/{{ section.variant }}</span>
                            <span :class="section.is_active ? 'text-green-600' : 'text-gray-300'" class="text-xs font-medium">
                                {{ section.is_active ? '● Active' : '○ Hidden' }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">No sections configured yet.</p>
                </div>

                <!-- Settings overview -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Settings ({{ tenantOverview.settings?.length ?? 0 }} keys)</h3>
                    <div v-if="tenantOverview.settings?.length" class="space-y-1.5">
                        <div v-for="setting in tenantOverview.settings" :key="setting.key"
                             class="flex items-center justify-between text-sm">
                            <span class="font-mono text-gray-500 text-xs">{{ setting.key }}</span>
                            <span class="text-xs text-gray-400">configured</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">No settings configured.</p>
                </div>

                <!-- Child schools (for sahodaya) -->
                <div v-if="tenant.type === 'sahodaya' && tenant.children?.length" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 lg:col-span-2">
                    <h3 class="font-bold text-gray-900 mb-4">Member Schools ({{ tenant.children.length }})</h3>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <Link v-for="school in tenant.children" :key="school.id"
                              :href="`/admin/tenants/${school.id}`"
                              class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50 transition text-sm">
                            <span class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs shrink-0">
                                {{ school.name.charAt(0).toUpperCase() }}
                            </span>
                            <span class="font-medium text-gray-800 truncate">{{ school.name }}</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tenant: Object,
    tenantBaseDomain: { type: String, default: 'sahodaya.test' },
    publicUrl: { type: String, default: null },
    subdomainUrl: { type: String, default: null },
    logoUrl: { type: String, default: null },
    listUrl: { type: String, default: '/admin/sahodayas' },
    database: { type: Object, default: null },
    tenantOverview: {
        type: Object,
        default: () => ({ sections: [], settings: [] }),
    },
    sahodayaAdmins: {
        type: Array,
        default: () => [],
    },
    schoolAdmins: {
        type: Array,
        default: () => [],
    },
    loginUrl: { type: String, default: null },
});

const portalAdmins = computed(() =>
    props.tenant.type === 'school' ? props.schoolAdmins : props.sahodayaAdmins
);

const portalAdminTitle = computed(() =>
    props.tenant.type === 'school' ? 'School admin login' : 'Sahodaya admin login'
);

const portalAdminHint = computed(() =>
    props.tenant.type === 'school'
        ? 'School admins sign in on the parent Sahodaya portal website.'
        : 'Sahodaya admins sign in on this cluster\'s portal website, not the superadmin site.'
);

const portalAdminEndpoint = computed(() =>
    props.tenant.type === 'school' ? 'school-admin' : 'sahodaya-admin'
);

const logoForm = useForm({ logo: null });
const databaseForm = useForm({
    database_name: props.database?.name ?? props.database?.suggested_name ?? '',
});
const migrateForm = useForm({ seed: true });
const adminForm = useForm({
    user_id: null,
    name: '',
    email: '',
    password: '',
});

function onLogoSelected(e) {
    logoForm.logo = e.target.files[0] ?? null;
}

function uploadLogo() {
    logoForm.post(`/admin/tenants/${props.tenant.id}/logo`, {
        forceFormData: true,
        onSuccess: () => logoForm.reset(),
    });
}

function saveDatabase() {
    databaseForm.post(`/admin/tenants/${props.tenant.id}/database`);
}

function runMigrations() {
    migrateForm.post(`/admin/tenants/${props.tenant.id}/migrate`);
}

function editAdmin(admin) {
    adminForm.user_id = admin.id;
    adminForm.name = admin.name;
    adminForm.email = admin.email;
    adminForm.password = admin.plain_password || '';
    adminForm.clearErrors();
}

function resetAdminForm() {
    adminForm.reset();
    adminForm.clearErrors();
}

function saveAdmin() {
    adminForm.post(`/admin/tenants/${props.tenant.id}/${portalAdminEndpoint.value}`, {
        onSuccess: () => resetAdminForm(),
    });
}

function removeAdmin(admin) {
    const label = props.tenant.type === 'school' ? 'school admin' : 'Sahodaya admin';
    if (! confirm(`Remove ${label} ${admin.email}?`)) {
        return;
    }

    router.delete(`/admin/tenants/${props.tenant.id}/${portalAdminEndpoint.value}/${admin.id}`, {
        onSuccess: () => {
            if (adminForm.user_id === admin.id) {
                resetAdminForm();
            }
        },
    });
}

const sahodayaLinks = computed(() => {
    const id = props.tenant.id;
    return [
        { href: `/sahodaya-admin/${id}/public-content`,      icon: '🌐', label: 'Website Content', hint: 'Announcements, programmes, links' },
        { href: `/sahodaya-admin/${id}/membership/settings`, icon: '⚙️', label: 'Registration Config', hint: 'Logo, fees, form fields' },
        { href: `/sahodaya-admin/${id}/schools`,             icon: '🏫', label: 'Member Schools', hint: 'Applications & approvals' },
        { href: `/sahodaya-admin/${id}/membership/submissions`, icon: '👨‍🎓', label: 'Student Counts', hint: 'View totals by school' },
        { href: `/sahodaya-admin/${id}/membership/payments`, icon: '💳', label: 'Payments', hint: 'Verify membership fees' },
        { href: `/sahodaya-admin/${id}/membership/reports`, icon: '📊', label: 'Reports', hint: 'Summary & CSV exports' },
        { href: `/sahodaya-admin/${id}/circulars`,          icon: '📄', label: 'Circulars', hint: 'Official notices' },
        { href: `/sahodaya-admin/${id}/office-bearers`,     icon: '👥', label: 'Office Bearers', hint: 'Leadership profiles' },
        { href: `/sahodaya-admin/${id}/kalotsav`,           icon: '🏆', label: 'Kalotsav', hint: 'Events & results' },
    ];
});
</script>
