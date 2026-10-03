<template>
    <SahodayaEventsLayout :title="`${event.title} — Food Coupons`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Coupon Generator`" eyebrow="Operations"
                    :description="isPartitionedHub
                        ? 'Coupons are issued per region — pick a region below.'
                        : 'Generate, ungenerate, manage extra buffer coupons, and print 12 or 10 coupons per A4 sheet with QR codes.'" />

        <EventHierarchyBadge :hierarchy="hierarchy" :hub-href="hubHref" />

        <FoodRegionDrillDown v-if="isPartitionedHub" :sahodaya-id="sahodaya.id" :regions="foodRegionSummary"
                              target-path="food-coupons" class="mb-6" />

        <template v-if="!isPartitionedHub">
            <!-- Action Toolbar -->
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/catering`" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 mr-1">
                        ← Catering
                    </Link>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-billing`" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 mr-2">
                        Food Billing →
                    </Link>
                    <template v-if="event.require_payment_for_coupons">
                        <button type="button" @click="issueFromBill" class="btn-primary flex items-center gap-1.5 shadow-sm" :disabled="issuingBill">
                            <span v-if="issuingBill">Issuing...</span>
                            <span v-else>🍽️ Issue from Food Bills (Settled Orders)</span>
                        </button>
                    </template>
                    <template v-else>
                        <button type="button" @click="issueCoupons" class="btn-primary flex items-center gap-1.5" :disabled="issuingCatering">
                            <span v-if="issuingCatering">Issuing...</span>
                            <span v-else>Issue from Catering</span>
                        </button>
                        <button type="button" @click="issueFromBill" class="btn-secondary flex items-center gap-1.5" :disabled="issuingBill">
                            <span v-if="issuingBill">Issuing...</span>
                            <span v-else>Issue from Food Bills</span>
                        </button>
                    </template>
                    <button type="button" @click="showExtraModal = true" class="px-3 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 transition flex items-center gap-1.5 shadow-sm">
                        <span>+</span> Generate Extra Coupons
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="showUngenerateModal = true" class="px-3 py-2 border border-rose-200 bg-rose-50 text-rose-700 rounded-lg text-sm font-semibold hover:bg-rose-100 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        Ungenerate Coupons
                    </button>
                    <button type="button" @click="showPreviewModal = true" class="px-3 py-2 border border-indigo-200 bg-indigo-50 text-indigo-700 rounded-lg text-sm font-semibold hover:bg-indigo-100 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        Preview Card
                    </button>
                    <a
                        :href="`${base}/food-coupons/print?preview=1`"
                        target="_blank"
                        class="px-3 py-2 border border-sky-200 bg-sky-50 text-sky-700 rounded-lg text-sm font-semibold hover:bg-sky-100 transition flex items-center gap-1.5"
                        title="Preview generated PDF sheets via high-fidelity converter"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Preview PDF Sheets
                    </a>
                    <button type="button" @click="showBgModal = true" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        Template Background
                        <span v-if="event.has_template_bg" class="w-2 h-2 rounded-full bg-emerald-500" title="Template active"></span>
                    </button>
                    <button type="button" @click="showPrintModal = true" class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        Print PDF Sheets
                    </button>
                </div>
            </div>

            <!-- Stat Tiles -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-9 gap-2.5 mb-6">
                <div class="stat-tile text-center p-3 bg-white rounded-xl border border-slate-200">
                    <p class="text-2xl font-bold text-slate-800">{{ summary.total || 0 }}</p>
                    <p class="text-xs font-medium text-slate-500 mt-1">Total</p>
                </div>
                <div class="stat-tile text-center p-3 bg-white rounded-xl border border-slate-200">
                    <p class="text-2xl font-bold text-blue-600">{{ summary.issued || 0 }}</p>
                    <p class="text-xs font-medium text-slate-500 mt-1">Ready</p>
                </div>
                <div class="stat-tile text-center p-3 bg-white rounded-xl border border-slate-200">
                    <p class="text-2xl font-bold text-emerald-600">{{ summary.redeemed || 0 }}</p>
                    <p class="text-xs font-medium text-slate-500 mt-1">Redeemed</p>
                </div>
                <div class="stat-tile text-center p-3 bg-white rounded-xl border border-slate-200">
                    <p class="text-2xl font-bold text-amber-600">{{ summary.extra || 0 }}</p>
                    <p class="text-xs font-medium text-slate-500 mt-1">Extra</p>
                </div>
                <div class="stat-tile text-center p-3 bg-amber-50/60 rounded-xl border border-amber-200">
                    <p class="text-2xl font-bold text-amber-800">{{ summary.breakfast || 0 }}</p>
                    <p class="text-xs font-medium text-amber-700 mt-1">Breakfast</p>
                </div>
                <div class="stat-tile text-center p-3 bg-emerald-50/60 rounded-xl border border-emerald-200">
                    <p class="text-2xl font-bold text-emerald-800">{{ summary.lunch || 0 }}</p>
                    <p class="text-xs font-medium text-emerald-700 mt-1">Lunch</p>
                </div>
                <div class="stat-tile text-center p-3 bg-indigo-50/60 rounded-xl border border-indigo-200">
                    <p class="text-2xl font-bold text-indigo-800">{{ summary.dinner || 0 }}</p>
                    <p class="text-xs font-medium text-indigo-700 mt-1">Dinner</p>
                </div>
                <div class="stat-tile text-center p-3 bg-pink-50/60 rounded-xl border border-pink-200">
                    <p class="text-2xl font-bold text-pink-800">{{ summary.snacks || 0 }}</p>
                    <p class="text-xs font-medium text-pink-700 mt-1">Snacks</p>
                </div>
                <div class="stat-tile text-center p-3 bg-orange-50/60 rounded-xl border border-orange-200">
                    <p class="text-2xl font-bold text-orange-800">{{ summary.tea || 0 }}</p>
                    <p class="text-xs font-medium text-orange-700 mt-1">Tea</p>
                </div>
            </div>

            <!-- Main View Navigation Tabs -->
            <div class="flex items-center gap-2 border-b border-slate-200 mb-5 pb-1 overflow-x-auto">
                <button
                    type="button"
                    @click="activeTab = 'schools'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition shrink-0"
                    :class="activeTab === 'schools' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    <span>School-wise Distribution & Download</span>
                    <span
                        class="px-2 py-0.5 text-xs rounded-full font-bold"
                        :class="activeTab === 'schools' ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-700'"
                    >
                        {{ schoolBreakdown.length }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'dates'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition shrink-0"
                    :class="activeTab === 'dates' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>Daily Downloads (Per Day)</span>
                    <span
                        class="px-2 py-0.5 text-xs rounded-full font-bold"
                        :class="activeTab === 'dates' ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-700'"
                    >
                        {{ dateBreakdown.length }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'coupons'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition shrink-0"
                    :class="activeTab === 'coupons' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                    </svg>
                    <span>All Individual Coupons & Verification</span>
                    <span
                        class="px-2 py-0.5 text-xs rounded-full font-bold"
                        :class="activeTab === 'coupons' ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-700'"
                    >
                        {{ summary.total || coupons.length }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'builder'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition shrink-0"
                    :class="activeTab === 'builder' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span>Coupon Template Builder</span>
                    <span
                        v-if="event.has_template_bg"
                        class="px-2 py-0.5 text-[10px] rounded-full font-bold uppercase tracking-wide"
                        :class="activeTab === 'builder' ? 'bg-emerald-500 text-white' : 'bg-emerald-100 text-emerald-800'"
                    >
                        Custom BG
                    </span>
                </button>
            </div>

            <!-- Tab 1: School-wise Distribution & Download -->
            <div v-show="activeTab === 'schools'" class="space-y-4">
                <!-- Sub-filter bar -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3 flex-1 min-w-[280px]">
                        <div class="relative flex-1 max-w-sm">
                            <input
                                v-model="schoolSearch"
                                type="text"
                                placeholder="Search school name..."
                                class="w-full text-xs rounded-lg border-slate-300 pl-8 py-2 pr-3"
                            >
                            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>

                        <div class="w-48">
                            <select v-model="schoolDateFilter" class="w-full text-xs rounded-lg border-slate-300 py-2 px-2">
                                <option value="">All Event Dates (Total)</option>
                                <option v-for="d in eventDates" :key="d" :value="d">{{ formatCalendarDate(d) }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="text-xs text-slate-500">
                        Showing <strong>{{ filteredSchoolBreakdown.length }}</strong> of {{ schoolBreakdown.length }} schools
                    </div>
                </div>

                <!-- Schools Table -->
                <div class="card card--flush bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="data-table w-full text-left">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                                    <th class="py-3 px-3 w-12 text-slate-400">#</th>
                                    <th class="py-3 px-3">School / Recipient Name</th>
                                    <th class="py-3 px-2 text-center text-amber-800 bg-amber-50/50">Breakfast (BF)</th>
                                    <th class="py-3 px-2 text-center text-emerald-800 bg-emerald-50/50">Lunch (LN)</th>
                                    <th class="py-3 px-2 text-center text-indigo-800 bg-indigo-50/50">Dinner (DN)</th>
                                    <th class="py-3 px-2 text-center text-pink-800 bg-pink-50/50">Snacks (SN)</th>
                                    <th class="py-3 px-2 text-center text-orange-800 bg-orange-50/50">Tea (TE)</th>
                                    <th class="py-3 px-2 text-center font-bold text-slate-900 bg-slate-100/60">Total Coupons</th>
                                    <th class="py-3 px-3 text-center">Dates Active</th>
                                    <th class="py-3 px-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                <tr v-for="(s, idx) in filteredSchoolBreakdown" :key="s.school_id || 'extra'" class="hover:bg-slate-50/70 transition">
                                    <td class="py-3 px-3 text-slate-400">{{ idx + 1 }}</td>
                                    <td class="py-3 px-3">
                                        <div class="font-semibold text-slate-900 flex items-center gap-1.5">
                                            <span>{{ s.school_name }}</span>
                                            <span v-if="s.is_extra" class="text-[10px] font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">Extra Buffer</span>
                                        </div>
                                        <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-2">
                                            <span class="text-emerald-600 font-medium">{{ getSchoolIssuedCount(s) }} Ready</span>
                                            <span v-if="getSchoolRedeemedCount(s)" class="text-slate-500">• {{ getSchoolRedeemedCount(s) }} Redeemed</span>
                                        </div>
                                    </td>

                                    <td class="py-3 px-2 text-center">
                                        <span v-if="getSchoolMealCount(s, 'breakfast')" class="inline-block px-2 py-0.5 rounded font-bold text-amber-800 bg-amber-100 border border-amber-200">
                                            {{ getSchoolMealCount(s, 'breakfast') }}
                                        </span>
                                        <span v-else class="text-slate-300">-</span>
                                    </td>

                                    <td class="py-3 px-2 text-center">
                                        <span v-if="getSchoolMealCount(s, 'lunch')" class="inline-block px-2 py-0.5 rounded font-bold text-emerald-800 bg-emerald-100 border border-emerald-200">
                                            {{ getSchoolMealCount(s, 'lunch') }}
                                        </span>
                                        <span v-else class="text-slate-300">-</span>
                                    </td>

                                    <td class="py-3 px-2 text-center">
                                        <span v-if="getSchoolMealCount(s, 'dinner')" class="inline-block px-2 py-0.5 rounded font-bold text-indigo-800 bg-indigo-100 border border-indigo-200">
                                            {{ getSchoolMealCount(s, 'dinner') }}
                                        </span>
                                        <span v-else class="text-slate-300">-</span>
                                    </td>

                                    <td class="py-3 px-2 text-center">
                                        <span v-if="getSchoolMealCount(s, 'snacks')" class="inline-block px-2 py-0.5 rounded font-bold text-pink-800 bg-pink-100 border border-pink-200">
                                            {{ getSchoolMealCount(s, 'snacks') }}
                                        </span>
                                        <span v-else class="text-slate-300">-</span>
                                    </td>

                                    <td class="py-3 px-2 text-center">
                                        <span v-if="getSchoolMealCount(s, 'tea')" class="inline-block px-2 py-0.5 rounded font-bold text-orange-800 bg-orange-100 border border-orange-200">
                                            {{ getSchoolMealCount(s, 'tea') }}
                                        </span>
                                        <span v-else class="text-slate-300">-</span>
                                    </td>

                                    <td class="py-3 px-2 text-center bg-slate-50/50">
                                        <span class="inline-block px-2.5 py-0.5 rounded-full font-bold text-sm text-slate-900 bg-slate-200">
                                            {{ getSchoolTotalCount(s) }}
                                        </span>
                                    </td>

                                    <td class="py-3 px-3 text-center">
                                        <div class="flex flex-wrap justify-center gap-1">
                                            <span
                                                v-for="(count, d) in s.date_counts"
                                                :key="d"
                                                class="text-[10px] font-mono font-medium px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 cursor-pointer hover:bg-slate-200"
                                                @click="schoolDateFilter = d"
                                                :title="`Click to filter to ${formatCalendarDate(d)} (${count} coupons)`"
                                            >
                                                {{ formatCalendarDate(d).split(',')[0] || d }}: <strong>{{ count }}</strong>
                                            </span>
                                        </div>
                                    </td>

                                    <td class="py-3 px-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a
                                                :href="schoolPreviewUrl(s.school_id, schoolDateFilter)"
                                                target="_blank"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition"
                                                :title="`Preview Printable PDF for ${s.school_name}`"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                <span>Preview</span>
                                            </a>

                                            <a
                                                :href="schoolDownloadUrl(s.school_id, schoolDateFilter)"
                                                target="_blank"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition"
                                                :title="`Download Printable PDF for ${s.school_name}`"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                                <span>Download PDF</span>
                                            </a>

                                            <button
                                                type="button"
                                                @click="filterBySchool(s.school_id)"
                                                class="text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:underline px-1.5 py-1"
                                            >
                                                View
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!filteredSchoolBreakdown.length">
                                    <td colspan="10" class="p-8 text-center text-slate-400">
                                        No schools match the filter criteria.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Daily Downloads (Per Day) -->
            <div v-show="activeTab === 'dates'" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="d in dateBreakdown"
                        :key="d.date"
                        class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between hover:border-indigo-300 transition"
                    >
                        <div>
                            <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-3 mb-3">
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">{{ formatCalendarDate(d.date) }}</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">{{ d.school_count }} schools participating</p>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">
                                    {{ d.total }} Meals
                                </span>
                            </div>

                            <!-- Meal type breakdown grid -->
                            <div class="grid grid-cols-3 gap-2 text-center text-xs mb-4">
                                <div class="p-2 rounded-lg bg-amber-50/70 border border-amber-200">
                                    <p class="font-bold text-amber-900 text-sm">{{ d.breakfast }}</p>
                                    <p class="text-[10px] text-amber-700 font-medium">Breakfast</p>
                                </div>
                                <div class="p-2 rounded-lg bg-emerald-50/70 border border-emerald-200">
                                    <p class="font-bold text-emerald-900 text-sm">{{ d.lunch }}</p>
                                    <p class="text-[10px] text-emerald-700 font-medium">Lunch</p>
                                </div>
                                <div class="p-2 rounded-lg bg-indigo-50/70 border border-indigo-200">
                                    <p class="font-bold text-indigo-900 text-sm">{{ d.dinner }}</p>
                                    <p class="text-[10px] text-indigo-700 font-medium">Dinner</p>
                                </div>
                                <div class="p-2 rounded-lg bg-pink-50/70 border border-pink-200">
                                    <p class="font-bold text-pink-900 text-sm">{{ d.snacks }}</p>
                                    <p class="text-[10px] text-pink-700 font-medium">Snacks</p>
                                </div>
                                <div class="p-2 rounded-lg bg-orange-50/70 border border-orange-200">
                                    <p class="font-bold text-orange-900 text-sm">{{ d.tea }}</p>
                                    <p class="text-[10px] text-orange-700 font-medium">Tea</p>
                                </div>
                                <div class="p-2 rounded-lg bg-slate-50 border border-slate-200">
                                    <p class="font-bold text-slate-700 text-sm">{{ d.issued }}</p>
                                    <p class="text-[10px] text-slate-500 font-medium">Issued / Ready</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2 pt-2 border-t border-slate-100">
                            <!-- Preview & Download for Day -->
                            <div class="grid grid-cols-2 gap-2">
                                <a
                                    :href="datePreviewUrl(d.date)"
                                    target="_blank"
                                    class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition"
                                    title="Preview Printable PDF Sheet"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Preview Sheet</span>
                                </a>

                                <a
                                    :href="dateDownloadUrl(d.date)"
                                    target="_blank"
                                    class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition"
                                    title="Download All Coupons for this date"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span>Download PDF</span>
                                </a>
                            </div>

                            <!-- Specific meal download links if meals exist -->
                            <div class="flex flex-wrap items-center justify-center gap-1.5 pt-1 text-[11px]">
                                <span class="text-slate-400">By Meal:</span>
                                <a v-if="d.breakfast" :href="dateDownloadUrl(d.date, 'breakfast')" target="_blank" class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 hover:bg-amber-200 font-medium">BF</a>
                                <a v-if="d.lunch" :href="dateDownloadUrl(d.date, 'lunch')" target="_blank" class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 hover:bg-emerald-200 font-medium">Lunch</a>
                                <a v-if="d.dinner" :href="dateDownloadUrl(d.date, 'dinner')" target="_blank" class="px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 hover:bg-indigo-200 font-medium">Dinner</a>
                                <a v-if="d.snacks" :href="dateDownloadUrl(d.date, 'snacks')" target="_blank" class="px-2 py-0.5 rounded bg-pink-100 text-pink-800 hover:bg-pink-200 font-medium">Snacks</a>
                                <a v-if="d.tea" :href="dateDownloadUrl(d.date, 'tea')" target="_blank" class="px-2 py-0.5 rounded bg-orange-100 text-orange-800 hover:bg-orange-200 font-medium">Tea</a>
                                <button type="button" @click="filterByDate(d.date)" class="ml-auto text-indigo-600 hover:underline">View in List →</button>
                            </div>
                        </div>
                    </div>

                    <div v-if="!dateBreakdown.length" class="col-span-full p-8 text-center text-slate-400 bg-white rounded-xl border border-slate-200">
                        No event dates with coupons found.
                    </div>
                </div>
            </div>

            <!-- Tab 3: All Individual Coupons & Verification -->
            <div v-show="activeTab === 'coupons'" class="space-y-4">
                <!-- Filters Bar -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Meal Type</label>
                            <select v-model="filterMeal" @change="applyFilters" class="w-full text-xs rounded-lg border-slate-300 py-1.5 px-2">
                                <option value="">All Meals (BF, LN, DN, etc.)</option>
                                <option v-for="(label, key) in mealTypes" :key="key" :value="key">
                                    {{ label }} ({{ mealPrefixes[key] || key }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">School / Recipient</label>
                            <select v-model="filterSchool" @change="applyFilters" class="w-full text-xs rounded-lg border-slate-300 py-1.5 px-2">
                                <option value="">All Schools & Extra</option>
                                <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Valid Date</label>
                            <select v-model="filterDate" @change="applyFilters" class="w-full text-xs rounded-lg border-slate-300 py-1.5 px-2">
                                <option value="">All Dates</option>
                                <option v-for="d in eventDates" :key="d" :value="d">{{ formatCalendarDate(d) }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                            <select v-model="filterStatus" @change="applyFilters" class="w-full text-xs rounded-lg border-slate-300 py-1.5 px-2">
                                <option value="">All Statuses</option>
                                <option value="issued">Issued / Ready</option>
                                <option value="redeemed">Redeemed</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-slate-700">
                                <input type="checkbox" v-model="filterExtraOnly" @change="applyFilters" class="rounded text-indigo-600 focus:ring-indigo-500">
                                <span>Extra Only</span>
                            </label>
                            <button v-if="hasActiveFilters" type="button" @click="resetFilters" class="text-xs text-rose-600 hover:text-rose-800 ml-auto font-medium">
                                Reset Filters
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                        <div class="relative flex-1 max-w-xs">
                            <input v-model="searchQuery" type="text" placeholder="Search code, QR token, school..." class="w-full text-xs rounded-lg border-slate-300 pl-8 py-1.5 pr-3">
                            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>

                        <div class="text-xs text-slate-500">
                            Showing <strong>{{ filteredCoupons.length }}</strong> of {{ coupons.length }} loaded coupons
                            <span v-if="selectedIds.length" class="ml-2 font-semibold text-indigo-600">({{ selectedIds.length }} selected)</span>
                        </div>
                    </div>
                </div>

                <!-- Coupons Data Table -->
                <div class="card card--flush bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                    <table class="data-table w-full text-left">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                                <th class="w-10 text-center py-3">
                                    <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="rounded text-indigo-600 focus:ring-indigo-500">
                                </th>
                                <th class="py-3 px-2">Sl No</th>
                                <th class="py-3 px-2">Serialized Code</th>
                                <th class="py-3 px-2">QR Decoded Value</th>
                                <th class="py-3 px-2">Meal Type</th>
                                <th class="py-3 px-2">School / Recipient</th>
                                <th class="py-3 px-2">Valid Date</th>
                                <th class="py-3 px-2">Status</th>
                                <th class="py-3 px-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            <tr v-for="(c, idx) in paginatedCoupons" :key="c.id" class="hover:bg-slate-50/70 transition">
                                <td class="text-center py-2.5">
                                    <input type="checkbox" :value="c.id" v-model="selectedIds" class="rounded text-indigo-600 focus:ring-indigo-500">
                                </td>
                                <td class="py-2.5 px-2 text-slate-400">{{ (currentPage - 1) * pageSize + idx + 1 }}</td>
                                <td class="py-2.5 px-2">
                                    <span class="font-mono font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                        {{ c.coupon_code }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-2">
                                    <span class="font-mono text-slate-600 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-200">
                                        {{ c.qr_token }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-2">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wide"
                                          :class="mealPillClass(c.meal_type)">
                                        {{ c.meal_type }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-2">
                                    <span class="font-medium text-slate-800">{{ c.school_name }}</span>
                                    <span v-if="c.is_extra" class="ml-1 text-[10px] font-bold text-amber-700 bg-amber-100 px-1.5 py-0.2 rounded">Extra</span>
                                </td>
                                <td class="py-2.5 px-2 text-slate-600">{{ formatCalendarDate(c.valid_date) }}</td>
                                <td class="py-2.5 px-2">
                                    <span class="status-pill text-[11px]" :class="couponStatusPillClass(c.status)">
                                        {{ couponStatusLabel(c.status) }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-2 text-right space-x-2">
                                    <a :href="`/food-coupons/verify/${c.qr_token}`" target="_blank" class="text-slate-500 hover:text-slate-800 font-medium">Verify</a>
                                    <button v-if="c.status === 'issued'" type="button" @click="redeem(c.id)" class="text-emerald-600 hover:text-emerald-800 font-semibold">Redeem</button>
                                </td>
                            </tr>
                            <tr v-if="!filteredCoupons.length">
                                <td colspan="9" class="p-10 text-center text-slate-400">
                                    No food coupons match your criteria. Click "Issue from Catering", "Issue from Food Bills", or "Generate Extra Coupons" above.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination footer -->
                    <div v-if="totalPages > 1" class="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs">
                        <span class="text-slate-500">Page {{ currentPage }} of {{ totalPages }} ({{ filteredCoupons.length }} items)</span>
                        <div class="space-x-1">
                            <button type="button" :disabled="currentPage === 1" @click="currentPage--" class="px-2.5 py-1 border rounded bg-white disabled:opacity-40">Previous</button>
                            <button type="button" :disabled="currentPage === totalPages" @click="currentPage++" class="px-2.5 py-1 border rounded bg-white disabled:opacity-40">Next</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Coupon Template Builder -->
            <div v-show="activeTab === 'builder'" class="space-y-6">
                <!-- Builder Header Card -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span>🎨 Food Coupon Visual Template Builder</span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                                96mm × 55mm (10 per A4 Sheet — Full Height)
                            </span>
                        </h2>
                        <p class="text-xs text-slate-500 mt-1">
                            Customize the position of QR codes, serial numbers, meal pills, and school text to match your Sahodaya's graphic background.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="applyKochiMetroPreset"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 transition"
                            title="Load preset aligned for Kochi Metro Sahodaya right-stub template"
                        >
                            Preset: Kochi Metro
                        </button>
                        <button
                            type="button"
                            @click="applyPlainPaperPreset"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 transition"
                            title="Load preset with outer borders for plain A4 paper"
                        >
                            Preset: Plain Paper
                        </button>
                        <button
                            type="button"
                            @click="resetToDefaultLayout"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 transition"
                        >
                            Reset Defaults
                        </button>
                        <a
                            :href="`${base}/food-coupons/print?extra_only=1&preview=1`"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-sky-200 bg-sky-50 hover:bg-sky-100 text-sky-700 transition"
                            title="Preview sample PDF sheet in browser using third-party PDF converter"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <span>Preview Sample PDF</span>
                        </a>
                        <a
                            :href="`${base}/food-coupons/print?extra_only=1`"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition"
                            title="Download sample PDF to test physical printer alignment"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <span>Download Sample PDF</span>
                        </a>
                        <button
                            type="button"
                            @click="submitSaveLayout"
                            class="btn-primary flex items-center gap-1.5"
                            :disabled="savingLayout"
                        >
                            <span v-if="savingLayout">Saving...</span>
                            <span v-else>Save Template Layout</span>
                        </button>
                    </div>
                </div>

                <!-- 2-Column Canvas + Settings Workspace -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    <!-- Left: Interactive Visual Canvas (7 cols on lg) -->
                    <div class="lg:col-span-7 bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">Live WYSIWYG Preview</h3>
                                <p class="text-[11px] text-slate-400">Click any element on the card to configure its position.</p>
                            </div>
                            <!-- Sample Meal Selector -->
                            <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-lg">
                                <button
                                    v-for="(label, key) in mealTypes"
                                    :key="key"
                                    type="button"
                                    @click="previewMeal = key"
                                    class="px-2 py-0.5 text-[11px] font-semibold rounded"
                                    :class="previewMeal === key ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    {{ mealPrefixes[key] || key }}
                                </button>
                            </div>
                        </div>

                        <!-- Card Canvas Frame -->
                        <div class="p-4 bg-slate-100 rounded-xl flex items-center justify-center overflow-hidden border border-slate-200">
                            <div
                                class="relative bg-white shadow-md rounded overflow-hidden select-none"
                                style="width: 100%; max-width: 540px; aspect-ratio: 96 / 55;"
                            >
                                <!-- Background Image -->
                                <img
                                    v-if="event.food_coupon_bg_image_url"
                                    :src="event.food_coupon_bg_image_url"
                                    class="absolute inset-0 w-full h-full object-cover pointer-events-none"
                                    alt="Coupon Background"
                                />

                                <!-- Fallback elements when NO background image -->
                                <template v-if="!event.food_coupon_bg_image_url">
                                    <div class="absolute left-[4.5%] top-[4%] w-[59%] pointer-events-none">
                                        <div class="text-[10px] font-bold text-slate-900 uppercase leading-tight">{{ event.title || 'Sahodaya Festival' }}</div>
                                        <div class="text-[8px] text-slate-500">{{ sahodayaName || 'Sahodaya Complex' }}</div>
                                    </div>
                                    <div
                                        v-if="layoutForm.fallback_title?.show ?? true"
                                        class="absolute text-[13px] font-extrabold text-slate-900 tracking-wider pointer-events-none"
                                        :style="{
                                            top: (layoutForm.fallback_title?.top ?? 72) + '%',
                                            left: (layoutForm.fallback_title?.left ?? 4.5) + '%',
                                        }"
                                    >
                                        FOOD COUPON
                                    </div>
                                    <div class="absolute left-[67%] top-0 bottom-0 border-l border-dashed border-slate-300 pointer-events-none"></div>
                                </template>

                                <!-- 1. Stub Serial Number -->
                                <div
                                    v-if="layoutForm.stub_serial?.show ?? true"
                                    @click="activeElement = 'stub_serial'"
                                    class="absolute text-center font-mono font-bold leading-tight cursor-pointer transition"
                                    :class="activeElement === 'stub_serial' ? 'ring-2 ring-indigo-500 rounded bg-indigo-50/70' : 'hover:outline hover:outline-1 hover:outline-indigo-300'"
                                    :style="{
                                        top: (layoutForm.stub_serial?.top ?? 8.5) + '%',
                                        left: (layoutForm.stub_serial?.left ?? 70.0) + '%',
                                        width: (layoutForm.stub_serial?.width ?? 24.5) + '%',
                                        fontSize: (layoutForm.stub_serial?.font_size ? layoutForm.stub_serial.font_size * 1.5 : 10) + 'px',
                                        color: layoutForm.stub_serial?.color || '#0f172a',
                                    }"
                                >
                                    {{ previewCoupon.coupon_code }}
                                </div>

                                <!-- 2. Stub QR Box & Code -->
                                <div
                                    @click="activeElement = 'qr_box'"
                                    class="absolute flex items-center justify-center cursor-pointer transition p-0.5"
                                    :class="[
                                        activeElement === 'qr_box' ? 'ring-2 ring-indigo-500 bg-indigo-50/50' : 'hover:outline hover:outline-1 hover:outline-indigo-300',
                                        layoutForm.qr_box?.show_border ? 'border border-slate-800 rounded' : ''
                                    ]"
                                    :style="{
                                        top: (layoutForm.qr_box?.top ?? 24.5) + '%',
                                        left: (layoutForm.qr_box?.left ?? 72.0) + '%',
                                        width: (layoutForm.qr_box?.width ?? 20.5) + '%',
                                        height: (layoutForm.qr_box?.height ?? 51.5) + '%',
                                    }"
                                >
                                    <img
                                        :src="sampleQrSrc"
                                        class="w-full h-full object-contain pointer-events-none"
                                        alt="QR"
                                    />
                                </div>

                                <!-- 3. Stub Decoded Token -->
                                <div
                                    v-if="layoutForm.stub_token?.show ?? true"
                                    @click="activeElement = 'stub_token'"
                                    class="absolute text-center font-mono font-bold leading-tight cursor-pointer transition"
                                    :class="activeElement === 'stub_token' ? 'ring-2 ring-indigo-500 rounded bg-indigo-50/70' : 'hover:outline hover:outline-1 hover:outline-indigo-300'"
                                    :style="{
                                        top: (layoutForm.stub_token?.top ?? 84.0) + '%',
                                        left: (layoutForm.stub_token?.left ?? 70.0) + '%',
                                        width: (layoutForm.stub_token?.width ?? 24.5) + '%',
                                        fontSize: (layoutForm.stub_token?.font_size ? layoutForm.stub_token.font_size * 1.4 : 9) + 'px',
                                        color: layoutForm.stub_token?.color || '#0f172a',
                                    }"
                                >
                                    {{ previewCoupon.qr_token }}
                                </div>

                                <!-- 4. Left Meal Pill -->
                                <span
                                    v-if="layoutForm.meal_badge?.show ?? true"
                                    @click="activeElement = 'meal_badge'"
                                    class="absolute inline-block text-white font-bold uppercase rounded cursor-pointer leading-none transition"
                                    :class="[
                                        previewPillClass,
                                        activeElement === 'meal_badge' ? 'ring-2 ring-indigo-500' : 'hover:outline hover:outline-1 hover:outline-indigo-300'
                                    ]"
                                    :style="{
                                        top: (layoutForm.meal_badge?.top ?? 48.5) + '%',
                                        left: (layoutForm.meal_badge?.left ?? 4.5) + '%',
                                        fontSize: (layoutForm.meal_badge?.font_size ? layoutForm.meal_badge.font_size * 1.5 : 8) + 'px',
                                        padding: '2px 6px',
                                    }"
                                >
                                    {{ previewCoupon.meal_type }}
                                </span>

                                <!-- 5. Left Date & Quantity -->
                                <div
                                    v-if="layoutForm.date_meta?.show ?? true"
                                    @click="activeElement = 'date_meta'"
                                    class="absolute whitespace-nowrap cursor-pointer transition"
                                    :class="activeElement === 'date_meta' ? 'ring-2 ring-indigo-500 rounded bg-indigo-50/70' : 'hover:outline hover:outline-1 hover:outline-indigo-300'"
                                    :style="{
                                        top: (layoutForm.date_meta?.top ?? 48.5) + '%',
                                        left: (layoutForm.date_meta?.left ?? 21.0) + '%',
                                        fontSize: (layoutForm.date_meta?.font_size ? layoutForm.date_meta.font_size * 1.4 : 8) + 'px',
                                        color: layoutForm.date_meta?.color || '#334155',
                                    }"
                                >
                                    <strong>Date:</strong> {{ previewCoupon.formatted_date }} &nbsp; <strong>Qty:</strong> 1
                                </div>

                                <!-- 6. Left School Name -->
                                <div
                                    v-if="layoutForm.school_name?.show ?? true"
                                    @click="activeElement = 'school_name'"
                                    class="absolute font-bold truncate cursor-pointer transition"
                                    :class="activeElement === 'school_name' ? 'ring-2 ring-indigo-500 rounded bg-indigo-50/70' : 'hover:outline hover:outline-1 hover:outline-indigo-300'"
                                    :style="{
                                        top: (layoutForm.school_name?.top ?? 59.5) + '%',
                                        left: (layoutForm.school_name?.left ?? 4.5) + '%',
                                        maxWidth: (layoutForm.school_name?.max_width ?? 61.0) + '%',
                                        fontSize: (layoutForm.school_name?.font_size ? layoutForm.school_name.font_size * 1.5 : 9) + 'px',
                                        color: layoutForm.school_name?.color || '#0f172a',
                                    }"
                                >
                                    {{ previewCoupon.school_name }}
                                </div>

                                <!-- 7. Main Voucher Serial Badge -->
                                <div
                                    v-if="layoutForm.voucher_serial?.show ?? true"
                                    @click="activeElement = 'voucher_serial'"
                                    class="absolute font-mono font-bold leading-none cursor-pointer transition"
                                    :class="[
                                        (layoutForm.voucher_serial?.style ?? 'pill') === 'pill' ? 'bg-blue-100 text-blue-900 border border-blue-300 rounded px-1.5 py-0.5' : '',
                                        activeElement === 'voucher_serial' ? 'ring-2 ring-indigo-500' : 'hover:outline hover:outline-1 hover:outline-indigo-300'
                                    ]"
                                    :style="{
                                        top: (layoutForm.voucher_serial?.top ?? 73.0) + '%',
                                        left: (layoutForm.voucher_serial?.left ?? 50.5) + '%',
                                        fontSize: (layoutForm.voucher_serial?.font_size ? layoutForm.voucher_serial.font_size * 1.5 : 10) + 'px',
                                        color: layoutForm.voucher_serial?.color || '#1e3a8a',
                                    }"
                                >
                                    {{ previewCoupon.coupon_code }}
                                </div>
                            </div>
                        </div>

                        <!-- Canvas Helper info -->
                        <div class="text-xs text-slate-500 flex items-center justify-between">
                            <span>Selected Element: <strong class="text-indigo-600 uppercase">{{ activeElement.replace('_', ' ') }}</strong></span>
                            <span class="text-slate-400">Dimensions: 96mm × 55mm (300 DPI)</span>
                        </div>
                    </div>

                    <!-- Right: Positioning & Styling Controls (5 cols on lg) -->
                    <div class="lg:col-span-5 space-y-4">
                        <!-- Background Image Card -->
                        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center justify-between">
                                <span>1. Template Background Graphic</span>
                                <span v-if="event.has_template_bg" class="text-[10px] text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded font-bold">Active</span>
                            </h4>
                            <p class="text-xs text-slate-500">
                                Upload your Sahodaya's designed coupon graphic (PNG, JPG, WebP). Recommended resolution: <strong>1122 × 480 px</strong>.
                            </p>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="showBgModal = true"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 transition"
                                >
                                    {{ event.has_template_bg ? 'Replace Background' : 'Upload Background Image' }}
                                </button>
                                <button
                                    v-if="event.has_template_bg"
                                    type="button"
                                    @click="removeTemplateBg"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-600 hover:bg-rose-50 border border-rose-200 transition"
                                    :disabled="removingBg"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>

                        <!-- Element Settings Tabs -->
                        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                                    2. Position & Font Settings
                                </h4>
                                <select v-model="activeElement" class="text-xs rounded border-slate-300 py-1 px-2 font-medium">
                                    <option value="qr_box">QR Code & Box (Stub)</option>
                                    <option value="stub_serial">Serial Code (Above QR)</option>
                                    <option value="stub_token">Decoded Token (Below QR)</option>
                                    <option value="meal_badge">Meal Type Pill</option>
                                    <option value="date_meta">Date & Qty Meta</option>
                                    <option value="school_name">School Name</option>
                                    <option value="voucher_serial">Voucher Serial Code</option>
                                </select>
                            </div>

                            <!-- Panel 1: QR Box -->
                            <div v-show="activeElement === 'qr_box'" class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">X Position (Left %)</label>
                                        <input type="number" step="0.5" min="0" max="100" v-model.number="layoutForm.qr_box.left" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Y Position (Top %)</label>
                                        <input type="number" step="0.5" min="0" max="100" v-model.number="layoutForm.qr_box.top" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Width (%)</label>
                                        <input type="number" step="0.5" min="5" max="50" v-model.number="layoutForm.qr_box.width" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Height (%)</label>
                                        <input type="number" step="0.5" min="5" max="80" v-model.number="layoutForm.qr_box.height" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                </div>
                                <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer pt-1">
                                    <input type="checkbox" v-model="layoutForm.qr_box.show_border" class="rounded text-indigo-600">
                                    <span>Draw Outline Border Box (useful when background has no box)</span>
                                </label>
                            </div>

                            <!-- Panel 2: Stub Serial -->
                            <div v-show="activeElement === 'stub_serial'" class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">X Position (Left %)</label>
                                        <input type="number" step="0.5" min="0" max="100" v-model.number="layoutForm.stub_serial.left" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Y Position (Top %)</label>
                                        <input type="number" step="0.5" min="0" max="100" v-model.number="layoutForm.stub_serial.top" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Width (%)</label>
                                        <input type="number" step="0.5" min="5" max="50" v-model.number="layoutForm.stub_serial.width" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Font Size (pt)</label>
                                        <input type="number" step="0.1" min="4" max="16" v-model.number="layoutForm.stub_serial.font_size" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                </div>
                                <div class="flex items-center justify-between pt-1">
                                    <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                        <input type="checkbox" v-model="layoutForm.stub_serial.show" class="rounded text-indigo-600">
                                        <span>Show Serial Number</span>
                                    </label>
                                    <input type="color" v-model="layoutForm.stub_serial.color" class="h-6 w-8 rounded cursor-pointer border border-slate-300">
                                </div>
                            </div>

                            <!-- Panel 3: Stub Token -->
                            <div v-show="activeElement === 'stub_token'" class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">X Position (Left %)</label>
                                        <input type="number" step="0.5" min="0" max="100" v-model.number="layoutForm.stub_token.left" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Y Position (Top %)</label>
                                        <input type="number" step="0.5" min="0" max="100" v-model.number="layoutForm.stub_token.top" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Width (%)</label>
                                        <input type="number" step="0.5" min="5" max="50" v-model.number="layoutForm.stub_token.width" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Font Size (pt)</label>
                                        <input type="number" step="0.1" min="4" max="14" v-model.number="layoutForm.stub_token.font_size" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                </div>
                                <div class="flex items-center justify-between pt-1">
                                    <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                        <input type="checkbox" v-model="layoutForm.stub_token.show" class="rounded text-indigo-600">
                                        <span>Show Decoded QR Token</span>
                                    </label>
                                    <input type="color" v-model="layoutForm.stub_token.color" class="h-6 w-8 rounded cursor-pointer border border-slate-300">
                                </div>
                            </div>

                            <!-- Panel 4: Meal Badge -->
                            <div v-show="activeElement === 'meal_badge'" class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">X Position (Left %)</label>
                                        <input type="number" step="0.5" min="0" max="100" v-model.number="layoutForm.meal_badge.left" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Y Position (Top %)</label>
                                        <input type="number" step="0.5" min="0" max="100" v-model.number="layoutForm.meal_badge.top" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Font Size (pt)</label>
                                        <input type="number" step="0.1" min="3" max="12" v-model.number="layoutForm.meal_badge.font_size" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                </div>
                                <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer pt-1">
                                    <input type="checkbox" v-model="layoutForm.meal_badge.show" class="rounded text-indigo-600">
                                    <span>Show Meal Type Pill</span>
                                </label>
                            </div>

                            <!-- Panel 5: Date Meta -->
                            <div v-show="activeElement === 'date_meta'" class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">X Position (Left %)</label>
                                        <input type="number" step="0.5" min="0" max="100" v-model.number="layoutForm.date_meta.left" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Y Position (Top %)</label>
                                        <input type="number" step="0.5" min="0" max="100" v-model.number="layoutForm.date_meta.top" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Font Size (pt)</label>
                                        <input type="number" step="0.1" min="3" max="12" v-model.number="layoutForm.date_meta.font_size" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Text Color</label>
                                        <input type="color" v-model="layoutForm.date_meta.color" class="h-8 w-full rounded cursor-pointer border border-slate-300">
                                    </div>
                                </div>
                                <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer pt-1">
                                    <input type="checkbox" v-model="layoutForm.date_meta.show" class="rounded text-indigo-600">
                                    <span>Show Date and Quantity</span>
                                </label>
                            </div>

                            <!-- Panel 6: School Name -->
                            <div v-show="activeElement === 'school_name'" class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">X Position (Left %)</label>
                                        <input type="number" step="0.5" min="0" max="100" v-model.number="layoutForm.school_name.left" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Y Position (Top %)</label>
                                        <input type="number" step="0.5" min="0" max="100" v-model.number="layoutForm.school_name.top" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Max Width (%)</label>
                                        <input type="number" step="1" min="20" max="90" v-model.number="layoutForm.school_name.max_width" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Font Size (pt)</label>
                                        <input type="number" step="0.1" min="3" max="14" v-model.number="layoutForm.school_name.font_size" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                </div>
                                <div class="flex items-center justify-between pt-1">
                                    <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                        <input type="checkbox" v-model="layoutForm.school_name.show" class="rounded text-indigo-600">
                                        <span>Show School / Recipient Name</span>
                                    </label>
                                    <input type="color" v-model="layoutForm.school_name.color" class="h-6 w-8 rounded cursor-pointer border border-slate-300">
                                </div>
                            </div>

                            <!-- Panel 7: Voucher Serial -->
                            <div v-show="activeElement === 'voucher_serial'" class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">X Position (Left %)</label>
                                        <input type="number" step="0.5" min="0" max="100" v-model.number="layoutForm.voucher_serial.left" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Y Position (Top %)</label>
                                        <input type="number" step="0.5" min="0" max="100" v-model.number="layoutForm.voucher_serial.top" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Font Size (pt)</label>
                                        <input type="number" step="0.1" min="4" max="16" v-model.number="layoutForm.voucher_serial.font_size" class="w-full text-xs rounded border-slate-300 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Style</label>
                                        <select v-model="layoutForm.voucher_serial.style" class="w-full text-xs rounded border-slate-300 py-1.5">
                                            <option value="pill">Pill Box (Light Blue)</option>
                                            <option value="plain">Clean Text Only (Transparent)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between pt-1">
                                    <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                        <input type="checkbox" v-model="layoutForm.voucher_serial.show" class="rounded text-indigo-600">
                                        <span>Show Serial Beside Food Coupon</span>
                                    </label>
                                    <input type="color" v-model="layoutForm.voucher_serial.color" class="h-6 w-8 rounded cursor-pointer border border-slate-300">
                                </div>
                            </div>
                        </div>

                        <!-- Save & Sahodaya Default Card -->
                        <div class="bg-indigo-50/70 p-4 rounded-xl border border-indigo-200 shadow-sm space-y-3">
                            <label class="flex items-start gap-2.5 text-xs font-medium text-indigo-950 cursor-pointer">
                                <input type="checkbox" v-model="saveAsSahodayaDefault" class="mt-0.5 rounded text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <span class="font-bold">Save as default template for this Sahodaya</span>
                                    <p class="text-[11px] text-indigo-700 mt-0.5">
                                        New events created in this Sahodaya will automatically inherit these coordinates.
                                    </p>
                                </div>
                            </label>

                            <div class="pt-2 border-t border-indigo-200/60 flex items-center justify-between">
                                <span class="text-xs text-indigo-800">Ready to apply changes?</span>
                                <button
                                    type="button"
                                    @click="submitSaveLayout"
                                    class="btn-primary"
                                    :disabled="savingLayout"
                                >
                                    <span v-if="savingLayout">Saving Layout...</span>
                                    <span v-else>Save Template Layout</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Modal 1: Generate Extra Coupons -->
        <Modal :show="showExtraModal" title="Generate Extra / Buffer Food Coupons" subtitle="Generate additional meal coupons for guests, staff, volunteers or emergency buffer." @close="showExtraModal = false">
            <form @submit.prevent="submitExtraCoupons" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Meal Type *</label>
                    <select v-model="extraForm.meal_type" required class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="breakfast">Breakfast (BF)</option>
                        <option value="lunch">Lunch (LN)</option>
                        <option value="dinner">Dinner (DN)</option>
                        <option value="snacks">Snacks (SN)</option>
                        <option value="tea">Tea (TE)</option>
                        <option value="other">Other (OT)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Valid Date *</label>
                    <input type="date" v-model="extraForm.valid_date" required class="w-full rounded-lg border-slate-300 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Quantity (Number of 1-head Coupons) *</label>
                    <input type="number" v-model.number="extraForm.quantity" min="1" max="500" required class="w-full rounded-lg border-slate-300 text-sm" placeholder="e.g. 20, 50, 100">
                    <p class="text-[11px] text-slate-500 mt-1">Serialized codes will continue the sequence (e.g. LN-0051 to LN-0100) with unique QR codes.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Assign to School (Optional)</label>
                    <select v-model="extraForm.school_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Leave blank for General Buffer / Organizer / Guest</option>
                        <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Notes / Purpose (Optional)</label>
                    <input type="text" v-model="extraForm.notes" class="w-full rounded-lg border-slate-300 text-sm" placeholder="e.g. Judges & Guest Buffer">
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="showExtraModal = false" class="px-4 py-2 border rounded-lg text-sm font-medium">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="generatingExtra">
                        <span v-if="generatingExtra">Generating...</span>
                        <span v-else>Generate {{ extraForm.quantity || '' }} Extra Coupons</span>
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Modal 2: Ungenerate Coupons -->
        <Modal :show="showUngenerateModal" title="Ungenerate Food Coupons" subtitle="Safely remove unredeemed coupons. Coupons already marked as Redeemed are protected and will never be deleted." @close="showUngenerateModal = false">
            <form @submit.prevent="submitUngenerate" class="space-y-4">
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
                    <strong>Notice:</strong> This action permanently removes unredeemed coupons from the database.
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-2">Scope to Ungenerate</label>
                    <div class="space-y-2">
                        <label class="flex items-start gap-2 cursor-pointer p-2 rounded hover:bg-slate-50 border border-slate-200">
                            <input type="radio" value="all_unredeemed" v-model="ungenerateForm.scope" class="mt-0.5 text-rose-600 focus:ring-rose-500">
                            <div>
                                <div class="text-xs font-semibold text-slate-800">All Unredeemed Coupons</div>
                                <div class="text-[11px] text-slate-500">Remove all unused coupons for this event so you can regenerate cleanly.</div>
                            </div>
                        </label>

                        <label class="flex items-start gap-2 cursor-pointer p-2 rounded hover:bg-slate-50 border border-slate-200">
                            <input type="radio" value="extra_only" v-model="ungenerateForm.scope" class="mt-0.5 text-rose-600 focus:ring-rose-500">
                            <div>
                                <div class="text-xs font-semibold text-slate-800">Only Admin Extra / Buffer Coupons</div>
                                <div class="text-[11px] text-slate-500">Remove only coupons generated as extra/buffer. Catering and food bill coupons stay intact.</div>
                            </div>
                        </label>

                        <label class="flex items-start gap-2 cursor-pointer p-2 rounded hover:bg-slate-50 border border-slate-200">
                            <input type="radio" value="by_meal_type" v-model="ungenerateForm.scope" class="mt-0.5 text-rose-600 focus:ring-rose-500">
                            <div>
                                <div class="text-xs font-semibold text-slate-800">By Specific Meal Type & Date</div>
                                <div class="text-[11px] text-slate-500">Select a specific meal type (e.g. only Breakfast or Lunch) to remove.</div>
                            </div>
                        </label>

                        <label v-if="selectedIds.length" class="flex items-start gap-2 cursor-pointer p-2 rounded hover:bg-slate-50 border border-indigo-200 bg-indigo-50/50">
                            <input type="radio" value="selected" v-model="ungenerateForm.scope" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <div class="text-xs font-semibold text-indigo-900">Only Selected Coupons ({{ selectedIds.length }} selected)</div>
                                <div class="text-[11px] text-indigo-700">Remove only the coupons currently checked in the table above.</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div v-if="ungenerateForm.scope === 'by_meal_type'" class="grid grid-cols-2 gap-3 pt-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Meal Type</label>
                        <select v-model="ungenerateForm.meal_type" class="w-full rounded-lg border-slate-300 text-xs">
                            <option value="">All Meals</option>
                            <option v-for="(label, key) in mealTypes" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Valid Date (Optional)</label>
                        <select v-model="ungenerateForm.valid_date" class="w-full rounded-lg border-slate-300 text-xs">
                            <option value="">All Dates</option>
                            <option v-for="d in eventDates" :key="d" :value="d">{{ formatCalendarDate(d) }}</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="showUngenerateModal = false" class="px-4 py-2 border rounded-lg text-sm font-medium">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-rose-600 text-white rounded-lg text-sm font-semibold hover:bg-rose-700 transition" :disabled="ungenerating">
                        <span v-if="ungenerating">Removing...</span>
                        <span v-else>Confirm & Ungenerate</span>
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Modal 3: Template Background Image -->
        <Modal :show="showBgModal" title="Coupon Card Background Template" subtitle="Provide a background design image for the 10 coupons per A4 sheet template." @close="showBgModal = false">
            <div class="space-y-4">
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600">
                    <p class="font-semibold text-slate-800 mb-1">Template Specification:</p>
                    <p>• 10 coupons fit in a 2×5 grid on a single A4 sheet (~96mm × 52mm per coupon).</p>
                    <p>• Provide a single coupon card background image (JPEG, PNG, or WEBP, max 5MB).</p>
                    <p>• The system automatically overlays the Sahodaya title, serialized code (BF/LN/DN), valid date, QR code, and decoded value.</p>
                </div>

                <div v-if="event.food_coupon_bg_image_url" class="border rounded-xl p-3 bg-white text-center">
                    <p class="text-xs font-semibold text-slate-700 mb-2">Current Background Template:</p>
                    <div class="relative inline-block max-w-full overflow-hidden rounded-lg border border-slate-300 shadow-sm">
                        <img v-if="!bgImgError" :src="event.food_coupon_bg_image_url" alt="Coupon Template Background" class="max-h-48 object-contain" @error="bgImgError = true">
                        <div v-else class="p-6 text-xs text-amber-800 bg-amber-50">
                            ⚠️ Preview could not be loaded in browser. Re-upload your background to refresh.
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" @click="removeTemplateBg" class="text-xs text-rose-600 hover:text-rose-800 font-semibold" :disabled="removingBg">
                            <span v-if="removingBg">Removing...</span>
                            <span v-else>Remove Current Background</span>
                        </button>
                    </div>
                </div>

                <form @submit.prevent="submitTemplateBg" class="space-y-3 pt-2">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Upload New Background Image</label>
                        <input type="file" ref="bgFileInput" accept="image/png,image/jpeg,image/webp" required class="w-full text-xs file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="showBgModal = false" class="px-4 py-2 border rounded-lg text-sm font-medium">Close</button>
                        <button type="submit" class="btn-primary" :disabled="uploadingBg">
                            <span v-if="uploadingBg">Uploading...</span>
                            <span v-else>Save Background</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Modal 4: Print Options -->
        <Modal :show="showPrintModal" title="Print Food Coupons (A4 Sheet)" subtitle="Generate high-resolution printable PDF sheets with QR codes (12 or 10 per sheet)." @close="showPrintModal = false">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Coupons per A4 Sheet</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label
                            class="flex items-start gap-2.5 p-3 border rounded-xl cursor-pointer transition"
                            :class="printPerSheet === 12 ? 'border-indigo-500 bg-indigo-50/60 ring-2 ring-indigo-500/20' : 'border-slate-200 hover:bg-slate-50'"
                        >
                            <input type="radio" v-model="printPerSheet" :value="12" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-xs font-bold text-slate-900">12 per Sheet</span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200">Recommended</span>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-0.5">2 × 6 grid • Saves 20% paper</p>
                            </div>
                        </label>
                        <label
                            class="flex items-start gap-2.5 p-3 border rounded-xl cursor-pointer transition"
                            :class="printPerSheet === 10 ? 'border-indigo-500 bg-indigo-50/60 ring-2 ring-indigo-500/20' : 'border-slate-200 hover:bg-slate-50'"
                        >
                            <input type="radio" v-model="printPerSheet" :value="10" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="text-xs font-bold text-slate-900">10 per Sheet</span>
                                <p class="text-[11px] text-slate-500 mt-0.5">2 × 5 grid • Card height 55mm</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Filter by Meal Type</label>
                    <select v-model="printMeal" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">All Meal Types (Breakfast, Lunch, Dinner, etc.)</option>
                        <option v-for="(label, key) in mealTypes" :key="key" :value="key">{{ label }} ({{ mealPrefixes[key] || key }})</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Filter by School</label>
                    <select v-model="printSchool" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">All Schools & Extra</option>
                        <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Filter by Valid Date</label>
                    <select v-model="printDate" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">All Dates</option>
                        <option v-for="d in eventDates" :key="d" :value="d">{{ formatCalendarDate(d) }}</option>
                    </select>
                </div>

                <div>
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-slate-700">
                        <input type="checkbox" v-model="printExtraOnly" class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span>Print Admin Extra / Buffer Coupons Only</span>
                    </label>
                </div>

                <div class="p-3 bg-indigo-50 border border-indigo-200 rounded-lg text-xs text-indigo-900 space-y-1">
                    <p>• <strong>{{ printPerSheet }} coupons per A4 sheet</strong> ({{ printPerSheet === 12 ? '2 columns × 6 rows' : '2 columns × 5 rows' }}).</p>
                    <p>• Includes unique QR code with decoded value underneath.</p>
                    <p>• Ready for guillotine cutting and distribution.</p>
                </div>

                <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between text-xs">
                    <span class="text-slate-700 font-medium">Matching coupons ready to print:</span>
                    <span
                        class="font-bold px-2.5 py-0.5 rounded-full text-xs border"
                        :class="matchedPrintCouponsCount > 0 ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-rose-100 text-rose-800 border-rose-300'"
                    >
                        {{ matchedPrintCouponsCount }} coupons ready
                    </span>
                </div>

                <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="showPrintModal = false" class="px-4 py-2 border rounded-lg text-sm font-medium">Cancel</button>
                    <div class="flex items-center gap-2">
                        <a
                            :href="matchedPrintCouponsCount > 0 ? printPreviewUrl : 'javascript:void(0)'"
                            target="_blank"
                            class="px-3.5 py-2 rounded-lg text-sm font-semibold border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition flex items-center gap-1.5"
                            :class="{ 'opacity-50 pointer-events-none cursor-not-allowed': matchedPrintCouponsCount === 0 }"
                            title="Preview PDF in browser tab before printing"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <span>Preview in Browser</span>
                        </a>

                        <a
                            :href="matchedPrintCouponsCount > 0 ? printUrl : 'javascript:void(0)'"
                            target="_blank"
                            @click="handlePrintDownload"
                            class="btn-primary flex items-center gap-1.5"
                            :class="{ 'opacity-50 pointer-events-none cursor-not-allowed': matchedPrintCouponsCount === 0 }"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Download PDF ({{ matchedPrintCouponsCount }})</span>
                        </a>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Modal 5: Live Coupon Card Preview -->
        <Modal :show="showPreviewModal" title="Food Coupon Card Preview" :subtitle="`Accurate preview of how individual coupons will print on A4 sheets (${printPerSheet === 12 ? '96mm × 46.5mm' : '96mm × 55mm'}).`" @close="showPreviewModal = false">
            <div class="space-y-4">
                <div class="flex items-center justify-between gap-3 p-2.5 bg-slate-50 border border-slate-200 rounded-lg">
                    <span class="text-xs font-semibold text-slate-700">Preview Meal:</span>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button v-for="(label, key) in mealTypes" :key="key" type="button"
                                @click="previewMeal = key"
                                class="px-2.5 py-1 text-xs rounded-md font-semibold transition"
                                :class="previewMeal === key ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white border text-slate-700 hover:bg-slate-100'">
                            {{ label }}
                        </button>
                    </div>
                </div>

                <!-- The Live Card -->
                <div class="p-4 bg-slate-100 border border-slate-200 rounded-xl flex items-center justify-center">
                    <div class="relative w-full max-w-[480px] rounded-lg overflow-hidden border border-slate-300 shadow-lg bg-white select-none transition-all duration-200"
                         :style="{ aspectRatio: printPerSheet === 12 ? '96 / 46.5' : '96 / 55' }">
                        <!-- Background Image or Fallback -->
                        <img v-if="event.food_coupon_bg_image_url && !bgImgError" :src="event.food_coupon_bg_image_url" class="absolute inset-0 w-full h-full object-fill z-0" alt="Background" />
                        <div v-else class="absolute inset-0 bg-gradient-to-r from-slate-50 to-slate-100 p-3 z-0">
                            <div class="text-[10px] font-bold text-slate-800 uppercase">{{ event.title }}</div>
                            <div class="text-[8px] text-slate-500">{{ sahodayaName || 'Sahodaya' }}</div>
                            <div class="absolute left-[4.5%] top-[72%] text-xs font-extrabold text-slate-800 tracking-wider">FOOD COUPON</div>
                            <div class="absolute left-[67%] top-0 bottom-0 border-l border-dashed border-slate-400"></div>
                            <div class="absolute left-[70.41%] top-[22.60%] w-[23.54%] h-[55.48%] border border-slate-900 rounded"></div>
                        </div>

                        <!-- Left Details Overlay -->
                        <div class="absolute left-[4.5%] top-[48.5%] w-[58%] z-10">
                            <div class="flex items-center gap-1.5 text-[9px] leading-none mb-1.5 whitespace-nowrap">
                                <span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider text-white" :class="previewPillClass">
                                    {{ previewCoupon.meal_type || 'breakfast' }}
                                </span>
                                <span class="text-slate-700 font-medium">Date: <strong class="text-slate-900">{{ previewCoupon.formatted_date || '03 Oct 2026' }}</strong></span>
                                <span class="text-slate-700 font-medium">Qty: <strong class="text-slate-900">1</strong></span>
                            </div>
                            <div class="text-[9.5px] font-bold text-slate-900 truncate">
                                {{ previewCoupon.school_name || 'Infant Jesus Public School' }}
                                <span v-if="previewCoupon.is_extra" class="ml-1 text-[8px] bg-amber-100 text-amber-800 border border-amber-300 px-1 rounded uppercase font-bold">Extra</span>
                            </div>
                        </div>

                        <!-- Main Serial Badge beside FOOD COUPON -->
                        <div class="absolute left-[50.5%] top-[73.0%] z-10 font-mono font-bold text-[10px] text-sky-800 bg-sky-100 border border-sky-300 px-1.5 py-0.5 rounded leading-none whitespace-nowrap shadow-xs">
                            {{ previewCoupon.coupon_code || 'BF-0001' }}
                        </div>

                        <!-- Stub Area: Serial Code OUTSIDE above box -->
                        <div class="absolute left-[70.0%] top-[8.5%] w-[24.5%] text-center z-10 font-mono font-bold text-[10.5px] text-slate-900 tracking-wide whitespace-nowrap leading-none">
                            {{ previewCoupon.coupon_code || 'BF-0001' }}
                        </div>

                        <!-- Stub Area: QR Code Box: Fits QR FULLY inside the box -->
                        <div class="absolute left-[72.0%] top-[24.5%] w-[20.5%] h-[51.5%] z-10 flex items-center justify-center p-0.5 overflow-hidden">
                            <img v-if="sampleQrSrc" :src="sampleQrSrc" class="w-full h-full object-contain" alt="QR" />
                            <div v-else class="w-full h-full bg-slate-900 flex items-center justify-center text-white text-[7px] font-mono">QR CODE</div>
                        </div>

                        <!-- Stub Area: Decoded Token OUTSIDE below box -->
                        <div class="absolute left-[70.0%] top-[84.0%] w-[24.5%] text-center z-10 font-mono font-bold text-[8.5px] text-slate-800 tracking-wider whitespace-nowrap leading-none">
                            {{ previewCoupon.qr_token || 'J4GJLO5GQW' }}
                        </div>
                    </div>
                </div>

                <!-- Layout Highlights -->
                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-xs text-emerald-900 space-y-1">
                    <p class="font-semibold text-emerald-950 mb-1">Layout Verification:</p>
                    <p>• <strong>QR Code:</strong> Fitted 100% inside the designated square box with clear margins.</p>
                    <p>• <strong>Serial Code:</strong> Positioned cleanly outside above the box, and next to "FOOD COUPON".</p>
                    <p>• <strong>Decoded Token:</strong> Positioned outside below the box.</p>
                    <p>• <strong>Continuous Serialization:</strong> Sequential numbers continue across schools.</p>
                </div>

                <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="showPreviewModal = false" class="px-4 py-2 border rounded-lg text-sm font-medium">Close</button>
                    <div class="flex items-center gap-2">
                        <a
                            :href="`${base}/food-coupons/print?preview=1&per_sheet=${printPerSheet}`"
                            target="_blank"
                            class="px-3.5 py-2 rounded-lg text-sm font-semibold border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition flex items-center gap-1.5"
                            :title="`Preview full ${printPerSheet}-per-sheet PDF in browser using high-fidelity converter`"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <span>Preview Full A4 Sheet (PDF)</span>
                        </a>
                        <button type="button" @click="showPreviewModal = false; showPrintModal = true" class="btn-primary">
                            Proceed to Print Options
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import EventHierarchyBadge from '@/Components/fest/EventHierarchyBadge.vue';
import FoodRegionDrillDown from '@/Components/sahodaya/FoodRegionDrillDown.vue';
import Modal from '@/Components/ui/Modal.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';
import { couponStatusLabel, couponStatusPillClass } from '@/support/foodBillStatus.js';

