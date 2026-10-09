<template>
    <SahodayaEventsLayout :title="`${event.title} — Trophy Distribution`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Trophy Distribution Template`" eyebrow="Prize Ceremony &amp; Valedictory"
                    description="Official trophy list, parent-event template synchronization, and live presentation winners." />
        <FestEventWorkflowStepper :sahodaya-id="sahodaya.id" :event-id="event.id"
                                  :event-type="event.event_type" :current-step="'operations'" />

        <!-- Parent-Child Event & Template Hierarchy Banner -->
        <div class="mt-4 p-4 rounded-xl border flex flex-wrap items-center justify-between gap-3 shadow-xs"
             :class="hierarchy.is_child ? 'bg-indigo-50/70 border-indigo-200' : 'bg-slate-50 border-slate-200'">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg"
                     :class="hierarchy.is_child ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-white'">
                    <span v-if="hierarchy.is_child">↳</span>
                    <span v-else>★</span>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">
                        <template v-if="hierarchy.is_child">
                            Child Event against Parent: <span class="text-indigo-700">{{ hierarchy.parent_title }}</span>
                        </template>
                        <template v-else-if="hierarchy.child_count > 0">
                            Parent Hub Event — Governing {{ hierarchy.child_count }} child events / phases
                        </template>
                        <template v-else>
                            Independent Event Trophy Distribution
                        </template>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <template v-if="hierarchy.is_child">
                            Inherit or customize the trophy distribution template against the parent event.
                        </template>
                        <template v-else-if="hierarchy.child_count > 0">
                            Configure standard trophies here and push down to all child events in one click.
                        </template>
                        <template v-else>
                            Manage the official list of trophies for the valedictory prize distribution ceremony.
                        </template>
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- If Child Event: Copy from Parent -->
                <button v-if="hierarchy.is_child" type="button" class="btn-primary text-xs flex items-center gap-1.5"
                        @click="copyFromParent">
                    <span>↓</span> Inherit from Parent Event
                </button>

                <!-- If Parent Hub: Push to Children -->
                <button v-if="!hierarchy.is_child && hierarchy.child_count > 0" type="button" class="btn-secondary text-xs flex items-center gap-1.5"
                        @click="pushToChildren">
                    <span>↑</span> Push to {{ hierarchy.child_count }} Child Events
                </button>

                <!-- Seed 75-Trophy Preset -->
                <button type="button" class="btn-secondary text-xs bg-amber-50 border-amber-200 text-amber-900 hover:bg-amber-100 flex items-center gap-1.5"
                        @click="seedPreset">
                    <span>🏆</span> Seed Metro Kalotsav Preset (75 Trophies)
                </button>

                <!-- Download Official PDF -->
                <a :href="pdfExportUrl" target="_blank" class="btn-secondary text-xs flex items-center gap-1.5">
                    <span>📄</span> Download Valedictory PDF
                </a>

                <!-- Add Custom Trophy -->
                <button type="button" class="btn-primary text-xs" @click="startNewTrophy">
                    + Add Trophy
                </button>
            </div>
        </div>

        <!-- Stats Overview Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4 mt-4">
            <div class="card card--muted !py-3 text-center">
                <p class="text-2xl font-black text-slate-800">{{ stats.total_trophies }}</p>
                <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wide">Total Trophies</p>
            </div>
            <div class="card card--muted !py-3 text-center">
                <p class="text-2xl font-black text-emerald-700">{{ stats.resolved_winners }} / {{ stats.total_trophies }}</p>
                <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wide">Recipients Ready</p>
            </div>
            <div class="card card--muted !py-3 text-center col-span-2 sm:col-span-1">
                <p class="text-2xl font-black text-amber-600">{{ stats.rolling_trophies }}</p>
                <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wide">Ever-rolling Trophies</p>
            </div>
        </div>

        <!-- Scope switch for phased hubs -->
        <div v-if="usesPhases" class="flex gap-2 mb-4">
            <button type="button" class="btn-secondary text-xs" :class="{ '!bg-indigo-600 !text-white': currentScope === 'phase' }" @click="switchScope('phase')">
                This phase
            </button>
            <button type="button" class="btn-secondary text-xs" :class="{ '!bg-indigo-600 !text-white': currentScope === 'cumulative' }" @click="switchScope('cumulative')">
                Cumulative — all phases
            </button>
        </div>

        <!-- Search & Filter Controls -->
        <div class="card mb-4 !py-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[260px]">
                    <input v-model="searchQuery" type="text" class="fld text-xs !w-72" placeholder="Search trophy #, title, or recipient school...">
                    <SearchableSelect v-model="filterType" class="w-48 text-xs" :options="typeOptions" :all-option="true" all-label="All Trophy Types" />
                    <SearchableSelect v-model="filterStatus" class="w-40 text-xs" :options="statusOptions" :all-option="true" all-label="All Statuses" />
                </div>
                <div class="text-xs text-slate-500 font-medium">
                    Showing {{ filteredRows.length }} of {{ trophyRows.length }} trophies
                </div>
            </div>
        </div>

        <!-- Trophies Presentation Table -->
        <div class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="p-3 w-14 text-center">#</th>
                            <th class="p-3 min-w-[280px]">Trophy Description</th>
                            <th class="p-3 w-36">Type</th>
                            <th class="p-3 min-w-[240px]">Winner / Recipient</th>
                            <th class="p-3 w-36">Score / Mark</th>
                            <th class="p-3 w-28 text-center">Stage Status</th>
                            <th class="p-3 w-24 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template v-for="group in groupedRows" :key="group.key">
                        <tr class="bg-slate-100"><td colspan="7" class="p-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-bold text-slate-700">{{ group.name }}</span>
                                <button type="button" class="text-indigo-700 font-semibold" @click="viewStandings(group.rows[0])" aria-label="View top 10">👁 Top 10</button>
                            </div>
                        </td></tr>
                        <tr v-for="r in group.rows" :key="r.trophy.id" class="hover:bg-slate-50/70 transition-colors">
                            <td class="p-3 text-center font-black text-slate-800 text-sm">
                                #{{ r.trophy.trophy_no }}
                            </td>
                            <td class="p-3">
                                <div class="font-bold text-slate-900 leading-snug">{{ r.trophy.title }}</div>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                    <span v-if="r.trophy.is_rolling" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-200">
                                        Ever-rolling Trophy
                                    </span>
                                    <span v-if="r.trophy.donor_name" class="text-[11px] text-slate-500 italic">
                                        Donor: {{ r.trophy.donor_name }}
                                    </span>
                                    <span v-if="r.trophy.notes" class="text-[10px] text-slate-400 block w-full mt-0.5">
                                        {{ r.trophy.notes }}
                                    </span>
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded-md font-medium text-[10px] uppercase tracking-wide"
                                      :class="badgeClassForType(r.trophy.trophy_type)">
                                    {{ trophyTypes[r.trophy.trophy_type] || r.trophy.trophy_type }}
                                </span>
                                <div v-if="r.trophy.position_ordinal" class="text-[11px] text-slate-500 font-semibold mt-1">
                                    Position: {{ r.trophy.position_ordinal }}
                                </div>
                            </td>
                            <td class="p-3">
                                <template v-if="r.winner && r.winner.name">
                                    <div class="font-bold text-slate-900 text-[13px] flex items-center gap-1.5">
                                        <span class="text-amber-500 font-black">★</span>
                                        <span>{{ r.winner.name }}</span>
                                    </div>
                                    <p v-if="r.winner.team_members?.length" class="text-xs text-slate-600 mt-1">{{ r.winner.team_members.join(', ') }}</p>
                                    <div v-if="r.winner.chest_no" class="text-[11px] text-slate-500 font-mono mt-0.5">
                                        Chest No: {{ r.winner.chest_no }}
                                    </div>
                                </template>
                                <template v-else>
                                    <span class="text-slate-400 italic">Awaiting published results</span>
                                </template>
                            </td>
                            <td class="p-3">
                                <template v-if="r.winner && (r.winner.detail || r.winner.points)">
                                    <span class="font-bold font-mono text-slate-800">{{ r.winner.detail || (r.winner.points + ' pts') }}</span>
                                </template>
                                <template v-else>
                                    <span class="text-slate-300">—</span>
                                </template>
                            </td>
                            <td class="p-3 text-center">
                                <span v-if="r.winner && r.winner.name" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    ✓ Ready
                                </span>
                                <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-medium bg-slate-100 text-slate-500">
                                    ○ Pending
                                </span>
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <button type="button" class="text-indigo-600 mr-2" @click="viewStandings(r)" aria-label="View top 10" title="View top 10">👁</button>
                                <button type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-900 mr-2" @click="editTrophy(r.trophy)">
                                    Edit
                                </button>
                                <button type="button" class="text-xs font-semibold text-rose-500 hover:text-rose-700" @click="deleteTrophy(r.trophy)">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        </template>
                        <tr v-if="!filteredRows.length">
                            <td colspan="7" class="p-12 text-center text-slate-400">
                                <p class="text-base font-bold text-slate-600">No matching trophies found</p>
                                <p class="text-xs mt-1">Click "Seed Metro Kalotsav Preset (75 Trophies)" or "+ Add Trophy" to get started.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add / Edit Trophy Modal -->
        <div v-if="standingsModal" class="fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-4" @click.self="standingsModal = null">
            <section role="dialog" aria-modal="true" aria-labelledby="trophy-standings-title" class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[85vh] overflow-y-auto p-5">
                <div class="flex justify-between items-center mb-4">
                    <h2 id="trophy-standings-title" class="font-bold text-lg">{{ standingsModal.name }} — Top 10</h2>
                    <button type="button" @click="standingsModal = null" aria-label="Close standings" class="btn-secondary">Close</button>
                </div>
                <table v-if="standingsModal.rows.length" class="w-full text-sm text-left">
                    <thead><tr class="border-b"><th class="p-2">Rank</th><th class="p-2">School / participant</th><th class="p-2">Points / marks</th></tr></thead>
                    <tbody><tr v-for="(entry, index) in standingsModal.rows" :key="index" class="border-b border-slate-100">
                        <td class="p-2">{{ entry.rank }}</td>
                        <td class="p-2">{{ entry.name }}<p v-if="entry.team_members?.length" class="text-xs text-slate-500 mt-1">{{ entry.team_members.join(', ') }}</p></td>
                        <td class="p-2">{{ entry.points ?? entry.score ?? '—' }}</td>
                    </tr></tbody>
                </table>
                <p v-else class="text-slate-500">No standings available for this award yet.</p>
            </section>
        </div>

        <div v-if="editingModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-start justify-between p-6 pb-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">{{ form.id ? 'Edit Trophy #' + form.trophy_no : 'Add New Trophy' }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Configure the trophy — pick a type, then fill in the matching fields below.</p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-600 text-xl font-bold leading-none" @click="editingModal = false">&times;</button>
                </div>

                <form @submit.prevent="submitForm" class="p-6 space-y-5 text-xs">
                    <!-- ── Section 1: Basics ── -->
                    <fieldset class="space-y-3">
                        <legend class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Basics</legend>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="lbl">Trophy <span class="text-slate-400 font-normal normal-case tracking-normal">#</span></label>
                                <input v-model.number="form.trophy_no" type="number" min="1" max="999" class="fld font-bold" required>
                            </div>
                            <div>
                                <label class="lbl">Position</label>
                                <select v-model.number="form.position" class="fld" required>
                                    <option :value="1">1st Place</option>
                                    <option :value="2">2nd Place</option>
                                    <option :value="3">3rd Place</option>
                                    <option :value="4">4th Place</option>
                                    <option :value="5">5th Place</option>
                                </select>
                            </div>
                            <div>
                                <label class="lbl">Award To</label>
                                <select v-model="form.award_type" class="fld" required>
                                    <option value="school">School</option>
                                    <option value="individual">Individual Student</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="lbl">Trophy Title</label>
                            <input v-model="form.title" type="text" class="fld" placeholder="e.g. First position in Folk Dance (Senior)" required>
                        </div>
                    </fieldset>

                    <!-- ── Section 2: Trophy Type ── -->
                    <fieldset class="space-y-3">
                        <legend class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Trophy Type</legend>
                        <div>
                            <label class="lbl">Category</label>
                            <select v-model="form.trophy_type" class="fld" required>
                                <option value="overall">🏆 Overall Champion (school points)</option>
                                <option value="category">📁 Category-wise (e.g. Arts, Sports)</option>
                                <option value="item">🎯 Single Item (specific competition)</option>
                                <option value="item_group">📦 Item Group / Cluster (e.g. all Art items)</option>
                                <option value="individual_championship">👤 Individual Championship (Kalaprathibha / Kalathilakam)</option>
                            </select>
                        </div>

                        <!-- Category / Individual Championship: pick a category -->
                        <div v-if="form.trophy_type === 'category' || form.trophy_type === 'individual_championship'">
                            <label class="lbl">Target Category</label>
                            <SearchableSelect v-model="form.category_key" class="w-full" :options="categoryOptions" :all-option="false" placeholder="Select category" />
                        </div>

                        <!-- Single Item: pick one item + optional name pattern -->
                        <div v-if="form.trophy_type === 'item'">
                            <label class="lbl">Match Item</label>
                            <SearchableSelect v-model="form.item_id" class="w-full" :options="itemsWithNull" :all-option="false" placeholder="— Use name pattern instead —" />
                            <div class="mt-2">
                                <label class="lbl">Name Pattern <span class="text-slate-400 font-normal normal-case tracking-normal">(matches items across events)</span></label>
                                <input v-model="form.item_name_pattern" type="text" class="fld" placeholder="e.g. One Act Play, Oppana, Margamkali">
                            </div>
                        </div>

                        <!-- Item Group: group name + optional explicit item list -->
                        <div v-if="form.trophy_type === 'item_group'">
                            <label class="lbl">Group / Cluster Name</label>
                            <input v-model="form.item_group_name" type="text" class="fld" placeholder="e.g. Music items, Art items">
                            <div class="mt-3">
                                <label class="lbl">Select specific items <span class="text-slate-400 font-normal normal-case tracking-normal">(optional)</span></label>
                                <input v-model="itemGroupFilter" type="text" class="fld mb-2" placeholder="Type to filter items…">
                                <div class="max-h-40 overflow-y-auto border rounded-lg p-2 bg-slate-50 space-y-1">
                                    <label v-for="it in filteredGroupItems" :key="it.id" class="flex items-center gap-2 text-slate-700 cursor-pointer hover:bg-white rounded px-1">
                                        <input type="checkbox" :value="it.id" v-model="form.item_ids" class="rounded">
                                        <span>{{ it.item_code }} — {{ it.title }}</span>
                                    </label>
                                    <p v-if="!filteredGroupItems.length" class="text-xs text-slate-400 px-1 py-1">No items match your filter.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Individual Championship: gender -->
                        <div v-if="form.trophy_type === 'individual_championship'">
                            <label class="lbl">Eligibility</label>
                            <SearchableSelect v-model="form.gender" class="w-full" :options="genderOptions" :all-option="false" placeholder="Select eligibility…" />
                        </div>
                    </fieldset>

                    <!-- ── Section 3: Details ── -->
                    <fieldset class="space-y-3">
                        <legend class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Details</legend>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="lbl">Donor / Sponsor <span class="text-slate-400 font-normal normal-case tracking-normal">(optional)</span></label>
                                <input v-model="form.donor_name" type="text" class="fld" placeholder="e.g. Late Shri ABC Memorial">
                            </div>
                            <div>
                                <label class="lbl">Notes <span class="text-slate-400 font-normal normal-case tracking-normal">(optional)</span></label>
                                <input v-model="form.notes" type="text" class="fld" placeholder="e.g. Includes classical music, violin…">
                            </div>
                        </div>
                        <div class="flex items-center gap-6 pt-1">
                            <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-700">
                                <input type="checkbox" v-model="form.is_rolling" class="rounded text-amber-600 focus:ring-amber-500">
                                <span>Ever-rolling Trophy</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-700">
                                <input type="checkbox" v-model="form.is_active" class="rounded text-indigo-600 focus:ring-indigo-500">
                                <span>Active</span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" class="btn-secondary" @click="editingModal = false">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">
                            {{ form.id ? 'Save Changes' : 'Create Trophy' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import FestEventWorkflowStepper from '@/Components/sahodaya/FestEventWorkflowStepper.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    scope: { type: String, default: 'phase' },
    usesPhases: { type: Boolean, default: false },
    trophyRows: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total_trophies: 0, resolved_winners: 0, rolling_trophies: 0 }) },
    hierarchy: { type: Object, default: () => ({ is_child: false, parent_id: null, parent_title: null, child_count: 0, children: [] }) },
    items: { type: Array, default: () => [] },
    categoryOptions: { type: Array, default: () => [] },
    trophyTypes: { type: Object, default: () => ({}) },
});

