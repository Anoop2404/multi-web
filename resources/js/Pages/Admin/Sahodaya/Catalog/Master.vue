<template>
    <SahodayaEventsLayout :title="pageTitle" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :program="program"
                         :show-header-title="false">
        <PageHeader
            :title="pageTitle"
            eyebrow="Items & fees"
            description="Year-independent master catalog — enable items, set fees, then assign to any fest event."
        >
            <template #actions>
                <Link :href="`${catalogBase}/assign`" class="btn-secondary text-xs">Assign to event →</Link>
                <button type="button" class="btn-secondary text-xs" @click="seedCatalog">Sync CKSC items</button>
            </template>
        </PageHeader>

        <div class="notice-banner notice-banner--info mb-4 max-w-3xl text-sm">
            Fees set here apply when items are imported into events. Disable items to hide them from assignment and registration.
            <span class="block mt-1 text-slate-600">
                Icons: <FestItemMetaIcons gender="male" participant-type="individual" class="inline-flex mx-1 align-middle" />
                Boys individual ·
                <FestItemMetaIcons gender="female" participant-type="team" class="inline-flex mx-1 align-middle" />
                Girls team ·
                <FestItemMetaIcons gender="mixed" participant-type="group" class="inline-flex mx-1 align-middle" />
                Mixed group
            </span>
        </div>

        <CatalogSubNav :sahodaya-id="sahodaya.id" :program-slug="program.slug" active="master" />

        <div class="card card--muted !p-4 mb-4">
            <CatalogSectionNav :base="catalogBase" mode="master" :sections="sections" :section="section" />
        </div>

        <div class="grid xl:grid-cols-[1fr_18rem] gap-8 items-start">
            <div class="space-y-4 min-w-0">
                <div class="card !p-4 space-y-3">
                    <form @submit.prevent="applyFilters" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <input v-model="filterForm.q" class="field sm:col-span-2 lg:col-span-4" placeholder="Search name or code">
                        <select v-if="isSports" v-model="filterForm.age_group" class="field">
                            <option value="">All ages</option>
                            <option v-for="(label, key) in ageGroupLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <select v-model="filterForm.gender" class="field">
                            <option value="">All genders</option>
                            <option v-for="(label, key) in taxonomy?.gender ?? {}" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <select v-model="filterForm.participant_type" class="field">
                            <option value="">All formats</option>
                            <option value="individual">Individual</option>
                            <option value="group">Group</option>
                            <option value="team">Team</option>
                        </select>
                        <select v-model="filterForm.enabled" class="field">
                            <option value="">All statuses</option>
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                        <div class="sm:col-span-2 lg:col-span-4 flex flex-wrap gap-2">
                            <button type="submit" class="btn-primary text-sm">Apply filters</button>
                            <button type="button" class="btn-secondary text-sm" @click="clearFilters">Clear</button>
                        </div>
                    </form>

                    <div class="flex flex-wrap gap-2 pt-2 border-t border-slate-100">
                        <button type="button" class="btn-secondary text-xs" :disabled="!selectedIds.length" @click="bulk({ is_enabled: true })">Enable</button>
                        <button type="button" class="btn-secondary text-xs" :disabled="!selectedIds.length" @click="bulk({ is_enabled: false })">Disable</button>
                        <button type="button" class="btn-secondary text-xs" :disabled="!selectedIds.length" @click="showBulkFee = true">Set fee…</button>
                        <span class="text-xs text-slate-400 self-center ml-auto">{{ selectedIds.length }} selected · {{ flatItems.length }} shown</span>
                    </div>
                </div>

                <div class="form-section overflow-hidden !p-0">
                    <EmptyState v-if="!flatItems.length" title="No items here" description="Try another section or sync CKSC items." icon="📋" class="p-8" />
                    <div v-else class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="w-10"><input type="checkbox" :checked="allVisibleSelected" @change="toggleAllVisible"></th>
                                    <th class="w-16 text-center">Type</th>
                                    <th>Item</th>
                                    <th class="w-16 text-center">On</th>
                                    <th class="w-28 text-right">Fee ₹</th>
                                    <th class="w-28 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in flatItems" :key="item.id" :class="!item.is_enabled ? 'bg-slate-50/80' : ''">
                                    <td><input type="checkbox" :value="item.id" v-model="selectedIds"></td>
                                    <td class="text-center">
                                        <FestItemMetaIcons :gender="item.gender" :participant-type="item.participant_type" />
                                    </td>
                                    <td>
                                        <p :class="item.is_enabled ? 'font-medium text-slate-900' : 'text-slate-400 line-through'">{{ item.title }}</p>
                                        <p class="text-xs text-slate-400 mt-0.5">{{ itemTags(item) }}</p>
                                        <span v-if="item.source === 'custom'" class="text-[10px] font-semibold text-violet-600">Custom</span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button"
                                                class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                                :class="item.is_enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'"
                                                @click="toggleItem(item, 'is_enabled', !item.is_enabled)">
                                            {{ item.is_enabled ? 'On' : 'Off' }}
                                        </button>
                                    </td>
                                    <td class="text-right">
                                        <input v-if="item.fee_enabled || item.fee_amount != null || feeEditingId === item.id"
                                               :value="item.fee_amount ?? ''"
                                               type="number" min="0" step="0.01"
                                               class="field w-24 text-xs py-1 ml-auto text-right"
                                               placeholder="—"
                                               @focus="feeEditingId = item.id"
                                               @change="updateFee(item, $event.target.value)">
                                        <button v-else type="button" class="text-xs text-[#0f3d7a] font-semibold hover:underline" @click="enableFee(item)">Add fee</button>
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <button type="button" class="text-xs font-semibold text-[#0f3d7a] mr-2" @click="openEdit(item)">Edit</button>
                                        <button v-if="item.source === 'custom'" type="button" class="text-xs text-red-600 font-semibold" @click="removeItem(item)">Delete</button>
                                        <button v-else type="button" class="text-xs text-slate-400" title="CKSC items cannot be deleted — disable instead" @click="toggleItem(item, 'is_enabled', false)">Disable</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <aside class="space-y-4 xl:sticky xl:top-6">
                <FormSection title="Add custom item" hint="Saved to master catalog for all years.">
                    <form @submit.prevent="addCustom" class="space-y-3">
                        <input v-model="customForm.title" class="field text-sm" placeholder="Item name *" required>
                        <select v-if="isSports" v-model="customForm.age_group" class="field text-sm">
                            <option value="">Age group</option>
                            <option v-for="(label, key) in ageGroupLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <div class="grid grid-cols-2 gap-2">
                            <select v-model="customForm.gender" class="field text-sm">
                                <option value="open">Open gender</option>
                                <option v-for="(label, key) in taxonomy?.gender ?? {}" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <select v-model="customForm.participant_type" class="field text-sm">
                                <option value="individual">Individual</option>
                                <option value="group">Group</option>
                                <option value="team">Team</option>
                            </select>
                        </div>
                        <input v-model.number="customForm.fee_amount" type="number" min="0" step="0.01" class="field text-sm" placeholder="Fee ₹ (optional)">
                        <button type="submit" class="btn-primary w-full text-sm" :disabled="customForm.processing">Add item</button>
                    </form>
                </FormSection>
                <FormSection title="Next step">
                    <Link :href="`${catalogBase}/assign`" class="btn-primary w-full text-sm text-center block">Assign to event →</Link>
                    <Link :href="listUrl" class="btn-secondary w-full text-sm text-center block mt-2">View listing</Link>
                </FormSection>
            </aside>
        </div>

        <!-- Edit modal -->
        <div v-if="editingItem" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="editingItem = null"></div>
            <form @submit.prevent="saveEdit" class="relative modal-shell max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <div class="modal-head">
                    <div>
                        <h3 class="font-bold text-[#041525]">Edit catalog item</h3>
                        <p class="text-xs text-gray-500 mt-0.5 flex items-center gap-2">
                            <FestItemMetaIcons :gender="editForm.gender" :participant-type="editForm.participant_type" />
                            {{ editingItem.source === 'custom' ? 'Custom' : 'CKSC' }}
                        </p>
                    </div>
                    <button type="button" class="text-2xl text-gray-400" @click="editingItem = null">&times;</button>
                </div>
                <div class="p-6 space-y-3">
                    <input v-if="editingItem.source === 'custom'" v-model="editForm.title" class="field" required>
                    <p v-else class="text-sm font-medium text-slate-800">{{ editingItem.title }}</p>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="editForm.is_enabled" type="checkbox" class="rounded"> Enabled for assignment
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <select v-model="editForm.gender" class="field text-sm">
                            <option value="open">Open gender</option>
                            <option v-for="(label, key) in taxonomy?.gender ?? {}" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <select v-model="editForm.participant_type" class="field text-sm">
                            <option value="individual">Individual</option>
                            <option value="group">Group</option>
                            <option value="team">Team</option>
                        </select>
                    </div>
                    <select v-if="isSports" v-model="editForm.age_group" class="field text-sm">
                        <option value="">Age group</option>
                        <option v-for="(label, key) in ageGroupLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <input v-model.number="editForm.fee_amount" type="number" min="0" step="0.01" class="field text-sm" placeholder="Fee ₹">
                </div>
                <div class="modal-foot flex justify-end gap-2">
                    <button type="button" class="btn-ghost text-sm" @click="editingItem = null">Cancel</button>
                    <button type="submit" class="btn-primary text-sm">Save</button>
                </div>
            </form>
        </div>

        <!-- Bulk fee modal -->
        <div v-if="showBulkFee" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60" @click="showBulkFee = false"></div>
            <div class="relative modal-shell max-w-sm w-full p-6 space-y-4">
                <h3 class="font-bold">Set fee for {{ selectedIds.length }} items</h3>
                <input v-model.number="bulkFeeAmount" type="number" min="0" step="0.01" class="field" placeholder="Amount ₹">
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-ghost text-sm" @click="showBulkFee = false">Cancel</button>
                    <button type="button" class="btn-primary text-sm" @click="applyBulkFee">Apply</button>
                </div>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import CatalogSubNav from '@/Components/sahodaya/CatalogSubNav.vue';
