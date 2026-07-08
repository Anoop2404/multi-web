<template>
    <section v-if="showSection" class="card card--flush overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="section-title !mb-0">Sports & athletics</h3>
            <p class="text-xs text-slate-500 mt-1">
                <template v-if="isPortal">
                    Your sports event registrations. Schools usually register athletes — self-registration is only available when enabled by your Sahodaya.
                </template>
                <template v-else>
                    Register this student for sports events and items. Event registration may be required before item entry.
                </template>
            </p>
        </div>

        <div v-if="!sportsEvents.length" class="px-5 py-4 text-sm text-slate-500">
            No open sports events for registration right now.
        </div>

        <div v-for="event in sportsEvents" :key="event.event_id" class="border-t border-slate-100">
            <div class="px-5 py-3 bg-slate-50/80 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-semibold text-slate-900">{{ event.event_title }}</p>
                    <p class="text-xs text-slate-500 capitalize mt-0.5">{{ event.event_status?.replace(/_/g, ' ') }}</p>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <span v-if="event.event_registration?.registered"
                          class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-800 px-2 py-0.5 text-xs font-semibold">
                        Event reg #{{ event.event_registration.registration_number }}
                    </span>
                    <span v-else-if="event.require_event_registration"
                          class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-800 px-2 py-0.5 text-xs font-semibold">
                        Not registered for event
                    </span>
                    <span v-else class="text-xs text-slate-400">Event registration optional</span>

                    <p v-if="event.actions?.read_only_reason"
                       class="text-xs text-slate-500 max-w-xs text-right">
                        {{ event.actions.read_only_reason }}
                    </p>
                    <p v-else-if="event.actions?.school_block_message"
                       class="text-xs text-amber-700 max-w-xs text-right">
                        {{ event.actions.school_block_message }}
                    </p>
                    <p v-else-if="event.actions?.fee_block_message"
                       class="text-xs text-amber-700 max-w-xs text-right">
                        {{ event.actions.fee_block_message }}
                    </p>

                    <button v-if="event.actions?.can_register_event"
                            type="button"
                            class="btn-primary text-xs !min-h-0 !py-1.5"
                            :disabled="registeringEventId === event.event_id"
                            @click="registerForEvent(event)">
                        {{ registeringEventId === event.event_id ? 'Registering…' : 'Register for event' }}
                    </button>
                    <button v-if="event.actions?.can_register_items"
                            type="button"
                            class="btn-secondary text-xs !min-h-0 !py-1.5"
                            @click="openItemModal(event)">
                        Register for item
                    </button>
                    <a v-if="!isPortal && event.actions?.item_registration_url"
                       :href="event.actions.item_registration_url"
                       class="text-xs font-semibold text-indigo-700 hover:underline px-1">
                        Full item registration →
                    </a>
                    <a v-else-if="isPortal && event.actions?.fest_registrations_url"
                       :href="event.actions.fest_registrations_url"
                       class="text-xs font-semibold text-indigo-700 hover:underline px-1">
                        Manage registrations →
                    </a>

                    <template v-if="event.id_cards?.payment_pending">
                        <span class="text-xs font-semibold text-amber-700 px-1" :title="event.id_cards.reason">
                            ID card — payment pending
                        </span>
                    </template>
                    <template v-else-if="event.id_cards?.available">
                        <a v-if="event.id_cards.heads?.length"
                           v-for="head in event.id_cards.heads"
                           :key="head.head_id"
                           :href="head.view_url"
                           target="_blank"
                           rel="noopener"
                           class="text-xs font-semibold text-slate-700 hover:underline px-1"
                           :title="`View ID card — ${head.head_name}`">
                            View ID ({{ head.head_name }})
                        </a>
                        <template v-else>
                            <a :href="event.id_cards.view_url"
                               target="_blank"
                               rel="noopener"
                               class="text-xs font-semibold text-slate-700 hover:underline px-1">
                                View ID card
                            </a>
                        </template>
                        <a v-if="event.id_cards.heads?.length"
                           v-for="head in event.id_cards.heads"
                           :key="`dl-${head.head_id}`"
                           :href="head.download_url"
                           class="text-xs font-semibold text-indigo-700 hover:underline px-1"
                           :title="`Download ID card — ${head.head_name}`">
                            Download ({{ head.head_name }})
                        </a>
                        <a v-else
                           :href="event.id_cards.download_url"
                           class="text-xs font-semibold text-indigo-700 hover:underline px-1">
                            Download ID card
                        </a>
                    </template>
                </div>
            </div>

            <div v-if="!event.heads?.length" class="px-5 py-3 text-sm text-slate-500">
                <template v-if="event.event_registration?.registered">
                    Registered for the event — no items assigned yet.
                </template>
                <template v-else>
                    Register for the event first, then add items.
                </template>
            </div>

            <div v-for="head in event.heads" :key="head.head_id ?? head.head_name" class="border-t border-slate-100">
                <div class="px-5 py-2 flex flex-wrap items-baseline justify-between gap-2 bg-white">
                    <p class="text-sm font-semibold text-indigo-900">{{ head.head_name }}</p>
                    <p v-if="head.competition_start || head.competition_end" class="text-xs text-slate-500">
                        Competition:
                        {{ formatDate(head.competition_start) }}
                        <span v-if="head.competition_end && head.competition_end !== head.competition_start">
                            – {{ formatDate(head.competition_end) }}
                        </span>
                    </p>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="p-3 pl-5">Item</th>
                            <th class="p-3">Chest</th>
                            <th class="p-3">Result</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in head.items" :key="item.item_id" class="border-t">
                            <td class="p-3 pl-5">
                                <p>{{ item.item_title }}</p>
                                <p v-if="item.sport_discipline" class="text-xs text-slate-400 capitalize">
                                    {{ item.sport_discipline.replace(/_/g, ' ') }}
                                </p>
                            </td>
                            <td class="p-3 font-mono text-xs">{{ item.chest_no ?? '—' }}</td>
                            <td class="p-3 text-xs">
                                <template v-if="item.mark">
                                    <span v-if="item.mark.measurement_value" class="font-semibold text-indigo-800">
                                        {{ item.mark.measurement_value }}
                                        <span v-if="item.mark.measurement_unit">{{ item.mark.measurement_unit }}</span>
                                    </span>
                                    <span v-if="item.mark.position" class="ml-1">· P{{ item.mark.position }}</span>
                                    <span v-if="item.mark.grade" class="ml-1">· {{ item.mark.grade }}</span>
                                    <span v-if="item.mark.record_break"
                                          class="block mt-0.5 text-amber-700 font-semibold"
                                          :title="item.mark.record_break_label">
                                        🏆 Record break
                                    </span>
                                </template>
                                <span v-else class="text-slate-400">Pending</span>
                            </td>
                            <td class="p-3 capitalize text-xs">{{ item.registration_status }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="otherFest.length" class="border-t border-slate-100">
            <div class="px-5 py-3 bg-slate-50/80">
                <p class="text-sm font-semibold text-slate-800">Other fest entries</p>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="p-3 pl-5">Event</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Chest</th>
                        <th class="p-3">Result</th>
                        <th class="p-3">ID card</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in otherFest" :key="idx" class="border-t">
                        <td class="p-3 pl-5">{{ row.event_title ?? '—' }}</td>
                        <td class="p-3">{{ row.item_title ?? '—' }}</td>
                        <td class="p-3 font-mono text-xs">{{ row.chest_no ?? '—' }}</td>
                        <td class="p-3 text-xs">
                            <span v-if="row.mark?.grade || row.mark?.position">
                                {{ row.mark.grade }}<span v-if="row.mark.position"> · P{{ row.mark.position }}</span>
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="p-3 text-xs whitespace-nowrap">
                            <template v-if="row.id_cards?.available">
                                <a :href="row.id_cards.view_url"
                                   target="_blank"
                                   rel="noopener"
                                   class="font-semibold text-slate-700 hover:underline mr-2">
                                    View
                                </a>
                                <a :href="row.id_cards.download_url"
                                   class="font-semibold text-indigo-700 hover:underline">
                                    Download
                                </a>
                            </template>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Item registration modal -->
        <div v-if="itemModalEvent" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/50" @click="closeItemModal"></div>
            <div class="relative card w-full max-w-lg max-h-[85vh] overflow-hidden flex flex-col shadow-xl !p-0">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900">Register for item</h3>
                    <p class="text-xs text-slate-500 mt-1">{{ itemModalEvent.event_title }}</p>
                </div>
                <div class="px-5 py-3 overflow-y-auto flex-1">
                    <p v-if="loadingItems" class="text-sm text-slate-500 py-6 text-center">Loading eligible items…</p>
                    <p v-else-if="itemsError" class="text-sm text-red-600 py-4">{{ itemsError }}</p>
                    <p v-else-if="!eligibleItems.length" class="text-sm text-slate-500 py-4">
                        No eligible items available for this student right now.
                    </p>
                    <div v-else class="space-y-2">
                        <label v-for="item in eligibleItems" :key="item.id"
                               class="flex items-start gap-2 rounded-xl border px-3 py-2 text-sm cursor-pointer transition-colors"
                               :class="itemPickerClass(item)">
                            <input type="checkbox"
                                   :value="item.id"
                                   v-model="selectedItemIds"
                                   :disabled="!item.eligible"
                                   class="rounded mt-0.5">
                            <span class="min-w-0">
                                <span class="font-medium text-slate-900">{{ item.title }}</span>
                                <span v-if="item.head_name" class="text-xs text-slate-500 block">{{ item.head_name }}</span>
                                <span v-if="item.reason && !item.eligible" class="text-xs text-amber-700 block mt-0.5">{{ item.reason }}</span>
                            </span>
                        </label>
                    </div>
                </div>
                <div class="px-5 py-3 border-t border-slate-100 flex flex-wrap gap-2 justify-end bg-slate-50/80">
                    <button type="button" class="btn-secondary text-sm" @click="closeItemModal">Cancel</button>
                    <button type="button" class="btn-primary text-sm"
                            :disabled="!selectedItemIds.length || submittingItems"
                            @click="submitItems">
                        {{ submittingItems ? 'Registering…' : `Register ${selectedItemIds.length || ''} item(s)`.trim() }}
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    sportsProfile: {
        type: Object,
        default: () => ({ sports_events: [], other_fest: [], has_open_sports_events: false }),
    },
    context: {
        type: String,
        default: 'school',
    },
    schoolId: {
        type: String,
        default: '',
    },
});

