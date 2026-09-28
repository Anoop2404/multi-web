<template>
    <AdminLayout :title="tenant.name">
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Breadcrumbs -->
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                <Link :href="listUrl" class="hover:text-indigo-600 transition flex items-center gap-1">
                    <span>← Back to {{ tenant.type === 'sahodaya' ? 'Sahodaya Clusters' : 'Member Schools' }}</span>
                </Link>
                <span>/</span>
                <span class="text-slate-800 font-bold truncate max-w-xs">{{ tenant.name }}</span>
            </div>

            <!-- Hero Header Card -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)] flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="flex items-start sm:items-center gap-4">
                    <!-- Logo / Avatar -->
                    <div class="w-16 h-16 rounded-2xl border border-slate-200 overflow-hidden shrink-0 bg-slate-50 flex items-center justify-center shadow-xs">
                        <img v-if="logoUrl" :src="logoUrl" :alt="tenant.name" class="w-full h-full object-cover">
                        <span v-else class="text-2xl font-black text-indigo-600">{{ tenant.name?.charAt(0) }}</span>
                    </div>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">{{ tenant.name }}</h1>
                            <span :class="tenant.type === 'sahodaya' ? 'bg-purple-100 text-purple-700 border-purple-200' : 'bg-blue-100 text-blue-700 border-blue-200'"
                                  class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider border">
                                {{ tenant.type }}
                            </span>
                            <span :class="tenant.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
                                  class="px-2.5 py-0.5 rounded-full text-xs font-bold border flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full" :class="tenant.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                {{ tenant.is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <span v-if="tenant.school_prefix"
                                  class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-slate-100 text-slate-700">
                                {{ tenant.school_prefix }}
                            </span>
                        </div>

                        <!-- Web Link Strip -->
                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 font-mono mt-1.5">
                            <a v-if="publicUrl" :href="publicUrl" target="_blank" rel="noopener"
                               class="text-indigo-600 hover:text-indigo-800 font-semibold hover:underline flex items-center gap-1">
                                <span>🌐 {{ publicUrl }}</span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                            <a v-else-if="subdomainUrl" :href="subdomainUrl" target="_blank" rel="noopener"
                               class="text-slate-600 hover:text-indigo-600 hover:underline flex items-center gap-1">
                                <span>🔗 {{ subdomainUrl }}</span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                            <span v-if="tenant.parent?.name" class="font-sans text-slate-500 font-medium">
                                Cluster: <strong class="text-slate-700">{{ tenant.parent.name }}</strong>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Action Controls -->
                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <Link :href="`/admin/tenants/${tenant.id}/edit`"
                          class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-xs">
                        Edit Profile
                    </Link>

                    <a v-if="loginUrl" :href="loginUrl" target="_blank" rel="noopener"
                       class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-xs flex items-center gap-1.5">
                        <span>Portal Login</span>
                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>

                    <Link v-if="tenant.type === 'sahodaya'"
                          :href="`/sahodaya-admin/${tenant.id}`"
                          class="px-4 py-2 rounded-xl bg-purple-600 text-xs font-bold text-white hover:bg-purple-700 transition shadow-xs">
                        Sahodaya Admin Panel →
                    </Link>
                </div>
            </div>

            <!-- Setup Checklist Notice -->
            <div v-if="setupChecklist && !setupChecklist.complete"
                 class="bg-amber-50 rounded-2xl border border-amber-200/80 p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-amber-600 text-lg">⚠️</span>
                        <h3 class="font-bold text-amber-950 text-sm">
                            Setup Incomplete — {{ setupChecklist.pending_count }} requirement(s) pending
                        </h3>
                    </div>
                    <p class="text-xs text-amber-800 mt-1">
                        Complete these steps so member schools and coordinators experience seamless portal access.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span v-for="(step, key) in setupChecklist.steps" :key="key"
                          :class="step.completed ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-white text-amber-900 border-amber-200'"
                          class="px-2.5 py-1 rounded-lg text-xs font-semibold border flex items-center gap-1">
                        <span>{{ step.completed ? '✓' : '○' }}</span>
                        <span>{{ step.label }}</span>
                    </span>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-1.5 shadow-xs flex flex-wrap gap-1">
                <button type="button" @click="activeTab = 'overview'"
                        :class="['px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer',
                                 activeTab === 'overview' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50']">
                    📋 Overview & Identity
                </button>

                <button type="button" @click="activeTab = 'admins'"
                        :class="['px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1.5',
                                 activeTab === 'admins' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50']">
                    <span>🔑 Portal Admin Accounts</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'admins' ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-700'">
                        {{ portalAdmins.length }}
                    </span>
                </button>

                <button v-if="tenant.type === 'sahodaya'" type="button" @click="activeTab = 'database'"
                        :class="['px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer',
                                 activeTab === 'database' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50']">
                    🗄️ Database & Permissions
                </button>

                <button type="button" @click="activeTab = 'danger'"
                        :class="['px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer ml-auto',
                                 activeTab === 'danger' ? 'bg-rose-600 text-white shadow-xs' : 'text-rose-600 hover:bg-rose-50']">
                    ⚠️ Super Admin Actions
                </button>
            </div>

            <!-- TAB 1: OVERVIEW & IDENTITY -->
            <div v-if="activeTab === 'overview'" class="space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Identity & Logo Card -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs space-y-4">
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Official Logo & Branding</h3>
                        <p class="text-xs text-slate-500">
                            Rendered across portal logins, official certificates, admit cards, and email headers.
                        </p>

                        <div class="flex items-center gap-4 p-4 rounded-xl bg-slate-50 border border-slate-100">
                            <div class="w-16 h-16 rounded-xl border border-slate-200 bg-white overflow-hidden flex items-center justify-center shrink-0">
                                <img v-if="logoUrl" :src="logoUrl" :alt="tenant.name" class="w-full h-full object-cover">
                                <span v-else class="text-xl font-black text-slate-400">{{ tenant.name?.charAt(0) }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold text-slate-700">Square PNG or JPG</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">Recommended 400×400px</p>
                            </div>
                        </div>

                        <form @submit.prevent="uploadLogo" class="space-y-3 pt-2">
                            <input type="file" accept="image/*" @change="onLogoSelected"
                                   class="text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                            <button type="submit" :disabled="!logoForm.logo || logoForm.processing"
                                    class="w-full py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 disabled:opacity-40 transition">
                                {{ logoForm.processing ? 'Uploading logo…' : 'Update Logo' }}
                            </button>
                            <p v-if="logoForm.errors.logo" class="text-xs text-rose-500">{{ logoForm.errors.logo }}</p>
                        </form>
                    </div>

                    <!-- Domain & Network Info -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs space-y-4 lg:col-span-2">
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Web Addresses & Routing</h3>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 space-y-1.5">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Primary Custom Domain</p>
                                <p v-if="tenant.domain" class="text-sm font-mono font-bold text-indigo-700">{{ tenant.domain }}</p>
                                <p v-else class="text-xs text-slate-400 italic">No custom domain attached</p>
                            </div>

                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 space-y-1.5">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Managed Subdomain</p>
                                <p v-if="tenant.subdomain" class="text-sm font-mono font-bold text-slate-800">
                                    {{ tenant.subdomain }}.{{ tenantBaseDomain }}
                                </p>
                                <p v-else class="text-xs text-slate-400 italic">No subdomain defined</p>
                            </div>
                        </div>

                        <div v-if="tenant.domains?.length" class="pt-2">
                            <p class="text-xs font-semibold text-slate-500 mb-2">Registered Domain Aliases:</p>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="d in tenant.domains" :key="d.id"
                                      class="px-2.5 py-1 rounded-lg text-xs font-mono bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ d.domain }}
                                </span>
                            </div>
                        </div>

                        <!-- Parent Cluster Info for Schools -->
                        <div v-if="tenant.type === 'school'" class="pt-4 border-t border-slate-100 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-slate-900">Affiliated Sahodaya Cluster</p>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    {{ tenant.parent?.name || 'Not assigned to a Sahodaya cluster yet' }}
                                </p>
                            </div>
                            <Link v-if="tenant.parent_id" :href="`/admin/tenants/${tenant.parent_id}`"
                                  class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                                View Cluster →
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Child schools list if Sahodaya -->
                <div v-if="tenant.type === 'sahodaya' && tenant.children?.length"
                     class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">
                            Member Schools in this Cluster ({{ tenant.children.length }})
                        </h3>
                    </div>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <Link v-for="school in tenant.children" :key="school.id"
                              :href="`/admin/tenants/${school.id}`"
                              class="p-3.5 rounded-xl border border-slate-200/80 hover:border-indigo-300 hover:bg-indigo-50/30 transition flex items-center gap-3">
                            <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-xs shrink-0">
                                {{ school.name.charAt(0) }}
                            </span>
                            <span class="text-xs font-bold text-slate-800 truncate">{{ school.name }}</span>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- TAB 2: PORTAL ADMIN ACCOUNTS -->
            <div v-if="activeTab === 'admins'" class="space-y-6">
                <!-- Admins Table Card -->
                <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-2 border-b border-slate-100">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 tracking-tight">{{ portalAdminTitle }}</h3>
                            <p class="text-xs text-slate-500 mt-0.5">{{ portalAdminHint }}</p>
                        </div>
                        <div v-if="loginUrl" class="text-xs font-mono text-slate-600 flex items-center gap-1.5 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200">
                            <span class="text-slate-400">Login URL:</span>
                            <a :href="loginUrl" target="_blank" rel="noopener" class="text-indigo-600 font-bold hover:underline">{{ loginUrl }}</a>
                        </div>
                    </div>

                    <div v-if="portalAdmins.length" class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Admin Name</th>
                                    <th class="px-4 py-3">Username (Login ID)</th>
                                    <th class="px-4 py-3">Password Access</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="admin in portalAdmins" :key="admin.id" class="hover:bg-slate-50/50">
                                    <td class="px-4 py-3.5 font-bold text-slate-900">{{ admin.name }}</td>
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <code class="px-2 py-0.5 rounded bg-slate-100 font-mono text-xs font-semibold text-slate-800">
                                                {{ admin.username || admin.email }}
                                            </code>
                                            <button type="button" @click="copyText(admin.username || admin.email)" class="text-slate-400 hover:text-slate-600">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <button v-if="revealedPasswords[admin.id]" type="button" @click="revealPassword(admin)"
                                                class="px-2 py-1 rounded bg-emerald-50 text-emerald-800 font-mono text-xs font-bold border border-emerald-200" title="Click to hide">
                                            {{ revealedPasswords[admin.id] }}
                                        </button>
                                        <span v-else-if="!admin.has_password" class="text-amber-700 text-xs italic">
                                            Not stored — reset password below
                                        </span>
                                        <button v-else type="button" @click="revealPassword(admin)"
                                                :disabled="revealingId === admin.id"
                                                class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-xs">
                                            {{ revealingId === admin.id ? 'Decrypting…' : '👁️ Reveal Password' }}
                                        </button>
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap space-x-2">
                                        <button type="button" @click="openImpersonate(admin)"
                                                class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 font-bold text-xs hover:bg-amber-100 transition">
                                            Impersonate
                                        </button>
                                        <button type="button" @click="editAdmin(admin)"
                                                class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 font-bold text-xs hover:bg-slate-50 transition">
                                            Edit
                                        </button>
                                        <button type="button" @click="removeAdmin(admin)"
                                                class="px-2.5 py-1 rounded-lg border border-rose-200 text-rose-700 font-bold text-xs hover:bg-rose-50 transition">
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-xs text-amber-700 font-semibold p-4 rounded-xl bg-amber-50 border border-amber-200">
                        No administrator accounts exist yet for this tenant. Use the form below to create one.
                    </p>
                </div>

                <!-- Create / Update Admin Form & Lookup -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Admin Form -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs space-y-4">
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">
                            {{ adminForm.user_id ? 'Update Admin Account' : 'Create New Portal Admin' }}
                        </h3>

                        <form @submit.prevent="saveAdmin" class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Full Name</label>
                                <input v-model="adminForm.name" type="text" required
                                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition outline-none">
                                <p v-if="adminForm.errors.name" class="text-xs text-rose-500 mt-1">{{ adminForm.errors.name }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
                                <input v-model="adminForm.email" type="email" required autocomplete="off"
                                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition outline-none">
                                <p v-if="adminForm.errors.email" class="text-xs text-rose-500 mt-1">{{ adminForm.errors.email }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">
                                    Login Username <span class="font-normal text-slate-400">(optional)</span>
                                </label>
                                <input v-model="adminForm.username" type="text" autocomplete="off" placeholder="Leave blank to use email"
                                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition outline-none">
                                <p v-if="adminForm.errors.username" class="text-xs text-rose-500 mt-1">{{ adminForm.errors.username }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">
                                    Password
                                </label>
                                <input v-model="adminForm.password" type="text" :required="!adminForm.user_id" autocomplete="off"
                                       placeholder="Enter login password"
                                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-medium focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition outline-none">
                                <p class="text-[11px] text-slate-400 mt-1">
                                    {{ adminForm.user_id ? 'Leave blank to preserve current password.' : 'Stored securely and recoverable by Super Admin.' }}
                                </p>
                                <p v-if="adminForm.errors.password" class="text-xs text-rose-500 mt-1">{{ adminForm.errors.password }}</p>
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit" :disabled="adminForm.processing"
                                        class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition disabled:opacity-50 shadow-xs">
                                    {{ adminForm.user_id ? 'Save Account Changes' : 'Create Admin Account' }}
                                </button>
                                <button v-if="adminForm.user_id" type="button" @click="resetAdminForm"
                                        class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Find Existing Login Lookup -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs space-y-4">
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Account Lookup Tool</h3>
                        <p class="text-xs text-slate-500">
                            Check if an admin or principal email already exists across this platform cluster.
                        </p>

                        <div class="flex gap-2">
                            <input v-model="loginLookupQuery" type="text" placeholder="Search email or username..."
                                   class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium outline-none focus:bg-white">
                            <button type="button" @click="searchLogin"
                                    class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition">
                                Search
                            </button>
                        </div>

                        <div v-if="loginLookup.searched" class="p-4 rounded-xl border text-xs space-y-2"
                             :class="loginLookup.matches.length ? 'bg-emerald-50 border-emerald-200' : 'bg-slate-50 border-slate-200'">
                            <p class="font-bold" :class="loginLookup.matches.length ? 'text-emerald-900' : 'text-slate-700'">
                                {{ loginLookup.matches.length ? `Found ${loginLookup.matches.length} matching account(s)` : 'No existing account matched' }}
                            </p>
                            <div v-for="match in loginLookup.matches" :key="match.id" class="p-3 rounded-lg bg-white border border-slate-200 space-y-1">
                                <p class="font-bold text-slate-900">{{ match.name }}</p>
                                <p class="text-slate-500 font-mono text-[11px]">{{ match.email }}</p>
                                <p class="text-indigo-600 font-semibold text-[11px]">Roles: {{ (match.roles || []).join(', ') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: DATABASE & PERMISSIONS (Sahodaya only) -->
            <div v-if="activeTab === 'database' && tenant.type === 'sahodaya'" class="space-y-6">
                <!-- Database Configuration -->
                <div v-if="database" class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Dedicated Cluster Database</h3>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold border"
                              :class="database.ready ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'">
                            {{ database.ready ? '✓ Database Ready & Connected' : '⚠️ Pending Migrations / Provisioning' }}
                        </span>
                    </div>

                    <form @submit.prevent="saveDatabase" class="space-y-4">
                        <div class="grid sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-3">
                                <label class="block text-xs font-bold text-slate-700 mb-1">PostgreSQL Database Name</label>
                                <input v-model="databaseForm.database_name" type="text" required
                                       :placeholder="database.suggested_name"
                                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono text-xs focus:bg-white">
                                <p class="text-[11px] text-slate-400 mt-1">Suggested naming convention: {{ database.suggested_name }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">DB Username (optional)</label>
                                <input v-model="databaseForm.db_username" type="text"
                                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono text-xs focus:bg-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">DB Password (optional)</label>
                                <input v-model="databaseForm.db_password" type="password"
                                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono text-xs focus:bg-white">
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <button type="submit" :disabled="databaseForm.processing"
                                    class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition">
                                Save Connection Details
                            </button>
                            <button type="button" @click="runMigrations" :disabled="migrateForm.processing || !database.configured"
                                    class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition disabled:opacity-40">
                                {{ migrateForm.processing ? 'Migrating…' : 'Run Migrations' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Navigation & Program Permissions -->
                <div v-if="navManager" class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Sidebar Navigation Permissions</h3>
                    <p class="text-xs text-slate-500">
                        Select which modules and festival programs are unlocked for this Sahodaya and its affiliated schools.
                    </p>

                    <form @submit.prevent="saveNavVisibility" class="space-y-4">
                        <div>
                            <p class="text-xs font-bold text-slate-700 mb-2">Available Menu Sections</p>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                <label v-for="(label, key) in navManager.menus" :key="key"
                                       class="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                    <input type="checkbox" class="rounded text-indigo-600"
                                           :checked="navForm.menus[key] !== false"
                                           @change="navForm.menus[key] = $event.target.checked">
                                    <span class="text-xs font-semibold text-slate-800">{{ label }}</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-bold text-slate-700 mb-2">Festival Programs</p>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                <label v-for="(label, key) in navManager.programs" :key="key"
                                       class="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                    <input type="checkbox" class="rounded text-indigo-600"
                                           :checked="navForm.programs[key] !== false"
                                           @change="navForm.programs[key] = $event.target.checked">
                                    <span class="text-xs font-semibold text-slate-800">{{ label }}</span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition">
                            {{ navForm.processing ? 'Saving…' : 'Save Sidebar Permissions' }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- TAB 4: SUPER ADMIN DANGER ZONE -->
            <div v-if="activeTab === 'danger'" class="space-y-6">
                <!-- Rejection for schools -->
                <div v-if="tenant.type === 'school'" class="bg-white rounded-2xl p-6 border border-rose-200 shadow-xs space-y-4">
                    <div class="flex items-center gap-2 text-rose-700 font-bold text-sm">
                        <span>⛔</span>
                        <span>School Membership Status: {{ tenant.membership_status || 'Pending' }}</span>
                    </div>

                    <form v-if="tenant.membership_status !== 'rejected'" @submit.prevent="rejectSchool" class="space-y-3 max-w-lg">
                        <label class="block text-xs font-bold text-slate-700">Reject Membership (School admin will be notified)</label>
                        <textarea v-model="rejectForm.reason" rows="2" required placeholder="Specify reason for rejection..."
                                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs"></textarea>
                        <button type="submit" :disabled="rejectForm.processing"
                                class="px-4 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold hover:bg-rose-700 transition disabled:opacity-40">
                            Reject School
                        </button>
                    </form>
                </div>

                <!-- Hard student erasure (Schools only) -->
                <div v-if="tenant.type === 'school'" class="bg-white rounded-2xl p-6 border border-rose-200 shadow-xs space-y-4">
                    <h3 class="text-sm font-extrabold text-rose-800 uppercase tracking-wider">Hard Student Data Erasure</h3>
                    <p class="text-xs text-rose-700 leading-relaxed">
                        Permanently purge every student record, mark entry, and event registration for this school.
                        A full snapshot is automatically saved into an Erasure Batch and can be restored at any time.
                    </p>

                    <div class="space-y-3 max-w-lg pt-2">
                        <label class="block text-xs font-bold text-slate-700">
                            Type school name (<strong>{{ tenant.name }}</strong>) to confirm:
                        </label>
                        <input v-model="eraseStudentsForm.confirm_school_name" type="text"
                               placeholder="Confirm school name..."
                               class="w-full px-3 py-2 bg-rose-50/50 border border-rose-200 rounded-xl text-xs font-semibold">

                        <button type="button" @click="eraseStudents"
                                :disabled="eraseStudentsForm.processing || !eraseStudentsConfirmMatches"
                                class="px-4 py-2.5 rounded-xl bg-rose-600 text-white text-xs font-bold hover:bg-rose-700 transition disabled:opacity-40">
                            {{ eraseStudentsForm.processing ? 'Erasing…' : 'Erase All Students (With Recovery Snapshot)' }}
                        </button>
                    </div>

                    <!-- Restoration History -->
                    <div v-if="erasureBatches?.length" class="pt-4 border-t border-slate-100 space-y-2">
                        <p class="text-xs font-bold text-slate-800">Snapshot Recovery History</p>
                        <div v-for="batch in erasureBatches" :key="batch.id"
                             class="p-3 rounded-xl border flex items-center justify-between text-xs"
                             :class="batch.restored_at ? 'bg-slate-50 border-slate-200' : 'bg-rose-50/60 border-rose-200'">
                            <div>
                                <p class="font-bold text-slate-900">{{ batch.student_count }} student(s) erased on {{ formatDateTime(batch.erased_at) }}</p>
                                <p class="text-[11px] text-slate-500 mt-0.5">Erased by: {{ batch.erased_by_name || batch.erased_by_email }}</p>
                            </div>
                            <button v-if="!batch.restored_at" type="button" @click="restoreErasure(batch)"
                                    :disabled="restoringBatchId === batch.id"
                                    class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 transition">
                                {{ restoringBatchId === batch.id ? 'Restoring…' : 'Restore Data' }}
                            </button>
                            <span v-else class="text-emerald-700 font-bold text-xs">✓ Restored</span>
                        </div>
                    </div>
                </div>

                <!-- Permanent Tenant Deletion -->
                <div class="bg-white rounded-2xl p-6 border border-rose-200 shadow-xs space-y-3">
                    <h3 class="text-sm font-extrabold text-rose-800 uppercase tracking-wider">Permanent Tenant Deletion</h3>
                    <p class="text-xs text-slate-600">
                        Permanently purge this {{ tenant.type === 'school' ? 'school' : 'Sahodaya cluster' }}, its domains, and all login credentials.
                        This operation is non-reversible.
                    </p>
                    <button type="button" @click="deleteTenant"
                            class="px-4 py-2.5 rounded-xl border border-rose-300 bg-rose-50 text-rose-700 text-xs font-bold hover:bg-rose-100 transition">
                        Permanently Delete {{ tenant.type === 'school' ? 'School' : 'Sahodaya' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Impersonate Modal -->
        <Modal :show="impersonateModal.open" title="Impersonate Admin" size="sm" @close="impersonateModal.open = false">
            <p class="text-xs text-slate-600 mb-3">
                You are about to securely view this portal as <strong>{{ impersonateModal.admin?.name }}</strong>
                ({{ impersonateModal.admin?.email }}). Every action during this session is logged to platform audit trails.
            </p>
            <textarea v-model="impersonateForm.reason" rows="3" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium outline-none"
                      placeholder="Audit reason (e.g. diagnosing school verification ticket)..."></textarea>
            <p v-if="impersonateForm.errors.reason" class="text-xs text-rose-600 mt-1">{{ impersonateForm.errors.reason }}</p>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="impersonateModal.open = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">Cancel</button>
                    <button type="button" @click="confirmImpersonate" :disabled="impersonateForm.processing"
                            class="px-4 py-2 rounded-xl bg-amber-600 text-white text-xs font-bold hover:bg-amber-700 transition">
                        {{ impersonateForm.processing ? 'Connecting…' : 'Start Session' }}
                    </button>
                </div>
            </template>
        </Modal>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Modal from '@/Components/ui/Modal.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    tenant: Object,
    tenantBaseDomain: { type: String, default: 'sahodaya.test' },
    publicUrl: { type: String, default: null },
    subdomainUrl: { type: String, default: null },
    logoUrl: { type: String, default: null },
    listUrl: { type: String, default: '/admin/sahodayas' },
    database: { type: Object, default: null },
    tenantOverview: { type: Object, default: () => ({ sections: [], settings: [] }) },
    sahodayaAdmins: { type: Array, default: () => [] },
    schoolAdmins: { type: Array, default: () => [] },
    loginUrl: { type: String, default: null },
    loginLookup: { type: Object, default: () => ({ query: '', matches: [], searched: false, message: null }) },
    navManager: { type: Object, default: null },
    erasureBatches: { type: Array, default: () => [] },
    setupChecklist: { type: Object, default: null },
});

const activeTab = ref('overview');

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
    props.tenant.type === 'school' ? 'School Portal Administrators' : 'Sahodaya Cluster Administrators'
);

const portalAdminHint = computed(() =>
    props.tenant.type === 'school'
        ? 'School admins authenticate on their parent Sahodaya portal to submit registrations, upload documents, and view scores.'
        : 'Sahodaya cluster admins sign into this cluster\'s administration hub.'
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
    username: '',
    password: '',
});
const loginLookupQuery = ref(props.loginLookup?.query ?? '');
const rejectForm = useForm({ reason: '' });
const eraseStudentsForm = useForm({ confirm_school_name: '' });

const eraseStudentsConfirmMatches = computed(() =>
    eraseStudentsForm.confirm_school_name.trim().toLowerCase() === (props.tenant?.name ?? '').trim().toLowerCase(),
);

async function eraseStudents() {
    if (!eraseStudentsConfirmMatches.value) return;
    if (!(await confirm({ message: `Permanently erase EVERY student record for "${props.tenant.name}"? This will be snapshotted and can be restored from the Danger Zone.`, destructive: true }))) {
        return;
    }

    eraseStudentsForm.delete(`/admin/tenants/${props.tenant.id}/erase-students`, {
        preserveScroll: true,
        onSuccess: () => eraseStudentsForm.reset(),
    });
}

const restoringBatchId = ref(null);

function formatDateTime(value) {
    if (!value) return '';
    return new Date(value).toLocaleString();
}

async function restoreErasure(batch) {
    if (!(await confirm({ message: `Restore ${batch.student_count} erased student record(s) for "${props.tenant.name}"?`, destructive: false }))) {
        return;
    }

    restoringBatchId.value = batch.id;
    router.post(`/admin/tenants/${props.tenant.id}/erasure-batches/${batch.id}/restore`, {}, {
        preserveScroll: true,
        onFinish: () => { restoringBatchId.value = null; },
    });
}

async function rejectSchool() {
    if (!(await confirm({ message: `Reject "${props.tenant.name}"? The school admin will be notified by email.`, destructive: true }))) {
        return;
    }

    rejectForm.post(`/admin/tenants/${props.tenant.id}/reject-membership`, {
        onSuccess: () => rejectForm.reset(),
    });
}

async function deleteTenant() {
    if (!(await confirm({ message: `Permanently delete "${props.tenant.name}" and all associated credentials? This cannot be undone.`, destructive: true }))) {
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
    adminForm.username = admin.username || '';
    adminForm.password = '';
    adminForm.clearErrors();
    activeTab.value = 'admins';
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

function searchLogin() {
    router.get(`/admin/tenants/${props.tenant.id}`, {
        login_lookup: loginLookupQuery.value.trim(),
    }, {
        preserveScroll: true,
        preserveState: true,
    });
}

const revealedPasswords = ref({});
const revealingId = ref(null);

async function revealPassword(admin) {
    if (revealedPasswords.value[admin.id]) {
        delete revealedPasswords.value[admin.id];
        return;
    }

    revealingId.value = admin.id;
    try {
        const response = await fetch(`/admin/tenants/${props.tenant.id}/portal-admin/${admin.id}/reveal-password`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data = await response.json();
        revealedPasswords.value = { ...revealedPasswords.value, [admin.id]: data.password || '(not stored)' };
    } finally {
        revealingId.value = null;
    }
}

const impersonateModal = reactive({ open: false, admin: null });
const impersonateForm = useForm({ reason: '' });

function openImpersonate(admin) {
    impersonateModal.admin = admin;
    impersonateModal.open = true;
    impersonateForm.reset();
    impersonateForm.clearErrors();
}

function confirmImpersonate() {
    impersonateForm.post(`/admin/tenants/${props.tenant.id}/impersonate/${impersonateModal.admin.id}`, {
        onSuccess: () => { impersonateModal.open = false; },
    });
}

async function removeAdmin(admin) {
    const label = props.tenant.type === 'school' ? 'school admin' : 'Sahodaya admin';
    if (!(await confirm({ message: `Remove ${label} ${admin.email}?`, destructive: true }))) {
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

function copyText(txt) {
    if (!txt) return;
    navigator.clipboard?.writeText(txt);
}
</script>
