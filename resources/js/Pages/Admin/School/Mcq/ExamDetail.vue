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
                    <p class="text-lg font-bold">{{ availableStudentCountLabel }}</p>
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
                    <div v-if="allowsStudents" class="card card--flush overflow-hidden">
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
                                            {{ c.name }}<template v-if="c.eligible_count !== null"> ({{ c.eligible_count }} to add)</template>
                                        </option>
                                    </select>
                                    <input v-model="studentSearch" type="search" class="field text-sm min-w-[200px]"
                                           placeholder="Search name or reg. no…" @input="registerPage = 1">
                                </div>
                            </div>
                            <p v-if="lazyLoadStudents && !studentSearch && !classFilter" class="text-xs text-indigo-700 bg-indigo-50 border border-indigo-100 rounded px-3 py-2">
                                This school has {{ studentCount }} students — search by name or admission number, or pick a class, to find and add them.
                            </p>
                            <div v-else class="flex flex-wrap gap-1.5">
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
                                        <th>Admission no.</th>
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
                                        <td class="font-medium">{{ studentDisplayName(s) }}</td>
                                        <td class="font-mono text-xs">{{ s.admission_number || '—' }}</td>
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

                        <div v-if="studentsLoading" class="py-10 text-center text-sm text-slate-500">
                            Loading students…
                        </div>

                        <EmptyState v-else-if="!filteredStudents.length" title="No students in this view"
                                    description="Change class filter, search, or status chip." icon="👥" class="py-8" />

                        <div v-if="studentResultTotal > registerPageSize"
                             class="px-4 py-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-sm">
                            <p class="text-xs text-slate-500">
                                Showing {{ pageRangeStart }}–{{ pageRangeEnd }} of {{ studentResultTotal }}
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

                    <div v-if="allowsTeachers" class="card card--flush overflow-hidden">
                        <div class="p-4 border-b border-slate-100 space-y-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="section-title !mb-0">Select teachers to register</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">Bulk-nominate eligible teachers for this Talent Search exam.</p>
                                </div>
                                <input v-model="teacherSearch" type="search" class="field text-sm min-w-[200px]"
                                       placeholder="Search teacher…">
                            </div>
                        </div>
                        <div v-if="teacherSelectionCount > 0"
                             class="px-4 py-3 border-b border-indigo-100 bg-indigo-50/80 flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-indigo-900">{{ teacherSelectionCount }} teacher(s) selected</p>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn-secondary text-xs" @click="clearTeacherSelection">Clear</button>
                                <button type="button" class="btn-primary text-sm"
                                        :disabled="!canRegister || bulkRegisteringTeachers"
                                        @click="registerSelectedTeachers">
                                    {{ bulkRegisteringTeachers ? 'Registering…' : `Register ${teacherSelectionCount}` }}
                                </button>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th class="w-10"><input type="checkbox" :checked="allVisibleTeachersSelected" @change="toggleAllVisibleTeachers"></th>
                                        <th>Teacher</th>
                                        <th>Code</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="t in filteredTeachers" :key="t.id"
                                        :class="{ 'bg-emerald-50/40': t.registered, 'bg-indigo-50/30': isTeacherSelected(t.id) }">
                                        <td>
                                            <input v-if="!t.registered && t.eligible && canRegister" type="checkbox"
                                                   :checked="isTeacherSelected(t.id)" @change="toggleTeacher(t.id)">
                                        </td>
                                        <td>{{ t.name }}</td>
                                        <td class="text-xs font-mono">{{ t.employee_code || t.reg_no || '—' }}</td>
                                        <td>
                                            <span v-if="t.registered" class="text-xs font-semibold text-emerald-700">Registered</span>
                                            <span v-else-if="!t.eligible" class="text-xs text-amber-700" :title="t.ineligible_reason">Not eligible</span>
                                            <span v-else class="text-xs text-slate-500">Available</span>
                                        </td>
                                        <td class="text-right">
                                            <button v-if="!t.registered && t.eligible && canRegister" type="button"
                                                    class="btn-secondary text-xs" @click="registerTeacherById(t.id)">Add</button>
                                            <button v-else-if="t.registered && t.can_cancel" type="button"
                                                    class="text-xs text-red-600" @click="cancelTeacher(t.id, t.name)">Cancel</button>
                                        </td>
                                    </tr>
                                    <tr v-if="!filteredTeachers.length">
                                        <td colspan="5" class="p-6 text-center text-slate-400">No matching teachers.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <aside class="space-y-4">
                    <div v-if="allowsStudents" class="card space-y-3 bg-indigo-50/40 border-indigo-100">
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
                                <span class="truncate">{{ r.participant_name || (r.student ? studentDisplayName(r.student) : (r.teacher?.name ?? '—')) }}</span>
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
                        <td>{{ r.participant_name || (r.student ? studentDisplayName(r.student) : (r.teacher?.name ?? '—')) }}</td>
                        <td><span class="text-xs capitalize">{{ r.approval_status_label || r.approval_status }}</span></td>
                        <td class="font-mono text-xs">{{ r.hall_ticket_no || '—' }}</td>
                        <td>{{ r.seat_no || '—' }}</td>
                        <td class="text-xs">
                            <span class="font-semibold" :class="lifecycleTone(r.lifecycle_status?.tone)">{{ r.lifecycle_status?.label || r.status }}</span>
                        </td>
                        <td class="text-right whitespace-nowrap space-x-2">
                            <a v-if="examHasFee" :href="`${base}/registrations/${r.id}/invoice`" target="_blank" class="text-xs font-semibold text-indigo-600 hover:underline">Invoice</a>
                            <button v-if="r.can_cancel && canRegister" type="button"
                                    class="text-xs font-semibold text-red-600 hover:text-red-700"
                                    @click="r.teacher_id ? cancelTeacher(r.teacher_id, r.participant_name || r.teacher?.name) : cancelStudent(r.student_id, r.participant_name || studentDisplayName(r.student))">Cancel</button>
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
                        <input ref="proofInput" type="file" accept=".pdf,.jpg,.jpeg,.png" multiple class="text-sm" required>
                        <p class="text-[10px] text-slate-400 mt-0.5">Up to 5 images for this one payment.</p>
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
                <div class="grid grid-cols-5 gap-3">
                    <div class="card card--muted !py-3 text-center">
                        <p class="text-lg font-bold text-emerald-700">{{ presentCount }}</p>
                        <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Present</p>
                    </div>
                    <div class="card card--muted !py-3 text-center">
                        <p class="text-lg font-bold text-rose-700">{{ absentCount }}</p>
                        <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Absent</p>
                    </div>
                    <div class="card card--muted !py-3 text-center">
                        <p class="text-lg font-bold text-amber-700">{{ malpracticeCount }}</p>
                        <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Malpractice</p>
                    </div>
                    <div class="card card--muted !py-3 text-center">
                        <p class="text-lg font-bold text-amber-700">{{ withheldCount }}</p>
                        <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Withheld</p>
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
                <p v-if="exam.results_published" class="text-xs text-amber-700">
                    Results are published for this exam. Attendance changes will be sent to the Sahodaya for approval instead of applying immediately.
                </p>
                <div class="card card--flush overflow-hidden">
                    <table class="data-table">
                        <thead><tr><th>Hall ticket</th><th>Student</th><th>Class</th><th class="text-center">Attendance</th><th>Note</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="row in attendanceState" :key="row.id">
                                <td class="font-mono text-xs">{{ row.hall_ticket_no || '—' }}</td>
                                <td>{{ studentDisplayName(row.student) }}</td>
                                <td class="text-xs">{{ row.class_name || row.student?.class_name || '—' }}</td>
                                <td class="text-center">
                                    <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-xs">
                                        <button type="button" class="px-3 py-1" :disabled="!!row.pending_correction_status"
                                                :class="row.attendance_status === 'present' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600'"
                                                @click="row.attendance_status = 'present'">Present</button>
                                        <button type="button" class="px-3 py-1 border-l border-slate-200" :disabled="!!row.pending_correction_status"
                                                :class="row.attendance_status === 'absent' ? 'bg-rose-600 text-white' : 'bg-white text-slate-600'"
                                                @click="row.attendance_status = 'absent'">Absent</button>
                                        <button type="button" class="px-3 py-1 border-l border-slate-200" :disabled="!!row.pending_correction_status"
                                                :class="row.attendance_status === 'malpractice' ? 'bg-amber-600 text-white' : 'bg-white text-slate-600'"
                                                @click="row.attendance_status = 'malpractice'">Malpractice</button>
                                        <button type="button" class="px-3 py-1 border-l border-slate-200" :disabled="!!row.pending_correction_status"
                                                :class="row.attendance_status === 'withheld' ? 'bg-amber-600 text-white' : 'bg-white text-slate-600'"
                                                @click="row.attendance_status = 'withheld'">Withheld</button>
                                        <button type="button" class="px-3 py-1 border-l border-slate-200" :disabled="!!row.pending_correction_status"
                                                :class="(!row.attendance_status || row.attendance_status === 'pending') ? 'bg-slate-500 text-white' : 'bg-white text-slate-600'"
                                                @click="row.attendance_status = 'pending'">—</button>
                                    </div>
                                </td>
                                <td>
                                    <input v-if="['malpractice','withheld'].includes(row.attendance_status)"
                                           v-model="row.attendance_note" type="text" class="field text-xs"
                                           :disabled="!!row.pending_correction_status"
                                           placeholder="Reason (required)">
                                    <span v-else class="text-slate-300 text-xs">—</span>
                                </td>
                                <td class="text-xs whitespace-nowrap">
                                    <span v-if="row.pending_correction_status" class="text-amber-700 font-semibold">
                                        Pending approval → {{ row.pending_correction_status }}
                                    </span>
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
                        <td>{{ r.participant_name || (r.student ? studentDisplayName(r.student) : (r.teacher?.name ?? '—')) }}</td>
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
                        <td>{{ studentDisplayName(t) }}</td>
                        <td class="text-xs">{{ t.class_name || '—' }}</td>
                        <td>{{ t.score ?? '—' }}</td>
                        <td>{{ t.grade ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-if="!toppers.length" title="No toppers yet" description="Results must be published by Sahodaya." icon="🏆" class="py-8" />
        </div>

        <!-- Reports tab -->
        <div v-else-if="tab === 'reports'" class="space-y-6">
            <!-- Filter Bar for Class selection -->
            <div class="card bg-slate-50 border border-slate-200 p-4 flex flex-wrap items-center justify-between gap-4 shadow-sm">
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Class Filter & Report Options</h3>
                    <p class="text-xs text-slate-500">Filter PDF/Excel reports by a specific class or generate for all classes.</p>
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-slate-700">Select Class:</label>
                    <select v-model="reportClassFilter" class="field text-xs min-w-[170px] bg-white font-semibold text-slate-800">
                        <option value="">All Classes</option>
                        <option v-for="c in classOptions" :key="c.id" :value="c.name">Class {{ c.name }}</option>
                    </select>
                    <button v-if="reportClassFilter" type="button" @click="reportClassFilter = ''" class="text-xs text-slate-500 hover:text-slate-800 underline ml-1">Clear</button>
                </div>
            </div>

            <!-- 4 Main Report Action Cards -->
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- 1. Registration Register -->
                <div class="card flex flex-col justify-between" :class="{'ring-2 ring-indigo-500 bg-indigo-50/20': activeReportPreviewTab === 'registration'}">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="section-title !mb-0">Registration register</h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-800">PDF & Excel</span>
                        </div>
                        <p class="section-desc">Your school's registrations with photos, hall tickets, and approval status.</p>
                    </div>
                    <div class="space-y-2 mt-4 pt-3 border-t border-slate-100">
                        <div class="flex items-center gap-1.5">
                            <a :href="reportUrlWithClass(reportExports.registration)" class="btn-secondary text-xs flex-1 justify-center">
                                <span>Export Excel ↓</span>
                            </a>
                            <a v-if="reportExports.registrationPdf" :href="reportUrlWithClass(reportExports.registrationPdf)" target="_blank" class="btn-secondary text-xs flex-1 justify-center">
                                <span>Export PDF ↓</span>
                            </a>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" v-if="reportExports.registrationPdf" @click="openPdfPreview(reportExports.registrationPdf)" class="btn-secondary text-xs flex-1 justify-center text-indigo-700 bg-indigo-50 border-indigo-200 hover:bg-indigo-100">
                                👁 Preview PDF
                            </button>
                            <button type="button" @click="setReportPreview('registration')" class="btn-secondary text-xs flex-1 justify-center text-slate-700 hover:bg-slate-100" :class="{'!bg-slate-800 !text-white': activeReportPreviewTab === 'registration'}">
                                📊 Preview Data
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 2. Attendance Sheet -->
                <div class="card flex flex-col justify-between" :class="{'ring-2 ring-indigo-500 bg-indigo-50/20': activeReportPreviewTab === 'attendance'}">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="section-title !mb-0">Attendance sheet</h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">PDF & Excel</span>
                        </div>
                        <p class="section-desc">Hall ticket list & attendance checklist for exam day.</p>
                    </div>
                    <div class="space-y-2 mt-4 pt-3 border-t border-slate-100">
                        <div class="flex items-center gap-1.5">
                            <a :href="reportUrlWithClass(reportExports.attendance)" class="btn-secondary text-xs flex-1 justify-center">
                                <span>Export Excel ↓</span>
                            </a>
                            <a v-if="reportExports.attendancePdf" :href="reportUrlWithClass(reportExports.attendancePdf)" target="_blank" class="btn-secondary text-xs flex-1 justify-center">
                                <span>Export PDF ↓</span>
                            </a>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" v-if="reportExports.attendancePdf" @click="openPdfPreview(reportExports.attendancePdf)" class="btn-secondary text-xs flex-1 justify-center text-indigo-700 bg-indigo-50 border-indigo-200 hover:bg-indigo-100">
                                👁 Preview PDF
                            </button>
                            <button type="button" @click="setReportPreview('attendance')" class="btn-secondary text-xs flex-1 justify-center text-slate-700 hover:bg-slate-100" :class="{'!bg-slate-800 !text-white': activeReportPreviewTab === 'attendance'}">
                                📊 Preview Data
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 3. Class-Wise Count Report -->
                <div class="card flex flex-col justify-between" :class="{'ring-2 ring-indigo-500 bg-indigo-50/20': activeReportPreviewTab === 'counts'}">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="section-title !mb-0">Class-wise count report</h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-purple-100 text-purple-800">PDF & Excel</span>
                        </div>
                        <p class="section-desc">Class-wise student registration count matrix for your school.</p>
                    </div>
                    <div class="space-y-2 mt-4 pt-3 border-t border-slate-100">
                        <div class="flex items-center gap-1.5">
                            <a :href="reportUrlWithClass(reportExports.classWiseCounts)" class="btn-secondary text-xs flex-1 justify-center">
                                <span>Export Excel ↓</span>
                            </a>
                            <a v-if="reportExports.classWiseCountsPdf" :href="reportUrlWithClass(reportExports.classWiseCountsPdf)" target="_blank" class="btn-secondary text-xs flex-1 justify-center">
                                <span>Export PDF ↓</span>
                            </a>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" v-if="reportExports.classWiseCountsPdf" @click="openPdfPreview(reportExports.classWiseCountsPdf)" class="btn-secondary text-xs flex-1 justify-center text-indigo-700 bg-indigo-50 border-indigo-200 hover:bg-indigo-100">
                                👁 Preview PDF
                            </button>
                            <button type="button" @click="setReportPreview('counts')" class="btn-secondary text-xs flex-1 justify-center text-slate-700 hover:bg-slate-100" :class="{'!bg-slate-800 !text-white': activeReportPreviewTab === 'counts'}">
                                📊 Preview Data
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 4. Class-Wise Fee Due Report -->
                <div class="card flex flex-col justify-between" :class="{'ring-2 ring-indigo-500 bg-indigo-50/20': activeReportPreviewTab === 'feeDue'}">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="section-title !mb-0">Class-wise Fee Due Report</h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800">PDF & Excel</span>
                        </div>
                        <p class="section-desc">Class-wise fee breakdown, paid amount, and pending due for your school.</p>
                    </div>
                    <div class="space-y-2 mt-4 pt-3 border-t border-slate-100">
                        <div class="flex items-center gap-1.5">
                            <a v-if="reportExports.feeDue" :href="reportUrlWithClass(reportExports.feeDue)" class="btn-secondary text-xs flex-1 justify-center">
                                <span>Export Excel ↓</span>
                            </a>
                            <a v-if="reportExports.feeDuePdf" :href="reportUrlWithClass(reportExports.feeDuePdf)" target="_blank" class="btn-secondary text-xs flex-1 justify-center">
                                <span>Export PDF ↓</span>
                            </a>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" v-if="reportExports.feeDuePdf" @click="openPdfPreview(reportExports.feeDuePdf)" class="btn-secondary text-xs flex-1 justify-center text-indigo-700 bg-indigo-50 border-indigo-200 hover:bg-indigo-100">
                                👁 Preview PDF
                            </button>
                            <button type="button" @click="setReportPreview('feeDue')" class="btn-secondary text-xs flex-1 justify-center text-slate-700 hover:bg-slate-100" :class="{'!bg-slate-800 !text-white': activeReportPreviewTab === 'feeDue'}">
                                📊 Preview Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Data Preview Section -->
            <div class="card card--flush overflow-hidden border border-slate-200 shadow-sm">
                <!-- Preview Header Bar & Tabs -->
                <div class="p-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-slate-900 text-base">Report Preview</h3>
                        <p class="text-xs text-slate-500">Live preview of selected report data for {{ school?.name }}</p>
                    </div>
                    <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 text-xs shadow-sm">
                        <button type="button" class="px-3 py-1.5 rounded-md font-medium transition"
                                :class="activeReportPreviewTab === 'registration' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                                @click="activeReportPreviewTab = 'registration'">
                            Registration Register ({{ reportRows.length }})
                        </button>
                        <button type="button" class="px-3 py-1.5 rounded-md font-medium transition"
                                :class="activeReportPreviewTab === 'attendance' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                                @click="activeReportPreviewTab = 'attendance'">
                            Attendance Sheet
                        </button>
                        <button type="button" class="px-3 py-1.5 rounded-md font-medium transition"
                                :class="activeReportPreviewTab === 'counts' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                                @click="activeReportPreviewTab = 'counts'">
                            Class-wise Counts
                        </button>
                        <button type="button" class="px-3 py-1.5 rounded-md font-medium transition"
                                :class="activeReportPreviewTab === 'feeDue' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                                @click="activeReportPreviewTab = 'feeDue'">
                            Class-wise Fee Due
                        </button>
                    </div>
                </div>

                <!-- 1. Registration Register Preview -->
                <div v-if="activeReportPreviewTab === 'registration'">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Hall ticket</th>
                                <th>Student</th>
                                <th>Reg No</th>
                                <th>Class</th>
                                <th>Approval</th>
                                <th>Attendance</th>
                                <th>Fee Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in filteredReportRows.slice(0, 100)" :key="i">
                                <td class="font-mono text-xs font-semibold text-slate-800">{{ row.hall_ticket_no || '—' }}</td>
                                <td class="font-medium text-slate-900">{{ row.student_name }}</td>
                                <td class="font-mono text-xs text-slate-500">{{ row.reg_no || '—' }}</td>
                                <td class="text-xs font-semibold text-indigo-700">{{ row.class_name || '—' }}</td>
                                <td class="text-xs">
                                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold capitalize"
                                          :class="{
                                              'bg-emerald-100 text-emerald-800': row.approval_status === 'approved',
                                              'bg-amber-100 text-amber-800': row.approval_status === 'pending' || row.approval_status === 'pending_payment',
                                              'bg-rose-100 text-rose-800': row.approval_status === 'rejected',
                                          }">
                                        {{ row.approval_status?.replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="text-xs capitalize">{{ row.attendance_status || 'pending' }}</td>
                                <td class="text-xs capitalize">{{ row.fee_status || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!filteredReportRows.length" class="p-8 text-center text-slate-500">
                        No registrations found to preview.
                    </div>
                </div>

                <!-- 2. Attendance Sheet Preview -->
                <div v-else-if="activeReportPreviewTab === 'attendance'">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Hall ticket</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Attendance Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in filteredReportRows" :key="i">
                                <td class="text-xs text-slate-400 font-bold">{{ i + 1 }}</td>
                                <td class="font-mono text-xs font-semibold">{{ row.hall_ticket_no || '—' }}</td>
                                <td class="font-medium text-slate-900">{{ row.student_name }}</td>
                                <td class="text-xs font-semibold text-indigo-700">{{ row.class_name || '—' }}</td>
                                <td class="text-xs font-bold capitalize"
                                    :class="{
                                        'text-emerald-700': row.attendance_status === 'present',
                                        'text-rose-700': row.attendance_status === 'absent',
                                        'text-amber-700': ['malpractice', 'withheld'].includes(row.attendance_status),
                                        'text-slate-400': !row.attendance_status || row.attendance_status === 'pending'
                                    }">
                                    {{ row.attendance_status || 'pending' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!filteredReportRows.length" class="p-8 text-center text-slate-500">
                        No attendance records found to preview.
                    </div>
                </div>

                <!-- 3. Class-Wise Count Matrix Preview -->
                <div v-else-if="activeReportPreviewTab === 'counts'">
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>School Name</th>
                                    <th v-for="cls in classWiseCountMatrix.classes" :key="cls" class="text-center">{{ cls }}</th>
                                    <th class="text-center font-bold">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(sch, i) in classWiseCountMatrix.schools" :key="sch.school_id">
                                    <td class="text-xs font-bold text-slate-400">{{ i + 1 }}</td>
                                    <td class="font-bold text-slate-800">{{ sch.school_name }}</td>
                                    <td v-for="cls in classWiseCountMatrix.classes" :key="cls" class="text-center font-mono">
                                        {{ sch.counts[cls] || 0 }}
                                    </td>
                                    <td class="text-center font-bold text-indigo-700">{{ sch.total }}</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="classWiseCountMatrix.schools?.length" class="bg-slate-100 font-bold border-t-2 border-slate-300">
                                <tr>
                                    <td colspan="2" class="text-right uppercase text-xs">Total All Classes</td>
                                    <td v-for="cls in classWiseCountMatrix.classes" :key="cls" class="text-center font-mono text-slate-900">
                                        {{ classWiseCountMatrix.totals[cls] || 0 }}
                                    </td>
                                    <td class="text-center font-mono text-emerald-800 bg-emerald-100 text-sm">{{ classWiseCountMatrix.grand_total }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div v-if="!classWiseCountMatrix.schools?.length" class="p-8 text-center text-slate-500">
                        No class-wise count data available to preview.
                    </div>
                </div>

                <!-- 4. Class-Wise Fee Due Report Preview -->
                <div v-else-if="activeReportPreviewTab === 'feeDue'">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Class / Roster</th>
                                <th class="text-center">Registered Students</th>
                                <th class="text-right">Fee Rate (₹)</th>
                                <th class="text-right">Total Amount (₹)</th>
                                <th class="text-right">Paid Amount (₹)</th>
                                <th class="text-right">Pending Fee Due (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in feeDueMatrix.rows" :key="i">
                                <td class="text-xs text-slate-400 font-bold">{{ i + 1 }}</td>
                                <td class="font-bold text-indigo-900">{{ row.class_name }}</td>
                                <td class="text-center font-bold text-slate-800">{{ row.count }}</td>
                                <td class="text-right font-mono">₹{{ Number(row.fee_rate).toFixed(2) }}</td>
                                <td class="text-right font-mono font-bold text-slate-900">₹{{ Number(row.total_fee).toFixed(2) }}</td>
                                <td class="text-right font-mono font-bold text-emerald-700">₹{{ Number(row.paid).toFixed(2) }}</td>
                                <td class="text-right font-mono font-bold" :class="Number(row.due) > 0 ? 'text-rose-700' : 'text-slate-400'">
                                    ₹{{ Number(row.due).toFixed(2) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="feeDueMatrix.rows?.length" class="bg-slate-100 font-bold border-t-2 border-slate-300">
                            <tr>
                                <td colspan="2" class="text-right uppercase text-xs">Grand Total</td>
                                <td class="text-center font-bold text-slate-900">{{ feeDueMatrix.grand_count }}</td>
                                <td class="text-right font-mono">₹{{ Number(feeDueMatrix.fee_rate).toFixed(2) }}</td>
                                <td class="text-right font-mono text-slate-900 text-sm">₹{{ Number(feeDueMatrix.grand_total_fee).toFixed(2) }}</td>
                                <td class="text-right font-mono text-emerald-700 text-sm">₹{{ Number(feeDueMatrix.grand_paid).toFixed(2) }}</td>
                                <td class="text-right font-mono text-rose-700 bg-rose-50 text-sm">₹{{ Number(feeDueMatrix.grand_due).toFixed(2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    <div v-if="!feeDueMatrix.rows?.length" class="p-8 text-center text-slate-500">
                        No fee due records available to preview.
                    </div>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SchoolMcqSubNav from '@/Components/school/SchoolMcqSubNav.vue';
import McqSchoolWorkflowStepper from '@/Components/school/McqSchoolWorkflowStepper.vue';
import { TALENT_SEARCH_EXAMS_LABEL } from '@/support/mcqSchoolLabels.js';
import { studentDisplayName } from '@/support/studentDisplay.js';

const props = defineProps({
    school: Object,
    exam: Object,
    tab: { type: String, default: 'register' },
    registrations: { type: Array, default: () => [] },
    schoolFee: Object,
    feeBreakdown: { type: Object, default: () => ({ by_class: [] }) },
    students: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
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
    classWiseCountMatrix: { type: Object, default: () => ({ classes: [], schools: [], totals: [], grand_total: 0 }) },
    feeDueMatrix: { type: Object, default: () => ({ rows: [], grand_count: 0, grand_total_fee: 0, grand_paid: 0, grand_due: 0 }) },
    toppers: { type: Array, default: () => [] },
    attendanceRows: { type: Array, default: () => [] },
    attendanceGate: { type: Object, default: () => ({ can_mark: false }) },
    reportExports: { type: Object, default: () => ({}) },
    lazyLoadStudents: { type: Boolean, default: false },
    studentCount: { type: Number, default: 0 },
});

const activeReportPreviewTab = ref('registration');
const reportClassFilter = ref('');

const filteredReportRows = computed(() => {
    if (!reportClassFilter.value) {
        return props.reportRows;
    }
    const clean = String(reportClassFilter.value).toLowerCase().replace('class', '').trim();
    return props.reportRows.filter(r => {
        const cName = String(r.class_name || '').toLowerCase().replace('class', '').trim();
        return cName === clean;
    });
});

function reportUrlWithClass(baseUrl, inline = false) {
    if (!baseUrl) return '#';
    let url = baseUrl;
    const params = [];
    if (inline) {
        params.push('inline=1');
    }
    if (reportClassFilter.value) {
        params.push('class=' + encodeURIComponent(reportClassFilter.value));
    }
    if (params.length) {
        url += (url.includes('?') ? '&' : '?') + params.join('&');
    }
    return url;
}

function setReportPreview(tabKey) {
    activeReportPreviewTab.value = tabKey;
}

function openPdfPreview(baseUrl) {
    const url = reportUrlWithClass(baseUrl, true);
    if (!url || url === '#') return;
    window.open(url, '_blank');
}

const page = usePage();
const newCredentials = computed(() => page.props.flash?.mcqNewCredentials ?? []);

const classFilter = ref('');
const studentSearch = ref('');
const studentFilter = ref('available');
const selectedIds = ref(new Set());
const registerPage = ref(1);
const registerPageSize = 50;
const studentsLoading = ref(false);
const remoteStudentTotal = ref(null);
const remoteLastPage = ref(1);
const bulkRegistering = ref(false);
const proofInput = ref(null);
const transactionRef = ref('');
const feeAmount = ref('');
const base = computed(() => `/school-admin/${props.school.id}/mcq/${props.exam.id}`);
const pdfUrl = computed(() => `${base.value}/hall-tickets/pdf`);
const canDownloadDocuments = computed(() => !props.downloadGate?.blocked);

// For large schools (see lazyLoadStudents/studentCount below), the server sends an
// empty `students` list and this page fetches 50-row batches on demand. For small
// schools, `localStudents` is simply the full eagerly-loaded `students` prop.
// See docs/SCALE_AND_PAGINATION_PLAN.md §6/§9-new.
const localStudents = ref([...props.students]);
watch(() => props.students, (value) => {
    localStudents.value = [...value];
});

const availableStudents = computed(() => localStudents.value.filter(s => s.eligible && !s.registered));

function matchesClass(s) {
    if (!classFilter.value) return true;
    return String(s.school_class_id) === String(classFilter.value);
}

function matchesSearch(s) {
    const q = studentSearch.value.trim().toLowerCase();
    if (!q) return true;
    return s.name?.toLowerCase().includes(q)
        || s.admission_number?.toLowerCase().includes(q)
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
    { key: 'available', label: 'To add', count: localStudents.value.filter(s => matchesFilter(s, 'available') && matchesClass(s)).length },
    { key: 'registered', label: 'Registered', count: localStudents.value.filter(s => matchesFilter(s, 'registered') && matchesClass(s)).length },
    { key: 'not_eligible', label: 'Not eligible', count: localStudents.value.filter(s => matchesFilter(s, 'not_eligible') && matchesClass(s)).length },
    { key: 'all', label: 'All', count: localStudents.value.filter(s => matchesClass(s)).length },
]);

const filteredStudents = computed(() =>
    localStudents.value.filter(s => matchesFilter(s, studentFilter.value) && matchesSearch(s) && matchesClass(s)),
);

// Debounced server-side lookup when the roster is too large to ship eagerly.
let studentSearchDebounce = null;
let studentRequestSequence = 0;
async function fetchEligibleStudents({ resetPage = false } = {}) {
    if (!props.lazyLoadStudents) return;
    if (resetPage) registerPage.value = 1;
    const requestSequence = ++studentRequestSequence;
    studentsLoading.value = true;
    try {
        const params = new URLSearchParams();
        if (studentSearch.value.trim()) params.set('search', studentSearch.value.trim());
        if (classFilter.value) params.set('class_id', classFilter.value);
        params.set('status', studentFilter.value);
        params.set('page', String(registerPage.value));
        params.set('per_page', String(registerPageSize));
        const res = await fetch(`${base.value}/eligible-students?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) return;
        const data = await res.json();
        if (requestSequence !== studentRequestSequence) return;
        localStudents.value = data.students ?? [];
        remoteStudentTotal.value = Number(data.meta?.total ?? localStudents.value.length);
        remoteLastPage.value = Math.max(1, Number(data.meta?.last_page ?? 1));
    } catch {
        // keep whatever's already loaded
    } finally {
        if (requestSequence === studentRequestSequence) studentsLoading.value = false;
    }
}

watch([studentSearch, classFilter, studentFilter], () => {
    if (!props.lazyLoadStudents) return;
    if (studentSearchDebounce) clearTimeout(studentSearchDebounce);
    studentSearchDebounce = setTimeout(() => fetchEligibleStudents({ resetPage: true }), 300);
});

watch(registerPage, () => {
    if (props.lazyLoadStudents) fetchEligibleStudents();
});

onMounted(() => {
    if (props.lazyLoadStudents) fetchEligibleStudents();
});

const selectableInView = computed(() =>
    filteredStudents.value.filter(s => isSelectable(s)),
);

const totalPages = computed(() =>
    props.lazyLoadStudents
        ? remoteLastPage.value
        : Math.max(1, Math.ceil(filteredStudents.value.length / registerPageSize)),
);

const paginatedStudents = computed(() => {
    if (props.lazyLoadStudents) return filteredStudents.value;
    const start = (registerPage.value - 1) * registerPageSize;
    return filteredStudents.value.slice(start, start + registerPageSize);
});

const studentResultTotal = computed(() =>
    props.lazyLoadStudents ? (remoteStudentTotal.value ?? 0) : filteredStudents.value.length,
);

const pageRangeStart = computed(() =>
    studentResultTotal.value ? (registerPage.value - 1) * registerPageSize + 1 : 0,
);

const pageRangeEnd = computed(() =>
    Math.min(registerPage.value * registerPageSize, studentResultTotal.value),
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
const availableStudentCountLabel = computed(() =>
    props.registerStats?.available == null
        ? (props.lazyLoadStudents ? '—' : 0)
        : props.registerStats.available,
);

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

const allowsStudents = computed(() => props.exam?.allows_students !== false && (props.registerStats?.allows_students ?? true));
const allowsTeachers = computed(() => !!(props.exam?.allows_teachers || props.registerStats?.allows_teachers));
const teacherSearch = ref('');
const selectedTeacherIds = ref(new Set());
const bulkRegisteringTeachers = ref(false);

const filteredTeachers = computed(() => {
    const q = teacherSearch.value.trim().toLowerCase();
    return props.teachers.filter(t => {
        if (!q) return true;
        return t.name?.toLowerCase().includes(q)
            || t.employee_code?.toLowerCase().includes(q)
            || t.reg_no?.toLowerCase().includes(q);
    });
});

const teacherSelectionCount = computed(() => selectedTeacherIds.value.size);
const allVisibleTeachersSelected = computed(() => {
    const available = filteredTeachers.value.filter(t => !t.registered && t.eligible && canRegister.value);
    return available.length > 0 && available.every(t => selectedTeacherIds.value.has(t.id));
});

function isTeacherSelected(id) { return selectedTeacherIds.value.has(id); }
function toggleTeacher(id) {
    const next = new Set(selectedTeacherIds.value);
    if (next.has(id)) next.delete(id); else next.add(id);
    selectedTeacherIds.value = next;
}
function clearTeacherSelection() { selectedTeacherIds.value = new Set(); }
function toggleAllVisibleTeachers(e) {
    const next = new Set(selectedTeacherIds.value);
    const available = filteredTeachers.value.filter(t => !t.registered && t.eligible && canRegister.value);
    if (e.target.checked) available.forEach(t => next.add(t.id));
    else available.forEach(t => next.delete(t.id));
    selectedTeacherIds.value = next;
}
function registerTeacherById(id) {
    router.post(`${base.value}/register-teacher`, { teacher_id: id }, { preserveScroll: true });
}
function registerSelectedTeachers() {
    const ids = [...selectedTeacherIds.value];
    if (!ids.length || bulkRegisteringTeachers.value) return;
    bulkRegisteringTeachers.value = true;
    router.post(`${base.value}/register-teachers-bulk`, { teacher_ids: ids }, {
        preserveScroll: true,
        onSuccess: () => clearTeacherSelection(),
        onFinish: () => { bulkRegisteringTeachers.value = false; },
    });
}
function cancelTeacher(id, name) {
    if (!window.confirm(`Cancel registration for ${name}?`)) return;
    router.post(`${base.value}/cancel`, { teacher_id: id }, { preserveScroll: true });
}

function registerStudentById(id) {
    router.post(`${base.value}/register`, { student_id: id }, {
        preserveScroll: true,
        onSuccess: () => fetchEligibleStudents(),
    });
}

function registerSelected() {
    const ids = [...selectedIds.value];
    if (!ids.length || bulkRegistering.value) return;

    bulkRegistering.value = true;
    router.post(`${base.value}/register-bulk`, { student_ids: ids }, {
        preserveScroll: true,
        onSuccess: () => {
            clearSelection();
            fetchEligibleStudents();
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
    // Up to 5 images for one payment — see docs/FLOW_GAP_FIX_PLAN.md multi-image
    // upload feature.
    const files = Array.from(proofInput.value?.files ?? []);
    if (!files.length) return;
    router.post(`${base.value}/school-payment`, {
        payment_proof: files,
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
const attendanceState = ref(props.attendanceRows.map(r => ({ ...r, attendance_status: r.attendance_status || 'pending', attendance_note: r.attendance_note || '' })));
const presentCount = computed(() => attendanceState.value.filter(r => r.attendance_status === 'present').length);
const absentCount = computed(() => attendanceState.value.filter(r => r.attendance_status === 'absent').length);
const malpracticeCount = computed(() => attendanceState.value.filter(r => r.attendance_status === 'malpractice').length);
const withheldCount = computed(() => attendanceState.value.filter(r => r.attendance_status === 'withheld').length);
const pendingCount = computed(() => attendanceState.value.filter(r => !r.attendance_status || r.attendance_status === 'pending').length);

function markAll(status) {
    attendanceState.value.forEach(r => { if (!r.pending_correction_status) r.attendance_status = status; });
}

function saveAttendance() {
    const editable = attendanceState.value.filter(r => !r.pending_correction_status);
    const missingNote = editable.find(r => ['malpractice', 'withheld'].includes(r.attendance_status) && !r.attendance_note?.trim());
    if (missingNote) {
        alert('A reason/note is required for every student marked Malpractice or Withheld.');
        return;
    }
    router.post(`${base.value}/attendance`, {
        attendance: editable.map(r => ({
            registration_id: r.id,
            attendance_status: r.attendance_status || 'pending',
            attendance_note: r.attendance_note || null,
        })),
    }, { preserveScroll: true });
}
</script>