const props = defineProps({
    sahodaya: Object,
    sahodayaName: { type: String, default: '' },
    sampleQrSrc: { type: String, default: '' },
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    coupons: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    hierarchy: { type: Object, default: null },
    isPartitionedHub: { type: Boolean, default: false },
    foodRegionSummary: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    eventDates: { type: Array, default: () => [] },
    mealTypes: { type: Object, default: () => ({}) },
    mealPrefixes: { type: Object, default: () => ({}) },
    schoolDates: { type: Array, default: () => [] },
    schoolBreakdown: { type: Array, default: () => [] },
    dateBreakdown: { type: Array, default: () => [] },
    couponMatrix: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    foodCouponLayout: { type: Object, default: () => ({}) },
    defaultLayout: { type: Object, default: () => ({}) },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const hubHref = computed(() => (
    props.hierarchy?.parent_event ? `/sahodaya-admin/${props.sahodaya.id}/events/${props.hierarchy.parent_event.id}/food-coupons` : null
));

// Loading states
const issuingCatering = ref(false);
const issuingBill = ref(false);
const generatingExtra = ref(false);
const ungenerating = ref(false);
const uploadingBg = ref(false);
const removingBg = ref(false);

// Active Tab ('schools' | 'dates' | 'coupons')
const activeTab = ref('schools');
const schoolSearch = ref('');
const schoolDateFilter = ref('');

const filteredSchoolBreakdown = computed(() => {
    let list = props.schoolBreakdown || [];
    if (schoolSearch.value) {
        const q = schoolSearch.value.toLowerCase().trim();
        list = list.filter(s => (s.school_name || '').toLowerCase().includes(q));
    }
    if (schoolDateFilter.value) {
        list = list.filter(s => s.date_counts && s.date_counts[schoolDateFilter.value] > 0);
    }
    return list;
});

function getSchoolMealCount(s, mealKey) {
    if (schoolDateFilter.value && s.date_meals && s.date_meals[schoolDateFilter.value]) {
        return s.date_meals[schoolDateFilter.value][mealKey] || 0;
    }
    return s[mealKey] || 0;
}

function getSchoolTotalCount(s) {
    if (schoolDateFilter.value && s.date_meals && s.date_meals[schoolDateFilter.value]) {
        return s.date_meals[schoolDateFilter.value].total || 0;
    }
    return s.total || 0;
}

function getSchoolIssuedCount(s) {
    if (schoolDateFilter.value && s.date_meals && s.date_meals[schoolDateFilter.value]) {
        return s.date_meals[schoolDateFilter.value].issued || 0;
    }
    return s.issued || 0;
}

function getSchoolRedeemedCount(s) {
    if (schoolDateFilter.value && s.date_meals && s.date_meals[schoolDateFilter.value]) {
        return s.date_meals[schoolDateFilter.value].redeemed || 0;
    }
    return s.redeemed || 0;
}

function schoolDownloadUrl(schoolId, date = '') {
    const params = new URLSearchParams();
    if (schoolId) params.set('school_id', schoolId);
    else params.set('extra_only', '1');
    if (date) params.set('valid_date', date);
    return `${base}/food-coupons/print?${params.toString()}`;
}

function schoolPreviewUrl(schoolId, date = '') {
    const params = new URLSearchParams();
    if (schoolId) params.set('school_id', schoolId);
    else params.set('extra_only', '1');
    if (date) params.set('valid_date', date);
    params.set('preview', '1');
    return `${base}/food-coupons/print?${params.toString()}`;
}

function dateDownloadUrl(date, mealType = '') {
    const params = new URLSearchParams();
    if (date) params.set('valid_date', date);
    if (mealType) params.set('meal_type', mealType);
    return `${base}/food-coupons/print?${params.toString()}`;
}

function datePreviewUrl(date, mealType = '') {
    const params = new URLSearchParams();
    if (date) params.set('valid_date', date);
    if (mealType) params.set('meal_type', mealType);
    params.set('preview', '1');
    return `${base}/food-coupons/print?${params.toString()}`;
}

function filterBySchool(schoolId) {
    filterSchool.value = schoolId || '';
    activeTab.value = 'coupons';
    applyFilters();
}

function filterByDate(date) {
    filterDate.value = date || '';
    activeTab.value = 'coupons';
    applyFilters();
}

// Modals
const showExtraModal = ref(false);
const showUngenerateModal = ref(false);
const showBgModal = ref(false);
const showPrintModal = ref(false);
const showPreviewModal = ref(false);

// Preview State
const previewMeal = ref('breakfast');

const previewCoupon = computed(() => {
    const matching = props.coupons.find(c => c.meal_type === previewMeal.value);
    if (matching) {
        return {
            ...matching,
            formatted_date: formatCalendarDate(matching.valid_date),
        };
    }
    const prefix = props.mealPrefixes?.[previewMeal.value] || 'FC';
    return {
        coupon_code: `${prefix}-0001`,
        qr_token: 'J4GJLO5GQW',
        meal_type: previewMeal.value,
        valid_date: props.eventDates?.[0] || '2026-10-03',
        formatted_date: props.eventDates?.[0] ? formatCalendarDate(props.eventDates[0]) : '03 Oct 2026',
        school_name: props.schools?.[0]?.name || 'Infant Jesus Public School',
        head_count: 1,
        is_extra: false,
    };
});

const previewPillClass = computed(() => {
    const map = {
        breakfast: 'bg-amber-600',
        lunch: 'bg-emerald-600',
        dinner: 'bg-indigo-600',
        snacks: 'bg-pink-600',
        tea: 'bg-orange-600',
        other: 'bg-slate-600',
    };
    return map[previewCoupon.value?.meal_type] || 'bg-slate-600';
});

// Filters
const filterMeal = ref(props.filters?.meal_type ?? '');
const filterSchool = ref(props.filters?.school_id ?? '');
const filterDate = ref(props.filters?.valid_date ?? '');
const filterStatus = ref(props.filters?.status ?? '');
const filterExtraOnly = ref(Boolean(props.filters?.extra_only));
const searchQuery = ref('');

// Print filters
const printPerSheet = ref(12);
const printMeal = ref('');
const printSchool = ref('');
const printDate = ref('');
const printExtraOnly = ref(false);

// Forms
const extraForm = reactive({
    meal_type: 'lunch',
    valid_date: props.eventDates?.[0] || new Date().toISOString().split('T')[0],
    quantity: 20,
    school_id: '',
    notes: '',
});

const ungenerateForm = reactive({
    scope: 'all_unredeemed',
    meal_type: '',
    valid_date: '',
});

const bgFileInput = ref(null);
const bgImgError = ref(false);

// Selected coupon IDs for batch operations
const selectedIds = ref([]);

// Pagination
const currentPage = ref(1);
const pageSize = 50;

const hasActiveFilters = computed(() => (
    Boolean(filterMeal.value || filterSchool.value || filterDate.value || filterStatus.value || filterExtraOnly.value || searchQuery.value)
));

const filteredCoupons = computed(() => {
    let result = props.coupons;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase().trim();
        result = result.filter(c => (
            (c.coupon_code || '').toLowerCase().includes(q) ||
            (c.qr_token || '').toLowerCase().includes(q) ||
            (c.school_name || '').toLowerCase().includes(q)
        ));
    }
    return result;
});

const totalPages = computed(() => Math.ceil(filteredCoupons.value.length / pageSize) || 1);

const paginatedCoupons = computed(() => {
    const start = (currentPage.value - 1) * pageSize;
    return filteredCoupons.value.slice(start, start + pageSize);
});

const isAllSelected = computed(() => (
    filteredCoupons.value.length > 0 && selectedIds.value.length === filteredCoupons.value.length
));

function toggleSelectAll() {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = filteredCoupons.value.map(c => c.id);
    }
}

