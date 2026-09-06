<template>
    <SahodayaEventsLayout :title="`${event.title} — Chest Numbers`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Chest numbers"
                    :description="selectedItem
                        ? (event.event_type === 'sports'
                            ? `Chest starts at ${selectedItem.chest_no_start} · one chest number per student, shared across sports items`
                            : `Chest starts at ${selectedItem.chest_no_start} · unique chest numbers assigned per item participant`)
                        : (event.event_type === 'sports'
                            ? 'Pick an item — each student keeps a single chest number across the sports event.'
                            : 'Pick an item — view or assign chest numbers per competition item.')">
            <template #actions>
                <Link :href="numberingUrl" class="btn-secondary text-sm">Numbering settings</Link>
            </template>
        </PageHeader>

        <SportsSetupSubNav v-if="event.event_type === 'sports'" :sahodaya-id="sahodaya.id" :event-id="event.id" active="chest-numbers" :event="event" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="chest-numbers" class="mb-4" />

        <!-- Sport Event / Region Switcher -->
        <div v-if="childEvents.length" class="card mb-4 !py-3">
            <div class="flex flex-wrap gap-3 items-center">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ event.event_type === 'sports' ? 'Select Sport Event / Region:' : 'Select Phase / Region:' }}</label>
                <SearchableSelect :model-value="String(event.id)" @update:model-value="switchSportEvent"
                                  :options="childEventOptions" :all-option="false" class="w-64" />
            </div>
        </div>

        <ReportHeadItemNavigator :groups="headItemGroups"
                                 :base-url="base"
                                 :selected-head-id="selectedHeadId"
                                 :selected-item-id="selectedItemId"
                                 :has-item-heads="hasItemHeads"
                                 :is-sports="event.event_type === 'sports'"
                                 :hint="event.event_type === 'sports'
                                     ? 'Select a competition item to view or assign chest numbers — each student holds one chest number for the whole sports event.'
                                     : 'Select a competition item to view or assign chest numbers for participants in that item.'"
                                 empty-heads-text="No enabled items on this event yet. Import items from the catalog first.">

            <template #default="{ item, head }">
                <div class="space-y-4 mt-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" :checked="includePending" class="rounded border-slate-300"
                                   @change="togglePending">
                            Include submitted (pending approval)
                        </label>
                        <span v-if="totalCount" class="text-xs font-semibold px-2.5 py-1 rounded-full"
                              :class="assignedCount === totalCount ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                            {{ assignedCount === totalCount ? '✓' : '⏳' }} {{ assignedCount }}/{{ totalCount }} chest numbers assigned
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn-primary text-sm" @click="generate">Assign missing chest</button>
                            <button type="button" class="btn-secondary text-sm" @click="assignItemReg">Assign missing item reg</button>
                            <a :href="`${printUrl}${printUrl.includes('?') ? '&' : '?'}inline=1`" target="_blank" class="btn-secondary text-sm">Preview list</a>
                            <a :href="`${printUrl}${printUrl.includes('?') ? '&' : '?'}download=1`" target="_blank" class="btn-secondary text-sm">Download list (PDF)</a>
                            <a :href="csvUrl" class="btn-secondary text-sm">CSV</a>
                            <button v-if="item?.stage_type === 'on_stage' && event.chest_reveal_mode === 'stage_entry'"
                                    type="button" class="btn-secondary text-sm"
                                    :class="showGreen ? 'border-emerald-400 bg-emerald-50' : ''"
                                    @click="showGreen = !showGreen">
                                Green room ({{ greenRoom.length }})
                            </button>
                        </div>

                        <!-- Kept visually apart from the safe/utility actions above — both of
                             these wipe already-assigned numbers (including manual ones), which
                             is easy to fat-finger when it's sitting in the same row as "CSV". -->
                        <div class="flex flex-wrap gap-2 border border-rose-200 bg-rose-50/50 rounded-lg p-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-rose-500 self-center pl-1">Danger zone</span>
                            <button type="button" class="btn-secondary text-sm !text-rose-700 hover:!bg-rose-100 !bg-white border-rose-300 font-semibold" @click="clearEntireEventChests">
                                Reset All Chests (Entire Event)
                            </button>
                            <button v-if="selectedItemId" type="button" class="btn-secondary text-sm !text-rose-700 hover:!bg-rose-100 !bg-white border-rose-300 font-semibold" @click="clearAllChests">
                                Clear Item Chests
                            </button>
                        </div>
                    </div>

                    <div v-if="showGreen" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                        <h3 class="font-semibold text-sm mb-2 text-emerald-900">Green room</h3>
                        <div class="overflow-x-auto">
                        <table class="w-full text-sm bg-white border rounded-lg">
                            <thead class="bg-gray-50"><tr>
                                <th class="p-2 text-left">Sl No</th><th class="p-2 text-left">Chest</th><th class="p-2 text-left">Fest ID</th><th class="p-2 text-left">Name</th><th class="p-2"></th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(p, idx) in greenRoom" :key="p.id" class="border-t">
                                    <td class="p-2 text-gray-500">{{ idx + 1 }}</td>
                                    <td class="p-2 font-mono">{{ p.chest_no ?? '—' }}</td>
                                    <td class="p-2 font-mono text-xs">{{ p.fest_id ?? '—' }}</td>
                                    <td class="p-2">{{ p.name }}</td>
                                    <td class="p-2 text-right">
                                        <button @click="reveal(p.id)" class="text-indigo-600 text-xs font-semibold">Reveal</button>
                                    </td>
                                </tr>
                                <tr v-if="!greenRoom.length"><td colspan="5" class="p-3 text-gray-400 text-center">None waiting</td></tr>
                            </tbody>
                        </table>
                        </div>
                    </div>

                    <div class="card card--flush overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="p-3">Sl No</th><th class="p-3">Chest</th><th class="p-3">Fest ID</th><th class="p-3">Item reg</th>
                                    <th class="p-3">Participant / Team</th><th class="p-3">School</th><th class="p-3">Status</th>
                                    <th class="p-3">{{ hasTeamRows ? 'Members' : 'Team' }}</th><th class="p-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(p, idx) in participants" :key="p.id" class="border-t"
                                    :class="p.chest_no ? 'hover:bg-slate-50' : 'bg-amber-50/60 hover:bg-amber-50'">
                                    <td class="p-3 text-gray-500">{{ idx + 1 }}</td>
                                    <td class="p-3">
                                        <div class="flex items-center gap-1">
                                            <input v-model="chestDrafts[p.id]" type="number" min="1" max="65535"
                                                   class="field text-xs font-mono font-bold w-20 chest-no-input" placeholder="—"
                                                   :data-participant-id="p.id"
                                                   :disabled="p.chest_is_manual"
                                                   :title="p.chest_is_manual ? 'Manually entered — clear it first to change' : ''"
                                                   @input="clearRowFeedback(p.id)"
                                                   @keydown.enter.prevent="saveChest(p.id)">
                                            <button v-if="!p.chest_is_manual" type="button" class="btn-secondary text-[11px] !py-1 !px-2 whitespace-nowrap"
                                                    :disabled="savingChestId === p.id || !chestDrafts[p.id] || Number(chestDrafts[p.id]) === (p.chest_no ?? null)"
                                                    @click="saveChest(p.id)">
                                                {{ savingChestId === p.id ? '...' : 'Save' }}
                                            </button>
                                            <span v-else class="text-[10px] font-semibold text-indigo-700 whitespace-nowrap">🔒 Manual</span>
                                        </div>
                                        <p v-if="savedIds.has(p.id)" class="text-[11px] font-semibold text-emerald-600 mt-0.5">Saved ✓</p>
                                        <p v-else-if="rowErrors[p.id]" class="text-[11px] font-semibold text-rose-600 mt-0.5">{{ rowErrors[p.id] }}</p>
                                    </td>
                                    <td class="p-3 font-mono text-xs text-[#0f3d7a]">{{ p.fest_id ?? '—' }}</td>
                                    <td class="p-3 font-mono text-xs">{{ p.item_reg ?? '—' }}</td>
                                    <td class="p-3">
                                        {{ p.name }}
                                        <span v-if="p.is_team" class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 align-middle">
                                            Team · {{ p.member_count }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-xs">{{ (p.school || '').toUpperCase() }}</td>
                                    <td class="p-3 text-xs" :class="p.reg_status === 'approved' ? 'text-emerald-700' : 'text-amber-700'">{{ p.reg_status }}</td>
                                    <td class="p-3 text-xs">{{ p.group ?? '—' }}</td>
                                    <td class="p-3 text-right whitespace-nowrap">
                                        <button v-if="p.chest_no" @click="clearChest(p.id)" class="text-red-600 text-xs mr-2">Clear</button>
                                        <button v-if="event.chest_reveal_mode === 'stage_entry' && !p.chest_revealed_at" @click="reveal(p.id)"
                                                class="text-indigo-600 text-xs">Reveal</button>
                                    </td>
                                </tr>
                                <tr v-if="!participants.length">
                                    <td colspan="9" class="p-0">
                                        <EmptyState title="No participants for this item"
                                            description="Approve registrations for this item first, then chest numbers can be generated." icon="🔢" class="py-8">
                                            <template #action>
                                                <Link :href="registrationsUrl" class="btn-primary text-xs">Review Registrations</Link>
                                            </template>
                                        </EmptyState>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="hasTeamRows" class="px-3 py-2 text-xs text-slate-500 border-t">
                            This is a team item — one chest number is shared by the whole squad. Clearing or revealing applies to every member.
                        </p>
                    </div>
                </div>
            </template>
        </ReportHeadItemNavigator>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportHeadItemNavigator from '@/Components/reports/ReportHeadItemNavigator.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object,
    headItemGroups: { type: Array, default: () => [] },
    hasItemHeads: { type: Boolean, default: false },
    selectedHeadId: { type: [String, Number], default: null },
    selectedItemId: { type: [String, Number], default: null },
    selectedItem: { type: Object, default: null },
    participants: { type: Array, default: () => [] },
    greenRoom: { type: Array, default: () => [] },
    includePending: { type: Boolean, default: false },
    view: String,
    activityLogs: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
    itemHasMarksOrAttendance: { type: Boolean, default: false },
    eventHasMarksOrAttendance: { type: Boolean, default: false },
});

function switchSportEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/chest-numbers`);
}

const childEventOptions = computed(() =>
    props.childEvents.map((ev) => ({ value: String(ev.id), label: ev.short_title || ev.title })),
);

const showGreen = ref(props.view === 'green-room');
const hasTeamRows = computed(() => props.participants.some((p) => p.is_team));
const assignedCount = computed(() => props.participants.filter((p) => p.chest_no).length);
const totalCount = computed(() => props.participants.length);
const base = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/chest-numbers`);
const registrationsUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations`);
const numberingUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/settings/numbering`);
const pageTitle = computed(() => {
    if (props.selectedItem) return `${props.event.title} — ${props.selectedItem.title}`;
    if (props.selectedHeadId) {
        const head = props.headItemGroups.find((g) =>
            props.selectedHeadId === 'other' ? g.head_id == null : String(g.head_id) === String(props.selectedHeadId));
        return `${props.event.title} — ${head?.head_name ?? 'Section'}`;
    }
    return `${props.event.title} — Chest Numbers`;
});
const printUrl = computed(() =>
    props.selectedItemId ? `${base.value}/print?item_id=${props.selectedItemId}` : `${base.value}/print`,
);
const csvUrl = computed(() =>
    props.selectedItemId ? `${base.value}/csv?item_id=${props.selectedItemId}` : `${base.value}/csv`,
);

