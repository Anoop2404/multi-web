<template>
    <PortalLayout
        role-label="Student Portal"
        title="My event registrations"
        :subtitle="school.name"
        accent="indigo"
        :nav-items="navItems"
    >
        <PageHeader title="Fest & sports registrations" eyebrow="Student portal" :description="`Registrations for ${student.name}`" />

        <div v-for="ev in events" :key="ev.id" class="card mb-4 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="font-semibold text-slate-900">{{ ev.title }}</h3>
                    <p class="text-xs text-slate-500 capitalize">{{ ev.event_type?.replace(/_/g, ' ') }}</p>
                </div>
                <button v-if="!ev.registered && ev.registration_open && !ev.school_registration_closed"
                        type="button" class="btn-primary text-sm"
                        :disabled="registeringEventId === ev.id"
                        @click="registerEvent(ev.id)">
                    {{ registeringEventId === ev.id ? 'Registering…' : 'Register for event' }}
                </button>
                <span v-else-if="ev.registered" class="text-xs font-medium text-emerald-700">Registered for event</span>
            </div>

            <div v-if="ev.school_registration_closed" class="notice-banner notice-banner--warning text-sm">
                Fest registration is closed for your school. Contact your coordinator.
            </div>
            <div v-else-if="ev.fee_block_message" class="notice-banner notice-banner--warning text-sm">
                {{ ev.fee_block_message }}
            </div>

            <div v-if="ev.items?.length" class="border-t border-slate-100 pt-3">
                <p class="text-xs font-semibold text-slate-500 mb-2">My items</p>
                <ul class="text-sm space-y-1">
                    <li v-for="item in ev.items" :key="item.id" class="flex justify-between gap-2">
                        <span>{{ item.item_title }}</span>
                        <span class="text-slate-500 capitalize">{{ item.status }}</span>
                    </li>
                </ul>
            </div>

            <div v-if="ev.registered && !ev.fee_blocks_items && !ev.school_registration_closed"
                 class="border-t border-slate-100 pt-3">
                <button type="button" class="btn-secondary text-sm"
                        @click="openItemModal(ev)">
                    Register for an item
                </button>
            </div>
            <p v-else-if="ev.require_event_registration && !ev.registered"
               class="text-xs text-amber-700 border-t border-slate-100 pt-3">
                Register for the event first, then you can pick items.
            </p>
        </div>

        <EmptyState v-if="!events.length" title="No self-registration events" description="Your Sahodaya has not enabled student self-registration for open events. Your school will register you for sports items." />

        <!-- Item registration modal -->
        <div v-if="itemModalEvent" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/50" @click="closeItemModal"></div>
            <div class="relative card w-full max-w-lg max-h-[85vh] overflow-hidden flex flex-col shadow-xl !p-0">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900">Register for item</h3>
                    <p class="text-xs text-slate-500 mt-1">{{ itemModalEvent.title }}</p>
                </div>
                <div class="px-5 py-3 overflow-y-auto flex-1">
                    <p v-if="loadingItems" class="text-sm text-slate-500 py-6 text-center">Loading eligible items…</p>
                    <p v-else-if="itemsError" class="text-sm text-red-600 py-4">{{ itemsError }}</p>
                    <p v-else-if="!eligibleItems.length" class="text-sm text-slate-500 py-4">
                        No eligible individual items available right now.
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
    </PortalLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';

const props = defineProps({
    school: Object,
    student: Object,
    events: { type: Array, default: () => [] },
});

const navItems = computed(() => studentPortalNavItems(props.school.id));

const registeringEventId = ref(null);
const itemModalEvent = ref(null);
const eligibleItems = ref([]);
const selectedItemIds = ref([]);
const loadingItems = ref(false);
const itemsError = ref('');
const submittingItems = ref(false);

function registerEvent(eventId) {
    registeringEventId.value = eventId;
    router.post(`/portal/student/${props.school.id}/fest/${eventId}/register`, {}, {
        preserveScroll: true,
        onFinish: () => { registeringEventId.value = null; },
    });
}

async function openItemModal(ev) {
    itemModalEvent.value = ev;
    eligibleItems.value = [];
    selectedItemIds.value = [];
    itemsError.value = '';
    loadingItems.value = true;

    try {
        const res = await fetch(
            `/portal/student/${props.school.id}/fest/${ev.id}/eligible-items`,
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
        );
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
    const ev = itemModalEvent.value;
    if (!ev || !selectedItemIds.value.length) return;

    submittingItems.value = true;
    itemsError.value = '';

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        for (const itemId of selectedItemIds.value) {
            const res = await fetch(
                `/portal/student/${props.school.id}/fest/${ev.id}/items/${itemId}/register`,
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
                throw new Error(data.message || 'Registration failed for one or more items.');
            }
        }
        closeItemModal();
        router.reload({ preserveScroll: true });
    } catch (e) {
        itemsError.value = e.message || 'Registration failed.';
    } finally {
        submittingItems.value = false;
    }
}
</script>