function applyFilters() {
    currentPage.value = 1;
    router.get(`${base}/food-coupons`, {
        meal_type: filterMeal.value,
        school_id: filterSchool.value,
        valid_date: filterDate.value,
        status: filterStatus.value,
        extra_only: filterExtraOnly.value ? '1' : '',
    }, { preserveState: true, preserveScroll: true });
}

function resetFilters() {
    filterMeal.value = '';
    filterSchool.value = '';
    filterDate.value = '';
    filterStatus.value = '';
    filterExtraOnly.value = false;
    searchQuery.value = '';
    currentPage.value = 1;
    router.get(`${base}/food-coupons`, {}, { preserveState: true, preserveScroll: true });
}

function mealPillClass(meal) {
    const map = {
        breakfast: 'bg-amber-100 text-amber-800 border border-amber-300',
        lunch: 'bg-emerald-100 text-emerald-800 border border-emerald-300',
        dinner: 'bg-indigo-100 text-indigo-800 border border-indigo-300',
        snacks: 'bg-pink-100 text-pink-800 border border-pink-300',
        tea: 'bg-orange-100 text-orange-800 border border-orange-300',
        other: 'bg-slate-100 text-slate-700 border border-slate-300',
    };
    return map[meal] || map.other;
}

function issueCoupons() {
    issuingCatering.value = true;
    router.post(`${base}/food-coupons/issue`, {}, {
        preserveScroll: true,
        onFinish: () => { issuingCatering.value = false; },
    });
}

