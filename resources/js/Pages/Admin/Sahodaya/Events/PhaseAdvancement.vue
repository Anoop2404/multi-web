<template>
    <SahodayaEventsLayout :title="`${event.title} — Phase Advancement`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Phase Advancement" eyebrow="Regional winners -> later phase"
                    description="Pick winners across a regional phase's regions and register them directly into a later phase's item — e.g. Off Stage/Sargadhara region winners advancing to District Kalotsav. This does not affect Sahodaya-to-State promotion." />

        <div class="mb-4">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/results`" class="link-brand text-sm">&larr; Back to Results</Link>
        </div>

        <div class="card space-y-4 mb-6">
            <h4 class="section-title">Pick an item to advance</h4>
            <div class="grid sm:grid-cols-2 gap-4">
                <label class="block">
                    <span class="text-xs font-semibold text-slate-500">From item (regional phase)</span>
                    <SearchableSelect
                        v-model="fromItemId"
                        :options="fromItemOptions"
                        placeholder="Select an item…"
                        search-placeholder="Type item name to search…"
                        :all-option="false"
                        class="mt-1"
                        @change="loadCandidates"
                    />
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-slate-500">To item (later phase)</span>
                    <SearchableSelect
                        v-model="toItemId"
                        :options="toItemOptions"
                        placeholder="Select an item…"
                        search-placeholder="Type item name to search…"
                        :all-option="false"
                        class="mt-1"
                    />
                </label>
            </div>
        </div>

        <div v-if="fromItemId" class="card space-y-3 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h4 class="section-title">Candidates ({{ candidates.length }})</h4>
                <button type="button" class="btn-primary text-sm shrink-0" :disabled="selectedIds.length === 0 || !toItemId || advanceForm.processing"
                        @click="advance">
                    Advance selected ({{ selectedIds.length }})
                </button>
            </div>
            <p v-if="!toItemId" class="text-xs text-amber-700">Select a target item above before advancing.</p>
            <div v-if="candidates.length === 0" class="text-sm text-slate-400">
                No eligible candidates — either no region under this phase has published results yet, or nothing is registered for this item.
            </div>
            <ul v-else class="divide-y divide-slate-100 text-sm max-h-[32rem] overflow-y-auto">
                <li v-for="c in candidates" :key="c.registration_id" class="py-2.5 flex items-start gap-3">
                    <input type="checkbox" class="mt-1" :checked="selectedIds.includes(c.registration_id)"
                           :disabled="c.already_advanced" @change="toggleSelected(c.registration_id)" />
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
                            <div class="min-w-0">
                                <span class="font-semibold text-slate-700">{{ c.school_name || c.school_id }}</span>
                                <span v-if="c.team_name" class="ml-1.5 text-xs text-slate-500">({{ c.team_name }})</span>
                                <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold bg-slate-200 text-slate-700">
                                    {{ c.region_name }}
                                </span>
                                <span v-if="c.already_advanced" class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                    Already advanced
                                </span>
                            </div>
                            <span class="text-xs text-slate-400 shrink-0">Pos {{ c.position ?? '—' }} · {{ c.grade || '—' }}</span>
                        </div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            {{ c.participants.map(p => p.name).filter(Boolean).join(', ') || '—' }}
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div class="card space-y-3">
            <h4 class="section-title">Advancement history ({{ advancements.length }})</h4>
            <div v-if="advancements.length === 0" class="text-sm text-slate-400">No advancements yet.</div>
            <ul v-else class="divide-y divide-slate-100 text-sm">
                <li v-for="a in advancements" :key="a.id" class="py-2.5 flex items-start justify-between gap-2">
                    <div>
                        <div class="font-semibold text-slate-700">
                            {{ a.from_registration?.school?.name || '—' }}
                        </div>
                        <div class="text-xs text-slate-500">
                            {{ a.from_item?.title }} ({{ a.region?.name || 'region' }}) &rarr; {{ a.to_item?.title }}
                            — {{ formatDate(a.advanced_at) }}
                        </div>
                        <div v-if="a.withdrawn_at" class="text-[11px] text-red-600 mt-0.5">Withdrawn {{ formatDate(a.withdrawn_at) }}</div>
                    </div>
                    <button v-if="!a.withdrawn_at" type="button" class="text-xs text-red-600 shrink-0" @click="withdraw(a)">
                        Withdraw
                    </button>
                </li>
            </ul>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useConfirm } from '@/composables/useConfirm';
import { useBulkSelection } from '@/composables/useBulkSelection';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    fromItems: { type: Array, default: () => [] },
    toItems: { type: Array, default: () => [] },
    advancements: { type: Array, default: () => [] },
    selectedFromItemId: [String, Number],
    candidates: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const { confirm } = useConfirm();

const fromItemId = ref(props.selectedFromItemId ?? null);
const toItemId = ref(null);

function itemOptionLabel(item) {
    const codeSuffix = item.item_code ? ` (${item.item_code})` : '';
    return item.category_label ? `${item.title} — ${item.category_label}${codeSuffix}` : `${item.title}${codeSuffix}`;
}

const fromItemOptions = computed(() => props.fromItems.map(item => ({ id: item.id, name: itemOptionLabel(item) })));
const toItemOptions = computed(() => props.toItems.map(item => ({ id: item.id, name: itemOptionLabel(item) })));

const selectableCandidates = () => props.candidates.filter((c) => ! c.already_advanced);
const { selectedIds, toggle: toggleSelected, clear: clearSelection } = useBulkSelection(selectableCandidates, 'registration_id');

const advanceForm = useForm({});
const withdrawForm = useForm({});

watch(() => props.candidates, () => clearSelection());

function loadCandidates() {
    clearSelection();
    router.get(base + '/results/advancement', { from_item_id: fromItemId.value }, {
        preserveState: true,
        preserveScroll: true,
        only: ['candidates', 'selectedFromItemId'],
    });
}

async function advance() {
    const toItem = props.toItems.find((item) => item.id === toItemId.value);
    if (!(await confirm({ message: `Advance ${selectedIds.value.length} selected candidate(s) to ${toItem?.title ?? 'the target item'}? They will be registered directly into that item.`, destructive: true }))) return;
    advanceForm.transform(() => ({
        from_item_id: fromItemId.value,
        to_item_id: toItemId.value,
        registration_ids: selectedIds.value,
    })).post(base + '/results/advancement', { preserveScroll: true });
}

async function withdraw(advancement) {
    if (!(await confirm({ message: `Withdraw this advancement for ${advancement.from_registration?.school?.name || 'this school'}? Their registration in the target item will be cancelled.`, destructive: true }))) return;
    withdrawForm.post(base + `/results/advancement/${advancement.id}/withdraw`, { preserveScroll: true });
}

function formatDate(value) {
    if (!value) return '';
    return new Date(value).toLocaleString();
}
</script>
