<template>
    <SahodayaEventsLayout :title="`${event.title} — Registrations`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Registrations`" eyebrow="Registrations"
                    :description="filterDescription">
            <template #actions>
                <a :href="approvedPdfUrl" target="_blank" class="btn-secondary text-xs border-emerald-300 text-emerald-800 hover:bg-emerald-50 font-semibold">
                    🖨️ Approved List PDF
                </a>
                <Link v-if="competitionUrl" :href="competitionUrl" class="btn-secondary text-xs">← {{ event.event_type === 'sports' ? 'By Event Head' : 'By item head' }}</Link>
                <button type="button" class="btn-primary text-xs" @click="openOnBehalf">Register on behalf</button>
                <Link :href="`${base}/registrations/import`" class="btn-secondary text-xs">Import CSV</Link>
                <Link v-if="feeRequired" :href="`${base}/fees`" class="btn-secondary text-xs">Event fees</Link>
            </template>
        </PageHeader>

        <SportsSetupSubNav v-if="event.event_type === 'sports'" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="registrations" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="registrations" />

        <p v-if="selectedItemId" class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
            Showing registrations for one item.
            <Link :href="competitionUrl" class="font-semibold underline ml-1">Back to item listing</Link>
        </p>

        <!-- Sport Event / Region Switcher — hidden when scoped down to a single option
             (nothing to actually switch to; see FestRegistrationReviewController::index()) -->
        <div v-if="childEvents.length > 1" class="card mb-4 !py-3">
            <div class="flex flex-wrap gap-3 items-center">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ event.event_type === 'sports' ? 'Select Sport Event / Region:' : 'Select Phase / Region:' }}</label>
                <SearchableSelect
                    :model-value="String(event.id)"
                    @update:model-value="switchSportEvent"
                    :options="childEventOptions"
                    :all-option="false"
                    class="w-64 text-xs font-semibold"
                />
            </div>
        </div>

        <div class="card mb-4 space-y-3">
            <div class="flex flex-wrap gap-2 items-end">
                <div v-if="regionOptions.length">
                    <label class="text-xs font-semibold text-gray-600">Filter by region</label>
                    <SearchableSelect
                        v-model="form.region_id"
                        :options="regionOptions"
                        all-label="All regions"
                        class="mt-1 w-44"
                        @change="applyFilters"
                    />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Filter by school</label>
                    <SearchableSelect
                        v-model="form.school_id"
                        :options="schoolFilterOptions"
                        all-label="All schools"
                        class="mt-1"
                        @change="applyFilters"
                    />
                </div>
                <div class="w-56">
                    <label class="text-xs font-semibold text-gray-600">Filter by item</label>
                    <SearchableSelect
                        v-model="form.item_id"
                        :options="eventItemOptions"
                        placeholder="All items"
                        search-placeholder="Type item name to search…"
                        all-label="All items"
                        class="mt-1"
                        @change="applyFilters"
                    />
                </div>
                <div v-if="classGroupOptions.length" class="w-44">
                    <label class="text-xs font-semibold text-gray-600">Filter by category</label>
                    <SearchableSelect
                        v-model="form.class_group"
                        :options="classGroupOptions"
                        all-label="All categories"
                        class="mt-1"
                        @change="applyFilters"
                    />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Filter by status</label>
                    <SearchableSelect
                        v-model="form.status"
                        :options="[{ value: 'submitted', label: 'Submitted' }, { value: 'approved', label: 'Approved' }, { value: 'rejected', label: 'Rejected' }, { value: 'withdrawn', label: 'Withdrawn' }]"
                        all-label="All statuses"
                        class="mt-1 w-32"
                        @change="applyFilters"
                    />
                </div>
                <div class="flex-1 min-w-[180px]">
                    <label class="text-xs font-semibold text-gray-600">Search participant</label>
                    <input v-model="form.search" type="search" placeholder="Name or reg no…"
                           class="field text-sm mt-1" @keyup.enter="applyFilters">
                </div>
                <button type="button" class="btn-secondary text-xs" @click="applyFilters">Search</button>
                <label class="flex items-center gap-1 text-xs text-gray-600 ml-auto font-medium"
                       title="When checked, Approve/Reject/Bulk actions below will go through even if registration is locked, closed, or past its deadline for this event. Leave unchecked for normal use — this is for late/exception entries only.">
                    <input type="checkbox" v-model="overrideLifecycle"> Override locked registration
                    <span class="text-slate-400" aria-hidden="true">ⓘ</span>
                </label>
            </div>

            <!-- Select-all is filter-based, not "everything currently in memory": the list
                 below is paginated, so selecting checkboxes only ever covers this page. To
                 act on every pending registration matching the school/item filters above
                 (which is what admins actually want during bulk review), use the explicit
                 "select all N matching" action, which calls the bulk endpoint with those
                 filters instead of an id list. See docs/SCALE_AND_PAGINATION_PLAN.md §2. -->
            <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-gray-100">
                <button type="button" class="btn-secondary text-xs" @click="toggleSelectAllPage">
                    {{ allPageSelected ? 'Clear page selection' : 'Select submitted on this page' }}
                </button>
                <button type="button" class="btn-primary text-xs" :disabled="!selectedIds.length && !filterWideMode" @click="runBulkApprove">
                    {{ filterWideMode ? `Approve all ${pendingMatchingCount} matching` : `Approve selected (${selectedIds.length})` }}
                </button>
                <button type="button" class="btn-secondary text-xs text-red-600" :disabled="!selectedIds.length && !filterWideMode" @click="runBulkReject">
                    {{ filterWideMode ? `Reject all ${pendingMatchingCount} matching` : 'Reject selected' }}
                </button>
                <button v-if="selectedIds.length || filterWideMode" type="button" class="btn-ghost text-xs text-gray-500" @click="clearSelection">
                    Clear selection
                </button>
                <button v-if="!form.search && pendingMatchingCount > 0 && !filterWideMode"
                        type="button" class="text-xs font-semibold text-indigo-700 underline underline-offset-2 ml-2"
                        @click="selectAllMatchingFilter">
                    Select all {{ pendingMatchingCount }} pending matching current school/item filter
                </button>
                <span v-else-if="form.search && pendingMatchingCount > 0" class="text-xs text-gray-400 ml-2">
                    Clear the search box to select all matching a filter.
                </span>
            </div>
        </div>

        <!-- ── Sports: group registrations by age group ── -->
        <SportsRegistrationsTable
            v-if="event.event_type === 'sports'"
            :grouped-registrations="sportsGroupedRegistrations"
            :has-registrations="registrationsList.length > 0"
            :selected-ids="selectedIds"
            :schools="schoolNames"
            :gender-label="genderLabel"
            :status-class="statusClass"
            :standby-count="standbyCount"
            :can-cancel="canCancel"
            @toggle-select="toggleId"
            @substitute="openSubstitute"
            @approve="approve"
            @reject="reject"
            @cancel="cancel"
        />

        <!-- ── Kalotsav / other events: flat table ── -->
        <div v-else class="card card--flush overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3 w-8"></th>
                        <th class="p-3 w-12">Sl No</th>
                        <th class="p-3">School</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Category</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Participants</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(reg, idx) in registrationsList" :key="reg.id" class="border-t align-top">
                        <td class="p-3">
                            <input v-if="reg.status === 'submitted'" type="checkbox"
                                   :checked="selectedIds.includes(reg.id)" @change="toggleId(reg.id)">
                        </td>
                        <td class="p-3 text-gray-500">{{ idx + 1 }}</td>
                        <td class="p-3">{{ (schoolNames[reg.school_id] ?? reg.school_id ?? '').toString().toUpperCase() }}</td>
                        <td class="p-3">{{ reg.item?.title ?? '—' }}</td>
                        <td class="p-3 text-gray-600">{{ reg.item?.category_label ?? '—' }}</td>
                        <td class="p-3">
                            <span :class="statusClass(reg.status)" class="text-xs font-semibold px-2 py-0.5 rounded">
                                {{ reg.status }}
                            </span>
                        </td>
                        <td class="p-3 text-xs space-y-1">
                                    <div v-for="p in reg.participants" :key="p.id" class="flex flex-wrap items-center gap-1.5">
                                        <span class="font-medium text-slate-800">{{ p.student?.name ?? p.teacher?.name ?? '—' }}</span>
                                        <span v-if="p.student?.reg_no" class="text-gray-400">· {{ p.student.reg_no }}</span>
                                        <span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider"
                                              :class="p.participant_role === 'standby' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-indigo-50 text-indigo-800 border border-indigo-200'">
                                            {{ p.participant_role || 'performer' }}
                                        </span>
                                    </div>
                                    <div v-if="reg.status === 'approved' && standbyCount(reg)" class="mt-1">
                                        <button type="button" class="text-indigo-600 font-semibold" @click="openSubstitute(reg)">Substitute</button>
                                    </div>
                                </td>
                        <td class="p-3 text-right space-x-2">
                            <template v-if="reg.status === 'submitted'">
                                <button @click="approve(reg.id)" class="text-green-600 text-xs font-semibold">Approve</button>
                                <button @click="reject(reg.id)" class="text-red-600 text-xs font-semibold">Reject</button>
                            </template>
                            <button v-if="reg.status === 'approved'"
                                    @click="openManageParticipants(reg)"
                                    class="text-indigo-700 text-xs font-semibold">
                                Manage participants
                            </button>
                            <button v-if="canCancel(reg)"
                                    @click="cancel(reg.id)"
                                    class="text-gray-600 text-xs font-semibold">
                                Cancel
                            </button>
                            <!-- For a registration whose fee is already paid & approved, plain
                                 cancel() refuses (server-side) — this is the explicit path for
                                 that case, see FestRegistrationService::cancelWithRefund() /
                                 docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §4/§9.4. Always shown
                                 alongside Cancel since the client doesn't know payment state here;
                                 the server rejects it with a clear message if it doesn't apply. -->
                            <button v-if="canCancel(reg)"
                                    @click="cancelWithRefund(reg.id)"
                                    title="Use this if the school already paid and the fee was approved — plain Cancel will refuse in that case."
                                    class="text-amber-700 text-xs font-semibold">
                                Cancel &amp; refund
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!registrationsList.length">
                        <td colspan="7" class="p-0">
                            <EmptyState title="No registrations match your filters"
                                description="Try a different school, status, or item filter, or clear the search box above." icon="📋" class="py-8" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="registrations?.links?.length > 3" class="flex justify-center gap-1 mt-4">
            <Link v-for="link in registrations.links" :key="link.label"
                  :href="link.url || '#'"
                  class="px-3 py-1 rounded text-xs font-medium"
                  :class="link.active ? 'bg-[#0f3d7a] text-white' : (link.url ? 'text-[#0f3d7a] hover:bg-gray-100' : 'text-gray-300 pointer-events-none')"
                  v-html="link.label" />
        </div>

        <div v-if="substituteReg" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="substituteReg = null">
            <div class="card max-w-md w-full">
                <h3 class="font-semibold mb-3">Substitute performer</h3>
                <p class="text-xs text-gray-500 mb-3">{{ substituteReg.item?.title }}</p>
                <FormField label="Performer (out)">
                    <SearchableSelect
                        v-model="substituteForm.performer_id"
                        :options="substitutePerformerOptions"
                        :all-option="false"
                    />
                </FormField>
                <FormField label="Standby (in)" class-extra="mt-2">
                    <SearchableSelect
                        v-model="substituteForm.standby_id"
                        :options="substituteStandbyOptions"
                        :all-option="false"
                    />
                </FormField>
                <div class="flex gap-2 mt-4">
                    <button type="button" class="btn-primary text-sm" @click="submitSubstitute">Confirm swap</button>
                    <button type="button" class="btn-secondary text-sm" @click="substituteReg = null">Cancel</button>
                </div>
            </div>
        </div>

        <div v-if="manageReg" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeManageParticipants">
            <div class="card max-w-lg w-full max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <h3 class="font-semibold">Manage participants</h3>
                        <p class="text-xs text-gray-500 truncate">{{ manageReg.item?.title }} · {{ (schoolNames[manageReg.school_id] ?? '').toString().toUpperCase() }}</p>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600 text-2xl leading-none shrink-0" @click="closeManageParticipants">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto space-y-2 mt-3">
                    <div v-for="p in manageReg.participants" :key="p.id"
                         class="flex items-center justify-between gap-2 border border-slate-100 rounded-lg px-3 py-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800 truncate">{{ p.student?.name ?? p.teacher?.name ?? '—' }}</p>
                            <p class="text-[11px] text-slate-500">
                                {{ p.participant_role || 'performer' }}<span v-if="p.student?.reg_no"> · {{ p.student.reg_no }}</span>
                            </p>
                        </div>
                        <button type="button" class="text-red-600 text-xs font-semibold shrink-0 disabled:opacity-40"
                                :disabled="removingParticipantId === p.id" @click="removeParticipant(p)">
                            {{ removingParticipantId === p.id ? 'Removing…' : 'Remove' }}
                        </button>
                    </div>
                    <p v-if="!manageReg.participants?.length" class="text-xs text-slate-400 text-center py-4">No participants left.</p>
                </div>
                <div class="border-t border-gray-100 mt-3 pt-3">
                    <label class="text-xs font-semibold text-gray-600">Add participant as</label>
                    <div class="flex items-center gap-3 mt-1">
                        <SearchableSelect
                            v-model="addParticipantRole"
                            :options="[{ value: 'performer', label: 'Performer' }, { value: 'standby', label: 'Standby' }]"
                            :all-option="false"
                            class="w-36"
                        />
                        <button type="button" class="btn-secondary text-xs" @click="openAddParticipantPicker">Pick student</button>
                    </div>
                </div>
            </div>
        </div>

        <FestStudentPickerModal
            v-model="addParticipantPickerOpen"
            title="Add participant"
            :subtitle="manageReg?.item?.title"
            :entries="addParticipantEntries"
            v-model:selected-ids="addParticipantSelectedIds"
            :max-selected="1"
            confirm-label="Add"
            :show-add-student="false"
            @confirm="submitAddParticipant"
            @search="searchAddParticipantStudents"
        />

        <div v-if="onBehalfOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="onBehalfOpen = false"></div>
            <div class="relative modal-shell max-w-2xl w-full max-h-[90vh] flex flex-col">
                <div class="modal-head shrink-0">
                    <div>
                        <h3 class="font-bold">Register on behalf of school</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Enter a registration when a school cannot submit themselves.</p>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600 text-2xl leading-none" @click="onBehalfOpen = false">&times;</button>
                </div>
                <form @submit.prevent="submitOnBehalf" class="p-5 space-y-4 overflow-y-auto">
                    <FormField label="School" required>
                        <SearchableSelect
                            v-model="onBehalfForm.school_id"
                            :options="onBehalfSchoolOptions"
                            :all-option="true"
                            all-label="Select school"
                            :required="true"
                            @change="loadSchoolStudents"
                        />
                    </FormField>
                    <div v-if="event.event_type === 'kalolsavam' && onBehalfForm.school_id"
                         class="rounded-lg border px-3 py-2 text-xs"
                         :class="selectedSchoolRegion ? 'border-emerald-100 bg-emerald-50 text-emerald-900' : 'border-amber-100 bg-amber-50 text-amber-900'">
                        <p v-if="selectedSchoolRegion">
                            Kalotsav region: <strong>{{ selectedSchoolRegion }}</strong>
                        </p>
                        <p v-else>
                            This school has no Kalotsav region for the active year.
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/regions`" class="font-semibold underline">Assign region first</Link>.
                        </p>
                    </div>
                    <FormField label="Event item" required>
                        <SearchableSelect
                            v-model="onBehalfForm.item_id"
                            :options="eventItemOptions"
                            placeholder="Select item"
                            search-placeholder="Type item name to search…"
                            :all-option="false"
                        />
                    </FormField>
                    <!-- Existing registration warning & details for selected item -->
                    <div v-if="existingRegistrationForItem" class="rounded-lg border border-amber-200 bg-amber-50/90 p-3.5 text-xs space-y-2.5 text-amber-950">
                        <div class="flex items-center justify-between font-semibold border-b border-amber-200/80 pb-2">
                            <span class="flex items-center gap-1.5 text-amber-900">
                                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                School already has a registration for this item
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-200 text-amber-900">
                                {{ existingRegistrationForItem.status }}
                            </span>
                        </div>

                        <div v-if="existingRegistrationForItem.team_name" class="text-amber-900">
                            Team name: <strong>{{ existingRegistrationForItem.team_name }}</strong>
                        </div>

                        <div>
                            <span class="font-semibold text-amber-900">Already Registered Performers:</span>
                            <div v-if="existingRegistrationForItem.performers.length" class="flex flex-wrap gap-1 mt-1">
                                <span v-for="p in existingRegistrationForItem.performers" :key="p.id" class="inline-flex items-center px-2 py-0.5 rounded bg-amber-200/80 text-amber-950 font-medium">
                                    {{ p.reg_no ? p.reg_no + ' · ' + p.name : p.name }}
                                </span>
                            </div>
                            <p v-else class="text-amber-700/80 italic mt-0.5">None</p>
                        </div>

                        <div>
                            <span class="font-semibold text-amber-900">Already Registered Standbys:</span>
                            <div v-if="existingRegistrationForItem.standbys.length" class="flex flex-wrap gap-1 mt-1">
                                <span v-for="s in existingRegistrationForItem.standbys" :key="s.id" class="inline-flex items-center px-2 py-0.5 rounded bg-amber-300/70 text-amber-950 font-medium">
                                    {{ s.reg_no ? s.reg_no + ' · ' + s.name : s.name }}
                                </span>
                            </div>
                            <p v-else class="text-amber-700/80 italic mt-0.5">None</p>
                        </div>
                    </div>

                    <!-- Summary of all registered items for selected school if no item selected yet -->
                    <div v-else-if="onBehalfForm.school_id && !onBehalfForm.item_id && props.existingSchoolRegistrations?.length" class="rounded-lg border border-slate-200 bg-slate-50 p-2.5 text-xs text-slate-700">
                        <span class="font-semibold">Registered items for this school ({{ props.existingSchoolRegistrations.length }}):</span>
                        <div class="flex flex-wrap gap-1 mt-1.5 max-h-24 overflow-y-auto">
                            <span v-for="reg in props.existingSchoolRegistrations" :key="reg.id" class="inline-flex items-center px-2 py-0.5 rounded bg-slate-200/80 text-slate-800 text-[11px]">
                                {{ reg.item?.title || 'Item #' + reg.item_id }} ({{ reg.performers.length }} performers{{ reg.standbys.length ? `, ${reg.standbys.length} standby` : '' }})
                            </span>
                        </div>
                    </div>
                    <FormField v-if="selectedItemIsGroup" label="Team name" required>
                        <input v-model="onBehalfForm.team_name" type="text" class="field text-sm" required>
                    </FormField>
                    <div v-if="selectedItemIsGroup" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <FormField label="Coach name">
                            <input v-model="onBehalfForm.coach_name" type="text" class="field text-sm" placeholder="Optional">
                        </FormField>
                        <FormField label="Coach phone">
                            <input v-model="onBehalfForm.coach_phone" type="text" class="field text-sm" placeholder="Optional">
                        </FormField>
                        <FormField label="Manager name">
                            <input v-model="onBehalfForm.manager_name" type="text" class="field text-sm" placeholder="Optional">
                        </FormField>
                        <FormField label="Manager phone">
                            <input v-model="onBehalfForm.manager_phone" type="text" class="field text-sm" placeholder="Optional">
                        </FormField>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <label class="text-xs font-semibold text-gray-600">Performers</label>
                            <button type="button" class="btn-secondary text-xs !min-h-0 !py-1"
                                    :disabled="!onBehalfForm.school_id || !onBehalfForm.item_id"
                                    @click="performerPickerOpen = true">
                                Pick students ({{ onBehalfForm.student_ids.length }})
                            </button>
                        </div>
                        <div v-if="onBehalfForm.student_ids.length" class="flex flex-wrap gap-1.5">
                            <span v-for="id in onBehalfForm.student_ids" :key="id"
                                  class="inline-flex items-center px-2 py-0.5 rounded-full bg-[#0f3d7a]/10 text-[#0f3d7a] text-[11px] font-medium">
                                {{ studentLabel(id) }}
                            </span>
                        </div>
                        <p v-else class="text-xs text-slate-400">No performers selected.</p>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <label class="text-xs font-semibold text-gray-600">Standbys (optional, max 2)</label>
                            <button type="button" class="btn-secondary text-xs !min-h-0 !py-1"
                                    :disabled="!onBehalfForm.school_id || !onBehalfForm.item_id"
                                    @click="standbyPickerOpen = true">
                                Pick standbys ({{ onBehalfForm.standby_ids.length }})
                            </button>
                        </div>
                        <div v-if="onBehalfForm.standby_ids.length" class="flex flex-wrap gap-1.5">
                            <span v-for="id in onBehalfForm.standby_ids" :key="id"
                                  class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[11px] font-medium">
                                {{ studentLabel(id) }}
                            </span>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="onBehalfForm.auto_approve" type="checkbox" class="rounded">
                        Auto-approve after submit
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="btn-ghost text-sm" @click="onBehalfOpen = false">Cancel</button>
                        <button type="submit" class="btn-primary text-sm"
                                :disabled="onBehalfSubmitting || !onBehalfForm.school_id || !onBehalfForm.item_id || !onBehalfForm.student_ids.length">
                            Submit registration
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <FestStudentPickerModal
            v-model="performerPickerOpen"
            title="Pick performers"
            :subtitle="pickerSubtitle"
            :entries="rosterEntries"
            v-model:selected-ids="onBehalfForm.student_ids"
            :team-name="selectedItemIsGroup ? onBehalfForm.team_name : undefined"
            :require-team-name="selectedItemIsGroup"
            confirm-label="Use selection"
            @update:team-name="onBehalfForm.team_name = $event"
        />

        <FestStudentPickerModal
            v-model="standbyPickerOpen"
            title="Pick standbys"
            subtitle="Optional substitutes — max 2"
            :entries="standbyRosterEntries"
            v-model:selected-ids="onBehalfForm.standby_ids"
            confirm-label="Use selection"
        />

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import FestStudentPickerModal from '@/Components/school/FestStudentPickerModal.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, registrations: Object, schools: Object, schoolNames: Object,
    feeRequired: Boolean, activityLogs: { type: Array, default: () => [] },
    registerStudents: { type: Array, default: () => [] },
    existingSchoolRegistrations: { type: Array, default: () => [] },
    registerSchoolId: { type: [String, Number], default: '' },
    eventItems: { type: Array, default: () => [] },
    classGroupOptions: { type: Array, default: () => [] },
    schoolRegions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({ search: '', school_id: '', status: '', region_id: '', class_group: '' }) },
    selectedHeadId: { type: [String, Number], default: null },
    selectedItemId: { type: [Number, String], default: null },
    competitionUrl: { type: String, default: null },
    regionOptions: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
});

const filterDescription = computed(() => {
    if (props.selectedItemId) {
        return 'Review and approve registrations for a single competition item.';
    }
    if (props.selectedHeadId) {
        return props.event.event_type === 'sports'
            ? 'Filtered by Event Head — approve, reject, or register on behalf of schools.'
            : 'Filtered by item head — approve, reject, or register on behalf of schools.';
    }
    return 'Approve or reject school registrations. Register on behalf of a school when needed.';
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const { confirm, prompt } = useConfirm();

function navigateEvent(eventId) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${eventId}/registrations`);
}