function issueFromBill() {
    issuingBill.value = true;
    router.post(`${base}/food-coupons/issue-from-bill`, {}, {
        preserveScroll: true,
        onFinish: () => { issuingBill.value = false; },
    });
}

function redeem(id) {
    router.post(`${base}/food-coupons/${id}/redeem`, {}, { preserveScroll: true });
}

function submitExtraCoupons() {
    generatingExtra.value = true;
    router.post(`${base}/food-coupons/generate-extra`, extraForm, {
        preserveScroll: true,
        onSuccess: () => {
            showExtraModal.value = false;
        },
        onFinish: () => {
            generatingExtra.value = false;
        },
    });
}

function submitUngenerate() {
    ungenerating.value = true;
    const payload = {
        scope: ungenerateForm.scope,
        meal_type: ungenerateForm.meal_type || null,
        valid_date: ungenerateForm.valid_date || null,
        coupon_ids: ungenerateForm.scope === 'selected' ? selectedIds.value : null,
    };

    router.post(`${base}/food-coupons/ungenerate`, payload, {
        preserveScroll: true,
        onSuccess: () => {
            showUngenerateModal.value = false;
            selectedIds.value = [];
        },
        onFinish: () => {
            ungenerating.value = false;
        },
    });
}

function submitTemplateBg() {
    if (!bgFileInput.value?.files?.[0]) return;
    uploadingBg.value = true;

    const formData = new FormData();
    formData.append('background_image', bgFileInput.value.files[0]);

    router.post(`${base}/food-coupons/template-background`, formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            showBgModal.value = false;
        },
        onFinish: () => {
            uploadingBg.value = false;
        },
    });
}

