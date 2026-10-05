<template>
    <SahodayaEventsLayout :title="`${event.title} — Food Menu`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Menu & Catering`" eyebrow="Operations"
                    :description="isPartitionedHub
                        ? 'Build the food item catalog here, then apply it to every region below. Schools order and pay against their own region\'s event, not this hub.'
                        : 'Manage dishes in your catalog, schedule meals across event dates, and configure where schools pay for their contingent food orders.'" />

        <EventSubNav v-if="event.event_type !== 'sports'"
                     :sahodaya-id="sahodaya.id" :event-id="event.id" active="food-menu" />

        <EventHierarchyBadge :hierarchy="hierarchy" :hub-href="hubHref" />

        <!-- Top Navigation & Quick Links -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div class="flex items-center gap-2">
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-billing`"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-xs">
                    <span>💳</span> Food Billing & Invoices →
                </Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-billing/report`"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-xs">
                    <span>📊</span> Kitchen Headcount Report →
                </Link>
            </div>
            <div v-if="isPartitionedHub" class="flex items-center gap-2">
                <button type="button" @click="syncToRegions" class="btn-secondary text-xs">
                    Apply menu to all regions
                </button>
            </div>
        </div>

        <FoodRegionDrillDown v-if="isPartitionedHub" :sahodaya-id="sahodaya.id" :regions="foodRegionSummary"
                              target-path="food-menu" class="mb-6" />

        <!-- 3 Primary Navigation Tabs -->
        <div class="border-b border-slate-200 mb-6">
            <nav class="flex space-x-6 overflow-x-auto" aria-label="Tabs">
                <button type="button"
                        @click="activeTab = 'schedule'"
                        class="group inline-flex items-center gap-2 py-3 px-1 border-b-2 font-bold text-sm transition"
                        :class="activeTab === 'schedule'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'">
                    <span class="text-base">📅</span>
                    <span>Meal Schedule</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                          :class="activeTab === 'schedule' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600'">
                        {{ menuItems.length }}
                    </span>
                </button>

                <button type="button"
                        @click="activeTab = 'catalog'"
                        class="group inline-flex items-center gap-2 py-3 px-1 border-b-2 font-bold text-sm transition"
                        :class="activeTab === 'catalog'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'">
                    <span class="text-base">🍽️</span>
                    <span>Food Catalog</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                          :class="activeTab === 'catalog' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600'">
                        {{ catalogItems.length }}
                    </span>
                </button>

                <button type="button"
                        @click="activeTab = 'payee'"
                        class="group inline-flex items-center gap-2 py-3 px-1 border-b-2 font-bold text-sm transition"
                        :class="activeTab === 'payee'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'">
                    <span class="text-base">💳</span>
                    <span class="whitespace-nowrap">Ordering & Billing</span>
                </button>
            </nav>
        </div>

        <!-- ========================================== -->
        <!-- TAB 1: MEAL SCHEDULE BY DATE               -->
        <!-- ========================================== -->
        <div v-if="activeTab === 'schedule'" class="space-y-6">
            <!-- Schedule Action & Filter Bar -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-slate-200/90 shadow-sm">
                <!-- Date Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider mr-1">Filter Date:</span>
                    <button type="button" @click="selectedDate = 'all'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition"
                            :class="selectedDate === 'all' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                        All Dates ({{ allDates.length }})
                    </button>
                    <button v-for="d in allDates" :key="d" type="button" @click="selectedDate = d"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition"
                            :class="selectedDate === d ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                        📅 {{ formatCalendarDate(d) }}
                    </button>
                </div>

                <button type="button" @click="openAssignModal(null, null)"
                        class="btn-primary text-xs font-bold px-4 py-2 shrink-0 justify-center shadow-sm">
                    <span>➕</span> Assign Dishes to Meal Slot
                </button>
            </div>

            <!-- Grouped Scheduled Items -->
            <div v-for="group in filteredGroupedItems" :key="group.date" class="space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b-2 border-slate-200 pb-2">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm">
                            📅
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-extrabold text-slate-900">{{ formatCalendarDate(group.date) }}</h2>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold"
                                      :class="dayWindowStatus(getDayWindow(group.date)).classes">
                                    {{ dayWindowStatus(getDayWindow(group.date)).label }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500">
                                {{ group.meals.reduce((acc, m) => acc + m.items.length, 0) }} item(s) scheduled
                                <span v-if="getDayWindow(group.date).opens_at || getDayWindow(group.date).closes_at" class="text-slate-400">
                                    · {{ dayWindowDescription(getDayWindow(group.date)) }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Quick Open / Close buttons directly against date of menus -->
                        <button v-if="dayWindowStatus(getDayWindow(group.date)).label === 'Closed'"
                                type="button"
                                @click="openDayNow(getDayWindow(group.date))"
                                :disabled="payeeForm.processing"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-emerald-300 bg-emerald-50 text-xs font-bold text-emerald-800 hover:bg-emerald-100 shadow-xs transition">
                            <span>🔓</span> Open orders
                        </button>
                        <button v-else
                                type="button"
                                @click="closeDayNow(getDayWindow(group.date))"
                                :disabled="payeeForm.processing"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-rose-300 bg-rose-50 text-xs font-bold text-rose-800 hover:bg-rose-100 shadow-xs transition">
                            <span>🔒</span> Close orders
                        </button>
                        <button type="button" @click="openSetWindowModal(group.date)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-xs transition">
                            <span>🕒</span> Window times
                        </button>
                        <button type="button" @click="openMoveDateModal(group.date)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-xs transition">
                            <span>📅</span> Move dishes
                        </button>
                    </div>
                </div>

                <div v-for="mealGroup in group.meals" :key="mealGroup.mealType" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-700 bg-slate-100 px-3 py-1.5 rounded-lg">
                            <span class="text-base" aria-hidden="true">{{ mealIcon(mealGroup.mealType) }}</span>
                            <span>{{ mealTypes[mealGroup.mealType] || mealGroup.mealType }}</span>
                            <span class="text-slate-400 font-normal">({{ mealGroup.items.length }})</span>
                        </div>
                        <button type="button" @click="openAssignModal(group.date, mealGroup.mealType)"
                                class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                            + Add dish to this {{ mealTypes[mealGroup.mealType] || mealGroup.mealType }}
                        </button>
                    </div>

                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <FoodItemCard v-for="item in mealGroup.items" :key="item.id"
                                      :name="item.name" :description="item.description" :price="Number(item.price)"
                                      :icon="mealIcon(mealGroup.mealType)" :muted="!item.is_available"
                                      :badges="menuItemBadges(item)">
                            <template #corner>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 border border-slate-200/60">
                                    #{{ item.sort_order }}
                                </span>
                            </template>
                            <template #actions>
                                <button type="button" class="text-xs font-semibold text-indigo-600 hover:underline" @click="startEdit(item)">Edit</button>
                                <button type="button" class="text-xs font-semibold text-rose-600 hover:underline" @click="removeItem(item)">Remove</button>
                            </template>
                        </FoodItemCard>
                    </div>
                </div>
            </div>

            <!-- Empty State for Schedule -->
            <div v-if="filteredGroupedItems.length === 0" class="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-12 text-center">
                <span class="text-4xl">📅</span>
                <h3 class="text-base font-bold text-slate-800 mt-3">Nothing scheduled yet</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                    {{ catalogItems.length === 0
                        ? 'Add dishes to your Food Catalog first, then assign them to dates and meal slots.'
                        : 'Assign dishes from your catalog to specific dates (Breakfast, Lunch, etc.) so schools can order them.' }}
                </p>
                <div class="mt-4 flex justify-center gap-3">
                    <button v-if="catalogItems.length === 0" type="button" @click="activeTab = 'catalog'" class="btn-primary text-xs">
                        Go to Food Catalog →
                    </button>
                    <button v-else type="button" @click="openAssignModal(null, null)" class="btn-primary text-xs">
                        Assign Dishes Now
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2: FOOD CATALOG                        -->
        <!-- ========================================== -->
        <div v-if="activeTab === 'catalog'" class="space-y-6">
            <div class="grid lg:grid-cols-3 gap-6 items-start">
                <!-- Add New Catalog Item Form -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                    <div>
                        <h3 class="font-bold text-base text-slate-900 flex items-center gap-2">
                            <span>🍽️</span> Add New Dish
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Define a dish once in the master catalog with its name and default price. You can schedule it across multiple dates and meals.
                        </p>
                    </div>

                    <form @submit.prevent="addCatalogItem" class="space-y-3">
                        <FormField label="Dish Name" :error="catalogForm.errors.name" required>
                            <template #default="{ id }">
                                <input :id="id" v-model="catalogForm.name" type="text" placeholder="e.g. Chicken Biriyani, Veg Meals, Tea" class="field text-xs w-full" required>
                            </template>
                        </FormField>

                        <FormField label="Description (Optional)" :error="catalogForm.errors.description">
                            <template #default="{ id }">
                                <input :id="id" v-model="catalogForm.description" type="text" placeholder="e.g. Served with raita & pickle" class="field text-xs w-full">
                            </template>
                        </FormField>

                        <FormField label="Default Price (₹)" :error="catalogForm.errors.default_price" required>
                            <template #default="{ id }">
                                <input :id="id" v-model="catalogForm.default_price" type="number" min="0" step="0.01" placeholder="100.00" class="field text-xs w-full" required>
                            </template>
                        </FormField>

                        <!-- Veg / Non-veg preview -->
                        <div v-if="catalogForm.name" class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between text-xs">
                            <span class="text-slate-500">Diet Type Detected:</span>
                            <VegBadge :name="catalogForm.name" :description="catalogForm.description" show-label />
                        </div>

                        <button type="submit" class="btn-primary w-full text-xs font-bold py-2.5 justify-center shadow-xs"
                                :disabled="catalogForm.processing || !catalogForm.name || !catalogForm.default_price">
                            {{ catalogForm.processing ? 'Adding to catalog…' : 'Add Dish to Catalog' }}
                        </button>
                    </form>
                </div>

                <!-- Catalog Dishes Explorer -->
                <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
                    <!-- Explorer Header -->
                    <div class="p-4 border-b border-slate-200 bg-slate-50/60 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">Master Catalog Dishes</h3>
                            <p class="text-xs text-slate-500">{{ catalogItems.length }} dishes registered</p>
                        </div>
                        <input v-model="catalogSearch" type="search" placeholder="Search catalog dishes…"
                               aria-label="Search catalog dishes"
                               class="field text-xs max-w-xs">
                    </div>

                    <!-- Dishes List / Table -->
                    <div class="divide-y divide-slate-100 max-h-[32rem] overflow-y-auto">
                        <div v-for="c in filteredCatalogItems" :key="c.id" class="p-4 hover:bg-slate-50/80 transition">
                            <!-- Inline Edit Mode -->
                            <form v-if="editingCatalogId === c.id" @submit.prevent="saveCatalogEdit(c)" class="space-y-2">
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <input v-model="catalogEditForm.name" type="text" class="field text-xs col-span-2" placeholder="Name" aria-label="Dish name" required>
                                    <input v-model="catalogEditForm.description" type="text" class="field text-xs col-span-2" placeholder="Description" aria-label="Dish description">
                                    <input v-model="catalogEditForm.default_price" type="number" min="0" step="0.01" class="field text-xs" placeholder="Price" aria-label="Default dish price" required>
                                    <label class="flex items-center gap-1.5 text-xs text-slate-700">
                                        <input type="checkbox" v-model="catalogEditForm.is_active"> Active in catalog
                                    </label>
                                </div>
                                <div class="flex gap-2 justify-end pt-1">
                                    <button type="button" class="btn-secondary text-xs py-1" @click="editingCatalogId = null">Cancel</button>
                                    <button type="submit" class="btn-primary text-xs py-1">Save Dish</button>
                                </div>
                            </form>

                            <!-- Normal Card Display -->
                            <div v-else class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3 min-w-0">
                                    <div class="mt-0.5">
                                        <VegBadge :name="c.name" :description="c.description" />
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="font-bold text-sm text-slate-900" :class="{ 'text-slate-400': !c.is_active }">{{ c.name }}</h4>
                                        <p v-if="c.description" class="text-xs text-slate-500 mt-0.5 line-clamp-1">{{ c.description }}</p>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
                                            <span class="font-extrabold text-slate-900">₹{{ Number(c.default_price).toFixed(2) }}</span>
                                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 border border-indigo-100">
                                                Used in {{ c.slots_count }} slot{{ c.slots_count === 1 ? '' : 's' }}
                                            </span>
                                            <span v-if="!c.is_active" class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500 border border-slate-200">
                                                Inactive
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0 text-xs">
                                    <button type="button" class="font-semibold text-indigo-600 hover:text-indigo-800" @click="startCatalogEdit(c)">Edit</button>
                                    <button type="button" class="font-semibold text-rose-600 hover:text-rose-800" @click="removeCatalogItem(c)">Delete</button>
                                </div>
                            </div>
                        </div>

                        <div v-if="filteredCatalogItems.length === 0" class="p-8 text-center text-slate-400 text-sm">
                            No dishes found matching "{{ catalogSearch }}".
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 3: ORDERING & BILLING SETTINGS          -->
        <!-- ========================================== -->
        <div v-if="activeTab === 'payee'" class="max-w-3xl space-y-6">
            <form @submit.prevent="savePayee" class="space-y-6">
                <!-- Section 1: Ordering Window -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div>
                            <h3 class="font-bold text-base text-slate-900 flex items-center gap-2">
                                <span aria-hidden="true">⏱️</span> School Ordering Window
                            </h3>
                            <p class="text-xs text-slate-500 mt-1">
                                Choose when schools can add or remove food items and submit payments.
                            </p>
                        </div>
                        <span class="inline-flex self-start items-center rounded-full border px-2.5 py-1 text-xs font-bold"
                              :class="orderingWindowStatus.classes">
                            {{ orderingWindowStatus.label }}
                        </span>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <FormField label="Orders open at" :error="payeeForm.errors.food_order_opens_at"
                                   hint="Leave blank to allow ordering immediately.">
                            <template #default="{ id }">
                                <input :id="id" v-model="payeeForm.food_order_opens_at" type="datetime-local" class="field text-xs w-full">
                            </template>
                        </FormField>
                        <FormField label="Orders close at" :error="payeeForm.errors.food_order_closes_at"
                                   hint="Leave blank for no admin-set closing time.">
                            <template #default="{ id }">
                                <input :id="id" v-model="payeeForm.food_order_closes_at" type="datetime-local" class="field text-xs w-full">
                            </template>
                        </FormField>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 border border-slate-200 p-3">
                        <button type="button" class="btn-secondary text-xs" :disabled="payeeForm.processing" @click="startOrderingNow">Start orders now</button>
                        <button type="button" class="inline-flex items-center rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50"
                                :disabled="payeeForm.processing" @click="closeOrderingNow">
                            Close orders now
                        </button>
                        <span class="text-[11px] text-slate-500">Quick actions apply immediately.</span>
                    </div>

                    <details v-if="payeeForm.food_order_day_windows.length" class="rounded-xl border border-slate-200 bg-slate-50/60">
                        <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-3">
                            <span>
                                <strong class="text-sm text-slate-800">Per-day ordering windows</strong>
                                <span class="block text-xs text-slate-500 mt-0.5">Optionally open later or close earlier for an individual food date.</span>
                            </span>
                            <span class="rounded-full bg-white border border-slate-200 px-2.5 py-1 text-[11px] font-bold text-slate-600 whitespace-nowrap">
                                {{ configuredDayWindowsCount }} configured
                            </span>
                        </summary>

                        <div class="border-t border-slate-200 p-3 sm:p-4 space-y-3">
                            <div v-for="(day, index) in payeeForm.food_order_day_windows" :key="day.date"
                                 class="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-bold text-slate-900">{{ formatCalendarDate(day.date) }}</p>
                                        <p class="text-[11px] text-slate-500">Controls items served on this date.</p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-bold"
                                          :class="dayWindowStatus(day).classes">
                                        {{ dayWindowStatus(day).label }}
                                    </span>
                                </div>

                                <div class="grid sm:grid-cols-2 gap-3">
                                    <FormField label="Opens at" :error="payeeForm.errors[`food_order_day_windows.${index}.opens_at`]">
                                        <template #default="{ id }">
                                            <input :id="id" v-model="day.opens_at" type="datetime-local" class="field text-xs w-full">
                                        </template>
                                    </FormField>
                                    <FormField label="Closes at" :error="payeeForm.errors[`food_order_day_windows.${index}.closes_at`]">
                                        <template #default="{ id }">
                                            <input :id="id" v-model="day.closes_at" type="datetime-local" class="field text-xs w-full">
                                        </template>
                                    </FormField>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="btn-secondary text-[11px]" :disabled="payeeForm.processing" @click="openDayNow(day)">Open this day now</button>
                                    <button type="button" class="text-[11px] font-bold text-rose-700 px-3 py-1.5 rounded-lg border border-rose-200 hover:bg-rose-50"
                                            :disabled="payeeForm.processing" @click="closeDayNow(day)">
                                        Close this day now
                                    </button>
                                    <button v-if="day.opens_at || day.closes_at" type="button" class="text-[11px] font-semibold text-slate-500 px-2 py-1.5 hover:text-slate-800"
                                            :disabled="payeeForm.processing" @click="clearDayWindow(day)">
                                        Use overall window
                                    </button>
                                </div>
                            </div>
                        </div>
                    </details>

                    <p v-if="event.phase_food_cutoff_at" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        This phase has a hard cutoff at <strong>{{ formatDateTime(event.phase_food_cutoff_at) }}</strong>.
                        Ordering will stop then even if the admin closing time is later or blank.
                    </p>
                </div>

                <!-- Section 2: Payee Designation -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                    <div>
                        <h3 class="font-bold text-base text-slate-900 flex items-center gap-2">
                            <span>🏛️</span> Food Payments Payee
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Choose who receives the food bill payments from schools and handles payment verification.
                        </p>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition"
                               :class="payeeForm.food_payee_type === 'sahodaya'
                                   ? 'border-indigo-600 bg-indigo-50/40 ring-1 ring-indigo-200'
                                   : 'border-slate-200 hover:border-slate-300 bg-white'">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-sm text-slate-900">Sahodaya Office</span>
                                <input type="radio" value="sahodaya" v-model="payeeForm.food_payee_type" class="text-indigo-600">
                            </div>
                            <p class="text-xs text-slate-500 leading-relaxed">
                                Schools pay directly into the Sahodaya bank account or UPI QR code configured in Sahodaya Settings.
                            </p>
                        </label>

                        <label class="relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition"
                               :class="payeeForm.food_payee_type === 'host_school'
                                   ? 'border-indigo-600 bg-indigo-50/40 ring-1 ring-indigo-200'
                                   : 'border-slate-200 hover:border-slate-300 bg-white'">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-sm text-slate-900">Designated Host School</span>
                                <input type="radio" value="host_school" v-model="payeeForm.food_payee_type" class="text-indigo-600">
                            </div>
                            <p class="text-xs text-slate-500 leading-relaxed">
                                A specific school venue hosts the event and manages catering payments from their own dashboard.
                            </p>
                        </label>
                    </div>

                    <!-- Sahodaya QR Code & Bank Details Settings -->
                    <div v-if="payeeForm.food_payee_type === 'sahodaya'" class="pt-3 border-t border-slate-100 space-y-4">
                        <!-- Sahodaya Current Bank Details Summary -->
                        <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-xs uppercase tracking-wider text-indigo-900 flex items-center gap-1.5">
                                    <span>🏛️</span> Sahodaya Bank Details on File
                                </h4>
                                <a :href="`/sahodaya-admin/${sahodaya.id}/membership-settings`"
                                   class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">
                                    Edit in Sahodaya Settings →
                                </a>
                            </div>

                            <div v-if="sahodayaPaymentDetails && (sahodayaPaymentDetails.bank_name || sahodayaPaymentDetails.account_no || sahodayaPaymentDetails.ifsc || sahodayaPaymentDetails.upi)"
                                 class="grid sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs bg-white rounded-lg p-3 border border-indigo-100">
                                <div>
                                    <span class="text-slate-400 block text-[11px]">Bank Name</span>
                                    <span class="font-semibold text-slate-800">{{ sahodayaPaymentDetails.bank_name || '—' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[11px]">Account Number</span>
                                    <span class="font-mono font-bold text-slate-800">{{ sahodayaPaymentDetails.account_no || '—' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[11px]">IFSC Code</span>
                                    <span class="font-mono font-bold text-slate-800">{{ sahodayaPaymentDetails.ifsc || '—' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[11px]">UPI ID</span>
                                    <span class="font-mono font-bold text-slate-800 break-all">{{ sahodayaPaymentDetails.upi || '—' }}</span>
                                </div>
                            </div>
                            <div v-else class="text-xs text-amber-800 bg-amber-50 rounded-lg p-3 border border-amber-200">
                                ⚠️ No default bank details configured in Sahodaya Settings yet. Schools will not see bank account details on checkout until configured.
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-3">
                            <div>
                                <h4 class="font-bold text-xs uppercase tracking-wider text-slate-700">Sahodaya Food Payment QR Code</h4>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Optionally upload a dedicated QR code image for catering payments, or leave blank to use the default QR code configured in Sahodaya Settings.
                                </p>
                            </div>
                            <FormField label="Food Payment QR Code Image (Optional)"
                                       hint="Upload a UPI QR Code image (PNG/JPG/WEBP up to 3MB)."
                                       :error="payeeForm.errors.payment_qr_code">
                                <template #default="{ id }">
                                    <div class="flex flex-wrap items-center gap-4 mt-1">
                                        <!-- Newly chosen preview -->
                                        <div v-if="qrPreviewUrl" class="shrink-0 p-2 bg-emerald-50 rounded-xl border border-emerald-200 shadow-xs flex items-center gap-3">
                                            <img :src="qrPreviewUrl" alt="New QR Preview" class="w-16 h-16 object-contain rounded-lg border border-emerald-300 bg-white">
                                            <div class="text-xs">
                                                <span class="inline-flex items-center gap-1 font-bold text-emerald-800">
                                                    <span>✓</span> New QR Selected
                                                </span>
                                                <p class="text-[11px] text-slate-500 mt-0.5">Click Save Settings below to apply</p>
                                                <button type="button" @click="clearSelectedQr" class="mt-1 text-[11px] font-semibold text-rose-600 hover:underline">
                                                    Cancel
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Existing QR -->
                                        <div v-else-if="currentPayeeQrUrl && !payeeForm.remove_payment_qr_code"
                                             class="shrink-0 p-2 bg-white rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
                                            <img :src="currentPayeeQrUrl" alt="Current QR Code" class="w-16 h-16 object-contain rounded-lg border border-slate-100">
                                            <div class="text-xs">
                                                <p class="font-bold text-slate-800">
                                                    {{ event.food_payment_qr_code_url ? 'Custom Food QR Code' : 'Sahodaya Default QR Code' }}
                                                </p>
                                                <label v-if="event.food_payment_qr_code_url"
                                                       class="inline-flex items-center gap-1.5 mt-1 text-rose-600 cursor-pointer font-medium hover:underline text-[11px]">
                                                    <input type="checkbox" v-model="payeeForm.remove_payment_qr_code" class="rounded text-rose-600">
                                                    Remove custom QR (use default)
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Removal state -->
                                        <div v-else-if="payeeForm.remove_payment_qr_code" class="text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded-xl p-2.5">
                                            Custom QR code will be removed upon saving.
                                            <button type="button" @click="payeeForm.remove_payment_qr_code = false" class="ml-2 font-semibold underline text-slate-700">Undo</button>
                                        </div>

                                        <input :id="id" type="file" accept="image/png,image/jpeg,image/webp" class="field text-xs max-w-xs"
                                               @change="handleQrFileChange">
                                    </div>
                                </template>
                            </FormField>
                        </div>
                    </div>

                    <!-- Host School Account Details -->
                    <div v-if="payeeForm.food_payee_type === 'host_school'" class="pt-3 border-t border-slate-100 space-y-3">
                        <FormField label="Select Host School" :error="payeeForm.errors.food_host_school_id" required>
                            <template #default="{ id }">
                                <SearchableSelect :id="id" v-model="payeeForm.food_host_school_id" :options="schoolOptions"
                                                  :all-option="true" all-label="— Select host school —" />
                            </template>
                        </FormField>

                        <div v-if="payeeForm.food_host_school_id" class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-bold text-xs uppercase tracking-wider text-slate-700">Host School Bank & UPI Account</h4>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        These details are displayed to ordering schools on their food checkout page.
                                    </p>
                                </div>
                                <span v-if="schoolPaymentDetails?.[payeeForm.food_host_school_id]"
                                      class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-800">
                                    ✓ Pre-filled from profile
                                </span>
                            </div>
                            <div class="grid sm:grid-cols-2 gap-3 text-xs">
                                <FormField label="Bank Name" :error="payeeForm.errors.payment_bank_name">
                                    <template #default="{ id }"><input :id="id" v-model="payeeForm.payment_bank_name" type="text" placeholder="e.g. State Bank of India" class="field text-xs w-full"></template>
                                </FormField>
                                <FormField label="Account Number" :error="payeeForm.errors.payment_account_no">
                                    <template #default="{ id }"><input :id="id" v-model="payeeForm.payment_account_no" type="text" placeholder="e.g. 10482910482" class="field text-xs w-full font-mono"></template>
                                </FormField>
                                <FormField label="IFSC Code" :error="payeeForm.errors.payment_ifsc">
                                    <template #default="{ id }"><input :id="id" v-model="payeeForm.payment_ifsc" type="text" placeholder="e.g. SBIN0001234" class="field text-xs w-full font-mono uppercase"></template>
                                </FormField>
                                <FormField label="UPI ID" :error="payeeForm.errors.payment_upi">
                                    <template #default="{ id }"><input :id="id" v-model="payeeForm.payment_upi" type="text" placeholder="e.g. schoolname@upi" class="field text-xs w-full font-mono"></template>
                                </FormField>
                            </div>

                            <div class="border-t border-slate-200/60 pt-3">
                                <FormField label="Host School Payment QR Code Image (Optional)"
                                           hint="Upload a UPI QR code image (PNG, JPG, or WebP up to 3MB) for schools to scan on food checkout."
                                           :error="payeeForm.errors.payment_qr_code">
                                    <template #default="{ id }">
                                        <div class="flex flex-wrap items-center gap-4 mt-1">
                                            <!-- Newly chosen preview -->
                                            <div v-if="qrPreviewUrl" class="shrink-0 p-2 bg-emerald-50 rounded-xl border border-emerald-200 shadow-xs flex items-center gap-3">
                                                <img :src="qrPreviewUrl" alt="New QR Preview" class="w-16 h-16 object-contain rounded-lg border border-emerald-300 bg-white">
                                                <div class="text-xs">
                                                    <span class="inline-flex items-center gap-1 font-bold text-emerald-800">
                                                        <span>✓</span> New QR Selected
                                                    </span>
                                                    <p class="text-[11px] text-slate-500 mt-0.5">Click Save Settings below to apply</p>
                                                    <button type="button" @click="clearSelectedQr" class="mt-1 text-[11px] font-semibold text-rose-600 hover:underline">
                                                        Cancel
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Existing QR -->
                                            <div v-else-if="currentPayeeQrUrl && !payeeForm.remove_payment_qr_code"
                                                 class="shrink-0 p-2 bg-white rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
                                                <img :src="currentPayeeQrUrl" alt="Current QR Code" class="w-16 h-16 object-contain rounded-lg border border-slate-100">
                                                <div class="text-xs">
                                                    <p class="font-bold text-slate-800">Current QR Code</p>
                                                    <label class="inline-flex items-center gap-1.5 mt-1 text-rose-600 cursor-pointer font-medium hover:underline text-[11px]">
                                                        <input type="checkbox" v-model="payeeForm.remove_payment_qr_code" class="rounded text-rose-600">
                                                        Remove QR code
                                                    </label>
                                                </div>
                                            </div>

                                            <!-- Removal state -->
                                            <div v-else-if="payeeForm.remove_payment_qr_code" class="text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded-xl p-2.5">
                                                QR code will be removed upon saving.
                                                <button type="button" @click="payeeForm.remove_payment_qr_code = false" class="ml-2 font-semibold underline text-slate-700">Undo</button>
                                            </div>

                                            <input :id="id" type="file" accept="image/png,image/jpeg,image/webp" class="field text-xs max-w-xs"
                                                   @change="handleQrFileChange">
                                        </div>
                                    </template>
                                </FormField>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Coupon Issuance Policy -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-3">
                    <h3 class="font-bold text-base text-slate-900 flex items-center gap-2">
                        <span>🎟️</span> Coupon Issuance Rule
                    </h3>
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50/60 cursor-pointer hover:bg-slate-50 transition">
                        <input type="checkbox" v-model="payeeForm.require_payment_for_coupons" class="mt-0.5 text-indigo-600 rounded">
                        <div>
                            <span class="font-bold text-xs text-slate-800 block">Only issue food coupons for settled (fully paid) bills</span>
                            <span class="text-xs text-slate-500 mt-0.5 block leading-relaxed">
                                When checked, meal coupons cannot be distributed or printed until the school's total food bill has been verified and settled.
                            </span>
                        </div>
                    </label>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary text-xs font-bold px-6 py-2.5 shadow-sm" :disabled="payeeForm.processing">
                        {{ payeeForm.processing ? 'Saving settings…' : 'Save Ordering & Billing Settings' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- ========================================== -->
        <!-- MODAL: ASSIGN DISHES TO MEAL SLOT          -->
        <!-- ========================================== -->
        <Modal :show="showAssignModal" title="Assign Dishes to Meal Slot" size="lg" @close="showAssignModal = false">
            <div class="space-y-4">
                <p class="text-xs text-slate-500">
                    Pick a date and meal slot, then select which dishes from the master catalog should be served.
                </p>

                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Date" :error="assignForm.errors.menu_date" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="assignForm.menu_date" type="date" class="field text-xs w-full" required>
                            <div v-if="eventDates.length" class="mt-1 flex flex-wrap gap-1">
                                <button v-for="d in eventDates" :key="d" type="button"
                                        class="px-2 py-0.5 rounded text-[10px] font-semibold border transition"
                                        :class="assignForm.menu_date === d ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                                        @click="assignForm.menu_date = d">
                                    {{ formatCalendarDate(d) }}
                                </button>
                            </div>
                        </template>
                    </FormField>
                    <FormField label="Meal Slot" :error="assignForm.errors.meal_type" required>
                        <template #default="{ id }">
                            <SearchableSelect :id="id" v-model="assignForm.meal_type" :options="mealTypeOptions"
                                              :all-option="true" all-label="— Select Meal —" />
                        </template>
                    </FormField>
                </div>

                <!-- Catalog Items Selection Table -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-700">Select Dishes ({{ selectedCatalogIds.length }} selected)</span>
                        <input v-model="catalogSearch" type="search" placeholder="Search dishes…" aria-label="Search dishes to assign" class="field text-xs py-1 max-w-[12rem]">
                    </div>

                    <div class="max-h-64 overflow-y-auto rounded-xl border border-slate-200">
                        <table class="data-table text-xs">
                            <thead class="sticky top-0 bg-slate-50">
                                <tr>
                                    <th class="w-8"><input type="checkbox" aria-label="Select all visible dishes" :checked="allCatalogSelected" @change="toggleSelectAllCatalog"></th>
                                    <th>Dish</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in filteredCatalogItems" :key="c.id" class="hover:bg-slate-50/70">
                                    <td class="align-middle"><input type="checkbox" :value="c.id" v-model="selectedCatalogIds" :aria-label="`Select ${c.name}`"></td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <VegBadge :name="c.name" :description="c.description" />
                                            <span class="font-semibold text-slate-900">{{ c.name }}</span>
                                            <span v-if="!c.is_active" class="text-[10px] text-slate-400">(inactive)</span>
                                        </div>
                                    </td>
                                    <td class="font-bold text-slate-800">₹{{ Number(c.default_price).toFixed(2) }}</td>
                                </tr>
                                <tr v-if="filteredCatalogItems.length === 0">
                                    <td colspan="3" class="p-6 text-center text-slate-400">No dishes match "{{ catalogSearch }}".</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <template #footer>
                <button type="button" class="btn-secondary text-xs" @click="showAssignModal = false">Cancel</button>
                <button type="button" class="btn-primary text-xs font-bold"
                        :disabled="selectedCatalogIds.length === 0 || !assignForm.menu_date || !assignForm.meal_type || assignForm.processing"
                        @click="executeAssign">
                    {{ assignForm.processing ? 'Assigning…' : `Assign (${selectedCatalogIds.length}) to Schedule` }}
                </button>
            </template>
        </Modal>

        <!-- ========================================== -->
        <!-- MODAL: EDIT SCHEDULED ITEM                 -->
        <!-- ========================================== -->
        <Modal :show="editingId !== null" title="Edit Scheduled Menu Item" size="md" @close="cancelEdit">
            <form v-if="editingItem" id="edit-menu-item-form" @submit.prevent="saveEdit(editingItem)" class="space-y-3">
                <FormField label="Item Name">
                    <template #default="{ id }"><input :id="id" v-model="editForm.name" type="text" class="field text-xs w-full" required></template>
                </FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Date" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="editForm.menu_date" type="date" class="field text-xs w-full" required>
                            <div v-if="eventDates.length" class="mt-1 flex flex-wrap gap-1">
                                <button v-for="d in eventDates" :key="d" type="button"
                                        class="px-2 py-0.5 rounded text-[10px] font-semibold border transition"
                                        :class="editForm.menu_date === d ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                                        @click="editForm.menu_date = d">
                                    {{ formatCalendarDate(d) }}
                                </button>
                            </div>
                        </template>
                    </FormField>
                    <FormField label="Meal Slot" required>
                        <template #default="{ id }">
                            <SearchableSelect :id="id" v-model="editForm.meal_type" :options="mealTypeOptions" :all-option="false" placeholder="Select meal" />
                        </template>
                    </FormField>
                    <FormField label="Price (₹)">
                        <template #default="{ id }"><input :id="id" v-model="editForm.price" type="number" min="0" step="0.01" class="field text-xs w-full" required></template>
                    </FormField>
                    <FormField label="Sort Order">
                        <template #default="{ id }"><input :id="id" v-model="editForm.sort_order" type="number" min="0" class="field text-xs w-full"></template>
                    </FormField>
                    <FormField label="Max Per School" hint="Leave blank for no limit" class="col-span-2">
                        <template #default="{ id }"><input :id="id" v-model="editForm.max_per_school" type="number" min="1" class="field text-xs w-full"></template>
                    </FormField>
                </div>
                <label class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 bg-slate-50/60 cursor-pointer">
                    <input type="checkbox" v-model="editForm.is_available" class="text-indigo-600 rounded">
                    <span class="text-xs font-semibold text-slate-800">Available for schools to order</span>
                </label>
            </form>
            <template #footer>
                <button type="button" class="btn-secondary text-xs" @click="cancelEdit">Cancel</button>
                <button type="submit" form="edit-menu-item-form" class="btn-primary text-xs font-bold">Save Changes</button>
            </template>
        </Modal>

        <!-- ========================================== -->
        <!-- MODAL: MOVE DISHES TO ANOTHER DATE         -->
        <!-- ========================================== -->
        <Modal :show="showMoveDateModal" title="Move Scheduled Dishes to Another Date" size="md" @close="showMoveDateModal = false">
            <form id="move-date-form" @submit.prevent="executeMoveDate" class="space-y-4">
                <p class="text-xs text-slate-600">
                    Move all dishes currently scheduled for <strong class="text-slate-900">{{ formatCalendarDate(moveDateFrom) }}</strong> to a new date:
                </p>
                <FormField label="Target Date" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="moveDateTo" type="date" class="field text-xs w-full" required>
                        <div v-if="eventDates.length" class="mt-1.5 flex flex-wrap gap-1">
                            <button v-for="d in eventDates" :key="d" type="button"
                                    class="px-2 py-0.5 rounded text-[10px] font-semibold border transition"
                                    :class="moveDateTo === d ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                                    @click="moveDateTo = d">
                                {{ formatCalendarDate(d) }}
                            </button>
                        </div>
                    </template>
                </FormField>
            </form>
            <template #footer>
                <button type="button" class="btn-secondary text-xs" @click="showMoveDateModal = false">Cancel</button>
                <button type="submit" form="move-date-form" :disabled="!moveDateTo || moveDateTo === moveDateFrom || isMovingDate"
                        class="btn-primary text-xs font-bold">
                    {{ isMovingDate ? 'Moving…' : 'Move Dishes' }}
                </button>
            </template>
        </Modal>

        <!-- ========================================== -->
        <!-- MODAL: SET ORDERING WINDOW FOR DATE        -->
        <!-- ========================================== -->
        <Modal :show="showWindowModal" :title="`Ordering Window for ${formatCalendarDate(activeWindowDate)}`" size="md" @close="showWindowModal = false">
            <div v-if="activeWindowDay" class="space-y-4">
                <div class="flex items-center justify-between p-3 rounded-xl border bg-slate-50" :class="dayWindowStatus(activeWindowDay).classes">
                    <div>
                        <span class="text-xs font-bold">Current Status:</span>
                        <span class="ml-1.5 font-extrabold text-xs uppercase">{{ dayWindowStatus(activeWindowDay).label }}</span>
                    </div>
                    <span class="text-[11px] text-slate-600 font-medium">{{ dayWindowDescription(activeWindowDay) || 'Following overall event window' }}</span>
                </div>

                <div class="grid sm:grid-cols-2 gap-3">
                    <FormField label="Orders open at" hint="Leave blank to follow overall window">
                        <template #default="{ id }">
                            <input :id="id" v-model="activeWindowDay.opens_at" type="datetime-local" class="field text-xs w-full">
                        </template>
                    </FormField>
                    <FormField label="Orders close at" hint="Leave blank to follow overall window">
                        <template #default="{ id }">
                            <input :id="id" v-model="activeWindowDay.closes_at" type="datetime-local" class="field text-xs w-full">
                        </template>
                    </FormField>
                </div>

                <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-200">
                    <button type="button" class="btn-secondary text-xs" :disabled="payeeForm.processing" @click="openDayNow(activeWindowDay)">
                        <span>🔓</span> Open orders now
                    </button>
                    <button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-rose-200 bg-rose-50 text-xs font-bold text-rose-700 hover:bg-rose-100"
                            :disabled="payeeForm.processing" @click="closeDayNow(activeWindowDay)">
                        <span>🔒</span> Close orders now
                    </button>
                    <button v-if="activeWindowDay.opens_at || activeWindowDay.closes_at" type="button"
                            class="text-xs text-slate-500 hover:text-slate-800 underline ml-auto"
                            :disabled="payeeForm.processing" @click="clearDayWindow(activeWindowDay)">
                        Use overall event window
                    </button>
                </div>
            </div>
            <template #footer>
                <button type="button" class="btn-secondary text-xs" @click="showWindowModal = false">Cancel</button>
                <button type="button" class="btn-primary text-xs font-bold" :disabled="payeeForm.processing" @click="saveWindowModal">
                    {{ payeeForm.processing ? 'Saving…' : 'Save Window' }}
                </button>
            </template>
        </Modal>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import EventHierarchyBadge from '@/Components/fest/EventHierarchyBadge.vue';
import FoodRegionDrillDown from '@/Components/sahodaya/FoodRegionDrillDown.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import FoodItemCard from '@/Components/food/FoodItemCard.vue';
import VegBadge from '@/Components/food/VegBadge.vue';
import Modal from '@/Components/ui/Modal.vue';
import FormField from '@/Components/ui/FormField.vue';
import { mealIcon } from '@/support/mealIcons.js';
import { formatCalendarDate } from '@/support/calendarDates.js';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    menuItems: { type: Array, default: () => [] },
    catalogItems: { type: Array, default: () => [] },
    hierarchy: { type: Object, default: null },
    mealTypes: { type: Object, default: () => ({}) },
    eventDates: { type: Array, default: () => [] },
    schoolOptions: { type: Array, default: () => [] },
    schoolPaymentDetails: { type: Object, default: () => ({}) },
    sahodayaPaymentDetails: { type: Object, default: null },
    activityLogs: { type: Array, default: () => [] },
    isPartitionedHub: { type: Boolean, default: false },
    foodRegionSummary: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const hubHref = computed(() => (
    props.hierarchy?.parent_event ? `/sahodaya-admin/${props.sahodaya.id}/events/${props.hierarchy.parent_event.id}/food-menu` : null
));
const { confirm } = useConfirm();

// Tab state: 'schedule' | 'catalog' | 'payee'
const activeTab = ref('schedule');
const selectedDate = ref('all');
const showAssignModal = ref(false);

function syncToRegions() {
    router.post(`${base}/food-menu/sync-to-regions`, {}, { preserveScroll: true });
}

// --- Payee Settings ---
const payeeForm = useForm({
    food_payee_type: props.event.food_payee_type || 'sahodaya',
    food_host_school_id: props.event.food_host_school_id || '',
    require_payment_for_coupons: props.event.require_payment_for_coupons || false,
    food_order_opens_at: toDateTimeLocal(props.event.food_order_opens_at),
    food_order_closes_at: toDateTimeLocal(props.event.food_order_closes_at),
    food_order_day_windows: buildDayWindowRows(),
    payment_bank_name: '', payment_account_no: '', payment_ifsc: '', payment_upi: '',
    payment_qr_code: null,
    remove_payment_qr_code: false,
});

function buildDayWindowRows() {
    const configured = props.event.food_order_day_windows ?? {};
    const dates = [...new Set([...props.eventDates, ...Object.keys(configured)])].sort();

    return dates.map((date) => ({
        date,
        opens_at: toDateTimeLocal(configured[date]?.opens_at),
        closes_at: toDateTimeLocal(configured[date]?.closes_at),
    }));
}

function toDateTimeLocal(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (part) => String(part).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('en-IN', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

function parsedTime(value) {
    if (!value) return null;
    const time = new Date(value).getTime();
    return Number.isNaN(time) ? null : time;
}

const orderingWindowStatus = computed(() => {
    const now = Date.now();
    const opensAt = parsedTime(payeeForm.food_order_opens_at);
    const configuredClose = parsedTime(payeeForm.food_order_closes_at);
    const phaseClose = parsedTime(props.event.phase_food_cutoff_at);
    const closingCandidates = [configuredClose, phaseClose].filter((value) => value !== null);
    const closesAt = closingCandidates.length ? Math.min(...closingCandidates) : null;

    if (closesAt !== null && now > closesAt) {
        return { label: 'Closed', classes: 'border-rose-200 bg-rose-50 text-rose-700' };
    }
    if (opensAt !== null && now < opensAt) {
        return { label: 'Scheduled', classes: 'border-sky-200 bg-sky-50 text-sky-700' };
    }
    return {
        label: opensAt === null && closesAt === null ? 'Open · no limits' : 'Open now',
        classes: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    };
});

const configuredDayWindowsCount = computed(() => payeeForm.food_order_day_windows
    .filter((day) => day.opens_at || day.closes_at).length);

function dayWindowStatus(day) {
    const now = Date.now();
    const openingCandidates = [parsedTime(payeeForm.food_order_opens_at), parsedTime(day.opens_at)]
        .filter((value) => value !== null);
    const closingCandidates = [
        parsedTime(payeeForm.food_order_closes_at),
        parsedTime(props.event.phase_food_cutoff_at),
        parsedTime(day.closes_at),
    ].filter((value) => value !== null);
    const opensAt = openingCandidates.length ? Math.max(...openingCandidates) : null;
    const closesAt = closingCandidates.length ? Math.min(...closingCandidates) : null;

    if (closesAt !== null && now > closesAt) {
        return { label: 'Closed', classes: 'border-rose-200 bg-rose-50 text-rose-700' };
    }
    if (opensAt !== null && now < opensAt) {
        return { label: 'Scheduled', classes: 'border-sky-200 bg-sky-50 text-sky-700' };
    }
    return { label: 'Open now', classes: 'border-emerald-200 bg-emerald-50 text-emerald-700' };
}

function startOrderingNow() {
    const opensAt = toDateTimeLocal(new Date());
    payeeForm.food_order_opens_at = opensAt;
    if (parsedTime(payeeForm.food_order_closes_at) !== null && parsedTime(payeeForm.food_order_closes_at) <= parsedTime(opensAt)) {
        payeeForm.food_order_closes_at = '';
    }
    savePayee();
}

async function closeOrderingNow() {
    if (!(await confirm({
        title: 'Close food ordering?',
        message: 'Schools will no longer be able to change orders or submit payments. They can still review their order history.',
        confirmLabel: 'Close Orders',
        destructive: true,
    }))) return;

    const closesAt = toDateTimeLocal(new Date());
    if (parsedTime(payeeForm.food_order_opens_at) !== null && parsedTime(payeeForm.food_order_opens_at) >= parsedTime(closesAt)) {
        payeeForm.food_order_opens_at = '';
    }
    payeeForm.food_order_closes_at = closesAt;
    savePayee();
}

function openDayNow(day) {
    const opensAt = toDateTimeLocal(new Date());
    day.opens_at = opensAt;
    if (parsedTime(day.closes_at) !== null && parsedTime(day.closes_at) <= parsedTime(opensAt)) {
        day.closes_at = '';
    }
    savePayee();
}

async function closeDayNow(day) {
    if (!(await confirm({
        title: `Close orders for ${formatCalendarDate(day.date)}?`,
        message: 'Schools will no longer be able to add or remove food served on this date.',
        confirmLabel: 'Close This Day',
        destructive: true,
    }))) return;

    const closesAt = toDateTimeLocal(new Date());
    if (parsedTime(day.opens_at) !== null && parsedTime(day.opens_at) >= parsedTime(closesAt)) {
        day.opens_at = '';
    }
    day.closes_at = closesAt;
    savePayee();
}

function clearDayWindow(day) {
    day.opens_at = '';
    day.closes_at = '';
    savePayee();
}

function getDayWindow(date) {
    let day = payeeForm.food_order_day_windows.find((d) => d.date === date);
    if (!day) {
        day = { date, opens_at: '', closes_at: '' };
        payeeForm.food_order_day_windows.push(day);
    }
    return day;
}

function dayWindowDescription(day) {
    if (!day || (!day.opens_at && !day.closes_at)) return '';
    if (day.opens_at && day.closes_at) return `Opens ${formatDateTime(day.opens_at)}, closes ${formatDateTime(day.closes_at)}`;
    if (day.opens_at) return `Opens ${formatDateTime(day.opens_at)}`;
    if (day.closes_at) return `Closes ${formatDateTime(day.closes_at)}`;
    return '';
}

const showWindowModal = ref(false);
const activeWindowDate = ref('');
const activeWindowDay = computed(() => activeWindowDate.value ? getDayWindow(activeWindowDate.value) : null);

function openSetWindowModal(date) {
    activeWindowDate.value = date;
    showWindowModal.value = true;
}

function saveWindowModal() {
    savePayee();
    showWindowModal.value = false;
}

const qrPreviewUrl = ref(null);

const currentPayeeQrUrl = computed(() => {
    if (payeeForm.food_payee_type === 'host_school') {
        if (!payeeForm.food_host_school_id) return null;
        const d = props.schoolPaymentDetails?.[payeeForm.food_host_school_id];
        return d?.qr_code_url || (payeeForm.food_host_school_id === props.event.food_host_school_id ? props.event.food_payment_qr_code_url : null);
    }
    return props.event.food_payment_qr_code_url
        || props.event.sahodaya_payment_qr_code_url
        || props.sahodayaPaymentDetails?.qr_code_url;
});

function handleQrFileChange(e) {
    const file = e.target.files?.[0];
    if (file) {
        payeeForm.payment_qr_code = file;
        payeeForm.remove_payment_qr_code = false;
        if (qrPreviewUrl.value) {
            URL.revokeObjectURL(qrPreviewUrl.value);
        }
        qrPreviewUrl.value = URL.createObjectURL(file);
    }
}

function clearSelectedQr() {
    payeeForm.payment_qr_code = null;
    if (qrPreviewUrl.value) {
        URL.revokeObjectURL(qrPreviewUrl.value);
        qrPreviewUrl.value = null;
    }
}

watch(() => [payeeForm.food_payee_type, payeeForm.food_host_school_id], () => {
    payeeForm.remove_payment_qr_code = false;
    clearSelectedQr();
});

onBeforeUnmount(() => {
    clearSelectedQr();
});

function fillHostPayment(schoolId) {
    const d = props.schoolPaymentDetails?.[schoolId] ?? {};
    payeeForm.payment_bank_name = d.bank_name ?? '';
    payeeForm.payment_account_no = d.account_no ?? '';
    payeeForm.payment_ifsc = d.ifsc ?? '';
    payeeForm.payment_upi = d.upi ?? '';
}
fillHostPayment(payeeForm.food_host_school_id);
watch(() => payeeForm.food_host_school_id, fillHostPayment);

function savePayee() {
    payeeForm.transform((data) => ({
        ...data,
        _method: 'put',
    })).post(`${base}/food-menu-payee`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            payeeForm.payment_qr_code = null;
            payeeForm.remove_payment_qr_code = false;
            clearSelectedQr();
        },
    });
}

// --- Food Catalog ---
const catalogForm = useForm({ name: '', description: '', default_price: '' });
function addCatalogItem() {
    catalogForm.post(`${base}/food-catalog`, { preserveScroll: true, onSuccess: () => catalogForm.reset() });
}

const editingCatalogId = ref(null);
const catalogEditForm = reactive({ name: '', description: '', default_price: '', is_active: true });

function startCatalogEdit(c) {
    editingCatalogId.value = c.id;
    catalogEditForm.name = c.name;
    catalogEditForm.description = c.description;
    catalogEditForm.default_price = c.default_price;
    catalogEditForm.is_active = c.is_active;
}
function saveCatalogEdit(c) {
    router.put(`${base}/food-catalog/${c.id}`, { ...catalogEditForm }, {
        preserveScroll: true,
        onSuccess: () => { editingCatalogId.value = null; },
    });
}
async function removeCatalogItem(c) {
    if (!(await confirm({ message: `Remove '${c.name}' from the catalog? Slots already assigned from it keep their own copy and are not affected.`, destructive: true }))) return;
    router.delete(`${base}/food-catalog/${c.id}`, { preserveScroll: true });
}

// --- Assign Dishes to Slot Modal ---
const catalogSearch = ref('');
const selectedCatalogIds = ref([]);
const assignForm = useForm({ catalog_item_ids: [], menu_date: '', meal_type: '' });

const eventDateOptions = computed(() => props.eventDates.map((d) => ({ value: d, label: formatCalendarDate(d) })));
const mealTypeOptions = computed(() => Object.entries(props.mealTypes).map(([value, label]) => ({ value, label })));

function openAssignModal(prefillDate = null, prefillMeal = null) {
    if (prefillDate) assignForm.menu_date = prefillDate;
    else if (props.eventDates.length) assignForm.menu_date = props.eventDates[0];

    if (prefillMeal) assignForm.meal_type = prefillMeal;
    else assignForm.meal_type = Object.keys(props.mealTypes)[0] || '';

    showAssignModal.value = true;
}

const filteredCatalogItems = computed(() => {
    const q = catalogSearch.value.trim().toLowerCase();
    if (!q) return props.catalogItems;
    return props.catalogItems.filter((c) => c.name.toLowerCase().includes(q) || (c.description || '').toLowerCase().includes(q));
});

const allCatalogSelected = computed(() => filteredCatalogItems.value.length > 0
    && filteredCatalogItems.value.every((c) => selectedCatalogIds.value.includes(c.id)));

function toggleSelectAllCatalog() {
    const filteredIds = filteredCatalogItems.value.map((c) => c.id);
    if (allCatalogSelected.value) {
        const excluded = new Set(filteredIds);
        selectedCatalogIds.value = selectedCatalogIds.value.filter((id) => !excluded.has(id));
    } else {
        selectedCatalogIds.value = Array.from(new Set([...selectedCatalogIds.value, ...filteredIds]));
    }
}

function executeAssign() {
    assignForm.catalog_item_ids = selectedCatalogIds.value;
    assignForm.post(`${base}/food-menu/assign-catalog-items`, {
        preserveScroll: true,
        onSuccess: () => {
            selectedCatalogIds.value = [];
            showAssignModal.value = false;
        },
    });
}

// --- Move Date Modal ---
const showMoveDateModal = ref(false);
const moveDateFrom = ref('');
const moveDateTo = ref('');
const isMovingDate = ref(false);

function openMoveDateModal(date) {
    moveDateFrom.value = date;
    moveDateTo.value = props.eventDates.find((d) => d !== date) || '';
    showMoveDateModal.value = true;
}

function executeMoveDate() {
    if (!moveDateTo.value || moveDateTo.value === moveDateFrom.value) return;
    isMovingDate.value = true;
    router.post(`${base}/food-menu/move-date`, {
        from_date: moveDateFrom.value,
        to_date: moveDateTo.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showMoveDateModal.value = false;
        },
        onFinish: () => {
            isMovingDate.value = false;
        },
    });
}

// --- Scheduled Menu Item Editing ---
const editingId = ref(null);
const editForm = reactive({ menu_date: '', meal_type: '', name: '', price: '', max_per_school: '', is_available: true, sort_order: 0 });
const editingItem = computed(() => props.menuItems.find((i) => i.id === editingId.value) ?? null);

function menuItemBadges(item) {
    const badges = [];
    if (item.max_per_school) badges.push({ label: `Max ${item.max_per_school}/school`, tone: 'slate' });
    if (!item.is_available) badges.push({ label: 'Unavailable', tone: 'amber' });
    return badges;
}

function startEdit(item) {
    editingId.value = item.id;
    editForm.menu_date = (item.menu_date || '').toString().slice(0, 10);
    editForm.meal_type = item.meal_type;
    editForm.name = item.name;
    editForm.price = item.price;
    editForm.max_per_school = item.max_per_school;
    editForm.is_available = item.is_available;
    editForm.sort_order = item.sort_order;
}
function cancelEdit() {
    editingId.value = null;
}
function saveEdit(item) {
    router.put(`${base}/food-menu/${item.id}`, {
        menu_date: editForm.menu_date,
        meal_type: editForm.meal_type,
        name: editForm.name,
        description: item.description,
        price: editForm.price,
        max_per_school: editForm.max_per_school || null,
        is_available: editForm.is_available,
        sort_order: editForm.sort_order || 0,
    }, { preserveScroll: true, onSuccess: () => { editingId.value = null; } });
}
async function removeItem(item) {
    if (!(await confirm({ message: `Remove '${item.name}'? Schools who already ordered it keep their order history.` }))) return;
    router.delete(`${base}/food-menu/${item.id}`, { preserveScroll: true });
}

const mealTypeOrder = computed(() => Object.keys(props.mealTypes));

const allDates = computed(() => {
    const dates = new Set();
    for (const item of props.menuItems) {
        if (item.menu_date) dates.add(item.menu_date);
    }
    return Array.from(dates).sort();
});

const filteredGroupedItems = computed(() => {
    const dateFilter = selectedDate.value;
    const byDate = {};

    for (const item of props.menuItems) {
        if (dateFilter !== 'all' && item.menu_date !== dateFilter) continue;

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
