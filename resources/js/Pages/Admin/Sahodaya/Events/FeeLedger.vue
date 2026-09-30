<template>
    <SahodayaEventsLayout :title="`${event.title} — Payment Ledger`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false"
                         max-width="w-full max-w-[98rem]">
        <!-- Page Header -->
        <PageHeader :title="`${event.title} — Payment Ledger`" eyebrow="Event Finance"
                    description="School fee collections, reconciliation status, and general ledger journal postings for this event.">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/fees`" class="btn-secondary text-xs">
                        ← Event Fees
                    </Link>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/finance`" class="btn-secondary text-xs">
                        School Invoices →
                    </Link>
                    <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/fees/pdf?preview=1`" target="_blank" class="btn-primary text-xs">
                        <span>📄 Fee Report PDF ↗</span>
                    </a>
                    <a :href="exportUrl" class="btn-secondary text-xs">
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

        <!-- Ledger Account Head Information Card -->
        <div class="mb-6 rounded-xl border border-indigo-200/90 bg-gradient-to-r from-indigo-50/80 via-white to-indigo-50/40 p-4 shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-lg font-bold shadow-xs">
                        🏛️
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-800">Ledger Account Head</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-indigo-100 text-indigo-900 border border-indigo-200">
                                {{ accountCode }}
                            </span>
                        </div>
                        <h2 class="text-sm font-bold text-slate-900 mt-0.5">{{ accountName }}</h2>
                    </div>
                </div>

                <div class="flex items-center gap-2 text-xs">
                    <button type="button" @click="copyAccountCode"
                            class="px-2.5 py-1 rounded-lg border border-indigo-200 bg-white text-indigo-700 hover:bg-indigo-50 font-semibold transition flex items-center gap-1.5 shadow-2xs">
                        <span>{{ copiedCode ? '✓ Copied' : '📋 Copy Code' }}</span>
                    </button>
                    <span v-if="levelLabel" class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 font-bold text-slate-700 text-[11px] border border-slate-200">
                        {{ levelLabel }}
                    </span>
                </div>
            </div>
            <p class="text-xs text-slate-600 mt-2.5 leading-relaxed border-t border-indigo-100/70 pt-2">
                Approved school event fees credit directly to this account in the general ledger (separate from Sahodaya annual membership, Talent Search exams, and training programs).
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
                <p class="text-xs text-slate-500 mt-1 font-medium">Across {{ summary.total_schools || schoolPayments.length }} registered schools</p>
            </div>

            <div class="card !p-5 border border-emerald-200/90 bg-gradient-to-br from-emerald-50/60 to-emerald-100/20 shadow-xs hover:shadow transition rounded-xl">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Applied to Current Dues</p>
                    <span class="w-8 h-8 rounded-lg bg-emerald-100/80 flex items-center justify-center text-emerald-700 text-sm">✓</span>
                </div>
                <p class="text-2xl lg:text-3xl font-black text-emerald-700 mt-2 tabular-nums">₹{{ fmt(summary.collected) }}</p>
                <div class="flex items-center gap-2 mt-1">
                    <span class="text-xs font-bold text-emerald-800">
                        {{ summary.total_due > 0 ? Math.min(100, Math.round((summary.collected / summary.total_due) * 100)) : 0 }}% settled
                    </span>
                    <span class="text-[11px] text-emerald-700/70 font-medium">({{ summary.approved || 0 }} schools)</span>
                </div>
                <p v-if="Number(summary.gross_receipts || 0) > Number(summary.collected || 0)"
                   class="mt-1 text-[11px] font-medium text-emerald-800/75">
                    Gross approved receipts: ₹{{ fmt(summary.gross_receipts) }}
                </p>
            </div>

            <div class="card !p-5 border border-indigo-200/90 bg-gradient-to-br from-indigo-50/60 to-indigo-100/20 shadow-xs hover:shadow transition rounded-xl">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-800">Posted to Ledger</p>
                    <span class="w-8 h-8 rounded-lg bg-indigo-100/80 flex items-center justify-center text-indigo-700 text-sm">📒</span>
                </div>
                <p class="text-2xl lg:text-3xl font-black text-indigo-700 mt-2 tabular-nums">₹{{ fmt(summary.ledger_credits) }}</p>
                <p class="text-xs text-indigo-800/80 mt-1 font-medium">{{ transactions.length }} journal credits recorded</p>
            </div>

            <div class="card !p-5 border border-amber-200/90 bg-gradient-to-br from-amber-50/60 to-amber-100/20 shadow-xs hover:shadow transition rounded-xl">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-amber-800">Pending Balance</p>
                    <span class="w-8 h-8 rounded-lg bg-amber-100/80 flex items-center justify-center text-amber-700 text-sm">⏳</span>
                </div>
                <p class="text-2xl lg:text-3xl font-black text-amber-700 mt-2 tabular-nums">₹{{ fmt(summary.pending_balance) }}</p>
                <p class="text-xs text-amber-800/80 mt-1 font-medium">
                    {{ summary.partial || 0 }} partial · {{ summary.pending || 0 }} awaiting proof
                </p>
            </div>
        </div>

        <!-- Overpayment / Reconciliation Alert Banner (if applicable) -->
        <div v-if="Number(summary.overpayment || 0) > 0"
             class="mb-6 rounded-xl border border-amber-300 bg-amber-50/90 p-4 text-xs text-amber-950 flex flex-wrap items-center justify-between gap-3 shadow-2xs">
            <div class="space-y-1">
                <p class="font-bold flex items-center gap-1.5 text-sm text-amber-900">
                    <span>⚠️</span> Payment Reconciliation: ₹{{ fmt(summary.overpayment) }} Overpayment Detected
                </p>
                <p class="leading-relaxed text-amber-900/80">
                    Approved fee receipts exceed current event dues by <strong>₹{{ fmt(summary.overpayment) }}</strong> across {{ overpaidSchoolsCount }} school(s).
                    Unreconciled excess receipts can be converted into school credits under <strong>Payment Reconciliation</strong> or filtered immediately below.
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" @click="schoolStatusFilter = 'overpaid'; activeView = 'schools'"
                        class="px-3 py-1.5 rounded-lg bg-amber-100 text-amber-900 font-bold text-xs hover:bg-amber-200 border border-amber-300 transition">
                    Show {{ overpaidSchoolsCount }} Overpaid Schools
                </button>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/finance/payment-reconciliation?event_id=${event.id}`"
                      class="px-3 py-1.5 rounded-lg bg-amber-700 text-white font-bold text-xs hover:bg-amber-800 transition shadow-xs">
                    Reconcile &amp; Record Credit →
                </Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/finance/payments/credits`"
                      class="px-3 py-1.5 rounded-lg bg-white text-slate-700 font-semibold text-xs hover:bg-slate-50 border border-slate-200 transition">
                    Issued Credits Register ↗
                </Link>
            </div>
        </div>

        <!-- View Tabs Bar -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 mb-6">
            <div class="flex items-center gap-2">
                <button type="button" @click="activeView = 'schools'"
                        :class="activeView === 'schools'
                            ? 'border-indigo-600 text-indigo-700 font-bold border-b-2'
                            : 'border-transparent text-slate-500 hover:text-slate-800 font-semibold'"
                        class="pb-3 px-2 text-sm transition flex items-center gap-2">
                    <span>🏫 School Fee Collections</span>
                    <span class="px-2 py-0.5 rounded-full text-xs"
                          :class="activeView === 'schools' ? 'bg-indigo-100 text-indigo-800 font-bold' : 'bg-slate-100 text-slate-600'">
                        {{ schoolPayments.length }}
                    </span>
                </button>
                <button type="button" @click="activeView = 'transactions'"
                        :class="activeView === 'transactions'
                            ? 'border-indigo-600 text-indigo-700 font-bold border-b-2'
                            : 'border-transparent text-slate-500 hover:text-slate-800 font-semibold'"
                        class="pb-3 px-2 text-sm transition flex items-center gap-2">
                    <span>📑 Ledger Journal Transactions</span>
                    <span class="px-2 py-0.5 rounded-full text-xs"
                          :class="activeView === 'transactions' ? 'bg-indigo-100 text-indigo-800 font-bold' : 'bg-slate-100 text-slate-600'">
                        {{ transactions.length }}
                    </span>
                </button>
                <button type="button" @click="activeView = 'split'"
                        :class="activeView === 'split'
                            ? 'border-indigo-600 text-indigo-700 font-bold border-b-2'
                            : 'border-transparent text-slate-500 hover:text-slate-800 font-semibold'"
                        class="pb-3 px-2 text-sm transition hidden xl:flex items-center gap-1.5">
                    <span>⬌ Split Comparison</span>
                </button>
            </div>
        </div>

        <!-- Tab 1: School Fee Collections (Full Width) -->
        <div v-if="activeView === 'schools'" class="space-y-4">
            <!-- Filter & Search Toolbar -->
            <div class="card !p-4 bg-white border border-slate-200/90 rounded-xl shadow-xs space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <!-- Status Filter Chips -->
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mr-1.5">Status</span>
                        <button v-for="opt in schoolStatusFilterOptions" :key="opt.value" type="button"
                                @click="schoolStatusFilter = opt.value"
                                :class="schoolStatusFilter === opt.value
                                    ? 'bg-slate-900 text-white font-bold shadow-xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold'"
                                class="px-3 py-1.5 rounded-full transition whitespace-nowrap">
                            {{ opt.label }} <span class="opacity-75 tabular-nums">({{ opt.count }})</span>
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="relative flex items-center min-w-[16rem] max-w-sm">
                        <input v-model="schoolSearch" type="search" placeholder="Search school, receipt #, UTR..."
                               class="field text-xs !py-2 pl-7 pr-7 w-full shadow-2xs" autocomplete="off">
                        <button v-if="schoolSearch" type="button" @click="schoolSearch = ''"
                                class="absolute right-2 text-xs text-slate-400 hover:text-slate-700 font-bold p-0.5">✕</button>
                        <span class="absolute left-2.5 text-slate-400 text-xs">🔍</span>
                    </div>
                </div>
            </div>

            <!-- Schools Table -->
            <div class="card card--flush overflow-hidden bg-white border border-slate-200/90 rounded-xl shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 text-slate-700 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="p-3 text-center w-12">#</th>
                                <th class="p-3 text-left">School Name</th>
                                <th class="p-3 text-right">Total Due</th>
                                <th class="p-3 text-right">Amount Paid</th>
                                <th class="p-3 text-right">Balance Due</th>
                                <th class="p-3 text-center">Fee Status</th>
                                <th class="p-3 text-left">Receipts &amp; Details</th>
                                <th class="p-3 text-center">Ledger Posting</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, i) in filteredSchoolPayments" :key="row.id || i" class="hover:bg-slate-50/70 transition">
                                <td class="p-3 text-center font-semibold text-slate-400 tabular-nums">{{ i + 1 }}</td>
                                <td class="p-3">
                                    <div class="font-bold text-slate-900 text-sm">{{ row.school }}</div>
                                    <div v-if="row.head" class="text-[11px] text-slate-500 mt-0.5">Head: {{ row.head }}</div>
                                </td>
                                <td class="p-3 text-right font-bold text-slate-900 tabular-nums">
                                    ₹{{ fmt(row.total_due) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    <div class="font-bold text-emerald-700">₹{{ fmt(row.amount_paid) }}</div>
                                    <span v-if="Number(row.amount_paid || 0) - Number(row.total_due || 0) > 0.01"
                                          class="inline-flex items-center px-1.5 py-0.5 mt-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200"
                                          title="Receipts exceed total due">
                                        +₹{{ fmt(Number(row.amount_paid) - Number(row.total_due)) }} excess
                                    </span>
                                </td>
                                <td class="p-3 text-right font-bold tabular-nums"
                                    :class="row.balance_due > 0 ? 'text-rose-700' : 'text-slate-400'">
                                    {{ row.balance_due > 0 ? `₹${fmt(row.balance_due)}` : '₹0' }}
                                </td>
                                <td class="p-3 text-center">
                                    <span v-if="row.status === 'approved'"
                                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        ✓ Approved
                                    </span>
                                    <span v-else-if="row.status === 'partial'"
                                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                        ◐ Partial
                                    </span>
                                    <span v-else-if="row.status === 'proof_uploaded'"
                                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">
                                        📑 Proof Uploaded
                                    </span>
                                    <span v-else
                                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                        Pending
                                    </span>
                                </td>
                                <td class="p-3">
                                    <div v-if="row.receipts?.length" class="space-y-1">
                                        <div v-for="r in row.receipts" :key="r.id" class="text-[11px] flex flex-wrap items-center gap-1.5">
                                            <span class="font-mono font-bold text-slate-800">{{ r.receipt_number || r.transaction_ref || 'Receipt' }}</span>
                                            <span class="text-slate-600">₹{{ fmt(r.amount) }}</span>
                                            <span v-if="r.payment_date" class="text-slate-400 text-[10px]">({{ r.payment_date }})</span>
                                        </div>
                                    </div>
                                    <div v-else-if="row.receipt_number || row.transaction_ref" class="text-[11px]">
                                        <span class="font-mono font-bold text-slate-800">{{ row.receipt_number || row.transaction_ref }}</span>
                                        <span v-if="row.payment_date" class="text-slate-400 text-[10px] ml-1">({{ row.payment_date }})</span>
                                    </div>
                                    <span v-else class="text-slate-400 italic text-[11px]">No receipts</span>
                                </td>
                                <td class="p-3 text-center">
                                    <span v-if="row.ledger_posted"
                                          class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                        ✓ Posted
                                    </span>
                                    <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-50 text-slate-400 border border-slate-100">
                                        Unposted
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!filteredSchoolPayments.length">
                                <td colspan="8" class="p-12 text-center text-slate-400 font-medium">
                                    No school payments found matching your filter criteria.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="filteredSchoolPayments.length" class="bg-slate-50 font-bold border-t border-slate-200">
                            <tr>
                                <td colspan="2" class="p-3 text-right text-slate-600">Total ({{ filteredSchoolPayments.length }} schools):</td>
                                <td class="p-3 text-right font-black text-slate-900 tabular-nums">
                                    ₹{{ fmt(filteredSchoolPayments.reduce((s, r) => s + (r.total_due || 0), 0)) }}
                                </td>
                                <td class="p-3 text-right font-black text-emerald-700 tabular-nums">
                                    ₹{{ fmt(filteredSchoolPayments.reduce((s, r) => s + (r.amount_paid || 0), 0)) }}
                                </td>
                                <td class="p-3 text-right font-black text-rose-700 tabular-nums">
                                    ₹{{ fmt(filteredSchoolPayments.reduce((s, r) => s + (r.balance_due || 0), 0)) }}
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 2: Ledger Journal Transactions (Full Width) -->
        <div v-else-if="activeView === 'transactions'" class="space-y-4">
            <!-- Search & Toolbar -->
            <div class="card !p-4 bg-white border border-slate-200/90 rounded-xl shadow-xs space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Account Head:</span>
                        <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200">
                            {{ accountCode }}
                        </span>
                        <span class="text-xs text-slate-500 font-medium">— {{ accountName }}</span>
                    </div>

                    <div class="relative flex items-center min-w-[16rem] max-w-sm">
                        <input v-model="transactionSearch" type="search" placeholder="Search description, reference, date..."
                               class="field text-xs !py-2 pl-7 pr-7 w-full shadow-2xs" autocomplete="off">
                        <button v-if="transactionSearch" type="button" @click="transactionSearch = ''"
                                class="absolute right-2 text-xs text-slate-400 hover:text-slate-700 font-bold p-0.5">✕</button>
                        <span class="absolute left-2.5 text-slate-400 text-xs">🔍</span>
                    </div>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="card card--flush overflow-hidden bg-white border border-slate-200/90 rounded-xl shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 text-slate-700 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="p-3 text-center w-12">#</th>
                                <th class="p-3 text-left w-32">Date</th>
                                <th class="p-3 text-center w-24">Type</th>
                                <th class="p-3 text-left">Description / Reference</th>
                                <th class="p-3 text-right w-36">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(t, idx) in filteredTransactions" :key="t.id" class="hover:bg-slate-50/70 transition">
                                <td class="p-3 text-center font-semibold text-slate-400 tabular-nums">{{ idx + 1 }}</td>
                                <td class="p-3 text-slate-700 font-medium whitespace-nowrap">
                                    {{ formatCalendarDate(t.transaction_date) }}
                                </td>
                                <td class="p-3 text-center">
                                    <span v-if="t.entry_type === 'credit'"
                                          class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Credit
                                    </span>
                                    <span v-else
                                          class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-100 text-rose-800 border border-rose-200">
                                        Debit
                                    </span>
                                </td>
                                <td class="p-3">
                                    <div class="text-slate-900 font-medium">{{ t.description || 'Event fee receipt posting' }}</div>
                                    <div v-if="t.reference_id" class="text-[11px] font-mono text-slate-400 mt-0.5">
                                        Ref: {{ t.reference_type }} #{{ t.reference_id }}
                                    </div>
                                </td>
                                <td class="p-3 text-right font-black tabular-nums text-sm"
                                    :class="t.entry_type === 'credit' ? 'text-emerald-700' : 'text-rose-700'">
                                    {{ t.entry_type === 'credit' ? '+' : '-' }}₹{{ fmt(t.amount) }}
                                </td>
                            </tr>
                            <tr v-if="!filteredTransactions.length">
                                <td colspan="5" class="p-12 text-center text-slate-400 font-medium">
                                    No ledger transactions found matching your search.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="filteredTransactions.length" class="bg-slate-50 font-bold border-t border-slate-200">
                            <tr>
                                <td colspan="4" class="p-3 text-right text-slate-600">Total Posted Credits:</td>
                                <td class="p-3 text-right font-black text-emerald-700 text-sm tabular-nums">
                                    ₹{{ fmt(filteredTransactions.filter(t => t.entry_type === 'credit').reduce((s, t) => s + (t.amount || 0), 0)) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 3: Split Comparison View (Side-by-Side) -->
        <div v-else-if="activeView === 'split'" class="grid lg:grid-cols-2 gap-5">
            <!-- Left: School Payments -->
            <section class="card card--flush overflow-hidden bg-white border border-slate-200/90 rounded-xl shadow-xs">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                        <span>🏫</span> School Payments ({{ filteredSchoolPayments.length }})
                    </h3>
                    <span class="text-xs font-bold text-emerald-700">₹{{ fmt(summary.collected) }} settled</span>
                </div>
                <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 text-slate-600 text-[10px] font-bold uppercase sticky top-0 z-10 border-b border-slate-200">
                            <tr>
                                <th class="p-2.5 text-left">School</th>
                                <th class="p-2.5 text-center">Status</th>
                                <th class="p-2.5 text-right">Paid</th>
                                <th class="p-2.5 text-left">Receipt</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, i) in filteredSchoolPayments" :key="i" class="hover:bg-slate-50/70 transition">
                                <td class="p-2.5">
                                    <div class="font-semibold text-slate-900 truncate max-w-[14rem]">{{ row.school }}</div>
                                    <div class="text-[10px] text-slate-400">Due: ₹{{ fmt(row.total_due) }}</div>
                                </td>
                                <td class="p-2.5 text-center">
                                    <span v-if="row.status === 'approved'" class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">
                                        Approved
                                    </span>
                                    <span v-else-if="row.status === 'partial'" class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded">
                                        Partial
                                    </span>
                                    <span v-else class="text-[10px] font-bold text-slate-500 bg-slate-50 px-2 py-0.5 rounded">
                                        Pending
                                    </span>
                                </td>
                                <td class="p-2.5 text-right font-mono tabular-nums">
                                    <div class="font-bold text-emerald-700">₹{{ fmt(row.amount_paid) }}</div>
                                    <span v-if="Number(row.amount_paid || 0) - Number(row.total_due || 0) > 0.01"
                                          class="inline-block text-[9px] font-bold text-amber-700 bg-amber-50 px-1 rounded">
                                        +₹{{ fmt(Number(row.amount_paid) - Number(row.total_due)) }}
                                    </span>
                                </td>
                                <td class="p-2.5 text-[11px] font-mono text-slate-600">
                                    {{ row.receipt_number || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Right: Ledger Transactions -->
            <section class="card card--flush overflow-hidden bg-white border border-slate-200/90 rounded-xl shadow-xs">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                        <span>📑</span> Ledger Postings ({{ transactions.length }})
                    </h3>
                    <span class="text-xs font-bold text-indigo-700">₹{{ fmt(summary.ledger_credits) }} credits</span>
                </div>
                <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 text-slate-600 text-[10px] font-bold uppercase sticky top-0 z-10 border-b border-slate-200">
                            <tr>
                                <th class="p-2.5 text-left">Date</th>
                                <th class="p-2.5 text-center">Type</th>
                                <th class="p-2.5 text-left">Description</th>
                                <th class="p-2.5 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="t in transactions" :key="t.id" class="hover:bg-slate-50/70 transition">
                                <td class="p-2.5 text-slate-600 whitespace-nowrap text-[11px]">
                                    {{ formatCalendarDate(t.transaction_date) }}
                                </td>
                                <td class="p-2.5 text-center">
                                    <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700">
                                        {{ t.entry_type }}
                                    </span>
                                </td>
                                <td class="p-2.5 text-slate-800 text-[11px] truncate max-w-[12rem]" :title="t.description">
                                    {{ t.description || 'Receipt credit' }}
                                </td>
                                <td class="p-2.5 text-right font-mono font-bold text-emerald-700 tabular-nums">
                                    ₹{{ fmt(t.amount) }}
                                </td>
                            </tr>
                            <tr v-if="!transactions.length">
                                <td colspan="4" class="p-8 text-center text-slate-400">
                                    No ledger entries recorded yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Activity Log -->
        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    accountCode: String,
    accountName: String,
    summary: Object,
    schoolPayments: { type: Array, default: () => [] },
    transactions: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    levelLabel: String,
});

const activeView = ref('schools'); // 'schools' | 'transactions' | 'split'
const schoolSearch = ref('');
const schoolStatusFilter = ref('all');
const transactionSearch = ref('');
const copiedCode = ref(false);

const exportUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/fees/export`);

