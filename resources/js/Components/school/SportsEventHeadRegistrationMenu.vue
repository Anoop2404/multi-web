<template>
    <div class="space-y-6">
        <div class="notice-banner notice-banner--info text-sm max-w-3xl">
            <p class="font-semibold text-[#0f3d7a] mb-1">Choose an item head</p>
            <p class="text-slate-700">
                Each section (Athletics, Field events, etc.) has its own registration window.
                Open a head to register athletes for its events.
            </p>
        </div>

        <div v-if="eventRegisteredAthletes.length" class="rounded-xl border border-indigo-100 bg-indigo-50/40 overflow-hidden">
            <div class="px-4 py-2.5 border-b border-indigo-100 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h4 class="text-sm font-bold text-indigo-950">Event athletes</h4>
                    <p class="text-xs text-indigo-800/80">{{ eventRegisteredAthletes.length }} registered for this fest</p>
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
        </div>

        <EmptyState v-if="!headItemGroups.length" title="No item heads yet" icon="📂"
                    description="Sahodaya has not synced item heads for this event yet." />

        <div v-else class="space-y-4">
            <div v-for="head in headItemGroups" :key="head.head_id ?? 'other'"
                 class="card !p-0 overflow-hidden hover:shadow-md transition-shadow">
                <div class="flex flex-wrap items-start justify-between gap-3 px-4 py-3 bg-slate-50/80 border-b border-slate-100">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-900">{{ head.head_name }}</h3>
                            <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border"
                                  :class="regStatusClass(head)">
                                {{ regStatusLabel(head) }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 mt-1">
                            <span class="font-medium">Registration:</span>
                            {{ formatRegWindow(head.reg_start, head.reg_end) }}
                        </p>
                        <p v-if="head.competition_start || head.competition_end" class="text-xs text-slate-500 mt-0.5">
                            Competition: {{ formatRegWindow(head.competition_start, head.competition_end) }}
                        </p>
                        <p class="text-xs text-slate-500 mt-1">
                            {{ head.item_count }} item{{ head.item_count === 1 ? '' : 's' }}
                            · {{ head.participant_count ?? 0 }} registered
                        </p>
                    </div>
                    <Link :href="headUrl(head.head_id)"
                          class="btn-primary text-sm !min-h-0 shrink-0">
                        Open & register →
                    </Link>
                </div>
                <ul v-if="head.items?.length" class="divide-y divide-slate-100 max-h-52 overflow-y-auto">
                    <li v-for="item in head.items.slice(0, 12)" :key="item.id"
                        class="px-4 py-2 text-sm flex flex-wrap items-center justify-between gap-2">
                        <span class="text-slate-800">{{ item.title }}</span>
                        <span class="text-xs text-slate-500">
                            <span v-if="item.participant_count">{{ item.participant_count }} registered</span>
                            <span v-else class="text-slate-400">No entries yet</span>
                        </span>
                    </li>
                    <li v-if="head.items.length > 12" class="px-4 py-2 text-xs text-indigo-600 bg-slate-50/50">
                        <Link :href="headUrl(head.head_id)" class="font-semibold hover:underline">
                            + {{ head.items.length - 12 }} more items — open head →
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    event: { type: Object, required: true },
    headItemGroups: { type: Array, default: () => [] },
    students: { type: Array, default: () => [] },
    itemsBaseUrl: { type: String, required: true },
    eventAthletesHref: { type: String, default: '' },
});

const eventRegisteredAthletes = computed(() =>
    (props.students ?? []).filter((s) => s.event_registered || s.event_registration_number),
);

function headUrl(headId) {
    return `${props.itemsBaseUrl}?head=${headId ?? 'other'}`;
}

function formatRegWindow(start, end) {
    if (!start && !end) return 'No dates set — follows event registration';
    if (start && end) return `${formatDate(start)} – ${formatDate(end)}`;
    if (start) return `From ${formatDate(start)}`;
    return `Until ${formatDate(end)}`;
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function regStatusLabel(head) {
    if (head.registration_open === false) {
        if (head.reg_start && new Date(`${head.reg_start}T12:00:00`) > new Date()) return 'Not open yet';
        return 'Closed';
    }
    if (!head.reg_start && !head.reg_end) return 'Open';
    return 'Open now';
}

function regStatusClass(head) {
    if (head.registration_open === false) {
        return 'bg-amber-50 text-amber-800 border-amber-200';
    }
    return 'bg-emerald-50 text-emerald-800 border-emerald-200';
}
</script>