const { confirm } = useConfirm();

// preserveState: true on every action below (bulk and per-row) so this component
// instance survives each round trip instead of being torn down and remounted — that's
// what makes the per-row Saved/Error feedback below actually visible (a fresh mount
// would reset savedIds/rowErrors to empty before the user ever sees them) and avoids a
// jarring scroll/flash on every click for what's often a single-field change.
function postAction(path) {
    if (!props.selectedItemId) return;
    router.post(path, { item_id: props.selectedItemId }, { preserveScroll: true, preserveState: true });
}
function generate() { postAction(`${base.value}/generate`); }
function assignItemReg() { postAction(`${base.value}/assign-item-ids`); }
async function clearEntireEventChests() {
    let message = `Are you sure you want to reset and clear ALL chest numbers across the ENTIRE event "${props.event.title}"?\n\nThis will wipe chest numbers for all items so numbering starts back at 100.`;
    if (props.eventHasMarksOrAttendance) {
        message += '\n\n⚠️ Marks or attendance already exist for this event. If judges have a printed sheet with the old chest numbers, it will no longer match.';
    }
    if (!(await confirm({ message, destructive: true }))) return;

    router.post(`${base.value}/clear-all`, {}, { preserveScroll: true, preserveState: true });
}
async function clearAllChests() {
    let message = `Are you sure you want to clear chest numbers for item "${props.selectedItem?.title || ''}"?`;
    if (props.itemHasMarksOrAttendance) {
        message += '\n\n⚠️ Marks or attendance already exist for this item. If a judge has a printed sheet with the old chest numbers, it will no longer match.';
    }
    if (!(await confirm({ message, destructive: props.itemHasMarksOrAttendance }))) return;

    router.post(`${base.value}/clear-all`, { item_id: props.selectedItemId }, { preserveScroll: true, preserveState: true });
}
async function clearChest(id) {
    let message = 'Clear chest number?';
    if (props.itemHasMarksOrAttendance) {
        message += '\n\n⚠️ Marks or attendance already exist for this item. If a judge has a printed sheet with the old chest number, it will no longer match.';
    }
    if (!(await confirm({ message, destructive: props.itemHasMarksOrAttendance }))) return;

    clearRowFeedback(id);
    router.post(`${base.value}/${id}/clear`, {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const err = usePage().props.flash?.error;
            if (err) markRowError(id, err); else markRowSaved(id);
        },
    });
}
function reveal(id) {
    router.post(`${base.value}/${id}/reveal`, {}, { preserveScroll: true, preserveState: true });
}

