<template>
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="close"></div>
        <div class="relative modal-shell max-w-2xl w-full max-h-[90vh] !overflow-y-auto flex flex-col">
            <div class="modal-head shrink-0 sticky top-0 z-10 bg-white">
                <div class="min-w-0 pr-4">
                    <h3 class="font-bold text-[#041525] truncate">{{ title }}</h3>
                    <p v-if="subtitle" class="text-xs text-gray-500 mt-0.5">{{ subtitle }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button v-if="showAddStudent" type="button"
                            class="btn-secondary text-xs !py-1.5 !px-3"
                            @click="requestAddStudent">
                        + Add student
                    </button>
                    <button type="button" class="text-gray-400 hover:text-gray-600 text-2xl leading-none" @click="close">&times;</button>
                </div>
            </div>

            <div class="px-5 py-3 border-b border-slate-100 shrink-0 space-y-3">
                <div class="flex flex-wrap gap-2 items-center">
                    <input
                        ref="searchInput"
                        v-model="search"
                        type="search"
                        class="field flex-1 min-w-[180px] !py-2 !text-sm"
                        placeholder="Search name, reg no, class…"
                        autocomplete="off"
                    >
                    <label v-if="hasIneligible" class="flex items-center gap-1.5 text-xs text-slate-600 whitespace-nowrap cursor-pointer">
                        <input v-model="showIneligible" type="checkbox" class="rounded">
                        Show ineligible
                    </label>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-500">
                        {{ filteredEligible.length }} eligible
                        <span v-if="!showIneligible && hasIneligible"> · {{ ineligibleCount }} hidden</span>
                    </span>
                    <span v-if="maxSelected" class="text-slate-400">
                        Max {{ maxSelected }}
                    </span>
                    <span v-if="localSelected.length" class="font-semibold text-[#0f3d7a]">
                        {{ localSelected.length }} selected
                    </span>
                </div>
                <div v-if="localSelected.length" class="flex flex-wrap gap-1.5 max-h-16 overflow-y-auto">
                    <button
                        v-for="chip in selectedChips"
                        :key="chip.id"
                        type="button"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#0f3d7a]/10 text-[#0f3d7a] text-[11px] font-medium"
                        @click="toggleId(chip.id)"
                    >
                        <span class="font-mono">{{ chip.regNo }}</span>
                        <span class="truncate max-w-[120px]">{{ chip.name }}</span>
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>

            <div class="min-h-[160px]">
                <p v-if="!entries.length" class="p-6 text-sm text-amber-800">
                    No students in your school yet.
                    <button type="button" class="link-brand font-semibold" @click="$emit('add-student')">Add student</button>
                </p>
                <p v-else-if="!visibleEntries.length" class="p-6 text-sm text-slate-600">
                    No students match your search.
                    <button v-if="search" type="button" class="link-brand font-semibold ml-1" @click="search = ''">Clear search</button>
                </p>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="entry in visibleEntries" :key="entry.id">
                        <label
                            class="flex items-start gap-3 px-5 py-2.5 cursor-pointer transition-colors"
                            :class="entry.eligible
                                ? 'hover:bg-slate-50'
                                : 'bg-slate-50/60 cursor-not-allowed opacity-75'"
                        >
                            <input
                                type="checkbox"
                                class="rounded mt-1 shrink-0"
                                :value="entry.id"
                                :checked="localSelected.includes(entry.id)"
                                :disabled="!entry.eligible"
                                @change="toggleId(entry.id)"
                            >
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <span class="font-mono text-xs font-semibold text-[#0f3d7a] shrink-0">
                                        {{ entry.regNo || '—' }}
                                    </span>
                                    <span class="font-medium text-sm text-gray-900">{{ entry.name }}</span>
                                </span>
                                <span v-if="entry.meta" class="block text-xs text-gray-500 mt-0.5">{{ entry.meta }}</span>
                                <span v-if="entry.eventRegistered"
                                      class="inline-flex mt-0.5 text-[10px] font-bold uppercase text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100">
                                    Event Reg{{ entry.eventRegNumber ? `: ${entry.eventRegNumber}` : ' registered' }}
                                </span>
                                <span v-if="entry.reason" class="block text-xs text-amber-700 mt-0.5">{{ entry.reason }}</span>
                            </span>
                        </label>
                    </li>
                </ul>
            </div>

            <div v-if="teamName !== undefined" class="px-5 py-3 border-t border-slate-100 shrink-0 space-y-3">
                <div>
                    <label class="text-xs font-semibold text-slate-600 block mb-1">Team name</label>
                    <input v-model="localTeamName" type="text" class="field !py-2 !text-sm max-w-sm" placeholder="Required for group items">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Coach name <span class="font-normal text-slate-400">(optional)</span></label>
                        <input v-model="localCoachName" type="text" class="field !py-2 !text-sm" placeholder="Coach name">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Coach phone <span class="font-normal text-slate-400">(optional)</span></label>
                        <input v-model="localCoachPhone" type="text" class="field !py-2 !text-sm" placeholder="Phone">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Manager name <span class="font-normal text-slate-400">(optional)</span></label>
                        <input v-model="localManagerName" type="text" class="field !py-2 !text-sm" placeholder="Manager name">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Manager phone <span class="font-normal text-slate-400">(optional)</span></label>
                        <input v-model="localManagerPhone" type="text" class="field !py-2 !text-sm" placeholder="Phone">
                    </div>
                </div>
            </div>

            <div class="modal-foot shrink-0 sticky bottom-0 z-10 bg-white flex flex-wrap justify-end gap-2">
                <button type="button" class="btn-ghost text-sm" @click="close">Cancel</button>
                <button
                    type="button"
                    class="btn-primary text-sm"
                    :disabled="!canConfirm"
                    @click="confirm"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch, nextTick } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: 'Select students' },
    subtitle: { type: String, default: '' },
    entries: { type: Array, default: () => [] },
    selectedIds: { type: Array, default: () => [] },
    teamName: { type: String, default: undefined },
    requireTeamName: { type: Boolean, default: false },
    coachName: { type: String, default: undefined },
    coachPhone: { type: String, default: undefined },
    managerName: { type: String, default: undefined },
    managerPhone: { type: String, default: undefined },
    confirmLabel: { type: String, default: 'Apply selection' },
    maxSelected: { type: Number, default: null },
    showAddStudent: { type: Boolean, default: true },
});