function removeTemplateBg() {
    if (!confirm('Are you sure you want to remove the template background image? Coupons will print with the default vector layout.')) {
        return;
    }
    removingBg.value = true;
    router.delete(`${base}/food-coupons/template-background`, {
        preserveScroll: true,
        onSuccess: () => {
            showBgModal.value = false;
        },
        onFinish: () => {
            removingBg.value = false;
        },
    });
}

const matchedPrintCouponsCount = computed(() => {
    let list = props.couponMatrix && props.couponMatrix.length ? props.couponMatrix : props.coupons || [];
    if (printMeal.value) {
        list = list.filter(c => (c.m || c.meal_type) === printMeal.value);
    }
    if (printSchool.value) {
        list = list.filter(c => (c.s || c.school_id) === printSchool.value);
    }
    if (printDate.value) {
        list = list.filter(c => (c.d || c.valid_date) === printDate.value);
    }
    if (printExtraOnly.value) {
        list = list.filter(c => Boolean(c.e !== undefined ? c.e : c.is_extra));
    }
    return list.filter(c => (c.st || c.status) === 'issued').length;
});

const printUrl = computed(() => {
    const params = new URLSearchParams();
    if (printPerSheet.value) params.set('per_sheet', String(printPerSheet.value));
    if (printMeal.value) params.set('meal_type', printMeal.value);
    if (printSchool.value) params.set('school_id', printSchool.value);
    if (printDate.value) params.set('valid_date', printDate.value);
    if (printExtraOnly.value) params.set('extra_only', '1');

    const qs = params.toString();
    return `${base}/food-coupons/print${qs ? '?' + qs : ''}`;
});

