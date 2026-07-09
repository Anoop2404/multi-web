<template>
    <div class="space-y-4">
        <!-- Event-registered athletes -->
        <section v-if="eventRegisteredAthletes.length" class="rounded-xl border border-indigo-100 bg-indigo-50/40 overflow-hidden">
            <div class="px-4 py-2.5 border-b border-indigo-100 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h4 class="text-sm font-bold text-indigo-950">Event athletes</h4>
                    <p class="text-xs text-indigo-800/80">
                        {{ eventRegisteredAthletes.length }} registered for this fest — pick them for items below.
                    </p>
                </div>
                <a v-if="eventAthletesHref" :href="eventAthletesHref" class="text-xs font-semibold text-indigo-700 hover:underline">
                    Manage event registration →
                </a>
            </div>
            <div class="px-4 py-2 flex flex-wrap gap-1.5 bg-white/70">
                <span v-for="athlete in eventRegisteredAthletes" :key="athlete.id"
                      class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full bg-white border border-indigo-100 text-indigo-900">
                    <span class="font-mono font-semibold text-indigo-700">{{ athlete.event_registration_number || '—' }}</span>
                    <span>{{ athlete.name }}</span>
                </span>
            </div>
        </section>
        <p v-else
           class="text-sm text-indigo-800 bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3">
            Pick students directly under each item head. Event registration number will be created automatically when you register an item.
        </p>

        <div class="rounded-xl border border-emerald-200 bg-emerald-50/30 overflow-hidden">
            <div class="px-4 py-3 border-b border-emerald-100 bg-white/80">
                <h4 class="text-sm font-bold text-emerald-950">
                    {{ activeHead?.head_name ?? 'Register for events' }}
                </h4>
                <p class="text-xs text-emerald-900/80 mt-0.5">
                    <template v-if="!headRegistrationOpen">Registration window closed — view only.</template>
                    <template v-else>Pick athletes for each item below.</template>
                </p>
            </div>

            <template v-if="!sportsGroups.length">
                <p class="text-sm text-slate-400 py-8 text-center">No items in this event yet.</p>
            </template>
            <template v-else>
                <div class="flex flex-wrap gap-2 items-center sticky top-0 z-10 px-4 py-2 bg-white/95 backdrop-blur border-b border-emerald-100">
                    <input v-model="sportsSearch" type="search" class="field flex-1 min-w-[10rem] !py-1.5 text-sm"
                           placeholder="Search items…" autocomplete="off">
                    <select v-if="itemOptions.length" v-model="itemFilter" class="field text-xs !py-1.5 max-w-[12rem]">
                        <option value="">All items</option>
                        <option v-for="it in itemOptions" :key="it.id" :value="it.id">{{ it.title }}</option>
                    </select>
                    <div class="flex flex-wrap gap-1">
                        <button type="button" class="text-xs px-2.5 py-1 rounded-full border transition-colors"
                                :class="!ageFilter ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200'"
                                @click="ageFilter = ''">All</button>
                        <button v-for="g in sportsGroups" :key="g.key" type="button"
                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                :class="ageFilter === g.key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200'"
                                @click="ageFilter = g.key">
                            {{ g.label }}
                            <span v-if="g.openCount" class="font-semibold">· {{ g.openCount }} open</span>
                            <span v-if="g.registeredCount" class="opacity-75">· {{ g.registeredCount }} done</span>
                        </button>
                    </div>
                    <button v-if="hasActiveFilters" type="button" class="btn-secondary text-xs !py-1" @click="clearFilters">Clear</button>
                    <p v-if="registrationSummary" class="w-full sm:w-auto sm:ml-auto text-[11px] text-slate-500">{{ registrationSummary }}</p>
                </div>

                <p v-if="!filteredGroups.length" class="text-sm text-slate-400 py-6 text-center">No events match your filters.</p>

                <div v-for="group in filteredGroups" :key="group.key"
                     class="mx-4 mb-4 last:mb-4 rounded-xl border border-slate-200/80 overflow-hidden shadow-sm bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 bg-slate-50 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-sm text-slate-800">{{ group.label }}</span>
                            <span v-if="group.headName"
                                  class="text-[10px] font-bold uppercase tracking-wide text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100">
                                {{ group.headName }}
                            </span>
                            <span class="text-[11px] text-slate-400 bg-white px-1.5 py-0.5 rounded border">{{ groupVisibleItemCount(group) }} events</span>
                        </div>
                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
                            <span>{{ group.eligibleCount }} eligible athletes</span>
                            <span v-if="group.openCount" class="text-indigo-700 font-semibold">{{ group.openCount }} open</span>
                            <span v-if="group.registeredCount" class="text-emerald-700 font-semibold">{{ group.registeredCount }} registered</span>
                        </div>
                    </div>
                    <div v-for="(genderGroup, gi) in group.genderGroups" :key="gi">
                        <div v-if="genderGroup.label" class="px-4 py-1 bg-white border-b border-slate-50">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ genderGroup.label }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="data-table w-full text-sm">
                                <thead>
                                    <tr>
                                        <th class="min-w-[140px]">Event</th>
                                        <th v-if="event.fee_required" class="w-20">Fee</th>
                                        <th class="min-w-[120px]">Registered</th>
                                        <th class="text-right min-w-[220px]">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <FestRegistrationItemRow
                                        v-for="item in genderGroup.items"
                                        :key="item.id"
                                        layout="sports"
                                        :row-id="itemRowId(item.id)"
                                        :item="item"
                                        :form="itemForms[itemFormKey(item.id)]"
                                        :registrations="registrationsForItem(item.id)"
                                        :eligible-students="eligibleStudentsForItem(item)"
                                        :all-students="students"
                                        :student-ineligibility-reason="(student) => studentIneligibilityReason(student, item)"
                                        :show-fee="event.fee_required"
                                        :blocked="isItemBlocked(item)"
                                        :block-reason="itemBlockReason(item)"
                                        :error-message="itemErrors[itemFormKey(item.id)]"
                                        :status-label="itemStatusMeta(item).label"
                                        :status-class="itemStatusMeta(item).badgeClass"
                                        :status-hint="itemStatusMeta(item).hint"
                                        performer-label="athletes"
                                        :is-teacher-fest="false"
                                        event-type="sports"
                                        :teachers="[]"
                                        :student-label="studentOptionLabel"
                                        :registered-names="registeredNames"
                                        :can-withdraw="canWithdraw"
                                        @register="submitItem(item)"
                                        @withdraw="withdraw"
                                        @add-student="$emit('add-student')"
                                    />
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div v-if="event.fee_required && event.school_fee" class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 space-y-2">
            <p class="text-xs font-semibold text-slate-800">Event fees & billing</p>
            <p class="text-xs text-indigo-900 font-semibold">
                Item fees due: ₹{{ formatMoney(itemFeesDue) }}
            </p>
            <a :href="`${programBase}/reports/${event.id}/fee-summary`" class="text-xs link-brand font-semibold">Fee report →</a>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import FestRegistrationItemRow from '@/Components/school/FestRegistrationItemRow.vue';