const childEventOptions = computed(() => props.childEvents.map(ev => ({
    value: String(ev.id),
    label: ev.short_title || ev.title,
})));

// Server-driven filters — school_id/item_id/status now run as real query constraints
// (see FestRegistrationReviewController::index()), not just an in-memory slice of an
// eagerly-loaded list. item_id defaults from selectedItemId so a link from the
// competition hub (which sets that prop) shows up correctly in the dropdown too.
const form = reactive({
    school_id: props.filters?.school_id ?? '',
    item_id: props.selectedItemId ? String(props.selectedItemId) : '',
    status: props.filters?.status ?? '',
    search: props.filters?.search ?? '',
    region_id: props.filters?.region_id ?? '',
    class_group: props.filters?.class_group ?? '',
});

const classGroupOptions = computed(() => props.classGroupOptions.map(c => ({ value: c.value, label: c.label })));

const approvedPdfUrl = computed(() => {
    const p = new URLSearchParams();
    if (form.school_id) p.set('school_id', form.school_id);
    if (form.item_id) p.set('item_id', form.item_id);
    if (form.region_id) p.set('region_id', form.region_id);
    if (form.search) p.set('search', form.search);
    const query = p.toString();
    return `${base}/registrations/approved-pdf${query ? '?' + query : ''}`;
});