const emit = defineEmits([
    'update:modelValue',
    'update:selectedIds',
    'update:teamName',
    'update:coachName',
    'update:coachPhone',
    'update:managerName',
    'update:managerPhone',
    'confirm',
    'add-student',
]);

const search = ref('');
const showIneligible = ref(false);
const localSelected = ref([]);
const localTeamName = ref('');
const localCoachName = ref('');
const localCoachPhone = ref('');
const localManagerName = ref('');
const localManagerPhone = ref('');
const searchInput = ref(null);

const ineligibleCount = computed(() => props.entries.filter(e => !e.eligible).length);
const hasIneligible = computed(() => ineligibleCount.value > 0);

const filteredEligible = computed(() => props.entries.filter(e => e.eligible));

const visibleEntries = computed(() => {
    const q = search.value.trim().toLowerCase();
    let pool = showIneligible.value ? props.entries : props.entries.filter(e => e.eligible);

    if (!q) return pool;

    return pool.filter((entry) => {
        const haystack = [
            entry.name,
            entry.regNo,
            entry.meta,
            entry.reason,
        ].filter(Boolean).join(' ').toLowerCase();
        return haystack.includes(q);
    });
});

const selectedChips = computed(() => {
    const map = new Map(props.entries.map(e => [e.id, e]));
    return localSelected.value
        .map(id => map.get(id))
        .filter(Boolean)
        .map(e => ({ id: e.id, name: e.name, regNo: e.regNo || '—' }));
});

const canConfirm = computed(() => {
    if (!localSelected.value.length) return false;
    if (props.requireTeamName && !String(localTeamName.value ?? '').trim()) return false;
    return true;
});

watch(() => props.modelValue, (open) => {
    if (open) {
        localSelected.value = [...(props.selectedIds ?? [])];
        localTeamName.value = props.teamName ?? '';
        localCoachName.value = props.coachName ?? '';
        localCoachPhone.value = props.coachPhone ?? '';
        localManagerName.value = props.managerName ?? '';
        localManagerPhone.value = props.managerPhone ?? '';
        search.value = '';
        showIneligible.value = false;
        nextTick(() => searchInput.value?.focus());
    }
});

function toggleId(id) {
    const entry = props.entries.find(e => e.id === id);
    if (!entry?.eligible) return;
    const idx = localSelected.value.indexOf(id);
    if (idx === -1) {
        if (props.maxSelected && localSelected.value.length >= props.maxSelected) {
            localSelected.value = [id];
            return;
        }
        localSelected.value = [...localSelected.value, id];
    } else {
        localSelected.value = localSelected.value.filter(x => x !== id);
    }
}

function close() {
    emit('update:modelValue', false);
}

function requestAddStudent() {
    close();
    emit('add-student');
}

function confirm() {
    emit('update:selectedIds', [...localSelected.value]);
    if (props.teamName !== undefined) {
        emit('update:teamName', localTeamName.value);
        emit('update:coachName', localCoachName.value);
        emit('update:coachPhone', localCoachPhone.value);
        emit('update:managerName', localManagerName.value);
        emit('update:managerPhone', localManagerPhone.value);
    }
    emit('confirm');
    close();
}
</script>