// Manual chest entry: each row keeps its own draft value, seeded from the assigned
// number (blank when unassigned) so the organizer can type a custom number instead of
// relying on "Assign missing chest" auto-numbering. With preserveState above keeping
// this component mounted across saves, a watcher (below) re-syncs drafts to the
// server's copy whenever participants refresh — covering every action that can change
// someone else's row (generate, assign-missing, clear-all), not just this row's own save.
const chestDrafts = reactive({});
for (const p of props.participants) {
    chestDrafts[p.id] = p.chest_no ?? '';
}
const savingChestId = ref(null);
const savedIds = ref(new Set());
const rowErrors = reactive({});
let savedTimer = null;

watch(() => props.participants, (list) => {
    for (const p of list) {
        if (savingChestId.value === p.id) continue;
        chestDrafts[p.id] = p.chest_no ?? '';
    }
});

function clearRowFeedback(id) {
    if (savedIds.value.has(id)) {
        const next = new Set(savedIds.value);
        next.delete(id);
        savedIds.value = next;
    }
    delete rowErrors[id];
}

function markRowSaved(id) {
    delete rowErrors[id];
    const next = new Set(savedIds.value);
    next.add(id);
    savedIds.value = next;
    // Fades on its own rather than lingering forever once the row is visibly updated.
    clearTimeout(savedTimer);
    savedTimer = setTimeout(() => {
        const cleared = new Set(savedIds.value);
        cleared.delete(id);
        savedIds.value = cleared;
    }, 2500);
}

function markRowError(id, message) {
    if (savedIds.value.has(id)) {
        const next = new Set(savedIds.value);
        next.delete(id);
        savedIds.value = next;
    }
    rowErrors[id] = message || 'Could not save — please try again.';
}

// Jump straight to the next row's chest field on success so entering numbers for a
// long roster doesn't require reaching for the mouse after every single Save. Waits a
// tick for Vue to apply the just-saved row's own disabled state first — otherwise, if
// that row already dropped out of a ":not(:disabled)" query by the time this runs, the
// lookup for "this row's position" fails and focus jumps to row 1 instead of the next
// one. Finding the index among ALL inputs (disabled included), then walking forward
// past any locked ones, keeps that lookup correct regardless of the timing.
async function focusNextChestInput(afterId) {
    await nextTick();
    const inputs = Array.from(document.querySelectorAll('input.chest-no-input'));
    const idx = inputs.findIndex((el) => Number(el.dataset.participantId) === Number(afterId));
    if (idx === -1) return;

    for (let i = idx + 1; i < inputs.length; i++) {
        if (!inputs[i].disabled) {
            inputs[i].focus();
            inputs[i].select();
            return;
        }
    }
}

function saveChest(id) {
    const raw = chestDrafts[id];
    const chestNo = Number(raw);
    if (!raw || !Number.isInteger(chestNo) || chestNo <= 0) return;

    const participant = props.participants.find((p) => p.id === id);
    if (participant && chestNo === (participant.chest_no ?? null)) return;

    savingChestId.value = id;
    router.post(`${base.value}/${id}/set`, { chest_no: chestNo }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const err = usePage().props.flash?.error;
            if (err) {
                markRowError(id, err);
            } else {
                markRowSaved(id);
                focusNextChestInput(id);
            }
        },
        onError: (errors) => markRowError(id, errors?.chest_no),
        onFinish: () => { savingChestId.value = null; },
    });
}
function togglePending(e) {
    if (!props.selectedItemId) return;
    const params = { item_id: props.selectedItemId, include_pending: e.target.checked ? 1 : undefined };
    if (props.selectedHeadId != null) params.head_id = props.selectedHeadId;
    router.get(base.value, params, { preserveState: true, preserveScroll: true });
}
</script>
