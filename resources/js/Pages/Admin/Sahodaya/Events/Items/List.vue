<template>
    <SahodayaEventsLayout :title="`${event.title} — Item listing`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Item listing`" eyebrow="Items under heads"
                    :description="isSports
                        ? 'Review every item, see its master values, and move items between Event Heads.'
                        : 'Review every item, see its master values, and move items between item heads.'">
            <template #actions>
                <button type="button" class="btn-primary text-xs" @click="openAddItem()">Add item</button>
                <button v-if="isSports" type="button" class="btn-secondary text-xs" @click="openAddHead()">Add Event Head</button>
                <Link v-if="isSports" :href="`${base}/competition`" class="btn-secondary text-xs">Event Heads & scheduling</Link>
                <Link :href="`${base}/items`" class="btn-secondary text-xs">Add / edit items</Link>
                <Link v-if="catalogMasterUrl" :href="catalogMasterUrl" class="btn-secondary text-xs">Catalog master</Link>
            </template>
        </PageHeader>

        <SportsSetupSubNav v-if="isSports" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="items-list" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="items-list" />

        <section v-if="isSports" class="grid md:grid-cols-2 xl:grid-cols-4 gap-3 mb-5">
            <Link v-for="card in setupCards" :key="card.title" :href="card.href"
                  class="card !p-4 hover:border-[#0f3d7a]/30 transition block">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ card.eyebrow }}</p>
                <h3 class="font-semibold text-[#0f3d7a] mt-1">{{ card.title }}</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ card.description }}</p>
            </Link>
        </section>

        <section class="card !p-4 mb-5 space-y-3">
            <div class="flex flex-wrap gap-3 items-center">
                <input v-model="searchQuery" type="search" class="field flex-1 min-w-[12rem] max-w-md"
                       placeholder="Search by item, code, head, venue, discipline…" autocomplete="off">
                <select v-if="isSports" v-model="headFilter" class="field max-w-xs">
                    <option value="">All Event Heads</option>
                    <option v-for="head in itemHeads" :key="head.id" :value="String(head.id)">{{ head.name }}</option>
                    <option value="other">Unassigned items</option>
                </select>
                <select v-if="isSports" v-model="ageFilter" class="field max-w-[11rem]">
                    <option value="">All ages</option>
                    <option v-for="(label, key) in taxonomy?.age_group ?? {}" :key="key" :value="key">{{ label }}</option>
                </select>
                <select v-model="statusFilter" class="field max-w-[10rem]">
                    <option value="">All statuses</option>
                    <option value="on">Enabled</option>
                    <option value="off">Disabled</option>
                </select>
                <button v-if="hasFilters" type="button" class="btn-secondary text-sm" @click="clearFilters">
                    Clear
                </button>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span class="font-semibold text-slate-700">{{ filteredItems.length }} / {{ flatItems.length }} items</span>
                <span v-if="isSports">Grouped by Event Head. Use “Move Event Head” to transfer an item from one head to another.</span>
            </div>
        </section>

        <div class="form-section overflow-hidden !p-0">
            <EmptyState v-if="!flatItems.length" title="No items" description="Import from catalog or add custom items." icon="📋" class="p-8" />
            <EmptyState v-else-if="!filteredItems.length" title="No matches" :description="`Nothing matches “${searchQuery.trim()}”. Try another term.`" icon="🔍" class="p-8" />

            <div v-else-if="isSports" class="divide-y divide-slate-100">
                <section v-for="group in groupedFilteredItems" :key="group.key" class="bg-white">
                    <div class="bg-slate-50 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">{{ group.label }}</h3>
                            <p class="text-xs text-slate-500">{{ group.rows.length }} item{{ group.rows.length === 1 ? '' : 's' }}</p>
                        </div>
                        <div v-if="group.headId" class="flex flex-wrap items-center gap-3">
                            <button type="button" class="text-xs font-semibold text-slate-600 hover:text-indigo-700 hover:underline"
                                    @click="openEditHead(group.headId)">
                                Edit Event Head
                            </button>
                            <button type="button" class="text-xs font-semibold text-indigo-600 hover:underline"
                                    @click="openAddItem(group.headId)">
                                Add item under this Event Head →
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="data-table text-sm">
                            <thead>
                                <tr>
                                    <th class="w-12 text-center">On</th>
                                    <th class="w-14 text-center">Type</th>
                                    <th class="min-w-[13rem]">Item</th>
                                    <th class="whitespace-nowrap">Venue</th>
                                    <th class="whitespace-nowrap">Discipline</th>
                                    <th class="whitespace-nowrap">Age group</th>
                                    <th class="whitespace-nowrap">Gender</th>
                                    <th class="whitespace-nowrap">Participant</th>
                                    <th class="whitespace-nowrap text-center">Qualifiers</th>
                                    <th class="whitespace-nowrap text-center">Max/school</th>
                                    <th class="whitespace-nowrap text-right">Fee</th>
                                    <th class="whitespace-nowrap text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in group.rows" :key="item.id" :class="item.is_enabled === false ? 'opacity-60 bg-slate-50/70' : ''">
                                    <td class="text-center">
                                        <button v-if="canEdit(item)" type="button"
                                                class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                                :class="item.is_enabled !== false ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'"
                                                @click="quickUpdate(item, { is_enabled: item.is_enabled === false })">
                                            {{ item.is_enabled !== false ? 'On' : 'Off' }}
                                        </button>
                                        <span v-else :class="item.is_enabled !== false ? 'text-emerald-700 text-xs font-semibold' : 'text-slate-400 text-xs'">
                                            {{ item.is_enabled !== false ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <FestItemMetaIcons :gender="item.gender" :participant-type="item.participant_type" />
                                    </td>
                                    <td>
                                        <p class="font-medium text-slate-900">{{ item.title }}</p>
                                        <p v-if="item.item_code" class="text-xs text-slate-400 font-mono mt-0.5">{{ item.item_code }}</p>
                                        <p v-if="squadSummary(item)" class="text-xs text-slate-500 mt-0.5">{{ squadSummary(item) }}</p>
                                    </td>
                                    <td class="text-slate-600">{{ venueLabel(item.venue_type) }}</td>
                                    <td class="text-slate-600">{{ disciplineLabel(item.sport_discipline) }}</td>
                                    <td class="text-slate-600">{{ categoryLabel(item) }}</td>
                                    <td class="text-slate-600">{{ genderLabel(item.gender) }}</td>
                                    <td class="text-slate-600">{{ participantLabel(item.participant_type) }}</td>
                                    <td class="text-center text-slate-600">{{ item.qualify_count ?? '—' }}</td>
                                    <td class="text-center text-slate-600">{{ item.max_per_school ?? '—' }}</td>
                                    <td class="text-right text-slate-600">
                                        <span v-if="item.fee_amount != null">₹{{ item.fee_amount }}</span>
                                        <span v-else class="text-slate-400">Default</span>
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <button v-if="canEdit(item)" type="button" class="text-xs font-semibold text-[#0f3d7a] mr-2" @click="openEdit(item)">
                                            Edit
                                        </button>
                                        <button v-if="canEdit(item)" type="button" class="text-xs font-semibold text-indigo-600" @click="openEdit(item, true)">
                                            Move Event Head
                                        </button>
                                        <span v-else class="text-xs text-amber-600">State item</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="data-table text-sm">
                    <thead>
                        <tr>
                            <th class="w-12 text-center">On</th>
                            <th class="w-14 text-center">Type</th>
                            <th class="min-w-[10rem]">Item</th>
                            <th class="whitespace-nowrap">{{ event.event_type === 'kids_fest' ? 'Band' : 'Class' }}</th>
                            <th class="whitespace-nowrap">Gender</th>
                            <th class="whitespace-nowrap">Participant</th>
                            <th class="whitespace-nowrap text-center">Qualifiers</th>
                            <th class="whitespace-nowrap text-center">Max/school</th>
                            <th class="whitespace-nowrap text-right">Fee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in filteredItems" :key="item.id" :class="item.is_enabled === false ? 'opacity-60' : ''">
                            <td class="text-center">
                                <span :class="item.is_enabled !== false ? 'text-emerald-700 text-xs font-semibold' : 'text-slate-400 text-xs'">
                                    {{ item.is_enabled !== false ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <FestItemMetaIcons :gender="item.gender" :participant-type="item.participant_type" />
                            </td>
                            <td>
                                <p class="font-medium text-slate-900">{{ item.title }}</p>
                                <p v-if="item.item_code" class="text-xs text-slate-400 font-mono mt-0.5">{{ item.item_code }}</p>
                                <p v-if="squadSummary(item)" class="text-xs text-slate-500 mt-0.5">{{ squadSummary(item) }}</p>
                            </td>
                            <td class="text-slate-600">{{ categoryLabel(item) }}</td>
                            <td class="text-slate-600">{{ genderLabel(item.gender) }}</td>
                            <td class="text-slate-600">{{ participantLabel(item.participant_type) }}</td>
                            <td class="text-center text-slate-600">{{ item.qualify_count ?? '—' }}</td>
                            <td class="text-center text-slate-600">{{ item.max_per_school ?? '—' }}</td>
                            <td class="text-right text-slate-600">
                                <span v-if="item.fee_amount != null">₹{{ item.fee_amount }}</span>
                                <span v-else class="text-slate-400">Default</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />

        <div v-if="headModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeHeadModal">
            <form @submit.prevent="saveHead" class="card w-full max-w-lg shadow-xl space-y-4">
                <div>
                    <h3 class="section-title">{{ editingHeadId ? 'Edit Event Head' : 'Add Event Head' }}</h3>
                    <p class="section-desc text-xs mt-1">
                        Heads group sports items for ID cards, schedules, reports, and registration flow.
                    </p>
                </div>

                <FormField label="Event Head name">
                    <input v-model="headForm.name" class="field" required placeholder="e.g. Athletics">
                </FormField>
                <FormField label="Sport discipline">
                    <select v-model="headForm.sport_discipline" class="field">
                        <option value="">Any</option>
                        <option v-for="(label, key) in taxonomy?.sport_discipline ?? {}" :key="key" :value="key">{{ label }}</option>
                    </select>
                </FormField>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="headForm.is_team_heading"> Use as ID card heading
                </label>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                    After saving, add items under this head or schedule the head from
                    <Link :href="`${base}/competition`" class="link-brand">Event Heads & scheduling →</Link>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="closeHeadModal" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="headForm.processing">
                        {{ editingHeadId ? 'Save Event Head' : 'Add Event Head' }}
                    </button>
                </div>
            </form>
        </div>

        <div v-if="addingItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeAddItem">
            <form @submit.prevent="saveNewItem" class="card w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-xl space-y-4">
                <div>
                    <h3 class="section-title">Add item</h3>
                    <p class="section-desc text-xs mt-1">
                        Add an event-specific item and place it under the correct Event Head.
                    </p>
                </div>

                <FormGrid>
                    <FormField label="Item name" class-extra="sm:col-span-2">
                        <input v-model="addForm.title" class="field" required placeholder="e.g. U14 — 100m Girls">
                    </FormField>
                    <FormField v-if="isSports && itemHeads.length" label="Event Head">
                        <select v-model="addForm.head_id" class="field">
                            <option value="">Unassigned</option>
                            <option v-for="h in itemHeads" :key="h.id" :value="h.id">{{ h.name }}</option>
                        </select>
                    </FormField>

                    <template v-if="isSports">
                        <FormField label="Age group">
                            <select v-model="addForm.age_group" class="field">
                                <option value="">Age group</option>
                                <option v-for="(label, key) in taxonomy?.age_group ?? {}" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Gender">
                            <select v-model="addForm.gender" class="field">
                                <option value="open">Open</option>
                                <option v-for="(label, key) in taxonomy?.gender ?? {}" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Venue type">
                            <select v-model="addForm.venue_type" class="field">
                                <option value="">Venue type</option>
                                <option v-for="(label, key) in taxonomy?.venue_type ?? {}" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Discipline">
                            <select v-model="addForm.sport_discipline" class="field">
                                <option value="">Discipline</option>
                                <option v-for="(label, key) in taxonomy?.sport_discipline ?? {}" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Format">
                            <select v-model="addForm.competition_format" class="field">
                                <option value="">Format</option>
                                <option v-for="(label, key) in taxonomy?.competition_format ?? {}" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                    </template>

                    <FormField v-else-if="event.event_type === 'kids_fest'" label="Kids Fest band">
                        <select v-model="addForm.kids_band" class="field">
                            <option value="">Kids Fest band</option>
                            <option v-for="(label, key) in taxonomy?.kids_band ?? {}" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField v-else label="Class category">
                        <select v-model="addForm.class_group" class="field">
                            <option value="">Class category</option>
                            <option v-for="(label, key) in taxonomy?.class_group ?? {}" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>

                    <FormField label="Participant type">
                        <select v-model="addForm.participant_type" class="field">
                            <option v-for="(label, key) in taxonomy?.participant_type ?? {}" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField label="Qualifiers to next level">
                        <input v-model.number="addForm.qualify_count" type="number" min="1" class="field">
                    </FormField>
                    <FormField label="Max per school">
                        <input v-model.number="addForm.max_per_school" type="number" min="1" class="field">
                    </FormField>
                    <FormField label="Fee override (₹)" class-extra="sm:col-span-2">
                        <input v-model.number="addForm.fee_amount" type="number" min="0" class="field" placeholder="Leave blank for default">
                    </FormField>
                </FormGrid>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                    Need a missing value in venue, discipline, format, gender, or participant type?
                    <Link :href="taxonomyMastersUrl" class="link-brand">Open dropdown masters →</Link>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="closeAddItem" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="addForm.processing">Add item</button>
                </div>
            </form>
        </div>

        <div v-if="editingItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeEdit">
            <form @submit.prevent="saveEdit" class="card w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-xl space-y-4">
                <div>
                    <h3 class="section-title">{{ moveFocus ? 'Move item to another head' : 'Edit item' }}</h3>
                    <p class="section-desc text-xs mt-1">
                        {{ editingItem.title }} · current head: {{ headLabel(editingItem.head_id) }}
                    </p>
                </div>

                <FormGrid>
                    <FormField label="Item name" class-extra="sm:col-span-2">
                        <input v-model="editForm.title" class="field" required>
                    </FormField>
                    <FormField label="Enabled for this event">
                        <CheckboxField v-model="editForm.is_enabled" label="Schools can register for this item" />
                    </FormField>
                    <FormField v-if="isSports && itemHeads.length" label="Event Head">
                        <select v-model="editForm.head_id" class="field">
                            <option value="">Unassigned</option>
                            <option v-for="h in itemHeads" :key="h.id" :value="h.id">{{ h.name }}</option>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Changing this moves the item under the selected head.</p>
                    </FormField>

                    <template v-if="isSports">
                        <FormField label="Age group">
                            <select v-model="editForm.age_group" class="field">
                                <option value="">Age group</option>
                                <option v-for="(label, key) in taxonomy?.age_group ?? {}" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Gender">
                            <select v-model="editForm.gender" class="field">
                                <option value="open">Open</option>
                                <option v-for="(label, key) in taxonomy?.gender ?? {}" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Venue type">
                            <select v-model="editForm.venue_type" class="field">
                                <option value="">Venue type</option>
                                <option v-for="(label, key) in taxonomy?.venue_type ?? {}" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Discipline">
                            <select v-model="editForm.sport_discipline" class="field">
                                <option value="">Discipline</option>
                                <option v-for="(label, key) in taxonomy?.sport_discipline ?? {}" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Format">
                            <select v-model="editForm.competition_format" class="field">
                                <option value="">Format</option>
                                <option v-for="(label, key) in taxonomy?.competition_format ?? {}" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                    </template>

                    <FormField v-else-if="event.event_type === 'kids_fest'" label="Kids Fest band">
                        <select v-model="editForm.kids_band" class="field">
                            <option value="">Kids Fest band</option>
                            <option v-for="(label, key) in taxonomy?.kids_band ?? {}" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField v-else label="Class category">
                        <select v-model="editForm.class_group" class="field">
                            <option value="">Class category</option>
                            <option v-for="(label, key) in taxonomy?.class_group ?? {}" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>

                    <FormField label="Participant type">
                        <select v-model="editForm.participant_type" class="field">
                            <option v-for="(label, key) in taxonomy?.participant_type ?? {}" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField label="Qualifiers to next level">
                        <input v-model.number="editForm.qualify_count" type="number" min="1" class="field">
                    </FormField>
                    <FormField label="Max per school">
                        <input v-model.number="editForm.max_per_school" type="number" min="1" class="field">
                    </FormField>
                    <FormField label="Fee override (₹)" class-extra="sm:col-span-2">
                        <input v-model.number="editForm.fee_amount" type="number" min="0" class="field" placeholder="Leave blank for default">
                    </FormField>
                </FormGrid>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                    Need to change dropdown values like venue, format, discipline, gender, or participant type?
                    <Link :href="taxonomyMastersUrl" class="link-brand">Open dropdown masters →</Link>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="closeEdit" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="editForm.processing">Save changes</button>
                </div>
            </form>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import FestItemMetaIcons from '@/Components/sahodaya/FestItemMetaIcons.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import {
    festItemGenderLabel,
    festItemListingDetails,
    festItemParticipantTypeLabel,
    festItemSearchHaystack,
} from '@/support/festItemListingMeta.js';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, itemsByLevel: Object, groupedItems: Object, taxonomy: Object,
    itemHeads: { type: Array, default: () => [] },
    ownerLevelLabels: Object, activityLogs: { type: Array, default: () => [] },
    taxonomyMastersUrl: String,
    catalogUrl: String,
    sportsAgeGroupsUrl: String,
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const isSports = computed(() => props.event.event_type === 'sports');
const searchQuery = ref('');
const headFilter = ref('');
const ageFilter = ref('');
const statusFilter = ref('');
const headModalOpen = ref(false);
const editingHeadId = ref(null);
const addingItem = ref(false);
const editingItem = ref(null);
const moveFocus = ref(false);

const catalogMasterUrl = computed(() => props.catalogUrl?.replace(/\/assign$/, '') ?? null);
const dropdownMastersUrl = computed(() => `${props.taxonomyMastersUrl}?dimension=sport_discipline`);

const setupCards = computed(() => [
    {
        eyebrow: '1. Event Heads',
        title: 'Event Head master',
        description: 'Create heads like Athletics, Chess, Aquatics and set head-level schedule.',
        href: `${base}/competition`,
    },
    {
        eyebrow: '2. Dropdowns',
        title: 'Dropdown masters',
        description: 'Manage venue type, format, discipline, gender and participant options.',
        href: dropdownMastersUrl.value,
    },
    {
        eyebrow: '3. Catalog',
        title: 'Items & fees catalog',
        description: 'Maintain reusable master items and fees before assigning them to events.',
        href: catalogMasterUrl.value ?? `${base}/items`,
    },
    {
        eyebrow: '4. This event',
        title: 'Items under heads',
        description: 'Add, enable, disable and edit the event-specific items schools register for.',
        href: `${base}/items`,
    },
]);

const flatItems = computed(() => {
    if (props.groupedItems) return Object.values(props.groupedItems).flat();
    return Object.values(props.itemsByLevel ?? {}).flat();
});

const hasFilters = computed(() => Boolean(searchQuery.value.trim() || headFilter.value || ageFilter.value || statusFilter.value));

function itemMetaOptions() {
    return { taxonomy: props.taxonomy, eventType: props.event.event_type };
}

const filteredItems = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();

    return flatItems.value.filter((item) => {
        if (q) {
            const haystack = `${festItemSearchHaystack(item, itemMetaOptions())} ${headLabel(item.head_id)}`.toLowerCase();
            const terms = q.split(/\s+/).filter(Boolean);
            if (!terms.every((term) => haystack.includes(term))) return false;
        }
        if (isSports.value && headFilter.value === 'other' && item.head_id) return false;
        if (isSports.value && headFilter.value && headFilter.value !== 'other' && String(item.head_id ?? '') !== headFilter.value) return false;
        if (isSports.value && ageFilter.value && item.age_group !== ageFilter.value) return false;
        if (statusFilter.value === 'on' && item.is_enabled === false) return false;
        if (statusFilter.value === 'off' && item.is_enabled !== false) return false;
        return true;
    });
});

const groupedFilteredItems = computed(() => {
    const headOrder = new Map(props.itemHeads.map((head, index) => [String(head.id), index]));
    const groups = new Map();

    for (const item of filteredItems.value) {
        const key = item.head_id ? String(item.head_id) : 'other';
        if (!groups.has(key)) {
            groups.set(key, {
                key,
                headId: item.head_id || null,
                label: headLabel(item.head_id),
                rows: [],
            });
        }
        groups.get(key).rows.push(item);
    }

    return [...groups.values()].sort((a, b) => {
        if (a.key === 'other') return 1;
        if (b.key === 'other') return -1;
        return (headOrder.get(a.key) ?? 999) - (headOrder.get(b.key) ?? 999);
    });
});

const editForm = useForm({
    title: '', is_enabled: true, gender: 'open', class_group: '', age_group: '', kids_band: '',
    venue_type: '', sport_discipline: '', competition_format: '', participant_type: 'individual', head_id: '',
    qualify_count: null, max_per_school: null, fee_amount: null,
});
const addForm = useForm({
    title: '', gender: 'open', class_group: '', age_group: '', kids_band: '',
    venue_type: '', sport_discipline: '', competition_format: '', participant_type: 'individual', head_id: '',
    qualify_count: null, max_per_school: null, fee_amount: null,
});
const headForm = useForm({
    name: '',
    sport_discipline: '',
    is_team_heading: true,
});

function clearFilters() {
    searchQuery.value = '';
    headFilter.value = '';
    ageFilter.value = '';
    statusFilter.value = '';
}

function canEdit(item) {
    return item.owner_level !== 'state';
}

function headLabel(headId) {
    if (!headId) return 'Unassigned';
    return props.itemHeads.find((head) => String(head.id) === String(headId))?.name ?? 'Unknown head';
}

function headById(headId) {
    return props.itemHeads.find((head) => String(head.id) === String(headId)) ?? null;
}

function venueLabel(value) {
    if (!value) return '—';
    return props.taxonomy?.venue_type?.[value] ?? (value === 'outdoor' ? 'Outdoor' : value === 'indoor' ? 'Indoor' : value);
}

function disciplineLabel(value) {
    if (!value) return '—';
    return props.taxonomy?.sport_discipline?.[value] ?? value;
}

function categoryLabel(item) {
    if (isSports.value && item.age_group) {
        return props.taxonomy?.age_group?.[item.age_group] ?? item.age_group;
    }
    if (props.event.event_type === 'kids_fest' && item.kids_band) {
        return props.taxonomy?.kids_band?.[item.kids_band] ?? item.kids_band;
    }
    if (item.class_group) {
        return props.taxonomy?.class_group?.[item.class_group] ?? item.class_group;
    }
    return '—';
}

function genderLabel(gender) {
    return festItemGenderLabel(gender, props.taxonomy);
}

function participantLabel(type) {
    return festItemParticipantTypeLabel(type);
}

function squadSummary(item) {
    const squad = festItemListingDetails(item, itemMetaOptions()).find((f) => f.label === 'Squad rules');
    return squad?.value ?? '';
}

function payloadFor(item, overrides = {}) {
    return {
        title: item.title,
        is_enabled: item.is_enabled !== false,
        gender: item.gender ?? 'open',
        class_group: item.class_group ?? '',
        age_group: item.age_group ?? '',
        kids_band: item.kids_band ?? '',
        venue_type: item.venue_type ?? '',
        sport_discipline: item.sport_discipline ?? '',
        competition_format: item.competition_format ?? '',
        participant_type: item.participant_type ?? 'individual',
        head_id: item.head_id ?? '',
        qualify_count: item.qualify_count ?? null,
        max_per_school: item.max_per_school ?? null,
        fee_amount: item.fee_amount ?? null,
        ...overrides,
    };
}

function quickUpdate(item, overrides) {
    if (!canEdit(item)) return;
    router.put(`${base}/items/${item.id}`, payloadFor(item, overrides), {
        preserveScroll: true,
        preserveState: true,
    });
}

function openAddHead() {
    editingHeadId.value = null;
    headForm.clearErrors();
    headForm.name = '';
    headForm.sport_discipline = '';
    headForm.is_team_heading = true;
    headModalOpen.value = true;
}

function openEditHead(headId) {
    const head = headById(headId);
    if (!head) return;
    editingHeadId.value = head.id;
    headForm.clearErrors();
    headForm.name = head.name ?? '';
    headForm.sport_discipline = head.sport_discipline ?? '';
    headForm.is_team_heading = head.is_team_heading !== false;
    headModalOpen.value = true;
}

function closeHeadModal() {
    headModalOpen.value = false;
    editingHeadId.value = null;
    headForm.clearErrors();
}

function saveHead() {
    if (editingHeadId.value) {
        headForm.patch(`${base}/item-heads/${editingHeadId.value}/windows`, {
            preserveScroll: true,
            onSuccess: closeHeadModal,
        });
        return;
    }

    headForm.post(`${base}/item-heads`, {
        preserveScroll: true,
        onSuccess: closeHeadModal,
    });
}

function resetAddForm(headId = '') {
    addForm.title = '';
    addForm.gender = 'open';
    addForm.class_group = '';
    addForm.age_group = '';
    addForm.kids_band = '';
    addForm.venue_type = '';
    addForm.sport_discipline = '';
    addForm.competition_format = '';
    addForm.participant_type = 'individual';
    addForm.head_id = headId ? String(headId) : (headFilter.value && headFilter.value !== 'other' ? headFilter.value : '');
    addForm.qualify_count = null;
    addForm.max_per_school = null;
    addForm.fee_amount = null;
}

function openAddItem(headId = '') {
    addForm.clearErrors();
    resetAddForm(headId);
    addingItem.value = true;
}

function closeAddItem() {
    addingItem.value = false;
    addForm.clearErrors();
}

function saveNewItem() {
    addForm.post(`${base}/items`, {
        preserveScroll: true,
        onSuccess: closeAddItem,
    });
}

function openEdit(item, focusMove = false) {
    if (!canEdit(item)) return;
    editingItem.value = item;
    moveFocus.value = focusMove;
    editForm.clearErrors();
    editForm.defaults(payloadFor(item));
    editForm.reset();
}

function closeEdit() {
    editingItem.value = null;
    moveFocus.value = false;
}

function saveEdit() {
    editForm.put(`${base}/items/${editingItem.value.id}`, {
        preserveScroll: true,
        onSuccess: closeEdit,
    });
}
</script>
