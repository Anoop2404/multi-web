<template>
    <SahodayaEventsLayout :title="`${event.title} — Food Menu`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Menu`" eyebrow="Operations"
                    description="Create priced menu items per day/meal for schools to preorder. Schools order and pay from Food Billing." />

        <div class="flex flex-wrap gap-2 mb-4">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-billing`" class="text-sm text-indigo-600">Food Billing →</Link>
            <button v-if="isPartitionedHub" @click="syncToRegions" class="btn-secondary text-sm ml-auto">
                Apply menu to all regions
            </button>
        </div>

        <!-- Payee settings -->
        <div class="card mb-6 max-w-xl">
            <h3 class="font-bold text-sm mb-3">Who food payments go to</h3>
            <form @submit.prevent="savePayee" class="space-y-3">
                <div class="flex gap-4 text-sm">
                    <label class="flex items-center gap-2">
                        <input type="radio" value="sahodaya" v-model="payeeForm.food_payee_type"> Sahodaya (default)
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" value="host_school" v-model="payeeForm.food_payee_type"> A host school
                    </label>
                </div>
                <div v-if="payeeForm.food_payee_type === 'host_school'">
                    <select v-model="payeeForm.food_host_school_id" class="field text-sm">
                        <option value="">— Select school —</option>
                        <option v-for="s in schoolOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
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

        <!-- Add item -->
        <div class="card mb-6 max-w-xl">
            <h3 class="font-bold text-sm mb-3">Add menu item</h3>
            <form @submit.prevent="addItem" class="grid grid-cols-2 gap-3">
                <FormField label="Date" :error="itemForm.errors.menu_date" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="itemForm.menu_date" type="date" class="field text-sm">
                    </template>
                </FormField>
                <FormField label="Meal" :error="itemForm.errors.meal_type" required>
                    <template #default="{ id }">
                        <select :id="id" v-model="itemForm.meal_type" class="field text-sm">
                            <option v-for="(label, key) in mealTypes" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Item name" :error="itemForm.errors.name" class-extra="col-span-2" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="itemForm.name" type="text" class="field text-sm w-full">
                    </template>
                </FormField>
                <FormField label="Description (optional)" :error="itemForm.errors.description" class-extra="col-span-2">
                    <template #default="{ id }">
                        <input :id="id" v-model="itemForm.description" type="text" class="field text-sm w-full">
                    </template>
                </FormField>
                <FormField label="Price (₹)" :error="itemForm.errors.price" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="itemForm.price" type="number" min="0" step="0.01" class="field text-sm">
                    </template>
                </FormField>
                <FormField label="Max qty per school (optional)" :error="itemForm.errors.max_per_school">
                    <template #default="{ id }">
                        <input :id="id" v-model="itemForm.max_per_school" type="number" min="1" class="field text-sm">
                    </template>
                </FormField>
                <FormField label="Display order" hint="Lower numbers show first within the same day/meal. Auto-filled to add at the end — override to reorder." :error="itemForm.errors.sort_order">
                    <template #default="{ id }">
                        <input :id="id" v-model="itemForm.sort_order" type="number" min="0" class="field text-sm">
                    </template>
                </FormField>
                <div class="col-span-2">
                    <button type="submit" class="btn-primary text-sm" :disabled="itemForm.processing">Add item</button>
                </div>
            </form>
        </div>

        <!-- Menu list grouped by day -->
        <div v-for="group in groupedItems" :key="group.date" class="card card--flush mb-4">
            <div class="p-3 border-b bg-gray-50 font-bold text-sm">{{ formatCalendarDate(group.date) }}</div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Order</th>
                        <th class="p-3">Meal</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Price</th>
                        <th class="p-3">Max/school</th>
                        <th class="p-3">Available</th>
                        <th class="p-3 text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in group.items" :key="item.id" class="border-t">
                        <template v-if="editingId === item.id">
                            <td class="p-2" colspan="7">
                                <form @submit.prevent="saveEdit(item)" class="grid grid-cols-7 gap-2 items-center">
                                    <input v-model="editForm.sort_order" type="number" min="0" class="field text-xs" placeholder="Order">
                                    <select v-model="editForm.meal_type" class="field text-xs">
                                        <option v-for="(label, key) in mealTypes" :key="key" :value="key">{{ label }}</option>
                                    </select>
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
                            <td class="p-3 capitalize">{{ mealTypes[item.meal_type] || item.meal_type }}</td>
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
        <EmptyState v-if="!menuItems.length" title="No menu items yet" description="Add items above to build the menu for each event day." />

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, menuItems: { type: Array, default: () => [] },
    mealTypes: { type: Object, default: () => ({}) },
    schoolOptions: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    isPartitionedHub: { type: Boolean, default: false },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;

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

const itemForm = useForm({
    menu_date: '', meal_type: 'lunch', name: '', description: '', price: '', max_per_school: '', sort_order: '',
});

// Auto-fill display order to "add at the end" of whatever day/meal is currently
// selected, so admins don't have to think about it unless they want to reorder.
function nextSortOrderFor(menuDate, mealType) {
    const matches = props.menuItems.filter((i) => i.menu_date === menuDate && i.meal_type === mealType);
    if (!matches.length) return 0;
    return Math.max(...matches.map((i) => Number(i.sort_order) || 0)) + 1;
}
watch(() => [itemForm.menu_date, itemForm.meal_type], ([date, meal]) => {
    if (date && meal) {
        itemForm.sort_order = nextSortOrderFor(date, meal);
    }
});

function addItem() {
    itemForm.post(`${base}/food-menu`, {
        preserveScroll: true,
        onSuccess: () => {
            itemForm.reset('name', 'description', 'price', 'max_per_school');
            itemForm.sort_order = nextSortOrderFor(itemForm.menu_date, itemForm.meal_type);
        },
    });
}

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
function removeItem(item) {
    if (!confirm(`Remove '${item.name}'? Schools who already ordered it keep their order history.`)) return;
    router.delete(`${base}/food-menu/${item.id}`, { preserveScroll: true });
}

const groupedItems = computed(() => {
    const byDate = {};
    for (const item of props.menuItems) {
        const d = item.menu_date;
        if (!byDate[d]) byDate[d] = [];
        byDate[d].push(item);
    }
    return Object.keys(byDate).sort().map((date) => ({ date, items: byDate[date] }));
});
</script>
