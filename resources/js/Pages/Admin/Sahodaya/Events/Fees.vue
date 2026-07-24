<template>
    <SahodayaEventsLayout :title="`${event.title} — Event Fees`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false" max-width="w-full max-w-[98rem]">
        
        <!-- Executive Header with Actions -->
        <PageHeader :title="`${event.title} — Event Fees`" eyebrow="Event Fee Ledger &amp; Submissions"
                    description="Review school fee submissions, payment proofs, and approval status.">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/finance`" class="btn-secondary text-xs">
                        School Invoices →
                    </Link>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/fees/ledger`" class="btn-primary text-xs">
                        Payment Ledger →
                    </Link>
                    <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/fees/pdf?preview=1`" target="_blank" class="btn-primary text-xs">
                        <span>📄 Fee Report PDF ↗</span>
                    </a>
                    <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/fees/pdf?download=1`" class="btn-secondary text-xs">
                        Download PDF ↓
                    </a>
                    <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/fees/export`" class="btn-secondary text-xs">
                        Export CSV ↓
                    </a>
                </div>
            </template>
        </PageHeader>

        <!-- Header Navigation Bar -->
        <SportsSetupSubNav v-if="event.event_type === 'sports'"
                           :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="fees" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="fees" class="mb-4" />

        <!-- Guidance Banner Card -->
        <div class="mb-5 rounded-xl border border-indigo-200/80 bg-indigo-50/50 p-4 text-xs text-indigo-950 shadow-xs space-y-1.5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-bold text-indigo-900 flex items-center gap-1.5 text-sm">
                    <span>💳</span> Fest Event Fees Ledger
                </p>
                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 font-bold text-indigo-800 text-[11px] border border-indigo-200">
                    {{ levelLabel }}
                </span>
            </div>
            <p class="text-indigo-900/80 leading-relaxed">
                Participation and item charges for this event. Approved fee payments post directly to the event ledger head, separate from Sahodaya annual membership.
                <template v-if="summary.fee_model === 'item_catalog'"> Billing model: <strong>Item catalog billing</strong> (age group / category / per-item rates).</template>
                <template v-else-if="summary.fee_model === 'cksc_tiered'"> Billing model: <strong>Tiered per-item participation fees</strong>.</template>
                <template v-else-if="summary.fee_model === 'sports_composite'"> Billing model: <strong>Sports composite billing</strong> (school reg + per-athlete + team fees).</template>
                <template v-else-if="summary.fee_model === 'none'"> Billing model: <strong>No event fee configured</strong>.</template>
            </p>
        </div>

        <!-- 4 Executive KPI Metric Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card !p-5 border border-slate-200/90 bg-white shadow-xs hover:shadow transition rounded-xl">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Event Due</p>
                    <span class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600 text-sm">💰</span>
                </div>
                <p class="text-2xl lg:text-3xl font-black text-slate-900 mt-2 tabular-nums">₹{{ fmt(summary.total_due) }}</p>
                <p class="text-xs text-slate-500 mt-1 font-medium">Across {{ summary.total_schools || rows.length }} registered schools</p>
            </div>

            <div class="card !p-5 border border-emerald-200/90 bg-gradient-to-br from-emerald-50/60 to-emerald-100/20 shadow-xs hover:shadow transition rounded-xl">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Collected &amp; Settled</p>
                    <span class="w-8 h-8 rounded-lg bg-emerald-100/80 flex items-center justify-center text-emerald-700 text-sm">✓</span>
                </div>
                <p class="text-2xl lg:text-3xl font-black text-emerald-700 mt-2 tabular-nums">₹{{ fmt(summary.total_paid) }}</p>
                <div class="flex items-center gap-2 mt-1">
                    <span class="text-xs font-bold text-emerald-800">
                        {{ summary.total_due > 0 ? Math.round((summary.total_paid / summary.total_due) * 100) : 0 }}% collected
                    </span>
                    <span class="text-[11px] text-emerald-700/70 font-medium">({{ summary.approved || 0 }} schools)</span>
                </div>
            </div>

            <div class="card !p-5 border border-amber-200/90 bg-gradient-to-br from-amber-50/60 to-amber-100/20 shadow-xs hover:shadow transition rounded-xl">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-amber-800">Proof Not Uploaded</p>
                    <span class="w-8 h-8 rounded-lg bg-amber-100/80 flex items-center justify-center text-amber-700 text-sm">⚠️</span>
                </div>
                <p class="text-2xl lg:text-3xl font-black text-amber-700 mt-2 tabular-nums">{{ summary.pending }}</p>
                <p class="text-xs text-amber-800/80 mt-1 font-medium">Schools awaiting payment proof</p>
            </div>

            <div class="card !p-5 border border-indigo-200/90 bg-gradient-to-br from-indigo-50/60 to-indigo-100/20 shadow-xs hover:shadow transition rounded-xl">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-800">Awaiting Review</p>
                    <span class="w-8 h-8 rounded-lg bg-indigo-100/80 flex items-center justify-center text-indigo-700 text-sm">📑</span>
                </div>
                <p class="text-2xl lg:text-3xl font-black text-indigo-700 mt-2 tabular-nums">{{ summary.awaiting }}</p>
                <p class="text-xs text-indigo-800/80 mt-1 font-medium">Payment proofs requiring approval</p>
            </div>
        </div>

        <!-- Filter Chips Bar & Search Toolbar -->
        <div class="card !p-5 space-y-4 mb-6 shadow-xs border border-slate-200/80 bg-white rounded-xl">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                <!-- Status Filter Chips -->
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mr-1.5">Filter</span>
                    <button v-for="opt in statusFilterOptions" :key="opt.value" type="button" @click="statusFilter = opt.value"
                            :class="statusFilter === opt.value
                                ? 'bg-slate-900 text-white font-bold shadow-xs'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold'"
                            class="px-3 py-1.5 rounded-full transition whitespace-nowrap">
                        {{ opt.label }} <span class="opacity-75 tabular-nums">({{ opt.count }})</span>
                    </button>
                </div>

                <!-- Search Input & Quick Actions -->
                <div class="relative flex items-center gap-2 flex-1 min-w-[16rem] max-w-md ml-auto">
                    <input v-model="search" type="search" placeholder="Search school, item, receipt #, UTR..."
                           class="field text-xs !py-2 pl-7 pr-7 flex-1 shadow-xs" autocomplete="off">
                    <button v-if="search" type="button" @click="search = ''"
                            class="absolute right-24 text-xs text-slate-400 hover:text-slate-700 font-bold p-0.5" title="Clear search">✕</button>
                    <span class="text-xs text-slate-500 whitespace-nowrap tabular-nums shrink-0 font-semibold">
                        {{ filteredRows.length }} of {{ rows.length }} schools
                    </span>
                </div>
            </div>
        </div>

        <!-- Master School Fees Data Table -->
        <div class="card card--flush overflow-hidden shadow-xs border border-slate-200 rounded-xl mb-8">
            <div class="overflow-x-auto">
                <table class="data-table text-sm w-full">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-left text-xs uppercase font-bold tracking-wider">
                            <th class="p-3.5 w-12 text-center">#</th>
                            <th class="p-3.5 min-w-[14rem]">School Name</th>
                            <th class="p-3.5 min-w-[10rem]">Participation Overview</th>
                            <th class="p-3.5 min-w-[16rem]">Itemized Fee Breakdown</th>
                            <th class="p-3.5 w-32">Total Due</th>
                            <th class="p-3.5 w-36">Payment Status</th>
                            <th class="p-3.5 min-w-[13rem] text-right">Actions &amp; Proofs</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(row, idx) in filteredRows" :key="row.id"
                            :class="[
                                row.status === 'proof_uploaded' ? 'bg-amber-50/30 hover:bg-amber-50/50' : 'hover:bg-slate-50/70',
                                'transition'
                            ]">
                            <td class="p-3.5 text-center text-xs font-mono text-slate-400 font-semibold">
                                {{ idx + 1 }}
                            </td>
                            <td class="p-3.5">
                                <div class="font-bold text-slate-900 leading-snug">
                                    {{ row.school }}
                                </div>
                                <div v-if="row.head" class="text-[11px] font-semibold text-indigo-700 mt-0.5">
                                    Head: {{ row.head }}
                                </div>
                            </td>
                            <td class="p-3.5 text-xs">
                                <template v-if="event.event_type === 'sports' && row.sports_participation">
                                    <div class="flex flex-col gap-1 font-semibold">
                                        <span v-if="row.sports_participation.team_count > 0" class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-100">
                                            {{ row.sports_participation.team_count }} team ({{ row.sports_participation.team_students_count }} stud.)
                                        </span>
                                        <span v-if="row.sports_participation.indiv_count > 0" class="inline-flex items-center px-2 py-0.5 rounded bg-sky-50 text-sky-700 border border-sky-100">
                                            {{ row.sports_participation.indiv_count }} indiv. item{{ row.sports_participation.indiv_count === 1 ? '' : 's' }}
                                        </span>
                                        <span v-if="row.sports_participation.team_count === 0 && row.sports_participation.indiv_count === 0" class="text-slate-400 font-normal italic">
                                            No registered items
                                        </span>
                                    </div>
                                </template>
                                <template v-else>
                                    <span class="font-bold text-slate-800 inline-flex items-center px-2.5 py-0.5 rounded bg-slate-100 text-slate-700">
                                        {{ row.participation_item_count }} item(s) registered
                                    </span>
                                </template>
                            </td>
                            <td class="p-3.5">
                                <div v-if="row.breakdown?.items?.length" class="space-y-1">
                                    <div v-for="(b, bIdx) in row.breakdown.items" :key="bIdx"
                                         class="flex items-center justify-between gap-3 text-[11px] py-0.5 border-b border-slate-100 last:border-0">
                                        <span class="text-slate-700 font-medium truncate max-w-[14rem]">{{ b.label }}</span>
                                        <span class="font-bold text-slate-900 shrink-0 tabular-nums">₹{{ fmt(b.amount) }}</span>
                                    </div>
                                </div>
                                <div v-else class="text-slate-400 italic text-[11px]">No items configured</div>

                                <details v-if="row.item_allocation?.length" class="mt-1.5">
                                    <summary class="text-[10px] font-bold text-indigo-700 cursor-pointer select-none">
                                        Payment coverage ({{ row.item_allocation.filter(a => a.covered).length }}/{{ row.item_allocation.length }} covered)
                                    </summary>
                                    <div class="mt-1 space-y-0.5">
                                        <div v-for="a in row.item_allocation" :key="a.registration_id"
                                             class="flex items-center justify-between gap-3 text-[11px]">
                                            <span :class="a.covered ? 'text-slate-700' : 'text-amber-700 font-semibold'">
                                                {{ a.covered ? '✓' : '○' }} {{ a.item_title ?? 'Item' }}
                                            </span>
                                            <span class="shrink-0 tabular-nums" :class="a.covered ? 'text-slate-600' : 'text-amber-700 font-bold'">
                                                ₹{{ fmt(a.amount) }}
                                            </span>
                                        </div>
                                    </div>
                                </details>
                            </td>
                            <td class="p-3.5">
                                <span class="font-black text-slate-900 text-base tabular-nums">
                                    ₹{{ fmt(row.total_due) }}
                                </span>
                            </td>
                            <td class="p-3.5 space-y-1">
                                <span v-if="isNoFeeDue(row)" class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                    No fee due
                                </span>
                                <span v-else-if="row.status === 'approved'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 shadow-xs">
                                    <span>✓</span> Approved
                                </span>
                                <span v-else-if="row.status === 'proof_uploaded'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300 animate-pulse shadow-xs">
                                    <span>⏳</span> Awaiting approval
                                </span>
                                <span v-else-if="row.status === 'partial'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-sky-100 text-sky-800 border border-sky-200"
                                      title="Amount paid is less than the current total due.">
                                    <span>◐</span> Partial
                                </span>
                                <span v-else-if="row.status === 'rejected'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-200"
                                      :title="row.fee_receipt?.rejection_reason">
                                    <span>✕</span> Rejected
                                </span>
                                <span v-else class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                    Pending proof
                                </span>

                                <p v-if="row.status === 'partial'" class="text-[10px] font-semibold text-sky-700 mt-0.5">
                                    ₹{{ fmt(row.amount_paid) }} paid of ₹{{ fmt(row.total_due) }}
                                </p>
                                <p v-if="row.status === 'rejected' && row.fee_receipt?.rejection_reason"
                                   class="text-[10px] font-medium text-rose-600 mt-0.5 max-w-[12rem]">
                                    Reason: {{ row.fee_receipt.rejection_reason }}
                                </p>
                                <p v-if="row.available_credit > 0"
                                   class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 mt-1">
                                    Credit owed: ₹{{ fmt(row.available_credit) }}
                                </p>
                            </td>

                            <!-- Action Column: Multi-Proof Management -->
                            <td class="p-3.5 text-right space-y-1">
                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                    <!-- Proofs Modal Trigger — counts only real school-uploaded
                                         proofs, not system credit-offset placeholder records
                                         (is_system_credit), so a school that never uploaded
                                         anything but had a fee credit auto-applied doesn't
                                         show a misleading "Proofs N" badge while its status is
                                         still (correctly) "Not uploaded yet". -->
                                    <button v-if="realProofsCount(row) > 0" type="button"
                                            @click="activeProofModalRow = row"
                                            :class="row.status === 'proof_uploaded'
                                                ? 'bg-amber-500 hover:bg-amber-600 text-white font-bold animate-pulse shadow-xs'
                                                : 'bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold border border-indigo-200 shadow-2xs'"
                                            class="px-2.5 py-1 rounded-lg text-xs inline-flex items-center gap-1.5 transition">
                                        <span>📷 Proofs</span>
                                        <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-black/10 font-black">
                                            {{ realProofsCount(row) }}
                                        </span>
                                    </button>
                                    <a v-else-if="row.fee_receipt?.file_path && !row.fee_receipt?.is_system_credit"
                                       :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/school-fees/${row.id}/proof`"
                                       target="_blank" rel="noopener"
                                       class="px-2.5 py-1 rounded-lg text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold border border-indigo-200 transition">
                                        View proof ↗
                                    </a>
                                    <!-- No real proof on file, but a credit was auto-applied — a
                                         distinct, non-misleading indicator instead of "Proofs".
                                         Still opens the same modal so the credit is auditable. -->
                                    <button v-else-if="row.all_receipts?.length" type="button"
                                            @click="activeProofModalRow = row"
                                            title="No uploaded payment proof — an existing fee credit was applied toward this balance"
                                            class="px-2.5 py-1 rounded-lg text-xs bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold border border-amber-200 transition inline-flex items-center gap-1.5">
                                        <span>💳 Credit applied</span>
                                    </button>

                                    <!-- Recalculate Icon Button -->
                                    <button v-if="!isNoFeeDue(row)" type="button" @click="recalculateFee(row.id, row.school)"
                                            title="Recalculate fee from current registrations"
                                            class="p-1.5 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 border border-slate-200 transition text-xs">
                                        🔄
                                    </button>
                                </div>

                                <!-- Action Buttons Based on Status -->
                                <div v-if="row.status === 'proof_uploaded'" class="flex items-center justify-end gap-1 pt-1">
                                    <button type="button" @click="approve(row.id, row.school)"
                                            class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition">
                                        Approve ✓
                                    </button>
                                    <button type="button" @click="reject(row.id, row.school)"
                                            class="px-2 py-1 rounded-md text-[11px] font-semibold text-rose-700 hover:bg-rose-50 border border-rose-200 transition">
                                        Reject
                                    </button>
                                </div>

                                <div v-else-if="row.status === 'rejected'" class="flex items-center justify-end pt-1">
                                    <button type="button" @click="approve(row.id, row.school)"
                                            class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition">
                                        Re-approve / Verify ✓
                                    </button>
                                </div>

                                <div v-else-if="row.status === 'partial'" class="flex items-center justify-end pt-1">
                                    <button type="button" @click="forceApprove(row)"
                                            title="Waives the gap between total due and amount paid, then approves."
                                            class="px-2 py-0.5 rounded-md text-[10px] font-bold text-sky-700 bg-sky-50 hover:bg-sky-100 border border-sky-200 transition">
                                        Force approve (waive ₹{{ partialShortfall(row) }})
                                    </button>
                                </div>

                                <!-- Approved Receipt Badge & Reversal Link -->
                                <div v-if="row.fee_receipt?.receipt_number && row.fee_receipt?.status === 'approved'"
                                     class="flex items-center justify-end gap-2 pt-0.5">
                                    <a :href="`/sahodaya-admin/${sahodaya.id}/finance/payments/receipts/${row.fee_receipt.id}`"
                                       target="_blank" rel="noopener"
                                       title="View & print official fee receipt"
                                       class="text-[11px] font-mono font-bold text-emerald-700 hover:text-emerald-900 underline decoration-emerald-300 hover:decoration-emerald-600 transition">
                                        #{{ row.fee_receipt.receipt_number }} ↗
                                    </a>
                                    <button type="button" @click="reject(row.id, row.school)"
                                            title="Reverse or reject this approved receipt if needed"
                                            class="text-[10px] font-semibold text-slate-400 hover:text-rose-600 transition">
                                        Reverse
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!filteredRows.length">
                            <td colspan="7" class="p-12 text-center text-slate-400">
                                <p class="text-sm font-medium">{{ rows.length ? 'No schools match this filter/search.' : 'No school event fees yet.' }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Multi-Proof History & Approval Modal -->
        <div v-if="activeProofModalRow" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-xs" @click="activeProofModalRow = null"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border border-slate-200">
                <!-- Modal Header -->
                <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Payment Proofs &amp; Approval</span>
                        <h3 class="text-base font-bold text-white mt-0.5">{{ activeProofModalRow.school }}</h3>
                    </div>
                    <button type="button" @click="activeProofModalRow = null" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
                </div>

                <!-- Financial Summary Bar -->
                <div class="px-6 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between text-xs">
                    <div>
                        <span class="text-slate-500">Total Due:</span>
                        <strong class="text-slate-900 ml-1">₹{{ fmt(activeProofModalRow.total_due) }}</strong>
                    </div>
                    <div>
                        <span class="text-slate-500">Approved Paid:</span>
                        <strong class="text-emerald-700 ml-1">₹{{ fmt(activeProofModalRow.amount_paid) }}</strong>
                    </div>
                    <div>
                        <span class="text-slate-500">Status:</span>
                        <span class="font-bold uppercase ml-1 px-2 py-0.5 rounded text-[10px]"
                              :class="activeProofModalRow.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : (activeProofModalRow.status === 'proof_uploaded' ? 'bg-amber-100 text-amber-900 font-black' : 'bg-slate-100 text-slate-700')">
                            {{ activeProofModalRow.status }}
                        </span>
                    </div>
                </div>

                <!-- Proofs List -->
                <div class="p-6 max-h-[60vh] overflow-y-auto space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Uploaded Payment Proofs ({{ realProofsCount(activeProofModalRow) }})
                    </h4>

                    <div v-for="(rc, idx) in (activeProofModalRow.all_receipts || [])" :key="rc.id"
                         class="p-4 rounded-xl border transition shadow-xs"
                         :class="rc.is_system_credit ? 'border-amber-200 bg-amber-50/40' : (rc.status === 'uploaded' ? 'border-amber-300 bg-amber-50/50 ring-2 ring-amber-200' : (rc.status === 'approved' ? 'border-emerald-200 bg-emerald-50/30' : 'border-slate-200 bg-white'))">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-900">
                                        {{ rc.is_system_credit ? '💳 Fee credit applied' : `Proof Upload #${activeProofModalRow.all_receipts.length - idx}` }}
                                    </span>
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded"
                                          :class="rc.is_system_credit ? 'bg-amber-100 text-amber-800' : (rc.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : (rc.status === 'uploaded' ? 'bg-amber-100 text-amber-900 font-bold' : 'bg-slate-100 text-slate-600'))">
                                        {{ rc.is_system_credit ? 'Not an uploaded proof' : (rc.status === 'uploaded' ? 'Awaiting Review' : rc.status) }}
                                    </span>
                                    <span v-if="rc.receipt_number" class="text-xs font-mono font-bold text-emerald-700">
                                        #{{ rc.receipt_number }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">
                                    <template v-if="rc.is_system_credit">
                                        Automatically offset from an existing fee credit on {{ formatCalendarDate(rc.created_at) }} — does not
                                        cover the school's full balance if any amount is still shown as due.
                                    </template>
                                    <template v-else>
                                        Uploaded on {{ formatCalendarDate(rc.created_at) }}
                                        <span v-if="rc.transaction_ref" class="ml-2 font-mono">Ref: {{ rc.transaction_ref }}</span>
                                    </template>
                                </p>
                                <p v-if="rc.rejection_reason" class="text-xs text-rose-600 font-medium mt-1">
                                    Rejection Reason: {{ rc.rejection_reason }}
                                </p>
                            </div>

                            <div class="text-right">
                                <span class="text-base font-black text-slate-900 tabular-nums">₹{{ fmt(rc.amount) }}</span>
                                <div class="mt-2 flex items-center justify-end gap-2 flex-wrap">
                                    <a v-if="rc.proof_url" :href="rc.proof_url" target="_blank" rel="noopener"
                                       class="btn-secondary !py-1 !px-2.5 text-xs text-indigo-700 font-bold shadow-xs">
                                        View Image ↗
                                    </a>
                                    <!-- Extra images for this same payment (multi-image upload). -->
                                    <a v-for="(att, ai) in (rc.attachments || [])" :key="att.id"
                                       :href="att.url" target="_blank" rel="noopener"
                                       class="btn-secondary !py-1 !px-2.5 text-xs text-indigo-600 font-semibold shadow-xs">
                                        +{{ ai + 1 }} ↗
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-slate-100 border-t border-slate-200 flex items-center justify-between">
                    <button type="button" @click="activeProofModalRow = null" class="btn-secondary text-xs">Close</button>

                    <div class="flex items-center gap-2">
                        <button v-if="activeProofModalRow.status === 'proof_uploaded' || activeProofModalRow.status === 'rejected'" type="button"
                                @click="approve(activeProofModalRow.id, activeProofModalRow.school)" class="btn-primary !bg-emerald-600 hover:!bg-emerald-500 text-xs shadow-xs">
                            {{ activeProofModalRow.status === 'rejected' ? 'Re-approve / Verify Payment ✓' : 'Approve Pending Proof ✓' }}
                        </button>
                        <button v-if="activeProofModalRow.status === 'proof_uploaded' || activeProofModalRow.status === 'approved' || activeProofModalRow.status === 'partial'" type="button"
                                @click="reject(activeProofModalRow.id, activeProofModalRow.school)" class="btn-secondary text-xs text-rose-700 hover:bg-rose-50 shadow-xs">
                            {{ activeProofModalRow.status === 'approved' || activeProofModalRow.status === 'partial' ? 'Reject / Reverse Payment ✕' : 'Reject Pending Proof' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { router, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, rows: Array, summary: Object, levelLabel: String, feeSchedule: Object,
    activityLogs: { type: Array, default: () => [] },
});

const search = ref('');
const activeProofModalRow = ref(null);

function fmt(n) {
    return Number(n ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function isNoFeeDue(row) {
    return Number(row.total_due) === 0 && row.status === 'approved';
}

function hasRegisteredItems(row) {
    if (props.event.event_type === 'sports' && row.sports_participation) {
        return (row.sports_participation.team_count > 0 || row.sports_participation.indiv_count > 0);
    }
    return (row.participation_item_count > 0) || (row.items && row.items.length > 0) || Number(row.total_due) > 0;
}

function isUnpaidPending(row) {
    return row.status === 'pending' && !isNoFeeDue(row);
}

/**
 * Real, school-uploaded proofs only — excludes system credit-offset placeholder receipts
 * (is_system_credit) created by FestSchoolEventFeeService::applyAvailableCredit(). Those
 * carry a fake file_path and inflate all_receipts.length without representing anything a
 * school actually submitted, which previously made "Not uploaded yet" rows show a
 * "Proofs N" badge that looked contradictory.
 */
function realProofsCount(row) {
    return (row.all_receipts ?? []).filter(r => !r.is_system_credit).length;
}

const statusFilter = ref('all');
const statusFilterOptions = computed(() => {
    const rows = props.rows;
    const activeRows = rows.filter(hasRegisteredItems);
    return [
        { value: 'all', label: 'Registered schools', count: activeRows.filter(r => !isUnpaidPending(r)).length },
        { value: 'proof_uploaded', label: 'Awaiting approval', count: activeRows.filter(r => r.status === 'proof_uploaded').length },
        { value: 'partial', label: 'Partial', count: activeRows.filter(r => r.status === 'partial').length },
        { value: 'approved', label: 'Approved', count: activeRows.filter(r => r.status === 'approved' && !isNoFeeDue(r)).length },
        { value: 'rejected', label: 'Rejected', count: activeRows.filter(r => r.status === 'rejected').length },
        { value: 'pending', label: 'Not uploaded yet', count: activeRows.filter(isUnpaidPending).length },
        { value: 'everything', label: 'All schools (incl. 0 items)', count: rows.length },
    ];
});

const filteredRows = computed(() => {
    let rows = props.rows;

    if (statusFilter.value === 'all') {
        rows = rows.filter(r => hasRegisteredItems(r) && !isUnpaidPending(r));
    } else if (statusFilter.value === 'pending') {
        rows = rows.filter(r => hasRegisteredItems(r) && isUnpaidPending(r));
    } else if (statusFilter.value === 'approved') {
        rows = rows.filter(r => hasRegisteredItems(r) && r.status === 'approved' && !isNoFeeDue(r));
    } else if (statusFilter.value !== 'everything') {
        rows = rows.filter(r => hasRegisteredItems(r) && r.status === statusFilter.value);
    }

    const q = search.value.trim().toLowerCase();
    if (q) {
        rows = rows.filter(row => {
            const schoolName = (row.school ?? '').toLowerCase();
            const headName = (row.head ?? '').toLowerCase();
            const itemsList = (row.items ?? []).join(' ').toLowerCase();
            const breakdownList = (row.breakdown?.items ?? []).map(b => b.label ?? '').join(' ').toLowerCase();
            const receiptNo = (row.fee_receipt?.receipt_number ?? '').toLowerCase();
            const txRef = (row.fee_receipt?.transaction_ref ?? '').toLowerCase();
            const statusStr = (row.status ?? '').toLowerCase();
            const allReceiptsSearch = (row.all_receipts ?? []).map(r => `${r.receipt_number ?? ''} ${r.transaction_ref ?? ''}`).join(' ').toLowerCase();

            return schoolName.includes(q)
                || headName.includes(q)
                || itemsList.includes(q)
                || breakdownList.includes(q)
                || receiptNo.includes(q)
                || txRef.includes(q)
                || statusStr.includes(q)
                || allReceiptsSearch.includes(q);
        });
    }

    return rows;
});

function approve(id, schoolName = '') {
    const targetName = schoolName || activeProofModalRow.value?.school || 'this school';
    if (!confirm(`Are you sure you want to approve/verify the fee payment for "${targetName}"?`)) {
        return;
    }
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/school-fees/${id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: () => { activeProofModalRow.value = null; },
    });
}

function reject(id, schoolName = '') {
    const targetName = schoolName || activeProofModalRow.value?.school || 'this school';
    const reason = prompt(`Rejection / Reversal confirmation for "${targetName}".\n\nPlease enter reason for rejecting or reversing this payment (optional):`);
    if (reason === null) return;

    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/school-fees/${id}/reject`, {
        rejection_reason: reason,
    }, {
        preserveScroll: true,
        onSuccess: () => { activeProofModalRow.value = null; },
    });
}

function recalculateFee(id, schoolName = '') {
    if (!confirm(`Recalculate event fee for "${schoolName || 'this school'}" from current registered items?`)) {
        return;
    }
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/school-fees/${id}/recalculate`, {}, { preserveScroll: true });
}

function partialShortfall(row) {
    return fmt(Math.max(0, Number(row.total_due) - Number(row.amount_paid ?? 0)));
}

function forceApprove(row) {
    const reason = prompt(
        `CONFIRM FORCE APPROVAL for "${row.school}":\n\n`
        + `This waives ₹${partialShortfall(row)} (the gap between total due and amount paid) and approves the school's registrations.\n`
        + `Only do this if the uploaded receipt genuinely covers their current items. Reason (required):`
    );
    if (!reason) return;

    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/school-fees/${row.id}/force-approve`, {
        reason,
    }, { preserveScroll: true });
}
</script>