import { genderLabel } from '@/support/festItemEligibility.js';

const props = defineProps({
    event: { type: Object, required: true },
    students: { type: Array, default: () => [] },
    registrations: { type: Array, default: () => [] },
    programBase: { type: String, required: true },
    eventAthletesHref: { type: String, default: '' },
    initialHeadId: { type: [Number, String], default: null },
    selectedHeadId: { type: [Number, String], default: null },
    headRegistrationOpen: { type: Boolean, default: true },
    activeHead: { type: Object, default: null },
});

defineEmits(['add-student']);

const SPORTS_AGE_ORDER = ['u8', 'u10', 'u11', 'u12', 'u14', 'u17', 'u19', 'open'];
const SPORTS_MALE_VALS = new Set(['male', 'm', 'boys', 'boy']);
const SPORTS_FEMALE_VALS = new Set(['female', 'f', 'girls', 'girl']);

const sportsSearch = ref('');
const ageFilter = ref('');
const itemFilter = ref('');
const itemForms = reactive({});
const itemErrors = reactive({});
const page = usePage();

const headOptions = computed(() => props.event.head_navigation?.headsForFilter ?? []);

const activeHeadId = computed(() =>
    props.selectedHeadId ?? props.initialHeadId ?? headOptions.value[0]?.id ?? null,
);

const eventRegisteredAthletes = computed(() =>
    (props.students ?? []).filter((s) => s.event_registered || s.event_registration_number),
);

