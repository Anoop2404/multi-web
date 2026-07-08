<template>
    <section class="mb-6">
        <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Item heads</h3>
                <p class="text-xs text-slate-500 mt-0.5">Pick a section — items and registration below update for that head.</p>
            </div>
        </div>

        <div v-if="!headItemGroups.length" class="text-sm text-slate-400 py-4 text-center border border-dashed border-slate-200 rounded-xl">
            No item heads configured for this event yet.
        </div>

        <div v-else class="flex gap-3 overflow-x-auto pb-1 -mx-1 px-1 snap-x snap-mandatory">
            <button v-for="head in headItemGroups"
                    :key="head.head_id ?? 'other'"
                    type="button"
                    class="snap-start shrink-0 min-w-[11rem] max-w-[14rem] text-left rounded-xl border p-3 transition-all"
                    :class="isActive(head.head_id)
                        ? 'border-indigo-500 bg-indigo-50/80 shadow-sm ring-1 ring-indigo-200'
                        : 'border-slate-200 bg-white hover:border-indigo-300 hover:bg-slate-50'"
                    @click="selectHead(head.head_id)">
                <div class="flex flex-wrap items-center gap-1.5 mb-1.5">
                    <span class="font-semibold text-sm text-slate-900 leading-tight">{{ head.head_name }}</span>
                    <span class="text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-full border"
                          :class="regStatusClass(head)">
                        {{ regStatusLabel(head) }}
                    </span>
                </div>
                <p class="text-[11px] text-slate-600 leading-snug">
                    <span class="font-medium">{{ head.item_count ?? 0 }}</span> item{{ head.item_count === 1 ? '' : 's' }}
                    · <span class="font-medium">{{ head.participant_count ?? 0 }}</span> registered
                </p>
                <p class="text-[10px] text-slate-500 mt-1 leading-snug">
                    <span class="font-medium text-slate-600">Reg:</span> {{ formatRegWindow(head.reg_start, head.reg_end) }}
                </p>
                <p v-if="head.competition_start || head.competition_end || head.competition_time" class="text-[10px] text-slate-500 mt-0.5 leading-snug">
                    <span class="font-medium text-slate-600">Events:</span> {{ formatCompetition(head) }}
                </p>
                <span v-if="head.schedule_mode === 'same_time'"
                      class="mt-1 inline-block text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-full bg-sky-50 text-sky-700 border border-sky-200">
                    Same time
                </span>
            </button>
        </div>
    </section>
</template>

<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    headItemGroups: { type: Array, default: () => [] },
    itemsBaseUrl: { type: String, required: true },
    selectedHeadId: { type: [Number, String], default: null },
});

function headParam(headId) {
    return headId == null ? 'other' : String(headId);
}

function isActive(headId) {
    const selected = props.selectedHeadId;
    if (selected == null || selected === '') return false;
    if (headId == null) return String(selected) === 'other';
    return String(selected) === String(headId);
}

function selectHead(headId) {
    if (isActive(headId)) return;
    router.get(props.itemsBaseUrl, { head: headParam(headId) }, {
        preserveScroll: true,
        preserveState: true,
        only: ['event', 'registrations', 'students', 'selectedHeadId', 'initialHeadId', 'headItemGroups'],
    });
}

function formatRegWindow(start, end) {
    if (!start && !end) return 'Follows event registration';
    if (start && end) return `${formatDate(start)} – ${formatDate(end)}`;
    if (start) return `From ${formatDate(start)}`;
    return `Until ${formatDate(end)}`;
}

function formatDate(iso) {
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatTime(hhmm) {
    if (!hhmm) return '';
    const [h, m] = String(hhmm).slice(0, 5).split(':');
    const d = new Date();
    d.setHours(Number(h), Number(m), 0, 0);
    return d.toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' });
}

function formatCompetition(head) {
    if (head.schedule_mode === 'same_time') {
        const day = head.competition_start ? formatDate(head.competition_start) : '';
        const time = formatTime(head.competition_time);
        if (day && time) return `${day}, ${time}`;
        return day || time || 'To be scheduled';
    }
    return formatRegWindow(head.competition_start, head.competition_end);
}

function regStatusLabel(head) {
    if (head.registration_open === false) {
        if (head.reg_start && new Date(`${head.reg_start}T12:00:00`) > new Date()) return 'Soon';
        return 'Closed';
    }
    return 'Open';
}

function regStatusClass(head) {
    if (head.registration_open === false) {
        return 'bg-amber-50 text-amber-800 border-amber-200';
    }
    return 'bg-emerald-50 text-emerald-800 border-emerald-200';
}
</script>
