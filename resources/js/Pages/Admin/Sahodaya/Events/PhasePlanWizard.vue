<template>
    <SahodayaEventsLayout title="Phase plan wizard" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">

        <PageHeader title="Phase plan wizard" eyebrow="Rounds & levels"
                    description="Define payment levels, phases, and map every item to a phase in one place — preview before committing, the same way the CLI setup command already does.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/phases`" class="btn-secondary text-xs">
                    ← Phases (one at a time)
                </Link>
            </template>
        </PageHeader>

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="phases" class="mb-4" />

        <div v-if="event.workflow_mode === 'phased_regional_billing'" class="card !p-3 mb-5 border border-emerald-200 bg-emerald-50 text-emerald-800 text-xs">
            This event's phased structure is already active. Re-running this wizard updates existing batches/phases in place (matched by code) instead of duplicating them.
        </div>

        <!-- 1. Payment levels -->
        <section class="card !p-4 mb-5 space-y-3">
            <h3 class="text-sm font-bold text-slate-900">1. Payment levels (batches)</h3>
            <div v-for="(batch, idx) in batches" :key="batch._key" class="grid grid-cols-12 gap-2 items-center">
                <input v-model="batch.code" placeholder="Code e.g. LEVEL_1" class="field col-span-3 text-xs uppercase">
                <input v-model="batch.name" placeholder="Name e.g. Level 1" class="field col-span-5 text-xs">
                <input v-model.number="batch.school_base_fee" type="number" min="0" placeholder="Base fee" class="field col-span-2 text-xs">
                <button type="button" class="col-span-2 text-rose-600 text-xs font-semibold hover:underline"
                        :disabled="batches.length <= 1" @click="batches.splice(idx, 1)">Remove</button>
            </div>
            <button type="button" class="btn-secondary text-xs" @click="addBatch">+ Add payment level</button>
        </section>

        <!-- 2. Phases -->
        <section class="card !p-4 mb-5 space-y-3">
            <h3 class="text-sm font-bold text-slate-900">2. Phases</h3>
            <div v-for="(phase, idx) in phases" :key="phase._key" class="border border-slate-100 rounded-lg p-3 space-y-2">
                <div class="grid grid-cols-12 gap-2 items-center">
                    <input v-model="phase.code" placeholder="Code e.g. L1_REGION" class="field col-span-3 text-xs uppercase">
                    <input v-model="phase.name" placeholder="Name e.g. Level 1 – Region Round" class="field col-span-4 text-xs">
                    <SearchableSelect v-model="phase.batch_code" class="col-span-3" :options="batchOptions"
                                      :all-option="true" all-label="Payment level…" />
                    <button type="button" class="col-span-2 text-rose-600 text-xs font-semibold hover:underline"
                            :disabled="phases.length <= 1" @click="phases.splice(idx, 1)">Remove</button>
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" v-model="phase.is_regional"> Regional (split by region)
                </label>
                <div v-if="phase.is_regional" class="flex flex-wrap gap-2">
                    <label v-for="r in regions" :key="r.code" class="flex items-center gap-1 text-[11px] bg-slate-50 border border-slate-200 rounded px-2 py-1">
                        <input type="checkbox" :value="r.code" v-model="phase.region_codes"> {{ r.name }}
                    </label>
                    <p v-if="!regions.length" class="text-[11px] text-slate-400">No regions configured for this Sahodaya yet.</p>
                </div>
            </div>
            <button type="button" class="btn-secondary text-xs" @click="addPhase">+ Add phase</button>
        </section>

        <!-- 3. Item assignment -->
        <section class="card !p-4 mb-5 space-y-3">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <h3 class="text-sm font-bold text-slate-900">3. Assign items to phases</h3>
                <span class="text-xs font-bold" :class="unmappedCount === 0 ? 'text-emerald-700' : 'text-amber-700'">
                    {{ mappedCount }}/{{ items.length }} items mapped
                </span>
            </div>
            <input v-model="itemSearch" type="search" placeholder="Search items by name or code…" class="field text-xs w-full max-w-sm">
            <div class="max-h-96 overflow-y-auto border border-slate-100 rounded-lg divide-y divide-slate-100">
                <div v-for="item in filteredItems" :key="item.id" class="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                    <span class="flex-1 truncate">
                        {{ item.title }}
                        <span class="text-slate-400 font-mono">{{ item.item_code || '(no code — cannot be mapped)' }}</span>
                    </span>
                    <SearchableSelect v-model="itemPhaseMap[item.item_code]" class="w-56 shrink-0" :options="phaseOptions"
                                      :all-option="true" all-label="— Unassigned —" :disabled="!item.item_code" />
                </div>
                <p v-if="!filteredItems.length" class="px-3 py-6 text-center text-slate-400">No items match your search.</p>
            </div>
        </section>

        <!-- Preview & commit -->
        <section class="card !p-4 space-y-4">
            <div class="flex items-center gap-3 flex-wrap">
                <button type="button" class="btn-secondary text-xs" :disabled="previewing" @click="runPreview">
                    {{ previewing ? 'Previewing…' : 'Preview plan' }}
                </button>
                <button type="button" class="btn-primary text-xs" :disabled="!canCommit || committing" @click="commit">
                    {{ committing ? 'Committing…' : 'Commit' }}
                </button>
                <span v-if="preview && !canCommit" class="text-xs text-amber-700 font-semibold">
                    Commit blocked — {{ preview.unmapped_items.length }} item(s) still unassigned.
                </span>
                <span v-if="previewError" class="text-xs text-rose-700 font-semibold">{{ previewError }}</span>
            </div>

            <div v-if="preview" class="space-y-4">
                <div>
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Batches</h4>
                    <table class="w-full text-xs">
                        <tr v-for="b in preview.batches" :key="b.code" class="border-b border-slate-50">
                            <td class="py-1 font-mono">{{ b.code }}</td>
                            <td class="py-1">{{ b.name }}</td>
                            <td class="py-1"><span :class="actionClass(b.action)">{{ b.action }}</span></td>
                        </tr>
                    </table>
                </div>
                <div>
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Phases</h4>
                    <table class="w-full text-xs">
                        <tr v-for="p in preview.phases" :key="p.code" class="border-b border-slate-50">
                            <td class="py-1 font-mono">{{ p.code }}</td>
                            <td class="py-1">{{ p.name }}</td>
                            <td class="py-1">{{ p.item_count }} item(s)</td>
                            <td class="py-1">{{ p.is_regional ? (p.region_codes.join(', ') || 'regional, no regions yet') : '—' }}</td>
                            <td class="py-1"><span :class="actionClass(p.action)">{{ p.action }}</span></td>
                        </tr>
                    </table>
                </div>
                <div v-if="preview.unmapped_items.length">
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-amber-700 mb-1">
                        Unmapped items ({{ preview.unmapped_items.length }})
                    </h4>
                    <p class="text-xs text-slate-500">{{ preview.unmapped_items.map(i => i.title).join(', ') }}</p>
                </div>
            </div>
        </section>
    </SahodayaEventsLayout>
