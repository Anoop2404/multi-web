<template>
    <div class="space-y-5">
        <p class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            Save each result before auto-ranking. Equal results share the same rank. Rank-only items use the agreed table result.
        </p>
        <EmptyState v-if="!sections.length" title="No approved participants" description="Approve registrations before entering results." icon="📊" />
        <section v-for="section in sections" :key="section.key" class="card !p-0 overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b bg-slate-50 px-5 py-4">
                <div>
                    <h3 class="font-semibold text-slate-900">{{ section.item.title }}</h3>
                    <p class="mt-1 text-xs text-slate-600">{{ section.rows.length }} participants · {{ entryHint(section.item) }}</p>
                </div>
                <div v-if="canAutoRank(section.item)" class="flex flex-col items-end gap-1">
                    <button type="button" class="btn-secondary text-sm" :disabled="busy || sectionDirty(section)" @click="autoRank(section.item)">
                        {{ rankingItem === section.item.id ? 'Ranking…' : 'Auto-rank saved results' }}
                    </button>
                    <span v-if="sectionDirty(section)" class="text-xs text-amber-700">Save your changes to enable auto-rank</span>
                </div>
                <span v-else-if="rankEntryOnly(section.item)" class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800">Rank only</span>
            </header>
            <div class="overflow-x-auto">
                <table class="data-table min-w-[40rem]">
                    <thead><tr>
                        <th>Participant</th>
                        <th v-if="showMeasurement(section.item)">{{ resultMethod(section.item) === 'distance' ? 'Distance' : 'Time / measurement' }}</th>
                        <th v-if="showMeasurement(section.item)" class="w-24">Unit</th>
                        <th class="w-28">Rank</th>
                        <th v-if="showScore(section.item)" class="w-28">Score</th>
                        <th class="w-36 text-right">Save result</th>
                    </tr></thead>
                    <tbody>
                        <template v-for="{ participant: p } in section.rows" :key="p.id">
                            <tr>
                                <td class="font-medium">{{ participantName(p) }}</td>
                                <td v-if="showMeasurement(section.item)">
                                    <input v-model="forms[p.id].measurement_value" class="field" :aria-label="`Measurement for ${participantName(p)}`" :placeholder="resultMethod(section.item) === 'time' ? '12.5 or 1:02.5' : '4.2'" :disabled="busy" />
                                </td>
                                <td v-if="showMeasurement(section.item)"><input v-model="forms[p.id].measurement_unit" class="field" :aria-label="`Unit for ${participantName(p)}`" :placeholder="measurementUnitHint(section.item)" :disabled="busy" /></td>
                                <td><input v-model.number="forms[p.id].position" type="number" min="1" max="255" class="field" :aria-label="`Rank for ${participantName(p)}`" placeholder="—" :disabled="busy" /></td>
                                <td v-if="showScore(section.item)"><input v-model.number="forms[p.id].score" type="number" min="0" step="0.01" class="field" :aria-label="`Score for ${participantName(p)}`" :disabled="busy" /></td>
                                <td class="text-right">
                                    <button type="button" class="btn-primary text-sm" :disabled="busy" :aria-label="`Save result for ${participantName(p)}`" @click="save(p, section.item)">{{ savingId === p.id ? 'Saving…' : 'Save' }}</button>
                                    <p aria-live="polite" class="mt-1 text-xs" :class="dirty(p, section.item) ? 'text-amber-700' : 'text-emerald-700'">{{ dirty(p, section.item) ? 'Unsaved changes' : savedIds.has(p.id) ? 'Saved' : '' }}</p>
                                </td>
                            </tr>
                            <tr v-if="errors[p.id]"><td :colspan="6" class="!py-2 text-sm text-red-700" role="alert">{{ errors[p.id] }}</td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useFestMarkEntryDisplay } from '@/composables/useFestMarkEntryDisplay.js';
import { studentDisplayName } from '@/support/studentDisplay.js';
import { useConfirm } from '@/composables/useConfirm.js';

const props = defineProps({ school: Object, event: Object, registrations: Array, marks: Object, saveUrl: String });
// School endpoints save individual participants, including every member of a group.
const groupedProps = computed(() => ({ ...props, registrations: (props.registrations ?? []).map(reg => ({ ...reg, item: { ...reg.item, participant_type: 'individual' } })) }));
const displayProps = { get event() { return props.event; }, get registrations() { return groupedProps.value.registrations; } };
const { sections, showMeasurement, rankEntryOnly, resultMethod, measurementUnitHint, directionLabel } = useFestMarkEntryDisplay(displayProps);
const { confirm } = useConfirm();
const forms = reactive({});
const originals = reactive({});
const errors = reactive({});
const savedIds = reactive(new Set());
const savingId = ref(null);
const rankingItem = ref(null);
const busy = computed(() => savingId.value !== null || rankingItem.value !== null);

function payload(p, item) {
    const form = forms[p.id];
    return { participant_id: p.id, item_id: item.id, position: form.position || null,
        score: showScore(item) ? form.score ?? null : null,
        measurement_value: showMeasurement(item) ? form.measurement_value.trim() || null : null,
        measurement_unit: showMeasurement(item) ? form.measurement_unit.trim() || null : null };
}
watch(() => props.registrations, () => {
    for (const reg of props.registrations ?? []) for (const p of reg.participants ?? []) {
        if (forms[p.id]) continue;
        const mark = props.marks?.[p.id] ?? {};
        forms[p.id] = { position: mark.position ?? null, score: mark.score ?? null, measurement_value: mark.measurement_value ?? '', measurement_unit: mark.measurement_unit ?? '' };
        originals[p.id] = JSON.stringify(payload(p, reg.item));
    }
}, { immediate: true });

function showScore(item) { return !rankEntryOnly(item) && !['time', 'distance'].includes(resultMethod(item)) && !(props.event.level_round === 'school' && props.event.event_type !== 'sports'); }
function canAutoRank(item) { return props.event.event_type === 'sports' && !rankEntryOnly(item); }
function participantName(p) { return p.student ? studentDisplayName(p.student) : p.teacher?.name ?? 'Participant'; }
function dirty(p, item) { return JSON.stringify(payload(p, item)) !== originals[p.id]; }
function sectionDirty(section) { return section.rows.some(({ participant, item }) => dirty(participant, item)); }
function entryHint(item) { return rankEntryOnly(item) ? 'Enter ranks directly; ties are allowed' : showMeasurement(item) ? `${resultMethod(item) === 'time' ? 'Seconds or minutes:seconds' : 'Record the measured result'} · ${directionLabel(item)}` : 'Enter scores and ranks'; }
function save(p, item) {
    if (busy.value) return;
    savingId.value = p.id;
    delete errors[p.id];
    const data = payload(p, item);
    router.post(props.saveUrl, data, { preserveScroll: true,
        onSuccess: () => { originals[p.id] = JSON.stringify(data); savedIds.add(p.id); },
        onError: messages => { errors[p.id] = Object.values(messages).flat().join(' '); },
        onFinish: () => { savingId.value = null; },
    });
}
async function autoRank(item) {
    if (busy.value || !(await confirm({ message: `Rank saved results for ${item.title}? Equal results share a rank.`, destructive: false }))) return;
    rankingItem.value = item.id;
    router.post(`/school-admin/${props.school.id}/sports/my-event/${props.event.id}/items/${item.id}/auto-rank`, {}, {
        preserveScroll: true, preserveState: false, onFinish: () => { rankingItem.value = null; },
    });
}
</script>