const isPortal = computed(() => props.context === 'portal');

const sportsEvents = computed(() => props.sportsProfile?.sports_events ?? []);
const otherFest = computed(() => props.sportsProfile?.other_fest ?? []);
const showSection = computed(() =>
    sportsEvents.value.length > 0
    || otherFest.value.length > 0
    || props.sportsProfile?.has_open_sports_events,
);

const registeringEventId = ref(null);
const itemModalEvent = ref(null);
const eligibleItems = ref([]);
const selectedItemIds = ref([]);
const loadingItems = ref(false);
const itemsError = ref('');
const submittingItems = ref(false);

function formatDate(value) {
    if (!value) return '';
    return new Date(value).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function registerForEvent(event) {
    if (!event.actions?.register_event_url) return;
    registeringEventId.value = event.event_id;
    router.post(event.actions.register_event_url, {}, {
        preserveScroll: true,
        onFinish: () => { registeringEventId.value = null; },
    });
}

async function openItemModal(event) {
    itemModalEvent.value = event;
    eligibleItems.value = [];
    selectedItemIds.value = [];
    itemsError.value = '';
    loadingItems.value = true;

    try {
        const url = event.actions?.eligible_items_url;
        if (!url) throw new Error('Unable to load items.');
        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error('Could not load eligible items.');
        const data = await res.json();
        eligibleItems.value = (data.items ?? []).filter((i) => !i.already_registered);
    } catch (e) {
        itemsError.value = e.message || 'Failed to load items.';
    } finally {
        loadingItems.value = false;
    }
}

function closeItemModal() {
    itemModalEvent.value = null;
    eligibleItems.value = [];
    selectedItemIds.value = [];
    itemsError.value = '';
}

function itemPickerClass(item) {
    if (!item.eligible) return 'border-slate-100 bg-slate-50 opacity-60 cursor-not-allowed';
    if (selectedItemIds.value.includes(item.id)) return 'border-indigo-300 bg-indigo-50';
    return 'border-slate-200 hover:border-indigo-200';
}

async function submitItems() {
    const event = itemModalEvent.value;
    if (!event?.actions || !selectedItemIds.value.length) return;

    submittingItems.value = true;

    try {
        if (isPortal.value) {
            const schoolId = props.schoolId;
            if (!schoolId) throw new Error('Missing school context.');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            for (const itemId of selectedItemIds.value) {
                const res = await fetch(
                    `/portal/student/${schoolId}/fest/${event.event_id}/items/${itemId}/register`,
                    {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                    },
                );
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    throw new Error(data.message || 'Could not register for one or more items.');
                }
            }
            router.reload({ preserveScroll: true });
        } else if (event.actions.register_items_url) {
            await new Promise((resolve, reject) => {
                router.post(event.actions.register_items_url, { item_ids: selectedItemIds.value }, {
                    preserveScroll: true,
                    onSuccess: resolve,
                    onError: reject,
                    onFinish: () => {},
                });
            });
        }
        closeItemModal();
    } catch (e) {
        itemsError.value = e.message || 'Registration failed.';
    } finally {
        submittingItems.value = false;
    }
}
</script>