const headNameById = computed(() =>
    Object.fromEntries(headOptions.value.map((h) => [Number(h.id), h.name])),
);

const eventRegisteredIds = computed(() => {
    const ids = new Set();
    for (const s of props.students ?? []) {
        if (s.event_registered || s.event_registration_number) ids.add(Number(s.id));
    }
    for (const r of props.event.event_registrations ?? []) {
        ids.add(Number(r.student_id));
    }
    return ids;
});

function initForms() {
    for (const item of props.event.items ?? []) {
        itemForms[itemFormKey(item.id)] = { team_name: '', student_ids: [], teacher_ids: [], standby_ids: [] };
    }
}
initForms();

watch(() => props.event.items, () => initForms(), { deep: true });

function itemFormKey(itemId) {
    return `${props.event.id}-${itemId}`;
}

function itemRowId(itemId) {
    return `reg-item-${itemFormKey(itemId)}`;
}

function selectHead(headId) {
    if (Number(headId) === Number(activeHeadId.value)) return;
    itemFilter.value = '';
    ageFilter.value = '';
    sportsSearch.value = '';
    router.get(`${props.programBase}/events/${props.event.id}/items`, { head: headId }, {
        preserveScroll: true,
        only: ['event', 'registrations', 'selectedHeadId', 'initialHeadId'],
    });
}

function filteredItems() {
    let items = props.event.items ?? [];
    if (itemFilter.value) {
        items = items.filter((i) => Number(i.id) === Number(itemFilter.value));
    }
    const q = sportsSearch.value.trim().toLowerCase();
    if (q) {
        items = items.filter((i) => String(i.title ?? '').toLowerCase().includes(q));
    }
    return items;
}

const itemOptions = computed(() => props.event.items ?? []);

const sportsGroups = computed(() => {
    const items = filteredItems();
    const labels = props.event.item_group_labels ?? {};
    const byAge = {};
    for (const item of items) {
        const key = item.age_group || 'open';
        if (!byAge[key]) byAge[key] = [];
        byAge[key].push(item);
    }
    return Object.keys(byAge)
        .filter((key) => byAge[key]?.length)
        .sort((a, b) => {
            const ai = SPORTS_AGE_ORDER.indexOf(a.toLowerCase());
            const bi = SPORTS_AGE_ORDER.indexOf(b.toLowerCase());
            return (ai < 0 ? 99 : ai) - (bi < 0 ? 99 : bi);
        })
        .map((key) => {
            const groupItems = byAge[key] ?? [];
            const headName = activeHeadId.value ? (headNameById.value[Number(activeHeadId.value)] ?? null) : null;
            const label = labels[key] ?? String(key).toUpperCase();
            const itemIds = new Set(groupItems.map((i) => Number(i.id)));
            const eligiblePool = props.students ?? [];
            const eligibleCount = eligiblePool.filter(
                (s) => (s.eligible_sports_groups ?? []).map((g) => g.toLowerCase()).includes(key.toLowerCase()),
            ).length;
            const registeredCount = (props.registrations ?? []).filter(
                (r) => itemIds.has(Number(r.item_id)) && !['withdrawn', 'rejected'].includes(r.status),
            ).length;
            let openCount = 0;
            for (const item of groupItems) {
                const st = itemRegistrationStatus(item);
                if (st === 'open' || st === 'partial') openCount++;
            }
            const maleItems = groupItems.filter((i) => SPORTS_MALE_VALS.has(String(i.gender ?? '').toLowerCase()));
            const femaleItems = groupItems.filter((i) => SPORTS_FEMALE_VALS.has(String(i.gender ?? '').toLowerCase()));
            const openItems = groupItems.filter(
                (i) => !SPORTS_MALE_VALS.has(String(i.gender ?? '').toLowerCase())
                    && !SPORTS_FEMALE_VALS.has(String(i.gender ?? '').toLowerCase()),
            );
            const hasBoth = maleItems.length > 0 && femaleItems.length > 0;
            const genderGroups = [];
            if (maleItems.length) genderGroups.push({ label: hasBoth ? 'Boys' : '', items: maleItems });
            if (femaleItems.length) genderGroups.push({ label: hasBoth ? 'Girls' : '', items: femaleItems });
            if (openItems.length) genderGroups.push({ label: hasBoth ? 'Open / Mixed' : '', items: openItems });
            if (!genderGroups.length) genderGroups.push({ label: '', items: groupItems });
            return { key, label, headName, eligibleCount, registeredCount, openCount, genderGroups };
        });
});

