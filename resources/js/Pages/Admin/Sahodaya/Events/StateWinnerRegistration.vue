<template>
    <AdminLayout :title="`Register winners for State — ${program.title}`">
        <div class="mx-auto max-w-6xl space-y-5 pb-10">
            <!-- Navigation & Header Card -->
            <header class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs">
                <div v-if="actionUrls?.slotCollection" class="mb-3">
                    <Link :href="actionUrls.slotCollection" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                        ← Back to State slot collection
                    </Link>
                </div>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-bold text-indigo-700 border border-indigo-100">Official Nomination Sheet</span>
                            <span v-if="batch.status === 'certified'" class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800 border border-emerald-200">
                                ✓ Registered with State
                            </span>
                        </div>
                        <h1 class="mt-2 text-2xl font-bold text-slate-900">Register winners for State</h1>
                        <p class="mt-1 text-xs text-slate-500 max-w-3xl leading-relaxed">
                            Official winner sheet for {{ program.title }}. Select qualifying winners from your certified Sahodaya results to represent the region at State. Any winner who cannot travel is recorded as declined so that next merit rank is cleanly promoted.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="btn-secondary text-xs inline-flex items-center gap-1.5 shadow-2xs" :disabled="isCertified" @click="autoFill">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            Fill from the top
                        </button>
                        <button type="button" class="btn-primary text-xs inline-flex items-center gap-1.5 shadow-2xs !bg-slate-900 hover:!bg-slate-800 disabled:opacity-50" :disabled="isCertified || !readiness.can_register" @click="register">
                            <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            Register {{ readiness.chosen }} with State
                        </button>
                    </div>
                </div>

                <!-- Stats summary pills -->
                <div class="mt-5 grid grid-cols-2 gap-2.5 sm:grid-cols-4 border-t border-slate-100 pt-4">
                    <button type="button" class="rounded-xl border p-3 text-left transition hover:border-indigo-300"
                            :class="filterMode === 'all' ? 'border-indigo-500 bg-indigo-50/40' : 'border-slate-200 bg-slate-50/50'"
                            @click="filterMode = 'all'">
                        <p class="text-[11px] font-semibold text-slate-500">Total items</p>
                        <p class="text-xl font-bold text-slate-900 mt-0.5">{{ sheet.length }}</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">In State catalog</p>
                    </button>
                    <button type="button" class="rounded-xl border p-3 text-left transition hover:border-emerald-300"
                            :class="filterMode === 'complete' ? 'border-emerald-500 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/50'"
                            @click="filterMode = 'complete'">
                        <p class="text-[11px] font-semibold text-emerald-700">All slots filled</p>
                        <p class="text-xl font-bold text-emerald-700 mt-0.5">{{ completeCount }}</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">Ready for State</p>
                    </button>
                    <button type="button" class="rounded-xl border p-3 text-left transition hover:border-amber-300"
                            :class="filterMode === 'unfilled' ? 'border-amber-500 bg-amber-50/40' : 'border-slate-200 bg-slate-50/50'"
                            @click="filterMode = 'unfilled'">
                        <p class="text-[11px] font-semibold text-amber-700">Slots unfilled</p>
                        <p class="text-xl font-bold text-amber-700 mt-0.5">{{ unfilledCount }}</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">{{ unfilledCount ? 'Available vacancies' : 'None unfilled' }}</p>
                    </button>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3 text-left">
                        <p class="text-[11px] font-semibold text-slate-500">Nominated</p>
                        <p class="text-xl font-bold text-indigo-700 mt-0.5">{{ readiness.chosen }}</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">Students / squads</p>
                    </div>
                </div>

                <!-- Search and Filters Bar -->
                <div class="mt-4 flex flex-wrap items-center gap-3 bg-slate-50/80 p-3 rounded-xl border border-slate-200/80">
                    <div class="relative min-w-[15rem] flex-1">
                        <svg class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input v-model="search" type="search" placeholder="Search item code, title, school, participant…"
                               class="w-full rounded-lg border border-slate-300 bg-white pl-9 pr-8 py-1.5 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                        <button v-if="search" type="button" class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 text-xs" @click="search = ''">×</button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <select :value="filters.category || ''" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 focus:border-indigo-500" @change="filter('category', $event.target.value)">
                            <option value="">All categories</option>
                            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                        </select>

                        <select :value="filters.class_group || ''" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 focus:border-indigo-500" @change="filter('class_group', $event.target.value)">
                            <option value="">All class groups</option>
                            <option v-for="g in classGroups" :key="g" :value="g">{{ g }}</option>
                        </select>

                        <label class="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 cursor-pointer select-none hover:bg-slate-50 transition">
                            <input type="checkbox" :checked="!!filters.only_incomplete" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @change="filter('only_incomplete', $event.target.checked ? 1 : '')">
                            <span>Only unfilled</span>
                        </label>

                        <button v-if="search || filters.category || filters.class_group || filters.only_incomplete || filterMode !== 'all'"
                                type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 px-2 py-1" @click="resetFilters">
                            Reset
                        </button>
                    </div>
                </div>
            </header>

            <!-- Blocking error notice (Stops registration) -->
            <div v-if="readiness.blocking.length" class="rounded-2xl border border-rose-200 bg-rose-50/90 p-4 shadow-xs">
                <div class="flex items-start gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-200 text-xs font-bold text-rose-800">✕</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-rose-900">Cannot register with State yet</p>
                        <p class="text-xs text-rose-700 mt-0.5">Resolve the following items before submitting nominations to State scrutiny:</p>
                        <ul class="mt-2 space-y-1.5 text-xs text-rose-800">
                            <li v-for="(b, i) in readiness.blocking" :key="i" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white/70 px-3 py-1.5 border border-rose-200/60">
                                <span>{{ b }}</span>
                                <a v-if="parseBlockingCode(b)" :href="`#item-${parseBlockingCode(b)}`" class="inline-flex items-center gap-1 font-bold text-rose-700 hover:text-rose-900 text-xs underline">
                                    Jump to item {{ parseBlockingCode(b) }} ↓
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Unfilled warning notice (Informational) -->
            <div v-else-if="readiness.warnings.length" class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 shadow-xs">
                <div class="flex items-start gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-200 text-xs font-bold text-amber-900">!</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs font-bold text-amber-900">{{ readiness.warnings.length }} item(s) have slots unfilled</p>
                            <span class="text-[11px] font-medium text-amber-700">You can still register — items with no participants simply send nobody.</span>
                        </div>
                        <ul class="mt-2 space-y-1 text-xs text-amber-800">
                            <li v-for="(w, i) in readiness.warnings.slice(0, 8)" :key="i" class="flex flex-wrap items-center justify-between gap-2">
                                <span>{{ w }}</span>
                                <a v-if="parseWarningCode(w)" :href="`#item-${parseWarningCode(w)}`" class="font-semibold text-amber-900 hover:underline text-[11px]">
                                    Jump to item {{ parseWarningCode(w) }} ↓
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!filteredSheet.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
                <p class="font-bold text-slate-800">{{ sheet.length ? 'No items match your filters or search' : 'No State items have results from this event yet' }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ sheet.length ? 'Try clearing your search query or changing category.' : 'Publish event results to begin nominating winners for State.' }}</p>
                <button v-if="search || filters.category || filters.class_group" type="button" class="mt-3 btn-secondary text-xs" @click="resetFilters">
                    Clear all filters
                </button>
            </div>

            <!-- Item Sheets List -->
            <section v-for="row in filteredSheet" :key="row.item_id" :id="`item-${row.item_code}`"
                     class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs scroll-mt-24 transition hover:border-slate-300">
                <header class="border-b border-slate-100 p-4 sm:p-5 bg-slate-50/40">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-700 border border-slate-200">{{ row.item_code || '—' }}</span>
                                <h2 class="text-base font-bold text-slate-900">{{ row.title }}</h2>
                                <span v-if="row.direct_entry" class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 border border-indigo-100">Direct Entry</span>
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500 font-medium flex items-center gap-1.5">
                                <span v-if="row.category" class="capitalize">{{ row.category }}</span>
                                <span v-if="row.category && row.class_group">·</span>
                                <span v-if="row.class_group">{{ row.class_group }}</span>
                                <span>·</span>
                                <span class="capitalize">{{ row.participant_type || 'individual' }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full px-3 py-1 text-xs font-bold shadow-2xs inline-flex items-center gap-1.5"
                                  :class="row.is_over_quota ? 'bg-rose-100 text-rose-800 border border-rose-200'
                                      : row.is_complete ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                                      : 'bg-amber-100 text-amber-900 border border-amber-200'">
                                <svg v-if="row.is_complete" class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <span>{{ row.chosen_count }} of {{ row.quota || '—' }} slot{{ row.quota === 1 ? '' : 's' }}</span>
                                <span v-if="row.slots_left" class="opacity-80">({{ row.slots_left }} unfilled)</span>
                            </span>
                        </div>
                    </div>

                    <div v-if="row.unresolved_ties.length" class="mt-3 rounded-xl bg-amber-50 p-3 text-xs text-amber-900 border border-amber-200 flex items-start gap-2">
                        <span class="font-bold">⚖ Tie needing decision:</span>
                        <span>Position {{ row.unresolved_ties.join(', ') }} has tied candidates and exceeds available slots. Select the candidate who should represent the Sahodaya.</span>
                    </div>
                </header>

                <!-- Winner candidates table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wider text-slate-500 border-b border-slate-100">
                            <tr>
                                <th class="py-2.5 px-4 w-14 text-center">Rank</th>
                                <th class="py-2.5 px-4">Participant / Team</th>
                                <th class="py-2.5 px-4">School</th>
                                <th class="py-2.5 px-3 w-16 text-center">Grade</th>
                                <th class="py-2.5 px-4 w-20 text-right">Score</th>
                                <th class="py-2.5 px-5 text-right w-56">Action / Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="c in row.candidates" :key="c.mark_id || c.participant_id || c.registration_id"
                                class="transition hover:bg-slate-50/60"
                                :class="{ 'bg-emerald-50/40': c.is_chosen, 'opacity-60 bg-slate-50/20': c.is_declined }">
                                <td class="py-2.5 px-4 text-center">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold"
                                          :class="c.source_position === 1 ? 'bg-amber-100 text-amber-900 border border-amber-300'
                                              : c.source_position === 2 ? 'bg-slate-200 text-slate-800 border border-slate-300'
                                              : c.source_position === 3 ? 'bg-orange-100 text-orange-900 border border-orange-200'
                                              : 'bg-slate-100 text-slate-600'">
                                        {{ c.source_position || '—' }}
                                    </span>
                                    <span v-if="row.tied_positions.includes(c.source_position)" class="block text-[9px] font-bold text-amber-600">tie</span>
                                </td>
                                <td class="py-2.5 px-4">
                                    <span class="font-bold text-slate-900">{{ c.student_name }}</span>
                                    <span v-if="c.class_name" class="ml-1 text-xs text-slate-400 font-medium">({{ c.class_name }})</span>
                                </td>
                                <td class="py-2.5 px-4 text-xs font-medium text-slate-600">{{ c.school_name || '—' }}</td>
                                <td class="py-2.5 px-3 text-center">
                                    <span v-if="c.grade" class="rounded px-1.5 py-0.5 font-bold text-xs"
                                          :class="c.grade === 'A' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-700'">
                                        {{ c.grade }}
                                    </span>
                                    <span v-else class="text-slate-300">—</span>
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono text-xs font-semibold text-slate-800">{{ c.score ?? '—' }}</td>
                                <td class="py-2.5 px-5 text-right whitespace-nowrap">
                                    <div v-if="c.is_chosen" class="inline-flex items-center gap-1.5">
                                        <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800 border border-emerald-200">
                                            ✓ Going
                                        </span>
                                        <button v-if="!isCertified && findChosenFor(row, c)" type="button"
                                                class="text-xs text-slate-400 hover:text-rose-600 font-semibold px-1 hover:underline"
                                                @click="remove(findChosenFor(row, c))">
                                            remove
                                        </button>
                                    </div>
                                    <div v-else-if="c.is_declined" class="inline-flex items-center gap-1.5">
                                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 line-through">
                                            Declined
                                        </span>
                                    </div>
                                    <div v-else-if="!isCertified" class="inline-flex items-center gap-1.5">
                                        <button type="button" class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700 hover:bg-indigo-100 border border-indigo-200 transition" @click="choose(row, c, 'primary')">
                                            + Send
                                        </button>
                                        <button type="button" class="rounded-md bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 border border-slate-200 transition" @click="choose(row, c, 'reserve')">
                                            Reserve
                                        </button>
                                        <button type="button" class="rounded px-1.5 py-1 text-xs text-slate-400 hover:text-rose-600 transition" @click="decline(c)">
                                            Can't go
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Nomination summary footer -->
                <div v-if="row.chosen.length || row.reserves.length || row.declined.length" class="border-t border-slate-100 bg-slate-50/50 p-4 space-y-2">
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Nomination Status for this item:</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <!-- Chosen entries -->
                        <div v-for="s in row.chosen" :key="`c${s.id}`"
                             class="rounded-xl p-2.5 flex items-center justify-between gap-2 border shadow-2xs"
                             :class="s.is_eligible === false ? 'bg-rose-50 border-rose-200 text-rose-900' : 'bg-white border-emerald-200 text-emerald-950'">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="rounded px-1.5 py-0.2 text-[10px] font-bold"
                                          :class="s.is_eligible === false ? 'bg-rose-200 text-rose-900' : 'bg-emerald-100 text-emerald-800'">
                                        Going · Pos {{ s.source_position }}
                                    </span>
                                    <p class="text-xs font-bold truncate">{{ s.student_name }}</p>
                                </div>
                                <p class="text-[11px] text-slate-500 truncate mt-0.5">{{ s.school_name }}</p>
                                <p v-if="s.is_eligible === false" class="text-[10px] font-bold text-rose-700 mt-1">⚠ Result or squad changed since selection</p>
                            </div>
                            <button v-if="!isCertified" type="button"
                                    class="shrink-0 rounded px-2 py-1 text-xs font-semibold transition"
                                    :class="s.is_eligible === false ? 'bg-rose-600 text-white hover:bg-rose-700' : 'text-slate-400 hover:text-rose-600 hover:bg-rose-50'"
                                    @click="remove(s)">
                                {{ s.is_eligible === false ? 'Remove invalid' : 'remove' }}
                            </button>
                        </div>

                        <!-- Reserves -->
                        <div v-for="s in row.reserves" :key="`r${s.id}`"
                             class="rounded-xl p-2.5 flex items-center justify-between gap-2 border border-slate-200 bg-white shadow-2xs">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="rounded bg-sky-100 px-1.5 py-0.2 text-[10px] font-bold text-sky-800">Reserve</span>
                                    <p class="text-xs font-bold text-slate-800 truncate">{{ s.student_name }}</p>
                                </div>
                                <p class="text-[11px] text-slate-500 truncate mt-0.5">{{ s.school_name }}<span v-if="s.note"> — {{ s.note }}</span></p>
                            </div>
                            <button v-if="!isCertified" type="button" class="shrink-0 text-xs text-slate-400 hover:text-rose-600 hover:bg-rose-50 px-2 py-1 rounded transition" @click="remove(s)">
                                remove
                            </button>
                        </div>

                        <!-- Declined -->
                        <div v-for="s in row.declined" :key="`d${s.id}`"
                             class="rounded-xl p-2.5 flex items-center justify-between gap-2 border border-amber-200 bg-amber-50/40 shadow-2xs">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="rounded bg-amber-200 px-1.5 py-0.2 text-[10px] font-bold text-amber-900">Declined · Pos {{ s.source_position }}</span>
                                    <p class="text-xs font-bold text-slate-800 truncate">{{ s.student_name }}</p>
                                </div>
                                <p class="text-[11px] text-amber-800 truncate mt-0.5">{{ s.note || 'No reason recorded' }}</p>
                            </div>
                            <button v-if="!isCertified" type="button" class="shrink-0 text-xs font-semibold text-slate-500 hover:text-slate-800 underline px-2 py-1 rounded transition" @click="withdrawDecline(s)">
                                undo
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    event: Object, program: Object, batch: Object, filters: Object,
    sheet: Array, readiness: Object, categories: Array, classGroups: Array,
    currentUserId: Number, actionUrls: Object,
});

