<template>
    <div class="space-y-5">
        <div v-if="$page.props.flash?.success" class="notice-banner notice-banner--success text-sm">
            {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash?.error || $page.props.errors?.measurement || $page.props.errors?.measurement_value" class="notice-banner notice-banner--error text-sm">
            {{ $page.props.flash?.error || $page.props.errors?.measurement || $page.props.errors?.measurement_value }}
        </div>

        <div class="rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50/80 to-indigo-50/50 p-4 text-sm text-slate-800 shadow-xs">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-2.5">
                    <span class="text-lg">📋</span>
                    <div>
                        <h4 class="font-semibold text-slate-900 text-sm">Evaluation & Measurement Instructions</h4>
                        <p class="text-xs text-slate-600 mt-0.5">Quick guide for recording accurate marks, time, distances, and ranks.</p>
                    </div>
                </div>
                <button type="button" class="text-xs text-indigo-700 hover:text-indigo-900 font-medium underline shrink-0" @click="showInstructions = !showInstructions">
                    {{ showInstructions ? 'Hide instructions ▲' : 'Show full instructions ▼' }}
                </button>
            </div>

            <!-- Quick badges (always visible) -->
            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                <span class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 border border-slate-200 text-slate-700 shadow-xs">
                    ⏱️ <strong>Sprints / Time:</strong> <code class="font-mono text-indigo-700">11.45</code> or <code class="font-mono text-indigo-700">1:02.50</code>
                </span>
                <span class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 border border-slate-200 text-slate-700 shadow-xs">
                    📏 <strong>Distance:</strong> <code class="font-mono text-indigo-700">5.45</code> (meters)
                </span>
                <span class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 border border-slate-200 text-slate-700 shadow-xs">
                    🏅 <strong>Auto-rank:</strong> Lowest time or highest distance ranks 1st
                </span>
                <span class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 border border-slate-200 text-slate-700 shadow-xs">
                    🛡️ <strong>Inter-House:</strong> Results automatically update House Standings
                </span>
            </div>

            <!-- Detailed instructions (collapsible) -->
            <div v-show="showInstructions" class="mt-3 pt-3 border-t border-blue-200/60 grid sm:grid-cols-2 gap-4 text-xs leading-relaxed text-slate-700">
                <div>
                    <h5 class="font-semibold text-slate-900 mb-1 flex items-center gap-1.5">
                        <span>⏱️</span> Timing & Distance Formats
                    </h5>
                    <ul class="list-disc pl-4 space-y-1">
                        <li><strong>Track / Sprints:</strong> Enter decimal seconds (<code class="font-mono text-indigo-600">12.45</code>), minutes & seconds (<code class="font-mono text-indigo-600">1:02.50</code>), or hours & minutes (<code class="font-mono text-indigo-600">1:05:20</code>). Natural input like <code class="font-mono text-indigo-600">1m 23s</code> is also accepted.</li>
                        <li><strong>Field / Distance:</strong> Enter distance in meters (e.g. <code class="font-mono text-indigo-600">5.45</code>). Units like <code class="font-mono text-indigo-600">m</code> or <code class="font-mono text-indigo-600">cm</code> are recognized.</li>
                        <li><strong>Live preview:</strong> An emerald preview badge will show the parsed format under the field while typing. Invalid formats show a red warning.</li>
                    </ul>
                </div>
                <div>
                    <h5 class="font-semibold text-slate-900 mb-1 flex items-center gap-1.5">
                        <span>🏅</span> Auto-Ranking & House Championship
                    </h5>
                    <ul class="list-disc pl-4 space-y-1">
                        <li><strong>Auto-rank:</strong> Click <em>"Save & Auto-rank"</em> to automatically assign 1st, 2nd, and 3rd places based on best performances. Ties share equal ranks.</li>
                        <li><strong>House Standings:</strong> Points are automatically computed and reflected in the Inter-House Championship Standings on the Overview tab.</li>
                        <li><strong>Cluster Meets:</strong> After completing school rounds, switch to the <em>"Winners"</em> tab to promote your top qualifiers to the Sahodaya cluster meet.</li>
                    </ul>
                </div>
            </div>
        </div>

        <EmptyState v-if="!sections.length" title="No approved participants" description="Approve registrations before entering results." icon="📊" />

        <section v-for="section in sections" :key="section.key" class="card !p-0 overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b bg-slate-50 px-5 py-4">
                <div>
                    <h3 class="font-semibold text-slate-900">{{ section.item.title }}</h3>
                    <p class="mt-1 text-xs text-slate-600">{{ section.rows.length }} participants · {{ entryHint(section.item) }}</p>
                    <div v-if="recordsByItemId[section.item.id]?.length" class="mt-1.5 flex items-center gap-1.5 text-xs text-amber-800 flex-wrap">
                        <span class="font-bold">⭐ Standing Record:</span>
                        <span v-for="rec in recordsByItemId[section.item.id]" :key="rec.id" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-900 font-mono text-[11px]">
                            <strong>{{ rec.record_value }} {{ rec.record_unit }}</strong>
                            <span v-if="rec.holder_name" class="font-sans text-amber-800">({{ rec.holder_name }}{{ rec.notes ? ' · ' + rec.notes : '' }})</span>
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <div v-if="canAutoRank(section.item)" class="flex items-center gap-1.5">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Rank by:</label>
                        <select :value="getAutoRankBasis(section.item.id, section.item)"
                                @change="setAutoRankBasis(section.item.id, $event.target.value)"
                                class="field !py-1 !px-2 !text-xs w-40"
                                title="Auto-rank criteria">
                            <option value="time">⏱️ Time (Faster 1st)</option>
                            <option value="distance">📏 Length / Dist (Longest 1st)</option>
                        </select>
                    </div>
                    <button v-if="sectionDirty(section)" type="button" class="btn-secondary text-xs !py-1 !px-2.5" :disabled="busy" @click="saveSection(section)">
                        {{ savingSection === section.key ? 'Saving all…' : 'Save all' }}
                    </button>
                    <button v-if="canAutoRank(section.item)" type="button" class="btn-primary text-xs !py-1 !px-2.5" :disabled="busy" @click="saveAndAutoRank(section)">
                        {{ rankingItem === section.item.id ? 'Ranking…' : sectionDirty(section) ? 'Save & Auto-rank' : 'Auto-rank saved results' }}
                    </button>
                    <span v-else-if="rankEntryOnly(section.item)" class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800">Rank only</span>
                </div>
            </header>

            <div class="overflow-x-auto">
                <table class="data-table min-w-[42rem]">
                    <thead>
                        <tr>
                            <th class="min-w-[14rem]">Participant & House</th>
                            <th v-if="showMeasurement(section.item)" class="min-w-[12rem]">{{ currentResultMethod(section.item) === 'distance' ? 'Length / Distance (m)' : 'Time (s or min:s)' }}</th>
                            <th v-if="showMeasurement(section.item)" class="w-20">Unit</th>
                            <th class="w-24">Rank</th>
                            <th v-if="showScore(section.item)" class="w-28 text-center">Score</th>
                            <th v-if="showGrade(section.item)" class="w-24 text-center">Grade</th>
                            <th class="w-36 text-right">Save result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="{ participant: p } in section.rows" :key="p.id">
                            <tr>
                                <td>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span v-if="p.chest_no" class="inline-flex items-center bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded text-xs font-mono font-bold border border-indigo-100">
                                            #{{ p.chest_no }}
                                        </span>
                                        <span class="text-slate-900 font-semibold">{{ participantName(p) }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-1 text-xs flex-wrap">
                                        <span v-if="houseOf(p)" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-medium text-[11px]" :style="houseBadgeStyle(p)">
                                            <span class="w-2 h-2 rounded-full inline-block" :style="{ backgroundColor: houseColor(p) }"></span>
                                            {{ houseOf(p) }}
                                        </span>
                                        <span v-if="classOf(p)" class="text-slate-500 font-normal">
                                            {{ classOf(p) }}
                                        </span>
                                    </div>
                                </td>
                                <td v-if="showMeasurement(section.item)">
                                    <div class="space-y-1">
                                        <input v-model="formFor(p.id).measurement_value"
                                               class="field text-sm"
                                               :class="measurementError(p.id, section.item) ? '!border-rose-400 !bg-rose-50/30' : ''"
                                               :aria-label="`Measurement for ${participantName(p)}`"
                                               :placeholder="measurementPlaceholder(section.item)"
                                               :disabled="busy" />
                                        <div v-if="measurementPreview(p.id, section.item)" class="text-[11px] font-mono leading-tight">
                                            <span v-if="measurementPreview(p.id, section.item).valid" class="text-emerald-700 font-medium">
                                                = {{ measurementPreview(p.id, section.item).formatted }}
                                            </span>
                                            <span v-else-if="formFor(p.id).measurement_value" class="text-rose-600 font-medium">
                                                {{ measurementPreview(p.id, section.item).error }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td v-if="showMeasurement(section.item)">
                                    <input v-model="formFor(p.id).measurement_unit" class="field text-sm" :aria-label="`Unit for ${participantName(p)}`" :placeholder="measurementUnitHint(section.item)" :disabled="busy" />
                                </td>
                                <td>
                                    <input v-model.number="formFor(p.id).position" type="number" min="1" max="255" class="field text-sm" :aria-label="`Rank for ${participantName(p)}`" placeholder="—" :disabled="busy" />
                                </td>
                                <td v-if="showScore(section.item)" class="text-center">
                                    <input v-model.number="formFor(p.id).score" type="number" min="0" step="0.01" class="field text-center text-sm" :aria-label="`Score for ${participantName(p)}`" :disabled="busy" />
                                </td>
                                <td v-if="showGrade(section.item)" class="text-center">
                                    <SearchableSelect v-model="formFor(p.id).grade"
                                            :options="gradeOptions.map(g => ({ value: g, label: g }))"
                                            :all-option="true" all-label="—" escape-overflow :disabled="busy" />
                                </td>
                                <td class="text-right">
                                    <button type="button" class="btn-primary text-sm" :disabled="busy" :aria-label="`Save result for ${participantName(p)}`" @click="save(p, section.item)">
                                        {{ savingId === p.id ? 'Saving…' : 'Save' }}
                                    </button>
                                    <p aria-live="polite" class="mt-1 text-xs" :class="dirty(p, section.item) ? 'text-amber-700' : 'text-emerald-700'">
                                        {{ dirty(p, section.item) ? 'Unsaved changes' : savedIds.has(p.id) ? 'Saved ✓' : '' }}
                                    </p>
                                </td>
                            </tr>
                            <tr v-if="errors[p.id]">
                                <td :colspan="colCount(section.item)" class="!py-2 text-sm text-red-700" role="alert">{{ errors[p.id] }}</td>
                            </tr>
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
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { parseTime, parseDistance } from '@/support/sportsMeasurementFormatter.js';

const props = defineProps({
    school: Object,
    event: Object,
    registrations: Array,
    marks: Object,
    saveUrl: String,
    gradeOptions: { type: Array, default: () => [] },
    athleticRecords: { type: Array, default: () => [] },
});

const showInstructions = ref(false);
const autoRankBasis = reactive({});

function getAutoRankBasis(itemId, item) {
    if (!autoRankBasis[itemId]) {
        autoRankBasis[itemId] = (resultMethod(item) === 'distance' || item?.ranking_direction === 'desc') ? 'distance' : 'time';
    }
    return autoRankBasis[itemId];
}

function setAutoRankBasis(itemId, val) {
    autoRankBasis[itemId] = val;
}

function currentResultMethod(item) {
    return autoRankBasis[item.id] || resultMethod(item) || 'time';
}

function measurementPlaceholder(item) {
    return currentResultMethod(item) === 'distance' ? '5.45 (m)' : '12.45 or 1:02.50';
}

const recordsByItemId = computed(() => {
    const map = {};
    for (const r of props.athleticRecords || []) {
        if (!map[r.item_id]) map[r.item_id] = [];
        map[r.item_id].push(r);
    }
    return map;
});

// School endpoints save individual participants, including every member of a group.
const groupedProps = computed(() => ({
    ...props,
    registrations: (props.registrations ?? []).map(reg => ({
        ...reg,
        item: { ...reg.item, participant_type: 'individual' },
    })),
}));

const displayProps = {
    get event() { return props.event; },
    get registrations() { return groupedProps.value.registrations; },
};

const { sections, showMeasurement, rankEntryOnly, resultMethod, measurementUnitHint, directionLabel } = useFestMarkEntryDisplay(displayProps);
const { confirm } = useConfirm();

const forms = reactive({});
const originals = reactive({});
const errors = reactive({});
const savedIds = reactive(new Set());
const savingId = ref(null);
const savingSection = ref(null);
const rankingItem = ref(null);

const busy = computed(() => savingId.value !== null || savingSection.value !== null || rankingItem.value !== null);

function formFor(pId) {
    if (!forms[pId]) {
        const mark = props.marks?.[pId] ?? {};
        forms[pId] = {
            position: mark.position ?? null,
            score: mark.score ?? null,
            grade: mark.grade ?? '',
            measurement_value: mark.measurement_value ?? '',
            measurement_unit: mark.measurement_unit ?? '',
        };
    }
    return forms[pId];
}

function payload(p, item) {
    const form = formFor(p.id);
    const measVal = form.measurement_value != null ? String(form.measurement_value).trim() : null;
    const measUnit = form.measurement_unit != null ? String(form.measurement_unit).trim() : null;

    return {
        participant_id: p.id,
        item_id: item.id,
        position: form.position || null,
        grade: showGrade(item) ? (form.grade || null) : null,
        score: showScore(item) ? (form.score ?? null) : null,
        measurement_value: showMeasurement(item) ? (measVal || null) : null,
        measurement_unit: showMeasurement(item) ? (measUnit || null) : null,
    };
}

watch(() => props.registrations, () => {
    for (const reg of props.registrations ?? []) {
        for (const p of reg.participants ?? []) {
            formFor(p.id);
            if (!originals[p.id]) {
                originals[p.id] = JSON.stringify(payload(p, reg.item));
            }
        }
    }
}, { immediate: true });

function colCount(item) {
    let count = 2; // Participant + Save result
    if (showMeasurement(item)) count += 2;
    count += 1; // Rank
    if (showScore(item)) count += 1;
    if (showGrade(item)) count += 1;
    return count;
}

function showGrade(item) {
    // Sports events NEVER have letter grades. Cultural events only show grades if configured.
    return props.event?.event_type !== 'sports'
        && props.gradeOptions?.length > 0
        && !rankEntryOnly(item);
}

function showScore(item) {
    return props.event?.event_type !== 'sports'
        && !rankEntryOnly(item)
        && !['time', 'distance'].includes(resultMethod(item));
}

function canAutoRank(item) {
    return props.event?.event_type === 'sports' && !rankEntryOnly(item);
}

function participantName(p) {
    return p.student ? studentDisplayName(p.student) : p.teacher?.name ?? 'Participant';
}

function houseOf(p) {
    return p.student?.school_house?.name ?? p.student?.schoolHouse?.name ?? null;
}

function houseColor(p) {
    return p.student?.school_house?.color ?? p.student?.schoolHouse?.color ?? '#4f46e5';
}

function houseBadgeStyle(p) {
    const col = houseColor(p);
    return {
        backgroundColor: `${col}15`,
        color: col,
        border: `1px solid ${col}40`,
    };
}

function classOf(p) {
    return p.student?.school_class?.name ?? p.student?.schoolClass?.name ?? null;
}

function measurementPreview(pId, item) {
    const val = forms[pId]?.measurement_value;
    if (!val) return null;
    if (val === '0' || val === '0.0' || val === '0.00') {
        return { valid: true, formatted: '0 (unranked / foul)' };
    }
    const method = currentResultMethod(item);
    if (method === 'time') {
        return parseTime(val);
    }
    if (method === 'distance') {
        const unit = forms[pId]?.measurement_unit || 'm';
        return parseDistance(val, unit);
    }
    return null;
}

function measurementError(pId, item) {
    const preview = measurementPreview(pId, item);
    return preview && preview.valid === false;
}

function dirty(p, item) {
    return JSON.stringify(payload(p, item)) !== originals[p.id];
}

function sectionDirty(section) {
    return section.rows.some(({ participant, item }) => dirty(participant, item));
}

function entryHint(item) {
    return rankEntryOnly(item)
        ? 'Enter ranks directly; ties are allowed'
        : showMeasurement(item)
            ? `${resultMethod(item) === 'time' ? 'Format: seconds (12.45) or minutes:seconds (1:02.50)' : 'Format: meters (e.g. 5.45)'} · ${directionLabel(item)}`
            : props.event.event_type === 'sports'
                ? 'Enter the measured result and rank'
                : 'Enter scores and ranks';
}

function save(p, item) {
    if (busy.value) return;
    savingId.value = p.id;
    delete errors[p.id];
    const data = payload(p, item);

    router.post(props.saveUrl, data, {
        preserveScroll: true,
        onSuccess: () => {
            originals[p.id] = JSON.stringify(data);
            savedIds.add(p.id);
        },
        onError: (messages) => {
            errors[p.id] = Object.values(messages).flat().join(' ');
        },
        onFinish: () => {
            savingId.value = null;
        },
    });
}

const bulkSaveUrl = computed(() => {
    if (props.saveUrl?.endsWith('/marks')) {
        return `${props.saveUrl}/bulk`;
    }
    return `/school-admin/${props.school.id}/sports/my-event/${props.event.id}/marks/bulk`;
});

function saveSection(section) {
    if (busy.value) return;
    savingSection.value = section.key;

    const rows = section.rows.map(({ participant, item }) => payload(participant, item));

    router.post(bulkSaveUrl.value, { rows }, {
        preserveScroll: true,
        onSuccess: () => {
            for (const { participant, item } of section.rows) {
                originals[participant.id] = JSON.stringify(payload(participant, item));
                savedIds.add(participant.id);
            }
        },
        onFinish: () => {
            savingSection.value = null;
        },
    });
}

async function saveAndAutoRank(section) {
    if (busy.value) return;

    const basis = getAutoRankBasis(section.item.id, section.item);

    if (sectionDirty(section)) {
        if (!(await confirm({ message: `Save changes and auto-rank results for ${section.item.title}? Equal results will share a rank.`, destructive: false }))) {
            return;
        }

        rankingItem.value = section.item.id;
        const rows = section.rows.map(({ participant, item }) => payload(participant, item));

        router.post(bulkSaveUrl.value, { rows, auto_rank_item_id: section.item.id, rank_basis: basis }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                for (const { participant, item } of section.rows) {
                    originals[participant.id] = JSON.stringify(payload(participant, item));
                    savedIds.add(participant.id);
                }
            },
            onFinish: () => {
                rankingItem.value = null;
            },
        });
    } else {
        if (!(await confirm({ message: `Rank saved results for ${section.item.title}? Equal results share a rank.`, destructive: false }))) {
            return;
        }

        rankingItem.value = section.item.id;
        router.post(`/school-admin/${props.school.id}/sports/my-event/${props.event.id}/items/${section.item.id}/auto-rank`, { rank_basis: basis }, {
            preserveScroll: true,
            preserveState: false,
            onFinish: () => {
                rankingItem.value = null;
            },
        });
    }
}
</script>