import CatalogSectionNav from '@/Components/sahodaya/CatalogSectionNav.vue';
import FestItemMetaIcons from '@/Components/sahodaya/FestItemMetaIcons.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    section: Object,
    sections: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    summary: Object,
    filters: Object,
    taxonomy: Object,
    ageGroupLabels: Object,
    groupedItems: Object,
    activityLogs: { type: Array, default: () => [] },
});

const catalogBase = `/sahodaya-admin/${props.sahodaya.id}/programs/${props.program.slug}/catalog`;
const listUrl = computed(() => {
    if (!props.section?.slug || props.section.slug === 'all') return `${catalogBase}/list`;
    return `${catalogBase}/list/${props.section.slug}`;
});
const pageBase = computed(() => `${catalogBase}/master${props.section?.slug && props.section.slug !== 'all' ? `/${props.section.slug}` : ''}`);
const isSports = computed(() => props.program.eventType === 'sports');
const selectedIds = ref([]);
const editingItem = ref(null);
const showBulkFee = ref(false);
const bulkFeeAmount = ref(null);
const feeEditingId = ref(null);

const editForm = reactive({
    title: '',
    is_enabled: true,
    gender: 'open',
    participant_type: 'individual',
    age_group: '',
    fee_amount: null,
});

const pageTitle = computed(() =>
    `${props.program.label} — ${props.section?.label ?? 'Items & fees'}`,
);

