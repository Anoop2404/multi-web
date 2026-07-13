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
                    <Link v-if="websiteEnabled" :href="`/admin/builder/sections?tenant=${tenant.id}`"
                          class="px-4 py-2 rounded-lg text-white text-sm font-medium transition">
                        Site Builder →
                    </Link>
                    <Link v-if="tenant.type === 'sahodaya'"
                          :href="`/sahodaya-admin/${tenant.id}`"
                          class="btn-primary px-4 py-2 rounded-lg text-sm font-medium transition">
                        Sahodaya Admin →
                    </Link>
                </div>
            </div>

            <!-- Sidebar menu manager (superadmin → Sahodaya) -->
            <div v-if="navManager" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-bold text-gray-900 mb-1">Sidebar menu access</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Turn off any menu or program to hide it for this Sahodaya <span class="font-medium">and all its schools</span>.
                    A disabled item cannot be re-enabled by the Sahodaya admin.
                </p>

                <form @submit.prevent="saveNavVisibility" class="space-y-5">
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Menus</h4>
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            <label v-for="(label, key) in navManager.menus" :key="key"
                                   class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" class="rounded border-gray-300"
                                       :checked="navForm.menus[key] !== false"
                                       @change="navForm.menus[key] = $event.target.checked">
                                <span class="text-sm text-gray-700">{{ label }}</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Fest programs</h4>
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            <label v-for="(label, key) in navManager.programs" :key="key"
                                   class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" class="rounded border-gray-300"
                                       :checked="navForm.programs[key] !== false"
                                       @change="navForm.programs[key] = $event.target.checked">
                                <span class="text-sm text-gray-700">{{ label }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="btn-primary px-4 py-2 rounded-lg text-sm font-medium" :disabled="navForm.processing">
                            {{ navForm.processing ? 'Saving…' : 'Save menu access' }}
                        </button>
                        <span v-if="navForm.recentlySuccessful" class="text-sm text-green-600">Saved.</span>
                    </div>
                </form>
            </div>

            <!-- School membership (superadmin) -->
            <div v-if="tenant.type === 'school'" class="bg-white rounded-xl shadow-sm border border-red-100 p-6">
                <h3 class="font-bold text-gray-900 mb-1">Membership status</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Reject an approved school to block portal access, or permanently delete test registrations.
                </p>

                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold capitalize"
                          :class="membershipStatusClass(tenant.membership_status)">
                        {{ tenant.membership_status || 'pending' }}
                    </span>
                    <span v-if="tenant.school_prefix" class="text-xs font-mono text-gray-500 bg-gray-50 px-2 py-1 rounded">
                        {{ tenant.school_prefix }}
                    </span>
                </div>

                <p v-if="tenant.application_payload?.rejection_reason"
                   class="text-sm text-red-700 bg-red-50 border border-red-100 rounded-lg px-3 py-2 mb-4">
                    <span class="font-semibold">Rejection reason:</span>
                    {{ tenant.application_payload.rejection_reason }}
                </p>

                <form v-if="tenant.membership_status !== 'rejected'"
                      @submit.prevent="rejectSchool"
                      class="space-y-3 max-w-lg mb-6 pb-6 border-b border-gray-100">
                    <div>
                        <label class="form-label mb-1.5">Reject school (with reason)</label>
                        <textarea v-model="rejectForm.reason" rows="3" required
                                  placeholder="Reason shown to the school admin by email…"
                                  class="field focus:ring-red-200"></textarea>
                        <p v-if="rejectForm.errors.reason" class="text-xs text-red-500 mt-1">{{ rejectForm.errors.reason }}</p>
                    </div>
                    <button type="submit" :disabled="rejectForm.processing"
                            class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700 disabled:opacity-50">
                        Reject school
                    </button>
                </form>

                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Danger zone</p>
                    <button type="button" @click="deleteTenant"
                            class="px-4 py-2 rounded-lg border border-red-200 text-red-700 text-sm font-semibold hover:bg-red-50">
                        Delete school permanently
                    </button>
                    <p class="text-xs text-gray-400">
                        Removes the school, its admin login(s), and domain records. Tenant DB rows for this school are not purged.
                    </p>
                </div>
            </div>

            <!-- Branding -->
            <div class="card">
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
                                class="px-4 py-2 rounded-lg text-white text-sm font-semibold disabled:opacity-50">
                            Upload
                        </button>
                    </form>
                </div>
            </div>

            <!-- Database (Sahodaya only) -->
            <div v-if="tenant.type === 'sahodaya' && database" class="card">
                <h3 class="font-bold text-gray-900 mb-1">Database</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Create the PostgreSQL database manually, then enter its name.
                    Username and password are optional — leave both blank to connect with the central app DB user (same as today).
                </p>

                <div class="flex flex-wrap gap-2 mb-4">
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold"
                          :class="database.ready ? 'bg-green-100 text-green-700' : database.exists ? 'bg-amber-100 text-amber-700' : database.configured ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600'">
                        {{ database.ready ? 'Ready' : database.exists ? 'Needs migrations' : database.configured ? 'Database not found' : 'Not configured' }}
                    </span>
                    <span v-if="database.name" class="text-xs font-mono text-gray-500 px-2 py-1 bg-gray-50 rounded">{{ database.name }}</span>
                    <span v-if="database.username" class="text-xs font-mono text-gray-500 px-2 py-1 bg-gray-50 rounded">user: {{ database.username }}</span>
                    <span v-if="database.has_password" class="text-xs font-mono text-emerald-700 px-2 py-1 bg-emerald-50 rounded">custom password set</span>
                </div>

                <form @submit.prevent="saveDatabase" class="space-y-4 mb-4">
                    <div class="grid sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-3">
                            <label class="form-label mb-1.5">PostgreSQL database name <span class="text-red-500">*</span></label>
                            <input v-model="databaseForm.database_name" type="text" required
                                   :placeholder="database.suggested_name"
                                   class="field font-mono focus:ring-indigo-300">
                            <p class="text-xs text-gray-400 mt-1">Lowercase letters, numbers, underscores. Suggested: {{ database.suggested_name }}</p>
                            <p v-if="databaseForm.errors.database_name" class="text-xs text-red-500 mt-1">{{ databaseForm.errors.database_name }}</p>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">DB username <span class="font-normal text-gray-400">(optional)</span></label>
                            <input v-model="databaseForm.db_username" type="text" class="field font-mono" autocomplete="off"
                                   placeholder="Optional — blank uses central DB user">
                            <p v-if="databaseForm.errors.db_username" class="text-xs text-red-500 mt-1">{{ databaseForm.errors.db_username }}</p>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">DB password <span class="font-normal text-gray-400">(optional)</span></label>
                            <input v-model="databaseForm.db_password" type="password" class="field font-mono" autocomplete="new-password"
                                   :placeholder="database.has_password ? '•••••••• (leave blank to keep)' : 'Optional — blank uses central password'">
                            <p v-if="databaseForm.errors.db_password" class="text-xs text-red-500 mt-1">{{ databaseForm.errors.db_password }}</p>
                        </div>
                        <div v-if="database.has_password" class="flex items-end">
                            <label class="flex items-center gap-2 text-sm text-gray-600 pb-2.5">
                                <input v-model="databaseForm.clear_db_password" type="checkbox" class="rounded">
                                Remove custom password (use central)
                            </label>
                        </div>
                    </div>
                    <button type="submit" :disabled="databaseForm.processing"
                            class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                        Save database connection
                    </button>
                </form>

                <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-4 mb-4 space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">
                        Optional: create first Sahodaya admin after migrations
                    </p>
                    <div class="grid sm:grid-cols-3 gap-3">
                        <div>
                            <label class="form-label mb-1.5">Admin name</label>
                            <input v-model="databaseForm.admin_name" type="text" class="field" placeholder="Cluster admin">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Admin email</label>
                            <input v-model="databaseForm.admin_email" type="email" class="field" placeholder="admin@example.com" autocomplete="off">
                            <p v-if="databaseForm.errors.admin_email" class="text-xs text-red-500 mt-1">{{ databaseForm.errors.admin_email }}</p>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Admin password <span class="font-normal text-gray-400">(optional)</span></label>
                            <input v-model="databaseForm.admin_password" type="text" class="field font-mono" placeholder="Only if creating an admin" autocomplete="off">
                            <p v-if="databaseForm.errors.admin_password" class="text-xs text-red-500 mt-1">{{ databaseForm.errors.admin_password }}</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">
                        Only the database name is required. Save it, create the empty PostgreSQL database, then Run migrations.
                        Fill admin email + password only if you want the first portal login created after migrate.
                    </p>
                </div>

                <form @submit.prevent="runMigrations" class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input v-model="migrateForm.seed" type="checkbox" class="rounded">
                        Seed default profile & site template
                    </label>
                    <button type="submit" :disabled="migrateForm.processing || !database.configured"
                            class="px-4 py-2.5 rounded-lg text-white text-sm font-semibold disabled:opacity-50">
                        Run migrations
                    </button>
                </form>

                <p v-if="!database.ready && database.exists" class="text-sm text-amber-700 mt-3">
                    Database exists but is not fully migrated (missing roles/users). Run migrations before creating portal admins.
                </p>

                <p class="text-xs text-gray-400 mt-3 font-mono">
                    CLI: php artisan sahodaya:provision-databases --tenant={{ tenant.id }} --create
                </p>
            </div>

            <!-- Portal admin login (Sahodaya or school) -->
            <div v-if="tenant.type === 'sahodaya' || tenant.type === 'school'" class="bg-white rounded-xl shadow-sm border p-6"
                 :class="tenant.type === 'sahodaya' ? 'border-purple-100' : 'border-blue-100'">
                <h3 class="font-bold text-gray-900 mb-1">{{ portalAdminTitle }}</h3>
                <p class="text-sm text-gray-500 mb-4">{{ portalAdminHint }}</p>

                <div v-if="tenant.type === 'sahodaya' && database && !database.ready"
                     class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-4">
                    Finish database setup (save name → create PostgreSQL DB → run migrations) before creating portal admins.
                </div>

                <template v-else>

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
                                <th class="px-4 py-2.5 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="admin in portalAdmins" :key="admin.id">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ admin.name }}</td>
                                <td class="px-4 py-3 font-mono text-gray-600 text-xs">{{ admin.email }}</td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <button type="button" @click="editAdmin(admin)"
                                            class="link-brand text-xs">
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
                        <label class="form-label mb-1.5">Full name</label>
                        <input v-model="adminForm.name" type="text" required
                               class="field focus:ring-indigo-300">
                        <p v-if="adminForm.errors.name" class="text-xs text-red-500 mt-1">{{ adminForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="form-label mb-1.5">Email (username)</label>
                        <input v-model="adminForm.email" type="email" required autocomplete="off"
                               class="field focus:ring-indigo-300">
                        <p v-if="adminForm.errors.email" class="text-xs text-red-500 mt-1">{{ adminForm.errors.email }}</p>
                    </div>
                    <div>
                        <label class="form-label mb-1.5">Password</label>
                        <input v-model="adminForm.password" type="text" :required="!adminForm.user_id" autocomplete="off"
                               class="field font-mono focus:ring-indigo-300">
                        <p class="text-xs text-gray-400 mt-1">
                            {{ adminForm.user_id ? 'Leave blank to keep the current password.' : 'Required for new logins.' }}
                        </p>
                        <p v-if="adminForm.errors.password" class="text-xs text-red-500 mt-1">{{ adminForm.errors.password }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" :disabled="adminForm.processing"
                                class="btn-primary disabled:opacity-50">
                            {{ adminForm.user_id ? 'Save changes' : 'Create login' }}
                        </button>
                        <button v-if="adminForm.user_id" type="button" @click="resetAdminForm"
                                class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                            Cancel edit
                        </button>
                    </div>
                </form>
                </template>
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
                <div class="card">
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
                <div class="card">
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
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
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
    navManager: { type: Object, default: null },
});

const websiteEnabled = computed(() => usePage().props.features?.website_enabled ?? false);

const navForm = useForm({
    programs: { ...(props.navManager?.overrides?.programs ?? {}) },
    menus: { ...(props.navManager?.overrides?.menus ?? {}) },
});

function saveNavVisibility() {
    navForm.put(`/admin/tenants/${props.tenant.id}/nav-visibility`, { preserveScroll: true });
}

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
    db_username: props.database?.username ?? '',
    db_password: '',
    clear_db_password: false,
    admin_name: '',
    admin_email: '',
    admin_password: '',
});
const migrateForm = useForm({
    seed: true,
    admin_name: '',
    admin_email: '',
    admin_password: '',
});
const adminForm = useForm({
    user_id: null,
    name: '',
    email: '',
    password: '',
});
const rejectForm = useForm({ reason: '' });

