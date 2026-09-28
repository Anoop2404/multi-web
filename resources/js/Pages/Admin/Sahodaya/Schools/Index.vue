<template>
    <SahodayaAdminLayout title="Member Schools" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="approvedSchoolsCount"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header section -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white rounded-2xl p-6 border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)]">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
                        <span>Cluster Membership</span>
                        <span>•</span>
                        <span class="text-[#0f3d7a] font-bold">{{ activeAcademicYear || 'Current Year' }}</span>
                    </div>
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-3">
                        <span>Member Schools</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-[#0f3d7a] border border-blue-100">
                            {{ verifiedCount }} verified
                        </span>
                    </h1>
                    <p class="text-sm text-slate-500 mt-1 max-w-2xl">
                        Manage affiliated schools, verify membership dues, issue portal admin credentials, and inspect student counts.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <Link v-if="pendingSchoolsCount > 0"
                          :href="`/sahodaya-admin/${sahodaya.id}/schools/applications`"
                          class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-amber-500 text-xs font-bold text-white hover:bg-amber-600 transition shadow-xs">
                        <span>📥 Review {{ pendingSchoolsCount }} Pending Application{{ pendingSchoolsCount === 1 ? '' : 's' }}</span>
                        <span>→</span>
                    </Link>

                    <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/code-assignment`"
                          class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition shadow-xs"
                          title="Auto assign, renumber, and manage permanent school codes">
                        <span>⚡ Assign Codes</span>
                    </Link>

                    <a :href="exportUrl()"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-blue-200 bg-blue-50/60 text-xs font-bold text-[#0f3d7a] hover:bg-blue-100 transition shadow-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Export Excel</span>
                    </a>
                </div>
            </div>

            <!-- Summary metrics cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <DashboardStatCard label="Verified Schools" :value="verifiedCount" icon="🏫" tone="navy" />
                <DashboardStatCard
                    label="Pending Applications"
                    :value="pendingSchoolsCount ?? 0"
                    icon="📥"
                    tone="amber"
                    :hint="pendingSchoolsCount > 0 ? 'Review applications →' : null"
                    :href="pendingSchoolsCount > 0 ? `/sahodaya-admin/${sahodaya.id}/schools/applications` : null"
                />
                <DashboardStatCard label="Active Students" :value="summary?.total_students ?? 0" icon="👨‍🎓" tone="green" />
                <DashboardStatCard label="Curriculum Classes" :value="summary?.total_classes ?? 0" icon="📚" tone="indigo" />
            </div>

            <!-- Bulk actions toolbar -->
            <div v-if="selectedIds.length"
                 class="flex flex-wrap items-center gap-2 rounded-2xl border border-blue-200 bg-blue-50/80 px-5 py-3 shadow-xs">
                <span class="text-xs font-bold text-[#0f3d7a] pr-2 border-r border-blue-200">
                    {{ selectedIds.length }} school{{ selectedIds.length === 1 ? '' : 's' }} selected
                </span>
                <button type="button" class="btn-primary text-xs !py-1.5" :disabled="bulkForm.processing" @click="bulkCreateLogin">
                    🔑 Create login & send credentials
                </button>
                <button type="button" class="btn-secondary text-xs !py-1.5" :disabled="bulkForm.processing" @click="bulkResetPassword">
                    Reset password
                </button>
                <button type="button" class="btn-secondary text-xs !py-1.5" :disabled="bulkForm.processing" @click="bulkSendCredentials">
                    Resend credentials
                </button>
                <button type="button" class="text-xs font-bold text-slate-500 hover:text-slate-700 ml-auto" @click="clearSelection">
                    Clear selection ✕
                </button>
            </div>

            <!-- Quick Payment & Login Status Filter Pills -->
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="setPaymentStatusFilter('all')"
                        :class="['px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer',
                                 (filterForm.payment_status || 'all') === 'all' && (filterForm.login_status || 'all') === 'all' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50']">
                    All Members ({{ verifiedCount }})
                </button>

                <button type="button" @click="setPaymentStatusFilter('no_proof')"
                        :class="['px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1.5 border',
                                 filterForm.payment_status === 'no_proof' || filterForm.payment_status === 'payment_not_done' ? 'bg-rose-700 text-white border-rose-700 shadow-xs' : 'bg-rose-50/70 text-rose-800 border-rose-200 hover:bg-rose-100']">
                    <span>⚠️ Fee Due / No Proof</span>
                </button>

                <button type="button" @click="setPaymentStatusFilter('payment_pending')"
                        :class="['px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1.5 border',
                                 filterForm.payment_status === 'payment_pending' ? 'bg-amber-600 text-white border-amber-600 shadow-xs' : 'bg-amber-50/70 text-amber-800 border-amber-200 hover:bg-amber-100']">
                    <span>⏳ Payment Uploaded (Review)</span>
                </button>

                <button type="button" @click="setPaymentStatusFilter('payment_verified')"
                        :class="['px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1.5 border',
                                 filterForm.payment_status === 'payment_verified' ? 'bg-emerald-700 text-white border-emerald-700 shadow-xs' : 'bg-emerald-50/70 text-emerald-800 border-emerald-200 hover:bg-emerald-100']">
                    <span>✓ Fee Cleared</span>
                </button>

                <button type="button" @click="setLoginStatusFilter(filterForm.login_status === 'no_login' ? 'all' : 'no_login')"
                        :class="['px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1.5 border ml-auto',
                                 filterForm.login_status === 'no_login' ? 'bg-purple-700 text-white border-purple-700 shadow-xs' : 'bg-purple-50/70 text-purple-900 border-purple-200 hover:bg-purple-100']">
                    <span>🔑 Schools Without Login</span>
                </button>
            </div>

            <!-- Filters Bar & View Switcher -->
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="relative flex-1 min-w-[240px] max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input v-model="filterForm.search" type="search" placeholder="Search school by name, code, email..."
                               class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400 transition outline-none">
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- View mode toggle -->
                        <div class="flex items-center p-1 bg-slate-100 rounded-xl border border-slate-200/60">
                            <button type="button" @click="viewMode = 'table'"
                                    :class="['p-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1',
                                             viewMode === 'table' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800']"
                                    title="Table view">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                <span class="hidden sm:inline">Table</span>
                            </button>
                            <button type="button" @click="viewMode = 'grid'"
                                    :class="['p-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1',
                                             viewMode === 'grid' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800']"
                                    title="Grid card view">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                <span class="hidden sm:inline">Cards</span>
                            </button>
                        </div>

                        <button v-if="hasActiveFilters" @click="clearFilters"
                                class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                            Reset filters ✕
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-1 text-xs">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Fee Status</label>
                        <select v-model="filterForm.payment_status"
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-medium outline-none">
                            <option value="all">All Fee Statuses</option>
                            <option value="no_proof">⚠️ Fee Due / No Proof</option>
                            <option value="payment_pending">⏳ Payment Uploaded (Review)</option>
                            <option value="payment_verified">✓ Fee Verified</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Sort Order</label>
                        <select :value="`${filters?.sort ?? 'name'}-${filters?.dir ?? 'asc'}`" @change="applySort($event.target.value)"
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-medium outline-none">
                            <option value="name-asc">Name A–Z</option>
                            <option value="name-desc">Name Z–A</option>
                            <option value="created_at-desc">Newest First</option>
                            <option value="created_at-asc">Oldest First</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">From Date</label>
                        <input v-model="filterForm.date_from" type="date"
                               class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-medium outline-none">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">To Date</label>
                        <input v-model="filterForm.date_to" type="date"
                               class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-medium outline-none">
                    </div>
                </div>
            </div>

            <!-- TABLE VIEW -->
            <div v-if="viewMode === 'table' && schools.data?.length"
                 class="bg-white rounded-2xl border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs min-w-[950px]">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                <th class="w-10 px-4 py-3.5 text-center">
                                    <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-slate-300">
                                </th>
                                <th class="px-4 py-3.5">School Identity</th>
                                <th class="px-4 py-3.5">Affiliation & Contact</th>
                                <th class="px-4 py-3.5">Fee Clearance</th>
                                <th class="px-4 py-3.5">Portal Admin Account</th>
                                <th class="px-4 py-3.5 text-center">Students / Classes</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="school in schools.data" :key="school.id"
                                class="hover:bg-blue-50/20 transition-colors group">
                                <td class="px-4 py-4 text-center">
                                    <input type="checkbox" :value="school.id" v-model="selectedIds" class="rounded border-slate-300">
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl flex items-center justify-center font-black text-xs text-white shrink-0 shadow-xs"
                                             :style="{ backgroundColor: hashColor(school.name) }">
                                            {{ schoolInitials(school.name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}`"
                                                  class="font-bold text-slate-900 hover:text-[#0f3d7a] transition text-sm">
                                                {{ school.name }}
                                            </Link>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span v-if="school.school_prefix"
                                                      class="px-1.5 py-0.2 rounded font-mono text-[10px] font-bold bg-slate-100 text-slate-700">
                                                    {{ school.school_prefix }}
                                                </span>
                                                <span v-if="school.is_non_affiliated"
                                                      class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                                    Non-affiliated
                                                </span>
                                                <span v-else
                                                      class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                    Affiliated
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4 space-y-0.5">
                                    <p v-if="school.contact_email" class="font-medium text-slate-700 truncate max-w-[200px]">
                                        ✉ {{ school.contact_email }}
                                    </p>
                                    <p v-if="school.contact_phone" class="text-slate-500 font-mono text-[11px]">
                                        📞 {{ school.contact_phone }}
                                    </p>
                                    <p v-if="school.affiliation" class="text-slate-400 font-mono text-[10px]">
                                        CBSE Aff: {{ school.affiliation }}
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    <span v-if="school.payment_status === 'payment_verified'"
                                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        ✓ Fee Cleared
                                    </span>
                                    <span v-else-if="school.payment_status === 'payment_pending'"
                                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                        ⏳ Review Uploaded
                                    </span>
                                    <span v-else
                                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-800 border border-rose-200">
                                        ⚠️ Fee Due
                                    </span>
                                </td>

                                <td class="px-4 py-4">
                                    <div v-if="school.has_login" class="flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <span class="font-medium text-slate-700 truncate max-w-[160px]">{{ school.login_email || 'Configured' }}</span>
                                    </div>
                                    <button v-else type="button" @click="createLoginForSchool(school)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-900 border border-amber-300 hover:bg-amber-100 transition">
                                        🔑 Create Login
                                    </button>
                                </td>

                                <td class="px-4 py-4 text-center">
                                    <span class="font-bold text-slate-800">{{ school.student_count ?? 0 }}</span>
                                    <span class="text-slate-400 text-[11px]"> / {{ school.classes_count ?? 0 }} classes</span>
                                </td>

                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}`"
                                          class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-50 text-[#0f3d7a] hover:bg-blue-100 transition shadow-2xs">
                                        <span>Manage</span>
                                        <span>→</span>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- GRID CARDS VIEW -->
            <div v-else-if="schools.data?.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="school in schools.data" :key="school.id"
                     class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] hover:shadow-md hover:border-blue-300 transition-all duration-200 flex flex-col justify-between relative group">
                    <input type="checkbox" :value="school.id" v-model="selectedIds"
                           class="absolute top-4 right-4 z-10 rounded border-slate-300 shadow-xs cursor-pointer">

                    <div>
                        <div class="flex items-start gap-3.5 pr-8">
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center font-black text-sm text-white shrink-0 shadow-xs"
                                 :style="{ backgroundColor: hashColor(school.name) }">
                                {{ schoolInitials(school.name) }}
                            </div>
                            <div class="min-w-0">
                                <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}`"
                                      class="font-bold text-slate-900 group-hover:text-[#0f3d7a] transition text-sm line-clamp-1">
                                    {{ school.name }}
                                </Link>
                                <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                    <span v-if="school.school_prefix"
                                          class="px-1.5 py-0.2 rounded font-mono text-[10px] font-bold bg-slate-100 text-slate-700">
                                        {{ school.school_prefix }}
                                    </span>
                                    <span v-if="school.payment_status === 'payment_verified'"
                                          class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        ✓ Fee Cleared
                                    </span>
                                    <span v-else-if="school.payment_status === 'payment_pending'"
                                          class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                        ⏳ Review Uploaded
                                    </span>
                                    <span v-else
                                          class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-rose-50 text-rose-800 border border-rose-200">
                                        ⚠️ Fee Due
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Contact details -->
                        <div class="mt-4 pt-3 border-t border-slate-100 space-y-1 text-xs text-slate-500">
                            <p v-if="school.contact_email" class="truncate font-medium text-slate-700">✉ {{ school.contact_email }}</p>
                            <p v-if="school.contact_phone">📞 {{ school.contact_phone }}</p>
                            <p v-if="school.affiliation" class="font-mono text-[11px] text-slate-400">CBSE Aff: {{ school.affiliation }}</p>
                        </div>
                    </div>

                    <!-- Footer actions -->
                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                        <div class="text-slate-500 font-semibold">
                            <span class="text-slate-900 font-extrabold">{{ school.student_count ?? 0 }}</span> students
                            <span class="text-slate-400 font-normal">({{ school.classes_count ?? 0 }} cls)</span>
                        </div>

                        <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}`"
                              class="inline-flex items-center gap-1 font-bold text-[#0f3d7a] hover:underline">
                            <span>Manage</span>
                            <span>→</span>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <EmptyState v-else title="No member schools match your criteria" description="Try adjusting your search query, fee status pill, or date filters." icon="🏫">
                <template v-if="pendingSchoolsCount > 0" #action>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/applications`" class="btn-primary text-xs">
                        Review {{ pendingSchoolsCount }} Pending Application{{ pendingSchoolsCount === 1 ? '' : 's' }} →
                    </Link>
                </template>
            </EmptyState>

            <!-- Pagination -->
            <div v-if="schools.data?.length && schools.links?.length > 3"
                 class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200/80 bg-white px-5 py-3.5 text-xs shadow-xs">
                <p class="text-slate-500 font-medium">
                    Showing <span class="font-bold text-slate-800">{{ schools.from }}–{{ schools.to }}</span>
                    of <span class="font-bold text-slate-800">{{ schools.total }}</span> schools
                </p>
                <nav class="flex flex-wrap gap-1">
                    <Link v-for="link in schools.links" :key="link.label"
                          :href="link.url ?? '#'"
                          class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
                          :class="[
                              link.active ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 border border-slate-200',
                              !link.url && 'pointer-events-none opacity-40',
                          ]"
                          v-html="link.label" />
                </nav>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import DashboardStatCard from '@/Components/ui/DashboardStatCard.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { reactive, computed, ref } from 'vue';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object, publicUrl: String,
    approvedSchoolsCount: Number, pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number, pendingPaymentsCount: Number,
    schools: Object, filters: Object,
    verifiedCount: { type: Number, default: 0 },
    activeAcademicYear: { type: String, default: null },
    summary: { type: Object, default: () => ({}) },
});