const filteredGroups = computed(() => {
    if (!ageFilter.value) return sportsGroups.value;
    return sportsGroups.value.filter((g) => g.key === ageFilter.value);
});

const hasActiveFilters = computed(() =>
    Boolean(sportsSearch.value.trim() || ageFilter.value || itemFilter.value),
);

const registrationSummary = computed(() => {
    let open = 0;
    let registered = 0;
    let total = 0;
    for (const group of filteredGroups.value) {
        for (const gg of group.genderGroups) {
            for (const item of gg.items) {
                total++;
                const st = itemRegistrationStatus(item);
                if (st === 'open' || st === 'partial') open++;
                else if (st === 'registered') registered++;
            }
        }
    }
    if (!total) return '';
    const parts = [`${total} event${total === 1 ? '' : 's'}`];
    if (open) parts.push(`${open} open`);
    if (registered) parts.push(`${registered} registered`);
    return parts.join(' · ');
});

const itemFeeLines = computed(() =>
    (props.event.school_fee?.breakdown?.items ?? []).filter(
        (line) => !String(line.label).toLowerCase().includes('school registration'),
    ),
);
const itemFeesDue = computed(() => itemFeeLines.value.reduce((sum, line) => sum + Number(line.amount || 0), 0));

function clearFilters() {
    sportsSearch.value = '';
    ageFilter.value = '';
    itemFilter.value = '';
}

function groupVisibleItemCount(group) {
    return group.genderGroups.reduce((n, gg) => n + gg.items.length, 0);
}

