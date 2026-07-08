<template>
    <SchoolAdminLayout :title="exam.title" :school="school" :show-header-title="false">
        <PageHeader :title="exam.title" :eyebrow="TALENT_SEARCH_EXAMS_LABEL" :description="examHeaderDesc">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/mcq`" class="btn-secondary text-sm">← All exams</Link>
            </template>
        </PageHeader>

        <div v-if="exam.level_label || exam.series_title" class="flex flex-wrap gap-2 mb-4">
            <span v-if="exam.level_label" class="text-xs font-semibold px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-800">{{ exam.level_label }}</span>
            <span v-if="exam.exam_type_label" class="text-xs px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">{{ exam.exam_type_label }}</span>
            <span class="status-pill capitalize text-xs" :class="statusClass(exam.status)">{{ exam.status_label || exam.status }}</span>
            <span v-if="exam.delivery_mode_label" class="text-xs px-2.5 py-1 rounded-full bg-slate-50 text-slate-600">{{ exam.delivery_mode_label }}</span>
            <span v-if="exam.series_title" class="text-xs text-slate-500">{{ exam.series_title }}</span>
        </div>

        <SchoolMcqSubNav :school-id="school.id" :exam-id="exam.id" :active="tab" :results-published="exam.results_published" />

        <McqSchoolWorkflowStepper
            :school-id="school.id"
            :exam-id="exam.id"
            :exam="exam"
            :active-tab="tab"
            :registration-count="registerStats.registered ?? registrations.length"
            :school-fee="schoolFee"
            :tickets-issued-count="ticketsIssuedCount"
        />

        <!-- Register tab -->
        <div v-if="tab === 'register'" class="space-y-4">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold" :class="examHasFee ? 'text-emerald-700' : 'text-amber-700'">{{ feeLabel }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Fee / student</p>
                </div>
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold">{{ registerStats.available ?? 0 }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Eligible to add</p>
                </div>
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold text-indigo-700">{{ registerStats.registered ?? registrations.length }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Registered</p>
                </div>
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold">{{ batchDueLabel }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Batch fee due</p>
                </div>
            </div>

            <div v-if="registrationGate?.blocked" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 mb-4">
                <p class="font-semibold">Registration blocked</p>
                <p class="text-xs mt-1">{{ registrationGate.reason }}</p>
                <p v-if="registrationGate.links?.membership" class="text-xs mt-2">
                    <Link :href="registrationGate.links.membership" class="link-brand font-semibold">Complete annual registration →</Link>
                </p>
            </div>

            <div v-if="!canRegister && !registrationGate?.blocked" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">{{ registrationBlockTitle }}</p>
                <p class="text-xs mt-1 text-amber-800">{{ registrationBlockDetail }}</p>
            </div>

            <div v-else-if="!examHasFee" class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                <p class="font-semibold">You can register students now</p>
                <p class="text-xs mt-1 text-sky-800">
                    Per-student fee is not set yet — register first, then pay the batch amount on
                    <Link :href="`${base}/fee`" class="link-brand font-semibold">Fee & payment</Link>
                    once Sahodaya configures the fee. Sahodaya approves payment before hall tickets are issued.
                </p>
            </div>

            <div v-if="newCredentials.length" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                <p class="font-semibold">New portal logins created</p>
                <p class="text-xs mt-1">Save these passwords now — they are shown once.</p>
                <ul class="mt-2 space-y-1 text-xs font-mono">
                    <li v-for="c in newCredentials" :key="c.student_id">
                        {{ c.student_name }} · {{ c.username }} · {{ c.password }}
                    </li>
                </ul>
            </div>

            <div class="grid lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 space-y-4">
                    <div class="card card--flush overflow-hidden">
                        <div class="p-4 border-b border-slate-100 space-y-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="section-title !mb-0">Select students to register</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        Filter by class, tick students, then register in one batch. Works for large rosters (300+).
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2 items-center">
                                    <select v-model="classFilter" class="field text-sm min-w-[160px]" @change="registerPage = 1">
                                        <option value="">All classes</option>
                                        <option v-for="c in classOptions" :key="c.id" :value="String(c.id)">
                                            {{ c.name }} ({{ c.eligible_count }} to add)
                                        </option>
                                    </select>
                                    <input v-model="studentSearch" type="search" class="field text-sm min-w-[200px]"
                                           placeholder="Search name or reg. no…" @input="registerPage = 1">
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="f in studentFilters" :key="f.key" type="button"
                                        class="text-xs font-medium px-2.5 py-1 rounded-full transition"
                                        :class="studentFilter === f.key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                        @click="setStudentFilter(f.key)">
                                    {{ f.label }} ({{ f.count }})
                                </button>
                            </div>
                        </div>

                        <div v-if="selectionCount > 0"
                             class="px-4 py-3 border-b border-indigo-100 bg-indigo-50/80 flex flex-wrap items-center justify-between gap-3 sticky top-0 z-10">
                            <p class="text-sm font-semibold text-indigo-900">
                                {{ selectionCount }} student{{ selectionCount === 1 ? '' : 's' }} selected
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn-secondary text-xs" @click="clearSelection">Clear</button>
                                <button type="button" class="btn-primary text-sm"
                                        :disabled="!canRegister || bulkRegistering"
                                        @click="registerSelected">
                                    {{ bulkRegistering ? 'Registering…' : `Register ${selectionCount}` }}
                                </button>
                            </div>
                        </div>

                        <div v-if="studentFilter === 'available' && selectableInView.length"
                             class="px-4 py-2 border-b border-slate-100 flex flex-wrap items-center gap-3 text-xs text-slate-600">
                            <label class="inline-flex items-center gap-2 cursor-pointer font-medium">
                                <input type="checkbox" class="rounded border-slate-300"
                                       :checked="allPageSelected"
                                       :indeterminate.prop="somePageSelected && !allPageSelected"
                                       @change="toggleSelectAllPage">
                                Select page ({{ paginatedStudents.length }})
                            </label>
                            <button v-if="selectableInView.length > paginatedStudents.length" type="button"
                                    class="link-brand font-semibold"
                                    @click="selectAllInView">
                                Select all {{ selectableInView.length }} eligible in this view
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th v-if="studentFilter === 'available'" class="w-10"></th>
                                        <th>Student</th>
                                        <th>Reg. no.</th>
                                        <th>Class</th>
                                        <th>Portal</th>
                                        <th>Status</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="s in paginatedStudents" :key="s.id"
                                        :class="{ 'bg-emerald-50/40': s.registered, 'bg-indigo-50/30': isSelected(s.id) }">
                                        <td v-if="studentFilter === 'available'">
                                            <input v-if="isSelectable(s)" type="checkbox" class="rounded border-slate-300"
                                                   :checked="isSelected(s.id)"
                                                   @change="toggleSelect(s.id)">
                                        </td>
                                        <td class="font-medium">{{ s.name }}</td>
                                        <td class="font-mono text-xs">{{ s.reg_no || '—' }}</td>
                                        <td class="text-xs">{{ s.class_name || '—' }}</td>
                                        <td class="text-xs">{{ s.has_portal_login ? 'Has login' : 'New on register' }}</td>
                                        <td>
                                            <span v-if="s.registered" class="text-xs font-semibold text-emerald-700">Registered</span>
                                            <span v-else-if="s.previously_cancelled" class="text-xs font-semibold text-amber-600">Cancelled — can re-add</span>
                                            <span v-else-if="s.eligible" class="text-xs font-semibold text-slate-600">Eligible</span>
                                            <span v-else class="text-xs text-amber-700">{{ s.ineligible_reason || 'Not eligible' }}</span>
                                        </td>
                                        <td class="text-right">
                                            <button v-if="!s.registered && s.eligible && canRegister" type="button"
                                                    class="btn-secondary !py-1 !px-3 text-xs"
                                                    @click="registerStudentById(s.id)">
                                                {{ s.previously_cancelled ? 'Re-add' : 'Add' }}
                                            </button>
                                            <button v-else-if="s.registered && s.can_cancel" type="button"
                                                    class="text-xs font-semibold text-red-600 hover:text-red-700"
                                                    @click="cancelStudent(s.id, s.name)">Cancel</button>
                                            <span v-else-if="s.registered" class="text-xs text-slate-400" title="Approved or exam started — contact Sahodaya">Locked</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <EmptyState v-if="!filteredStudents.length" title="No students in this view"
                                    description="Change class filter, search, or status chip." icon="👥" class="py-8" />

                        <div v-if="filteredStudents.length > registerPageSize"
                             class="px-4 py-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-sm">
                            <p class="text-xs text-slate-500">
                                Showing {{ pageRangeStart }}–{{ pageRangeEnd }} of {{ filteredStudents.length }}
                            </p>
                            <div class="flex items-center gap-2">
                                <button type="button" class="btn-secondary text-xs" :disabled="registerPage <= 1"
                                        @click="registerPage--">Previous</button>
                                <span class="text-xs text-slate-600">Page {{ registerPage }} / {{ totalPages }}</span>
                                <button type="button" class="btn-secondary text-xs" :disabled="registerPage >= totalPages"
                                        @click="registerPage++">Next</button>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="space-y-4">
                    <div class="card space-y-3 bg-indigo-50/40 border-indigo-100">
                        <h3 class="section-title !mb-0">Student portal logins</h3>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Each newly registered student gets a portal account automatically:
                        </p>
                        <ul class="text-xs text-slate-700 space-y-2 list-disc list-inside">
                            <li><strong>Student ID</strong> = reg. no. (e.g. STU/26/0001)</li>
                            <li><strong>Password</strong> = random temp password (shown once after you register)</li>
                            <li>No Gmail — students use Student ID + password only</li>
                        </ul>
                        <p class="text-xs text-slate-600">
                            Portal URL:
                            <a :href="portalLoginUrl" target="_blank" rel="noopener" class="link-brand font-mono break-all">{{ portalLoginUrl }}</a>
                        </p>
                        <a v-if="credentialsExportUrl && canDownloadDocuments" :href="credentialsExportUrl" class="btn-secondary text-xs inline-block">Export usernames</a>
                        <p v-else-if="credentialsExportUrl && downloadGate?.blocked" class="text-xs text-amber-700">
                            Export locked — {{ downloadGate.reason }}
                        </p>
                    </div>

                    <div v-if="mcqCoordinators.length" class="card space-y-2 text-xs text-slate-600">
                        <h3 class="section-title !mb-0">Talent Search coordinators</h3>
                        <p v-for="u in mcqCoordinators" :key="u.id">{{ u.name }} · {{ u.email }}</p>
                    </div>

                    <div class="card space-y-2">
                        <h3 class="section-title !mb-0">After registering</h3>
                        <ol class="text-xs text-slate-600 space-y-2 list-decimal list-inside">
                            <li>Register students on this tab</li>
                            <li>Upload batch fee proof on <Link :href="`${base}/fee`" class="link-brand">Fee & payment</Link> (after Sahodaya sets fee amount)</li>
                            <li>Sahodaya verifies payment and approves registrations</li>
                            <li>Pay membership and exam fees, then download hall tickets from <Link :href="`${base}/hall-tickets`" class="link-brand">Hall tickets</Link></li>
                        </ol>
                    </div>

                    <div v-if="registrations.length" class="card space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="section-title !mb-0">Registered ({{ registrations.length }})</h3>
                            <Link :href="`${base}/students`" class="link-brand text-xs font-semibold">View all →</Link>
                        </div>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            <div v-for="r in registrations.slice(0, 8)" :key="r.id"
                                 class="flex justify-between gap-2 text-sm border border-slate-100 rounded-lg px-3 py-2">
                                <span class="truncate">{{ r.student?.name }}</span>
                                <span class="text-xs capitalize shrink-0 text-slate-500">{{ r.approval_status_label || r.approval_status }}</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Students tab -->
        <div v-else-if="tab === 'students'" class="card card--flush overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h3 class="section-title !mb-0">Registered students</h3>
                <p class="text-xs text-slate-500 mt-1">{{ registrations.length }} student(s) registered for this exam.</p>
            </div>
            <table class="data-table">
                <thead><tr><th>Student</th><th>Approval</th><th>Exam reg. no.</th><th>Seat</th><th>Status</th><th class="text-right">Action</th></tr></thead>
                <tbody>
                    <tr v-for="r in registrations" :key="r.id">
                        <td>{{ r.student?.name }}</td>
                        <td><span class="text-xs capitalize">{{ r.approval_status_label || r.approval_status }}</span></td>
                        <td class="font-mono text-xs">{{ r.hall_ticket_no || '—' }}</td>
                        <td>{{ r.seat_no || '—' }}</td>
                        <td class="text-xs">
                            <span class="font-semibold" :class="lifecycleTone(r.lifecycle_status?.tone)">{{ r.lifecycle_status?.label || r.status }}</span>
                        </td>
                        <td class="text-right">
                            <button v-if="r.can_cancel && canRegister" type="button"
                                    class="text-xs font-semibold text-red-600 hover:text-red-700"
                                    @click="cancelStudent(r.student_id, r.student?.name)">Cancel</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-if="!registrations.length" title="No registrations yet" description="Register students from the Register tab." icon="👥" class="py-8" />
        </div>

        <!-- Hall tickets tab -->
        <div v-else-if="tab === 'hall-tickets'" class="space-y-4">
            <div v-if="downloadGate?.blocked" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Payment pending</p>
                <p class="text-xs mt-1">{{ downloadGate.reason }} Pay Sahodaya membership and exam fees to download hall tickets.</p>
                <Link v-if="downloadGate.links?.payments" :href="downloadGate.links.payments" class="link-brand text-xs font-semibold mt-2 inline-block">Go to payments →</Link>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold text-indigo-700">{{ ticketsIssuedCount }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Tickets issued</p>
                </div>
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold">{{ registrations.length - ticketsIssuedCount }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Pending issue</p>
                </div>
                <div class="card card--muted !py-3 text-center lg:col-span-1 col-span-2">
                    <p class="text-sm font-semibold capitalize">{{ schoolFee?.status?.replace('_', ' ') || 'No fee batch' }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Fee status</p>
                </div>
            </div>
            <div class="card p-4 space-y-3">
                <p class="text-sm text-slate-700">
                    Hall tickets are available after membership and exam fees are paid and verified.
                </p>
                <div class="flex flex-wrap gap-2">
                    <a v-if="ticketsIssuedCount && canDownloadDocuments" :href="pdfUrl" target="_blank" class="btn-primary text-sm">Download hall tickets PDF</a>
                    <Link v-if="schoolFee?.status !== 'approved'" :href="`${base}/fee`" class="btn-secondary text-sm">Check fee status</Link>
                </div>
                <p v-if="!ticketsIssuedCount && !downloadGate?.blocked" class="text-sm text-amber-700">No hall tickets yet — register students and complete fee payment.</p>
                <p v-else-if="!canDownloadDocuments" class="text-sm text-amber-700">Hall ticket download is locked until fees are cleared.</p>
            </div>
        </div>

        <!-- Fee tab -->
        <div v-else-if="tab === 'fee'" class="space-y-4">
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold" :class="examHasFee ? 'text-slate-800' : 'text-amber-700'">{{ studentFeeLabel }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Student fee</p>
                </div>
                <div v-if="hasSchoolDiscount" class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold text-emerald-700">−{{ schoolDiscountLabel }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">School discount</p>
                </div>
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold" :class="examHasFee ? 'text-emerald-700' : 'text-amber-700'">{{ payablePerStudentLabel }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Pay Sahodaya / student</p>
                </div>
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold">{{ feeBreakdown?.student_count ?? schoolFee?.student_count ?? registerStats.registered ?? 0 }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Students</p>
                </div>
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold">{{ batchDueLabel }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Total payable</p>
                </div>
            </div>

            <div v-if="feeBreakdown?.by_class?.length" class="card card--flush overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="section-title !mb-0">Class-wise summary</h3>
                    <p class="text-xs text-slate-500 mt-1">Registered students and fees by class.</p>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th class="text-right">Students</th>
                            <th class="text-right">Student fees</th>
                            <th v-if="hasSchoolDiscount" class="text-right">Discount</th>
                            <th class="text-right">Payable to Sahodaya</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in feeBreakdown.by_class" :key="row.class_id ?? row.class_name">
                            <td class="font-medium">{{ row.class_name }}</td>
                            <td class="text-right">{{ row.student_count }}</td>
                            <td class="text-right">{{ formatRupee(row.student_fee_total) }}</td>
                            <td v-if="hasSchoolDiscount" class="text-right text-emerald-700">−{{ formatRupee(row.discount_total) }}</td>
                            <td class="text-right font-semibold">{{ formatRupee(row.payable_total) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-semibold">
                            <td>Total</td>
                            <td class="text-right">{{ feeBreakdown.student_count }}</td>
                            <td class="text-right">{{ formatRupee(feeBreakdown.student_fee_total) }}</td>
                            <td v-if="hasSchoolDiscount" class="text-right text-emerald-700">−{{ formatRupee(feeBreakdown.discount_total) }}</td>
                            <td class="text-right">{{ formatRupee(feeBreakdown.payable_total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="card p-4 space-y-4">
                <p class="text-sm text-slate-600">
                    <template v-if="hasSchoolDiscount">
                        Students pay {{ studentFeeLabel }} each. Sahodaya gives a {{ schoolDiscountLabel }} discount per student — remit {{ payablePerStudentLabel }} × registered count.
                    </template>
                    <template v-else>
                        Pay the total batch amount to Sahodaya and upload proof here.
                    </template>
                    After verification, registrations are confirmed and hall tickets are issued.
                </p>
                <div v-if="examHasFee && schoolFee && Number(schoolFee.amount_paid) > 0" class="grid grid-cols-3 gap-3">
                    <div class="rounded-lg bg-emerald-50 border border-emerald-100 px-3 py-2 text-center">
                        <p class="text-sm font-bold text-emerald-700">{{ formatRupee(schoolFee.amount_paid) }}</p>
                        <p class="text-[10px] uppercase tracking-wide text-emerald-600">Paid so far</p>
                    </div>
                    <div class="rounded-lg bg-amber-50 border border-amber-100 px-3 py-2 text-center">
                        <p class="text-sm font-bold text-amber-700">{{ formatRupee(feeBalance) }}</p>
                        <p class="text-[10px] uppercase tracking-wide text-amber-600">Balance due</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 border border-slate-100 px-3 py-2 text-center">
                        <p class="text-sm font-bold capitalize">{{ (schoolFee.status || '').replace('_', ' ') }}</p>
                        <p class="text-[10px] uppercase tracking-wide text-slate-500">Status</p>
                    </div>
                </div>
                <p v-if="!examHasFee && registrations.length" class="text-sm text-sky-800 bg-sky-50 border border-sky-100 rounded-lg px-3 py-2">
                    Batch fee total will be calculated when Sahodaya sets the per-student exam fee.
                </p>
                <p v-else-if="!registrations.length" class="text-sm text-amber-700">Register students first, then upload payment here.</p>
                <form v-else-if="examHasFee && schoolFee && feeBalance > 0"
                      @submit.prevent="uploadBatchFee" class="flex flex-wrap gap-2 items-end border-t border-slate-100 pt-4">
                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Payment proof</label>
                        <input ref="proofInput" type="file" accept=".pdf,.jpg,.jpeg,.png" class="text-sm" required>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Amount (₹)</label>
                        <input v-model="feeAmount" type="number" min="1" :max="feeBalance" step="0.01"
                               class="field max-w-[140px] text-sm" :placeholder="String(feeBalance)">
                        <p class="text-[10px] text-slate-400 mt-0.5">Leave blank to pay full balance ({{ formatRupee(feeBalance) }})</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Transaction ref (optional)</label>
                        <input v-model="transactionRef" class="field max-w-xs text-sm" placeholder="UTR / ref no.">
                    </div>
                    <button type="submit" class="btn-primary text-sm">Upload proof</button>
                </form>
                <p v-else-if="schoolFee?.status === 'approved' || feeBalance <= 0 && Number(schoolFee?.amount_paid) > 0" class="text-sm font-semibold text-emerald-700">Fee fully paid — hall tickets can be issued by Sahodaya.</p>
                <p v-else-if="schoolFee?.status === 'proof_uploaded'" class="text-sm text-amber-800">Proof uploaded — awaiting Sahodaya verification.</p>
                <p v-else class="text-sm font-semibold capitalize text-slate-600">Status: {{ schoolFee?.status?.replace('_', ' ') || 'Not calculated' }}</p>
            </div>
        </div>

        <!-- Attendance tab -->
        <div v-else-if="tab === 'attendance'" class="space-y-4">
            <div v-if="!attendanceGate.can_mark" class="card p-6 text-center">
                <EmptyState title="Attendance not available yet"
                            description="Attendance can be marked once hall tickets are issued (after Sahodaya approves your batch fee)."
                            icon="🕒" class="py-6" />
            </div>
            <template v-else>
                <div class="grid grid-cols-3 gap-3">
                    <div class="card card--muted !py-3 text-center">
                        <p class="text-lg font-bold text-emerald-700">{{ presentCount }}</p>
                        <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Present</p>
                    </div>
                    <div class="card card--muted !py-3 text-center">
                        <p class="text-lg font-bold text-rose-700">{{ absentCount }}</p>
                        <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Absent</p>
                    </div>
                    <div class="card card--muted !py-3 text-center">
                        <p class="text-lg font-bold text-slate-500">{{ pendingCount }}</p>
                        <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Pending</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 items-center">
                    <button type="button" class="btn-secondary text-xs" @click="markAll('present')">Mark all present</button>
                    <button type="button" class="btn-secondary text-xs" @click="markAll('absent')">Mark all absent</button>
                    <a :href="reportExports.attendance" class="btn-secondary text-xs ml-auto">Export attendance ↓</a>
                </div>
                <div class="card card--flush overflow-hidden">
                    <table class="data-table">
                        <thead><tr><th>Hall ticket</th><th>Student</th><th>Class</th><th class="text-center">Attendance</th></tr></thead>
                        <tbody>
                            <tr v-for="row in attendanceState" :key="row.id">
                                <td class="font-mono text-xs">{{ row.hall_ticket_no || '—' }}</td>
                                <td>{{ row.student?.name }} <span class="text-slate-400 text-xs">{{ row.student?.reg_no }}</span></td>
                                <td class="text-xs">{{ row.class_name || row.student?.class_name || '—' }}</td>
                                <td class="text-center">
                                    <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-xs">
                                        <button type="button" class="px-3 py-1"
                                                :class="row.attendance_status === 'present' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600'"
                                                @click="row.attendance_status = 'present'">Present</button>
                                        <button type="button" class="px-3 py-1 border-l border-slate-200"
                                                :class="row.attendance_status === 'absent' ? 'bg-rose-600 text-white' : 'bg-white text-slate-600'"
                                                @click="row.attendance_status = 'absent'">Absent</button>
                                        <button type="button" class="px-3 py-1 border-l border-slate-200"
                                                :class="(!row.attendance_status || row.attendance_status === 'pending') ? 'bg-slate-500 text-white' : 'bg-white text-slate-600'"
                                                @click="row.attendance_status = 'pending'">—</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-4 flex justify-end">
                    <button type="button" class="btn-primary" @click="saveAttendance">Save attendance</button>
                </div>
            </template>
        </div>

        <!-- Results tab -->
        <div v-else-if="tab === 'results'" class="card card--flush overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h3 class="section-title !mb-0">Exam results</h3>
                <p class="text-xs text-slate-500 mt-1">Published by Sahodaya for your registered students.</p>
            </div>
            <table class="data-table">
                <thead><tr><th>Student</th><th>Score</th><th>Rank</th><th>Grade</th></tr></thead>
                <tbody>
                    <tr v-for="r in registrations" :key="r.id">
                        <td>{{ r.student?.name }}</td>
                        <td>{{ r.mark?.score ?? '—' }}</td>
                        <td>{{ r.mark?.rank ?? '—' }}</td>
                        <td>{{ r.mark?.grade ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-if="!registrations.length" title="No results" description="No registered students for this exam." icon="📊" class="py-8" />
        </div>

        <!-- Toppers tab -->
        <div v-else-if="tab === 'toppers'" class="card card--flush overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h3 class="section-title !mb-0">School toppers</h3>
                <p class="text-xs text-slate-500 mt-1">Top performers from your school in this exam.</p>
            </div>
            <table class="data-table">
                <thead><tr><th>Rank</th><th>Student</th><th>Class</th><th>Score</th><th>Grade</th></tr></thead>
                <tbody>
                    <tr v-for="(t, i) in toppers" :key="i">
                        <td class="font-semibold">{{ t.rank ?? '—' }}</td>
                        <td>{{ t.name }} <span class="text-slate-400 text-xs">{{ t.reg_no }}</span></td>
                        <td class="text-xs">{{ t.class_name || '—' }}</td>
                        <td>{{ t.score ?? '—' }}</td>
                        <td>{{ t.grade ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-if="!toppers.length" title="No toppers yet" description="Results must be published by Sahodaya." icon="🏆" class="py-8" />
        </div>

        <!-- Reports tab -->
        <div v-else-if="tab === 'reports'" class="space-y-4">
            <div class="grid md:grid-cols-2 gap-4">
                <div class="card">
                    <h3 class="section-title">Registration register</h3>
                    <p class="section-desc">Your school's registrations with hall tickets and approval status.</p>
                    <a :href="reportExports.registration" class="btn-secondary text-sm mt-3 inline-block">Export Excel ↓</a>
                </div>
                <div class="card">
                    <h3 class="section-title">Attendance sheet</h3>
                    <p class="section-desc">Hall ticket list for exam-day attendance.</p>
                    <a :href="reportExports.attendance" class="btn-secondary text-sm mt-3 inline-block">Export Excel ↓</a>
                </div>
                <div v-if="exam.results_published && reportExports.toppers" class="card">
                    <h3 class="section-title">School toppers</h3>
                    <p class="section-desc">Top performers from your school after results are published.</p>
                    <a :href="reportExports.toppers" class="btn-secondary text-sm mt-3 inline-block">Export toppers ↓</a>
                </div>
            </div>
            <div class="card card--flush overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="section-title !mb-0">Preview ({{ reportRows.length }})</h3>
                </div>
                <table class="data-table">
                    <thead><tr><th>Hall ticket</th><th>Student</th><th>Class</th><th>Approval</th><th>Attendance</th></tr></thead>
                    <tbody>
                        <tr v-for="(row, i) in reportRows.slice(0, 50)" :key="i">
                            <td>{{ row.hall_ticket_no || '—' }}</td>
                            <td>{{ row.student_name }}</td>
                            <td class="text-xs">{{ row.class_name || '—' }}</td>
                            <td class="text-xs">{{ row.approval_status }}</td>
                            <td class="text-xs">{{ row.attendance_status || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SchoolMcqSubNav from '@/Components/school/SchoolMcqSubNav.vue';
import McqSchoolWorkflowStepper from '@/Components/school/McqSchoolWorkflowStepper.vue';
import { TALENT_SEARCH_EXAMS_LABEL } from '@/support/mcqSchoolLabels.js';

const props = defineProps({
    school: Object,
    exam: Object,
    tab: { type: String, default: 'register' },
    registrations: { type: Array, default: () => [] },
    schoolFee: Object,
    feeBreakdown: { type: Object, default: () => ({ by_class: [] }) },
    students: { type: Array, default: () => [] },
    classOptions: { type: Array, default: () => [] },
    registeredStudentIds: { type: Array, default: () => [] },
    ticketsIssuedCount: { type: Number, default: 0 },
    registerStats: { type: Object, default: () => ({}) },
    registrationGate: { type: Object, default: () => ({ blocked: false }) },
    downloadGate: { type: Object, default: () => ({ blocked: false }) },
    mcqCoordinators: { type: Array, default: () => [] },
    portalLoginUrl: { type: String, default: '/portal/login' },
    credentialsExportUrl: { type: String, default: '' },
    reportRows: { type: Array, default: () => [] },
    toppers: { type: Array, default: () => [] },
    attendanceRows: { type: Array, default: () => [] },
    attendanceGate: { type: Object, default: () => ({ can_mark: false }) },
    reportExports: { type: Object, default: () => ({}) },
});

const page = usePage();
const newCredentials = computed(() => page.props.flash?.mcqNewCredentials ?? []);

const classFilter = ref('');
const studentSearch = ref('');
const studentFilter = ref('available');
const selectedIds = ref(new Set());
const registerPage = ref(1);
const registerPageSize = 100;
const bulkRegistering = ref(false);
const proofInput = ref(null);
const transactionRef = ref('');
const feeAmount = ref('');
const base = computed(() => `/school-admin/${props.school.id}/mcq/${props.exam.id}`);
const pdfUrl = computed(() => `${base.value}/hall-tickets/pdf`);
const canDownloadDocuments = computed(() => !props.downloadGate?.blocked);

const availableStudents = computed(() => props.students.filter(s => s.eligible && !s.registered));

function matchesClass(s) {
    if (!classFilter.value) return true;
    return String(s.school_class_id) === String(classFilter.value);
}

function matchesSearch(s) {
    const q = studentSearch.value.trim().toLowerCase();
    if (!q) return true;
    return s.name?.toLowerCase().includes(q)
        || s.reg_no?.toLowerCase().includes(q)
        || s.class_name?.toLowerCase().includes(q);
}

function matchesFilter(s, key) {
    if (key === 'all') return true;
    if (key === 'registered') return s.registered;
    if (key === 'available') return !s.registered && s.eligible;
    if (key === 'not_eligible') return !s.registered && !s.eligible;
    return true;
}

const studentFilters = computed(() => [
    { key: 'available', label: 'To add', count: props.students.filter(s => matchesFilter(s, 'available') && matchesClass(s)).length },
    { key: 'registered', label: 'Registered', count: props.students.filter(s => matchesFilter(s, 'registered') && matchesClass(s)).length },
    { key: 'not_eligible', label: 'Not eligible', count: props.students.filter(s => matchesFilter(s, 'not_eligible') && matchesClass(s)).length },
    { key: 'all', label: 'All', count: props.students.filter(s => matchesClass(s)).length },
]);

const filteredStudents = computed(() =>
    props.students.filter(s => matchesFilter(s, studentFilter.value) && matchesSearch(s) && matchesClass(s)),
);

const selectableInView = computed(() =>
    filteredStudents.value.filter(s => isSelectable(s)),
);

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredStudents.value.length / registerPageSize)),
);

const paginatedStudents = computed(() => {
    const start = (registerPage.value - 1) * registerPageSize;
    return filteredStudents.value.slice(start, start + registerPageSize);
});

const pageRangeStart = computed(() =>
    filteredStudents.value.length ? (registerPage.value - 1) * registerPageSize + 1 : 0,
);

const pageRangeEnd = computed(() =>
    Math.min(registerPage.value * registerPageSize, filteredStudents.value.length),
);

const selectionCount = computed(() => selectedIds.value.size);

const allPageSelected = computed(() => {
    const selectable = paginatedStudents.value.filter(s => isSelectable(s));
    return selectable.length > 0 && selectable.every(s => selectedIds.value.has(s.id));
});

const somePageSelected = computed(() =>
    paginatedStudents.value.some(s => isSelectable(s) && selectedIds.value.has(s.id)),
);

function isSelectable(s) {
    return !s.registered && s.eligible && canRegister.value;
}

function isSelected(id) {
    return selectedIds.value.has(id);
}

function toggleSelect(id) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    selectedIds.value = next;
}

function toggleSelectAllPage() {
    const selectable = paginatedStudents.value.filter(s => isSelectable(s));
    const next = new Set(selectedIds.value);
    if (allPageSelected.value) {
        selectable.forEach(s => next.delete(s.id));
    } else {
        selectable.forEach(s => next.add(s.id));
    }
    selectedIds.value = next;
}

function selectAllInView() {
    const next = new Set(selectedIds.value);
    selectableInView.value.forEach(s => next.add(s.id));
    selectedIds.value = next;
}

function clearSelection() {
    selectedIds.value = new Set();
}

function setStudentFilter(key) {
    studentFilter.value = key;
    registerPage.value = 1;
    if (key !== 'available') {
        clearSelection();
    }
}

const examHasFee = computed(() => Boolean(props.exam?.has_fee) || (Number(props.exam?.fee_amount) > 0 && (props.exam?.fee_type ?? 'none') !== 'none'));
const hasSchoolDiscount = computed(() => Number(props.feeBreakdown?.school_discount ?? props.exam?.school_discount_amount ?? 0) > 0);
const canRegister = computed(() => props.registerStats?.can_register ?? props.exam?.registration_open !== false);

function formatRupee(amount) {
    const value = Number(amount ?? 0);
    if (!value) return '₹0';
    return value % 1 === 0 ? `₹${value}` : `₹${value.toFixed(2)}`;
}

const studentFeeLabel = computed(() => props.exam?.student_fee_label ?? props.exam?.fee_label ?? formatRupee(props.feeBreakdown?.student_fee));
const schoolDiscountLabel = computed(() => props.exam?.school_discount_label ?? formatRupee(props.feeBreakdown?.school_discount));
const payablePerStudentLabel = computed(() => props.exam?.payable_per_student_label ?? formatRupee(props.feeBreakdown?.payable_per_student));

const feeLabel = computed(() => payablePerStudentLabel.value);

const batchDueLabel = computed(() => {
    const due = props.feeBreakdown?.payable_total ?? props.registerStats?.batch_due ?? props.schoolFee?.total_due ?? 0;
    return formatRupee(due);
});

const feeBalance = computed(() => {
    const due = Number(props.schoolFee?.total_due ?? props.registerStats?.batch_due ?? 0);
    const paid = Number(props.schoolFee?.amount_paid ?? 0);
    return Math.max(0, Math.round((due - paid) * 100) / 100);
});

const registrationBlockTitle = computed(() => 'Registration closed');

const registrationBlockDetail = computed(() => {
    if (props.registrationGate?.blocked) {
        return props.registrationGate.reason;
    }
    if (props.exam?.registration_open !== false && ['published', 'ongoing'].includes(props.exam?.status)) {
        return '';
    }
    return 'This exam is not open for registration (status: ' + (props.exam?.status_label || props.exam?.status) + ').';
});

const examHeaderDesc = computed(() => {
    const parts = [];
    if (props.exam?.scheduled_at_label) parts.push(props.exam.scheduled_at_label);
    else if (props.exam?.scheduled_at) {
        parts.push(new Date(props.exam.scheduled_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }));
    }
    if (examHasFee.value) {
        if (hasSchoolDiscount.value) {
            parts.push(`${studentFeeLabel.value} student fee · ${payablePerStudentLabel.value} payable to Sahodaya`);
        } else {
            parts.push(`${feeLabel.value} per student`);
        }
    }
    if (props.exam?.eligibility_summary) parts.push(props.exam.eligibility_summary);
    return parts.length ? parts.join(' · ') : 'Register students, pay batch fee, download hall tickets.';
});

function statusClass(status) {
    if (status === 'published' || status === 'ongoing') return 'status-pill--published';
    if (status === 'completed') return 'status-pill--success';
    return 'status-pill--draft';
}

function lifecycleTone(tone) {
    return ({
        success: 'text-emerald-700',
        warning: 'text-amber-700',
        danger: 'text-red-700',
        info: 'text-blue-700',
    })[tone] || 'text-slate-700';
}

function registerStudentById(id) {
    router.post(`${base.value}/register`, { student_id: id }, { preserveScroll: true });
}

function registerSelected() {
    const ids = [...selectedIds.value];
    if (!ids.length || bulkRegistering.value) return;

    bulkRegistering.value = true;
    router.post(`${base.value}/register-bulk`, { student_ids: ids }, {
        preserveScroll: true,
        onSuccess: () => {
            clearSelection();
        },
        onFinish: () => {
            bulkRegistering.value = false;
        },
    });
}

function cancelStudent(id, name) {
    if (!window.confirm(`Cancel registration for ${name}? You can re-add them later.`)) return;
    router.post(`${base.value}/cancel`, { student_id: id }, { preserveScroll: true });
}

function uploadBatchFee() {
    const file = proofInput.value?.files?.[0];
    if (!file) return;
    router.post(`${base.value}/school-payment`, {
        payment_proof: file,
        transaction_ref: transactionRef.value || null,
        amount: feeAmount.value ? Number(feeAmount.value) : null,
    }, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            transactionRef.value = '';
            feeAmount.value = '';
            if (proofInput.value) proofInput.value.value = '';
        },
    });
}

// --- Attendance ---
const attendanceState = ref(props.attendanceRows.map(r => ({ ...r, attendance_status: r.attendance_status || 'pending' })));
const presentCount = computed(() => attendanceState.value.filter(r => r.attendance_status === 'present').length);
const absentCount = computed(() => attendanceState.value.filter(r => r.attendance_status === 'absent').length);
const pendingCount = computed(() => attendanceState.value.filter(r => !r.attendance_status || r.attendance_status === 'pending').length);

function markAll(status) {
    attendanceState.value.forEach(r => { r.attendance_status = status; });
}

function saveAttendance() {
    router.post(`${base.value}/attendance`, {
        attendance: attendanceState.value.map(r => ({
            registration_id: r.id,
            attendance_status: r.attendance_status || 'pending',
        })),
    }, { preserveScroll: true });
}
</script>
