<template>
    <SahodayaEventsLayout :title="event.title" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        
        <!-- Header with Add Item Modal Trigger -->
        <PageHeader :title="`${event.title} — Event Items`" eyebrow="Items Management"
                    description="Search, filter, edit configurations, toggle availability, and add event items.">
            <template #actions>
                <button type="button" class="btn-primary text-xs flex items-center gap-1.5 shadow-sm" @click="showAddModal = true">
                    <span>+ Add event item</span>
                </button>
            </template>
        </PageHeader>

        <SportsSetupSubNav v-if="isSports" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="items" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="items" />

        <div class="space-y-5">
            <!-- Search & Filter Bar + Items Registry -->
            <div class="card !p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[14rem]">
                        <input v-model="searchQuery" type="search"
                               class="field flex-1 min-w-[10rem] max-w-sm"
                               placeholder="Search items by name, code, discipline…" autocomplete="off">
                        <span class="text-xs text-slate-500 whitespace-nowrap font-medium tabular-nums">
                            <template v-if="hasActiveFilters">{{ filteredItemCount }} / {{ flatItemCount }} items</template>
                            <template v-else>{{ flatItemCount }} items total</template>
                        </span>
                        <button v-if="hasActiveFilters" type="button"
                                class="text-xs font-bold text-indigo-600 hover:text-indigo-800 shrink-0"
                                @click="clearFilters">
                            Clear filters
                        </button>
                    </div>

                    <button type="button" class="btn-primary text-xs" @click="showAddModal = true">
                        + Add item
                    </button>
                </div>

                <!-- Filter Chips -->
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs pt-1">
                    <div v-if="isSports && ageGroupOptions.length" class="flex flex-wrap items-center gap-1">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400 shrink-0">Age Group</span>
                        <button type="button"
                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                :class="!filterAgeGroup
                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                @click="filterAgeGroup = ''">All</button>
                        <button v-for="opt in ageGroupOptions" :key="opt.key" type="button"
                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                :class="filterAgeGroup === opt.key
                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                @click="filterAgeGroup = opt.key">{{ opt.label }}</button>
                    </div>

                    <div class="flex flex-wrap items-center gap-1">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400 shrink-0">Gender</span>
                        <button type="button"
                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                :class="!filterGender
                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                @click="filterGender = ''">All</button>
                        <button type="button"
                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap inline-flex items-center gap-1"
                                :class="filterGender === 'male'
                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                @click="filterGender = 'male'">
                            <FestItemMetaIcons gender="male" bare class="shrink-0" />
                            Boys
                        </button>
                        <button type="button"
                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap inline-flex items-center gap-1"
                                :class="filterGender === 'female'
                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                @click="filterGender = 'female'">
                            <FestItemMetaIcons gender="female" bare class="shrink-0" />
                            Girls
                        </button>
                        <button type="button"
                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                :class="filterGender === 'open'
                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                @click="filterGender = 'open'">Open</button>
                    </div>

                    <div class="flex flex-wrap items-center gap-1 ml-auto">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400 shrink-0">Status</span>
                        <button type="button"
                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                :class="!filterEnabled
                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                @click="filterEnabled = ''">All</button>
                        <button type="button"
                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                :class="filterEnabled === 'on'
                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                @click="filterEnabled = 'on'">Enabled</button>
                        <button type="button"
                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                :class="filterEnabled === 'off'
                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                @click="filterEnabled = 'off'">Disabled</button>
                    </div>
                </div>

                <!-- Item Listing Table -->
                <EmptyState v-if="!flatItemCount" title="No event items" description="Click '+ Add event item' to create items for this event." icon="📋" class="py-12" />
                <EmptyState v-else-if="!filteredItemCount" title="No matching items" description="No items match your selected filters. Try clearing search filters." icon="🔍" class="py-12" />

                <div v-else class="space-y-4 pt-2">
                    <div v-for="(levelItems, level) in filteredItemsByLevel" :key="level" class="space-y-2">
                        <p v-if="levelItems.length" class="text-xs font-bold uppercase tracking-wider text-slate-500 bg-slate-100 px-3 py-1.5 rounded-lg">
                            {{ ownerLevelLabels[level] ?? level }}
                        </p>
                        <ul v-if="levelItems.length" class="divide-y divide-slate-100 rounded-xl border border-slate-200 overflow-hidden">
                            <li v-for="item in levelItems" :key="item.id"
                                class="flex flex-wrap items-center justify-between gap-3 bg-white p-3.5 hover:bg-slate-50/70 transition text-sm"
                                :class="item.is_enabled === false ? 'opacity-60 bg-slate-50/50' : ''">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <button type="button"
                                            class="text-xs font-bold px-2.5 py-1 rounded-full transition shrink-0"
                                            :class="item.is_enabled !== false ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-200 text-slate-600 hover:bg-slate-300'"
                                            @click="toggleItemEnabled(item)">
                                        {{ item.is_enabled !== false ? 'Enabled' : 'Disabled' }}
                                    </button>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="font-bold text-slate-900 leading-snug truncate">{{ item.title }}</p>
                                            <FestItemMetaIcons :gender="item.gender" :participant-type="item.participant_type" />
                                        </div>
                                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                            <span v-for="(detail, dIdx) in itemDetails(item)" :key="dIdx"
                                                  class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 border border-slate-200/60">
                                                <span class="text-slate-400 font-normal text-[10px] uppercase tracking-wide">{{ detail.label }}:</span>
                                                <span class="font-semibold text-slate-800">{{ detail.value }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 text-xs shrink-0">
                                    <span class="font-semibold text-slate-700 bg-slate-100 px-2.5 py-1 rounded-full border border-slate-200">
                                        {{ item.registrations_count ?? 0 }} registered
                                    </span>
                                    <button type="button" class="btn-secondary text-xs" @click="startEditItem(item)">
                                        Edit →
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- ADD ITEM MODAL DIALOG -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto" @click.self="showAddModal = false">
            <div class="card w-full max-w-2xl shadow-2xl my-auto space-y-4 bg-white border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="section-title !mb-0">Add Event Item</h3>
                        <p class="section-desc mt-0.5">Fill in item details and configure rules.</p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-600 text-xl font-bold leading-none" @click="showAddModal = false">×</button>
                </div>

                <form @submit.prevent="addItem" class="grid sm:grid-cols-2 gap-3.5">
                    <FormField label="Item Name" class-extra="sm:col-span-2" required>
                        <input v-model="itemForm.title" class="field font-medium" placeholder="Item name (e.g. 100m Dash Boys U-14)" required>
                    </FormField>

                    <FormField label="Item Code" hint="Short code (e.g. ATH-101)">
                        <input v-model="itemForm.item_code" class="field font-mono" placeholder="e.g. ATH-101">
                    </FormField>

                    <FormField v-if="isArts" label="Stage Type">
                        <select v-model="itemForm.stage_type" class="field">
                            <option value="">Stage type</option>
                            <option value="on_stage">On Stage</option>
                            <option value="off_stage">Off Stage</option>
                        </select>
                    </FormField>

                    <FormField v-if="!isSports" label="Category">
                        <select v-model="itemForm.category" class="field">
                            <option value="">Category</option>
                            <option v-for="(label, key) in taxonomy.arts_category" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>

                    <template v-if="isSports">
                        <FormField label="Venue Type">
                            <select v-model="itemForm.venue_type" class="field">
                                <option value="">Venue type</option>
                                <option v-for="(label, key) in taxonomy.venue_type" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Format">
                            <select v-model="itemForm.competition_format" class="field">
                                <option value="">Format</option>
                                <option v-for="(label, key) in taxonomy.competition_format" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Discipline">
                            <select v-model="itemForm.sport_discipline" class="field">
                                <option value="">Discipline</option>
                                <option v-for="(label, key) in taxonomy.sport_discipline" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                    </template>

                    <FormField v-if="isSports" label="Age Group">
                        <select v-model="itemForm.age_group" class="field">
                            <option value="">Age group</option>
                            <option v-for="(label, key) in taxonomy.age_group" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField v-else-if="event.event_type === 'kids_fest'" label="Kids Fest Band">
                        <select v-model="itemForm.kids_band" class="field">
                            <option value="">Kids Fest band</option>
                            <option v-for="(label, key) in taxonomy.kids_band" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField v-else label="Class Category">
                        <select v-model="itemForm.class_group" class="field">
                            <option value="">Class category</option>
                            <option v-for="(label, key) in taxonomy.class_group" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>

                    <FormField label="Gender">
                        <select v-model="itemForm.gender" class="field font-medium">
                            <option value="open">Open</option>
                            <option v-for="(label, key) in taxonomy.gender" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>

                    <FormField label="Participant Type">
                        <select v-model="itemForm.participant_type" class="field font-medium">
                            <option v-for="(label, key) in taxonomy.participant_type" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>

                    <FormField label="Max per School" hint="Max entries allowed per school">
                        <input v-model.number="itemForm.max_per_school" type="number" min="1" class="field" placeholder="e.g. 2">
                    </FormField>

                    <FormField label="Qualifiers Count" hint="Qualifiers promoting to next round">
                        <input v-model.number="itemForm.qualify_count" type="number" min="1" class="field" placeholder="e.g. 2">
                    </FormField>

                    <FormField label="Est. Duration (Mins)" hint="For scheduling">
                        <input v-model.number="itemForm.duration_minutes" type="number" min="1" class="field" placeholder="e.g. 30">
                    </FormField>

                    <FormField label="Result Method">
                        <select v-model="itemForm.result_method" class="field">
                            <option value="">Default</option>
                            <option v-for="(label, key) in (taxonomy.result_method || {})" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>

                    <FormField v-if="!isSports && competitionAreas.length" label="Competition Area">
                        <select v-model="itemForm.area_id" class="field">
                            <option value="">None</option>
                            <option v-for="a in competitionAreas" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </FormField>

                    <FormField label="Tie-Break On Promote" hint="When ranks tie at the qualifier cutoff">
                        <select v-model="itemForm.tiebreak_mode" class="field">
                            <option v-for="(label, key) in tiebreakModes" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>

                    <FormField label="Fee Override (₹)" class-extra="sm:col-span-2" hint="Optional per-item fee override">
                        <input v-model.number="itemForm.fee_amount" type="number" min="0" class="field" placeholder="Leave blank for default">
                    </FormField>

                    <FormField v-if="isSports" label="Free Quota" class-extra="sm:col-span-2">
                        <CheckboxField v-model="itemForm.quota_eligible"
                                       label="Waivable by the head's free quota (Sports composite billing)" />
                    </FormField>

                    <template v-if="['team', 'group', 'pair', 'trio'].includes(itemForm.participant_type)">
                        <div class="sm:col-span-2 border-t border-slate-200 pt-3">
                            <p class="form-label font-bold text-slate-800 mb-2">Squad &amp; Roster Rules</p>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <FormField label="Min on field"><input v-model.number="itemForm.min_playing" type="number" min="1" class="field"></FormField>
                                <FormField label="Max substitutes"><input v-model.number="itemForm.max_subs" type="number" min="0" class="field"></FormField>
                                <FormField label="Max squad"><input v-model.number="itemForm.max_squad" type="number" min="1" class="field"></FormField>
                                <FormField label="Min squad"><input v-model.number="itemForm.min_squad" type="number" min="1" class="field"></FormField>
                            </div>
                        </div>
                    </template>

                    <div class="sm:col-span-2 flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" class="btn-secondary text-sm" @click="showAddModal = false">Cancel</button>
                        <button type="submit" class="btn-primary text-sm flex items-center gap-1.5" :disabled="itemForm.processing">
                            <span>{{ itemForm.processing ? 'Creating...' : '+ Create Event Item' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- EDIT ITEM MODAL DIALOG -->
        <div v-if="editingItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="editingItem = null">
            <form @submit.prevent="saveEditItem" class="card w-full max-w-2xl max-h-[90vh] flex flex-col shadow-2xl bg-white border border-slate-200 !p-0 overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5 bg-slate-50 shrink-0">
                    <h3 class="section-title !mb-0 text-slate-900 font-bold">Edit Item: {{ editingItem.title }}</h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600 text-2xl font-bold leading-none" @click="editingItem = null">×</button>
                </div>

                <div class="p-5 overflow-y-auto space-y-4 flex-1">
                    <FormGrid>
                        <FormField label="Title" class-extra="sm:col-span-2">
                            <input v-model="editForm.title" class="field" required>
                        </FormField>

                        <FormField label="Item Code">
                            <input v-model="editForm.item_code" class="field font-mono" placeholder="e.g. ATH-101">
                        </FormField>

                        <FormField v-if="isSports" label="Age Group">
                            <select v-model="editForm.age_group" class="field">
                                <option value="">None / Open</option>
                                <option v-for="(label, key) in taxonomy.age_group" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>

                        <FormField v-else-if="event.event_type === 'kids_fest'" label="Kids Fest Band">
                            <select v-model="editForm.kids_band" class="field">
                                <option value="">None / Open</option>
                                <option v-for="(label, key) in (taxonomy.kids_band || {})" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>

                        <FormField v-else label="Class Category / Group">
                            <select v-model="editForm.class_group" class="field">
                                <option value="">Open / All Classes</option>
                                <option v-for="(label, key) in (taxonomy.class_group || {})" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>

                        <FormField label="Gender">
                            <select v-model="editForm.gender" class="field">
                                <option value="open">Open / Mixed</option>
                                <option v-for="(label, key) in taxonomy.gender" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>

                        <FormField label="Participant Type">
                            <select v-model="editForm.participant_type" class="field">
                                <option v-for="(label, key) in taxonomy.participant_type" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>

                        <FormField label="Max per School">
                            <input v-model.number="editForm.max_per_school" type="number" min="1" class="field" placeholder="e.g. 2">
                        </FormField>

                        <template v-if="['group', 'team'].includes(editForm.participant_type)">
                            <FormField label="Min Team Size">
                                <input v-model.number="editForm.min_group_size" type="number" min="1" class="field" placeholder="e.g. 2">
                            </FormField>

                            <FormField label="Max Team Size">
                                <input v-model.number="editForm.max_group_size" type="number" min="1" class="field" placeholder="e.g. 10">
                            </FormField>

                            <FormField label="Max Substitutes (Standbys)">
                                <input v-model.number="editForm.standbys" type="number" min="0" class="field" placeholder="e.g. 2">
                            </FormField>
                        </template>

                        <FormField label="Qualifiers Count">
                            <input v-model.number="editForm.qualify_count" type="number" min="1" class="field" placeholder="e.g. 2">
                        </FormField>

                        <FormField label="Stage / Category Type">
                            <select v-model="editForm.stage_type" class="field">
                                <option value="">Default / None</option>
                                <option v-for="(label, key) in (taxonomy.stage_type || {})" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>

                        <FormField label="Est. Duration (Mins)">
                            <input v-model.number="editForm.duration_minutes" type="number" min="1" class="field" placeholder="e.g. 30">
                        </FormField>

                        <FormField label="Result Method">
                            <select v-model="editForm.result_method" class="field">
                                <option value="">Default</option>
                                <option v-for="(label, key) in (taxonomy.result_method || {})" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>

                        <FormField label="Fee Override (₹)" class-extra="sm:col-span-2">
                            <input v-model.number="editForm.fee_amount" type="number" min="0" class="field" placeholder="Default fee">
                        </FormField>
                    </FormGrid>
                </div>

                <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-slate-200 bg-slate-50 shrink-0">
                    <button type="button" @click="editingItem = null" class="btn-secondary text-sm">Cancel</button>
                    <button type="submit" class="btn-primary text-sm" :disabled="editForm.processing">Save Changes</button>
                </div>
            </form>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import FestItemMetaIcons from '@/Components/sahodaya/FestItemMetaIcons.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormGrid from '@/Components/ui/FormGrid.vue';
import FormField from '@/Components/ui/FormField.vue';
import CheckboxField from '@/Components/ui/CheckboxField.vue';
import { festItemListingDetails, festItemSearchHaystack, festItemTagsLine } from '@/support/festItemListingMeta.js';
import { normalizeFestItemGender } from '@/support/festItemEligibility.js';

const SPORTS_AGE_ORDER = ['u8', 'u10', 'u11', 'u12', 'u14', 'u17', 'u19', 'open'];

const tiebreakModes = {
    none: 'Top N by position (default)',
    include_all_ties: 'Include all tied at cutoff',
    exclude_ties: 'Skip contested ranks that overflow',
    lot_draw: 'Lot draw among ties at cutoff',
    manual: 'Block promote until resolved manually',
};

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, groupedItems: Object, taxonomy: Object,
    competitionAreas: { type: Array, default: () => [] },
    taxonomyMastersUrl: String,
    catalogSummary: Object, catalogUrl: String,
    levelLabels: Object, itemsByLevel: Object, ownerLevelLabels: Object,
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const isArts = computed(() => ['kalolsavam', 'kids_fest'].includes(props.event.event_type));
const isSports = computed(() => props.event.event_type === 'sports');

const showAddModal = ref(false);
const searchQuery = ref('');
const filterAgeGroup = ref('');
const filterGender = ref('');
const filterEnabled = ref('');

const flatItemCount = computed(() => Object.values(props.itemsByLevel ?? {}).flat().length);

const ageGroupOptions = computed(() => {
    if (!isSports.value) return [];
    const set = new Set();
    for (const item of Object.values(props.itemsByLevel ?? {}).flat()) {
        if (item.age_group) set.add(item.age_group);
    }
    const map = props.taxonomy?.age_group ?? {};
    const keys = Array.from(set).sort((a, b) => {
        const ia = SPORTS_AGE_ORDER.indexOf(a.toLowerCase());
        const ib = SPORTS_AGE_ORDER.indexOf(b.toLowerCase());
        if (ia !== -1 && ib !== -1) return ia - ib;
        if (ia !== -1) return -1;
        if (ib !== -1) return 1;
        return a.localeCompare(b);
    });
    return keys.map((k) => ({ key: k, label: map[k] ?? k }));
});

const hasActiveFilters = computed(() => !!(searchQuery.value.trim() || filterAgeGroup.value || filterGender.value || filterEnabled.value));

function itemMetaOptions() {
    return { taxonomy: props.taxonomy, eventType: props.event.event_type };
}

function itemDetails(item) {
    return festItemListingDetails(item, itemMetaOptions());
}

function itemMatchesSearch(item, q) {
    const haystack = festItemSearchHaystack(item, itemMetaOptions());
    const terms = q.split(/\s+/).filter(Boolean);
    return terms.every((term) => haystack.includes(term));
}

function itemMatchesFilters(item) {
    const q = searchQuery.value.trim().toLowerCase();
    if (q && !itemMatchesSearch(item, q)) return false;
    if (filterAgeGroup.value && item.age_group !== filterAgeGroup.value) return false;
    if (filterGender.value && normalizeFestItemGender(item.gender) !== filterGender.value) return false;
    if (filterEnabled.value === 'on' && item.is_enabled === false) return false;
    if (filterEnabled.value === 'off' && item.is_enabled !== false) return false;
    return true;
}

const filteredItemsByLevel = computed(() => {
    const source = props.itemsByLevel ?? {};
    if (!hasActiveFilters.value) return source;
    const out = {};
    for (const [level, items] of Object.entries(source)) {
        const filtered = items.filter(itemMatchesFilters);
        if (filtered.length) out[level] = filtered;
    }
    return out;
});

const filteredItemCount = computed(() => Object.values(filteredItemsByLevel.value).flat().length);

function clearFilters() {
    searchQuery.value = '';
    filterAgeGroup.value = '';
    filterGender.value = '';
    filterEnabled.value = '';
}

function toggleItemEnabled(item) {
    router.patch(`${base}/items/${item.id}/windows`, {
        is_enabled: item.is_enabled === false,
    }, { preserveScroll: true });
}

const itemForm = useForm({
    title: '', item_code: '', participant_type: 'individual', result_method: '', stage_type: '', venue_type: '',
    competition_format: '', sport_discipline: '', class_group: '', age_group: '', kids_band: '', gender: 'open',
    category: '', area_id: '', tiebreak_mode: 'none',
    max_per_school: null, qualify_count: null, duration_minutes: null,
    min_playing: null, max_subs: null, max_squad: null, min_squad: null, standbys: null,
    fee_amount: null, quota_eligible: false,
});

function addItem() {
    itemForm.post(`${base}/items`, {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            itemForm.reset();
        },
    });
}

const editingItem = ref(null);
const editForm = useForm({
    title: '', item_code: '', is_enabled: true, gender: 'open', class_group: '', age_group: '', kids_band: '',
    stage_type: '', venue_type: '', sport_discipline: '', competition_format: '', participant_type: 'individual', result_method: '',
    category: '', area_id: '', tiebreak_mode: 'none',
    max_per_school: null, qualify_count: null, duration_minutes: null,
    min_group_size: null, max_group_size: null, standbys: null,
    fee_amount: null,
});

function startEditItem(item) {
    editingItem.value = item;
    editForm.title = item.title ?? '';
    editForm.item_code = item.item_code ?? '';
    editForm.gender = item.gender ?? 'open';
    editForm.class_group = item.class_group ?? '';
    editForm.age_group = item.age_group ?? '';
    editForm.kids_band = item.kids_band ?? '';
    editForm.stage_type = item.stage_type ?? '';
    editForm.area_id = item.area_id ?? '';
    editForm.participant_type = item.participant_type ?? 'individual';
    editForm.result_method = item.result_method ?? '';
    editForm.max_per_school = item.max_per_school ?? null;
    editForm.min_group_size = item.min_group_size ?? null;
    editForm.max_group_size = item.max_group_size ?? null;
    editForm.standbys = item.standbys ?? null;
    editForm.qualify_count = item.qualify_count ?? null;
    editForm.duration_minutes = item.duration_minutes ?? null;
    editForm.fee_amount = item.fee_amount ?? null;
}

function saveEditItem() {
    if (!editingItem.value) return;
    editForm.put(`${base}/items/${editingItem.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editingItem.value = null; },
    });
}
</script>
