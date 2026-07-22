<template>
    <SchoolAdminLayout :title="`${programLabel} Registration`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`${programLabel} Registration`"
            :eyebrow="programLabel"
                :description="isSports
                ? 'Register athletes for the event, then assign items by head (Athletics, etc.).'
                : isTeacherFest
                    ? 'Register teachers for open Teacher Fest events.'
                    : `Register students for open ${programLabel} events.`"
        >
            <template #actions>
                <button v-if="!isTeacherFest" type="button" class="btn-secondary text-sm" @click="showAddStudent = true">
                    {{ isLocked ? 'Request student' : '+ Add student' }}
                </button>
                <button v-if="events.length && isSports" type="button" class="btn-secondary text-sm" @click="showBulkAssign = !showBulkAssign">
                    Bulk assign items
                </button>
                <button v-if="events.length" type="button" class="btn-secondary text-sm" @click="showBulkImport = !showBulkImport">
                    Import CSV
                </button>
                <a :href="`${programBase}/reports`" class="btn-secondary text-sm">All events reports →</a>
            </template>
        </PageHeader>

        <div v-if="schoolRegion?.applies" class="mb-5 max-w-2xl">
            <div v-if="schoolRegion.region" class="notice-banner notice-banner--info text-sm">
                <p>Your Kalotsav region: <strong>{{ schoolRegion.region }}</strong>.
                    <a :href="schoolRegion.set_url" class="link-brand font-semibold">Change →</a>
                </p>
            </div>
            <div v-else class="notice-banner notice-banner--warning text-sm">
                <p class="font-semibold">Select your Kalotsav region</p>
                <p class="mt-1">Your Sahodaya runs Kalotsav by region. Choose your region in
                    <a :href="schoolRegion.set_url" class="link-brand font-semibold">annual registration →</a>
                    (or ask your Sahodaya to assign it).
                </p>
            </div>
        </div>

        <SchoolEventWorkflowStepper v-if="singleEventMode && event?.id"
                                    :school-id="school.id"
                                    :program-prefix="programPrefix"
                                    :event-id="event.id"
                                    :is-sports="isSports"
                                    :current-step="getTab(event.id) === 'athletes' ? 'event-reg' : (getTab(event.id) === 'items' ? 'item-reg' : 'payment')"
                                    @select-step="step => setTab(event.id, step.tab)" />

        <div v-if="showBulkImport && events.length" class="card mb-5 max-w-2xl text-sm border-indigo-100">
            <div class="flex items-center justify-between gap-2 mb-3">
                <p class="font-semibold text-slate-800">Bulk import from CSV</p>
                <button type="button" class="text-slate-400 hover:text-slate-600 text-lg leading-none" @click="showBulkImport = false">×</button>
            </div>
            <p class="text-xs text-slate-500 mb-3">Columns: item_id, item_title, reg_no, team_name, role (performer|standby)</p>
            <div class="flex flex-wrap gap-2 items-end">
                <a :href="`${programBase}/import-template`"
                   class="btn-secondary text-xs">Download template</a>
                <select v-model="importEventId" class="field text-sm max-w-xs">
                    <option value="">Select event</option>
                    <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.title }}</option>
                </select>
                <input type="file" accept=".csv,text/csv" class="text-xs" @change="onImportFile" />
                <button type="button" class="btn-primary text-xs" :disabled="!importEventId || !importFile || importForm.processing"
                        @click="submitImport">
                    Import CSV
                </button>
            </div>
            <ul v-if="$page.props.importErrors?.length" class="mt-3 text-xs text-red-600 list-disc pl-4">
                <li v-for="(err, i) in $page.props.importErrors" :key="i">{{ err }}</li>
            </ul>
        </div>

        <div v-if="showBulkAssign && events.length && isSports" class="card mb-5 max-w-3xl text-sm border-emerald-100">
            <div class="flex items-center justify-between gap-2 mb-3">
                <p class="font-semibold text-slate-800">Bulk assign athletes to items</p>
                <button type="button" class="text-slate-400 hover:text-slate-600 text-lg leading-none" @click="showBulkAssign = false">×</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-gray-600">Event</span>
                    <select v-model="bulkAssignEventId" class="field mt-1">
                        <option value="">Select event</option>
                        <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.title }}</option>
                    </select>
                </label>
            </div>
            <p class="text-xs text-slate-500 mt-3">Select students and items on the registration grid below, then use bulk assign from the event row actions.</p>
            <button type="button" class="btn-primary text-xs mt-3" :disabled="!bulkAssignEventId || bulkAssignForm.processing"
                    @click="submitBulkAssign">
                Assign selected students to checked items
            </button>
        </div>

        <details v-if="!isTeacherFest && isSports" class="mb-5 max-w-3xl rounded-xl border border-slate-200/80 bg-slate-50/50 text-sm group">
            <summary class="px-4 py-3 cursor-pointer select-none font-medium text-slate-700 flex items-center justify-between gap-2">
                <span>How Sports Meet registration works</span>
                <span class="text-xs text-slate-400 group-open:hidden">Show tips</span>
            </summary>
            <div class="px-4 pb-4 pt-0 border-t border-slate-100">
                <ol class="list-decimal pl-4 space-y-1 text-slate-600 mt-3 mb-3">
                    <li><strong>Step 1 · Register for event</strong> — add athletes to the sports fest (event ID assigned).</li>
                    <li><strong>Step 2 · Register by Sport Event</strong> — pick a sport event (Athletics, Chess…), then add athletes to each item inside it.</li>
                    <li>Pay event + item fees in the billing section; Sahodaya approves → chest numbers on fest day.</li>
                </ol>
                <p class="text-xs text-slate-500">
                    <button type="button" class="link-brand font-semibold" @click="showAddStudent = true">Add student</button>
                    or
                    <a :href="`/school-admin/${school.id}/students`" class="link-brand font-semibold">manage students →</a>
                </p>
            </div>
        </details>

        <div v-else-if="!isTeacherFest" class="notice-banner notice-banner--info mb-6 max-w-3xl text-sm">
            Register students against each event item in the list below. Item fees are charged per registration;
            annual Sahodaya membership is paid separately under Annual Registration.
            <button type="button" class="link-brand font-semibold" @click="showAddStudent = true">Add student</button>
            or
            <a :href="`/school-admin/${school.id}/students`" class="link-brand font-semibold whitespace-nowrap">manage all students →</a>
        </div>

        <EmptyState
            v-if="!events.length"
            title="No events open for registration"
            description="Check back when Sahodaya or your school publishes an event with registration open."
            icon="📅"
        />

        <div v-else class="space-y-5">
            <!-- Squad warnings alert -->
            <div v-if="incompleteSquads.length" class="notice-banner notice-banner--warning text-sm mb-4 max-w-3xl">
                <p class="font-semibold text-amber-950">⚠️ Attention: Incomplete Squads</p>
                <p class="mt-1 text-slate-700">You have registered teams for the following items but haven't added the minimum required participants. These may be rejected by Sahodaya:</p>
                <ul class="list-disc pl-5 mt-2 space-y-1 text-slate-700 font-medium">
                    <li v-for="(squad, idx) in incompleteSquads" :key="idx">
                        <strong>{{ squad.item_title }}</strong>: Currently has {{ squad.count }} participant{{ squad.count === 1 ? '' : 's' }} (requires at least {{ squad.min }}).
                    </li>
                </ul>
            </div>

            <div v-if="focusEventId && displayEvents.length === 1" class="notice-banner notice-banner--info text-sm mb-2">
                Showing Sahodaya event registration. <Link :href="`${programBase}/registration`" class="link-brand font-semibold">View all events</Link>
            </div>
            <div v-for="event in displayEvents" :key="event.id" class="card !p-0 overflow-hidden" :id="`event-${event.id}`">
                <!-- Event header -->
                <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50/80 to-white">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-slate-900">{{ event.title }}</h3>
                            <p class="text-xs text-slate-500 mt-0.5">{{ event.payer_label }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 shrink-0">
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full capitalize"
                                  :class="statusClass(event.status)">
                                {{ statusLabel(event.status) }}
                            </span>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                                  :class="event.level_round === 'school' ? 'bg-violet-50 text-violet-700' : 'bg-blue-50 text-blue-700'">
                                {{ event.level_label || event.level_round }}
                            </span>
                            <a v-if="['ongoing','registration_open','published'].includes(event.status)"
                               :href="`${programBase}/fest-day/${event.id}`"
                               class="text-xs font-semibold text-indigo-600 px-2 py-1 rounded-full bg-indigo-50 hover:bg-indigo-100">
                                Fest day →
                            </a>
                            <a :href="`${programBase}/events/${event.id}/substitution-requests`"
                               class="text-xs font-semibold text-slate-600 px-2 py-1 rounded-full bg-slate-100 hover:bg-slate-200">
                                Substitutions
                            </a>
                            <a :href="`${programBase}/events/${event.id}/clash-requests`"
                               class="text-xs font-semibold text-slate-600 px-2 py-1 rounded-full bg-slate-100 hover:bg-slate-200">
                                Clash report
                            </a>
                        </div>
                    </div>
                    <div v-if="event.verification_status?.verification_day" class="mt-3 notice-banner text-xs"
                         :class="event.verification_status.documents_verified ? 'notice-banner--success' : 'notice-banner--warning'">
                        Verification day {{ event.verification_status.verification_day }} —
                        {{ event.verification_status.documents_verified ? 'Documents verified by Sahodaya' : 'Awaiting document verification' }}
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        <span v-if="event.academic_year_label"
                              class="inline-flex items-center gap-1 rounded-lg bg-violet-50 text-violet-800 px-2.5 py-1 border border-violet-100">
                            Academic year <strong class="font-mono">{{ event.academic_year_label }}</strong>
                        </span>
                        <span v-if="event.registration_open || event.registration_close"
                              class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 text-indigo-800 px-2.5 py-1 border border-indigo-100">
                            <strong>Registration:</strong> {{ formatDateRange(event.registration_open, event.registration_close) }}
                        </span>
                        <span v-if="event.event_start || event.event_end"
                              class="inline-flex items-center gap-1 rounded-lg bg-sky-50 text-sky-800 px-2.5 py-1 border border-sky-100">
                            <strong>Competition:</strong> {{ formatDateRange(event.event_start, event.event_end) }}
                        </span>
                        <span v-if="event.quotas && eventType === 'sports'"
                              class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 text-emerald-800 px-2.5 py-1 border border-emerald-100">
                            <strong>{{ event.quotas.used.total }}</strong> school {{ event.quotas.used.total === 1 ? 'entry' : 'entries' }}
                        </span>
                        <span v-if="event.sports_age_cutoff_display || event.sports_age_cutoff_date"
                              class="inline-flex items-center gap-1 rounded-lg bg-slate-100 text-slate-600 px-2.5 py-1">
                            Age cutoff {{ event.sports_age_cutoff_display ?? formatDate(event.sports_age_cutoff_date) }}
                        </span>
                    </div>
                    <p v-if="event.age_rule_summary" class="text-xs text-indigo-800 mt-3 leading-relaxed">
                        {{ event.age_rule_summary }}
                    </p>
                </div>

                <!-- In-card navigation tabs (Option 2) -->
                <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-2.5 flex flex-wrap gap-2 text-xs font-semibold">
                    <button v-if="isSports" type="button" @click="setTab(event.id, 'athletes')"
                            class="px-3.5 py-1.5 rounded-lg transition"
                            :class="getTab(event.id) === 'athletes' ? 'bg-[#0f3d7a] text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'">
                        Step 1: Event Athletes ({{ (event.event_registrations || []).length }})
                    </button>
                    <button type="button" @click="setTab(event.id, 'items')"
                            class="px-3.5 py-1.5 rounded-lg transition"
                            :class="getTab(event.id) === 'items' ? 'bg-[#0f3d7a] text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'">
                        {{ isSports ? 'Step 2: Item Registration' : 'Item Registration' }}
                    </button>
                    <button v-if="event.fee_required" type="button" @click="setTab(event.id, 'payment')"
                            class="px-3.5 py-1.5 rounded-lg transition flex items-center gap-1.5"
                            :class="getTab(event.id) === 'payment' ? 'bg-[#0f3d7a] text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'">
                        <span>{{ isSports ? 'Step 3: Billing & Payment' : 'Billing & Payment' }}</span>
                        <span v-if="event.school_fee?.status" class="text-[10px] px-1.5 py-0.5 rounded-full uppercase font-mono"
                              :class="event.school_fee.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900'">
                            {{ event.school_fee.status }}
                        </span>
                    </button>
                </div>

                <div class="p-5">
                <!-- Kalotsav-style participation quotas -->
                <div v-if="event.quotas && eventType === 'kalolsavam'" class="grid sm:grid-cols-3 gap-2 mb-4">
                    <div class="bg-gray-50 border rounded-lg px-3 py-2 text-xs">
                        <span class="text-gray-500">On-stage</span>
                        <p class="font-semibold">{{ event.quotas.used.on_stage }}/{{ limitLabel(event.quotas.limits?.max_onstage_per_student) }}</p>
                    </div>
                    <div class="bg-gray-50 border rounded-lg px-3 py-2 text-xs">
                        <span class="text-gray-500">Off-stage</span>
                        <p class="font-semibold">{{ event.quotas.used.off_stage }}/{{ limitLabel(event.quotas.limits?.max_offstage_per_student) }}</p>
                    </div>
                    <div class="bg-gray-50 border rounded-lg px-3 py-2 text-xs">
                        <span class="text-gray-500">Group</span>
                        <p class="font-semibold">{{ event.quotas.used.group }}/{{ limitLabel(event.quotas.limits?.max_group_per_student) }}</p>
                    </div>
                </div>

                <div v-if="!canRegister(event)" class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-slate-600">
                    {{ registrationClosedMessage(event) }}
                </div>

                <!-- ── SPORTS: event athletes + head/age filters ── -->
                <div v-else-if="isSports" class="space-y-4">
                    <div v-if="event.download_gate?.blocked"
                         class="notice-banner notice-banner--warning text-sm">
                        <p class="font-semibold">Payment pending — ID cards & hall tickets locked</p>
                        <p class="mt-0.5">{{ event.download_gate.reason }} Pay membership and event fees to download ID cards and admit cards.</p>
                        <p v-if="event.download_gate.links?.payments" class="mt-2">
                            <a :href="event.download_gate.links.payments" class="link-brand font-semibold">Go to payments →</a>
                        </p>
                    </div>

                    <SportsEventAthletesPanel
                        v-show="getTab(event.id) === 'athletes'"
                        :event="event"
                        :students="studentsForEvent(event.id)"
                        :event-registrations="event.event_registrations ?? []"
                        :register-url="`${programBase}/events/${event.id}/register-students`"
                        :items-url="`#item-registration-${event.id}`"
                        :reports-href="`${programBase}/reports/${event.id}`"
                        :student-event-reg-fee="Number(event.student_event_reg_fee ?? 0)"
                        :school-classes="schoolClasses"
                    />

                    <!-- ── Step 2: item registration (inline — Head = Event) ── -->
                    <div v-show="getTab(event.id) === 'items'" :id="`item-registration-${event.id}`" class="rounded-xl border border-emerald-200 overflow-hidden">
                        <div class="px-4 py-3 bg-emerald-50/40 border-b border-emerald-100">
                            <h4 class="text-sm font-bold text-emerald-950">Step 2 · Register for items</h4>
                            <p class="text-xs text-emerald-900/80 mt-0.5">
                                <strong>{{ eventRegisteredCount(event) }}</strong> event athlete{{ eventRegisteredCount(event) === 1 ? '' : 's' }}
                                · pick items below and add participants.
                                <span v-if="sportsRegistrationSummary(event)"> {{ sportsRegistrationSummary(event) }}</span>
                            </p>
                        </div>

                        <div v-if="event.require_event_registration && !eventRegisteredCount(event)"
                             class="px-4 py-3 text-sm text-amber-800 bg-amber-50 border-b border-amber-100">
                            Register students for the event above first — item registration needs event athletes.
                        </div>

                        <div class="px-4 py-2 bg-white border-b border-gray-100 flex flex-wrap gap-2 items-center">
                            <input v-model="sportsSearch[event.id]" type="search"
                                   class="field flex-1 min-w-[10rem] !py-1.5 text-sm"
                                   placeholder="Search items…" autocomplete="off">
                            <select v-model="sportsAgeFilter[event.id]" class="field text-xs !py-1.5 min-w-[9rem] max-w-[14rem]">
                                <option value="">All age categories</option>
                                <option v-for="(label, key) in (event.item_group_labels ?? {})" :key="key" :value="key">
                                    {{ label }}
                                </option>
                            </select>
                            <button v-if="sportsSearch[event.id] || sportsAgeFilter[event.id]"
                                    type="button" class="btn-ghost text-xs !py-1.5"
                                    @click="clearSportsFilters(event.id)">
                                Clear
                            </button>
                        </div>

                        <div class="overflow-x-auto bg-white">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide min-w-[160px]">Event item</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide min-w-[140px]">Eligibility</th>
                                        <th v-if="event.fee_required" class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-24">Item fee</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide min-w-[120px]">Registered</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-48">Participants</th>
                                        <th class="px-3 py-2 w-24"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <FestRegistrationItemRow
                                        v-for="item in sportsFlatItems(event)"
                                        :key="item.id"
                                        :row-id="itemRowId(event.id, item.id)"
                                        :item="item"
                                        :form="itemForms[itemFormKey(event.id, item.id)]"
                                        :registrations="registrationsForItem(event.id, item.id)"
                                        :eligible-students="eligibleStudentsForItem(event.id, item)"
                                        :all-students="studentsForEvent(event.id)"
                                        :student-ineligibility-reason="(student) => studentIneligibilityReason(student, event, item)"
                                        :show-fee="event.fee_required"
                                        :blocked="isItemBlocked(event, item)"
                                        :block-reason="itemBlockReason(event, item)"
                                        :error-message="itemErrors[itemFormKey(event.id, item.id)]"
                                        :status-label="itemStatusMeta(event, item).label"
                                        :status-class="itemStatusMeta(event, item).badgeClass"
                                        :status-hint="itemStatusMeta(event, item).hint"
                                        performer-label="participants"
                                        :is-teacher-fest="false"
                                        :event-type="eventType"
                                        :teachers="teachers"
                                        :student-label="studentOptionLabel"
                                        :registered-names="registeredNames"
                                        :can-withdraw="canWithdraw"
                                        :can-edit="canEdit"
                                        :editing-registration-id="editingRegistrationId[itemFormKey(event.id, item.id)]"
                                        :column-count="event.fee_required ? 6 : 5"
                                        @register="submitItem(event, item)"
                                        @update="updateItem(event, item)"
                                        @withdraw="withdraw"
                                        @edit="startEdit($event, event, item)"
                                        @cancel-edit="cancelEdit(event, item)"
                                        @add-student="showAddStudent = true"
                                    />
                                </tbody>
                            </table>
                            <p v-if="!sportsFlatItems(event).length" class="px-4 py-6 text-sm text-slate-500 text-center">
                                No items match the current filters.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ── KALOTSAV / KIDS FEST / TEACHER FEST: generic flat table ── -->
                <form v-else v-show="getTab(event.id) === 'items'" class="mt-4 space-y-4" @submit.prevent>
                    <div class="rounded-xl border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide min-w-[160px]">Event item</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide min-w-[140px]">Eligibility</th>
                                        <th v-if="event.fee_required" class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-24">Item fee</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide min-w-[120px]">Registered</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-48">
                                            {{ isTeacherFest ? 'Teachers' : 'Students' }}
                                        </th>
                                        <th class="px-3 py-2 w-24"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <FestRegistrationItemRow
                                        v-for="item in allItems(event)"
                                        :key="item.id"
                                        :row-id="itemRowId(event.id, item.id)"
                                        :item="item"
                                        :form="itemForms[itemFormKey(event.id, item.id)]"
                                        :registrations="registrationsForItem(event.id, item.id)"
                                        :eligible-students="eligibleStudentsForItem(event.id, item)"
                                        :all-students="studentsForEvent(event.id)"
                                        :student-ineligibility-reason="(student) => studentIneligibilityReason(student, event, item)"
                                        :show-fee="event.fee_required"
                                        :blocked="isItemBlocked(event, item)"
                                        :block-reason="itemBlockReason(event, item)"
                                        :error-message="itemErrors[itemFormKey(event.id, item.id)]"
                                        :status-label="itemStatusMeta(event, item).label"
                                        :status-class="itemStatusMeta(event, item).badgeClass"
                                        :status-hint="itemStatusMeta(event, item).hint"
                                        :performer-label="isTeacherFest ? 'teachers' : (isSports ? 'participants' : 'students')"
                                        :is-teacher-fest="isTeacherFest"
                                        :event-type="eventType"
                                        :teachers="teachers"
                                        :student-label="studentOptionLabel"
                                        :registered-names="registeredNames"
                                        :can-withdraw="canWithdraw"
                                        :can-edit="canEdit"
                                        :editing-registration-id="editingRegistrationId[itemFormKey(event.id, item.id)]"
                                        :column-count="event.fee_required ? 6 : 5"
                                        @register="submitItem(event, item)"
                                        @update="updateItem(event, item)"
                                        @withdraw="withdraw"
                                        @edit="startEdit($event, event, item)"
                                        @cancel-edit="cancelEdit(event, item)"
                                        @add-student="showAddStudent = true"
                                    />
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>

                <EventBillingPanel
                    v-if="event.fee_required && (event.uses_per_head_billing ? event.school_head_fees?.length : event.school_fee)"
                    v-show="getTab(event.id) === 'payment'"
                    :event="event"
                    :school-id="school.id"
                    :program-base="programBase"
                    :payment-details="paymentDetails"
                    :item-fee-lines="itemFeeLines(event)"
                    :item-fees-due="itemFeesDue(event)"
                    :is-min-fee-applied="isMinFeeApplied(event)"
                    :event-payment-ref="eventPaymentRefs[event.id] ?? ''"
                    :head-payment-ref-map="headPaymentRefs"
                    @upload-event-payment="uploadEventPayment(event)"
                    @set-event-file="file => eventPaymentFiles[event.id] = file"
                    @update-event-ref="refVal => eventPaymentRefs[event.id] = refVal"
                    @upload-head-payment="headFee => uploadHeadPayment(event, headFee)"
                    @set-head-file="(headId, file) => setHeadPaymentFile(event.id, headId, file)"
                    @update-head-ref="(headId, refVal) => headPaymentRefs[headPaymentKey(event.id, headId)] = refVal"
                />
                <p v-else-if="canRegister(event) && !event.fee_required" class="text-xs text-gray-400 mt-4 border-t border-gray-100 pt-4">No fee for this round</p>
                </div>
            </div>
        </div>

        <QuickAddStudentModal
            v-model="showAddStudent"
            :school="school"
            :school-classes="schoolClasses"
            :student-edit-lock="studentEditLock"
        />
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import QuickAddStudentModal from '@/Components/school/QuickAddStudentModal.vue';
import FestRegistrationItemRow from '@/Components/school/FestRegistrationItemRow.vue';
import SportsEventAthletesPanel from '@/Components/school/SportsEventAthletesPanel.vue';
import SchoolEventWorkflowStepper from '@/Components/school/SchoolEventWorkflowStepper.vue';
import EventBillingPanel from '@/Components/school/EventBillingPanel.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { genderLabel } from '@/support/festItemEligibility.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    eventType: String,
    events: Array,
    focusEventId: { type: Number, default: null },
    singleEventMode: { type: Boolean, default: false },
    event: { type: Object, default: null },
    programPrefix: { type: String, default: '' },
    registrations: Array,
    students: Array,
    studentsByEvent: { type: Object, default: () => ({}) },
    lazyLoadStudents: { type: Boolean, default: false },
    studentCount: { type: Number, default: 0 },
    schoolClasses: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
    isTeacherFest: { type: Boolean, default: false },
    studentEditLock: { type: Object, default: () => ({ locked: false }) },
    schoolRegion: { type: Object, default: null },
    profile: { type: Object, default: null },
});