function applyFilters() {
    router.get(`${base}/registrations`, {
        search: form.search || undefined,
        school_id: form.school_id || undefined,
        item_id: form.item_id || undefined,
        status: form.status || undefined,
        region_id: form.region_id || undefined,
        class_group: form.class_group || undefined,
    }, { preserveScroll: true, preserveState: true, replace: true });
}

const registrationsList = computed(() => props.registrations?.data ?? []);

function formatGenderLabel(gender) {
    if (!gender) return null;
    const g = String(gender).toLowerCase();
    if (g === 'male' || g === 'boys') return 'Boys';
    if (g === 'female' || g === 'girls') return 'Girls';
    if (g === 'mixed') return 'Mixed';
    if (g === 'open') return 'Open';
    return g.charAt(0).toUpperCase() + g.slice(1);
}

const eventItemOptions = computed(() => props.eventItems.map(i => {
    const genderLabel = formatGenderLabel(i.gender);
    const details = [];
    if (i.category_label) details.push(i.category_label);
    if (genderLabel) details.push(genderLabel);

    const name = details.length ? `${i.title} (${details.join(' · ')})` : i.title;
    return {
        id: i.id,
        name,
    };
}));

const schoolFilterOptions = computed(() => Object.entries(props.schoolNames).map(([id, name]) => ({
    value: id,
    label: name,
})));