</template>

<script setup>
import { reactive, ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    items: { type: Array, default: () => [] },
    regions: { type: Array, default: () => [] },
    existingBatches: { type: Array, default: () => [] },
    existingPhases: { type: Array, default: () => [] },
});

let keySeq = 0;

const batches = reactive(
    (props.existingBatches.length ? props.existingBatches : [{}]).map((b) => ({
        _key: keySeq++,
        code: b.code ?? '',
        name: b.name ?? '',
        school_base_fee: b.school_base_fee ?? null,
    })),
);

const phases = reactive(
    (props.existingPhases.length ? props.existingPhases : [{}]).map((p) => ({
        _key: keySeq++,
        code: p.code ?? '',
        name: p.name ?? '',
        batch_code: p.batch_code ?? '',
        is_regional: p.is_regional ?? false,
        region_codes: [],
    })),
);

function addBatch() {
    batches.push({ _key: keySeq++, code: '', name: '', school_base_fee: null });
}

function addPhase() {
    phases.push({ _key: keySeq++, code: '', name: '', batch_code: '', is_regional: false, region_codes: [] });
}

const batchOptions = computed(() => batches.map((b) => ({ value: b.code, label: b.name || b.code })));
const phaseOptions = computed(() => phases.filter((p) => p.code).map((p) => ({ value: p.code, label: p.name || p.code })));

const itemPhaseMap = reactive({});
const itemSearch = ref('');

const filteredItems = computed(() => {
    const q = itemSearch.value.trim().toLowerCase();
    if (!q) return props.items;
    return props.items.filter((i) => String(i.title ?? '').toLowerCase().includes(q)
        || String(i.item_code ?? '').toLowerCase().includes(q));
});

const mappedCount = computed(() => props.items.filter((i) => i.item_code && itemPhaseMap[i.item_code]).length);
const unmappedCount = computed(() => props.items.length - mappedCount.value);

function csrf() {
    return document.querySelector('meta[name=csrf-token]')?.content ?? '';
}

function buildPayload() {
    return {
        batches: batches.map((b) => ({ code: b.code, name: b.name, school_base_fee: b.school_base_fee })),
        phases: phases.map((p) => ({
            code: p.code,
            name: p.name,
            batch_code: p.batch_code,
            is_regional: p.is_regional,
            region_codes: p.is_regional ? p.region_codes : [],
        })),
        item_phase_map: Object.fromEntries(Object.entries(itemPhaseMap).filter(([, v]) => v)),
    };
}

const preview = ref(null);
const previewing = ref(false);
const previewError = ref('');
const committing = ref(false);

async function runPreview() {
    previewing.value = true;
    previewError.value = '';
    preview.value = null;
    try {
        const res = await fetch(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/phase-plan-wizard/preview`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(buildPayload()),
        });
        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            previewError.value = body.message || `Preview failed (HTTP ${res.status}).`;
            return;
        }
        preview.value = await res.json();
    } catch {
        previewError.value = 'Preview failed — check your connection and try again.';
    } finally {
        previewing.value = false;
    }
}

const canCommit = computed(() => preview.value !== null && preview.value.unmapped_items.length === 0);

function commit() {
    if (!canCommit.value) return;
    committing.value = true;
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/phase-plan-wizard/commit`,
        buildPayload(),
        { onFinish: () => { committing.value = false; } },
    );
}

function actionClass(action) {
    return {
        create: 'text-emerald-700 font-semibold uppercase text-[10px]',
        update: 'text-amber-700 font-semibold uppercase text-[10px]',
        unchanged: 'text-slate-400 uppercase text-[10px]',
    }[action] ?? '';
}
</script>