const standingsModal = ref(null);
function trophyGroupKey(trophy) {
    return JSON.stringify([trophy.trophy_type, trophy.category_key || '', trophy.gender || '',
        trophy.item_id || trophy.item_name_pattern || '', trophy.item_group_name || '',
        [...(trophy.item_ids || [])].sort((a, b) => a - b)]);
}
function trophyGroupName(trophy) {
    return [trophy.item_group_name || trophy.item_name || trophy.item_name_pattern || props.trophyTypes[trophy.trophy_type] || trophy.trophy_type,
        trophy.category_label || props.categoryOptions.find(option => option.value === trophy.category_key)?.label || trophy.category_key?.replaceAll('_', ' '), trophy.gender].filter(Boolean).join(' · ');
}
function viewStandings(row) {
    const matching = props.trophyRows.find(other => trophyGroupKey(other.trophy) === trophyGroupKey(row.trophy) && other.winner?.top_ten?.length);
    standingsModal.value = { name: trophyGroupName(row.trophy), rows: matching?.winner?.top_ten || [] };
}
const groupedRows = computed(() => {
    const groups = new Map();
    for (const row of filteredRows.value) {
        const key = trophyGroupKey(row.trophy);
        if (!groups.has(key)) groups.set(key, { key, name: trophyGroupName(row.trophy), rows: [] });
        groups.get(key).rows.push(row);
    }
    return [...groups.values()].map(group => ({ ...group, rows: group.rows.sort((a, b) => a.trophy.position - b.trophy.position || a.trophy.trophy_no - b.trophy.trophy_no) }));
});

