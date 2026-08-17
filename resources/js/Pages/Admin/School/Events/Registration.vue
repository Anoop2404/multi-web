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

        <InlineAlert :message="alertMessage" type="error" @dismiss="alertMessage = ''" />

        <div v-if="schoolRegion?.applies && !event?.uses_registration_batch_billing" class="mb-5 max-w-2xl">
            <div v-if="schoolRegion.region && !showChangeRegion" class="notice-banner notice-banner--info text-sm flex flex-wrap items-center gap-3 justify-between">
                <p>Your {{ programLabel }} region: <strong>{{ schoolRegion.region }}</strong>.</p>
                <button type="button" class="link-brand font-semibold text-xs shrink-0" @click="showChangeRegion = true">Change region →</button>
            </div>
            <div v-else class="notice-banner notice-banner--warning text-sm">
                <p class="font-semibold mb-2">{{ schoolRegion.region ? 'Change your' : 'Select your' }} {{ programLabel }} region</p>
                <form @submit.prevent="submitRegion" class="flex flex-wrap items-center gap-3">
                    <select v-model="regionForm.region_id" class="field !py-1.5 min-w-[15rem]" required>
                        <option value="" disabled>Choose your region...</option>
                        <option v-for="region in schoolRegion.regions" :key="region.id" :value="region.id">
                            {{ region.name }}
                        </option>
                    </select>
                    <button type="submit" class="btn-primary text-xs !py-1.5" :disabled="regionForm.processing">
                        {{ regionForm.processing ? 'Saving…' : 'Save Region' }}
                    </button>
                    <button v-if="schoolRegion.region" type="button" class="btn-ghost text-xs !py-1.5" @click="showChangeRegion = false">Cancel</button>
                </form>
                <p v-if="!schoolRegion.region" class="text-xs text-amber-800 mt-2">
                    Your Sahodaya runs {{ programLabel }} by region. You must select your region before you can register for events.
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

        <PhasedRegionBillingPanel v-if="event?.uses_registration_batch_billing"
                                  :event="event"
                                  :school-id="school.id"
                                  :program-prefix="programPrefix" />

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
                    <button type="button" @click="setTab(event.id, 'athletes')"
                            class="px-3.5 py-1.5 rounded-lg transition"
                            :class="getTab(event.id) === 'athletes' ? 'bg-[#0f3d7a] text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'">
                        {{ isSports ? 'Step 1: Event Athletes' : 'Step 1: Event Registration' }} ({{ (event.event_registrations || []).length }})
                    </button>
                    <button type="button" @click="setTab(event.id, 'items')"
                            class="px-3.5 py-1.5 rounded-lg transition"
                            :class="getTab(event.id) === 'items' ? 'bg-[#0f3d7a] text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'">
                        Step 2: Item Registration
                    </button>
                    <button v-if="event.fee_required" type="button" @click="setTab(event.id, 'payment')"
                            class="px-3.5 py-1.5 rounded-lg transition flex items-center gap-1.5"
                            :class="getTab(event.id) === 'payment' ? 'bg-[#0f3d7a] text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'">
                        <span>Step 3: Billing & Payment</span>
                        <span v-if="event.school_fee?.status" class="text-[10px] px-1.5 py-0.5 rounded-full uppercase font-mono"
                              :class="event.school_fee.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900'">
                            {{ event.school_fee.status }}
                        </span>
                    </button>
                </div>

                <div class="p-5">
                <!-- Kalotsav-style participation quotas -->
                <div v-if="event.quotas && eventType === 'kalolsavam'" class="grid sm:grid-cols-3 gap-3 mb-4">
                    <div class="bg-indigo-50/60 border border-indigo-100 rounded-xl p-3 text-xs flex items-center justify-between">
                        <div>
                            <span class="font-semibold text-indigo-900 block">🎭 On-stage Individual Quota</span>
                            <span class="text-[11px] text-indigo-700/80">Max items allowed per student</span>
                        </div>
                        <span class="font-bold text-xs px-2.5 py-1 rounded-lg bg-indigo-100 text-indigo-900 border border-indigo-200 shrink-0">
                            {{ limitLabel(event.quotas.limits?.max_onstage_per_student) }} max / student
                        </span>
                    </div>
                    <div class="bg-amber-50/60 border border-amber-100 rounded-xl p-3 text-xs flex items-center justify-between">
                        <div>
                            <span class="font-semibold text-amber-900 block">🎨 Off-stage Quota</span>
                            <span class="text-[11px] text-amber-700/80">Max items allowed per student</span>
                        </div>
                        <span class="font-bold text-xs px-2.5 py-1 rounded-lg bg-amber-100 text-amber-900 border border-amber-200 shrink-0">
                            {{ limitLabel(event.quotas.limits?.max_offstage_per_student) }} max / student
                        </span>
                    </div>
                    <div class="bg-emerald-50/60 border border-emerald-100 rounded-xl p-3 text-xs flex items-center justify-between">
                        <div>
                            <span class="font-semibold text-emerald-900 block">👥 Group Items Quota</span>
                            <span class="text-[11px] text-emerald-700/80">Max group items allowed per student</span>
                        </div>
                        <span class="font-bold text-xs px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-900 border border-emerald-200 shrink-0">
                            {{ limitLabel(event.quotas.limits?.max_group_per_student) }} max / student
                        </span>
                    </div>
                </div>

                <!-- ── Non-sports fests: Step 1 · Event Registration (deliberately NOT part
                     of the v-if/v-else-if/v-else chain below — an element with its own
                     v-if placed between that chain's branches would hijack it: Vue
                     attaches a v-else-if/v-else to whatever sibling immediately precedes
                     it in the template, not to the "logically intended" branch. Having
                     this here previously swallowed the generic items form and the sports
                     block entirely for every non-sports event. Sports already renders the
                     equivalent panel inside its own branch further down. -->
                <SportsEventAthletesPanel
                    v-if="canRegister(event) && !isSports"
                    v-show="getTab(event.id) === 'athletes'"
                    :event="event"
                    :students="studentsForEvent(event.id)"
                    :event-registrations="event.event_registrations ?? []"
                    :register-url="`${programBase}/events/${event.id}/register-students`"
                    :items-url="`#item-registration-${event.id}`"
                    :reports-href="`${programBase}/reports/${event.id}`"
                    :student-event-reg-fee="Number(event.student_event_reg_fee ?? 0)"
                    :school-classes="schoolClasses"
                    class="mb-4"
                />

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
                                        :class-group-labels="event.class_group_labels ?? {}"
                                        @register="submitItem(event, item)"
                                        @update="updateItem(event, item)"
                                        @withdraw="withdraw"
                                        @edit="startEdit($event, event, item)"
                                        @cancel-edit="cancelEdit(event, item)"
                                        @add-student="showAddStudent = true"
                                        @search-students="query => searchStudentsForEvent(event.id, query)"
                                    />
                                </tbody>
                            </table>
                            <p v-if="!sportsFlatItems(event).length" class="px-4 py-6 text-sm text-slate-500 text-center">
                                No items match the current filters.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ── KALOTSAV / KIDS FEST / TEACHER FEST / ENGLISH FEST / SCIENCE FEST: generic flat table ── -->
                <form v-else v-show="getTab(event.id) === 'items'" :id="`item-registration-${event.id}`" class="mt-4 space-y-4" @submit.prevent>
                    <div class="rounded-xl border border-gray-100 overflow-hidden bg-white shadow-sm">
                        <!-- 3-Type Quick Tabs (On-stage Individual, On-stage Group, Off-stage) -->
                        <div class="px-4 py-2.5 bg-slate-100/90 border-b border-slate-200 flex flex-wrap gap-2 text-xs font-semibold">
                            <button type="button" @click="itemTypeTab[event.id] = 'all'"
                                    class="px-3 py-1.5 rounded-lg transition flex items-center gap-1.5"
                                    :class="itemTypeTab[event.id] === 'all' ? 'bg-[#0f3d7a] text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-200/70 border border-slate-200'">
                                <span>All Items</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="itemTypeTab[event.id] === 'all' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'">
                                    {{ itemCountsByCompetitionType(event).all }}
                                </span>
                            </button>
                            <button type="button" @click="itemTypeTab[event.id] = 'on_stage_single'"
                                    class="px-3 py-1.5 rounded-lg transition flex items-center gap-1.5"
                                    :class="itemTypeTab[event.id] === 'on_stage_single' ? 'bg-indigo-700 text-white shadow-sm' : 'bg-white text-indigo-900 hover:bg-indigo-50 border border-indigo-200'">
                                <span>🎭 On-stage Individual</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="itemTypeTab[event.id] === 'on_stage_single' ? 'bg-white/20 text-white' : 'bg-indigo-100 text-indigo-800'">
                                    {{ itemCountsByCompetitionType(event).on_stage_single }}
                                </span>
                            </button>
                            <button type="button" @click="itemTypeTab[event.id] = 'on_stage_group'"
                                    class="px-3 py-1.5 rounded-lg transition flex items-center gap-1.5"
                                    :class="itemTypeTab[event.id] === 'on_stage_group' ? 'bg-emerald-700 text-white shadow-sm' : 'bg-white text-emerald-900 hover:bg-emerald-50 border border-emerald-200'">
                                <span>👥 On-stage Group</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="itemTypeTab[event.id] === 'on_stage_group' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800'">
                                    {{ itemCountsByCompetitionType(event).on_stage_group }}
                                </span>
                            </button>
                            <button type="button" @click="itemTypeTab[event.id] = 'off_stage'"
                                    class="px-3 py-1.5 rounded-lg transition flex items-center gap-1.5"
                                    :class="itemTypeTab[event.id] === 'off_stage' ? 'bg-amber-700 text-white shadow-sm' : 'bg-white text-amber-900 hover:bg-amber-50 border border-amber-200'">
                                <span>🎨 Off-stage</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="itemTypeTab[event.id] === 'off_stage' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-900'">
                                    {{ itemCountsByCompetitionType(event).off_stage }}
                                </span>
                            </button>
                        </div>

                        <!-- Search & Filter Controls Bar -->
                        <div class="px-4 py-3 bg-slate-50/80 border-b border-slate-200 space-y-2">
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-2 items-center">
                                <div class="md:col-span-2">
                                    <input v-model="itemSearch[event.id]" type="search"
                                           class="field w-full !py-1.5 text-xs rounded-lg border-slate-300"
                                           placeholder="Search items by name or code..." autocomplete="off">
                                </div>
                                <div>
                                    <select v-model="itemCategoryFilter[event.id]" class="field w-full text-xs !py-1.5 rounded-lg border-slate-300">
                                        <option value="">All Categories</option>
                                        <option value="category1">Category 1 (Classes 3 & 4)</option>
                                        <option value="category2">Category 2 (Classes 5, 6 & 7)</option>
                                        <option value="category3">Category 3 (Classes 8–10)</option>
                                        <option value="category4">Category 4 (Classes 11 & 12)</option>
                                        <option value="open">Category 5 / Open</option>
                                    </select>
                                </div>
                                <div>
                                    <select v-model="itemStageFilter[event.id]" class="field w-full text-xs !py-1.5 rounded-lg border-slate-300">
                                        <option value="">All Stage Modes</option>
                                        <option value="on_stage">🎭 On-stage</option>
                                        <option value="off_stage">🎨 Off-stage</option>
                                    </select>
                                </div>
                                <div>
                                    <select v-model="itemSort[event.id]" class="field w-full text-xs !py-1.5 rounded-lg border-indigo-200 bg-indigo-50/50 font-medium text-indigo-900">
                                        <option value="">Sort: Default</option>
                                        <option value="category">Sort by Category (Cat 1 → 5)</option>
                                        <option value="stage">Sort by Stage (On-stage first)</option>
                                        <option value="name">Sort by Item Name (A → Z)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-xs text-slate-500 font-medium pt-1">
                                <span>
                                    Showing <strong class="text-slate-800">{{ filteredAllItems(event).length }}</strong> of {{ (event.items || []).length }} items
                                </span>
                                <button v-if="itemSearch[event.id] || itemCategoryFilter[event.id] || itemStageFilter[event.id] || itemGroupFilter[event.id] || itemSort[event.id]"
                                        type="button" class="btn-ghost text-xs !py-0.5 text-indigo-700 font-semibold hover:underline"
                                        @click="clearItemFilters(event.id)">
                                    Clear all filters
                                </button>
                            </div>
                        </div>

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
                                        v-for="item in filteredAllItems(event)"
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
                                        :class-group-labels="event.class_group_labels ?? {}"
                                        @register="submitItem(event, item)"
                                        @update="updateItem(event, item)"
                                        @withdraw="withdraw"
                                        @edit="startEdit($event, event, item)"
                                        @cancel-edit="cancelEdit(event, item)"
                                        @add-student="showAddStudent = true"
                                        @search-students="query => searchStudentsForEvent(event.id, query)"
                                    />
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>

                <EventBillingPanel
                    v-if="!event.uses_registration_batch_billing && event.fee_required && (event.uses_per_head_billing ? event.school_head_fees?.length : (event.uses_per_phase_billing ? event.school_phase_fees?.length : event.school_fee))"
                    v-show="getTab(event.id) === 'payment'"
                    :event="event"
                    :school-id="school.id"
                    :program-base="programBase"
                    :payment-details="paymentDetails"
                    :item-fee-lines="itemFeeLines(event)"
                    :item-fees-due="itemFeesDue(event)"
                    :item-unit-count="itemUnitCount(event)"
                    :student-reg-line="studentRegLine(event)"
                    :is-min-fee-applied="isMinFeeApplied(event)"
                    :event-payment-ref="eventPaymentRefs[event.id] ?? ''"
                    :event-payment-bank="eventPaymentBanks[event.id] ?? ''"
                    :event-payment-amount="eventPaymentAmounts[event.id] ?? ''"
                    :head-payment-ref-map="headPaymentRefs"
                    :head-payment-bank-map="headPaymentBanks"
                    :head-payment-amount-map="headPaymentAmounts"
                    :phase-payment-ref-map="phasePaymentRefs"
                    :phase-payment-bank-map="phasePaymentBanks"
                    :phase-payment-amount-map="phasePaymentAmounts"
                    @upload-event-payment="uploadEventPayment(event)"
                    @set-event-file="file => eventPaymentFiles[event.id] = file"
                    @update-event-ref="refVal => eventPaymentRefs[event.id] = refVal"
                    @update-event-bank="bankVal => eventPaymentBanks[event.id] = bankVal"
                    @update-event-amount="amountVal => eventPaymentAmounts[event.id] = amountVal"
                    @upload-head-payment="headFee => uploadHeadPayment(event, headFee)"
                    @set-head-file="(headId, file) => setHeadPaymentFile(event.id, headId, file)"
                    @update-head-ref="(headId, refVal) => headPaymentRefs[headPaymentKey(event.id, headId)] = refVal"
                    @update-head-bank="(headId, bankVal) => headPaymentBanks[headPaymentKey(event.id, headId)] = bankVal"
                    @update-head-amount="(headId, amountVal) => headPaymentAmounts[headPaymentKey(event.id, headId)] = amountVal"
                    @upload-phase-payment="phaseFee => uploadPhasePayment(event, phaseFee)"
                    @set-phase-file="(phaseId, file) => setPhasePaymentFile(event.id, phaseId, file)"
                    @update-phase-ref="(phaseId, refVal) => phasePaymentRefs[phasePaymentKey(event.id, phaseId)] = refVal"
                    @update-phase-bank="(phaseId, bankVal) => phasePaymentBanks[phasePaymentKey(event.id, phaseId)] = bankVal"
                    @update-phase-amount="(phaseId, amountVal) => phasePaymentAmounts[phasePaymentKey(event.id, phaseId)] = amountVal"
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
import InlineAlert from '@/Components/ui/InlineAlert.vue';
import SchoolEventWorkflowStepper from '@/Components/school/SchoolEventWorkflowStepper.vue';
import EventBillingPanel from '@/Components/school/EventBillingPanel.vue';
import PhasedRegionBillingPanel from '@/Components/school/PhasedRegionBillingPanel.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { genderLabel } from '@/support/festItemEligibility.js';
import { studentDisplayName } from '@/support/studentDisplay.js';
import { useConfirm } from '@/composables/useConfirm';
import { useSweetAlert } from '@/composables/useSweetAlert.js';
const { confirm, prompt } = useConfirm();
const { showError, showWarning, showSuccess } = useSweetAlert();

const alertMessage = ref('');

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

const regionForm = useForm({
    region_id: '',
});

const showChangeRegion = ref(false);

function submitRegion() {
    regionForm.post(props.schoolRegion.set_url, {
        preserveScroll: true,
        onSuccess: () => {
            showChangeRegion.value = false;
            router.reload();
        }
    });
}

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
        } else {
            // Every fest type now has an Event Registration step (see
            // SportsEventAthletesPanel usage above) — default to it first, matching the
            // intended event-reg → item-reg → payment flow.
            activeTabMap[eventId] = 'athletes';
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

// A large school's initial pool (loadStudentsForEvent above) is capped at the first ~150
// students by name (see FestRegistrationController::eligibleStudents()) — this is what lets
// the picker modal actually find someone outside that window, by asking the server instead
// of only filtering what's already downloaded. Merges by ID rather than replacing, so
// students shown/selected from the initial batch don't disappear when a search narrows the
// result. No-op for small (non-lazy) schools — their full roster is already loaded, and the
// modal's existing client-side filter already covers them.
async function searchStudentsForEvent(eventId, query) {
    if (!props.lazyLoadStudents) return;
    const term = String(query ?? '').trim();
    if (!term) return;

    try {
        const res = await fetch(
            `${programBase.value}/events/${eventId}/eligible-students?json=1&search=${encodeURIComponent(term)}`,
            {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            },
        );
        if (!res.ok) return;
        const data = await res.json();
        const existing = fetchedStudentsByEvent[eventId] ?? [];
        const byId = new Map(existing.map(s => [s.id, s]));
        for (const s of (data.students ?? [])) {
            byId.set(s.id, s);
        }
        fetchedStudentsByEvent[eventId] = Array.from(byId.values());
    } catch {
        // keep whatever's already loaded — user can retry the search
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
const eventPaymentBanks = reactive({});
const eventPaymentAmounts = reactive({});
const headPaymentFiles = reactive({});
const headPaymentRefs = reactive({});
const headPaymentBanks = reactive({});
const headPaymentAmounts = reactive({});
const phasePaymentFiles = reactive({});
const phasePaymentRefs = reactive({});
const phasePaymentBanks = reactive({});
const phasePaymentAmounts = reactive({});
const editingRegistrationId = reactive({});
const itemSearch = reactive({});
const itemCategoryFilter = reactive({});
const itemStageFilter = reactive({});
const itemGroupFilter = reactive({});
const itemSort = reactive({});
const itemTypeTab = reactive({});

function allItemsStatic(event) {
    return event?.items ?? [];
}

function itemFormKey(eventId, itemId) {
    return `${eventId}-${itemId}`;
}

function headPaymentKey(eventId, headId) {
    return `${eventId}:${headId}`;
}

function phasePaymentKey(eventId, phaseId) {
    return `${eventId}:${phaseId}`;
}

for (const e of props.events) {
    eventPaymentRefs[e.id] = '';
    eventPaymentBanks[e.id] = '';
    eventPaymentAmounts[e.id] = '';
    sportsSearch[e.id] = '';
    sportsAgeFilter[e.id] = '';
    sportsItemFilter[e.id] = '';
    itemSearch[e.id] = '';
    itemCategoryFilter[e.id] = '';
    itemStageFilter[e.id] = '';
    itemGroupFilter[e.id] = '';
    itemSort[e.id] = '';
    itemTypeTab[e.id] = 'all';
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

function clearItemFilters(eventId) {
    if (!eventId) return;
    itemSearch[eventId] = '';
    itemCategoryFilter[eventId] = '';
    itemStageFilter[eventId] = '';
    itemGroupFilter[eventId] = '';
    itemSort[eventId] = '';
    itemTypeTab[eventId] = 'all';
}

function itemCompetitionType(item) {
    const sm = String(item.stage_mode || item.stage_type || '').toLowerCase();
    const title = String(item.title || item.name || '').toLowerCase();
    const isGrp = ['group', 'team'].includes(item.participant_type);
    const isOff = sm.includes('off') || item.is_onstage === false || title.includes('offstage') || title.includes('off stage') || title.includes('painting') || title.includes('drawing') || title.includes('essay') || title.includes('story') || title.includes('versification') || title.includes('quiz') || title.includes('carrom') || title.includes('chess');

    if (isOff) return 'off_stage';
    if (isGrp) return 'on_stage_group';
    return 'on_stage_single';
}

function itemCountsByCompetitionType(event) {
    const items = event?.items ?? [];
    const counts = { all: items.length, on_stage_single: 0, on_stage_group: 0, off_stage: 0 };
    for (const item of items) {
        const type = itemCompetitionType(item);
        if (counts[type] !== undefined) {
            counts[type]++;
        }
    }
    return counts;
}

function allItems(event) {
    return event?.items ?? [];
}

function filteredAllItems(event) {
    const rawItems = event?.items ?? [];
    const eventId = event?.id;
    if (!eventId) return rawItems;

    const query = String(itemSearch[eventId] ?? '').trim().toLowerCase();
    const catFilter = itemCategoryFilter[eventId] ?? '';
    const stageFilter = itemStageFilter[eventId] ?? '';
    const groupFilter = itemGroupFilter[eventId] ?? '';
    const sortMode = itemSort[eventId] ?? '';
    const activeTab = itemTypeTab[eventId] ?? 'all';

    let items = rawItems;

    if (activeTab !== 'all') {
        items = items.filter(item => itemCompetitionType(item) === activeTab);
    }

    if (query || catFilter || stageFilter || groupFilter) {
        items = items.filter((item) => {
            if (query) {
                const title = String(item.clean_title || item.title || item.name || '').toLowerCase();
                const code = String(item.item_code || '').toLowerCase();
                if (!title.includes(query) && !code.includes(query)) {
                    return false;
                }
            }

            if (catFilter) {
                const grp = normalizedClassGroup(item.class_group);
                if (catFilter === 'category1' && !['lp', 'category1', 'cat1', 'cc1'].includes(grp)) return false;
                if (catFilter === 'category2' && !['up', 'category2', 'cat2', 'cc2'].includes(grp)) return false;
                if (catFilter === 'category3' && !['hs', 'category3', 'cat3', 'cc3'].includes(grp)) return false;
                if (catFilter === 'category4' && !['hss', 'category4', 'cat4', 'cc4'].includes(grp)) return false;
                if (catFilter === 'open' && grp !== 'open' && !['category5', 'cat5', 'cc5'].includes(grp)) return false;
            }

            if (stageFilter) {
                const sm = String(item.stage_mode || item.stage_type || '').toLowerCase();
                const title = String(item.title || item.name || '').toLowerCase();
                const isOn = sm.includes('on') || item.is_onstage === true;
                const isOff = sm.includes('off') || item.is_onstage === false || title.includes('offstage') || title.includes('off stage') || title.includes('painting') || title.includes('drawing') || title.includes('essay');
                if (stageFilter === 'on_stage' && isOff && !isOn) return false;
                if (stageFilter === 'off_stage' && isOn && !isOff) return false;
            }

            if (groupFilter) {
                const isGrp = ['group', 'team'].includes(item.participant_type);
                if (groupFilter === 'group' && !isGrp) return false;
                if (groupFilter === 'single' && isGrp) return false;
            }

            return true;
        });
    }

    if (sortMode) {
        items = [...items];
        if (sortMode === 'category') {
            const catOrder = { lp: 1, category1: 1, cat1: 1, cc1: 1, up: 2, category2: 2, cat2: 2, cc2: 2, hs: 3, category3: 3, cat3: 3, cc3: 3, hss: 4, category4: 4, cat4: 4, cc4: 4, open: 5, category5: 5, cat5: 5, cc5: 5 };
            items.sort((a, b) => {
                const orderA = catOrder[normalizedClassGroup(a.class_group)] ?? 99;
                const orderB = catOrder[normalizedClassGroup(b.class_group)] ?? 99;
                return orderA - orderB;
            });
        } else if (sortMode === 'stage') {
            items.sort((a, b) => {
                const smA = String(a.stage_mode || a.stage_type || '').toLowerCase().includes('on') || a.is_onstage === true ? 1 : 2;
                const smB = String(b.stage_mode || b.stage_type || '').toLowerCase().includes('on') || b.is_onstage === true ? 1 : 2;
                return smA - smB;
            });
        } else if (sortMode === 'name') {
            items.sort((a, b) => {
                const titleA = String(a.clean_title || a.title || a.name || '').toLowerCase();
                const titleB = String(b.clean_title || b.title || b.name || '').toLowerCase();
                return titleA.localeCompare(titleB);
            });
        }
    }

    return items;
}

function formatMoney(value) {
    const n = Number(value);
    if (Number.isNaN(n)) return '0.00';
    return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function registrationsForItem(eventId, itemId) {
    const numEventId = Number(eventId);
    const numItemId = Number(itemId);

    let targetItem = null;
    for (const ev of (props.events ?? [])) {
        const found = (ev.items ?? []).find(i => Number(i.id) === numItemId);
        if (found) { targetItem = found; break; }
    }
    const targetInheritedId = targetItem?.inherited_from_item_id ? Number(targetItem.inherited_from_item_id) : null;
    const targetItemCode = targetItem?.item_code ? String(targetItem.item_code).trim().toLowerCase() : null;

    return (props.registrations ?? []).filter((reg) => {
        if (['withdrawn', 'rejected'].includes(reg.status)) {
            return false;
        }

        const regItemId = Number(reg.item_id);
        const regInheritedId = reg.item?.inherited_from_item_id ? Number(reg.item.inherited_from_item_id) : null;
        const regItemCode = reg.item?.item_code ? String(reg.item.item_code).trim().toLowerCase() : null;

        const itemMatch = regItemId === numItemId
            || (targetInheritedId !== null && regItemId === targetInheritedId)
            || (regInheritedId !== null && regInheritedId === numItemId)
            || (targetInheritedId !== null && regInheritedId !== null && regInheritedId === targetInheritedId)
            || (targetItemCode !== null && regItemCode !== null && targetItemCode === regItemCode);

        if (!itemMatch) {
            return false;
        }

        const regEventId = Number(reg.event_id);
        const regParentEventId = reg.event?.parent_event_id ? Number(reg.event.parent_event_id) : null;
        const regRootEventId = reg.event?.root_event_id ? Number(reg.event.root_event_id) : null;

        const targetEvent = (props.events ?? []).find(e => Number(e.id) === numEventId) || props.event;
        const targetParentId = targetEvent?.parent_event_id ? Number(targetEvent.parent_event_id) : null;
        const targetRootId = targetEvent?.root_event_id ? Number(targetEvent.root_event_id) : null;

        const eventMatch = regEventId === numEventId
            || (targetParentId !== null && regEventId === targetParentId)
            || (targetRootId !== null && regEventId === targetRootId)
            || (regParentEventId !== null && regParentEventId === numEventId)
            || (regRootEventId !== null && regRootEventId === numEventId)
            || (targetParentId !== null && regParentEventId !== null && targetParentId === regParentEventId)
            || (targetRootId !== null && regRootEventId !== null && targetRootId === regRootEventId);

        return eventMatch;
    });
}

function registeredNames(reg) {
    const labels = (reg.participants ?? [])
        .filter(p => p.participant_role !== 'standby')
        .map((p) => {
            const name = p.student ? studentDisplayName(p.student) : (p.teacher?.name ?? null);
            const regNo = p.student?.admission_number ?? p.teacher?.reg_no;
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
    return event?.require_verified_students === true;
}

const GROUP_ALIASES = {
    lp: ['lp', 'category1', 'categoryi', 'cat1', 'cati', 'category_1', 'cc1'],
    up: ['up', 'category2', 'categoryii', 'cat2', 'catii', 'category_2', 'cc2'],
    hs: ['hs', 'category3', 'categoryiii', 'cat3', 'catiii', 'category_3', 'cc3'],
    hss: ['hss', 'category4', 'categoryiv', 'cat4', 'cativ', 'category_4', 'cc4'],
    open: ['open', 'category5', 'categoryv', 'cat5', 'catv', 'category_5', 'cc5'],
};

function normalizedClassGroup(value) {
    return String(value ?? '').toLowerCase().replace(/[^a-z0-9]+/g, '');
}

function matchesClassGroup(studentGrpRaw, itemGrpRaw) {
    if (!itemGrpRaw || itemGrpRaw === 'open') return true;
    const studentGrp = normalizedClassGroup(studentGrpRaw);
    const itemGrp = normalizedClassGroup(itemGrpRaw);
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
    if (event?.academic_year_id && student.academic_year_id && Number(event.academic_year_id) !== Number(student.academic_year_id)) {
        return false;
    }
    if (['kalolsavam', 'kalotsav', 'custom', 'english_fest', 'science_fest'].includes(props.eventType) || String(props.eventType ?? '').includes('kalotsav')) {
        if (['kalolsavam', 'kalotsav'].includes(props.eventType) && student.eligible_kalolsav === false) return false;
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
        if (item.class_group && item.class_group !== 'open' && !matchesClassGroup(student.kalolsav_class_group, item.class_group)) {
            return classGroupMismatchReason(student, item, event);
        }
    }

    if (['custom', 'english_fest', 'science_fest'].includes(props.eventType)) {
        if (item.class_group && item.class_group !== 'open') {
            if (!student.kalolsav_class_group) return 'Class is not assigned to a membership category';
            if (!matchesClassGroup(student.kalolsav_class_group, item.class_group)) {
                return classGroupMismatchReason(student, item, event);
            }
        }
    }

    if (props.eventType === 'kids_fest') {
        if (!student.eligible_kids_fest) return 'Not eligible for Kids Fest (Pre-KG to Class 2)';
    }

    return 'Not eligible for this item';
}

// FestSchoolEventFeeService::breakdown() now tags every line with a line_type for every
// fee model (previously only sports_composite did, forcing this file to guess a line's
// category from its label text — the exact fragility that produced two bugs in a row:
// the student registration fee getting silently double-counted into "Item fees", then
// silently disappearing from the breakdown entirely). line_type is now the single source
// of truth; no label matching needed here anymore.
//
// Deliberately excludes school_reg and student_reg — those are once-per-school /
// once-per-student costs, not per-item ones, and EventBillingPanel.vue shows them
// separately as their own <li> rows (school reg reads event.school_fee.
// school_registration_fee directly; student reg via studentRegLine() below).
//
// This function/itemFeesDue() is only the item-level subtotal shown under "Item fees due"
// — do NOT use itemFeesDue() as "what the school owes" for gating upload forms or invoice
// links; use event.school_fee.total_due / .outstanding for that instead, since a school
// can owe money (school fee) with zero item fees.
function itemFeeLines(event) {
    const lines = event.school_fee?.breakdown?.items ?? [];
    return lines.filter(line => {
        const type = String(line.line_type || '').toLowerCase();
        return type !== 'school_reg' && type !== 'student_reg';
    });
}

function itemFeesDue(event) {
    return itemFeeLines(event).reduce((sum, line) => sum + Number(line.amount || 0), 0);
}

// How many billable units (items) the "Item fees" caption should report — summed from
// each line's own quantity rather than counting lines (one line can cover several units,
// e.g. cksc_tiered's "Additional items" line is a single row covering count-1 items) and
// restricted to genuinely per-item line types. flat_school's single flat charge and any
// team_fee lines are deliberately excluded — "(1 item)"/"(1 item)" would misreport a flat
// fee or a team entry as an item, which is the same class of bug this whole pass fixed.
// Returns 0 when nothing here is item-shaped (e.g. a pure flat_school or team-only fee),
// which the caller uses to hide the count/caption entirely rather than show "(0 items)".
function itemUnitCount(event) {
    const itemLineTypes = ['item_fee', 'item_fee_waived', 'extra_item'];

    return itemFeeLines(event)
        .filter(line => itemLineTypes.includes(String(line.line_type || '').toLowerCase()))
        .reduce((sum, line) => sum + Number(line.quantity ?? 1), 0);
}

// The one line itemFeeLines() deliberately excludes but that still needs its own visible
// row (same treatment as the school registration fee) — otherwise it silently vanishes
// from the breakdown while still being folded into Total fees due, which is exactly the
// "amount shown doesn't add up to the total" bug this introduced. Found by inspection when
// a school with no items beyond one extra saw School reg + item extra fee sum to less than
// Total fees due, with the ₹300/student line nowhere on screen.
function studentRegLine(event) {
    const lines = event.school_fee?.breakdown?.items ?? [];

    return lines.find(line => String(line.line_type || '').toLowerCase() === 'student_reg') ?? null;
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
    parts.push(studentDisplayName(student));
    if (student.event_registration_number) {
        parts.push(`Fest ID ${student.event_registration_number}`);
    }
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
    const event = props.events.find(e => Number(e.id) === Number(reg.event_id) || Number(e.parent_event_id) === Number(reg.event_id)) ?? props.events[0];
    if (!event) return reg.status === 'submitted';
    if (event.results_published || ['completed', 'cancelled'].includes(event.status)) return false;
    const fee = event.school_fee;
    if (fee && (fee.status === 'approved' || Number(fee.amount_paid ?? 0) > 0)) return false;
    return event.status === 'registration_open' || reg.status === 'submitted';
}

async function withdraw(id) {
    if (!(await confirm({ message: 'Cancel this registration?', destructive: true }))) return;
    router.post(`${programBase.value}/registrations/${id}/withdraw`, {}, { preserveScroll: true });
}

function canEdit(reg) {
    if (['withdrawn', 'rejected'].includes(reg.status)) return false;
    const event = props.events.find(e => Number(e.id) === Number(reg.event_id) || Number(e.parent_event_id) === Number(reg.event_id)) ?? props.events[0];
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

    if (!['group', 'team'].includes(item.participant_type) && (form.student_ids?.length ?? 0) > itemMaxPerSchool(item)) {
        const msg = `Maximum ${itemMaxPerSchool(item)} participants allowed for this item.`;
        itemErrors[key] = msg;
        showWarning(msg, 'Limit Exceeded');
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
            showSuccess(`Updated registration for ${item.title || item.clean_title}!`, 'Registration Updated');
        },
        onError: (errors) => {
            const err = extractItemErrors(errors, item.id)
                || errors.registration
                || page.props.flash?.error
                || 'Could not update registration.';
            itemErrors[key] = err;
            showError(err, 'Update Failed');
            scrollToItemRow(event.id, item.id);
        },
    });
}

function itemRegistrationCount(eventId, itemId) {
    const regs = registrationsForItem(eventId, itemId);
    if (!regs.length) return 0;
    const firstItem = regs[0]?.item;
    const isGroup = firstItem && ['group', 'team'].includes(firstItem.participant_type);
    if (isGroup) {
        return regs.length;
    }
    return regs.reduce((sum, r) => {
        const performers = (r.participants ?? []).filter(p => p.participant_role !== 'standby');
        return sum + Math.max(1, performers.length);
    }, 0);
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
            label: max > 1 ? `Registered (${regs}/${max})` : 'Registered',
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
    const limitLabel = max === 1 ? 'Max 1 entry per school' : `Max ${max} entries per school`;
    const rosterLabel = `${eligible} ${participantNoun}${eligible === 1 ? '' : 's'} eligible`;

    if (status === 'partial') {
        return {
            label: `Registered (${regs}/${max})`,
            badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            hint: `${regs} of ${max} entries registered · ${rosterLabel}`,
        };
    }

    return {
        label: 'Open',
        badgeClass: 'bg-indigo-50 text-indigo-700 border-indigo-100',
        hint: `${limitLabel} · ${rosterLabel}`,
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
    // Also surface top-level region/partition/membership errors
    for (const fallbackKey of ['region', 'partition', 'membership', 'registration']) {
        const fb = errors?.[fallbackKey];
        if (Array.isArray(fb) && fb.length) return fb.join(' ');
        if (typeof fb === 'string' && fb) return fb;
    }
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
        const reason = itemBlockReason(event, item);
        itemErrors[key] = reason;
        showWarning(reason, 'Item Registration Notice');
        scrollToItemRow(event.id, item.id);
        return;
    }

    if (!['group', 'team'].includes(item.participant_type) && (form.student_ids?.length ?? 0) > itemMaxPerSchool(item)) {
        const msg = `Maximum ${itemMaxPerSchool(item)} participants allowed for this item.`;
        itemErrors[key] = msg;
        showWarning(msg, 'Limit Exceeded');
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
            showSuccess(`Registered successfully for ${item.title || item.clean_title}!`, 'Registration Successful');
        },
        onError: (errors) => {
            const err = extractItemErrors(errors, item.id)
                || errors.registration
                || page.props.flash?.error
                || 'Could not register for this item.';
            itemErrors[key] = err;
            showError(err, 'Registration Failed');
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
        alertMessage.value = 'Pick athletes in item rows and select items for bulk assign.';
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
        onError: (errors) => {
            const keys = Object.keys(errors);
            if (keys.length) {
                alertMessage.value = errors[keys[0]];
            }
        },
    });
}

function uploadEventPayment(event) {
    // eventPaymentFiles[event.id] is now an array (see set-event-file / multiple file
    // input) — up to 5 images for one payment, submitted together as one receipt.
    const files = eventPaymentFiles[event.id];
    if (!files || !files.length) {
        alertMessage.value = 'Choose a payment proof file first, or skip — registration does not require it.';
        return;
    }
    // Txn ref / bank name / amount are all required by the backend — see
    // FestRegistrationController::uploadEventPayment(). The <input required> attributes
    // stop most bad submits, but router.post bypasses native form validation, so re-check here.
    if (!eventPaymentRefs[event.id] || !eventPaymentBanks[event.id] || !eventPaymentAmounts[event.id]) {
        alertMessage.value = 'Enter the transaction reference, bank name, and amount paid before uploading.';
        return;
    }
    router.post(`${programBase.value}/events/${event.id}/payment`, {
        payment_proof: files,
        transaction_ref: eventPaymentRefs[event.id],
        bank_name: eventPaymentBanks[event.id],
        amount: eventPaymentAmounts[event.id],
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
    // headPaymentFiles[key] is now an array (see set-head-file / multiple file input).
    const key = headPaymentKey(event.id, headFee.head_id);
    const files = headPaymentFiles[key];
    if (!files || !files.length) {
        alertMessage.value = 'Choose a payment proof file for this Sport Event first.';
        return;
    }
    // Txn ref / bank name / amount are all required by the backend — see
    // FestRegistrationController::uploadEventPayment(). Re-check here since router.post
    // bypasses native <input required> form validation.
    if (!headPaymentRefs[key] || !headPaymentBanks[key] || !headPaymentAmounts[key]) {
        alertMessage.value = 'Enter the transaction reference, bank name, and amount paid before uploading.';
        return;
    }
    router.post(`${programBase.value}/events/${event.id}/payment`, {
        payment_proof: files,
        transaction_ref: headPaymentRefs[key],
        bank_name: headPaymentBanks[key],
        amount: headPaymentAmounts[key],
        head_id: headFee.head_id,
    }, { forceFormData: true, preserveScroll: true });
}

function setPhasePaymentFile(eventId, phaseId, file) {
    phasePaymentFiles[phasePaymentKey(eventId, phaseId)] = file;
}

// Mirrors uploadHeadPayment() above — same shared payment route, distinguished by
// phase_id instead of head_id. Independent per phase: uploading for one phase never
// touches another phase's fee record (each phase has its own FestSchoolEventFee row
// via phase_id — see docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §3/§9).
function uploadPhasePayment(event, phaseFee) {
    const key = phasePaymentKey(event.id, phaseFee.phase_id);
    const files = phasePaymentFiles[key];
    if (!files || !files.length) {
        alertMessage.value = 'Choose a payment proof file for this phase first.';
        return;
    }
    if (!phasePaymentRefs[key] || !phasePaymentBanks[key] || !phasePaymentAmounts[key]) {
        alertMessage.value = 'Enter the transaction reference, bank name, and amount paid before uploading.';
        return;
    }
    router.post(`${programBase.value}/events/${event.id}/payment`, {
        payment_proof: files,
        transaction_ref: phasePaymentRefs[key],
        bank_name: phasePaymentBanks[key],
        amount: phasePaymentAmounts[key],
        phase_id: phaseFee.phase_id,
    }, { forceFormData: true, preserveScroll: true });
}

const incompleteSquads = computed(() => {
    if (!props.registrations || !props.events) return [];
    const list = [];
    for (const reg of props.registrations) {
        const regItemId = Number(reg.item_id);
        const item = props.events.flatMap(ev => ev.items ?? []).find(it => {
            const id = Number(it.id);
            const inhId = it.inherited_from_item_id ? Number(it.inherited_from_item_id) : null;
            return id === regItemId || (inhId !== null && inhId === regItemId);
        });
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