const search = ref('');
const filterMode = ref('all');
const isCertified = computed(() => props.batch.status === 'certified');

const completeCount = computed(() => props.sheet.filter(r => r.is_complete).length);
const unfilledCount = computed(() => props.sheet.filter(r => !r.is_complete && r.slots_left > 0).length);

function parseBlockingCode(msg) {
    const match = String(msg || '').match(/^([0-9A-Za-z_-]+):/);
    return match ? match[1] : null;
}

function parseWarningCode(msg) {
    const match = String(msg || '').match(/^([0-9A-Za-z_-]+)\s+/);
    return match ? match[1] : null;
}

function findChosenFor(row, candidate) {
    return row.chosen.find(s =>
        (candidate.participant_id && s.participant_id === candidate.participant_id)
        || (candidate.mark_id && s.mark_id === candidate.mark_id)
        || (candidate.registration_id && s.registration_id === candidate.registration_id)
    );
}

const filteredSheet = computed(() => {
    let rows = props.sheet;

    if (filterMode.value === 'complete') {
        rows = rows.filter(r => r.is_complete);
    } else if (filterMode.value === 'unfilled') {
        rows = rows.filter(r => !r.is_complete && r.slots_left > 0);
    }

    const q = search.value.trim().toLowerCase();
    if (!q) return rows;

    return rows.filter(row =>
        String(row.item_code || '').toLowerCase().includes(q)
        || String(row.title || '').toLowerCase().includes(q)
        || (row.candidates && row.candidates.some(c => (c.student_name && c.student_name.toLowerCase().includes(q)) || (c.school_name && c.school_name.toLowerCase().includes(q))))
        || (row.chosen && row.chosen.some(c => (c.student_name && c.student_name.toLowerCase().includes(q)) || (c.school_name && c.school_name.toLowerCase().includes(q))))
    );
});