const paymentDetails = computed(() => props.profile?.payment_details_text || '');

const { programSlug, programLabel, programBase } = useSchoolProgramContext(props);
const page = usePage();
const programPrefix = computed(() =>
    props.programPrefix
    || page.props.programPrefix
    || programBase.value.split('/').pop(),
);

const activeTabMap = reactive({});

function getTab(eventId) {
    if (!activeTabMap[eventId]) {
        const urlParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
        const tabParam = urlParams ? urlParams.get('tab') : null;

        if (tabParam === 'event-reg' || tabParam === 'athletes' || tabParam === 'student-reg') {
            activeTabMap[eventId] = 'athletes';
        } else if (tabParam === 'item-reg' || tabParam === 'items') {
            activeTabMap[eventId] = 'items';
        } else if (tabParam === 'payment' || tabParam === 'billing' || tabParam === 'fees') {
            activeTabMap[eventId] = 'payment';
        } else if (props.eventType === 'sports' || isSports.value) {
            activeTabMap[eventId] = 'athletes';
        } else {
            activeTabMap[eventId] = 'items';
        }
    }
    return activeTabMap[eventId];
}

function setTab(eventId, tab) {
    activeTabMap[eventId] = tab;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        const tabKeyMap = { athletes: 'student-reg', items: 'item-reg', payment: 'fees' };
        url.searchParams.set('tab', tabKeyMap[tab] || tab);
        window.history.replaceState({}, '', url.toString());
    }
}