const onBehalfSchoolOptions = computed(() => Object.entries(props.schools).map(([id, name]) => ({
    value: id,
    label: name,
})));

// ── Selection / bulk approve-reject ─────────────────────────────────────────────
// selectedIds is an explicit id list, now necessarily page-scoped since the list
// itself is paginated. filterWideMode is the separate, explicit "act on every
// submitted registration matching the school/item filter" action the plan called
// for — it does NOT enumerate ids at all, it just re-sends the current school_id/
// item_id filters to the bulk endpoint, which already scopes to status='submitted'
// server-side. See docs/SCALE_AND_PAGINATION_PLAN.md §2.
const selectedIds = ref([]);
const filterWideMode = ref(false);
const overrideLifecycle = ref(false);
const substituteReg = ref(null);
const substituteForm = ref({ performer_id: '', standby_id: '' });

const manageReg = ref(null);
const addParticipantRole = ref('performer');
const addParticipantPickerOpen = ref(false);
const addParticipantEntries = ref([]);
const addParticipantSelectedIds = ref([]);
const removingParticipantId = ref(null);
const onBehalfOpen = ref(false);
const performerPickerOpen = ref(false);
const standbyPickerOpen = ref(false);
const onBehalfSubmitting = ref(false);
const onBehalfForm = reactive({
    school_id: props.registerSchoolId ? String(props.registerSchoolId) : '',
    item_id: '',
    team_name: '',
    coach_name: '',
    coach_phone: '',
    manager_name: '',
    manager_phone: '',
    student_ids: [],
    standby_ids: [],
    auto_approve: true,
});
const selectedSchoolRegion = computed(() => {
    if (!onBehalfForm.school_id) return null;
    return props.schoolRegions?.[onBehalfForm.school_id]
        ?? props.schoolRegions?.[String(onBehalfForm.school_id)]
        ?? null;
});

