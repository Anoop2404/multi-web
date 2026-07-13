<template>
    <section class="rounded-xl border border-indigo-100 bg-indigo-50/40 overflow-hidden mb-4">
        <div class="px-4 py-3 border-b border-indigo-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h4 class="text-sm font-bold text-indigo-950">Event athletes</h4>
                <p class="text-xs text-indigo-800/80 mt-0.5">
                    Register students for <strong>{{ event.title }}</strong> first — then assign them to items below.
                    <span v-if="studentEventRegFee > 0" class="block mt-1">
                        Event registration fee: <strong>₹{{ formatMoney(studentEventRegFee) }}</strong> per student (included in billing below).
                    </span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <a v-if="reportsHref" :href="reportsHref" class="btn-secondary text-xs !min-h-0">Event reports →</a>
                <a v-if="itemsUrl && registeredCount" :href="itemsUrl" class="btn-secondary text-xs !min-h-0">
                    Step 2 · Register by Event Head →
                </a>
                <button type="button" class="btn-primary text-xs !min-h-0"
                        :disabled="!selectedIds.length || form.processing"
                        @click="submit">
                    {{ form.processing ? 'Registering…' : `Register ${selectedIds.length || ''} for event`.trim() }}
                </button>
            </div>
        </div>

        <div class="px-4 py-2 border-b border-indigo-50 flex flex-wrap gap-2 items-center bg-white/60">
            <input v-model="search" type="search" class="field flex-1 min-w-[10rem] !py-1.5 text-sm"
                   placeholder="Search students…" autocomplete="off">
            <select v-if="classOptions.length" v-model="classFilter"
                    class="field text-xs !py-1.5 min-w-[7rem] max-w-[10rem]">
                <option value="">All classes</option>
                <option v-for="cls in classOptions" :key="cls" :value="cls">Class {{ cls }}</option>
            </select>
            <select v-if="ageGroupOptions.length" v-model="ageFilter"
                    class="field text-xs !py-1.5 min-w-[9rem] max-w-[14rem]">
                <option value="">All age categories</option>
                <option v-for="group in ageGroupOptions" :key="group.key" :value="group.key">
                    {{ group.label }}
                </option>
            </select>
            <button v-if="hasActiveFilters" type="button" class="btn-ghost text-xs !py-1.5" @click="clearFilters">
                Clear
            </button>
            <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 ml-auto sm:ml-0">
                <input v-model="showUnregisteredOnly" type="checkbox" class="rounded border-slate-300">
                Not registered yet
            </label>
        </div>
        <p v-if="hasActiveFilters && visibleRows.length !== rows.length"
           class="px-4 py-1.5 text-[11px] text-slate-500 bg-white/40 border-b border-indigo-50">
            Showing {{ visibleRows.length }} of {{ rows.length }} students
        </p>

        <div class="overflow-x-auto max-h-64 overflow-y-auto bg-white">
            <table class="data-table w-full text-sm">
                <thead class="sticky top-0 bg-slate-50 z-[1]">
                    <tr>
                        <th class="w-10">
                            <input ref="selectAllRef" type="checkbox"
                                   :checked="allVisibleSelected"
                                   @change="toggleAllVisible">
                        </th>
                        <th>Student</th>
                        <th class="w-24">Class</th>
                        <th class="w-28">Age cat.</th>
                        <th class="w-28">Event ID</th>
                        <th class="w-32">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in visibleRows" :key="row.id" class="hover:bg-slate-50/80">
                        <td>
                            <input v-if="!row.registered && row.hasDob && (row.isVerified || !requireVerified)" type="checkbox"
                                   :checked="isSelected(row.id)"
                                   @change="toggleRow(row.id, $event)">
                            <span v-else-if="row.registered" class="text-emerald-600 text-xs">✓</span>
                            <span v-else class="text-slate-300 text-xs">—</span>
                        </td>
                        <td class="font-medium">
                            <span class="font-mono text-xs text-indigo-800 mr-1.5">{{ row.reg_no || '—' }}</span>
                            {{ row.name }}
                            <span v-if="row.registered && row.event_reg_number"
                                  class="ml-1.5 text-[10px] font-bold uppercase text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100">
                                Fest ID {{ row.event_reg_number }}
                            </span>
                        </td>
                        <td class="text-xs text-slate-500">{{ row.class_name || '—' }}</td>
                        <td class="text-xs text-slate-500">{{ row.age_label || '—' }}</td>
                        <td class="font-mono text-xs">{{ row.event_reg_number || '—' }}</td>
                        <td>
                            <span v-if="row.registered"
                                  class="text-[10px] font-bold uppercase text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">
                                Registered
                            </span>
                            <span v-else-if="!row.hasDob"
                                  class="text-[10px] text-amber-700" title="Add date of birth on student profile">
                                DOB required
                            </span>
                            <span v-else-if="requireVerified && !row.isVerified"
                                  class="text-[10px] text-amber-700" title="Sahodaya must verify this student before event registration">
                                Verification required
                            </span>
                            <span v-else class="text-[10px] text-slate-400">Ready</span>
                        </td>
                    </tr>
                    <tr v-if="!visibleRows.length">
                        <td colspan="6" class="text-center text-sm text-slate-400 py-6">No students match.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="registeredCount" class="px-4 py-2 text-xs text-slate-600 border-t border-indigo-50 bg-white/60">
            <strong>{{ registeredCount }}</strong> athlete{{ registeredCount === 1 ? '' : 's' }} registered for this event.
        </p>
        <p v-if="requireVerified && unregisteredVisibleCount && selectableVisibleCount < unregisteredVisibleCount"
           class="px-4 py-2 text-xs text-amber-800 border-t border-indigo-50 bg-amber-50/60">
            {{ unregisteredVisibleCount - selectableVisibleCount }} student(s) need a date of birth and/or Sahodaya verification before they can be registered.
        </p>
    </section>