function formatMoney(value) {
    const n = Number(value);
    return Number.isNaN(n) ? '0.00' : n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function registrationsForItem(itemId) {
    return (props.registrations ?? []).filter(
        (r) => Number(r.item_id) === Number(itemId) && !['withdrawn', 'rejected'].includes(r.status),
    );
}

function registeredNames(reg) {
    const labels = (reg.participants ?? [])
        .filter((p) => p.participant_role !== 'standby')
        .map((p) => {
            const name = p.student?.name;
            const festId = p.level_registration_number;
            const regNo = p.student?.reg_no;
            if (name && festId) return `${name} (${festId})`;
            if (name && regNo) return `${name} (${regNo})`;
            return name ?? regNo;
        })
        .filter(Boolean);
    return labels.length ? labels.join(', ') : 'Registered';
}

function studentMatchesItem(student, item) {
    if (props.event?.require_verified_students !== false && student.is_verified === false) return false;
    if (!student.dob) return false;
    if (item.age_group && item.age_group !== 'open') {
        if (!(student.eligible_sports_groups ?? []).includes(item.age_group)) return false;
    }
    if (item.gender && !['open', 'mixed'].includes(item.gender) && student.gender && student.gender !== item.gender) {
        return false;
    }
    return true;
}

function eligibleStudentsForItem(item) {
    return (props.students ?? []).filter((s) => studentMatchesItem(s, item));
}

function studentIneligibilityReason(student, item) {
    if (props.event?.require_verified_students !== false && student.is_verified === false) {
        return 'Pending Sahodaya verification';
    }
    if (!student.dob) return 'Date of birth required';
    const itemGender = String(item.gender ?? 'open').toLowerCase();
    const studentGender = String(student.gender ?? '').toLowerCase();
    if (!['open', 'mixed'].includes(itemGender)) {
        if (!studentGender) return 'Set gender on student profile';
        if (studentGender !== itemGender) return `For ${genderLabel(itemGender) ?? itemGender} only`;
    }
    if (item.age_group && item.age_group !== 'open') {
        if (!(student.eligible_sports_groups ?? []).includes(item.age_group)) {
            return `Not eligible for ${String(item.age_group).toUpperCase()}`;
        }
    }
    return 'Not eligible for this item';
}

function studentOptionLabel(student) {
    const parts = [];
    if (student.event_registration_number) {
        parts.push(`Fest ID ${student.event_registration_number}`);
    } else if (student.event_registered) {
        parts.push('Event registered');
    }
    if (student.reg_no) parts.push(student.reg_no);
    parts.push(student.class_name || 'no class');
    if (student.sports_age_on_cutoff != null) parts.push(`age ${student.sports_age_on_cutoff}`);
    const g = genderLabel(student.gender);
    if (g) parts.push(g);
    return parts.join(' · ');
}

function itemRegistrationCount(itemId) {
    return registrationsForItem(itemId).length;
}

function itemMaxPerSchool(item) {
    const max = Number(item.max_per_school ?? 1);
    return max > 0 ? max : 1;
}

function isItemFull(item) {
    return itemRegistrationCount(item.id) >= itemMaxPerSchool(item);
}

function itemBlockReason(item) {
    if (!props.headRegistrationOpen) {
        return 'Registration is closed for this item head.';
    }
    if (item.registration_open === false) {
        if (item.reg_start && new Date(`${item.reg_start}T12:00:00`) > new Date()) {
            return `Registration opens ${item.reg_start}.`;
        }
        if (item.reg_end) {
            return `Registration closed on ${item.reg_end}.`;
        }
        return 'Registration is closed for this item.';
    }
    if (isItemFull(item)) {
        return itemMaxPerSchool(item) === 1
            ? 'Your school already has an entry for this item (max 1 per school).'
            : `Maximum ${itemMaxPerSchool(item)} entries per school — limit reached.`;
    }
    return '';
}

function isItemBlocked(item) {
    return Boolean(itemBlockReason(item));
}

function itemEligibleCount(item) {
    return eligibleStudentsForItem(item).length;
}

function itemRegistrationStatus(item) {
    const regs = itemRegistrationCount(item.id);
    const max = itemMaxPerSchool(item);
    if (isItemFull(item)) return regs > 0 ? 'registered' : 'full';
    if (itemEligibleCount(item) === 0) return 'no_eligible';
    if (regs > 0 && max > 1) return 'partial';
    return 'open';
}

function itemStatusMeta(item) {
    const status = itemRegistrationStatus(item);
    const eligible = itemEligibleCount(item);
    const regs = itemRegistrationCount(item.id);
    const max = itemMaxPerSchool(item);
    if (status === 'registered') {
        return { label: 'Registered', badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-100', hint: 'Entry submitted' };
    }
    if (status === 'full') {
        return { label: 'Full', badgeClass: 'bg-amber-50 text-amber-800 border-amber-100', hint: '' };
    }
    if (status === 'no_eligible') {
        return { label: 'No match', badgeClass: 'bg-slate-100 text-slate-600 border-slate-200', hint: 'No athletes match age/gender' };
    }
    return { label: 'Open', badgeClass: 'bg-indigo-50 text-indigo-700 border-indigo-100', hint: `${eligible} athlete${eligible === 1 ? '' : 's'} eligible` };
}

function canWithdraw(reg) {
    if (!props.headRegistrationOpen) return false;
    if (['withdrawn', 'rejected'].includes(reg.status)) return false;
    if (props.event.results_published || ['completed', 'cancelled'].includes(props.event.status)) return false;
    return props.event.status === 'registration_open' || reg.status === 'submitted';
}

function withdraw(id) {
    if (!confirm('Cancel this registration?')) return;
    router.post(`${props.programBase}/registrations/${id}/withdraw`, {}, { preserveScroll: true });
}

function submitItem(item) {
    const key = itemFormKey(item.id);
    const form = itemForms[key];
    delete itemErrors[key];
    if (isItemBlocked(item)) {
        itemErrors[key] = itemBlockReason(item);
        return;
    }
    if (!['group', 'team'].includes(item.participant_type) && (form.student_ids?.length ?? 0) > 1) {
        itemErrors[key] = 'This item allows only one participant.';
        return;
    }
    router.post(`${props.programBase}/register`, {
        event_id: props.event.id,
        item_id: item.id,
        team_name: form.team_name,
        student_ids: form.student_ids,
        teacher_ids: [],
        standby_ids: (form.standby_ids ?? []).slice(0, 2),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            delete itemErrors[key];
            form.student_ids = [];
            form.standby_ids = [];
            form.team_name = '';
            router.reload({ only: ['event', 'students', 'registrations'] });
        },
        onError: (errors) => {
            itemErrors[key] = errors[`items.${item.id}`] || errors.registration || 'Could not register.';
        },
    });
}
</script>