watch(() => props.registerStudents, () => {
    if (onBehalfForm.school_id && !props.registerStudents.length && props.registerSchoolId) {
        // students loaded via page reload
    }
}, { deep: true });

watch(() => onBehalfForm.standby_ids, (ids) => {
    if (ids.length > 2) onBehalfForm.standby_ids = ids.slice(0, 2);
}, { deep: true });

const selectedItem = computed(() =>
    props.eventItems.find(i => String(i.id) === String(onBehalfForm.item_id)) ?? null,
);

const selectedItemIsGroup = computed(() =>
    selectedItem.value && ['team', 'group', 'pair', 'trio'].includes(selectedItem.value.participant_type),
);

const pickerSubtitle = computed(() => {
    if (!selectedItem.value) return 'Select an item first';
    const parts = [selectedItem.value.title];
    if (selectedItem.value.age_group && selectedItem.value.age_group !== 'open') {
        parts.push(String(selectedItem.value.age_group).toUpperCase());
    }
    return parts.join(' · ');
});

function studentMatchesItem(student, item) {
    if (props.event.academic_year_id && student.academic_year_id && props.event.academic_year_id !== student.academic_year_id) {
        return false;
    }
    if (props.event.event_type === 'kalolsavam') {
        if (!student.eligible_kalolsav) return false;
        if (item.class_group && item.class_group !== 'open' && student.kalolsav_class_group !== item.class_group) return false;
    }
    if (['custom', 'english_fest', 'science_fest'].includes(props.event.event_type)) {
        if (item.class_group && item.class_group !== 'open' && student.kalolsav_class_group !== item.class_group) return false;
    }
    if (props.event.event_type === 'kids_fest') {
        if (!student.eligible_kids_fest) return false;
        if (item.kids_band && item.kids_band !== 'open' && student.kids_fest_band !== item.kids_band) return false;
    }
    if (props.event.event_type === 'sports') {
        if (!student.dob) return false;
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

function ineligibilityReason(student, item) {
    if (props.event.academic_year_id && student.academic_year_id && props.event.academic_year_id !== student.academic_year_id) {
        return 'Wrong academic year';
    }
    if (props.event.event_type === 'sports' && item?.age_group && item.age_group !== 'open') {
        const age = student.sports_age_on_cutoff;
        if (age != null) return `Age ${age} — needs ${String(item.age_group).toUpperCase()}`;
    }
    if (item?.gender && !['open', 'mixed'].includes(item.gender) && student.gender && student.gender !== item.gender) {
        return 'Gender mismatch';
    }
    return 'Not eligible for this item';
}

function buildRosterEntries(excludeIds = []) {
    const item = selectedItem.value;
    if (!item) return [];
    return (props.registerStudents ?? []).map((student) => {
        const eligible = !excludeIds.includes(student.id) && studentMatchesItem(student, item);
        return {
            id: student.id,
            name: student.name,
            regNo: student.reg_no || '',
            meta: [student.class_name, student.reg_no].filter(Boolean).join(' · '),
            eligible,
            reason: eligible ? null : ineligibilityReason(student, item),
        };
    });
}

const rosterEntries = computed(() => buildRosterEntries(onBehalfForm.standby_ids));
const standbyRosterEntries = computed(() => buildRosterEntries(onBehalfForm.student_ids));

function studentLabel(id) {
    const s = props.registerStudents.find(st => st.id === id);
    if (!s) return `#${id}`;
    return s.reg_no ? `${s.reg_no} · ${s.name}` : s.name;
}

function openOnBehalf() {
    onBehalfForm.school_id = form.school_id || onBehalfForm.school_id || '';
    onBehalfForm.item_id = '';
    onBehalfForm.team_name = '';
    onBehalfForm.coach_name = '';
    onBehalfForm.coach_phone = '';
    onBehalfForm.manager_name = '';
    onBehalfForm.manager_phone = '';
    onBehalfForm.student_ids = [];
    onBehalfForm.standby_ids = [];
    onBehalfOpen.value = true;
    if (onBehalfForm.school_id) loadSchoolStudents();
}

const existingRegistrationForItem = computed(() => {
    if (!onBehalfForm.school_id || !onBehalfForm.item_id) return null;
    return (props.existingSchoolRegistrations ?? []).find(
        r => String(r.item_id) === String(onBehalfForm.item_id)
    ) ?? null;
});

function loadSchoolStudents() {
    onBehalfForm.student_ids = [];
    onBehalfForm.standby_ids = [];
    if (!onBehalfForm.school_id) return;
    router.get(`${base}/registrations`, { school_id: onBehalfForm.school_id }, {
        preserveScroll: true,
        preserveState: true,
        only: ['registerStudents', 'registerSchoolId', 'existingSchoolRegistrations'],
    });
}

function submitOnBehalf() {
    if (!onBehalfForm.school_id || !onBehalfForm.item_id || !onBehalfForm.student_ids.length) return;
    onBehalfSubmitting.value = true;
    router.post(`${base}/registrations/on-behalf`, {
        school_id: onBehalfForm.school_id,
        item_id: onBehalfForm.item_id,
        team_name: onBehalfForm.team_name || null,
        coach_name: onBehalfForm.coach_name || null,
        coach_phone: onBehalfForm.coach_phone || null,
        manager_name: onBehalfForm.manager_name || null,
        manager_phone: onBehalfForm.manager_phone || null,
        student_ids: onBehalfForm.student_ids,
        standby_ids: onBehalfForm.standby_ids,
        auto_approve: onBehalfForm.auto_approve,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            onBehalfOpen.value = false;
            onBehalfSubmitting.value = false;
        },
        onError: () => { onBehalfSubmitting.value = false; },
        onFinish: () => { onBehalfSubmitting.value = false; },
    });
}

function statusClass(status) {
    return {
        submitted: 'bg-yellow-50 text-yellow-700',
        approved:  'bg-green-50 text-green-700',
        rejected:  'bg-red-50 text-red-600',
        withdrawn: 'bg-gray-100 text-gray-500',
    }[status] ?? 'bg-gray-50 text-gray-600';
}

function canCancel(reg) {
    return !['withdrawn', 'rejected'].includes(reg.status);
}

function performerCount(reg) {
    return reg.participants?.filter(p => p.participant_role !== 'standby').length ?? reg.participants?.length ?? 0;
}

function standbyCount(reg) {
    return reg.participants?.filter(p => p.participant_role === 'standby').length ?? 0;
}

function performers(reg) {
    return reg.participants?.filter(p => p.participant_role !== 'standby') ?? [];
}

function standbys(reg) {
    return reg.participants?.filter(p => p.participant_role === 'standby') ?? [];
}

function participantLabel(p) {
    return p.student?.name ?? p.teacher?.name ?? `#${p.id}`;
}

const substitutePerformerOptions = computed(() =>
    substituteReg.value ? performers(substituteReg.value).map(p => ({ value: p.id, label: participantLabel(p) })) : [],
);

const substituteStandbyOptions = computed(() =>
    substituteReg.value ? standbys(substituteReg.value).map(p => ({ value: p.id, label: participantLabel(p) })) : [],
);

function openSubstitute(reg) {
    substituteReg.value = reg;
    const perf = performers(reg)[0];
    const stby = standbys(reg)[0];
    substituteForm.value = { performer_id: perf?.id ?? '', standby_id: stby?.id ?? '' };
}

function submitSubstitute() {
    const reg = substituteReg.value;
    if (!reg || !substituteForm.value.performer_id || !substituteForm.value.standby_id) return;
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/${reg.id}/substitute/${substituteForm.value.performer_id}/${substituteForm.value.standby_id}`,
        {},
        { preserveScroll: true, onSuccess: () => { substituteReg.value = null; } },
    );
}

function openManageParticipants(reg) {
    manageReg.value = reg;
}

function closeManageParticipants() {
    manageReg.value = null;
}

// Re-point manageReg at the freshly-reloaded copy in registrationsList after an add/remove —
// Inertia replaces the whole `registrations` prop rather than mutating the snapshot the modal
// already holds, so without this the modal would keep showing the pre-mutation roster.
function refreshManageReg(regId) {
    const updated = registrationsList.value.find(r => r.id === regId);
    if (updated) manageReg.value = updated;
}

function eligibleStudentsUrl(reg, search) {
    const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/${reg.id}/eligible-students`;
    return search ? `${base}?search=${encodeURIComponent(search)}` : base;
}

function studentRowToEntry(row) {
    return {
        id: row.id,
        name: row.name,
        regNo: row.reg_no || '',
        admissionNo: row.admission_number || '',
        meta: [row.class_name, row.reg_no].filter(Boolean).join(' · '),
        eligible: !!row.eligible,
        reason: row.eligible ? null : 'Not eligible for this item',
    };
}

async function fetchEligibleStudents(reg, search = '') {
    try {
        const res = await fetch(eligibleStudentsUrl(reg, search), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) return [];
        const data = await res.json();
        return (data.students ?? []).map(studentRowToEntry);
    } catch {
        return [];
    }
}

async function openAddParticipantPicker() {
    if (!manageReg.value) return;
    addParticipantSelectedIds.value = [];
    addParticipantEntries.value = await fetchEligibleStudents(manageReg.value);
    addParticipantPickerOpen.value = true;
}

// Mirrors FestRegistrationController::eligibleStudents()'s consumer in Registration.vue
// (searchStudentsForEvent) — merge by id so results already shown/selected don't disappear
// when a search narrows the list.
async function searchAddParticipantStudents(query) {
    if (!manageReg.value) return;
    const term = String(query ?? '').trim();
    if (!term) return;
    const results = await fetchEligibleStudents(manageReg.value, term);
    const byId = new Map(addParticipantEntries.value.map(e => [e.id, e]));
    for (const entry of results) byId.set(entry.id, entry);
    addParticipantEntries.value = Array.from(byId.values());
}

async function submitAddParticipant() {
    const reg = manageReg.value;
    const studentId = addParticipantSelectedIds.value[0];
    if (!reg || !studentId) return;
    if (reg.item?.results_published_at) {
        const ok = await confirm({
            message: `Results for "${reg.item?.title}" are already published. Adding a participant now won't automatically appear in the published results until you re-publish. Continue?`,
            destructive: true,
        });
        if (!ok) return;
    }
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/${reg.id}/participants`,
        { student_id: studentId, role: addParticipantRole.value },
        { preserveScroll: true, onSuccess: () => refreshManageReg(reg.id) },
    );
}

async function removeParticipant(participant) {
    const reg = manageReg.value;
    if (!reg) return;
    if (reg.item?.results_published_at) {
        const ok = await confirm({
            message: `Results for "${reg.item?.title}" are already published. Removing this participant now won't automatically update the published results until you re-publish. Continue?`,
            destructive: true,
        });
        if (!ok) return;
    }
    removingParticipantId.value = participant.id;
    router.delete(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/${reg.id}/participants/${participant.id}`,
        {
            preserveScroll: true,
            onSuccess: () => refreshManageReg(reg.id),
            onFinish: () => { removingParticipantId.value = null; },
        },
    );
}

function approve(id) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/${id}/approve`, {
        override_lifecycle: overrideLifecycle.value,
    }, { preserveScroll: true });
}