</template>

<script setup>
import { computed, ref, watch, watchEffect } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: { type: Object, required: true },
    students: { type: Array, default: () => [] },
    eventRegistrations: { type: Array, default: () => [] },
    registerUrl: { type: String, required: true },
    itemsUrl: { type: String, default: '' },
    reportsHref: { type: String, default: '' },
    studentEventRegFee: { type: Number, default: 0 },
    schoolClasses: { type: Array, default: () => [] },
});

const SPORTS_AGE_ORDER = ['u8', 'u10', 'u11', 'u12', 'u14', 'u17', 'u19', 'open'];

const search = ref('');
const classFilter = ref('');
const ageFilter = ref('');
const showUnregisteredOnly = ref(false);
const selectedIds = ref([]);
const selectAllRef = ref(null);
const form = useForm({ student_ids: [] });

const requireVerified = computed(() => props.event?.require_verified_students !== false);

function normalizeId(id) {
    return Number(id);
}

function isSelected(id) {
    const n = normalizeId(id);
    return selectedIds.value.some((sid) => normalizeId(sid) === n);
}

const regByStudent = computed(() => {
    const map = {};
    for (const r of props.eventRegistrations ?? []) {
        map[normalizeId(r.student_id)] = r;
    }
    return map;
});

const ageGroupLabels = computed(() => props.event?.item_group_labels ?? {});

const classOptions = computed(() => {
    const fromSchool = (props.schoolClasses ?? [])
        .map((c) => c.name)
        .filter(Boolean);
    const fromRows = [...new Set((props.students ?? []).map((s) => s.class_name).filter(Boolean))];
    const names = fromSchool.length ? fromSchool : fromRows;

    return [...names].sort((a, b) =>
        String(a).localeCompare(String(b), undefined, { numeric: true }),
    );
});

const ageGroupOptions = computed(() => {
    const keys = new Set();
    for (const student of props.students ?? []) {
        for (const group of student.eligible_sports_groups ?? []) {
            if (group) keys.add(String(group).toLowerCase());
        }
        if (student.sports_age_group) {
            keys.add(String(student.sports_age_group).toLowerCase());
        }
    }

    return [...keys]
        .sort((a, b) => {
            const ai = SPORTS_AGE_ORDER.indexOf(a);
            const bi = SPORTS_AGE_ORDER.indexOf(b);
            return (ai < 0 ? 99 : ai) - (bi < 0 ? 99 : bi);
        })
        .map((key) => ({
            key,
            label: ageGroupLabels.value[key] ?? key.toUpperCase(),
        }));
});

function ageLabelForStudent(student) {
    const primary = student.sports_age_group
        ? String(student.sports_age_group).toLowerCase()
        : null;
    if (primary) {
        return ageGroupLabels.value[primary] ?? primary.toUpperCase();
    }
    const groups = (student.eligible_sports_groups ?? []).map((g) => String(g).toLowerCase());
    if (!groups.length) return null;
    const first = groups.sort((a, b) => {
        const ai = SPORTS_AGE_ORDER.indexOf(a);
        const bi = SPORTS_AGE_ORDER.indexOf(b);
        return (ai < 0 ? 99 : ai) - (bi < 0 ? 99 : bi);
    })[0];
    return ageGroupLabels.value[first] ?? first.toUpperCase();
}