const currentScope = ref(props.scope);
const searchQuery = ref('');
const filterType = ref('');
const filterStatus = ref('');
const editingModal = ref(false);

const typeOptions = computed(() => Object.entries(props.trophyTypes).map(([v, l]) => ({ value: v, label: l })));
const statusOptions = [
    { value: 'ready', label: 'Ready (Winner Found)' },
    { value: 'pending', label: 'Pending Results' },
];

const itemsWithNull = computed(() => [{ value: null, label: '— Use name pattern instead —' }, ...props.items.map(it => ({
    value: it.id,
    label: `${it.item_code ? it.item_code + ' — ' : ''}${it.title} (${it.class_group || it.category})`,
}))]);

const genderOptions = [
    { value: null, label: 'Any Gender / Open' },
    { value: 'male', label: 'Boys (Kalaprathibha)' },
    { value: 'female', label: 'Girls (Kalathilakam)' },
];

const itemGroupFilter = ref('');

const filteredGroupItems = computed(() => {
    if (!itemGroupFilter.value.trim()) return props.items;
    const q = itemGroupFilter.value.toLowerCase().trim();
    return props.items.filter(it =>
        it.title.toLowerCase().includes(q) ||
        (it.item_code && it.item_code.toLowerCase().includes(q)) ||
        (it.class_group && it.class_group.toLowerCase().includes(q))
    );
});