const flatItems = computed(() => {
    if (!props.groupedItems) return props.items;
    return Object.values(props.groupedItems).flat();
});

const filterForm = useForm({
    q: props.filters?.q ?? '',
    age_group: props.filters?.age_group ?? '',
    gender: props.filters?.gender ?? '',
    participant_type: props.filters?.participant_type ?? '',
    enabled: props.filters?.enabled ?? '',
});

const customForm = useForm({
    title: '',
    participant_type: 'individual',
    gender: 'open',
    age_group: '',
    fee_amount: null,
});

const allVisibleSelected = computed(() =>
    flatItems.value.length > 0 && flatItems.value.every((i) => selectedIds.value.includes(i.id)),
);

function itemTags(item) {
    const parts = [];
    if (item.item_code) parts.push(item.item_code);
    if (isSports.value && item.age_group) parts.push(props.ageGroupLabels?.[item.age_group] ?? item.age_group);
    else if (item.kids_band) parts.push(props.taxonomy?.kids_band?.[item.kids_band] ?? item.kids_band);
    else if (item.class_group && item.class_group !== 'open') parts.push(props.taxonomy?.class_group?.[item.class_group] ?? item.class_group);
    if (item.sport_discipline) parts.push(item.sport_discipline);
    return parts.join(' · ');
}