function registeredItemCount(event) {
    let count = 0;
    const items = event.items || [];
    for (const item of items) {
        if (registrationsForItem(event.id, item.id)?.length > 0) {
            count++;
        }
    }
    return count;
}

function isMinFeeApplied(event) {
    const minFee = Number(event.fee_settings?.school_fee_min ?? (props.eventType === 'sports' ? 1500 : 0));
    if (!minFee || !event.school_fee) return false;
    const totalDue = Number(event.school_fee.total_due ?? 0);
    return totalDue > 0 && totalDue === minFee;
}
const isSports = computed(() => props.eventType === 'sports' || programSlug.value === 'sports-meet');
const isLocked = computed(() => !!props.studentEditLock?.locked);

const displayEvents = computed(() => {
    if (!props.focusEventId) return props.events ?? [];
    return (props.events ?? []).filter((e) => e.id === props.focusEventId);
});

onMounted(() => {
    // Head = Event for sports now — head_navigation is always empty, so there's
    // no per-head filter to preselect from a ?head_id= URL param anymore.

    if (!props.focusEventId) return;
    requestAnimationFrame(() => {
        document.getElementById(`event-${props.focusEventId}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    if (props.lazyLoadStudents) {
        for (const event of props.events ?? []) {
            loadStudentsForEvent(event.id);
        }
    }
});

async function loadStudentsForEvent(eventId) {
    const map = props.studentsByEvent ?? {};
    if ((map[eventId] ?? map[String(eventId)] ?? []).length) {
        return;
    }
    if (fetchedStudentsByEvent[eventId]?.length) {
        return;
    }

    try {
        const res = await fetch(`${programBase.value}/events/${eventId}/eligible-students?json=1`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) return;
        const data = await res.json();
        fetchedStudentsByEvent[eventId] = data.students ?? [];
    } catch {
        // keep empty pool — user can refresh
    }
}

const importEventId = ref('');
const importFile = ref(null);
const importForm = useForm({ event_id: '', file: null });
const showAddStudent = ref(false);
const showBulkImport = ref(false);
const showBulkAssign = ref(false);
const bulkAssignEventId = ref('');
const bulkAssignStudentIds = ref([]);
const bulkAssignItemIds = ref([]);
const bulkAssignForm = useForm({ student_ids: [], item_ids: [] });
const sportsSearch = reactive({});
const sportsAgeFilter = reactive({});
const sportsItemFilter = reactive({});
const fetchedStudentsByEvent = reactive({});

const kalotsavItemGroups = [
    { key: 'on_stage', label: 'On stage' },
    { key: 'off_stage', label: 'Off stage' },
    { key: 'group', label: 'Group / team' },
    { key: 'other', label: 'Other' },
];

const SPORTS_AGE_ORDER = ['u8', 'u10', 'u11', 'u12', 'u14', 'u17', 'u19', 'open'];
const SPORTS_MALE_VALS = new Set(['male', 'm', 'boys', 'boy']);
const SPORTS_FEMALE_VALS = new Set(['female', 'f', 'girls', 'girl']);

function eventRegisteredStudentIds(event) {
    return new Set((event.event_registrations ?? []).map((r) => r.student_id));
}

function eventRegisteredCount(event) {
    const fromEventList = (event.event_registrations ?? []).length;
    if (fromEventList > 0) return fromEventList;
    return studentsForEvent(event.id).filter(
        (s) => s.event_registered || s.event_registration_number,
    ).length;
}

// Head = Event for sports now — no head filter, just item id + free-text search.
function sportsItemsForFilters(event) {
    const itemId = sportsItemFilter[event.id] ?? '';
    let items = event?.items ?? [];

    if (itemId) {
        items = items.filter((i) => Number(i.id) === Number(itemId));
    }

    const q = (sportsSearch[event.id] ?? '').trim().toLowerCase();
    if (q) {
        items = items.filter((i) => String(i.title ?? '').toLowerCase().includes(q));
    }

    return items;
}

function sportsGroups(event) {
    const items = sportsItemsForFilters(event);
    const labels = event?.item_group_labels ?? {};
    const students = studentsForEvent(event.id);
    const allRegs = props.registrations ?? [];
    const registeredStudentIds = eventRegisteredStudentIds(event);

    const byAge = {};
    for (const item of items) {
        const key = item.age_group || 'open';
        if (!byAge[key]) byAge[key] = [];
        byAge[key].push(item);
    }

    return Object.keys(byAge)
        .filter((key) => (byAge[key]?.length ?? 0) > 0)
        .sort((a, b) => {
            const ai = SPORTS_AGE_ORDER.indexOf(a.toLowerCase());
            const bi = SPORTS_AGE_ORDER.indexOf(b.toLowerCase());
            return (ai < 0 ? 99 : ai) - (bi < 0 ? 99 : bi);
        })
        .map((key) => {
            const groupItems = byAge[key] ?? [];
            const label = labels[key] ?? String(key).toUpperCase();
            const itemIds = new Set(groupItems.map((i) => Number(i.id)));

            const eligiblePool = event.require_event_registration
                ? students.filter((s) => registeredStudentIds.has(s.id))
                : students;

            const eligibleCount = eligiblePool.filter(
                (s) => (s.eligible_sports_groups ?? []).map(g => g.toLowerCase()).includes(key.toLowerCase()),
            ).length;

            const registeredCount = allRegs.filter(
                (r) => Number(r.event_id) === Number(event.id)
                    && itemIds.has(Number(r.item_id))
                    && !['withdrawn', 'rejected'].includes(r.status),
            ).length;

            let openCount = 0;
            let noEligibleCount = 0;
            for (const item of groupItems) {
                const status = itemRegistrationStatus(event, item);
                if (status === 'open' || status === 'partial') openCount++;
                else if (status === 'no_eligible') noEligibleCount++;
            }

            const maleItems = groupItems.filter((i) => SPORTS_MALE_VALS.has(String(i.gender ?? '').toLowerCase()));
            const femaleItems = groupItems.filter((i) => SPORTS_FEMALE_VALS.has(String(i.gender ?? '').toLowerCase()));
            const openItems = groupItems.filter(
                (i) => !SPORTS_MALE_VALS.has(String(i.gender ?? '').toLowerCase())
                    && !SPORTS_FEMALE_VALS.has(String(i.gender ?? '').toLowerCase()),
            );

            const hasBoth = maleItems.length > 0 && femaleItems.length > 0;
            const genderGroups = [];
            if (maleItems.length) genderGroups.push({ gender: 'male', label: hasBoth ? 'Boys' : '', items: maleItems });
            if (femaleItems.length) genderGroups.push({ gender: 'female', label: hasBoth ? 'Girls' : '', items: femaleItems });
            if (openItems.length) {
                genderGroups.push({ gender: 'open', label: hasBoth ? 'Open / Mixed' : '', items: openItems });
            }
            if (!genderGroups.length) genderGroups.push({ gender: 'all', label: '', items: groupItems });

            return { key, label, items: groupItems, eligibleCount, registeredCount, openCount, noEligibleCount, genderGroups };
        });
}

function filteredSportsGroups(event) {
    const ageKey = sportsAgeFilter[event.id] ?? '';
    return sportsGroups(event).filter((group) => !ageKey || group.key === ageKey);
}

function sportsRegistrationSummary(event) {
    const groups = filteredSportsGroups(event);
    let open = 0;
    let registered = 0;
    let noMatch = 0;
    let total = 0;

    for (const group of groups) {
        for (const gg of group.genderGroups) {
            for (const item of gg.items) {
                total++;
                const status = itemRegistrationStatus(event, item);
                if (status === 'open' || status === 'partial') open++;
                else if (status === 'registered') registered++;
                else if (status === 'no_eligible') noMatch++;
            }
        }
    }

    if (!total) return '';

    const parts = [`${total} event${total === 1 ? '' : 's'}`];
    if (open) parts.push(`${open} open for registration`);
    if (registered) parts.push(`${registered} registered`);
    if (noMatch) parts.push(`${noMatch} need matching ${isSports.value ? 'participants' : 'students'}`);
    return parts.join(' · ');
}

function groupVisibleItemCount(group) {
    return group.genderGroups.reduce((n, gg) => n + gg.items.length, 0);
}

// Flat, filtered item list for the inline sports item-registration table
// (Head = Event: no head grouping — age filter + search only), ordered by age group.
function sportsFlatItems(event) {
    let items = sportsItemsForFilters(event);

    const ageKey = sportsAgeFilter[event.id] ?? '';
    if (ageKey) {
        items = items.filter((i) => (i.age_group || 'open') === ageKey);
    }

    return [...items].sort((a, b) => {
        const ai = SPORTS_AGE_ORDER.indexOf(String(a.age_group || 'open').toLowerCase());
        const bi = SPORTS_AGE_ORDER.indexOf(String(b.age_group || 'open').toLowerCase());
        if (ai !== bi) return (ai < 0 ? 99 : ai) - (bi < 0 ? 99 : bi);
        return String(a.title ?? '').localeCompare(String(b.title ?? ''));
    });
}

function clearSportsFilters(eventId) {
    sportsSearch[eventId] = '';
    sportsAgeFilter[eventId] = '';
    sportsItemFilter[eventId] = '';
}

function onImportFile(e) {
    importFile.value = e.target.files[0] ?? null;
}

function submitImport() {
    importForm.event_id = importEventId.value;
    importForm.file = importFile.value;
    importForm.post(`${programBase.value}/import`, {
        forceFormData: true,
        preserveScroll: true,
    });
}

const itemGroups = kalotsavItemGroups;

function itemGroupsFor(event) {
    const grouped = event?.items_grouped ?? {};
    const labels = event?.item_group_labels ?? {};

    if (props.eventType === 'sports') {
        return Object.keys(grouped)
            .filter((key) => (grouped[key]?.length ?? 0) > 0)
            .map((key) => ({ key, label: labels[key] ?? String(key).toUpperCase() }));
    }

    if (props.eventType === 'kids_fest') {
        return Object.keys(grouped)
            .filter((key) => (grouped[key]?.length ?? 0) > 0)
            .map((key) => ({ key, label: labels[key] ?? 'Events' }));
    }

    return kalotsavItemGroups.filter((g) => (grouped[g.key]?.length ?? 0) > 0);
}

function limitLabel(val) {
    return val == null || val === '' ? '∞' : val;
}

const itemForms = reactive({});
const itemErrors = reactive({});
const eventPaymentFiles = reactive({});
const eventPaymentRefs = reactive({});
const headPaymentFiles = reactive({});
const headPaymentRefs = reactive({});
const editingRegistrationId = reactive({});

function allItemsStatic(event) {
    return event?.items ?? [];
}

function itemFormKey(eventId, itemId) {
    return `${eventId}-${itemId}`;
}

function headPaymentKey(eventId, headId) {
    return `${eventId}:${headId}`;
}

for (const e of props.events) {
    eventPaymentRefs[e.id] = '';
    sportsSearch[e.id] = '';
    sportsAgeFilter[e.id] = '';
    sportsItemFilter[e.id] = '';
    for (const item of allItemsStatic(e)) {
        itemForms[itemFormKey(e.id, item.id)] = {
            team_name: '',
            coach_name: '',
            coach_phone: '',
            manager_name: '',
            manager_phone: '',
            student_ids: [],
            teacher_ids: [],
            standby_ids: [],
        };
    }
}

function allItems(event) {
    return event?.items ?? [];
}

function formatMoney(value) {
    const n = Number(value);
    if (Number.isNaN(n)) return '0.00';
    return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function registrationsForItem(eventId, itemId) {
    return (props.registrations ?? []).filter(
        reg => Number(reg.event_id) === Number(eventId) && Number(reg.item_id) === Number(itemId)
            && !['withdrawn', 'rejected'].includes(reg.status),
    );
}

function registeredNames(reg) {
    const labels = (reg.participants ?? [])
        .filter(p => p.participant_role !== 'standby')
        .map((p) => {
            const name = p.student?.name ?? p.teacher?.name;
            const regNo = p.student?.reg_no ?? p.teacher?.reg_no;
            const festId = p.level_registration_number;
            if (name && festId) return `${name} (${festId})`;
            if (name && regNo) return `${name} (${regNo})`;
            return name ?? regNo;
        })
        .filter(Boolean);
    return labels.length ? labels.join(', ') : 'Registered';
}

function isGroupItemRow(item) {
    return item && ['group', 'team'].includes(item.participant_type);
}

function studentsForEvent(eventId) {
    const map = props.studentsByEvent ?? {};
    return fetchedStudentsByEvent[eventId]
        ?? map[eventId]
        ?? map[String(eventId)]
        ?? props.students
        ?? [];
}

function requireVerifiedForEvent(event) {
    return event?.require_verified_students !== false;
}

const GROUP_ALIASES = {
    lp: ['lp', 'category1', 'category_1', 'cat1', 'category 1', 'cat 1'],
    up: ['up', 'category2', 'category_2', 'cat2', 'category 2', 'cat 2'],
    hs: ['hs', 'category3', 'category_3', 'cat3', 'category 3', 'cat 3'],
    hss: ['hss', 'category4', 'category_4', 'cat4', 'category 4', 'cat 4'],
};

function matchesClassGroup(studentGrpRaw, itemGrpRaw) {
    if (!itemGrpRaw || itemGrpRaw === 'open') return true;
    const studentGrp = String(studentGrpRaw ?? '').toLowerCase().trim();
    const itemGrp = String(itemGrpRaw ?? '').toLowerCase().trim();
    if (studentGrp === itemGrp) return true;

    for (const [, aliases] of Object.entries(GROUP_ALIASES)) {
        if (aliases.includes(studentGrp) && aliases.includes(itemGrp)) {
            return true;
        }
    }
    return false;
}

function studentMatchesItem(student, event, item, { skipVerification = false } = {}) {
    if (!skipVerification && requireVerifiedForEvent(event) && student.is_verified === false) {
        return false;
    }
    if (event?.academic_year_id && student.academic_year_id && event.academic_year_id !== student.academic_year_id) {
        return false;
    }
    if (['kalolsavam', 'custom'].includes(props.eventType)) {
        if (props.eventType === 'kalolsavam' && student.eligible_kalolsav === false) return false;
        if (item.class_group && item.class_group !== 'open') {
            if (!matchesClassGroup(student.kalolsav_class_group, item.class_group)) return false;
        }
    }
    if (props.eventType === 'kids_fest') {
        if (!student.eligible_kids_fest) return false;
        if (item.kids_band && item.kids_band !== 'open' && student.kids_fest_band !== item.kids_band) return false;
    }
    if (props.eventType === 'sports') {
        if (event?.require_event_registration && !eventRegisteredStudentIds(event).has(student.id)) {
            return false;
        }
        if (!student.dob) {
            return false;
        }
        if (item.age_group && item.age_group !== 'open') {
            const groups = student.eligible_sports_groups ?? [];
            if (!groups.includes(item.age_group)) return false;
        }
    }
    if (item.gender && !['open', 'mixed'].includes(item.gender) && student.gender && student.gender !== item.gender) {
        return false;
    }
    return true;
}

function eligibleStudentsForItem(eventId, item) {
    const event = props.events.find(e => e.id === eventId);
    const pool = studentsForEvent(eventId);
    let filtered = pool.filter(s => studentMatchesItem(s, event, item));
    if (event?.require_event_registration) {
        const registered = eventRegisteredStudentIds(event);
        filtered = filtered.filter((s) => registered.has(s.id));
    }
    return filtered;
}

function studentIneligibilityReason(student, event, item) {
    if (requireVerifiedForEvent(event) && student.is_verified === false) {
        return 'Pending Sahodaya verification';
    }
    if (event?.academic_year_id && student.academic_year_id
        && Number(event.academic_year_id) !== Number(student.academic_year_id)) {
        return 'Not enrolled in this event\'s academic year';
    }

    const itemGender = String(item.gender ?? 'open').toLowerCase();
    const studentGender = String(student.gender ?? '').toLowerCase();
    if (!['open', 'mixed'].includes(itemGender)) {
        if (!studentGender || studentGender === 'open') {
            return 'Set gender on the student profile';
        }
        if (studentGender !== itemGender) {
            return `This item is for ${genderLabel(itemGender) ?? itemGender} only`;
        }
    }

    if (props.eventType === 'sports') {
        if (event?.require_event_registration && !eventRegisteredStudentIds(event).has(student.id)) {
            return 'Register for the event first (Event athletes section above)';
        }
        if (!student.dob) {
            return 'Date of birth is required for sports';
        }
        const itemAge = item.age_group;
        if (itemAge && itemAge !== 'open') {
            const groups = student.eligible_sports_groups ?? [];
            if (!groups.includes(itemAge)) {
                const underN = String(itemAge).replace(/^u/i, '');
                const ageHint = student.sports_age_on_cutoff != null
                    ? ` (age ${student.sports_age_on_cutoff} on cutoff)`
                    : '';
                return `Must be under ${underN} on cutoff date${ageHint} — not eligible for ${String(itemAge).toUpperCase()}`;
            }
        }
    }

    if (props.eventType === 'kalolsavam') {
        if (!student.eligible_kalolsav) return 'Not eligible for Kalotsav (Classes 3–12)';
        if (item.class_group && item.class_group !== 'open' && student.kalolsav_class_group !== item.class_group) {
            return classGroupMismatchReason(student, item, event);
        }
    }

    if (props.eventType === 'custom') {
        if (item.class_group && item.class_group !== 'open') {
            if (!student.kalolsav_class_group) return 'Class is not assigned to a membership category';
            if (student.kalolsav_class_group !== item.class_group) {
                return classGroupMismatchReason(student, item, event);
            }
        }
    }

    if (props.eventType === 'kids_fest') {
        if (!student.eligible_kids_fest) return 'Not eligible for Kids Fest (Pre-KG to Class 2)';
    }

    return 'Not eligible for this item';
}

function itemFeeLines(event) {
    const lines = event.school_fee?.breakdown?.items ?? [];
    return lines.filter(line => !String(line.label).toLowerCase().includes('school registration'));
}

function itemFeesDue(event) {
    return itemFeeLines(event).reduce((sum, line) => sum + Number(line.amount || 0), 0);
}

function schoolMembershipFeeAmount(event) {
    const line = (event.school_fee?.breakdown?.items ?? []).find(
        l => String(l.label).toLowerCase().includes('school registration'),
    );
    return line?.amount ?? event.school_fee?.school_registration_fee ?? 0;
}

function canRegister(event) {
    if (event.school_fest_registration_closed || props.school?.fest_registration_closed) {
        return false;
    }
    if (event.registration_locked) {
        return false;
    }
    return event.status === 'registration_open';
}

function statusLabel(status) {
    const labels = {
        published: 'Published — registration not open',
        registration_open: 'Registration open',
        ongoing: 'Event ongoing',
        completed: 'Completed',
        draft: 'Draft',
    };
    return labels[status] ?? status;
}

function statusClass(status) {
    if (status === 'registration_open') return 'bg-green-50 text-green-700';
    if (status === 'published') return 'bg-amber-50 text-amber-800';
    if (status === 'ongoing') return 'bg-blue-50 text-blue-700';
    return 'bg-gray-100 text-gray-600';
}

function registrationClosedMessage(event) {
    if (event.school_fest_registration_closed || props.school?.fest_registration_closed) {
        return 'Fest registration has been closed for your school by Sahodaya. Contact your coordinator to reopen.';
    }
    if (event.registration_locked) {
        return 'Registration is locked for this event.';
    }
    if (event.status === 'published') {
        return 'This event is published but registration has not been opened yet. Check back when your Sahodaya opens registration.';
    }
    if (event.status === 'ongoing') {
        return 'Registration is closed — this event is already in progress.';
    }
    if (event.status === 'completed') {
        return 'This event has ended. Registration is no longer available.';
    }
    return 'Registration is not open for this event.';
}

function studentOptionLabel(student) {
    const parts = [];
    if (student.event_registration_number) {
        parts.push(`Fest ID ${student.event_registration_number}`);
    }
    if (student.reg_no) parts.push(student.reg_no);
    parts.push(student.class_name || 'no class');
    if (student.sports_age_on_cutoff != null) parts.push(`age ${student.sports_age_on_cutoff}`);
    if (student.sports_age_group) parts.push(String(student.sports_age_group).toUpperCase());
    if (student.kalolsav_class_group) parts.push(`Cat ${categoryShort(student)}`);
    if (student.kids_fest_band) parts.push(student.kids_fest_band);
    const g = genderLabel(student.gender);
    if (g) parts.push(g);
    return parts.join(' · ');
}

function categoryShort(student) {
    const map = { lp: '1', up: '2', hs: '3', hss: '4' };
    return map[student.kalolsav_class_group] ?? student.kalolsav_class_group;
}

function classGroupMismatchReason(student, item, event) {
    const labels = event?.class_group_labels ?? {};
    const expected = labels[item.class_group] ?? item.class_group?.toUpperCase?.() ?? item.class_group;
    const actual = labels[student.kalolsav_class_group]
        ?? (student.class_name ? `Class ${student.class_name}` : student.kalolsav_class_group?.toUpperCase?.() ?? 'another category');

    return `Student is in ${actual}, but this item is for ${expected}`;
}

function performerCount(reg) {
    const performers = reg.participants?.filter(p => p.participant_role !== 'standby') ?? reg.participants ?? [];
    return performers.length;
}

function standbyCount(reg) {
    return reg.participants?.filter(p => p.participant_role === 'standby').length ?? 0;
}

function canWithdraw(reg) {
    if (['withdrawn', 'rejected'].includes(reg.status)) return false;
    const event = props.events.find(e => e.id === reg.event_id);
    if (!event) return reg.status === 'submitted';
    if (event.results_published || ['completed', 'cancelled'].includes(event.status)) return false;
    // Mirrors the server-side rule (FestRegistrationService::canSchoolCancel): once the
    // school has an approved/paid fee for this event, cancellation is blocked to avoid
    // an out-of-sync fee ledger. Without this check the Cancel link was always shown and
    // always failed with a 422 once payment was approved.
    const fee = event.school_fee;
    if (fee && (fee.status === 'approved' || Number(fee.amount_paid ?? 0) > 0)) return false;
    return event.status === 'registration_open' || reg.status === 'submitted';
}

function withdraw(id) {
    if (!confirm('Cancel this registration?')) return;
    router.post(`${programBase.value}/registrations/${id}/withdraw`, {}, { preserveScroll: true });
}

function canEdit(reg) {
    if (['withdrawn', 'rejected'].includes(reg.status)) return false;
    const event = props.events.find(e => e.id === reg.event_id);
    if (!event) return reg.status === 'submitted';
    if (event.schedule_published || event.results_published || ['completed', 'cancelled'].includes(event.status)) return false;
    return event.status === 'registration_open' || reg.status === 'submitted';
}

function resetItemForm(eventId, itemId) {
    const key = itemFormKey(eventId, itemId);
    itemForms[key] = {
        team_name: '',
        coach_name: '',
        coach_phone: '',
        manager_name: '',
        manager_phone: '',
        student_ids: [],
        teacher_ids: [],
        standby_ids: [],
    };
}

function startEdit(reg, event, item) {
    const key = itemFormKey(event.id, item.id);
    const participants = reg.participants ?? [];
    const performerIds = participants
        .filter(p => p.participant_role !== 'standby')
        .map(p => p.student_id ?? p.teacher_id)
        .filter(Boolean);
    const standbyIds = participants
        .filter(p => p.participant_role === 'standby')
        .map(p => p.student_id)
        .filter(Boolean);
    const group = participants.find(p => p.group)?.group;

    itemForms[key] = {
        team_name: group?.team_name ?? '',
        coach_name: group?.coach_name ?? '',
        coach_phone: group?.coach_phone ?? '',
        manager_name: group?.manager_name ?? '',
        manager_phone: group?.manager_phone ?? '',
        student_ids: props.isTeacherFest ? [] : performerIds,
        teacher_ids: props.isTeacherFest ? performerIds : [],
        standby_ids: standbyIds,
    };
    editingRegistrationId[key] = reg.id;
    delete itemErrors[key];
}

function cancelEdit(event, item) {
    const key = itemFormKey(event.id, item.id);
    delete editingRegistrationId[key];
    resetItemForm(event.id, item.id);
    delete itemErrors[key];
}

function updateItem(event, item) {
    const key = itemFormKey(event.id, item.id);
    const registrationId = editingRegistrationId[key];
    if (!registrationId) return;
    const form = itemForms[key];
    const standby = (form.standby_ids ?? []).slice(0, 2);
    delete itemErrors[key];

    if (!['group', 'team'].includes(item.participant_type) && (form.student_ids?.length ?? 0) > 1) {
        itemErrors[key] = 'This item allows only one participant.';
        scrollToItemRow(event.id, item.id);
        return;
    }

    router.post(`${programBase.value}/registrations/${registrationId}/update`, {
        team_name: form.team_name,
        coach_name: form.coach_name || null,
        coach_phone: form.coach_phone || null,
        manager_name: form.manager_name || null,
        manager_phone: form.manager_phone || null,
        student_ids: form.student_ids,
        teacher_ids: form.teacher_ids,
        standby_ids: standby,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            delete itemErrors[key];
            delete editingRegistrationId[key];
            resetItemForm(event.id, item.id);
        },
        onError: (errors) => {
            itemErrors[key] = extractItemErrors(errors, item.id)
                || errors.registration
                || page.props.flash?.error
                || 'Could not update registration.';
            scrollToItemRow(event.id, item.id);
        },
    });
}

function itemRegistrationCount(eventId, itemId) {
    return registrationsForItem(eventId, itemId).length;
}

function itemMaxPerSchool(item) {
    const max = Number(item.max_per_school ?? 1);
    return max > 0 ? max : 1;
}

function isItemFull(event, item) {
    return itemRegistrationCount(event.id, item.id) >= itemMaxPerSchool(item);
}

function itemRegWindowMessage(item) {
    if (item.registration_open !== false) return '';
    if (item.reg_start && item.reg_end) {
        return `Registration for this item is closed (window was ${item.reg_start} – ${item.reg_end}).`;
    }
    if (item.reg_end) return `Registration for this item closed on ${item.reg_end}.`;
    if (item.reg_start) return `Registration for this item opens on ${item.reg_start}.`;
    return 'Registration is closed for this item.';
}

function itemBlockReason(event, item) {
    if (item.registration_open === false) {
        return itemRegWindowMessage(item);
    }

    // Skip the "full" check when the school is editing one of their own existing
    // registrations — that registration already counts in the full tally, so we
    // should not block the edit/standby buttons.
    const isCurrentlyEditing = editingRegistrationId[itemFormKey(event.id, item.id)] != null;
    if (!isCurrentlyEditing && isItemFull(event, item)) {
        const max = itemMaxPerSchool(item);
        return max === 1
            ? 'Your school already has an entry for this item (max 1 per school).'
            : `Maximum ${max} entries per school for this item — limit reached.`;
    }

    const quotas = event.quotas;
    if (!quotas) return '';

    const limits = quotas.limits ?? {};
    if (item.stage_type === 'on_stage' && limits.max_onstage_per_school != null
        && quotas.used.on_stage >= limits.max_onstage_per_school) {
        return `School on-stage participation limit reached (max ${limits.max_onstage_per_school}).`;
    }
    if (item.stage_type === 'off_stage' && limits.max_offstage_per_school != null
        && quotas.used.off_stage >= limits.max_offstage_per_school) {
        return `School off-stage participation limit reached (max ${limits.max_offstage_per_school}).`;
    }
    if (['group', 'team'].includes(item.participant_type) && limits.max_group_per_school != null
        && quotas.used.group >= limits.max_group_per_school) {
        return `School group/team participation limit reached (max ${limits.max_group_per_school}).`;
    }

    return '';
}

function isItemBlocked(event, item) {
    return Boolean(itemBlockReason(event, item));
}

function itemEligibleParticipantCount(eventId, item) {
    return eligibleStudentsForItem(eventId, item).length;
}

/** @deprecated alias */
function itemEligibleAthleteCount(eventId, item) {
    return itemEligibleParticipantCount(eventId, item);
}

function itemNoEligibleHint(event, item) {
    const pool = studentsForEvent(event.id);
    if (!pool.length) {
        return 'No students on record — add students first.';
    }

    const pendingVerify = requireVerifiedForEvent(event)
        ? pool.filter((s) => s.is_verified === false)
        : [];
    const verifyBlockedMatches = pendingVerify.filter(
        (s) => studentMatchesItem(s, event, item, { skipVerification: true }),
    );

    if (verifyBlockedMatches.length > 0) {
        const noun = isSports.value ? 'participant' : 'student';
        if (pendingVerify.length === pool.length) {
            return `All ${noun}s are awaiting Sahodaya verification. Sahodaya admin: Membership → Student verification.`;
        }

        return `${verifyBlockedMatches.length} ${noun}${verifyBlockedMatches.length === 1 ? '' : 's'} match this item but need Sahodaya verification first.`;
    }

    const teamMin = Number(item.min_group_size ?? item.criteria_json?.min_playing ?? 0);
    if (teamMin > 1 && ['group', 'team'].includes(item.participant_type)) {
        const matching = pool.filter((s) => studentMatchesItem(s, event, item, { skipVerification: true })).length;
        if (matching > 0 && matching < teamMin) {
            return `This team item needs ${teamMin} students — only ${matching} eligible on record.`;
        }
    }

    if (isSports.value) {
        return 'No participants meet age/gender for this item.';
    }

    return 'No students match this item (class category, gender, or academic year).';
}

function itemRegistrationStatus(event, item) {
    if (item.registration_open === false) {
        return 'closed';
    }

    const regs = itemRegistrationCount(event.id, item.id);
    const max = itemMaxPerSchool(item);

    if (isItemFull(event, item)) {
        return regs > 0 ? 'registered' : 'full';
    }

    if (itemEligibleParticipantCount(event.id, item) === 0) {
        return 'no_eligible';
    }

    if (regs > 0 && max > 1) {
        return 'partial';
    }

    return 'open';
}

function itemStatusMeta(event, item) {
    const status = itemRegistrationStatus(event, item);
    const eligible = itemEligibleParticipantCount(event.id, item);
    const regs = itemRegistrationCount(event.id, item.id);
    const max = itemMaxPerSchool(item);

    if (status === 'registered') {
        return {
            label: 'Registered',
            badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-100',
            hint: max === 1 ? 'Entry submitted for this event' : `${regs} of ${max} school entries used`,
        };
    }

    if (status === 'full') {
        return {
            label: 'Full',
            badgeClass: 'bg-amber-50 text-amber-800 border-amber-100',
            hint: '',
        };
    }

    if (status === 'no_eligible') {
        return {
            label: 'No match',
            badgeClass: 'bg-slate-100 text-slate-600 border-slate-200',
            hint: itemNoEligibleHint(event, item),
        };
    }

    if (status === 'closed') {
        return {
            label: 'Closed',
            badgeClass: 'bg-amber-50 text-amber-800 border-amber-100',
            hint: itemRegWindowMessage(item),
        };
    }

    const participantNoun = isSports.value ? 'participant' : 'student';

    if (status === 'partial') {
        return {
            label: `Registered (${regs}/${max})`,
            badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            hint: `${regs} of ${max} entries registered · ${eligible} ${participantNoun}${eligible === 1 ? '' : 's'} eligible for more entries`,
        };
    }

    return {
        label: 'Open',
        badgeClass: 'bg-indigo-50 text-indigo-700 border-indigo-100',
        hint: `${eligible} ${participantNoun}${eligible === 1 ? '' : 's'} can register`,
    };
}

function itemRowId(eventId, itemId) {
    return `reg-item-${itemFormKey(eventId, itemId)}`;
}

function extractItemErrors(errors, itemId) {
    const key = `items.${itemId}`;
    const messages = errors?.[key];
    if (Array.isArray(messages)) return messages.join(' ');
    if (typeof messages === 'string') return messages;
    return '';
}

function scrollToItemRow(eventId, itemId) {
    const el = document.getElementById(itemRowId(eventId, itemId));
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function submitItem(event, item) {
    const key = itemFormKey(event.id, item.id);
    const form = itemForms[key];
    const standby = (form.standby_ids ?? []).slice(0, 2);

    delete itemErrors[key];

    if (isItemBlocked(event, item)) {
        itemErrors[key] = itemBlockReason(event, item);
        scrollToItemRow(event.id, item.id);
        return;
    }

    if (!['group', 'team'].includes(item.participant_type) && (form.student_ids?.length ?? 0) > 1) {
        itemErrors[key] = 'This item allows only one participant.';
        scrollToItemRow(event.id, item.id);
        return;
    }

    router.post(`${programBase.value}/register`, {
        event_id: event.id,
        item_id: item.id,
        team_name: form.team_name,
        coach_name: form.coach_name || null,
        coach_phone: form.coach_phone || null,
        manager_name: form.manager_name || null,
        manager_phone: form.manager_phone || null,
        student_ids: form.student_ids,
        teacher_ids: form.teacher_ids,
        standby_ids: standby,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            delete itemErrors[key];
            form.student_ids = [];
            form.teacher_ids = [];
            form.standby_ids = [];
            form.team_name = '';
            form.coach_name = '';
            form.coach_phone = '';
            form.manager_name = '';
            form.manager_phone = '';
        },
        onError: (errors) => {
            itemErrors[key] = extractItemErrors(errors, item.id)
                || errors.registration
                || page.props.flash?.error
                || 'Could not register for this item.';
            scrollToItemRow(event.id, item.id);
        },
    });
}

function submitBulkAssign() {
    const event = props.events.find((ev) => ev.id === Number(bulkAssignEventId.value));
    if (!event) return;

    const studentIds = Object.values(itemForms)
        .flatMap((form) => form.student_ids ?? [])
        .filter(Boolean);
    const itemIds = (event.items ?? []).filter((item) => bulkAssignItemIds.value.includes(item.id)).map((i) => i.id);

    if (!studentIds.length || !itemIds.length) {
        alert('Pick athletes in item rows and select items for bulk assign.');
        return;
    }

    bulkAssignForm.student_ids = [...new Set(studentIds)];
    bulkAssignForm.item_ids = itemIds;
    bulkAssignForm.post(`${programBase.value}/events/${event.id}/bulk-assign`, {
        preserveScroll: true,
        onSuccess: () => {
            bulkAssignForm.reset();
            showBulkAssign.value = false;
        },
    });
}

function uploadEventPayment(event) {
    const file = eventPaymentFiles[event.id];
    if (!file) {
        alert('Choose a payment proof file first, or skip — registration does not require it.');
        return;
    }
    router.post(`${programBase.value}/events/${event.id}/payment`, {
        payment_proof: file,
        transaction_ref: eventPaymentRefs[event.id] || null,
    }, { forceFormData: true, preserveScroll: true });
}

function setHeadPaymentFile(eventId, headId, file) {
    headPaymentFiles[headPaymentKey(eventId, headId)] = file;
}

function canUploadHeadFee(headFee) {
    if (Number(headFee.outstanding) <= 0) return false;
    if (['approved', 'proof_uploaded'].includes(headFee.status)) return false;

    return ['pending', 'rejected', 'partial'].includes(headFee.status);
}

function headFeeStatusLabel(status) {
    return ({
        approved: 'Paid / approved',
        proof_uploaded: 'Proof pending approval',
        rejected: 'Rejected — re-upload',
        partial: 'Partially paid',
        pending: 'Payment due',
    })[status] ?? status;
}

function headFeeStatusClass(status) {
    return ({
        approved: 'bg-green-50 text-green-700 border-green-200',
        proof_uploaded: 'bg-amber-50 text-amber-800 border-amber-200',
        rejected: 'bg-red-50 text-red-700 border-red-200',
        partial: 'bg-sky-50 text-sky-800 border-sky-200',
        pending: 'bg-white text-indigo-800 border-indigo-200',
    })[status] ?? 'bg-white text-slate-600 border-slate-200';
}

function uploadHeadPayment(event, headFee) {
    const key = headPaymentKey(event.id, headFee.head_id);
    const file = headPaymentFiles[key];
    if (!file) {
        alert('Choose a payment proof file for this Sport Event first.');
        return;
    }
    router.post(`${programBase.value}/events/${event.id}/payment`, {
        payment_proof: file,
        transaction_ref: headPaymentRefs[key] || null,
        head_id: headFee.head_id,
    }, { forceFormData: true, preserveScroll: true });
}

const incompleteSquads = computed(() => {
    if (!props.registrations || !props.events) return [];
    const list = [];
    for (const reg of props.registrations) {
        const item = props.events.flatMap(ev => ev.items ?? []).find(it => Number(it.id) === Number(reg.item_id));
        if (!item) continue;
        const isGroup = ['group', 'team'].includes(item.participant_type);
        if (!isGroup) continue;
        const count = (reg.participants ?? []).filter(p => p.participant_role !== 'standby' && p.student_id).length;
        const min = item.min_group_size || 1;
        if (count < min) {
            list.push({
                item_title: item.title,
                count,
                min,
            });
        }
    }
    return list;
});

// Event dates arrive as full ISO timestamps (e.g. Eloquent `date` casts
// serialize as UTC midnight of the next IST day). Appending "T12:00:00" to a
// value that already has a time component produces an invalid Date — this
// helper handles both a bare "YYYY-MM-DD" string and a full ISO timestamp.
function toEventDate(value) {
    if (!value) return null;
    const str = String(value);
    const iso = /^\d{4}-\d{2}-\d{2}$/.test(str) ? `${str}T12:00:00` : str;
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? null : d;
}

function formatDate(iso) {
    const d = toEventDate(iso);
    if (!d) return '—';
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Asia/Kolkata' });
}

function formatDateRange(start, end) {
    const startD = toEventDate(start);
    const endD = toEventDate(end);
    if (!startD && !endD) return 'Not scheduled';
    if (startD && endD) {
        if (start === end) return formatDate(start);
        return `${formatDate(start)} – ${formatDate(end)}`;
    }
    return startD ? `From ${formatDate(start)}` : `Until ${formatDate(end)}`;
}
</script>
