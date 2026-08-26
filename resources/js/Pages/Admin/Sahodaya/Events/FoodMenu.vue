<template>
    <SahodayaEventsLayout :title="`${event.title} — Food Menu`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Menu`" eyebrow="Operations"
                    :description="isPartitionedHub
                        ? 'Build the food item catalog here, then apply it to every region below. Schools order and pay against their own region\'s event, not this hub.'
                        : 'Define food items once, then assign them to breakfast, lunch, and other meal slots across the event\'s days. Schools order and pay from Food Billing.'" />

        <EventHierarchyBadge :hierarchy="hierarchy" :hub-href="hubHref" />

        <div class="flex flex-wrap gap-2 mb-6">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-billing`" class="text-sm text-indigo-600">Food Billing →</Link>
            <button v-if="isPartitionedHub" @click="syncToRegions" class="btn-secondary text-sm ml-auto">
                Apply menu to all regions
            </button>
        </div>

        <FoodRegionDrillDown v-if="isPartitionedHub" :sahodaya-id="sahodaya.id" :regions="foodRegionSummary"
                              target-path="food-menu" class="mb-6" />

        <!-- Payee settings -->
        <div class="card mb-6 max-w-xl">
            <h3 class="section-title">Who food payments go to</h3>
            <form @submit.prevent="savePayee" class="space-y-3 mt-3">
                <div class="flex gap-4 text-sm">
                    <label class="flex items-center gap-2">
                        <input type="radio" value="sahodaya" v-model="payeeForm.food_payee_type"> Sahodaya (default)
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" value="host_school" v-model="payeeForm.food_payee_type"> A host school
                    </label>
                </div>
                <div v-if="payeeForm.food_payee_type === 'host_school'">
                    <SearchableSelect v-model="payeeForm.food_host_school_id" :options="schoolOptions"
                                      :all-option="true" all-label="— Select school —" />
                    <p v-if="payeeForm.errors.food_host_school_id" class="text-xs text-red-600 mt-1">{{ payeeForm.errors.food_host_school_id }}</p>
                    <p v-if="event.conducting_school_id" class="text-xs text-gray-400 mt-1">
                        This event's conducting school is set — you can pick the same one here if that's who should be paid.
                    </p>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="payeeForm.require_payment_for_coupons">
                    Only issue food coupons for settled (fully paid) bills
                </label>
                <button type="submit" class="btn-secondary text-sm" :disabled="payeeForm.processing">Save</button>
            </form>
        </div>

        <!-- Step 1 + 2: build the food-item catalog, then assign items to meal slots -->
        <div class="grid lg:grid-cols-2 gap-6 mb-8">
            <div class="card space-y-4">
                <div>
                    <h3 class="section-title flex items-center gap-2"><span aria-hidden="true">🍽️</span> Food items</h3>
                    <p class="section-desc">Define each dish once — name, description, price. Assign it to meal slots on the right, as many times as it's served.</p>
                </div>

                <form @submit.prevent="addCatalogItem" class="grid sm:grid-cols-2 gap-2 p-3 rounded-xl border border-slate-200 bg-slate-50/60">
                    <input v-model="catalogForm.name" type="text" placeholder="Item name (e.g. Idli)" class="field text-sm sm:col-span-2">
                    <p v-if="catalogForm.errors.name" class="text-xs text-red-600 sm:col-span-2 -mt-1">{{ catalogForm.errors.name }}</p>
                    <input v-model="catalogForm.description" type="text" placeholder="Description (optional)" class="field text-sm sm:col-span-2">
                    <input v-model="catalogForm.default_price" type="number" min="0" step="0.01" placeholder="Price (₹)" class="field text-sm">
                    <button type="submit" class="btn-primary text-sm" :disabled="catalogForm.processing || !catalogForm.name || !catalogForm.default_price">
                        Add item
                    </button>
                    <p v-if="catalogForm.errors.default_price" class="text-xs text-red-600 sm:col-span-2 -mt-1">{{ catalogForm.errors.default_price }}</p>
                </form>

                <div v-if="catalogItems.length" class="divide-y divide-slate-100 rounded-xl border border-slate-200 max-h-[26rem] overflow-y-auto">
                    <div v-for="c in catalogItems" :key="c.id" class="p-3 hover:bg-slate-50/60">
                        <form v-if="editingCatalogId === c.id" @submit.prevent="saveCatalogEdit(c)" class="grid grid-cols-2 gap-2 text-xs">
                            <input v-model="catalogEditForm.name" type="text" class="field text-xs col-span-2" placeholder="Name">
                            <input v-model="catalogEditForm.description" type="text" class="field text-xs col-span-2" placeholder="Description">
                            <input v-model="catalogEditForm.default_price" type="number" min="0" step="0.01" class="field text-xs">
                            <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="catalogEditForm.is_active"> Active</label>
                            <div class="col-span-2 flex gap-3">
                                <button type="submit" class="text-xs font-semibold text-indigo-600">Save</button>
                                <button type="button" class="text-xs text-slate-500" @click="editingCatalogId = null">Cancel</button>
                            </div>
                        </form>
                        <div v-else class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-sm text-slate-900" :class="{ 'text-slate-400': !c.is_active }">{{ c.name }}</p>
                                <p v-if="c.description" class="text-xs text-slate-500">{{ c.description }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                                    <span class="font-semibold text-slate-700">₹{{ Number(c.default_price).toFixed(2) }}</span>
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 border border-indigo-100">
                                        {{ c.slots_count }} slot{{ c.slots_count === 1 ? '' : 's' }}
                                    </span>
                                    <span v-if="!c.is_active" class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500 border border-slate-200">Inactive</span>
                                </div>
                            </div>
                            <div class="flex gap-3 shrink-0 text-xs">
                                <button class="font-semibold text-indigo-600" @click="startCatalogEdit(c)">Edit</button>
                                <button class="font-semibold text-red-500" @click="removeCatalogItem(c)">Remove</button>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-400">No food items yet — add your first one above.</p>
            </div>

            <div class="card space-y-4">
                <div>
                    <h3 class="section-title flex items-center gap-2"><span aria-hidden="true">📋</span> Assign items to a meal</h3>
                    <p class="section-desc">Select items below, pick a date and meal, then assign. Assigning the same item to a slot twice is safely skipped.</p>
                </div>

                <div v-if="catalogItems.length === 0" class="text-sm text-slate-400">Add a food item on the left first.</div>
                <template v-else>
                    <input v-model="catalogSearch" type="search" class="field text-sm" placeholder="Search food items…">

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <SearchableSelect v-if="eventDates.length" v-model="assignForm.menu_date" :options="eventDateOptions"
                                              :all-option="true" all-label="— Date —" />
                            <input v-else v-model="assignForm.menu_date" type="date" class="field text-sm"
                                   :min="event.event_start" :max="event.event_end">
                        </div>
                        <SearchableSelect v-model="assignForm.meal_type" :options="mealTypeOptions"
                                          :all-option="true" all-label="— Meal —" />
                    </div>
                    <p v-if="assignForm.errors.menu_date" class="text-xs text-red-600 -mt-2">{{ assignForm.errors.menu_date }}</p>
                    <p v-if="assignForm.errors.meal_type" class="text-xs text-red-600 -mt-2">{{ assignForm.errors.meal_type }}</p>

                    <button type="button" class="btn-primary text-sm w-full justify-center"
                            :disabled="selectedCatalogIds.length === 0 || !assignForm.menu_date || !assignForm.meal_type || assignForm.processing"
                            @click="assignCatalogItems">
                        Assign ({{ selectedCatalogIds.length }})
                    </button>

                    <p class="text-xs text-slate-400">{{ filteredCatalogItems.length }} of {{ catalogItems.length }} item(s)</p>

                    <div class="max-h-80 overflow-y-auto rounded-xl border border-slate-200">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 sticky top-0">
                                <tr>
                                    <th class="p-2 w-8"><input type="checkbox" :checked="allCatalogSelected" @change="toggleSelectAllCatalog"></th>
                                    <th class="p-2">Item</th>
                                    <th class="p-2">Price</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="c in filteredCatalogItems" :key="c.id" class="bg-white">
                                    <td class="p-2 align-top"><input type="checkbox" :value="c.id" v-model="selectedCatalogIds"></td>
                                    <td class="p-2">
                                        {{ c.name }}
                                        <span v-if="!c.is_active" class="ml-1 text-[10px] text-slate-400">(inactive)</span>
                                    </td>
                                    <td class="p-2 text-slate-600">₹{{ Number(c.default_price).toFixed(2) }}</td>
                                </tr>
                                <tr v-if="filteredCatalogItems.length === 0">
                                    <td colspan="3" class="p-4 text-center text-sm text-slate-400">No items match "{{ catalogSearch }}".</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>

        <!-- Scheduled menu, grouped by day then by meal (in breakfast → lunch → snacks →
             tea → dinner → other order — see FestFoodMenuItem::MEAL_TYPES, the source of
             truth both here and on the school-facing FoodOrder page). -->
        <div>
            <h3 class="section-title mb-3">Scheduled menu</h3>
            <div v-for="group in groupedItems" :key="group.date" class="card card--flush mb-4">
                <div class="p-3 border-b bg-gray-50 font-bold text-sm">{{ formatCalendarDate(group.date) }}</div>
                <div v-for="mealGroup in group.meals" :key="mealGroup.mealType" class="border-t first:border-t-0">
                    <div class="px-3 py-1.5 bg-gray-50/70 text-xs font-semibold uppercase tracking-wide text-gray-500 flex items-center gap-1.5">
                        <span aria-hidden="true">{{ MEAL_ICONS[mealGroup.mealType] || '🍴' }}</span>
                        <span>{{ mealTypes[mealGroup.mealType] || mealGroup.mealType }}</span>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-400">
                            <tr>
                                <th class="p-3 w-16">Order</th>
                                <th class="p-3">Item</th>
                                <th class="p-3">Price</th>
                                <th class="p-3">Max/school</th>
                                <th class="p-3">Available</th>
                                <th class="p-3 text-right"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in mealGroup.items" :key="item.id" class="border-t">
                                <template v-if="editingId === item.id">
                                    <td class="p-2" colspan="6">
                                        <form @submit.prevent="saveEdit(item)" class="grid grid-cols-7 gap-2 items-center">
                                            <input v-model="editForm.sort_order" type="number" min="0" class="field text-xs" placeholder="Order">
                                            <SearchableSelect v-model="editForm.meal_type" :options="mealTypeOptions"
                                                              :all-option="false" placeholder="Select meal" />
                                            <input v-model="editForm.name" type="text" class="field text-xs col-span-2">
                                            <input v-model="editForm.price" type="number" min="0" step="0.01" class="field text-xs">
                                            <input v-model="editForm.max_per_school" type="number" min="1" class="field text-xs" placeholder="Max">
                                            <label class="text-xs flex items-center gap-1">
                                                <input type="checkbox" v-model="editForm.is_available"> Available
                                            </label>
                                            <div class="col-span-7 flex gap-2">
                                                <button type="submit" class="text-xs font-semibold text-indigo-600">Save</button>
                                                <button type="button" class="text-xs text-gray-500" @click="cancelEdit">Cancel</button>
                                            </div>
                                        </form>
                                    </td>
                                </template>
                                <template v-else>
                                    <td class="p-3 text-gray-500">{{ item.sort_order }}</td>
                                    <td class="p-3">
                                        {{ item.name }}
                                        <p v-if="item.description" class="text-xs text-gray-400">{{ item.description }}</p>
                                    </td>
                                    <td class="p-3">₹{{ Number(item.price).toFixed(2) }}</td>
                                    <td class="p-3">{{ item.max_per_school || '—' }}</td>
                                    <td class="p-3">
                                        <span :class="item.is_available ? 'text-green-700' : 'text-gray-400'" class="text-xs px-2 py-0.5 rounded bg-gray-100">
                                            {{ item.is_available ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-right">
                                        <button class="text-xs font-semibold text-indigo-600 mr-3" @click="startEdit(item)">Edit</button>
                                        <button class="text-xs font-semibold text-red-500" @click="removeItem(item)">Remove</button>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <EmptyState v-if="!menuItems.length" title="Nothing scheduled yet"
                        description="Assign a food item to a date and meal above to build the schedule." />
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import EventHierarchyBadge from '@/Components/fest/EventHierarchyBadge.vue';
import FoodRegionDrillDown from '@/Components/sahodaya/FoodRegionDrillDown.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';
import { useConfirm } from '@/composables/useConfirm';

const MEAL_ICONS = { breakfast: '🌅', lunch: '🍽️', snacks: '🍪', tea: '☕', dinner: '🌙', other: '🍴' };

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, menuItems: { type: Array, default: () => [] },
    catalogItems: { type: Array, default: () => [] },
    hierarchy: { type: Object, default: null },
    mealTypes: { type: Object, default: () => ({}) },
    eventDates: { type: Array, default: () => [] },
    schoolOptions: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    isPartitionedHub: { type: Boolean, default: false },
    foodRegionSummary: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const hubHref = computed(() => (
    props.hierarchy?.parent_event ? `/sahodaya-admin/${props.sahodaya.id}/events/${props.hierarchy.parent_event.id}/food-menu` : null
));
const { confirm } = useConfirm();

function syncToRegions() {
    router.post(`${base}/food-menu/sync-to-regions`, {}, { preserveScroll: true });
}

const payeeForm = useForm({
    food_payee_type: props.event.food_payee_type || 'sahodaya',
    food_host_school_id: props.event.food_host_school_id || '',
    require_payment_for_coupons: props.event.require_payment_for_coupons || false,
});
function savePayee() {
    payeeForm.put(`${base}/food-menu-payee`, { preserveScroll: true });
}

// --- Food item catalog: define once, reuse across as many meal slots as needed ---
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

// --- Assign selected catalog items onto one date+meal slot in bulk ---
const catalogSearch = ref('');
const selectedCatalogIds = ref([]);
const assignForm = useForm({ catalog_item_ids: [], menu_date: '', meal_type: '' });

// Options for the date/meal SearchableSelects above — dates need their labels formatted,
// and meal types (a plain object keyed by meal type) need flattening into {value, label}.
const eventDateOptions = computed(() => props.eventDates.map((d) => ({ value: d, label: formatCalendarDate(d) })));
const mealTypeOptions = computed(() => Object.entries(props.mealTypes).map(([value, label]) => ({ value, label })));

const filteredCatalogItems = computed(() => {
    const q = catalogSearch.value.trim().toLowerCase();
    if (!q) return props.catalogItems;
    return props.catalogItems.filter((c) => c.name.toLowerCase().includes(q) || (c.description || '').toLowerCase().includes(q));
});

// Scoped to the currently filtered set, not the global catalog — searching, selecting
// everything visible, then broadening the search doesn't silently drop the earlier
// selection (mirrors the Phases page's item-assignment panel).
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

function assignCatalogItems() {
    assignForm.catalog_item_ids = selectedCatalogIds.value;
    assignForm.post(`${base}/food-menu/assign-catalog-items`, {
        preserveScroll: true,
        onSuccess: () => { selectedCatalogIds.value = []; },
    });
}

// --- Scheduled menu: per-slot inline edit/remove (unchanged from the direct-add flow) ---
const editingId = ref(null);
const editForm = reactive({ meal_type: '', name: '', price: '', max_per_school: '', is_available: true, sort_order: 0 });
function startEdit(item) {
    editingId.value = item.id;
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
        menu_date: item.menu_date,
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

// Canonical meal order comes from the mealTypes prop (an ordered object, keyed
// breakfast/lunch/snacks/tea/dinner/other — see FestFoodMenuItem::MEAL_TYPES), not from
// however the items array happens to arrive.
const mealTypeOrder = computed(() => Object.keys(props.mealTypes));

const groupedItems = computed(() => {
    const byDate = {};
    for (const item of props.menuItems) {
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
    }));
});
</script>