const printPreviewUrl = computed(() => {
    const params = new URLSearchParams();
    if (printPerSheet.value) params.set('per_sheet', String(printPerSheet.value));
    if (printMeal.value) params.set('meal_type', printMeal.value);
    if (printSchool.value) params.set('school_id', printSchool.value);
    if (printDate.value) params.set('valid_date', printDate.value);
    if (printExtraOnly.value) params.set('extra_only', '1');
    params.set('preview', '1');

    const qs = params.toString();
    return `${base}/food-coupons/print${qs ? '?' + qs : ''}`;
});

function handlePrintDownload() {
    if (matchedPrintCouponsCount.value > 0) {
        showPrintModal.value = false;
    }
}

// Layout Builder State & Handlers
const activeElement = ref('qr_box');
const savingLayout = ref(false);
const saveAsSahodayaDefault = ref(false);

const defaultCoordinates = {
    qr_box: { top: 24.0, left: 76.0, width: 21.0, height: 54.0, show_border: false },
    stub_serial: { top: 6.5, left: 75.0, width: 23.0, font_size: 8.0, color: '#0f172a', show: true },
    stub_token: { top: 85.0, left: 75.0, width: 23.0, font_size: 6.8, color: '#0f172a', show: true },
    meal_badge: { top: 47.0, left: 4.5, font_size: 5.5, show: true },
    date_meta: { top: 47.0, left: 20.0, font_size: 5.8, color: '#334155', show: true },
    school_name: { top: 59.0, left: 4.5, max_width: 61.0, font_size: 6.2, color: '#0f172a', show: true },
    voucher_serial: { top: 73.0, left: 48.0, font_size: 7.5, color: '#1e3a8a', style: 'pill', show: true },
    fallback_title: { top: 73.0, left: 4.5, font_size: 10.5, color: '#1e3a8a', show: true },
};

