<template>
    <SahodayaEventsLayout :title="event.title" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Items`" eyebrow="Event items setup"
                    description="Enable items, add custom entries, import from master catalog.">
            <template #actions>
                <Link :href="`${base}/items/list`" class="btn-secondary text-xs">Item listing</Link>
                <Link :href="catalogUrl" class="btn-secondary text-xs">Assign from catalog</Link>
            </template>
        </PageHeader>

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="items" />

        <div class="space-y-5">
                <div class="form-section">
                    <div class="form-section-head">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="form-section-title">Event items</h3>
                                <p class="form-section-hint">
                                    Toggle items on/off for this event. Year-independent defaults live in
                                    <Link :href="catalogUrl" class="link-brand">Items & fees catalog →</Link>
                                </p>
                            </div>
                            <button v-if="catalogSummary?.enabled" type="button" @click="importCatalog" class="btn-secondary text-xs">
                                Import enabled ({{ catalogSummary.enabled }})
                            </button>
                        </div>
                    </div>
                    <div class="form-section-body">
                        <form @submit.prevent="addItem" class="grid sm:grid-cols-2 gap-3 mb-5">
                            <FormField label="Item name" class-extra="sm:col-span-2">
                                <input v-model="itemForm.title" class="field" placeholder="Item name" required>
                            </FormField>
                            <FormField v-if="isArts" label="Stage type">
                                <select v-model="itemForm.stage_type" class="field">
                                    <option value="">Stage type</option>
                                    <option value="on_stage">On Stage</option>
                                    <option value="off_stage">Off Stage</option>
                                </select>
                            </FormField>
                            <template v-if="isSports">
                                <FormField label="Venue">
                                    <select v-model="itemForm.venue_type" class="field">
                                        <option value="">Venue</option>
                                        <option value="outdoor">Outdoor</option>
                                        <option value="indoor">Indoor</option>
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
                            <FormField v-if="isSports" label="Age group">
                                <select v-model="itemForm.age_group" class="field">
                                    <option value="">Age group</option>
                                    <option v-for="(label, key) in taxonomy.age_group" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </FormField>
                            <FormField v-else-if="event.event_type === 'kids_fest'" label="Kids Fest band">
                                <select v-model="itemForm.kids_band" class="field">
                                    <option value="">Kids Fest band</option>
                                    <option v-for="(label, key) in taxonomy.kids_band" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </FormField>
                            <FormField v-else label="Class category">
                                <select v-model="itemForm.class_group" class="field">
                                    <option value="">Class category</option>
                                    <option v-for="(label, key) in taxonomy.class_group" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </FormField>
                            <FormField label="Gender">
                                <select v-model="itemForm.gender" class="field">
                                    <option value="open">Open</option>
                                    <option v-for="(label, key) in taxonomy.gender" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </FormField>
                            <FormField label="Participant type">
                                <select v-model="itemForm.participant_type" class="field">
                                    <option value="individual">Individual</option>
                                    <option value="group">Group</option>
                                    <option value="team">Team</option>
                                </select>
                            </FormField>
                            <FormField label="Fee override (₹)" class-extra="sm:col-span-2" hint="Optional per-item fee">
                                <input v-model.number="itemForm.fee_amount" type="number" min="0" class="field" placeholder="Leave blank for default">
                            </FormField>
                            <template v-if="['team', 'group'].includes(itemForm.participant_type)">
                                <p class="sm:col-span-2 form-label">Squad / roster rules</p>
                                <FormField label="Min on field"><input v-model.number="itemForm.min_playing" type="number" min="1" class="field"></FormField>
                                <FormField label="Max substitutes"><input v-model.number="itemForm.max_subs" type="number" min="0" class="field"></FormField>
                                <FormField label="Max squad"><input v-model.number="itemForm.max_squad" type="number" min="1" class="field"></FormField>
                                <FormField label="Min to register"><input v-model.number="itemForm.min_squad" type="number" min="1" class="field"></FormField>
                                <FormField label="Standbys" class-extra="sm:col-span-2" hint="No fee or certificate"><input v-model.number="itemForm.standbys" type="number" min="0" class="field"></FormField>
                            </template>
                            <div class="sm:col-span-2">
                                <button type="submit" class="btn-primary w-full sm:w-auto">Add Sahodaya item</button>
                            </div>
                        </form>

                        <div v-if="flatItemCount" class="sticky top-0 z-10 -mx-1 px-1 py-2 mb-3 bg-white/95 backdrop-blur border-b border-slate-100">
                            <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 px-3 py-2.5 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <input v-model="searchQuery" type="search"
                                           class="field field--sm flex-1 min-w-[8rem] max-w-sm !py-1.5 !text-sm"
                                           placeholder="Search items…" autocomplete="off">
                                    <span class="text-xs text-slate-500 whitespace-nowrap tabular-nums">
                                        <template v-if="hasActiveFilters">{{ filteredItemCount }} / {{ flatItemCount }}</template>
                                        <template v-else>{{ flatItemCount }} items</template>
                                    </span>
                                    <button v-if="hasActiveFilters" type="button"
                                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 shrink-0"
                                            @click="clearFilters">
                                        Clear
                                    </button>
                                </div>

                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                    <div v-if="isSports && ageGroupOptions.length" class="flex flex-wrap items-center gap-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400 shrink-0">Age</span>
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
                                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                                :class="filterGender === 'male'
                                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                                @click="filterGender = 'male'">Boys</button>
                                        <button type="button"
                                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                                :class="filterGender === 'female'
                                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                                @click="filterGender = 'female'">Girls</button>
                                        <button type="button"
                                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                                :class="filterGender === 'open'
                                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                                @click="filterGender = 'open'">Open</button>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-1">
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
                                                @click="filterEnabled = 'on'">On</button>
                                        <button type="button"
                                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                                :class="filterEnabled === 'off'
                                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                                @click="filterEnabled = 'off'">Off</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-for="(levelItems, level) in filteredItemsByLevel" :key="level" class="mb-4">
                            <p v-if="levelItems.length" class="text-xs font-bold uppercase tracking-wide text-slate-500 bg-slate-50 px-3 py-1.5 rounded-lg mb-2">
                                {{ ownerLevelLabels[level] ?? level }}
                            </p>
                            <ul v-if="levelItems.length" class="divide-y divide-slate-100 rounded-xl border border-slate-200/80 overflow-hidden">
                                <li v-for="item in levelItems" :key="item.id"
                                    class="flex flex-wrap items-start justify-between gap-3 bg-white px-4 py-3 text-sm"
                                    :class="item.is_enabled === false ? 'opacity-60' : ''">
                                    <div class="flex gap-3 min-w-0 flex-1">
                                        <label v-if="item.owner_level !== 'state'" class="flex items-start gap-1.5 shrink-0 pt-0.5">
                                            <input type="checkbox" :checked="item.is_enabled !== false"
                                                   @change="toggleItemEnabled(item, $event.target.checked)">
                                            <span class="text-xs text-slate-500">On</span>
                                        </label>
                                        <span class="min-w-0 flex items-start gap-2">
                                            <FestItemMetaIcons :gender="item.gender" :participant-type="item.participant_type" class="mt-0.5 shrink-0" />
                                            <span>
                                            <span :class="item.is_enabled === false ? 'text-slate-400 line-through' : ''">
                                                <span v-if="item.item_code" class="font-mono text-xs text-slate-400 mr-1">{{ item.item_code }}</span>
                                                {{ item.title }}
                                            </span>
                                            <span class="text-slate-400 text-xs block mt-0.5">
                                                {{ itemTags(item) }}
                                            </span>
                                            <details v-if="itemDetails(item).length" class="mt-2 group/details">
                                                <summary class="text-[11px] font-semibold text-indigo-600 cursor-pointer select-none list-none flex items-center gap-1">
                                                    <span class="group-open/details:hidden">Show details</span>
                                                    <span class="hidden group-open/details:inline">Hide details</span>
                                                </summary>
                                                <dl class="mt-1.5 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-3 gap-y-1 text-[11px] text-slate-500">
                                                    <div v-for="field in itemDetails(item)" :key="field.label" class="min-w-0">
                                                        <dt class="text-slate-400 truncate">{{ field.label }}</dt>
                                                        <dd class="font-medium text-slate-700 truncate" :title="field.value">{{ field.value }}</dd>
                                                    </div>
                                                </dl>
                                            </details>
                                            </span>
                                        </span>
                                    </div>
                                    <div v-if="item.owner_level !== 'state'" class="flex gap-1 shrink-0">
                                        <button type="button" @click="openEditItem(item)" class="btn-ghost text-xs text-indigo-600">Edit</button>
                                        <button type="button" @click="removeItem(item.id)" class="btn-ghost text-xs text-red-600">Remove</button>
                                    </div>
                                    <span v-else class="text-xs text-amber-600 shrink-0">State</span>
                                </li>
                            </ul>
                        </div>

                        <EmptyState v-if="!flatItemCount" title="No items yet" :description="`Enable items in the master catalog, then import them into this event.`" icon="📋" />
                        <EmptyState v-else-if="hasActiveFilters && !filteredItemCount" title="No matches"
                                    description="No items match your filters. Try clearing or adjusting them." icon="🔍" class="py-8" />
                    </div>
                </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />

        <div v-if="editingItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="editingItem = null">
            <form @submit.prevent="saveEditItem" class="card w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-xl space-y-4">
                <h3 class="section-title">Edit item</h3>
                <FormGrid>
                    <FormField label="Item name" class-extra="sm:col-span-2">
                        <input v-model="editForm.title" class="field" required>
                    </FormField>
                    <FormField label="Enabled for this event">
                        <CheckboxField v-model="editForm.is_enabled" label="Schools can register for this item" />
                    </FormField>
                    <template v-if="isSports">
                        <FormField label="Age group">
                            <select v-model="editForm.age_group" class="field">
                                <option value="">Age group</option>
                                <option v-for="(label, key) in taxonomy.age_group" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Gender">
                            <select v-model="editForm.gender" class="field">
                                <option value="open">Open</option>
                                <option v-for="(label, key) in taxonomy.gender" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Venue">
                            <select v-model="editForm.venue_type" class="field">
                                <option value="">Venue</option>
                                <option value="outdoor">Outdoor</option>
                                <option value="indoor">Indoor</option>
                            </select>
                        </FormField>
                        <FormField label="Discipline">
                            <select v-model="editForm.sport_discipline" class="field">
                                <option value="">Discipline</option>
                                <option v-for="(label, key) in taxonomy.sport_discipline" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                    </template>
                    <FormField v-else-if="event.event_type === 'kids_fest'" label="Kids Fest band">
                        <select v-model="editForm.kids_band" class="field">
                            <option value="">Kids Fest band</option>
                            <option v-for="(label, key) in taxonomy.kids_band" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField v-else label="Class category">
                        <select v-model="editForm.class_group" class="field">
                            <option value="">Class category</option>
                            <option v-for="(label, key) in taxonomy.class_group" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField label="Participant type">
                        <select v-model="editForm.participant_type" class="field">
                            <option value="individual">Individual</option>
                            <option value="group">Group</option>
                            <option value="team">Team</option>
                        </select>
                    </FormField>
                    <template v-if="['team', 'group'].includes(editForm.participant_type)">
                        <p class="sm:col-span-2 form-label">Squad / roster rules</p>
                        <FormField label="Min on field">
                            <input v-model.number="editForm.min_playing" type="number" min="1" class="field">
                        </FormField>
                        <FormField label="Max substitutes">
                            <input v-model.number="editForm.max_subs" type="number" min="0" class="field">
                        </FormField>
                        <FormField label="Max squad">
                            <input v-model.number="editForm.max_squad" type="number" min="1" class="field">
                        </FormField>
                        <FormField label="Min to register">
                            <input v-model.number="editForm.min_squad" type="number" min="1" class="field">
                        </FormField>
                        <FormField label="Standbys" class-extra="sm:col-span-2" hint="No fee or certificate">
                            <input v-model.number="editForm.standbys" type="number" min="0" class="field">
                        </FormField>
                        <p class="sm:col-span-2 text-xs text-slate-500">For simple group items (e.g. group dance), use member count instead:</p>
                        <FormField label="Min members">
                            <input v-model.number="editForm.min_group_size" type="number" min="1" class="field">
                        </FormField>
                        <FormField label="Max members">
                            <input v-model.number="editForm.max_group_size" type="number" min="1" class="field">
                        </FormField>
                    </template>
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
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="editingItem = null" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="editForm.processing">Save changes</button>
                </div>
            </form>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import FestItemMetaIcons from '@/Components/sahodaya/FestItemMetaIcons.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import { festItemListingDetails, festItemSearchHaystack, festItemTagsLine } from '@/support/festItemListingMeta.js';

const SPORTS_AGE_ORDER = ['u8', 'u10', 'u11', 'u12', 'u14', 'u17', 'u19', 'open'];

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, groupedItems: Object, taxonomy: Object,
    catalogSummary: Object, catalogUrl: String,
    levelLabels: Object, itemsByLevel: Object, ownerLevelLabels: Object,
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const isArts = computed(() => ['kalolsavam', 'kids_fest'].includes(props.event.event_type));
const isSports = computed(() => props.event.event_type === 'sports');
const searchQuery = ref('');
const filterAgeGroup = ref('');
const filterGender = ref('');
const filterEnabled = ref('');

const flatItemCount = computed(() => Object.values(props.itemsByLevel ?? {}).flat().length);

const ageGroupOptions = computed(() => {
    if (!isSports.value) return [];
    const keys = new Set();
    for (const items of Object.values(props.itemsByLevel ?? {})) {
        for (const item of items) {
            if (item.age_group) keys.add(item.age_group);
        }
    }
    return [...keys]
        .sort((a, b) => {
            const ai = SPORTS_AGE_ORDER.indexOf(String(a).toLowerCase());
            const bi = SPORTS_AGE_ORDER.indexOf(String(b).toLowerCase());
            return (ai < 0 ? 99 : ai) - (bi < 0 ? 99 : bi);
        })
        .map((key) => ({
            key,
            label: props.taxonomy?.age_group?.[key] ?? String(key).toUpperCase(),
        }));
});

const hasActiveFilters = computed(() =>
    Boolean(searchQuery.value.trim() || filterAgeGroup.value || filterGender.value || filterEnabled.value)
);

function itemMetaOptions() {
    return { taxonomy: props.taxonomy, eventType: props.event.event_type };
}

function itemDetails(item) {
    return festItemListingDetails(item, itemMetaOptions());
}

function itemTags(item) {
    const line = festItemTagsLine(item, itemMetaOptions());
    if (item.is_enabled === false) {
        return `${line} · Disabled for this event`;
    }
    return line;
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
    if (filterGender.value && (item.gender ?? 'open') !== filterGender.value) return false;
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

const itemForm = useForm({
    title: '', participant_type: 'individual', stage_type: '', venue_type: '',
    competition_format: '', sport_discipline: '', class_group: '', age_group: '', kids_band: '', gender: 'open',
    min_playing: null, max_subs: null, max_squad: null, min_squad: null, standbys: null,
    fee_amount: null,
});
const editingItem = ref(null);
const editForm = useForm({
    title: '', is_enabled: true, gender: 'open', class_group: '', age_group: '', kids_band: '',
    venue_type: '', sport_discipline: '', participant_type: 'individual',
    qualify_count: null, max_per_school: null, fee_amount: null,
    min_playing: null, max_subs: null, max_squad: null, min_squad: null, standbys: null,
    min_group_size: null, max_group_size: null,
});

function addItem() {
    itemForm.post(`${base}/items`, {
        preserveScroll: true,         onSuccess: () => itemForm.reset({
            gender: 'open', participant_type: 'individual', fee_amount: null,
            min_playing: null, max_subs: null, max_squad: null, min_squad: null, standbys: null,
        }),
    });
}
function importCatalog() {
    if (!confirm(`Import ${props.catalogSummary?.enabled ?? 0} enabled item(s) from your Sahodaya catalog?`)) return;
    router.post(`${base}/items/import-catalog`, {}, { preserveScroll: true });
}
function removeItem(id) {
    router.delete(`${base}/items/${id}`, { preserveScroll: true });
}
function openEditItem(item) {
    const c = item.criteria_json ?? {};
    editingItem.value = item;
    editForm.clearErrors();
    editForm.title = item.title;
    editForm.is_enabled = item.is_enabled !== false;
    editForm.gender = item.gender ?? 'open';
    editForm.class_group = item.class_group ?? '';
    editForm.age_group = item.age_group ?? '';
    editForm.kids_band = item.kids_band ?? '';
    editForm.venue_type = item.venue_type ?? '';
    editForm.sport_discipline = item.sport_discipline ?? '';
    editForm.participant_type = item.participant_type ?? 'individual';
    editForm.qualify_count = item.qualify_count ?? null;
    editForm.max_per_school = item.max_per_school ?? null;
    editForm.fee_amount = item.fee_amount ?? null;
    editForm.min_playing = c.min_playing ?? null;
    editForm.max_subs = c.max_subs ?? null;
    editForm.max_squad = c.max_squad ?? item.max_group_size ?? null;
    editForm.min_squad = c.min_squad ?? item.min_group_size ?? null;
    editForm.standbys = c.standbys ?? null;
    editForm.min_group_size = item.min_group_size ?? null;
    editForm.max_group_size = item.max_group_size ?? null;
}
function saveEditItem() {
    editForm.put(`${base}/items/${editingItem.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editingItem.value = null; },
    });
}
function toggleItemEnabled(item, enabled) {
    router.put(`${base}/items/${item.id}`, {
        title: item.title,
        is_enabled: enabled,
        gender: item.gender,
        class_group: item.class_group,
        age_group: item.age_group,
        kids_band: item.kids_band,
        qualify_count: item.qualify_count,
        max_per_school: item.max_per_school,
        fee_amount: item.fee_amount,
    }, { preserveScroll: true, preserveState: true });
}
</script>