function applyFilters() {
    filterForm.get(pageBase.value, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    filterForm.reset();
    router.get(pageBase.value, {}, { preserveState: true, preserveScroll: true });
}

function toggleAllVisible(e) {
    selectedIds.value = e.target.checked ? flatItems.value.map((i) => i.id) : [];
}

function toggleItem(item, field, value) {
    router.put(`${catalogBase}/items/${item.id}`, { [field]: value }, { preserveScroll: true, preserveState: true });
}

function enableFee(item) {
    feeEditingId.value = item.id;
    toggleItem(item, 'fee_enabled', true);
}

function updateFee(item, value) {
    router.put(`${catalogBase}/items/${item.id}`, {
        fee_enabled: true,
        fee_amount: value === '' ? null : Number(value),
    }, { preserveScroll: true, preserveState: true });
}

function bulk(payload) {
    if (!selectedIds.value.length) return;
    router.post(`${catalogBase}/bulk`, { item_ids: selectedIds.value, ...payload }, { preserveScroll: true });
}

function applyBulkFee() {
    if (bulkFeeAmount.value == null || bulkFeeAmount.value === '') return;
    bulk({ fee_amount: bulkFeeAmount.value, fee_enabled: true });
    showBulkFee.value = false;
    bulkFeeAmount.value = null;
}

function seedCatalog() {
    router.post(`${catalogBase}/seed`, {}, { preserveScroll: true });
}

function addCustom() {
    customForm.post(`${catalogBase}/items`, {
        preserveScroll: true,
        onSuccess: () => customForm.reset(),
    });
}

function openEdit(item) {
    editingItem.value = item;
    Object.assign(editForm, {
        title: item.title,
        is_enabled: item.is_enabled,
        gender: item.gender ?? 'open',
        participant_type: item.participant_type ?? 'individual',
        age_group: item.age_group ?? '',
        fee_amount: item.fee_amount,
    });
}

function saveEdit() {
    router.put(`${catalogBase}/items/${editingItem.value.id}`, {
        title: editForm.title,
        is_enabled: editForm.is_enabled,
        gender: editForm.gender,
        participant_type: editForm.participant_type,
        age_group: editForm.age_group || null,
        fee_enabled: editForm.fee_amount != null,
        fee_amount: editForm.fee_amount,
    }, {
        preserveScroll: true,
        onSuccess: () => { editingItem.value = null; },
    });
}

function removeItem(item) {
    if (!confirm(`Delete "${item.title}" from the master catalog?`)) return;
    router.delete(`${catalogBase}/items/${item.id}`, { preserveScroll: true });
}
</script>