const filteredRows = computed(() => {
    let list = props.trophyRows;

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(r => {
            const num = String(r.trophy.trophy_no);
            const title = (r.trophy.title || '').toLowerCase();
            const winner = (r.winner?.name || '').toLowerCase();
            const donor = (r.trophy.donor_name || '').toLowerCase();
            return num.includes(q) || title.includes(q) || winner.includes(q) || donor.includes(q);
        });
    }

    if (filterType.value) {
        list = list.filter(r => r.trophy.trophy_type === filterType.value);
    }

    if (filterStatus.value) {
        if (filterStatus.value === 'ready') {
            list = list.filter(r => r.has_winner);
        } else if (filterStatus.value === 'pending') {
            list = list.filter(r => !r.has_winner);
        }
    }

    return list;
});

function badgeClassForType(type) {
    return {
        overall: 'bg-amber-100 text-amber-900 border border-amber-200',
        category: 'bg-indigo-100 text-indigo-900 border border-indigo-200',
        item: 'bg-purple-100 text-purple-900 border border-purple-200',
        item_group: 'bg-emerald-100 text-emerald-900 border border-emerald-200',
        individual_championship: 'bg-rose-100 text-rose-900 border border-rose-200',
    }[type] || 'bg-slate-100 text-slate-700';
}

