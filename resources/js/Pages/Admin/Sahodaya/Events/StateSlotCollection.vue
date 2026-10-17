<template>
    <SahodayaEventsLayout :title="`State slot collection — ${program.title}`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl" :show-header-title="false">
        <div class="mx-auto max-w-7xl space-y-5 pb-8">
            <header class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <Link :href="actionUrls.overview" class="text-xs font-semibold text-slate-500 hover:text-slate-900">← All State events</Link>
                <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-indigo-600">State registration · School confirmations</p>
                        <h1 class="mt-1 text-2xl font-bold text-slate-900">State slot collection</h1>
                        <p class="mt-1 text-sm text-slate-500">{{ event.title }} · {{ program.title }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link :href="actionUrls.winnerSheet" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">
                            Winner Sheet →
                        </Link>
                        <span class="rounded-full px-3 py-1.5 text-xs font-bold" :class="event.state_slot_collection_approved_at ? 'bg-emerald-100 text-emerald-800' : event.state_slot_collection_open ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-700'">
                            {{ statusLabel }}
                        </span>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span><strong class="text-slate-900">{{ totals.accepted }} / {{ totals.quota }}</strong> slots confirmed by schools</span>
                    <span class="font-semibold text-slate-500">{{ completionPercent }}% confirmed</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" :aria-valuenow="completionPercent" aria-valuemin="0" aria-valuemax="100" aria-label="School slot confirmation progress">
                    <div class="h-full rounded-full bg-emerald-500 transition-all" :style="{ width: `${completionPercent}%` }" />
                </div>
                <p class="mt-2 text-xs text-slate-500">An offer is complete only after the school confirms it. Ranks 3–5 remain on the waiting list until a higher rank opts out.</p>
                <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
                    <button v-if="!event.state_slot_collection_open" type="button" class="btn-primary text-sm" :disabled="busy || !summary.length" @click="open">
                        {{ opening ? 'Opening slots…' : 'Open school confirmations' }}
                    </button>
                    <template v-else-if="!event.state_slot_collection_approved_at">
                        <button type="button" class="btn-secondary text-sm" :disabled="busy" @click="saveSlotQuotas(true)">
                            {{ savingSlots ? 'Updating offers…' : 'Sync available offers' }}
                        </button>
                        <button type="button" class="btn-primary text-sm" :disabled="!canApprove" @click="approve">
                            {{ approving ? 'Approving…' : 'Approve confirmed list' }}
                        </button>
                        <span v-if="totals.pending" class="text-xs text-amber-800">{{ totals.pending }} school response(s) still required</span>
                        <span v-else-if="counts.attention || counts.not_offered" class="text-xs text-rose-700">Resolve items needing attention or send available offers first.</span>
                        <span v-else-if="hasChanges" class="text-xs text-amber-800">Save your slot changes first.</span>
                        <span v-else class="text-xs text-emerald-700">School responses are complete. Review any unfilled slots before approval.</span>
                    </template>
                    <span v-else class="text-sm text-emerald-800">Approved {{ formatDate(event.state_slot_collection_approved_at) }}. Continue to the State winner registration sheet to submit.</span>
                </div>
                <p v-if="opening || savingSlots" role="status" class="mt-3 rounded-lg bg-indigo-50 p-3 text-xs text-indigo-800">
                    {{ opening ? 'Preparing offers and checking quotas. Keep this page open; one submission is enough.' : 'Updating slot counts and checking new offers…' }}
                </p>
                <p v-if="operationError" role="alert" class="mt-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-800">{{ operationError }}</p>
            </header>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
                <button v-for="card in statCards" :key="card.key" type="button" class="rounded-xl border bg-white p-4 text-left transition hover:border-indigo-300" :class="statusFilter === card.filter ? 'border-indigo-500 ring-1 ring-indigo-100' : 'border-slate-200'" @click="statusFilter = card.filter">
                    <p class="text-xs font-semibold text-slate-500">{{ card.label }}</p>
                    <p class="mt-2 text-2xl font-bold" :class="card.color">{{ card.count }}</p>
                    <p class="mt-1 text-[11px] text-slate-500">{{ card.hint }}</p>
                </button>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-xl border border-slate-200 bg-white p-4">
                    <h2 class="text-sm font-bold text-slate-800">School submission link</h2>
                    <p class="mt-1 text-xs text-slate-500">Schools sign in with their existing accounts and see only their own entries.</p>
                    <div class="mt-3 flex gap-2">
                        <input :value="schoolEntryUrl" readonly class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs" aria-label="School submission URL" @focus="$event.target.select()">
                        <button type="button" class="btn-secondary text-xs" @click="copySchoolLink">{{ copied ? 'Copied' : 'Copy link' }}</button>
                    </div>
                </section>
                <section class="rounded-xl border border-slate-200 bg-white p-4">
                    <h2 class="text-sm font-bold text-slate-800">Slot settings</h2>
                    <p class="mt-1 text-xs text-slate-500">Up to two State slots per item. Unpublished items with one or two entries can receive direct offers.</p>
                    <div v-if="!event.state_slot_collection_approved_at" class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="text-xs text-slate-500">Set all items:</span>
                        <button type="button" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" :disabled="busy" @click="applyBatch(1)">1 slot</button>
                        <button type="button" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" :disabled="busy" @click="applyBatch(2)">2 slots</button>
                        <button type="button" class="btn-secondary text-xs" :disabled="busy || !hasChanges" @click="saveSlotQuotas(false)">Save changes</button>
                        <span v-if="hasChanges" class="text-xs text-amber-700">Unsaved changes</span>
                    </div>
                    <p v-else class="mt-3 text-xs text-slate-500">Slot settings are locked after approval.</p>
                </section>
            </div>

            <section class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap gap-3">
                    <input v-model="search" type="search" placeholder="Search item, code, school or participant…" aria-label="Search State slots" class="min-w-[14rem] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <select v-model="statusFilter" aria-label="Filter by slot status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="all">All statuses</option>
                        <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <select v-model="categoryFilter" aria-label="Filter by category" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="all">All categories</option>
                        <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
                    </select>
                    <select v-model="sort" aria-label="Sort State items" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="attention">Action needed first</option>
                        <option value="code">Item code</option>
                    </select>
                    <button type="button" class="text-xs font-semibold text-indigo-700" @click="resetFilters">Reset</button>
                </div>
                <p class="mt-3 text-xs text-slate-500">Showing {{ visibleRows.length }} of {{ summary.length }} State-quota items · {{ totals.pending }} pending responses · {{ totals.unfilled }} unfilled slots</p>
            </section>

            <section v-if="!visibleRows.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <h2 class="font-semibold text-slate-800">{{ summary.length ? 'No items match these filters' : 'No linked State-quota items' }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ summary.length ? 'Try another status or clear the search.' : 'Link eligible event items to the State program to start collection.' }}</p>
            </section>

            <section v-for="row in visibleRows" :key="row.item_id" :id="`item-${row.item_code}`" class="overflow-hidden rounded-2xl border border-slate-200 bg-white scroll-mt-24 transition shadow-xs hover:border-slate-300">
                <header class="border-b border-slate-100 p-4 sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded bg-slate-100 px-2.5 py-1 font-mono text-xs font-bold text-slate-700 border border-slate-200">{{ row.item_code || '—' }}</span>
                                <h2 class="text-base font-bold text-slate-900">{{ row.title }}</h2>
                                <span v-if="row.direct_entry" class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 border border-indigo-100">Direct entry</span>
                            </div>
                            <p class="mt-2 text-xs text-slate-500 font-medium">{{ [row.category_label, row.gender_label, row.type_label].filter(Boolean).join(' · ') }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1.5 text-xs font-bold shadow-2xs" :class="statusClasses[row.slot_status]">{{ statusLabels[row.slot_status] }}</span>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap gap-2">
                            <span v-for="(slot, index) in slotsFor(row)" :key="index" class="rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-2xs" :class="slot.class">
                                Slot {{ index + 1 }} · {{ slot.label }}
                            </span>
                        </div>
                        <label v-if="!event.state_slot_collection_approved_at && row.quota > 0" class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            State quota
                            <input v-model.number="slotQuotas[row.item_id]" type="number" min="1" max="2" :disabled="busy" :aria-label="`State quota for ${row.title}`" class="w-16 rounded-lg border border-slate-300 px-2 py-1.5 text-center font-bold text-slate-800 shadow-2xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" @change="hasChanges = true">
                        </label>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">
                        <strong class="text-slate-800">{{ row.slot_progress.accepted }}/{{ row.quota }} confirmed</strong> · {{ row.slot_progress.pending }} awaiting response · {{ row.slot_progress.unfilled }} unfilled
                        <span v-if="row.slot_progress.invalid" class="font-semibold text-rose-700"> · {{ row.slot_progress.invalid }} need review</span>
                        <span v-if="row.school_responses.opted_out"> · {{ row.school_responses.opted_out }} opted out</span>
                    </p>
                </header>

                <!-- Needs Attention Banner (Ties / Ineligible entries) -->
                <div v-if="row.slot_status === 'attention'" class="border-b border-rose-200 bg-rose-50/80 px-5 py-3.5 text-xs text-rose-900">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-rose-200 text-[11px] font-bold text-rose-800">!</span>
                            <div>
                                <span v-if="row.unresolved_ties.length">Rank {{ row.unresolved_ties.join(', ') }} has an unresolved tie. Pick which candidate gets the State slot below.</span>
                                <span v-if="row.slot_progress.invalid"> {{ row.slot_progress.invalid }} offered entry(s) need review due to certified result or squad changes.</span>
                                <span v-if="row.is_over_quota"> More offers exist than the allowed quota.</span>
                            </div>
                        </div>
                        <Link :href="`${actionUrls.winnerSheet}#item-${row.item_code}`" class="inline-flex items-center gap-1 rounded-md bg-rose-600 px-3 py-1 font-semibold text-white shadow-2xs hover:bg-rose-700 transition">
                            Review on winner sheet →
                        </Link>
                    </div>

                    <!-- Direct Tie-Breaker: choose candidate to send offer -->
                    <div v-if="tiedCandidates(row).length" class="mt-3.5 pt-3 border-t border-rose-200">
                        <p class="font-bold text-rose-950 mb-2 flex items-center gap-2">
                            <span>🎯 Tied Candidates — Choose who gets the slot:</span>
                            <span class="rounded bg-rose-200 px-1.5 py-0.5 text-[10px] font-bold text-rose-900">{{ row.slots_left }} slot(s) open</span>
                        </p>
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                            <div v-for="c in tiedCandidates(row)" :key="candidateKey(c)"
                                 class="rounded-xl border border-rose-200 bg-white p-3.5 flex flex-col justify-between shadow-2xs">
                                <div>
                                    <div class="flex items-center justify-between gap-1.5">
                                        <span class="text-xs font-bold text-slate-900 truncate">{{ c.student_name }}</span>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-100 text-rose-800 border border-rose-200">
                                            Rank {{ c.source_position }} · {{ c.score ?? '—' }} pts
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 truncate mt-1">{{ c.school_name }}</p>
                                </div>
                                <div class="mt-3 pt-2 border-t border-slate-100 flex items-center justify-between">
                                    <span class="text-[10px] text-slate-400">Position {{ c.source_position }} tie</span>
                                    <button v-if="!event.state_slot_collection_approved_at"
                                            type="button"
                                            class="rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs py-1 px-3 transition shadow-2xs"
                                            :disabled="busy || row.slots_left <= 0"
                                            @click="sendOffer(row, c)">
                                        {{ sendingOfferKey === candidateKey(c) ? 'Sending…' : 'Send Offer' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-else-if="row.slot_status === 'not_open'" class="bg-slate-50 px-5 py-3 text-xs text-slate-600">Collection is not open. Eligible top ranks will be offered when you open school confirmations.</p>
                <p v-else-if="row.slot_status === 'not_offered'" class="bg-indigo-50 px-5 py-3 text-xs text-indigo-800">Eligible entries are ready but not offered. Use “Sync available offers” to send them to schools.</p>
                <p v-else-if="row.slot_status === 'awaiting_eligibility'" class="bg-slate-50 px-5 py-3 text-xs text-slate-600">No eligible candidates yet. Publish results, or verify that this unpublished item has one or two active entries.</p>
                <p v-else-if="row.slot_status === 'unfilled'" class="bg-amber-50 px-5 py-3 text-xs text-amber-900">{{ row.slot_progress.unfilled }} slot(s) remain unfilled. No eligible replacement is currently available within ranks 1–5.</p>

                <!-- Offered Entries Table with Actions -->
                <div v-if="row.chosen.length" class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider font-semibold text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Offered entry</th>
                                <th class="px-4 py-3">School</th>
                                <th class="px-4 py-3">Rank</th>
                                <th class="px-4 py-3">School response</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="selection in row.chosen" :key="selection.id" class="border-t border-slate-100 hover:bg-slate-50/50 transition">
                                <td class="px-5 py-3 font-semibold text-slate-800">
                                    {{ selection.student_name }}
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600 font-medium">
                                    {{ selection.school_name }}
                                </td>
                                <td class="px-4 py-3 text-xs font-semibold text-slate-700">
                                    {{ row.direct_entry ? 'Direct' : 'Rank ' + (selection.source_position || '—') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                              :class="selection.is_eligible === false ? 'bg-rose-100 text-rose-800 border border-rose-200' : selection.school_response === 'accepted' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                            {{ selection.is_eligible === false ? 'Needs review' : selection.school_response === 'accepted' ? 'Confirmed' : 'Awaiting response' }}
                                        </span>
                                        <Link v-if="selection.is_eligible === false" :href="`${actionUrls.winnerSheet}#item-${row.item_code}`" class="text-xs font-semibold text-rose-700 underline hover:text-rose-900">Resolve →</Link>
                                    </div>
                                    <p v-if="selection.school_responded_at" class="mt-1 text-[10px] text-slate-400">
                                        {{ formatDate(selection.school_responded_at) }}
                                        <span v-if="selection.school_responded_by_name"> ({{ selection.school_responded_by_name }})</span>
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <div v-if="!event.state_slot_collection_approved_at" class="inline-flex items-center gap-1.5">
                                        <button v-if="selection.school_response !== 'accepted'" type="button"
                                                class="rounded-md border border-emerald-300 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-800 hover:bg-emerald-100 transition shadow-2xs"
                                                :disabled="busy"
                                                title="Confirm on school's behalf"
                                                @click="alterSelection(selection, 'accept')">
                                            Confirm
                                        </button>
                                        <button type="button"
                                                class="rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-900 hover:bg-amber-100 transition shadow-2xs"
                                                :disabled="busy"
                                                title="Record student opt-out and roll down to next rank"
                                                @click="openOptOutModal(selection, row)">
                                            Opt out
                                        </button>
                                        <button type="button"
                                                class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-500 hover:text-rose-600 hover:border-rose-200 transition shadow-2xs"
                                                :disabled="busy"
                                                title="Revoke offer and free slot"
                                                @click="alterSelection(selection, 'revoke')">
                                            Revoke
                                        </button>
                                    </div>
                                    <span v-else class="text-xs text-slate-400">Locked</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Waiting List (Ranks 3-5 or non-selected) -->
                <div v-if="waitingFor(row).length" class="border-t border-slate-100 bg-slate-50/60 p-4 sm:p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">⏳ Waiting List {{ row.direct_entry ? '(Other entries)' : `(Ranks ${Number(row.quota || 2) + 1}–5)` }}</span>
                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-700">{{ waitingFor(row).length }}</span>
                        </div>
                        <span class="text-[11px] text-slate-500">Promoted automatically in merit order if a Top {{ row.quota || 2 }} qualifier opts out</span>
                    </div>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                        <div v-for="(candidate, cIdx) in waitingFor(row)" :key="candidateKey(candidate)"
                             class="rounded-xl border border-slate-200 bg-white p-3.5 flex flex-col justify-between shadow-2xs hover:border-indigo-300 transition">
                            <div>
                                <div class="flex items-center justify-between gap-1.5">
                                    <span class="text-xs font-bold text-slate-900 truncate">{{ candidate.student_name }}</span>
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                                          :class="cIdx === 0 ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'bg-slate-100 text-slate-600'">
                                        {{ candidate.direct_entry ? 'Direct' : 'Rank ' + candidate.source_position }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 truncate mt-1">{{ candidate.school_name }}</p>
                            </div>
                            <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between text-[10px]">
                                <div>
                                    <span class="font-medium" :class="cIdx === 0 ? 'text-indigo-600 font-bold' : 'text-slate-400'">
                                        {{ cIdx === 0 ? '★ 1st in waitlist queue' : `Queue #${cIdx + 1}` }}
                                    </span>
                                    <span class="text-slate-500 ml-1.5">
                                        {{ cIdx === 0 ? 'Gets slot on 1st opt-out' : `Needs ${cIdx + 1} opt-outs` }}
                                    </span>
                                </div>
                                <button v-if="row.slots_left > 0 && !event.state_slot_collection_approved_at"
                                        type="button"
                                        class="rounded-md bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-600 hover:text-white font-semibold text-[11px] px-2 py-0.5 transition shadow-2xs"
                                        :disabled="busy"
                                        @click="sendOffer(row, candidate)">
                                    {{ sendingOfferKey === candidateKey(candidate) ? 'Sending…' : 'Send Offer' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Opt-out records with Reopen action -->
                <details v-if="row.declined.length" class="border-t border-slate-100 px-5 py-3">
                    <summary class="cursor-pointer text-xs font-semibold text-slate-500 hover:text-slate-800">
                        {{ row.declined.length }} opt-out record(s)
                    </summary>
                    <div class="mt-3 space-y-2">
                        <div v-for="selection in row.declined" :key="selection.id"
                             class="rounded-xl bg-slate-50 p-3 text-xs border border-slate-200/60 flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-900 border border-amber-200">
                                        Opted out · {{ row.direct_entry ? 'Direct' : 'Rank ' + (selection.source_position || '—') }}
                                    </span>
                                    <p class="font-bold text-slate-800 truncate">{{ selection.student_name }}</p>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-0.5">{{ selection.school_name }}</p>
                                <p class="mt-1 text-slate-600 font-medium">Reason: {{ selection.note || 'No reason recorded' }}</p>
                                <p v-if="selection.school_responded_by_name" class="text-[10px] text-slate-400 mt-0.5">
                                    Recorded by {{ selection.school_responded_by_name }} {{ selection.school_responded_at ? '· ' + formatDate(selection.school_responded_at) : '' }}
                                </p>
                            </div>
                            <div v-if="!event.state_slot_collection_approved_at" class="shrink-0 flex items-center gap-2">
                                <button type="button"
                                        class="rounded-md border px-2.5 py-1 text-xs font-semibold transition shadow-2xs"
                                        :class="row.slots_left > 0 ? 'bg-indigo-600 text-white hover:bg-indigo-700 border-indigo-600' : 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed'"
                                        :disabled="busy || row.slots_left <= 0"
                                        :title="row.slots_left <= 0 ? 'Slots are full. Free a slot first to reopen.' : 'Reopen offer for this candidate'"
                                        @click="alterSelection(selection, 'reopen')">
                                    Reopen offer
                                </button>
                            </div>
                        </div>
                    </div>
                </details>
            </section>
        </div>

        <!-- Modal for Recording Opt-Out Reason -->
        <div v-if="optOutModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Record Winner Opt-Out</h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ optOutTarget?.student_name }} · {{ optOutTarget?.school_name }}
                        </p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-600 text-lg leading-none" @click="optOutModalOpen = false">×</button>
                </div>
                <p class="text-xs text-slate-600">
                    This will record the student's opt-out and automatically offer the freed slot down the merit order (ranks 3–5) unless an unresolved tie requires manual selection.
                </p>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Reason for Opting Out <span class="text-rose-600">*</span></label>
                    <textarea v-model="optOutReason" rows="3" placeholder="e.g. Schedule conflict, medical reason, or school withdrawal…"
                              class="w-full rounded-xl border border-slate-300 p-2.5 text-xs text-slate-800 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 shadow-2xs"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                            @click="optOutModalOpen = false">
                        Cancel
                    </button>
                    <button type="button" class="rounded-lg bg-amber-600 hover:bg-amber-700 text-white px-3 py-1.5 text-xs font-semibold shadow-2xs"
                            :disabled="!optOutReason.trim() || busy"
                            @click="submitOptOut">
                        Confirm Opt-Out & Roll Down
                    </button>
                </div>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: { type: String, default: '' },
    event: Object,
    program: Object,
    summary: { type: Array, default: () => [] },
    actionUrls: Object,
    schoolEntryUrl: String,
});

const statusLabels = {
    complete: 'Complete',
    pending: 'Awaiting school response',
    not_offered: 'Offers not sent',
    attention: 'Needs attention',
    unfilled: 'Unfilled slots',
    awaiting_eligibility: 'Awaiting results / entries',
    not_open: 'Not opened',
};

const statusClasses = {
    complete: 'bg-emerald-100 text-emerald-800',
    pending: 'bg-amber-100 text-amber-800',
    not_offered: 'bg-indigo-100 text-indigo-800',
    attention: 'bg-rose-100 text-rose-800',
    unfilled: 'bg-orange-100 text-orange-800',
    awaiting_eligibility: 'bg-slate-100 text-slate-600',
    not_open: 'bg-slate-100 text-slate-600',
};

const priority = { attention: 0, not_offered: 1, pending: 2, unfilled: 3, awaiting_eligibility: 4, not_open: 5, complete: 6 };

const statusFilter = ref('all');
const categoryFilter = ref('all');
const search = ref('');
const sort = ref('attention');
const copied = ref(false);
const slotQuotas = reactive({});
const savingSlots = ref(false);
const opening = ref(false);
const approving = ref(false);
const hasChanges = ref(false);
const operationError = ref('');
const sendingOfferKey = ref('');

const optOutModalOpen = ref(false);
const optOutTarget = ref(null);
const optOutReason = ref('');

const busy = computed(() => savingSlots.value || opening.value || approving.value || Boolean(sendingOfferKey.value));

const counts = computed(() => props.summary.reduce((acc, row) => {
    acc[row.slot_status] = (acc[row.slot_status] || 0) + 1;
    return acc;
}, Object.fromEntries(Object.keys(statusLabels).map(key => [key, 0]))));

const totals = computed(() => props.summary.reduce((acc, row) => ({
    quota: acc.quota + row.quota,
    accepted: acc.accepted + row.slot_progress.accepted,
    pending: acc.pending + row.slot_progress.pending,
    unfilled: acc.unfilled + row.slot_progress.unfilled,
}), { quota: 0, accepted: 0, pending: 0, unfilled: 0 }));

const completionPercent = computed(() => totals.value.quota ? Math.min(100, Math.round(100 * totals.value.accepted / totals.value.quota)) : 0);
const canApprove = computed(() => !busy.value && !hasChanges.value && props.summary.length > 0 && !totals.value.pending && !counts.value.attention && !counts.value.not_offered);

const statCards = computed(() => [
    { key: 'all', filter: 'all', label: 'State items', count: props.summary.length, hint: `${totals.value.quota} available slots`, color: 'text-slate-900' },
    { key: 'complete', filter: 'complete', label: 'Complete', count: counts.value.complete, hint: 'All slots confirmed', color: 'text-emerald-700' },
    { key: 'pending', filter: 'pending', label: 'Waiting for schools', count: counts.value.pending, hint: `${totals.value.pending} responses pending`, color: 'text-amber-700' },
    { key: 'not_offered', filter: 'not_offered', label: 'Offers not sent', count: counts.value.not_offered, hint: 'Ready for sync', color: 'text-indigo-700' },
    { key: 'attention', filter: 'attention', label: 'Needs attention', count: counts.value.attention, hint: 'Ties or eligibility issues', color: 'text-rose-700' },
    { key: 'unfilled', filter: 'unfilled', label: 'Unfilled items', count: counts.value.unfilled, hint: `${counts.value.awaiting_eligibility} more awaiting results / entries`, color: 'text-orange-700' },
]);

const categories = computed(() => [...new Set(props.summary.map(row => row.category_label).filter(Boolean))].sort());

const visibleRows = computed(() => props.summary.filter(row => {
    if (statusFilter.value !== 'all' && row.slot_status !== statusFilter.value) return false;
    if (categoryFilter.value !== 'all' && row.category_label !== categoryFilter.value) return false;
    const needle = search.value.trim().toLowerCase();
    return !needle || [
        row.title, row.item_code, row.category_label, row.gender_label,
        ...row.candidates.flatMap(c => [c.student_name, c.school_name]),
        ...row.chosen.flatMap(c => [c.student_name, c.school_name]),
    ].filter(Boolean).join(' ').toLowerCase().includes(needle);
}).sort((a, b) => (sort.value === 'attention' ? (priority[a.slot_status] ?? 9) - (priority[b.slot_status] ?? 9) : 0) || String(a.item_code || '').localeCompare(String(b.item_code || ''), undefined, { numeric: true })));

const statusLabel = computed(() => props.event.state_slot_collection_approved_at ? 'Approved' : props.event.state_slot_collection_open ? 'School confirmations open' : 'Not opened');

function candidateKey(c) {
    return String(c.participant_id || c.registration_id || c.mark_id || Math.random());
}

function tiedCandidates(row) {
    if (!row.unresolved_ties || !row.unresolved_ties.length) return [];
    return (row.candidates || []).filter(c =>
        !c.is_chosen && !c.is_declined && row.unresolved_ties.includes(Number(c.source_position))
    );
}

const waitingFor = row => row.candidates.filter(c => !c.is_chosen && !c.is_declined);

function slotsFor(row) {
    return Array.from({ length: Math.max(0, row.quota) }, (_, index) => index < row.slot_progress.accepted
        ? { label: 'Confirmed', class: 'border-emerald-200 bg-emerald-50 text-emerald-800' }
        : index < row.slot_progress.accepted + row.slot_progress.pending
            ? { label: 'Awaiting response', class: 'border-amber-200 bg-amber-50 text-amber-800' }
            : index < row.slot_progress.accepted + row.slot_progress.pending + row.slot_progress.invalid
                ? { label: 'Needs review', class: 'border-rose-200 bg-rose-50 text-rose-800' }
                : { label: 'Unfilled', class: 'border-slate-200 bg-slate-50 text-slate-500' });
}

function resetFilters() {
    statusFilter.value = 'all';
    categoryFilter.value = 'all';
    search.value = '';
}

function initQuotas() {
    props.summary.forEach(row => {
        slotQuotas[row.item_id] = Number(row.quota) || 2;
    });
}
initQuotas();
watch(() => props.summary, () => {
    initQuotas();
    hasChanges.value = false;
});

function applyBatch(value) {
    props.summary.forEach(row => {
        if (row.quota > 0) slotQuotas[row.item_id] = value;
    });
    hasChanges.value = true;
}

function showErrors(errors) {
    operationError.value = Object.values(errors || {}).flat().join(' ');
}

function saveSlotQuotas(syncNow = false) {
    if (busy.value) return;
    savingSlots.value = true;
    operationError.value = '';
    router.post(props.actionUrls.updateSlots, { slots: { ...slotQuotas }, sync_now: syncNow }, {
        preserveScroll: true,
        onError: showErrors,
        onSuccess: () => { hasChanges.value = false; },
        onFinish: () => { savingSlots.value = false; },
    });
}

function open() {
    if (busy.value || !window.confirm('Open school confirmations? Eligible entries will be offered within each item’s State quota.')) return;
    opening.value = true;
    operationError.value = '';
    const sendOffers = () => router.post(props.actionUrls.open, {}, {
        preserveScroll: true,
        onError: showErrors,
        onFinish: () => { opening.value = false; },
    });
    if (hasChanges.value) {
        router.post(props.actionUrls.updateSlots, { slots: { ...slotQuotas } }, {
            preserveScroll: true,
            onSuccess: sendOffers,
            onError: errors => {
                showErrors(errors);
                opening.value = false;
            },
            onCancel: () => { opening.value = false; },
        });
    } else {
        sendOffers();
    }
}

function approve() {
    if (!canApprove.value || !window.confirm(`Approve the confirmed list? ${totals.value.unfilled} slot(s) remain unfilled. State submission is the next step.`)) return;
    approving.value = true;
    operationError.value = '';
    router.post(props.actionUrls.approve, {}, {
        preserveScroll: true,
        onError: showErrors,
        onFinish: () => { approving.value = false; },
    });
}

function sendOffer(row, candidate) {
    if (busy.value) return;
    const key = candidateKey(candidate);
    sendingOfferKey.value = key;
    operationError.value = '';
    router.post(props.actionUrls.sendOffer, { candidate }, {
        preserveScroll: true,
        onError: showErrors,
        onFinish: () => { sendingOfferKey.value = ''; },
    });
}

function openOptOutModal(selection, row) {
    optOutTarget.value = selection;
    optOutReason.value = '';
    optOutModalOpen.value = true;
}

function submitOptOut() {
    if (!optOutTarget.value || !optOutReason.value.trim()) {
        alert('Please provide a reason for opting out.');
        return;
    }
    const sel = optOutTarget.value;
    optOutModalOpen.value = false;
    alterSelection(sel, 'opt_out', optOutReason.value.trim());
}

function alterSelection(selection, action, reason = null) {
    if (busy.value) return;
    if (action === 'revoke' && !window.confirm(`Revoke the offer for ${selection.student_name}? The slot will become unfilled.`)) {
        return;
    }
    if (action === 'accept' && !window.confirm(`Confirm offer on behalf of ${selection.school_name} for ${selection.student_name}?`)) {
        return;
    }
    operationError.value = '';
    router.post(`${props.actionUrls.alterSelection}/${selection.id}/alter`, {
        action,
        reason,
    }, {
        preserveScroll: true,
        onError: showErrors,
    });
}

async function copySchoolLink() {
    try {
        await navigator.clipboard.writeText(props.schoolEntryUrl);
        copied.value = true;
    } catch {
        window.prompt('Copy the school submission link:', props.schoolEntryUrl);
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '';
}
</script>
