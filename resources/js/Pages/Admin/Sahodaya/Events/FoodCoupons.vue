<template>
    <SahodayaEventsLayout :title="`${event.title} — Food Coupons`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Coupon Generator`" eyebrow="Operations"
                    :description="isPartitionedHub
                        ? 'Coupons are issued per region — pick a region below.'
                        : 'Generate, ungenerate, manage extra buffer coupons, and print 10 coupons per A4 sheet with QR codes.'" />

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
                    <button type="button" @click="showBgModal = true" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        Template Background
                        <span v-if="event.has_template_bg" class="w-2 h-2 rounded-full bg-emerald-500" title="Template active"></span>
                    </button>
                    <button type="button" @click="showPrintModal = true" class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        Print 10-per-Sheet PDF
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
        <Modal :show="showPrintModal" title="Print Food Coupons (10 per A4 Sheet)" subtitle="Generate high-resolution printable PDF sheets with QR codes." @close="showPrintModal = false">
            <div class="space-y-4">
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

                <div class="p-3 bg-indigo-50 border border-indigo-200 rounded-lg text-xs text-indigo-900">
                    <p>• 10 coupons per A4 sheet (2 columns × 5 rows).</p>
                    <p>• Includes unique QR code with decoded value underneath.</p>
                    <p>• Ready for guillotine cutting and distribution.</p>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="showPrintModal = false" class="px-4 py-2 border rounded-lg text-sm font-medium">Cancel</button>
                    <a :href="printUrl" target="_blank" @click="showPrintModal = false" class="btn-primary">
                        Download PDF
                    </a>
                </div>
            </div>
        </Modal>

        <!-- Modal 5: Live Coupon Card Preview -->
        <Modal :show="showPreviewModal" title="Food Coupon Card Preview" subtitle="Accurate preview of how individual coupons will print on A4 sheets (95mm × 40.63mm)." @close="showPreviewModal = false">
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
                    <div class="relative w-full max-w-[480px] aspect-[95/40.63] rounded-lg overflow-hidden border border-slate-300 shadow-lg bg-white select-none">
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
                        <div class="absolute left-[4.5%] top-[45.5%] w-[58%] h-[25%] z-10 flex flex-col justify-center overflow-hidden">
                            <div class="flex items-center gap-1.5 text-[9px] leading-none mb-1 whitespace-nowrap">
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
                        <div class="absolute left-[50.5%] top-[71.5%] z-10 font-mono font-bold text-[10px] text-sky-800 bg-sky-100 border border-sky-300 px-1.5 py-0.5 rounded leading-none whitespace-nowrap shadow-xs">
                            {{ previewCoupon.coupon_code || 'BF-0001' }}
                        </div>

                        <!-- Stub Area: Serial Code OUTSIDE above box -->
                        <div class="absolute left-[68%] top-[6%] w-[28%] text-center z-10 font-mono font-bold text-[10.5px] text-slate-900 tracking-wide whitespace-nowrap leading-none">
                            {{ previewCoupon.coupon_code || 'BF-0001' }}
                        </div>

                        <!-- Stub Area: QR Code Box: Fits QR FULLY inside the box -->
                        <div class="absolute left-[70.41%] top-[22.60%] w-[23.54%] h-[55.48%] z-10 flex items-center justify-center p-1 overflow-hidden">
                            <img v-if="sampleQrSrc" :src="sampleQrSrc" class="w-full h-full object-contain" alt="QR" />
                            <div v-else class="w-full h-full bg-slate-900 flex items-center justify-center text-white text-[7px] font-mono">QR CODE</div>
                        </div>

                        <!-- Stub Area: Decoded Token OUTSIDE below box -->
                        <div class="absolute left-[68%] top-[79.5%] w-[28%] text-center z-10 font-mono font-bold text-[8.5px] text-slate-800 tracking-wider whitespace-nowrap leading-none">
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

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="showPreviewModal = false" class="px-4 py-2 border rounded-lg text-sm font-medium">Close</button>
                    <button type="button" @click="showPreviewModal = false; showPrintModal = true" class="btn-primary">
                        Proceed to Print PDF
                    </button>
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
    filters: { type: Object, default: () => ({}) },
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

const printUrl = computed(() => {
    const params = new URLSearchParams();
    if (printMeal.value) params.set('meal_type', printMeal.value);
    if (printSchool.value) params.set('school_id', printSchool.value);
    if (printDate.value) params.set('valid_date', printDate.value);
    if (printExtraOnly.value) params.set('extra_only', '1');

    const qs = params.toString();
    return `${base}/food-coupons/print${qs ? '?' + qs : ''}`;
});
</script>
