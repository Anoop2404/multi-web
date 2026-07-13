<template>
    <div class="space-y-6 max-w-4xl">
        <div class="rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-950">
            <p class="font-semibold">Fest event fees</p>
            <p class="mt-1 text-xs text-sky-900/90">
                Charged per school for this event only — not annual Sahodaya membership.
                Settings here override the state program defaults for this event.
            </p>
        </div>

        <div v-if="event.event_type === 'sports'"
             class="rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-950">
            <p class="font-semibold">Sports composite billing (always on)</p>
            <p class="mt-1 text-xs text-sky-900/90">
                Each Event Head bills school / student / team fees independently.
                Edit those on the Competition hub (same form as Add head). Settings below are optional event-wide fallbacks only.
            </p>
        </div>

        <form @submit.prevent="saveFeeSettings" class="space-y-6">
            <section class="card space-y-4">
                <div>
                    <h3 class="section-title">{{ event.event_type === 'sports' ? 'Event-wide fee override (optional)' : 'Fee model' }}</h3>
                    <p class="section-desc">
                        <template v-if="event.event_type === 'sports'">
                            Billing is always Sports composite. Per-head fees on Competition take priority.
                        </template>
                        <template v-else>
                            How schools are billed for registrations in this event.
                        </template>
                    </p>
                </div>

                <FormField v-if="event.event_type !== 'sports'" label="Billing model">
                    <template #default="{ id }">
                        <select :id="id" v-model="feeSettingsForm.fee_model" class="field mt-1">
                            <option v-for="(label, key) in feeModels" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </template>
                </FormField>
                <input v-else type="hidden" v-model="feeSettingsForm.fee_model">

                <div v-if="event.event_type === 'sports'" class="grid gap-3 sm:grid-cols-2 border-t border-slate-100 pt-4">
                    <FormField label="Fallback school fee (₹)" hint="When a head has no school fee">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.school_registration_flat" type="number" min="0" class="field" placeholder="—">
                        </template>
                    </FormField>
                    <FormField label="Optional fee cap (₹)">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.school_fee_cap" type="number" min="0" class="field" placeholder="—">
                        </template>
                    </FormField>
                    <p class="sm:col-span-2 text-sm text-slate-600">
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/competition`" class="link-brand font-semibold">
                            Edit head fees on Competition →
                        </Link>
                    </p>
                </div>

                <div v-else-if="feeSettingsForm.fee_model === 'cksc_tiered'" class="space-y-4 border-t border-slate-100 pt-4">
                    <p class="text-xs text-slate-600">
                        Tiered participation fees for this event only. Annual Sahodaya school membership is collected separately under Membership.
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <FormField label="First item (₹)">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="feeSettingsForm.first_item" type="number" min="0" class="field" placeholder="350">
                            </template>
                        </FormField>
                        <FormField label="Each additional item (₹)">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="feeSettingsForm.additional_item" type="number" min="0" class="field" placeholder="100">
                            </template>
                        </FormField>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="feeSettingsForm.charge_standbys">
                        Charge standby registrations as billable items
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" v-model="feeSettingsForm.include_school_registration" class="mt-0.5">
                        <span>
                            Add optional one-time school registration line on the event invoice
                            <span class="block text-xs text-slate-500 mt-0.5">Not the annual Sahodaya membership fee — only enable if you charge an extra registration amount for this event.</span>
                        </span>
                    </label>
                    <div v-if="feeSettingsForm.include_school_registration" class="grid gap-3 sm:grid-cols-2">
                        <FormField label="Secondary school (₹)">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="feeSettingsForm.school_registration.secondary" type="number" min="0" class="field">
                            </template>
                        </FormField>
                        <FormField label="Senior secondary (₹)">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="feeSettingsForm.school_registration.senior_secondary" type="number" min="0" class="field">
                            </template>
                        </FormField>
                    </div>
                    <FormField label="Optional fee cap (₹)" hint="Maximum total due per school">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.school_fee_cap" type="number" min="0" class="field max-w-xs">
                        </template>
                    </FormField>
                </div>

                <div v-else-if="feeSettingsForm.fee_model === 'flat_school'" class="space-y-3 border-t border-slate-100 pt-4">
                    <FormField label="Flat amount per school (₹)">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.flat_amount" type="number" min="0" class="field max-w-xs">
                        </template>
                    </FormField>
                    <FormField label="Optional fee cap (₹)" hint="Maximum total due per school — leave blank for no cap">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.school_fee_cap" type="number" min="0" class="field max-w-xs">
                        </template>
                    </FormField>
                </div>

                <div v-else-if="feeSettingsForm.fee_model === 'per_item'" class="space-y-3 border-t border-slate-100 pt-4">
                    <FormField label="Amount per registered item (₹)">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.per_item_amount" type="number" min="0" class="field max-w-xs">
                        </template>
                    </FormField>
                    <FormField label="Optional fee cap (₹)" hint="Maximum total due per school">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.school_fee_cap" type="number" min="0" class="field max-w-xs">
                        </template>
                    </FormField>
                </div>

                <div v-else-if="feeSettingsForm.fee_model === 'per_student'" class="space-y-3 border-t border-slate-100 pt-4">
                    <FormField label="Amount per participating student (₹)" hint="Counts unique students/teachers in approved registrations">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.per_student_amount" type="number" min="0" class="field max-w-xs">
                        </template>
                    </FormField>
                    <FormField label="Optional fee cap (₹)" hint="Maximum total due per school">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.school_fee_cap" type="number" min="0" class="field max-w-xs">
                        </template>
                    </FormField>
                </div>

                <div v-else-if="feeSettingsForm.fee_model === 'sports_composite'" class="space-y-4 border-t border-slate-100 pt-4">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-xs text-slate-700 space-y-2">
                        <p class="font-semibold text-slate-900">How billing works</p>
                        <ol class="list-decimal pl-4 space-y-1">
                            <li><strong>School registration</strong> — once per school</li>
                            <li><strong>Student registration</strong> — per student registered for the event</li>
                            <li><strong>Free quota</strong> — how many item entries each student gets within the student fee (set <strong>0</strong> to charge every item)</li>
                            <li><strong>Extra item fee</strong> — per item beyond the free quota (or every item when quota is 0)</li>
                        </ol>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <FormField label="School registration (₹)" hint="Once per school">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="feeSettingsForm.school_registration_flat" type="number" min="0" class="field" placeholder="2000">
                            </template>
                        </FormField>
                        <FormField label="Student registration (₹)" hint="Per student in this event">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="feeSettingsForm.per_student_amount" type="number" min="0" class="field" placeholder="300">
                            </template>
                        </FormField>
                        <FormField label="Free quota (items per student)" hint="0 = no free items; each item billed at extra item fee">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="feeSettingsForm.included_items_per_student" type="number" min="0" class="field" placeholder="2">
                            </template>
                        </FormField>
                        <FormField label="Extra item fee (₹)" hint="Per item beyond free quota (or every item when quota is 0)">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="feeSettingsForm.default_item_fee" type="number" min="0" class="field" placeholder="150">
                            </template>
                        </FormField>
                    </div>
                    <FormField label="Optional fee cap (₹)" hint="Maximum total due per school">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.school_fee_cap" type="number" min="0" class="field max-w-xs">
                        </template>
                    </FormField>
                </div>

                <p v-else-if="feeSettingsForm.fee_model === 'none'" class="text-sm text-slate-600 border-t border-slate-100 pt-4">
                    No fest fee is charged for this event. Schools can register without payment.
                </p>

                <div class="space-y-3 border-t border-slate-100 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Registration gates</p>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" v-model="feeSettingsForm.require_fee_before_registration" class="mt-0.5">
                        <span>
                            Require school fest fee verified before item registration
                            <span class="block text-xs text-slate-500 mt-0.5">Schools cannot register students for items until Sahodaya verifies their event fee payment.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" v-model="feeSettingsForm.require_verified_students" class="mt-0.5">
                        <span>
                            Require Sahodaya-verified students only
                            <span class="block text-xs text-slate-500 mt-0.5">Overrides cluster default for this event. Turn off to allow unverified students for this event's items.</span>
                        </span>
                    </label>
                </div>
            </section>

            <section v-if="event.event_type === 'sports' && !feeSettingsForm.head_fees.length && feeSettingsForm.fee_model !== 'sports_composite'" class="card space-y-3">
                <div>
                    <h3 class="section-title">{{ event.event_type === 'sports' ? 'Event Head fees' : 'Item head fees' }}</h3>
                    <p class="section-desc">Per-head default and extra item rates (Chess, Athletics, …).</p>
                </div>
                <p class="text-sm text-slate-600">
                    No Event Heads on this event yet.
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/competition`" class="link-brand font-semibold">
                        Open competition hub →
                    </Link>
                    then return here to set fees per head.
                </p>
            </section>

            <section v-else-if="feeSettingsForm.head_fees.length && feeSettingsForm.fee_model !== 'sports_composite'" class="card space-y-4">
                <div>
                    <h3 class="section-title">{{ event.event_type === 'sports' ? 'Event Head fees' : 'Item head fees' }}</h3>
                    <p class="section-desc">
                        Per-head rates for item registrations — <strong>Default</strong> applies to each billed item (or all items when included quota is 0);
                        <strong>Extra</strong> applies only to items beyond the included count when quota is greater than 0.
                        Per-item overrides on the event catalog take priority over head rates.
                    </p>
                </div>
                <div class="overflow-x-auto rounded-xl border border-slate-100">
                    <table class="data-table">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="text-left px-4 py-2.5 text-xs font-semibold text-slate-600">Head</th>
                                <th class="text-right px-4 py-2.5 text-xs font-semibold text-slate-600">Default item fee (₹)</th>
                                <th class="text-right px-4 py-2.5 text-xs font-semibold text-slate-600">Extra item fee (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="row in feeSettingsForm.head_fees" :key="row.id">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ row.name }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input v-model.number="row.default_item_fee" type="number" min="0"
                                           class="field w-full max-w-[8rem] ml-auto text-right" placeholder="—">
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input v-model.number="row.extra_item_fee" type="number" min="0"
                                           class="field w-full max-w-[8rem] ml-auto text-right" placeholder="—">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="feeSettingsForm.fee_model === 'sports_composite' && feeSettingsForm.head_fees.length" class="card space-y-4">
                <div>
                    <h3 class="section-title">Event Head billing</h3>
                    <p class="section-desc">
                        Each Event Head (Athletics, Chess, …) is billed and paid <strong>independently</strong> — a school can clear
                        Athletics while Chess is still pending. School/Student/Team fees below override the event-wide defaults above
                        once a head has its own rates set.
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-xs text-slate-700 space-y-1">
                    <p><strong>School fee</strong> — once per school, per head.</p>
                    <p><strong>Student fee</strong> — once per student registered under this head, added on top of item charges.</p>
                    <p><strong>Team fee</strong> — once per team entry (relay, group items), separate from the student fee and its own quota.</p>
                    <p><strong>Individual / Team quota</strong> — how many item entries / team entries per student are free before item/team fees apply (0 = none free).</p>
                    <p><strong>Approval</strong> — Auto approves registrations the moment this head's fee is fully paid; Manual requires a Sahodaya reviewer regardless of payment.</p>
                </div>
                <div class="space-y-4">
                    <div v-for="row in feeSettingsForm.head_fees" :key="row.id"
                         class="rounded-xl border border-slate-200 p-4 space-y-3">
                        <p class="font-semibold text-slate-900">{{ row.name }}</p>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <FormField label="School fee (₹)" hint="Once per school">
                                <template #default="{ id }">
                                    <input :id="id" v-model.number="row.school_registration_fee" type="number" min="0" class="field" placeholder="—">
                                </template>
                            </FormField>
                            <FormField label="Student fee (₹)" hint="Per student under this head">
                                <template #default="{ id }">
                                    <input :id="id" v-model.number="row.student_registration_fee" type="number" min="0" class="field" placeholder="—">
                                </template>
                            </FormField>
                            <FormField label="Team fee (₹)" hint="Per team entry">
                                <template #default="{ id }">
                                    <input :id="id" v-model.number="row.team_registration_fee" type="number" min="0" class="field" placeholder="—">
                                </template>
                            </FormField>
                            <FormField label="Individual quota" hint="Free item entries per student">
                                <template #default="{ id }">
                                    <input :id="id" v-model.number="row.included_items_per_student" type="number" min="0" class="field" placeholder="0">
                                </template>
                            </FormField>
                            <FormField label="Team quota" hint="Free team entries per student">
                                <template #default="{ id }">
                                    <input :id="id" v-model.number="row.included_teams" type="number" min="0" class="field" placeholder="0">
                                </template>
                            </FormField>
                            <FormField label="Verification policy">
                                <template #default="{ id }">
                                    <select :id="id" v-model="row.verification_policy" class="field">
                                        <option value="all_students">All students</option>
                                        <option value="verified_only">Verified students only</option>
                                    </select>
                                </template>
                            </FormField>
                            <FormField label="Approval policy">
                                <template #default="{ id }">
                                    <select :id="id" v-model="row.approval_policy" class="field">
                                        <option value="auto">Auto (on full payment)</option>
                                        <option value="manual">Manual review</option>
                                    </select>
                                </template>
                            </FormField>
                            <FormField label="Max participants" hint="Leave blank for no cap">
                                <template #default="{ id }">
                                    <input :id="id" v-model.number="row.max_participants" type="number" min="0" class="field" placeholder="—">
                                </template>
                            </FormField>
                            <FormField label="Max teams" hint="Leave blank for no cap">
                                <template #default="{ id }">
                                    <input :id="id" v-model.number="row.max_teams" type="number" min="0" class="field" placeholder="—">
                                </template>
                            </FormField>
                        </div>
                    </div>
                </div>
            </section>

            <section v-else-if="feeSettingsForm.fee_model === 'sports_composite'" class="card space-y-3">
                <div>
                    <h3 class="section-title">Event Head billing</h3>
                    <p class="section-desc">Per-head School/Student/Team fees, quotas and approval policy (Chess, Athletics, …).</p>
                </div>
                <p class="text-sm text-slate-600">
                    No Event Heads on this event yet.
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/competition`" class="link-brand font-semibold">
                        Open competition hub →
                    </Link>
                    then return here to set fees per head.
                </p>
            </section>

            <section v-if="feeSettingsForm.fee_model === 'item_catalog'" class="card space-y-4">
                <div>
                    <h3 class="section-title">Category rates</h3>
                    <p class="section-desc">Default fee applied when an item has no per-item override.</p>
                </div>

                <div v-if="event.event_type !== 'sports'">
                    <FormField label="Class category scheme">
                        <template #default="{ id }">
                            <select :id="id" v-model="feeSettingsForm.class_group_scheme" class="field mt-1">
                                <option value="">Use Sahodaya default</option>
                                <option v-for="(label, key) in classGroupSchemeOptions" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </template>
                    </FormField>
                    <p class="text-xs text-slate-500 mt-1">
                        Active scheme: <strong>{{ classGroupSchemeOptions[classGroupScheme] ?? classGroupScheme }}</strong>
                    </p>
                </div>

                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" v-model="feeSettingsForm.include_school_registration" class="mt-0.5">
                    <span>
                        Add optional one-time school registration line on the event invoice
                        <span class="block text-xs text-slate-500 mt-0.5">Annual Sahodaya membership is separate — only enable for an extra event-specific registration charge.</span>
                    </span>
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="feeSettingsForm.charge_standbys">
                    Charge standby participants (uses default / category rate per standby)
                </label>

                <FormField label="Optional fee cap (₹)" hint="Maximum total due per school">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="feeSettingsForm.school_fee_cap" type="number" min="0" class="field max-w-xs">
                    </template>
                </FormField>

                <div v-if="feeSettingsForm.include_school_registration" class="grid gap-3 sm:grid-cols-2">
                    <FormField label="Secondary registration (₹)">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.school_registration.secondary" type="number" min="0" class="field">
                        </template>
                    </FormField>
                    <FormField label="Senior secondary (₹)">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.school_registration.senior_secondary" type="number" min="0" class="field">
                        </template>
                    </FormField>
                </div>

                <div v-if="event.event_type === 'sports'">
                    <p class="form-label mb-2">Fees by age group</p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <FormField v-for="(label, key) in ageGroupLabels" :key="key" :label="label">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="feeSettingsForm.age_group_fees[key]" type="number" min="0"
                                       class="field" :placeholder="placeholderAmount(defaultAgeGroupFees[key])">
                            </template>
                        </FormField>
                    </div>
                </div>

                <div v-else>
                    <p class="form-label mb-2">Fees by class category</p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <FormField v-for="(label, key) in effectiveClassGroupLabels" :key="key" :label="label">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="feeSettingsForm.class_group_fees[key]" type="number" min="0"
                                       class="field" :placeholder="placeholderAmount(defaultClassGroupFees[key])">
                            </template>
                        </FormField>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 border-t border-slate-100 pt-4">
                    <FormField label="Group item fee">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.participant_type_fees.group" type="number" min="0"
                                   class="field" placeholder="₹150">
                        </template>
                    </FormField>
                    <FormField label="Team item fee">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.participant_type_fees.team" type="number" min="0"
                                   class="field" placeholder="₹150">
                        </template>
                    </FormField>
                    <FormField label="Default fallback" hint="When no category matches">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.default_item_fee" type="number" min="0"
                                   class="field" placeholder="₹200">
                        </template>
                    </FormField>
                </div>
            </section>

            <section v-if="feeSettingsForm.fee_model === 'item_catalog' && feeSettingsForm.item_fees.length" class="card space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="section-title">Per-item overrides</h3>
                        <p class="section-desc">
                            Leave blank to use the category rate.
                            <span class="text-slate-600">{{ overrideCount }} custom override{{ overrideCount === 1 ? '' : 's' }} set.</span>
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 items-center">
                    <input v-model="itemSearch" type="search" class="field flex-1 min-w-[12rem] max-w-md"
                           placeholder="Search items…" autocomplete="off">
                    <select v-model="itemFilter" class="field w-44">
                        <option value="all">All items</option>
                        <option value="override">Overrides only</option>
                        <option value="category">Using category rate</option>
                    </select>
                    <button v-if="itemSearch.trim() || itemFilter !== 'all'" type="button" class="btn-secondary text-sm"
                            @click="itemSearch = ''; itemFilter = 'all'">
                        Clear
                    </button>
                </div>

                <p class="text-xs text-slate-500">
                    <template v-if="itemSearch.trim() || itemFilter !== 'all'">
                        Showing {{ filteredItemFees.length }} of {{ feeSettingsForm.item_fees.length }} items
                    </template>
                    <template v-else>
                        {{ feeSettingsForm.item_fees.length }} items
                    </template>
                </p>

                <EmptyState v-if="!filteredItemFees.length" title="No matches"
                            description="Try another search term or filter." icon="🔍" class="py-8" />

                <div v-else class="form-section overflow-hidden !p-0">
                    <div class="max-h-[28rem] overflow-y-auto overflow-x-auto">
                        <table class="data-table">
                            <thead class="sticky top-0 z-10 bg-white shadow-[0_1px_0_0_#e2e8f0]">
                                <tr>
                                    <th>Item</th>
                                    <th class="w-36">Category rate</th>
                                    <th class="w-40 text-right">Override (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in filteredItemFees" :key="row.id"
                                    :class="hasOverride(row) ? 'bg-amber-50/40' : ''">
                                    <td>
                                        <p class="font-medium text-slate-900">{{ row.title }}</p>
                                        <p class="text-xs text-slate-400 mt-0.5">{{ itemMeta(row) }}</p>
                                    </td>
                                    <td>
                                        <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">
                                            {{ formatAmount(categoryRateForRow(row)) }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <input v-model.number="row.fee_amount" type="number" min="0"
                                               class="field w-full max-w-[8rem] ml-auto text-right"
                                               :placeholder="formatAmount(categoryRateForRow(row), true)">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="card space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="section-title">Ledger account</h3>
                        <p class="section-desc">Income from verified school fees posts to this account head.</p>
                    </div>
                    <Link v-if="ledgerAccount?.ledger_url" :href="ledgerAccount.ledger_url" class="btn-secondary text-sm shrink-0">View ledger →</Link>
                </div>
                <p class="text-xs font-mono text-slate-500">{{ ledgerAccount?.code }}</p>
                <form @submit.prevent="saveLedgerAccount" class="flex flex-wrap gap-2 items-end">
                    <FormField label="Account name" class-extra="mb-0 flex-1 min-w-[14rem]">
                        <template #default="{ id }">
                            <input :id="id" v-model="ledgerForm.name" class="field" required>
                        </template>
                    </FormField>
                    <button type="submit" class="btn-secondary text-sm mb-0.5" :disabled="ledgerForm.processing">Save account name</button>
                </form>
            </section>

            <FormActions sticky>
                <button type="submit" class="btn-primary" :disabled="feeSettingsForm.processing">
                    {{ feeSettingsForm.processing ? 'Saving…' : 'Save fee settings' }}
                </button>
            </FormActions>
        </form>
    </div>
</template>

<script setup>
import { computed, inject, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';

const {
    feeSettingsForm, feeModels, event, classGroupSchemeOptions, classGroupScheme,
    ageGroupLabels, defaultAgeGroupFees, defaultClassGroupFees, effectiveClassGroupLabels, saveFeeSettings,
    sahodaya, ledgerAccount,
} = inject('eventSettings');

if (event.event_type === 'sports') {
    feeSettingsForm.fee_model = 'sports_composite';
}

const ledgerForm = useForm({ name: ledgerAccount?.name ?? '' });

function saveLedgerAccount() {
    ledgerForm.put(`/sahodaya-admin/${sahodaya.id}/events/${event.id}/ledger-account`, { preserveScroll: true });
}

const itemSearch = ref('');
const itemFilter = ref('all');

const overrideCount = computed(() =>
    feeSettingsForm.item_fees.filter((row) => hasOverride(row)).length,
);

const filteredItemFees = computed(() => {
    const q = itemSearch.value.trim().toLowerCase();
    const terms = q.split(/\s+/).filter(Boolean);

    return feeSettingsForm.item_fees.filter((row) => {
        if (itemFilter.value === 'override' && !hasOverride(row)) {
            return false;
        }
        if (itemFilter.value === 'category' && hasOverride(row)) {
            return false;
        }

        if (!terms.length) {
            return true;
        }

        const haystack = [row.title, row.item_code, itemMeta(row)].filter(Boolean).join(' ').toLowerCase();
        return terms.every((term) => haystack.includes(term));
    });
});

function hasOverride(row) {
    return row.fee_amount !== '' && row.fee_amount != null;
}

function placeholderAmount(value) {
    return value != null && value !== '' ? `₹${value}` : '₹';
}

function formatAmount(value, asPlaceholder = false) {
    if (value == null || value === '') {
        return asPlaceholder ? 'Category rate' : '—';
    }
    return `₹${value}`;
}

function categoryRateForRow(row) {
    if (event.event_type === 'sports') {
        const ageFee = row.age_group ? feeSettingsForm.age_group_fees[row.age_group] : null;
        if (ageFee !== '' && ageFee != null) {
            return ageFee;
        }
    } else if (row.class_group) {
        const classFee = feeSettingsForm.class_group_fees[row.class_group];
        if (classFee !== '' && classFee != null) {
            return classFee;
        }
    }

    if (row.participant_type === 'group') {
        const groupFee = feeSettingsForm.participant_type_fees.group;
        if (groupFee !== '' && groupFee != null) {
            return groupFee;
        }
    }
    if (row.participant_type === 'team') {
        const teamFee = feeSettingsForm.participant_type_fees.team;
        if (teamFee !== '' && teamFee != null) {
            return teamFee;
        }
    }

    const fallback = feeSettingsForm.default_item_fee;
    if (fallback !== '' && fallback != null) {
        return fallback;
    }

    if (event.event_type === 'sports' && row.age_group) {
        return defaultAgeGroupFees[row.age_group] ?? null;
    }
    if (row.class_group) {
        return defaultClassGroupFees[row.class_group] ?? null;
    }

    return null;
}

function itemMeta(row) {
    const parts = [];
    if (row.item_code) {
        parts.push(row.item_code);
    }
    if (event.event_type === 'sports' && row.age_group) {
        parts.push(ageGroupLabels[row.age_group] ?? row.age_group);
    } else if (row.class_group) {
        parts.push(effectiveClassGroupLabels.value[row.class_group] ?? row.class_group);
    }
    if (row.participant_type && row.participant_type !== 'individual') {
        parts.push(row.participant_type);
    }
    return parts.join(' · ') || 'Individual';
}
</script>