const getInitialLayout = () => {
    const incoming = (props.foodCouponLayout && Object.keys(props.foodCouponLayout).length > 0)
        ? props.foodCouponLayout
        : (props.defaultLayout && Object.keys(props.defaultLayout).length > 0 ? props.defaultLayout : {});
    return {
        ...JSON.parse(JSON.stringify(defaultCoordinates)),
        ...JSON.parse(JSON.stringify(incoming)),
    };
};

const layoutForm = reactive(getInitialLayout());

function applyKochiMetroPreset() {
    const preset = {
        qr_box: { top: 24.5, left: 72.0, width: 20.5, height: 51.5, show_border: false },
        stub_serial: { top: 8.5, left: 70.0, width: 24.5, font_size: 8.5, color: '#0f172a', show: true },
        stub_token: { top: 84.0, left: 70.0, width: 24.5, font_size: 7.2, color: '#0f172a', show: true },
        meal_badge: { top: 48.5, left: 4.5, font_size: 6.0, show: true },
        date_meta: { top: 48.5, left: 21.0, font_size: 6.2, color: '#334155', show: true },
        school_name: { top: 59.5, left: 4.5, max_width: 61.0, font_size: 6.5, color: '#0f172a', show: true },
        voucher_serial: { top: 73.0, left: 50.5, font_size: 8.0, color: '#1e3a8a', style: 'pill', show: true },
        fallback_title: { top: 73.0, left: 4.5, font_size: 12.0, color: '#1e3a8a', show: true },
    };
    Object.assign(layoutForm, JSON.parse(JSON.stringify(preset)));
}

