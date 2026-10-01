<template>
    <SchoolAdminLayout :title="`${event.title} — Food Order`" :school="school" :show-header-title="false">
        <!-- Hero Header -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 sm:p-8 text-white shadow-xl mb-6">
            <div class="absolute -right-8 -bottom-8 w-44 h-44 rounded-full bg-indigo-500/10 blur-2xl pointer-events-none" />
            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-400/20 px-3 py-1 text-xs font-semibold text-amber-300 border border-amber-400/30">
                            <span>🍱</span> Food & Catering
                        </span>
                        <span v-if="bill" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                              :class="bill.status === 'settled' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-blue-500/20 text-blue-300 border border-blue-500/30'">
                            Bill {{ bill.status === 'settled' ? 'Settled' : 'Open' }}
                        </span>
                        <span v-if="pendingPaymentsCount > 0"
                              class="inline-flex items-center gap-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 px-2.5 py-0.5 text-xs font-medium">
                            <span aria-hidden="true">⏳</span> {{ pendingPaymentsCount }} payment review pending
                        </span>
                        <span v-if="foodOrderOpensAt || foodOrderClosesAt"
                              class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                              :class="orderingStatus === 'upcoming'
                                  ? 'bg-sky-500/20 text-sky-200 border-sky-400/30'
                                  : orderingOpen
                                      ? 'bg-white/10 text-slate-200 border-white/20'
                                      : 'bg-rose-500/20 text-rose-200 border-rose-400/30'">
                            {{ orderingStatus === 'upcoming'
                                ? `Orders open ${formatCutoff(foodOrderOpensAt)}`
                                : orderingOpen && foodOrderClosesAt
                                    ? `Orders close ${formatCutoff(foodOrderClosesAt)}`
                                    : 'Ordering closed' }}
                        </span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">{{ event.title }}</h1>
                    <p class="text-sm text-slate-300 mt-1 max-w-2xl">
                        Pre-order meals and refreshments for your school contingent. {{ payeeLabel }}.
                    </p>
                    <div class="mt-3">
                        <EventHierarchyBadge :hierarchy="hierarchy" />
                    </div>
                </div>

                <!-- Quick Contingent Stats -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-white/10 backdrop-blur-md rounded-xl p-3 sm:p-4 border border-white/15 shrink-0 text-center">
                    <div>
                        <p class="text-xs text-slate-300 font-medium">Items Ordered</p>
                        <p class="text-xl sm:text-2xl font-black text-white mt-0.5">{{ totalOrderedCount }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-300 font-medium">Order Total</p>
                        <p class="text-xl sm:text-2xl font-black text-amber-300 mt-0.5">₹{{ (bill ? Number(bill.amount_total) : 0).toFixed(0) }}</p>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <p class="text-xs text-slate-300 font-medium">Balance Due</p>
                        <p class="text-xl sm:text-2xl font-black mt-0.5"
                           :class="bill && Number(bill.balance_due) > 0 ? 'text-rose-300' : 'text-emerald-300'">
                            ₹{{ (bill ? Number(bill.balance_due) : 0).toFixed(0) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="bill && bill.status !== 'open'" class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50/90 p-4 text-amber-800 text-sm mb-6 shadow-xs">
            <span class="text-xl shrink-0" aria-hidden="true">🔒</span>
            <div>
                <strong class="font-semibold">This bill is settled.</strong> Ordering is locked. Please contact the Sahodaya administration if you need to modify your contingent's food requirements.
            </div>
        </div>

        <div v-else-if="orderingStatus === 'upcoming'" class="flex items-center gap-3 rounded-xl border border-sky-200 bg-sky-50/90 p-4 text-sky-800 text-sm mb-6 shadow-xs" role="status">
            <span class="text-xl shrink-0" aria-hidden="true">🕒</span>
            <div>
                <strong class="font-semibold">Food ordering has not opened yet.</strong>
                Ordering starts {{ formatCutoff(foodOrderOpensAt) }}. You can review the menu in the meantime.
            </div>
        </div>

        <div v-else-if="!orderingOpen" class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50/90 p-4 text-rose-800 text-sm mb-6 shadow-xs" role="status">
            <span class="text-xl shrink-0" aria-hidden="true">⏱️</span>
            <div>
                <strong class="font-semibold">Food ordering is closed.</strong>
                The cutoff was {{ formatCutoff(foodOrderClosesAt) }}. You can still review your order and payment history below.
            </div>
        </div>

        <!-- Filter & Navigation Bar -->
        <div class="sticky top-2 z-20 bg-white/95 backdrop-blur-md rounded-2xl border border-slate-200/90 p-3 sm:p-4 shadow-sm mb-6 space-y-3">
            <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                <!-- Search bar -->
                <div class="relative flex-1 max-w-md">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input v-model="searchQuery" type="text"
                           class="field pl-9 pr-8 text-sm w-full"
                           aria-label="Search food items"
                           placeholder="Search food items (e.g. Biriyani, Idli, Tea)…">
                    <button v-if="searchQuery" type="button" aria-label="Clear food search" @click="searchQuery = ''"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                        ×
                    </button>
                </div>

                <!-- Diet Filter Buttons -->
                <div class="flex items-center gap-1.5 self-start md:self-auto bg-slate-100 p-1 rounded-xl shrink-0">
                    <button type="button" @click="selectedDiet = 'all'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                            :class="selectedDiet === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                        All Dishes
                    </button>
                    <button type="button" @click="selectedDiet = 'veg'"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                            :class="selectedDiet === 'veg' ? 'bg-white text-emerald-800 shadow-xs ring-1 ring-emerald-200' : 'text-slate-600 hover:text-emerald-700'">
                        <span class="w-2.5 h-2.5 rounded-[2px] border border-emerald-600 flex items-center justify-center p-0.5">
                            <span class="w-1 h-1 rounded-full bg-emerald-600"></span>
                        </span>
                        Veg Only
                    </button>
                    <button type="button" @click="selectedDiet = 'non-veg'"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                            :class="selectedDiet === 'non-veg' ? 'bg-white text-rose-800 shadow-xs ring-1 ring-rose-200' : 'text-slate-600 hover:text-rose-700'">
                        <span class="w-2.5 h-2.5 rounded-[2px] border border-rose-600 flex items-center justify-center p-0.5">
                            <span class="w-1 h-1 rounded-full bg-rose-600"></span>
                        </span>
                        Non-Veg
                    </button>
                </div>
            </div>

            <p v-if="allDates.length > 1 || Object.keys(mealTypes).length > 3"
               class="sm:hidden text-[10px] font-semibold text-indigo-600 text-right">
                Swipe filter rows to see more →
            </p>

            <!-- Date Selector Tabs (if multiple dates) -->
            <div v-if="allDates.length > 1" class="flex items-center gap-2 overflow-x-auto pb-1 border-t border-slate-100 pt-2.5" role="group" aria-label="Filter by menu date">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider shrink-0 mr-1">Date:</span>
                <button type="button" @click="selectedDate = 'all'"
                        class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition"
                        :class="selectedDate === 'all' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                    All Dates ({{ allDates.length }} days)
                </button>
                <button v-for="d in allDates" :key="d" type="button" @click="selectedDate = d"
                        class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition"
                        :class="selectedDate === d ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                    📅 {{ formatCalendarDate(d) }}
                </button>
            </div>

            <!-- Meal Type Selector Pills -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 border-t border-slate-100 pt-2.5" role="group" aria-label="Filter by meal type">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider shrink-0 mr-1">Meal:</span>
                <button type="button" @click="selectedMeal = 'all'"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition"
                        :class="selectedMeal === 'all' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-200/60'">
                    All Meals
                </button>
                <button v-for="(label, key) in mealTypes" :key="key" type="button" @click="selectedMeal = key"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition"
                        :class="selectedMeal === key ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-200/60'">
                    <span>{{ mealIcon(key) }}</span>
                    <span>{{ label }}</span>
                </button>
            </div>
        </div>

        <!-- Main Layout: Menu (Left 7 cols) & Right Order / Payment Panel (Right 5 cols) -->
        <div class="grid lg:grid-cols-12 gap-6 lg:gap-8 items-start">
            <!-- Left: Food Menu Items -->
            <div class="lg:col-span-7 xl:col-span-7 space-y-8">
                <div v-for="group in filteredGroupedMenu" :key="group.date" class="space-y-6">
                    <!-- Date Section Header -->
                    <div class="flex flex-wrap items-center gap-3 border-b-2 border-slate-200 pb-2">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm">
                            📅
                        </div>
                        <div>
                            <h2 class="text-lg font-extrabold text-slate-900">{{ formatCalendarDate(group.date) }}</h2>
                            <p class="text-xs text-slate-500">{{ group.meals.reduce((acc, m) => acc + m.items.length, 0) }} item(s) available</p>
                        </div>
                        <span v-if="dayWindowFor(group.date)" class="ml-auto inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-bold"
                              :class="dayWindowClass(group.date)">
                            {{ dayWindowLabel(group.date) }}
                        </span>
                    </div>

                    <!-- Meal Groups inside Date -->
                    <div v-for="mealGroup in group.meals" :key="mealGroup.mealType" class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-700 bg-slate-100 px-3 py-1.5 rounded-lg">
                                <span class="text-base" aria-hidden="true">{{ mealIcon(mealGroup.mealType) }}</span>
                                <span>{{ mealTypes[mealGroup.mealType] || mealGroup.mealType }}</span>
                                <span class="text-slate-400 font-normal">({{ mealGroup.items.length }})</span>
                            </div>
                        </div>

                        <!-- 2-Column Cards Grid -->
                        <div class="grid sm:grid-cols-2 gap-4">
                            <FoodItemCard v-for="item in mealGroup.items" :key="item.id"
                                          :name="item.name" :description="item.description" :price="Number(item.price)"
                                          :icon="mealIcon(mealGroup.mealType)" :badges="badgesFor(item)"
                                          :in-order="orderedQty(item.id) > 0">
                                <template #actions-left>
                                    <span v-if="orderedQty(item.id) > 0"
                                          class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200/80 px-2 py-0.5 rounded-md">
                                        ✓ {{ orderedQty(item.id) }} in tray
                                    </span>
                                </template>

                                <template #actions>
                                    <template v-if="canOrderItem(item) && (!item.max_per_school || remainingFor(item) > 0)">
                                        <div class="flex items-center gap-1.5">
                                            <QuantityStepper :model-value="qty[item.id] ?? 1" :max="item.max_per_school ? remainingFor(item) : 999"
                                                              @update:model-value="(val) => (qty[item.id] = val)" />
                                            <button type="button"
                                                    class="inline-flex items-center justify-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold transition shadow-xs"
                                                    :class="orderedQty(item.id) > 0
                                                        ? 'bg-slate-900 hover:bg-slate-800 text-white'
                                                        : 'bg-indigo-600 hover:bg-indigo-700 text-white'"
                                                    :disabled="itemForm.processing && itemForm.menu_item_id === item.id"
                                                    @click="addItem(item)">
                                                <span v-if="itemForm.processing && itemForm.menu_item_id === item.id">…</span>
                                                <span v-else>{{ orderedQty(item.id) > 0 ? '+ Add more' : '+ Add' }}</span>
                                            </button>
                                        </div>
                                    </template>
                                    <span v-else class="text-xs font-semibold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-md border border-amber-200">
                                        {{ orderUnavailableLabel(item) }}
                                    </span>
                                </template>
                            </FoodItemCard>
                        </div>
                    </div>
                </div>

                <div v-if="filteredGroupedMenu.length === 0" class="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-12 text-center">
                    <span class="text-4xl" aria-hidden="true">🍽️</span>
                    <h3 class="text-base font-bold text-slate-800 mt-3">
                        {{ menuItems.length === 0 ? 'Food menu not published yet' : 'No matching food items' }}
                    </h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        {{ menuItems.length === 0
                            ? 'The event organizer has not published food items for this event. Please check back later.'
                            : 'No dishes match your selected filters. Try clearing your search query or selecting a different meal or diet filter.' }}
                    </p>
                    <button v-if="menuItems.length > 0" type="button" @click="resetFilters" class="btn-secondary text-xs mt-4">
                        Reset Filters
                    </button>
                </div>
            </div>

            <!-- Right Column: Sticky Order & Payment Panel with Sub-Tabs -->
            <div id="your-order" class="lg:col-span-5 xl:col-span-5 space-y-5 lg:sticky lg:top-4">
                <!-- Panel Tab Switcher -->
                <div class="flex rounded-2xl bg-slate-200/80 p-1.5 shadow-inner">
                    <button type="button" @click="rightPanelTab = 'order'"
                            class="flex-1 py-2.5 rounded-xl text-sm font-extrabold transition flex items-center justify-center gap-2"
                            :class="rightPanelTab === 'order' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'">
                        <span class="text-base">🛒</span>
                        <span>Order Tray</span>
                        <span v-if="totalOrderedCount > 0" class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full font-bold">
                            {{ totalOrderedCount }}
                        </span>
                    </button>
                    <button type="button" @click="rightPanelTab = 'history'"
                            class="flex-1 py-2.5 rounded-xl text-sm font-extrabold transition flex items-center justify-center gap-2"
                            :class="rightPanelTab === 'history' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'">
                        <span class="text-base">📜</span>
                        <span>Payment History</span>
                        <span v-if="payments.length > 0" class="bg-slate-100 text-slate-700 text-xs px-2 py-0.5 rounded-full font-bold"
                              :class="pendingPaymentsCount > 0 ? 'bg-amber-100 text-amber-800' : ''">
                            {{ payments.length }}
                        </span>
                    </button>
                </div>

                <!-- ============================================== -->
                <!-- SUB-TAB 1: ORDER TRAY & CHECKOUT               -->
                <!-- ============================================== -->
                <div v-if="rightPanelTab === 'order'" class="space-y-5">
                    <!-- Cart Container -->
                    <div class="rounded-2xl border border-slate-200 bg-white shadow-md overflow-hidden">
                        <!-- Cart Header -->
                        <div class="bg-gradient-to-r from-slate-900 to-indigo-950 p-4 sm:p-5 text-white flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">🛒</span>
                                <div>
                                    <h3 class="font-extrabold text-base sm:text-lg leading-tight">Order Tray</h3>
                                    <p class="text-xs text-slate-300">Contingent pre-ordered items</p>
                                </div>
                            </div>
                            <span class="rounded-full bg-white/20 px-3 py-1 text-xs sm:text-sm font-extrabold text-white">
                                {{ totalOrderedCount }} item{{ totalOrderedCount === 1 ? '' : 's' }}
                            </span>
                        </div>

                        <!-- Items List -->
                        <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                            <div v-if="!orderItems.length" class="p-8 text-center">
                                <div class="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center text-3xl mx-auto mb-3 text-slate-400">
                                    🍽️
                                </div>
                                <p class="font-bold text-base text-slate-800">Your tray is empty</p>
                                <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-xs mx-auto">
                                    Select dishes from the menu to pre-order food for your students and accompanying staff.
                                </p>
                            </div>

                            <div v-for="oi in orderItems" :key="oi.id" class="p-4 hover:bg-slate-50/80 transition flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <VegBadge :name="oi.item_name" />
                                        <p class="truncate text-sm sm:text-base font-bold text-slate-900">{{ oi.item_name }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs sm:text-sm text-slate-500 mt-1">
                                        <span>{{ formatCalendarDate(oi.menu_date) }}</span>
                                        <span>·</span>
                                        <span class="font-medium text-slate-700">{{ oi.quantity }} × ₹{{ Number(oi.unit_price).toFixed(2) }}</span>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm sm:text-base font-extrabold text-slate-900">₹{{ Number(oi.line_total).toFixed(2) }}</p>
                                    <button v-if="canOrderItem(oi)" type="button"
                                            class="text-xs font-bold text-rose-600 hover:text-rose-800 mt-1 inline-block"
                                            @click="removeItem(oi)">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Financial Summary -->
                        <div v-if="bill" class="border-t border-slate-200 bg-slate-50/70 p-4 sm:p-5 space-y-3">
                            <div class="flex justify-between text-sm text-slate-600">
                                <span>Subtotal</span>
                                <span class="font-bold text-slate-900">₹{{ Number(bill.amount_total).toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between text-sm text-slate-600">
                                <span>Amount Paid</span>
                                <span class="font-bold text-emerald-700">₹{{ Number(bill.amount_paid).toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between text-base font-black pt-2.5 border-t border-slate-200">
                                <span class="text-slate-900">Balance Due</span>
                                <span :class="Number(bill.balance_due) > 0 ? 'text-rose-700' : 'text-emerald-700'">
                                    ₹{{ Number(bill.balance_due).toFixed(2) }}
                                </span>
                            </div>

                            <!-- Progress Bar -->
                            <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden mt-1.5">
                                <div class="bg-emerald-500 h-2 rounded-full transition-all"
                                     :style="{ width: `${paymentProgress}%` }"></div>
                            </div>
                            <p class="text-xs text-right text-slate-500 font-medium">{{ paymentProgress.toFixed(0) }}% settled</p>
                        </div>
                    </div>

                    <!-- Where to Pay Card -->
                    <div v-if="payeeDetails" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="font-black text-base sm:text-lg text-slate-900 flex items-center gap-2 shrink-0">
                                <span class="text-xl">💳</span> Where to pay
                            </h4>
                            <span class="text-xs text-slate-600 font-bold truncate max-w-[16rem] text-right bg-slate-100 px-2.5 py-1 rounded-lg" :title="payeeLabel">
                                {{ payeeLabel }}
                            </span>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50/90 p-4 sm:p-5 space-y-3.5 text-sm">
                            <div v-if="payeeDetails.bank_name" class="flex items-center justify-between gap-3">
                                <span class="text-xs sm:text-sm text-slate-500 font-bold uppercase tracking-wider shrink-0">Bank</span>
                                <span class="font-extrabold text-slate-900 text-sm sm:text-base text-right truncate">{{ payeeDetails.bank_name }}</span>
                            </div>
                            <div v-if="payeeDetails.account_no" class="flex items-center justify-between gap-3">
                                <span class="text-xs sm:text-sm text-slate-500 font-bold uppercase tracking-wider shrink-0">A/C Number</span>
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="font-mono font-black text-base sm:text-lg text-slate-900 text-right tracking-wider break-all select-all">{{ payeeDetails.account_no }}</span>
                                    <button type="button" @click="copyText(payeeDetails.account_no, 'Account No')"
                                            class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-indigo-700 border border-slate-200 hover:bg-indigo-50 shadow-2xs transition">
                                        {{ copiedField === 'Account No' ? '✓ Copied' : 'Copy' }}
                                    </button>
                                </div>
                            </div>
                            <div v-if="payeeDetails.ifsc" class="flex items-center justify-between gap-3">
                                <span class="text-xs sm:text-sm text-slate-500 font-bold uppercase tracking-wider shrink-0">IFSC Code</span>
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="font-mono font-black text-base sm:text-lg text-slate-900 text-right tracking-wider select-all">{{ payeeDetails.ifsc }}</span>
                                    <button type="button" @click="copyText(payeeDetails.ifsc, 'IFSC')"
                                            class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-indigo-700 border border-slate-200 hover:bg-indigo-50 shadow-2xs transition">
                                        {{ copiedField === 'IFSC' ? '✓ Copied' : 'Copy' }}
                                    </button>
                                </div>
                            </div>
                            <div v-if="displayUpi" class="pt-2 border-t border-slate-200 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs sm:text-sm text-slate-500 font-bold uppercase tracking-wider shrink-0">UPI ID / VPA</span>
                                    <button type="button" @click="copyText(displayUpi, 'UPI')"
                                            class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-indigo-700 border border-slate-200 hover:bg-indigo-50 shadow-2xs transition">
                                        {{ copiedField === 'UPI' ? '✓ Copied' : 'Copy UPI' }}
                                    </button>
                                </div>
                                <div class="font-mono font-black text-sm sm:text-base text-indigo-950 bg-white border border-indigo-200 px-3.5 py-2 rounded-xl break-all select-all shadow-2xs">
                                    {{ displayUpi }}
                                </div>
                            </div>
                        </div>

                        <!-- QR Code with preview modal -->
                        <div v-if="payeeDetails.qr_code_url && !qrImageFailed" class="flex items-center gap-4 pt-3 border-t border-slate-100">
                            <div class="relative shrink-0 group cursor-pointer" @click="showQrModal = true">
                                <img :src="payeeDetails.qr_code_url" alt="Payment QR Code"
                                     class="w-28 h-28 sm:w-36 sm:h-36 object-contain rounded-2xl border-2 border-slate-200 bg-white p-2 group-hover:ring-2 group-hover:ring-indigo-500 transition shadow-xs"
                                     @error="qrImageFailed = true">
                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 rounded-2xl transition flex items-center justify-center">
                                    <span class="opacity-0 group-hover:opacity-100 transition bg-slate-900/80 text-white text-[11px] font-bold px-2 py-0.5 rounded-md">🔍 Zoom</span>
                                </div>
                            </div>
                            <div class="min-w-0 space-y-1.5">
                                <p class="text-base sm:text-lg font-extrabold text-slate-900 leading-snug">Scan & Pay via UPI</p>
                                <p class="text-xs sm:text-sm text-slate-500 leading-snug">Google Pay, PhonePe, Paytm, BHIM</p>
                                <div class="flex flex-wrap items-center gap-2 pt-1">
                                    <button type="button" @click="showQrModal = true"
                                            class="text-xs sm:text-sm text-indigo-600 font-bold hover:underline inline-flex items-center gap-1">
                                        <span>View larger QR</span>
                                        <span>↗</span>
                                    </button>
                                    <a v-if="upiDeepLink" :href="upiDeepLink"
                                       class="sm:hidden inline-flex items-center gap-1 text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                                        Open UPI App ↗
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Focused Payment Submission Entry Point -->
                    <div v-if="bill && Number(bill.balance_due) > 0 && canOrder" class="rounded-2xl border-2 border-indigo-200 bg-gradient-to-br from-indigo-50/80 to-blue-50/40 p-5 shadow-sm space-y-3.5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h4 class="font-extrabold text-base text-slate-900">Ready to record your payment?</h4>
                                <p class="text-xs sm:text-sm text-slate-600 mt-1 leading-relaxed">
                                    Upload the receipt or transaction details after completing the transfer.
                                </p>
                            </div>
                            <span class="shrink-0 text-sm font-black text-rose-700 bg-white px-3 py-1 rounded-lg border border-rose-200 shadow-2xs">
                                ₹{{ Number(bill.balance_due).toFixed(2) }} due
                            </span>
                        </div>
                        <button type="button" class="btn-primary w-full text-sm font-extrabold py-3 justify-center shadow-md hover:shadow-lg transition" @click="openPaymentModal">
                            Upload Payment Proof
                        </button>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- SUB-TAB 2: PAYMENT HISTORY & RECEIPTS          -->
                <!-- ============================================== -->
                <div v-if="rightPanelTab === 'history'" class="space-y-4">
                    <!-- Financial Status Card -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-extrabold text-base text-slate-900">Payment Status</h4>
                            <span v-if="bill" class="text-xs font-extrabold px-3 py-1 rounded-full"
                                  :class="Number(bill.balance_due) <= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                {{ Number(bill.balance_due) <= 0 ? 'Fully Paid' : 'Balance Due' }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-center">
                            <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-100">
                                <p class="text-xs text-slate-500 font-bold uppercase tracking-wider">Total Paid</p>
                                <p class="text-lg sm:text-xl font-black text-emerald-700 mt-1">₹{{ (bill ? Number(bill.amount_paid) : 0).toFixed(2) }}</p>
                            </div>
                            <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-100">
                                <p class="text-xs text-slate-500 font-bold uppercase tracking-wider">Balance Due</p>
                                <p class="text-lg sm:text-xl font-black text-rose-700 mt-1">₹{{ (bill ? Number(bill.balance_due) : 0).toFixed(2) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Submissions List -->
                    <div class="space-y-3">
                        <div v-for="p in payments" :key="p.id"
                             class="rounded-2xl border bg-white p-4 shadow-sm space-y-2.5 transition"
                             :class="p.status === 'approved' ? 'border-emerald-200' : (p.status === 'rejected' ? 'border-rose-200' : 'border-amber-200')">
                            <!-- Top Row: Receipt & Status -->
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span v-if="p.receipt_number" class="font-mono text-[11px] font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
                                        Receipt: {{ p.receipt_number }}
                                    </span>
                                    <span v-else class="text-[11px] text-slate-400">Payment submission</span>
                                    <p class="text-base font-black text-slate-900 mt-1">₹{{ Number(p.amount).toFixed(2) }}</p>
                                </div>
                                <span :class="statusBadgeClass(p.status)">
                                    {{ statusLabel(p.status) }}
                                </span>
                            </div>

                            <!-- Meta Details -->
                            <div class="text-xs text-slate-600 space-y-1 pt-1 border-t border-slate-100">
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Mode:</span>
                                    <span class="font-medium capitalize">{{ p.payment_mode.replace('_', ' ') }}</span>
                                </div>
                                <div v-if="p.transaction_ref" class="flex justify-between">
                                    <span class="text-slate-400">Ref / UTR:</span>
                                    <span class="font-mono font-bold text-slate-800">{{ p.transaction_ref }}</span>
                                </div>
                                <div v-if="p.bank_name" class="flex justify-between">
                                    <span class="text-slate-400">Bank:</span>
                                    <span>{{ p.bank_name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Date:</span>
                                    <span>{{ formatCalendarDate(p.received_at || p.submitted_at) }}</span>
                                </div>
                            </div>

                            <!-- Rejection Reason Warning (if rejected) -->
                            <div v-if="p.status === 'rejected' && p.rejection_reason"
                                 class="rounded-xl border border-rose-200 bg-rose-50 p-2.5 text-xs text-rose-800 space-y-1">
                                <p class="font-bold flex items-center gap-1">
                                    <span>⚠️</span> Rejection Note from Billing Team:
                                </p>
                                <p class="leading-relaxed">{{ p.rejection_reason }}</p>
                                <button type="button" @click="openPaymentModal"
                                        class="text-xs font-bold text-rose-700 underline mt-1 block">
                                    Submit a new payment proof →
                                </button>
                            </div>

                            <!-- View Proof Button -->
                            <div v-if="p.has_proof" class="pt-1 flex justify-end">
                                <button type="button" @click="openSchoolProofModal(p)"
                                        class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline">
                                    <span>🔍</span> View Submitted Proof
                                </button>
                            </div>
                        </div>

                        <div v-if="!payments.length" class="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-8 text-center">
                            <span class="text-3xl block mb-2">📜</span>
                            <p class="font-bold text-sm text-slate-800">No payment history yet</p>
                            <p class="text-xs text-slate-500 mt-1">
                                Once you submit payment proof via the Order Tray, verification updates and receipts will show up here.
                            </p>
                            <button type="button" @click="rightPanelTab = 'order'" class="btn-secondary text-xs mt-3">
                                Go to Order Tray
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Keeps the final controls clear of the fixed mobile tray bar. -->
        <div v-if="totalOrderedCount > 0" class="h-20 lg:hidden" aria-hidden="true"></div>

        <!-- Mobile Sticky Bottom Floating Cart Bar -->
        <div v-if="totalOrderedCount > 0" class="lg:hidden fixed bottom-3 inset-x-3 z-30">
            <button type="button" @click="showMobileCart = true"
                    class="flex items-center justify-between w-full rounded-2xl bg-slate-900 px-5 py-3.5 text-white shadow-2xl ring-2 ring-white/20 active:scale-[0.99] transition">
                <div class="flex items-center gap-2">
                    <span class="text-xl">🛒</span>
                    <span class="text-sm font-bold">{{ totalOrderedCount }} item{{ totalOrderedCount === 1 ? '' : 's' }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm font-extrabold text-amber-300">₹{{ (bill ? Number(bill.amount_total) : 0).toFixed(2) }}</span>
                    <span class="bg-indigo-600 text-white text-xs font-semibold px-2.5 py-1 rounded-lg">View Tray</span>
                </div>
            </button>
        </div>

        <!-- Mobile Cart Modal / Drawer -->
        <Modal :show="showMobileCart" title="Your Food Order Tray" size="lg" @close="showMobileCart = false">
            <div class="space-y-4">
                <div v-for="oi in orderItems" :key="oi.id" class="p-3 rounded-xl border border-slate-200 flex items-center justify-between gap-3">
                    <div>
                        <p class="font-bold text-sm text-slate-900">{{ oi.item_name }}</p>
                        <p class="text-xs text-slate-500">{{ formatCalendarDate(oi.menu_date) }} · {{ oi.quantity }} × ₹{{ Number(oi.unit_price).toFixed(2) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-sm text-slate-900">₹{{ Number(oi.line_total).toFixed(2) }}</p>
                        <button v-if="canOrderItem(oi)" type="button" class="text-xs text-rose-600 font-semibold mt-1" @click="removeItem(oi)">
                            Remove
                        </button>
                    </div>
                </div>

                <div v-if="bill" class="rounded-xl bg-slate-50 p-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span>Total</span>
                        <span class="font-bold">₹{{ Number(bill.amount_total).toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm text-emerald-700">
                        <span>Paid</span>
                        <span class="font-bold">₹{{ Number(bill.amount_paid).toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between text-base font-bold text-rose-700 pt-2 border-t">
                        <span>Balance Due</span>
                        <span>₹{{ Number(bill.balance_due).toFixed(2) }}</span>
                    </div>
                </div>

                <button v-if="bill && Number(bill.balance_due) > 0 && canOrder" type="button"
                        class="btn-primary w-full justify-center text-sm py-2.5" @click="openPaymentFromMobileCart">
                    Upload Payment Proof
                </button>
                <a v-else href="#your-order" @click="showMobileCart = false"
                   class="btn-primary w-full justify-center text-sm py-2.5">
                    View Payment & Bank Details ↓
                </a>
            </div>
        </Modal>

        <!-- Focused Payment Proof Form -->
        <Modal :show="showPaymentModal" title="Submit Payment Proof" size="md" @close="closePaymentModal">
            <form id="payment-proof-form" class="space-y-4" @submit.prevent="submitPayment">
                <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-3 text-xs text-indigo-900">
                    <span class="font-semibold">Balance due:</span>
                    ₹{{ (bill ? Number(bill.balance_due) : 0).toFixed(2) }}. Your submission will remain pending until the billing team verifies it.
                </div>

                <div class="grid sm:grid-cols-2 gap-3">
                    <FormField label="Amount (₹)" :error="paymentForm.errors.amount" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="paymentForm.amount" type="number" min="0.01" step="0.01"
                                   class="field text-sm w-full" :placeholder="bill ? Number(bill.balance_due).toFixed(2) : ''" required>
                        </template>
                    </FormField>
                    <FormField label="Payment Mode" :error="paymentForm.errors.payment_mode" required>
                        <template #default="{ id }">
                            <select :id="id" v-model="paymentForm.payment_mode" class="field text-sm w-full" required>
                                <option value="upi">UPI (GPay/PhonePe)</option>
                                <option value="bank_transfer">Bank Transfer (NEFT/IMPS)</option>
                                <option value="cash">Cash</option>
                                <option value="other">Other</option>
                            </select>
                        </template>
                    </FormField>
                </div>

                <FormField label="Transaction Ref / UTR" :error="paymentForm.errors.transaction_ref"
                           hint="Enter the reference shown by your bank or payment app.">
                    <template #default="{ id }">
                        <input :id="id" v-model="paymentForm.transaction_ref" type="text" class="field text-sm w-full font-mono"
                               placeholder="e.g. 324109482190">
                    </template>
                </FormField>

                <FormField label="Paid From Bank (Optional)" :error="paymentForm.errors.bank_name">
                    <template #default="{ id }">
                        <input :id="id" v-model="paymentForm.bank_name" type="text" class="field text-sm w-full"
                               placeholder="e.g. SBI, HDFC, Canara">
                    </template>
                </FormField>

                <FormField label="Proof (Screenshot or PDF)" :error="paymentForm.errors.proof"
                           hint="JPG, PNG, or PDF up to 5 MB." required>
                    <template #default="{ id }">
                        <input :id="id" ref="proofInput" type="file" accept=".pdf,.jpg,.jpeg,.png"
                               class="field text-sm w-full file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-slate-100"
                               required @change="onProofSelected">
                    </template>
                </FormField>

                <div v-if="proofPreviewUrl" class="p-2 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-3">
                    <img :src="proofPreviewUrl" alt="Selected payment proof preview" class="w-14 h-14 rounded object-cover border border-slate-200">
                    <div class="text-xs min-w-0">
                        <p class="font-medium text-slate-800 truncate">{{ paymentForm.proof?.name }}</p>
                        <p class="text-slate-500">Ready to upload</p>
                    </div>
                </div>

                <FormField label="Notes (Optional)" :error="paymentForm.errors.notes">
                    <template #default="{ id }">
                        <textarea :id="id" v-model="paymentForm.notes" rows="3" class="field text-sm w-full"
                                  placeholder="Any notes for the billing team…"></textarea>
                    </template>
                </FormField>
            </form>

            <template #footer>
                <button type="button" class="btn-secondary text-xs" :disabled="paymentForm.processing" @click="closePaymentModal">Cancel</button>
                <button type="submit" form="payment-proof-form" class="btn-primary text-xs font-bold"
                        :disabled="paymentForm.processing">
                    {{ paymentForm.processing ? 'Submitting proof…' : 'Submit for Verification' }}
                </button>
            </template>
        </Modal>

        <!-- QR Code Zoom Modal -->
        <Modal :show="showQrModal" title="UPI Payment QR Code" size="lg" @close="showQrModal = false">
            <div class="text-center p-4 sm:p-6 space-y-4">
                <div v-if="payeeDetails?.qr_code_url && !qrImageFailed" class="inline-block p-4 sm:p-5 rounded-3xl border-2 border-indigo-200 bg-white shadow-lg max-w-full">
                    <img :src="payeeDetails.qr_code_url" alt="UPI QR Code"
                         class="w-80 sm:w-[26rem] h-80 sm:h-[26rem] md:h-[30rem] object-contain mx-auto rounded-xl"
                         @error="qrImageFailed = true">
                </div>
                <div class="space-y-1.5 max-w-lg mx-auto">
                    <p class="font-black text-slate-900 text-base sm:text-xl tracking-tight">{{ payeeDetails?.name || 'Payment Payee' }}</p>
                    <div v-if="displayUpi" class="inline-flex items-center gap-2 bg-indigo-50 px-4 py-2 rounded-2xl border-2 border-indigo-200 mt-1 max-w-full shadow-2xs">
                        <span class="font-mono text-xs sm:text-base text-indigo-950 font-black break-all select-all tracking-wide">{{ displayUpi }}</span>
                        <button type="button" @click="copyText(displayUpi, 'UPI ID')"
                                class="shrink-0 px-3 py-1 rounded-lg bg-white border border-indigo-300 text-indigo-700 hover:bg-indigo-100 text-xs font-bold shadow-2xs transition">
                            {{ copiedField === 'UPI ID' ? '✓ Copied' : 'Copy' }}
                        </button>
                    </div>
                </div>

                <div v-if="payeeDetails?.account_no || payeeDetails?.ifsc" class="max-w-lg mx-auto text-left bg-slate-50/90 rounded-2xl p-4 sm:p-5 border-2 border-slate-200 text-xs sm:text-sm space-y-2.5 shadow-2xs">
                    <div v-if="payeeDetails.bank_name" class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 font-bold uppercase text-[11px] sm:text-xs tracking-wider shrink-0">Bank</span>
                        <span class="font-extrabold text-slate-900 text-sm sm:text-base text-right">{{ payeeDetails.bank_name }}</span>
                    </div>
                    <div v-if="payeeDetails.account_no" class="flex items-center justify-between gap-3 pt-2 border-t border-slate-200">
                        <span class="text-slate-500 font-bold uppercase text-[11px] sm:text-xs tracking-wider shrink-0">A/C No</span>
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="font-mono font-black text-base sm:text-lg text-slate-900 text-right tracking-wider select-all break-all">{{ payeeDetails.account_no }}</span>
                            <button type="button" @click="copyText(payeeDetails.account_no, 'A/C No')"
                                    class="shrink-0 px-2.5 py-1 rounded-lg bg-white border border-slate-300 text-indigo-700 hover:bg-indigo-50 text-xs font-bold shadow-2xs transition">
                                {{ copiedField === 'A/C No' ? '✓ Copied' : 'Copy' }}
                            </button>
                        </div>
                    </div>
                    <div v-if="payeeDetails.ifsc" class="flex items-center justify-between gap-3 pt-2 border-t border-slate-200">
                        <span class="text-slate-500 font-bold uppercase text-[11px] sm:text-xs tracking-wider shrink-0">IFSC</span>
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="font-mono font-black text-base sm:text-lg text-slate-900 text-right tracking-wider select-all">{{ payeeDetails.ifsc }}</span>
                            <button type="button" @click="copyText(payeeDetails.ifsc, 'IFSC')"
                                    class="shrink-0 px-2.5 py-1 rounded-lg bg-white border border-slate-300 text-indigo-700 hover:bg-indigo-50 text-xs font-bold shadow-2xs transition">
                                {{ copiedField === 'IFSC' ? '✓ Copied' : 'Copy' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="upiDeepLink" class="pt-1 max-w-lg mx-auto sm:hidden">
                    <a :href="upiDeepLink" class="btn-primary w-full text-xs font-bold py-2.5 justify-center shadow-xs">
                        Open in UPI App ↗
                    </a>
                </div>

                <p class="text-xs sm:text-sm text-slate-500 font-medium">Scan with Google Pay, PhonePe, Paytm, BHIM, or any UPI app to transfer.</p>
            </div>
        </Modal>

        <!-- School Payment Proof Preview Modal -->
        <Modal :show="schoolProofModalOpen" title="Submitted Payment Proof" size="md" @close="schoolProofModalOpen = false">
            <div v-if="selectedSchoolProofPayment" class="space-y-4">
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                    <div>
                        <span class="text-slate-500">Amount</span>
                        <p class="font-extrabold text-slate-900 text-sm">₹{{ Number(selectedSchoolProofPayment.amount).toFixed(2) }}</p>
                    </div>
                    <div class="text-right">
                        <span class="text-slate-500 block">Status</span>
                        <span :class="statusBadgeClass(selectedSchoolProofPayment.status)">
                            {{ statusLabel(selectedSchoolProofPayment.status) }}
                        </span>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 overflow-hidden bg-slate-100 flex items-center justify-center max-h-[26rem]">
                    <img :src="`${base}/payments/${selectedSchoolProofPayment.id}/proof`"
                         alt="Payment Proof"
                         class="max-h-[26rem] w-auto object-contain mx-auto">
                </div>

                <div class="flex justify-end pt-1">
                    <a :href="`${base}/payments/${selectedSchoolProofPayment.id}/proof`" target="_blank"
                       class="text-xs font-semibold text-indigo-600 hover:underline">
                        Open in New Tab ↗
                    </a>
                </div>
            </div>
        </Modal>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import EventHierarchyBadge from '@/Components/fest/EventHierarchyBadge.vue';
import FoodItemCard from '@/Components/food/FoodItemCard.vue';
import VegBadge from '@/Components/food/VegBadge.vue';
import QuantityStepper from '@/Components/food/QuantityStepper.vue';
import Modal from '@/Components/ui/Modal.vue';
import FormField from '@/Components/ui/FormField.vue';
import { mealIcon } from '@/support/mealIcons.js';
import { isNonVeg } from '@/support/dietDetector.js';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { formatCalendarDate } from '@/support/calendarDates.js';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    event: Object,
    hierarchy: { type: Object, default: null },
    menuItems: { type: Array, default: () => [] },
    mealTypes: { type: Object, default: () => ({}) },
    bill: { type: Object, default: null },
    orderItems: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    payeeLabel: { type: String, default: '' },
    payeeDetails: { type: Object, default: null },
    foodCutoffAt: { type: String, default: null },
    foodOrderOpensAt: { type: String, default: null },
    foodOrderClosesAt: { type: String, default: null },
    foodOrderDayWindows: { type: Object, default: () => ({}) },
    orderingStatus: { type: String, default: 'open' },
    orderingOpen: { type: Boolean, default: true },
});

const school = computed(() => usePage().props.school);
const base = computed(() => `/school-admin/${school.value?.id}/fest/${props.event.id}/food-order`);

const canOrder = computed(() => props.orderingOpen && (!props.bill || props.bill.status === 'open'));

function dayWindowFor(date) {
    return props.foodOrderDayWindows?.[date] ?? null;
}

function canOrderItem(item) {
    if (!canOrder.value) return false;
    const dayWindow = dayWindowFor(item.menu_date);
    return !dayWindow || dayWindow.status === 'open';
}

function dayWindowLabel(date) {
    const window = dayWindowFor(date);
    if (!window) return '';
    if (window.status === 'upcoming') return `Orders open ${formatCutoff(window.opens_at)}`;
    if (window.status === 'closed') return 'Ordering closed for this day';
    if (window.closes_at) return `Orders close ${formatCutoff(window.closes_at)}`;
    return 'Ordering open';
}

function dayWindowClass(date) {
    const status = dayWindowFor(date)?.status;
    if (status === 'closed') return 'border-rose-200 bg-rose-50 text-rose-700';
    if (status === 'upcoming') return 'border-sky-200 bg-sky-50 text-sky-700';
    return 'border-emerald-200 bg-emerald-50 text-emerald-700';
}

function orderUnavailableLabel(item) {
    if (props.orderingStatus === 'upcoming') return 'Ordering not open';
    if (!props.orderingOpen) return 'Ordering closed';
    if (props.bill && props.bill.status !== 'open') return 'Bill settled';
    const dayStatus = dayWindowFor(item.menu_date)?.status;
    if (dayStatus === 'upcoming') return 'This day is not open';
    if (dayStatus === 'closed') return 'This day is closed';
    return 'Limit reached';
}

// Sub-Tab state: 'order' | 'history'
const rightPanelTab = ref('order');

// Filters state
const searchQuery = ref('');
const selectedDiet = ref('all'); // all | veg | non-veg
const selectedDate = ref('all');
const selectedMeal = ref('all');

const showMobileCart = ref(false);
const showQrModal = ref(false);
const showPaymentModal = ref(false);
const copiedField = ref(null);
const qrImageFailed = ref(false);

watch(() => props.payeeDetails?.qr_code_url, () => {
    qrImageFailed.value = false;
});

const displayUpi = computed(() => {
    if (!props.payeeDetails?.upi) return '';
    const raw = props.payeeDetails.display_upi || props.payeeDetails.upi;
    if (typeof raw === 'string' && raw.startsWith('upi://')) {
        try {
            const url = new URL(raw);
            return url.searchParams.get('pa') || raw;
        } catch {
            const match = raw.match(/[?&]pa=([^&]+)/);
            return match ? decodeURIComponent(match[1]) : raw;
        }
    }
    return raw;
});

const upiDeepLink = computed(() => {
    if (props.payeeDetails?.upi_uri) return props.payeeDetails.upi_uri;
    const raw = props.payeeDetails?.upi;
    if (!raw) return '';
    if (typeof raw === 'string' && raw.startsWith('upi://')) return raw;
    const name = encodeURIComponent(props.payeeDetails.name || 'Food Payment');
    return `upi://pay?pa=${encodeURIComponent(raw)}&pn=${name}&cu=INR`;
});

const proofPreviewUrl = ref(null);
const proofInput = ref(null);
const schoolProofModalOpen = ref(false);
const selectedSchoolProofPayment = ref(null);

function openSchoolProofModal(p) {
    selectedSchoolProofPayment.value = p;
    schoolProofModalOpen.value = true;
}

function copyText(text, label) {
    if (!text) return;
    navigator.clipboard.writeText(text);
    copiedField.value = label;
    setTimeout(() => { copiedField.value = null; }, 2000);
}

function resetFilters() {
    searchQuery.value = '';
    selectedDiet.value = 'all';
    selectedDate.value = 'all';
    selectedMeal.value = 'all';
}

const qty = reactive({});
const itemForm = useForm({ menu_item_id: '', quantity: 1 });

const paymentForm = useForm({
    amount: '', payment_mode: 'upi', transaction_ref: '', bank_name: '', proof: null, notes: '',
});

function openPaymentModal() {
    if (!canOrder.value || !props.bill || Number(props.bill.balance_due) <= 0) return;
    if (!paymentForm.amount) paymentForm.amount = Number(props.bill.balance_due).toFixed(2);
    showPaymentModal.value = true;
}

function openPaymentFromMobileCart() {
    showMobileCart.value = false;
    openPaymentModal();
}

function closePaymentModal() {
    if (paymentForm.processing) return;
    showPaymentModal.value = false;
    paymentForm.clearErrors();
}

function onProofSelected(event) {
    const file = event.target.files[0] ?? null;
    paymentForm.proof = file;
    if (proofPreviewUrl.value) URL.revokeObjectURL(proofPreviewUrl.value);
    if (file && file.type.startsWith('image/')) {
        proofPreviewUrl.value = URL.createObjectURL(file);
    } else {
        proofPreviewUrl.value = null;
    }
}

function submitPayment() {
    paymentForm.post(`${base.value}/payments`, {
        preserveScroll: true,
        onSuccess: () => {
            paymentForm.reset();
            if (proofPreviewUrl.value) URL.revokeObjectURL(proofPreviewUrl.value);
            proofPreviewUrl.value = null;
            if (proofInput.value) proofInput.value.value = '';
            showPaymentModal.value = false;
            rightPanelTab.value = 'history';
        },
    });
}

const totalOrderedCount = computed(() => {
    return props.orderItems.reduce((acc, oi) => acc + (oi.quantity || 1), 0);
});

const pendingPaymentsCount = computed(() => {
    return props.payments.filter((p) => p.status === 'pending').length;
});

const paymentProgress = computed(() => {
    if (!props.bill || !Number(props.bill.amount_total)) return 0;
    return Math.min(100, Math.max(0, (Number(props.bill.amount_paid) / Number(props.bill.amount_total)) * 100));
});

const STATUS_LABELS = { pending: 'Awaiting review', approved: 'Approved', rejected: 'Rejected' };
function statusLabel(status) {
    return STATUS_LABELS[status] ?? status;
}
function statusBadgeClass(status) {
    const base = 'inline-block text-[11px] font-bold px-2.5 py-0.5 rounded-full';
    if (status === 'approved') return `${base} bg-emerald-50 text-emerald-700 border border-emerald-200`;
    if (status === 'rejected') return `${base} bg-rose-50 text-rose-700 border border-rose-200`;
    return `${base} bg-amber-50 text-amber-700 border border-amber-200`;
}

function orderedQty(menuItemId) {
    return props.orderItems.filter((oi) => oi.menu_item_id === menuItemId).reduce((sum, oi) => sum + oi.quantity, 0);
}

function remainingFor(item) {
    if (!item.max_per_school) return Infinity;
    return item.max_per_school - orderedQty(item.id);
}

function badgesFor(item) {
    if (!item.max_per_school) return [];
    const remaining = remainingFor(item);
    const badges = [{ label: `${orderedQty(item.id)} / ${item.max_per_school} ordered`, tone: remaining <= 0 ? 'amber' : 'slate' }];
    if (remaining <= 0) badges.push({ label: 'Limit reached', tone: 'amber' });
    else badges.push({ label: `Only ${remaining} remaining`, tone: remaining <= 10 ? 'amber' : 'slate' });
    return badges;
}

async function addItem(item) {
    if (!canOrderItem(item)) return;
    const requested = Math.min(Number(qty[item.id]) || 1, remainingFor(item));
    if (requested < 1) return;
    if (requested >= 10 && !(await confirm({
        title: 'Confirm large quantity',
        message: `Add ${requested} × ${item.name} to your school’s order?`,
        confirmLabel: 'Add to Order',
    }))) return;
    itemForm.menu_item_id = item.id;
    itemForm.quantity = requested;
    itemForm.post(`${base.value}/items`, {
        preserveScroll: true,
        onSuccess: () => {
            qty[item.id] = 1;
        },
    });
}

function formatCutoff(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('en-IN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

async function removeItem(oi) {
    if (!(await confirm({ message: `Remove ${oi.item_name} (x${oi.quantity})?`, destructive: true }))) return;
    router.delete(`${base.value}/items/${oi.id}`, { preserveScroll: true });
}

const mealTypeOrder = computed(() => Object.keys(props.mealTypes));

const allDates = computed(() => {
    const dates = new Set();
    for (const item of props.menuItems) {
        if (item.menu_date) dates.add(item.menu_date);
    }
    return Array.from(dates).sort();
});

const filteredGroupedMenu = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const diet = selectedDiet.value;
    const dateFilter = selectedDate.value;
    const mealFilter = selectedMeal.value;

    const byDate = {};

    for (const item of props.menuItems) {
        // Date filter
        if (dateFilter !== 'all' && item.menu_date !== dateFilter) continue;

        // Meal filter
        if (mealFilter !== 'all' && item.meal_type !== mealFilter) continue;

        // Diet filter
        if (diet === 'veg' && isNonVeg(item.name, item.description)) continue;
        if (diet === 'non-veg' && !isNonVeg(item.name, item.description)) continue;

        // Search query
        if (query) {
            const matchName = item.name.toLowerCase().includes(query);
            const matchDesc = item.description?.toLowerCase().includes(query);
            if (!matchName && !matchDesc) continue;
        }

        const d = item.menu_date;
        if (!byDate[d]) byDate[d] = {};
        if (!byDate[d][item.meal_type]) byDate[d][item.meal_type] = [];
        byDate[d][item.meal_type].push(item);
    }

    return Object.keys(byDate).sort().map((date) => ({
        date,
        meals: mealTypeOrder.value
            .filter((mt) => byDate[date][mt]?.length)
            .map((mt) => ({ mealType: mt, items: byDate[date][mt] })),
    })).filter((group) => group.meals.length > 0);
});
</script>