const overpaidSchoolsCount = computed(() => {
    return props.schoolPayments.filter(r => (Number(r.amount_paid || 0) - Number(r.total_due || 0)) > 0.01).length;
});

const schoolStatusFilterOptions = computed(() => {
    const list = props.schoolPayments;
    const opts = [
        { value: 'all', label: 'All Schools', count: list.length },
        { value: 'approved', label: 'Approved', count: list.filter(r => r.status === 'approved').length },
        { value: 'partial', label: 'Partial', count: list.filter(r => r.status === 'partial').length },
        { value: 'pending', label: 'Pending', count: list.filter(r => r.status === 'pending' || r.status === 'proof_uploaded').length },
    ];
    if (overpaidSchoolsCount.value > 0) {
        opts.push({ value: 'overpaid', label: 'Overpaid / Excess', count: overpaidSchoolsCount.value });
    }
    return opts;
});

const filteredSchoolPayments = computed(() => {
    let list = props.schoolPayments;

    if (schoolStatusFilter.value === 'pending') {
        list = list.filter(r => r.status === 'pending' || r.status === 'proof_uploaded');
    } else if (schoolStatusFilter.value === 'overpaid') {
        list = list.filter(r => (Number(r.amount_paid || 0) - Number(r.total_due || 0)) > 0.01);
    } else if (schoolStatusFilter.value !== 'all') {
        list = list.filter(r => r.status === schoolStatusFilter.value);
    }

    const q = schoolSearch.value.trim().toLowerCase();
    if (q) {
        list = list.filter(r => {
            const name = (r.school ?? '').toLowerCase();
            const head = (r.head ?? '').toLowerCase();
            const receipt = (r.receipt_number ?? '').toLowerCase();
            const tx = (r.transaction_ref ?? '').toLowerCase();
            const receiptsStr = (r.receipts ?? []).map(x => `${x.receipt_number ?? ''} ${x.transaction_ref ?? ''}`).join(' ').toLowerCase();

            return name.includes(q) || head.includes(q) || receipt.includes(q) || tx.includes(q) || receiptsStr.includes(q);
        });
    }

    return list;
});

const filteredTransactions = computed(() => {
    let list = props.transactions;

    const q = transactionSearch.value.trim().toLowerCase();
    if (q) {
        list = list.filter(t => {
            const desc = (t.description ?? '').toLowerCase();
            const type = (t.entry_type ?? '').toLowerCase();
            const date = (t.transaction_date ?? '').toLowerCase();
            const amt = String(t.amount ?? '');

            return desc.includes(q) || type.includes(q) || date.includes(q) || amt.includes(q);
        });
    }

    return list;
});

function copyAccountCode() {
    if (!props.accountCode) return;
    navigator.clipboard?.writeText(props.accountCode);
    copiedCode.value = true;
    setTimeout(() => {
        copiedCode.value = false;
    }, 2000);
}

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
</script>