function applyPlainPaperPreset() {
    const preset = {
        qr_box: { top: 18.0, left: 70.0, width: 25.0, height: 58.0, show_border: true },
        stub_serial: { top: 6.0, left: 68.0, width: 28.0, font_size: 8.0, color: '#0f172a', show: true },
        stub_token: { top: 80.0, left: 68.0, width: 28.0, font_size: 6.5, color: '#0f172a', show: true },
        meal_badge: { top: 28.0, left: 4.5, font_size: 6.5, show: true },
        date_meta: { top: 28.0, left: 22.0, font_size: 6.0, color: '#334155', show: true },
        school_name: { top: 42.0, left: 4.5, max_width: 62.0, font_size: 6.5, color: '#0f172a', show: true },
        voucher_serial: { top: 70.0, left: 50.0, font_size: 7.5, color: '#1e3a8a', style: 'pill', show: true },
        fallback_title: { top: 70.0, left: 4.5, show: true },
    };
    Object.assign(layoutForm, JSON.parse(JSON.stringify(preset)));
}

function resetToDefaultLayout() {
    if (!confirm('Reset template layout coordinates to default settings?')) return;
    router.post(`${base}/food-coupons/reset-layout`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (props.defaultLayout && Object.keys(props.defaultLayout).length > 0) {
                Object.assign(layoutForm, JSON.parse(JSON.stringify(props.defaultLayout)));
            } else {
                applyKochiMetroPreset();
            }
        },
    });
}

function submitSaveLayout() {
    savingLayout.value = true;
    router.post(`${base}/food-coupons/layout`, {
        layout: layoutForm,
        save_as_sahodaya_default: saveAsSahodayaDefault.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            savingLayout.value = false;
        },
    });
}
</script>