function studentMatchesAgeFilter(student, filterKey) {
    if (!filterKey) return true;
    const key = String(filterKey).toLowerCase();
    const eligible = (student.eligible_sports_groups ?? []).map((g) => String(g).toLowerCase());
    return eligible.includes(key)
        || String(student.sports_age_group ?? '').toLowerCase() === key;
}

const rows = computed(() => (props.students ?? []).map((s) => {
    const id = normalizeId(s.id);
    const reg = regByStudent.value[id];
    const eventRegNumber = reg?.registration_number ?? s.event_registration_number ?? null;
    const registered = Boolean(reg || s.event_registered || eventRegNumber);
    const hasDob = !!s.dob;
    const needsVerification = requireVerified.value && s.is_verified === false;
    return {
        id,
        name: s.name,
        reg_no: s.reg_no,
        class_name: s.class_name ?? s.school_class?.name,
        age_label: ageLabelForStudent(s),
        eligible_sports_groups: s.eligible_sports_groups ?? [],
        sports_age_group: s.sports_age_group ?? null,
        registered,
        event_reg_number: eventRegNumber,
        hasDob,
        isVerified: s.is_verified !== false,
        ineligible_reason: !hasDob ? 'DOB required' : (needsVerification ? 'Verification required' : null),
    };
}));

const visibleRows = computed(() => {
    const q = search.value.trim().toLowerCase();
    return rows.value.filter((row) => {
        if (showUnregisteredOnly.value && row.registered) return false;
        if (classFilter.value && row.class_name !== classFilter.value) return false;
        if (ageFilter.value && !studentMatchesAgeFilter(row, ageFilter.value)) return false;
        if (!q) return true;
        return String(row.name ?? '').toLowerCase().includes(q)
            || String(row.reg_no ?? '').toLowerCase().includes(q)
            || String(row.class_name ?? '').toLowerCase().includes(q);
    });
});

const hasActiveFilters = computed(() =>
    !!search.value.trim()
    || !!classFilter.value
    || !!ageFilter.value
    || showUnregisteredOnly.value,
);

function clearFilters() {
    search.value = '';
    classFilter.value = '';
    ageFilter.value = '';
    showUnregisteredOnly.value = false;
}

const registeredCount = computed(() => rows.value.filter((r) => r.registered).length);

const unregisteredVisible = computed(() => visibleRows.value.filter((r) => !r.registered));

const unregisteredVisibleCount = computed(() => unregisteredVisible.value.length);

/** Unregistered students with DOB (and verification when required) — eligible for event registration. */
const selectableVisible = computed(() => unregisteredVisible.value.filter((r) => {
    if (!r.hasDob) return false;
    if (requireVerified.value && !r.isVerified) return false;
    return true;
}));

const selectableVisibleCount = computed(() => selectableVisible.value.length);

const allVisibleSelected = computed(() =>
    selectableVisible.value.length > 0
    && selectableVisible.value.every((r) => isSelected(r.id)),
);

const someVisibleSelected = computed(() =>
    selectableVisible.value.some((r) => isSelected(r.id)) && !allVisibleSelected.value,
);

watchEffect(() => {
    const el = selectAllRef.value;
    if (el) {
        el.indeterminate = someVisibleSelected.value;
    }
});

watch(() => props.event.id, () => {
    selectedIds.value = [];
    clearFilters();
});

function toggleRow(id, event) {
    const n = normalizeId(id);
    if (event.target.checked) {
        if (!isSelected(n)) selectedIds.value = [...selectedIds.value, n];
    } else {
        selectedIds.value = selectedIds.value.filter((sid) => normalizeId(sid) !== n);
    }
}

function toggleAllVisible(event) {
    const visibleIds = selectableVisible.value.map((r) => r.id);
    if (event.target.checked) {
        const merged = new Set([...selectedIds.value.map(normalizeId), ...visibleIds]);
        selectedIds.value = [...merged];
    } else {
        const visibleSet = new Set(visibleIds);
        selectedIds.value = selectedIds.value.filter((id) => !visibleSet.has(normalizeId(id)));
    }
}

function formatMoney(value) {
    const n = Number(value);
    if (Number.isNaN(n)) return '0.00';
    return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function submit() {
    form.student_ids = [...selectedIds.value];
    form.post(props.registerUrl, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            router.reload({ only: ['events', 'studentsByEvent', 'students'] });
        },
    });
}
</script>