function filter(key, value) {
    const next = { ...props.filters, [key]: value || undefined };
    router.get(props.actionUrls.index, next, { preserveState: true, preserveScroll: true, replace: true });
}

function resetFilters() {
    search.value = '';
    filterMode.value = 'all';
    router.get(props.actionUrls.index, {}, { preserveState: true, preserveScroll: true, replace: true });
}

function choose(row, candidate, type) {
    if (type === 'primary' && row.quota && row.chosen_count >= row.quota) {
        alert(`${row.item_code} already has its ${row.quota} slot(s) filled. Remove someone first, or add this candidate as a reserve.`);
        return;
    }
    router.post(props.actionUrls.choose, {
        candidate, nomination_type: type, priority_order: row.chosen_count + 1,
    }, { preserveScroll: true });
}

function remove(selection) {
    if (!selection) return;
    router.delete(`${props.actionUrls.remove}/${selection.id}`, { preserveScroll: true });
}

function decline(candidate) {
    const reason = window.prompt(`Why can ${candidate.student_name} not go to State?`);
    if (!reason || !reason.trim()) return;
    router.post(props.actionUrls.decline, { candidate, reason }, { preserveScroll: true });
}

function withdrawDecline(selection) {
    router.delete(`${props.actionUrls.withdrawDecline}/${selection.id}`, { preserveScroll: true });
}

function autoFill() {
    if (!confirm('Fill every empty slot with the top of that item\'s sheet? Tied positions are left for you to decide.')) return;
    router.post(props.actionUrls.autoFill, {}, { preserveScroll: true });
}

function register() {
    if (!confirm(`Register ${props.readiness.chosen} winner(s) with State? They enter the State's scrutiny queue.`)) return;
    router.post(props.actionUrls.register, {}, { preserveScroll: true });
}
</script>