function switchScope(newScope) {
    currentScope.value = newScope;
    router.get(window.location.pathname, { scope: newScope }, { preserveScroll: true, preserveState: true });
}

const form = useForm({
    id: null,
    trophy_no: 1,
    title: '',
    trophy_type: 'overall',
    position: 1,
    award_type: 'school',
    category_key: '',
    item_id: null,
    item_name_pattern: '',
    item_ids: [],
    item_group_name: '',
    gender: null,
    notes: '',
    is_rolling: false,
    donor_name: '',
    is_active: true,
});

function startNewTrophy() {
    form.reset();
    form.id = null;
    form.trophy_no = (props.trophyRows.length ? Math.max(...props.trophyRows.map(r => r.trophy.trophy_no)) + 1 : 1);
    editingModal.value = true;
}

function editTrophy(trophy) {
    form.reset();
    Object.assign(form, {
        id: trophy.id,
        trophy_no: trophy.trophy_no,
        title: trophy.title,
        trophy_type: trophy.trophy_type,
        position: trophy.position,
        award_type: trophy.award_type,
        category_key: trophy.category_key || '',
        item_id: trophy.item_id,
        item_name_pattern: trophy.item_name_pattern || '',
        item_ids: trophy.item_ids || [],
        item_group_name: trophy.item_group_name || '',
        gender: trophy.gender,
        notes: trophy.notes || '',
        is_rolling: trophy.is_rolling,
        donor_name: trophy.donor_name || '',
        is_active: trophy.is_active,
    });
    editingModal.value = true;
}