function membershipStatusClass(status) {
    return {
        approved: 'bg-green-100 text-green-700',
        pending:  'bg-amber-100 text-amber-800',
        rejected: 'bg-red-100 text-red-700',
    }[status] || 'bg-gray-100 text-gray-600';
}

function rejectSchool() {
    if (! confirm(`Reject "${props.tenant.name}"? The school admin will be notified by email.`)) {
        return;
    }

    rejectForm.post(`/admin/tenants/${props.tenant.id}/reject-membership`, {
        onSuccess: () => rejectForm.reset(),
    });
}

function deleteTenant() {
    if (! confirm(`Permanently delete "${props.tenant.name}" and its admin account(s)? This cannot be undone.`)) {
        return;
    }

    router.delete(`/admin/tenants/${props.tenant.id}`);
}

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
    migrateForm.admin_name = databaseForm.admin_name;
    migrateForm.admin_email = databaseForm.admin_email;
    migrateForm.admin_password = databaseForm.admin_password;
    migrateForm.post(`/admin/tenants/${props.tenant.id}/migrate`);
}

function editAdmin(admin) {
    adminForm.user_id = admin.id;
    adminForm.name = admin.name;
    adminForm.email = admin.email;
    adminForm.password = '';
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
        { href: `/sahodaya-admin/${id}/kalotsav`, icon: '🏆', label: 'Kalotsav', hint: 'Events & catalog' },
    ];
});
</script>