async function reject(id) {
    const reason = await prompt({ message: 'Rejection reason (required):', inputMultiline: true });
    if (!reason?.trim()) return;

    if (await confirm({ message: 'Reject this registration?' })) {
        router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/${id}/reject`, {
            override_lifecycle: overrideLifecycle.value,
            rejection_reason: reason.trim(),
        }, { preserveScroll: true });
    }
}

async function cancel(id) {
    if (!(await confirm({ message: 'Cancel this registration? The school will be notified.' }))) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/${id}/cancel`, {}, { preserveScroll: true });
}

async function cancelWithRefund(id) {
    const reason = await prompt({
        message: 'This cancels the registration even though its fee was already paid and approved, and issues a fee '
        + 'credit to the school for the amount freed up. Only use this for a genuinely paid+approved registration '
        + '— plain Cancel already handles everything else. Reason (required):',
        inputMultiline: true,
    });
    if (!reason) return;

    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/${id}/cancel-with-refund`, {
        reason,
    }, { preserveScroll: true });
}

function toggleId(id) {
    // A manual per-row pick always means "just this explicit set" — drop out of
    // filter-wide mode so the two selection concepts never mix silently.
    if (filterWideMode.value) filterWideMode.value = false;
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) selectedIds.value.push(id);
    else selectedIds.value.splice(idx, 1);
}

const pageSubmittedIds = computed(() => registrationsList.value.filter(r => r.status === 'submitted').map(r => r.id));

const allPageSelected = computed(() =>
    pageSubmittedIds.value.length > 0 && pageSubmittedIds.value.every(id => selectedIds.value.includes(id)),
);

function toggleSelectAllPage() {
    filterWideMode.value = false;
    selectedIds.value = allPageSelected.value ? [] : [...pageSubmittedIds.value];
}

function selectAllMatchingFilter() {
    selectedIds.value = [];
    filterWideMode.value = true;
}

function clearSelection() {
    selectedIds.value = [];
    filterWideMode.value = false;
}

async function runBulkApprove() {
    if (filterWideMode.value) {
        if (!(await confirm({ message: `Approve all ${props.pendingMatchingCount} pending registration(s) matching the current school/item filter?`, destructive: false }))) return;
        router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/bulk-approve`, {
            school_id: form.school_id || undefined,
            item_id: form.item_id || undefined,
            override_lifecycle: overrideLifecycle.value,
        }, { preserveScroll: true, onSuccess: clearSelection });
        return;
    }

    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/bulk-approve`, {
        registration_ids: selectedIds.value,
        override_lifecycle: overrideLifecycle.value,
    }, { preserveScroll: true, onSuccess: clearSelection });
}

async function runBulkReject() {
    const reason = await prompt({ message: 'Rejection reason for these registrations (optional):', inputMultiline: true, inputRequired: false });
    if (reason === null) return; // User cancelled prompt

    if (filterWideMode.value) {
        if (!(await confirm({ message: `Reject all ${props.pendingMatchingCount} pending registration(s) matching the current school/item filter?` }))) return;
        router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/bulk-reject`, {
            school_id: form.school_id || undefined,
            item_id: form.item_id || undefined,
            override_lifecycle: overrideLifecycle.value,
            rejection_reason: reason.trim(),
        }, { preserveScroll: true, onSuccess: clearSelection });
        return;
    }

    if (!(await confirm({ message: `Reject ${selectedIds.value.length} registration(s)?` }))) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/bulk-reject`, {
        registration_ids: selectedIds.value,
        override_lifecycle: overrideLifecycle.value,
        rejection_reason: reason.trim(),
    }, { preserveScroll: true, onSuccess: clearSelection });
}

// ── Sports helpers ────────────────────────────────────────────────────────────
const SPORTS_AGE_ORDER = ['u8', 'u10', 'u11', 'u12', 'u14', 'u16', 'u17', 'u18', 'u19', 'open'];

function ageGroupKey(reg) {
    return reg.item?.age_group
        ? String(reg.item.age_group).toLowerCase()
        : 'open';
}

function ageGroupLabel(key) {
    return key === 'open' ? 'Open' : String(key).toUpperCase();
}

import SportsRegistrationsTable from '@/Components/sahodaya/SportsRegistrationsTable.vue';

function genderLabel(gender) {
    const g = String(gender ?? '').toLowerCase();
    if (['male', 'm', 'boys', 'boy'].includes(g)) return 'Boys';
    if (['female', 'f', 'girls', 'girl'].includes(g)) return 'Girls';
    if (g === 'mixed') return 'Mixed';
    return gender ?? 'Open';
}

const sportsGroupedRegistrations = computed(() => {
    const grouped = {};
    for (const reg of registrationsList.value) {
        const key = ageGroupKey(reg);
        const label = ageGroupLabel(key);
        if (!grouped[label]) grouped[label] = [];
        grouped[label].push(reg);
    }
    // Sort by SPORTS_AGE_ORDER
    const sorted = {};
    const orderMap = Object.fromEntries(SPORTS_AGE_ORDER.map((k, i) => [k, i]));
    Object.keys(grouped)
        .sort((a, b) => {
            const ka = a === 'Open' ? 'open' : a.toLowerCase();
            const kb = b === 'Open' ? 'open' : b.toLowerCase();
            return (orderMap[ka] ?? 99) - (orderMap[kb] ?? 99);
        })
        .forEach(label => { sorted[label] = grouped[label]; });
    return sorted;
});
</script>