function submitForm() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/trophies`, {
        preserveScroll: true,
        onSuccess: () => {
            editingModal.value = false;
        },
    });
}

function deleteTrophy(trophy) {
    if (!confirm(`Are you sure you want to remove Trophy #${trophy.trophy_no} ("${trophy.title}")?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/trophies/${trophy.id}`, {
        preserveScroll: true,
    });
}

function seedPreset() {
    if (props.trophyRows.length && !confirm('This will replace current trophies with the official 75-trophy Metro Kalotsav template. Proceed?')) {
        return;
    }
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/trophies/seed-preset`, {}, {
        preserveScroll: true,
    });
}

function copyFromParent() {
    if (!confirm(`Inherit trophy template from parent event "${props.hierarchy.parent_title}"?`)) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/trophies/copy-parent`, {}, {
        preserveScroll: true,
    });
}

function pushToChildren() {
    if (!confirm(`Push this event's trophy template down to all ${props.hierarchy.child_count} child events?`)) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/trophies/push-children`, {}, {
        preserveScroll: true,
    });
}

const pdfExportUrl = computed(() => {
    return `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/trophies/export-pdf?scope=${currentScope.value}`;
});
</script>

<style scoped>
.lbl { display: block; margin-bottom: 0.25rem; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: rgb(100 116 139); }
.fld { width: 100%; border-radius: 0.5rem; border: 1px solid rgb(203 213 225); padding: 0.4rem 0.6rem; font-size: 0.8125rem; }
.fld:focus { outline: none; border-color: rgb(99 102 241); ring: 2px rgb(99 102 241 / 20%); }
</style>