const { confirm, prompt } = useConfirm();
const selectedIds = ref([]);
const bulkForm = useForm({ school_ids: [] });
const viewMode = ref('table');

const allSelected = computed(() => {
    const ids = (props.schools.data ?? []).map((s) => s.id);
    return ids.length > 0 && ids.every((id) => selectedIds.value.includes(id));
});

function toggleSelectAll(event) {
    const ids = (props.schools.data ?? []).map((s) => s.id);
    selectedIds.value = event.target.checked ? ids : [];
}

function clearSelection() {
    selectedIds.value = [];
}

async function createLoginForSchool(school) {
    let email = (school.contact_email || '').trim();
    if (!email) {
        email = await prompt({ message: `Enter portal login email address for "${school.name}":`, inputPlaceholder: 'school@example.com' });
        if (!email?.trim()) return;
        email = email.trim();
    } else {
        if (!(await confirm({ message: `Create portal login for "${school.name}" using email ${email}? Credentials will be sent by email.`, destructive: false }))) return;
    }

    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${school.id}/create-login`, {
        email: email,
    }, { preserveScroll: true });
}

async function bulkCreateLogin() {
    if (!selectedIds.value.length) return;
    if (!(await confirm({ message: `Create portal logins and email login details for ${selectedIds.value.length} selected school(s)?`, destructive: false }))) return;
    bulkForm.school_ids = [...selectedIds.value];
    bulkForm.post(`/sahodaya-admin/${props.sahodaya.id}/schools/bulk-create-login`, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; bulkForm.reset(); },
    });
}

async function bulkResetPassword() {
    if (!selectedIds.value.length) return;
    if (!(await confirm({ message: `Reset the password for ${selectedIds.value.length} school(s)? New temporary passwords will be emailed.` }))) return;
    bulkForm.school_ids = [...selectedIds.value];
    bulkForm.post(`/sahodaya-admin/${props.sahodaya.id}/schools/bulk-reset-password`, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; bulkForm.reset(); },
    });
}

async function bulkSendCredentials() {
    if (!selectedIds.value.length) return;
    if (!(await confirm({ message: `Resend current credentials to ${selectedIds.value.length} school(s)?`, destructive: false }))) return;
    bulkForm.school_ids = [...selectedIds.value];
    bulkForm.post(`/sahodaya-admin/${props.sahodaya.id}/schools/bulk-send-credentials`, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; bulkForm.reset(); },
    });
}

const filterForm = reactive({
    search:         props.filters?.search ?? '',
    date_from:      props.filters?.date_from ?? '',
    date_to:        props.filters?.date_to ?? '',
    payment_status: props.filters?.payment_status ?? 'all',
    login_status:   props.filters?.login_status ?? 'all',
});

function applySort(value) {
    const [sort, dir] = value.split('-');
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({ sort, dir }), {
        preserveState: true, replace: true,
    });
}

const hasActiveFilters = computed(() =>
    filterForm.search || filterForm.date_from || filterForm.date_to || (filterForm.payment_status && filterForm.payment_status !== 'all') || (filterForm.login_status && filterForm.login_status !== 'all')
);

function listParams(overrides = {}) {
    return {
        search:         props.filters?.search ?? '',
        date_from:      props.filters?.date_from ?? '',
        date_to:        props.filters?.date_to ?? '',
        payment_status: props.filters?.payment_status ?? 'all',
        login_status:   props.filters?.login_status ?? 'all',
        sort:           props.filters?.sort ?? 'name',
        dir:            props.filters?.dir ?? 'asc',
        ...overrides,
    };
}

function setPaymentStatusFilter(status) {
    filterForm.payment_status = status;
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({ payment_status: status }), {
        preserveState: true, replace: true,
    });
}

function setLoginStatusFilter(status) {
    filterForm.login_status = status;
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({ login_status: status }), {
        preserveState: true, replace: true,
    });
}

function applyFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({
        search:         filterForm.search,
        date_from:      filterForm.date_from,
        date_to:        filterForm.date_to,
        payment_status: filterForm.payment_status,
        login_status:   filterForm.login_status,
    }), { preserveState: true, replace: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function clearFilters() {
    filterForm.search = '';
    filterForm.date_from = '';
    filterForm.date_to = '';
    filterForm.payment_status = 'all';
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({
        search: '', date_from: '', date_to: '', payment_status: 'all',
    }), { preserveState: true, replace: true });
}

function exportUrl() {
    const params = new URLSearchParams();
    const p = listParams({
        search: filterForm.search,
        date_from: filterForm.date_from,
        date_to: filterForm.date_to,
    });
    Object.entries(p).forEach(([key, value]) => {
        if (value) params.set(key, value);
    });
    const qs = params.toString();
    return `/sahodaya-admin/${props.sahodaya.id}/schools/export${qs ? `?${qs}` : ''}`;
}

function schoolInitials(name) {
    if (!name) return '?';
    const parts = String(name).trim().split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return parts[0].slice(0, 2).toUpperCase();
}

function hashColor(str) {
    if (!str) return '#0f3d7a';
    const palette = ['#0284c7', '#0d9488', '#16a34a', '#d97706', '#dc2626', '#7c3aed', '#0f3d7a', '#db2777', '#475569'];
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    return palette[Math.abs(hash) % palette.length];
}
</script>
