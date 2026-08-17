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
                This sport event bills school / student / team fees for all items under it.
                Set rates in the Sport event billing section below.
            </p>
        </div>

        <form @submit.prevent="saveFeeSettings" class="space-y-6">
            <section class="card space-y-4">
                <div>
                    <h3 class="section-title">{{ event.event_type === 'sports' ? 'Event-wide fee override (optional)' : 'Fee model' }}</h3>
                    <p class="section-desc">
                        <template v-if="event.event_type === 'sports'">
                            Billing is always Sports composite. Set Sport event billing rates below.
                        </template>
                        <template v-else>
                            How schools are billed for registrations in this event.
                        </template>
                    </p>
                </div>

                <FormField v-if="event.event_type !== 'sports'" label="Billing model">
                    <template #default="{ id }">
                        <select :id="id" v-model="feeSettingsForm.fee_model" class="field mt-1">
                            <option v-for="(label, key) in feeModels" :key="key" :value="key">{{ billingModelLabel(key, label) }}</option>
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
                    <FormField label="Minimum school fee floor (₹)" hint="Minimum total fee charged per school once registered (e.g. ₹1,500 for sports)">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.school_fee_min" type="number" min="0" class="field" placeholder="1500">
                        </template>
                    </FormField>
                    <p class="sm:col-span-2 text-sm text-slate-600">
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/competition`" class="link-brand font-semibold">
                            Edit head fees on Competition →
                        </Link>
                    </p>

                    <div class="sm:col-span-2 border-t border-slate-100 pt-4 space-y-2">
                        <h4 class="text-sm font-semibold text-slate-800">Group/team item per-participant surcharge (optional)</h4>
                        <p class="text-xs text-slate-500">
                            When set, a team item bills <strong>flat fee + (rate × actual participant count)</strong>
                            instead of a single flat team fee — e.g. ₹250 flat + ₹100 × 7 members = ₹950.
                            Leave both blank to keep billing team items at the flat team registration fee (Competition → head fees).
                        </p>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" v-model="feeSettingsForm.charge_standbys">
                            Count standby participants toward this surcharge
                        </label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <FormField label="Flat event fee (₹)">
                                <template #default="{ id }">
                                    <input :id="id" v-model.number="feeSettingsForm.group_item_flat_fee" type="number" min="0"
                                           class="field" placeholder="₹0">
                                </template>
                            </FormField>
                            <FormField label="Per-participant rate (₹)">
                                <template #default="{ id }">
                                    <input :id="id" v-model.number="feeSettingsForm.group_item_per_participant_rate" type="number" min="0"
                                           class="field" placeholder="₹0">
                                </template>
                            </FormField>
                        </div>
                    </div>
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
                    <div v-if="feeSettingsForm.include_school_registration" class="space-y-2">
                        <div v-for="tier in Object.keys(feeSettingsForm.school_registration)" :key="tier" class="flex items-end gap-2">
                            <FormField :label="schoolRegistrationTierLabel(tier)" class-extra="flex-1 mb-0">
                                <template #default="{ id }">
                                    <input :id="id" v-model.number="feeSettingsForm.school_registration[tier]" type="number" min="0" class="field">
                                </template>
                            </FormField>
                            <button type="button" class="text-xs text-red-400 hover:text-red-600 mb-2.5" @click="removeSchoolRegistrationTier(tier)">Remove</button>
                        </div>
                        <div class="flex items-end gap-2">
                            <FormField label="Add tier" hint="e.g. secondary, senior_secondary, other" class-extra="flex-1 mb-0 max-w-xs">
                                <template #default="{ id }">
                                    <input :id="id" v-model="newSchoolRegistrationTierKey" type="text" class="field" placeholder="tier key">
                                </template>
                            </FormField>
                            <button type="button" class="btn-secondary text-xs mb-2.5" @click="addSchoolRegistrationTier">+ Add tier</button>
                        </div>
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

                <div v-else-if="feeSettingsForm.fee_model === 'student_count_slab'" class="space-y-3 border-t border-slate-100 pt-4">
                    <p class="text-xs text-slate-600">
                        Bills each school a single stepped amount based on its total registered student count for
                        this event. Slabs are scoped to this event only — configure them below.
                    </p>
                    <div class="overflow-x-auto rounded-xl border border-slate-100">
                        <table class="data-table">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="text-left px-4 py-2.5 text-xs font-semibold text-slate-600">Min students</th>
                                    <th class="text-left px-4 py-2.5 text-xs font-semibold text-slate-600">Max students</th>
                                    <th class="text-right px-4 py-2.5 text-xs font-semibold text-slate-600">Amount (₹)</th>
                                    <th class="px-4 py-2.5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="(slab, index) in feeSettingsForm.student_count_slabs" :key="index">
                                    <td class="px-4 py-2">
                                        <input v-model.number="slab.min_count" type="number" min="0" class="field w-24">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input v-model.number="slab.max_count" type="number" min="0" class="field w-24" placeholder="∞">
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <input v-model.number="slab.amount" type="number" min="0" class="field w-32 ml-auto text-right">
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <button type="button" class="text-xs text-red-400 hover:text-red-600" @click="removeStudentCountSlab(index)">Remove</button>
                                    </td>
                                </tr>
                                <tr v-if="!feeSettingsForm.student_count_slabs.length">
                                    <td colspan="4" class="px-4 py-3 text-sm text-slate-500">No slabs yet — add one below.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn-secondary text-sm" @click="addStudentCountSlab">+ Add slab</button>
                    <FormField label="Optional fee cap (₹)" hint="Maximum total due per school">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.school_fee_cap" type="number" min="0" class="field max-w-xs">
                        </template>
                    </FormField>
                </div>

                <!-- This branch only ever renders for a NON-sports event whose billing model was manually set to
                     "Sports composite" from the dropdown above — a real sports event (event_type === 'sports')
                     never reaches this far down the chain; it resolves at the very first v-if on line 43.
                     For that non-sports case, these fields (not "Sport event billing" below, which is sports-only —
                     see the gate added there) are what updateFeeSettings() actually persists via
                     normalizeEventFeeSettings(), so this block must stay intact. -->
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

            <!-- Sports-only: updateFeeSettings() only writes sport_event_fees.* to the event's composite fee
                 columns when event.event_type === 'sports'. Without this gate, a non-sports event with
                 fee_model = sports_composite (e.g. an arts/language fest that picked this billing model) showed
                 this whole section, but anything entered here was silently discarded on save — the fields above
                 in the sports_composite block are what actually persist for that case. -->
            <section v-if="feeSettingsForm.fee_model === 'sports_composite' && event.event_type === 'sports'" class="card space-y-4">
                <div>
                    <h3 class="section-title">Sport event billing</h3>
                    <p class="section-desc">
                        School, student, item, and team fee configuration for this event.
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-700 space-y-2">
                    <p class="font-bold text-slate-900 flex items-center gap-1 text-sm">
                        <span>💡</span> Fee Calculation Reference Guide:
                    </p>
                    <ul class="list-disc pl-4 space-y-1 text-slate-600">
                        <li><strong>School fee:</strong> One-time institutional participation fee charged once per school for this event.</li>
                        <li><strong>Student fee:</strong> Base fee charged once per registered student (added on top of item fees). <em>Leave blank if charging per item entry instead.</em></li>
                        <li><strong>Team fee:</strong> Fee charged per team entry (relay, group, or squad competitions).</li>
                        <li><strong>Default item fee:</strong> Standard fee charged for each individual item entry (e.g. ₹350 per item).</li>
                        <li><strong>Extra item fee:</strong> Fee charged for items beyond the student's included free quota.</li>
                        <li><strong>Individual / Team quota:</strong> Number of free item or team entries included before fees apply (0 = no free entries).</li>
                    </ul>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <FormField label="School fee (₹)" hint="One-time institutional participation fee charged once per school for this event (e.g. ₹4,000).">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.sport_event_fees.school_registration_fee" type="number" min="0" class="field" placeholder="e.g. 4000">
                        </template>
                    </FormField>
                    <FormField label="Student fee (₹)" hint="Base fee charged once per student registered (leave blank if charging per-item instead).">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.sport_event_fees.student_registration_fee" type="number" min="0" class="field" placeholder="—">
                        </template>
                    </FormField>
                    <FormField label="Team fee (₹)" hint="Fee charged per team entry (relay, group, or squad competitions).">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.sport_event_fees.team_registration_fee" type="number" min="0" class="field" placeholder="—">
                        </template>
                    </FormField>
                    <FormField label="Default item fee (₹)" hint="Standard fee charged for each individual item entry (e.g. ₹350 per item).">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.sport_event_fees.default_item_fee" type="number" min="0" class="field" placeholder="e.g. 350">
                        </template>
                    </FormField>
                    <FormField label="Extra item fee (₹)" hint="Fee charged for additional items beyond the student's free individual quota.">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.sport_event_fees.extra_item_fee" type="number" min="0" class="field" placeholder="—">
                        </template>
                    </FormField>
                    <FormField label="Individual quota" hint="Number of free item entries included per student before item fees apply (0 = no free items).">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.sport_event_fees.included_items_per_student" type="number" min="0" class="field" placeholder="0">
                        </template>
                    </FormField>
                    <FormField label="Team quota" hint="Number of free team entries included per school before team fees apply (0 = no free teams).">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.sport_event_fees.included_teams" type="number" min="0" class="field" placeholder="0">
                        </template>
                    </FormField>
                    <FormField label="Verification policy" hint="Require Sahodaya-verified students only or allow all student registrations.">
                        <template #default="{ id }">
                            <select :id="id" v-model="feeSettingsForm.sport_event_fees.verification_policy" class="field">
                                <option value="all_students">All students</option>
                                <option value="verified_only">Verified students only</option>
                            </select>
                        </template>
                    </FormField>
                    <FormField label="Approval policy" hint="Auto-approve registrations on full payment or require manual admin review.">
                        <template #default="{ id }">
                            <select :id="id" v-model="feeSettingsForm.sport_event_fees.approval_policy" class="field">
                                <option value="auto">Auto (on full payment)</option>
                                <option value="manual">Manual review</option>
                            </select>
                        </template>
                    </FormField>
                    <FormField label="Max participants" hint="Maximum individual entries allowed per school, per item (each item like U17_BOYS/U19_BOYS gets its own quota — leave blank for no limit).">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.sport_event_fees.max_participants" type="number" min="0" class="field" placeholder="Leave blank for no cap">
                        </template>
                    </FormField>
                    <FormField label="Max teams" hint="Maximum team entries allowed per school, per item (each item like U17_TEAM_BOYS/U19_TEAM_BOYS gets its own quota — leave blank for no limit).">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="feeSettingsForm.sport_event_fees.max_teams" type="number" min="0" class="field" placeholder="Leave blank for no cap">
                        </template>
                    </FormField>
                </div>
            </section>

            <!-- Class Category Schemes — named, Sahodaya-wide category setups (e.g. "CBSE
                 Kerala", "English Fest") shared across every event, picked here by id.
                 Superseded the old fixed cbse/sahodaya/cluster/custom choices. -->
            <section v-if="event.event_type !== 'sports'" class="card space-y-4">
                <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 space-y-3">
                    <div class="border-b border-indigo-100 pb-2.5">
                        <h4 class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                            <span>🏷️</span> Event Competition Categories &amp; Linked Classes
                        </h4>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            Pick a named category scheme for this event, or create a new one below — e.g. "Category I–IV", "English Fest".
                        </p>
                    </div>

                    <FormField label="Class Category Scheme">
                        <template #default="{ id }">
                            <select :id="id" v-model="feeSettingsForm.class_group_scheme" class="field bg-white mt-1 font-medium">
                                <option value="">Use Sahodaya Default Scheme</option>
                                <option v-for="scheme in classCategorySchemes" :key="scheme.id" :value="String(scheme.id)">
                                    {{ scheme.name }}{{ scheme.is_default ? ' (Sahodaya default)' : '' }}
                                </option>
                            </select>
                        </template>
                    </FormField>

                    <!-- Active Category -> Linked Class List — reflects what's actually saved
                         for this event (server-computed), not just what's selected above. -->
                    <div v-if="Object.keys(classGroupLabels ?? {}).length" class="space-y-1.5 pt-1">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Active Category &amp; Class Mappings</p>
                        <div class="grid sm:grid-cols-2 gap-2 text-xs">
                            <div v-for="(label, key) in classGroupLabels" :key="key"
                                 class="p-2.5 rounded-lg border border-white bg-white/80 shadow-xs flex items-center justify-between">
                                <span class="font-semibold text-slate-800">{{ label }}</span>
                                <span class="font-mono text-[10px] uppercase px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-bold">{{ key }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-indigo-100 pt-3 space-y-3">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Manage Category Schemes</p>

                        <!-- Create a brand new named scheme, reusable by any event in this Sahodaya -->
                        <form @submit.prevent="addClassCategoryScheme"
                              class="bg-white/80 p-3 rounded-lg border border-indigo-100 flex flex-wrap gap-2 items-end">
                            <FormField label="New scheme name" :error="classCategorySchemeForm.errors.name" class-extra="mb-0 flex-1 min-w-[10rem]">
                                <template #default="{ id }">
                                    <input :id="id" v-model="classCategorySchemeForm.name" class="field text-xs" placeholder="e.g. English Fest" required>
                                </template>
                            </FormField>
                            <FormField label="Description (optional)" class-extra="mb-0 flex-1 min-w-[10rem]">
                                <template #default="{ id }">
                                    <input :id="id" v-model="classCategorySchemeForm.description" class="field text-xs" placeholder="Internal note">
                                </template>
                            </FormField>
                            <button type="submit" class="btn-secondary text-xs !py-1 !px-3" :disabled="classCategorySchemeForm.processing">
                                Create scheme
                            </button>
                        </form>

                        <!-- Categories within whichever scheme is selected above -->
                        <div v-if="selectedScheme" class="bg-white/80 p-3 rounded-lg border border-indigo-100 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold text-slate-800">Categories in "{{ selectedScheme.name }}"</p>
                                <button type="button" @click="confirmDeleteScheme(selectedScheme)" class="text-red-600 text-[11px] shrink-0">
                                    Delete this scheme
                                </button>
                            </div>

                            <form @submit.prevent="addClassCategorySchemeGroup(selectedScheme.id)" class="space-y-2.5">
                                <div class="grid gap-2.5 sm:grid-cols-3">
                                    <FormField label="Key" hint="Letters/numbers/dashes only — no spaces" :error="schemeGroupForm.errors.key" class-extra="mb-0">
                                        <template #default="{ id }">
                                            <input :id="id" v-model="schemeGroupForm.key" class="field text-xs" placeholder="e.g. junior" required>
                                        </template>
                                    </FormField>
                                    <FormField label="Display label" :error="schemeGroupForm.errors.label" class-extra="mb-0">
                                        <template #default="{ id }">
                                            <input :id="id" v-model="schemeGroupForm.label" class="field text-xs" placeholder="e.g. Junior" required>
                                        </template>
                                    </FormField>
                                    <FormField label="Description (optional)" :error="schemeGroupForm.errors.description" class-extra="mb-0">
                                        <template #default="{ id }">
                                            <input :id="id" v-model="schemeGroupForm.description" class="field text-xs" placeholder="Internal note">
                                        </template>
                                    </FormField>
                                </div>
                                <div>
                                    <p class="form-label text-xs mb-1">Classes in this category</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <label v-for="n in 12" :key="n"
                                               class="inline-flex items-center gap-1 px-2 py-1 rounded-md border text-xs cursor-pointer select-none"
                                               :class="schemeGroupForm.classes.includes(n)
                                                   ? 'bg-indigo-600 border-indigo-600 text-white font-semibold'
                                                   : 'bg-white border-slate-200 text-slate-600 hover:border-indigo-300'">
                                            <input type="checkbox" class="hidden" :value="n" v-model="schemeGroupForm.classes">
                                            Class {{ n }}
                                        </label>
                                    </div>
                                    <p class="text-[11px] text-slate-400 mt-1">
                                        A class left unassigned to any category in this scheme falls back to "Open / All Categories".
                                    </p>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="btn-secondary text-xs !py-1 !px-3" :disabled="schemeGroupForm.processing">Add category</button>
                                </div>
                            </form>

                            <div v-if="selectedScheme.groups?.length" class="rounded-lg border border-indigo-100 overflow-hidden bg-white">
                                <ul class="divide-y divide-slate-100 text-xs">
                                    <li v-for="g in selectedScheme.groups" :key="g.id" class="p-2.5">
                                        <!-- Inline edit mode -->
                                        <form v-if="editingSchemeGroupId === g.id" @submit.prevent="saveSchemeGroupEdit(selectedScheme.id)" class="space-y-2.5">
                                            <div class="grid gap-2.5 sm:grid-cols-3">
                                                <FormField label="Key" hint="Letters/numbers/dashes only — no spaces" :error="schemeGroupEditForm.errors.key" class-extra="mb-0">
                                                    <template #default="{ id }">
                                                        <input :id="id" v-model="schemeGroupEditForm.key" class="field text-xs" required>
                                                    </template>
                                                </FormField>
                                                <FormField label="Display label" :error="schemeGroupEditForm.errors.label" class-extra="mb-0">
                                                    <template #default="{ id }">
                                                        <input :id="id" v-model="schemeGroupEditForm.label" class="field text-xs" required>
                                                    </template>
                                                </FormField>
                                                <FormField label="Description (optional)" :error="schemeGroupEditForm.errors.description" class-extra="mb-0">
                                                    <template #default="{ id }">
                                                        <input :id="id" v-model="schemeGroupEditForm.description" class="field text-xs">
                                                    </template>
                                                </FormField>
                                            </div>
                                            <div>
                                                <p class="form-label text-xs mb-1">Classes in this category</p>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <label v-for="n in 12" :key="n"
                                                           class="inline-flex items-center gap-1 px-2 py-1 rounded-md border text-xs cursor-pointer select-none"
                                                           :class="schemeGroupEditForm.classes.includes(n)
                                                               ? 'bg-indigo-600 border-indigo-600 text-white font-semibold'
                                                               : 'bg-white border-slate-200 text-slate-600 hover:border-indigo-300'">
                                                        <input type="checkbox" class="hidden" :value="n" v-model="schemeGroupEditForm.classes">
                                                        Class {{ n }}
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="flex justify-end gap-2">
                                                <button type="button" @click="cancelEditSchemeGroup" class="btn-secondary text-xs !py-1 !px-3">Cancel</button>
                                                <button type="submit" class="btn-primary text-xs !py-1 !px-3" :disabled="schemeGroupEditForm.processing">Save</button>
                                            </div>
                                        </form>

                                        <!-- Display mode -->
                                        <div v-else class="flex items-center justify-between">
                                            <div>
                                                <span class="font-semibold text-slate-900">{{ g.label }}</span>
                                                <span class="font-mono text-[10px] text-slate-400 ml-1">({{ g.key }})</span>
                                                <p class="text-slate-500">
                                                    {{ g.classes?.length ? `Classes ${[...g.classes].sort((a, b) => a - b).join(', ')}` : 'No classes assigned yet' }}
                                                </p>
                                                <p v-if="g.description" class="text-slate-400">{{ g.description }}</p>
                                            </div>
                                            <div class="flex items-center gap-3 shrink-0">
                                                <button type="button" @click="startEditSchemeGroup(g)" class="text-slate-600 text-xs">Edit</button>
                                                <button type="button" @click="confirmDeleteSchemeGroup(selectedScheme, g)" class="text-red-600 text-xs">Remove</button>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            <p v-else class="text-xs text-slate-400">No categories in this scheme yet.</p>
                        </div>
                        <p v-else class="text-xs text-slate-400">Pick a scheme above (or create one) to manage its categories.</p>
                    </div>
                </div>
            </section>

            <section v-if="feeSettingsForm.fee_model === 'item_catalog'" class="card space-y-4">
                <div>
                    <h3 class="section-title">Category rates</h3>
                    <p class="section-desc">Default fee applied when an item has no per-item override.</p>
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

                <FormField v-if="feeSettingsForm.charge_standbys" label="Team standby fee (₹)"
                           hint="Charged per standby in a team/group item, instead of the full team fee. Leave blank to not charge team standbys at all.">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="feeSettingsForm.team_standby_fee_amount" type="number" min="0"
                               class="field max-w-xs" placeholder="₹0 (not charged)">
                    </template>
                </FormField>

                <div class="border-t border-slate-100 pt-4 space-y-2">
                    <h4 class="text-sm font-semibold text-slate-800">Group/team item per-participant surcharge (optional)</h4>
                    <p class="text-xs text-slate-500">
                        When set, a group/team item bills <strong>flat fee + (rate × actual participant count)</strong>
                        instead of a single flat team fee — e.g. ₹250 flat + ₹100 × 7 members = ₹950.
                        Leave both blank to keep billing group/team items at the flat category rate above.
                        "Charge standby participants" above also decides whether standbys count toward this participant total.
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <FormField label="Flat event fee (₹)">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="feeSettingsForm.group_item_flat_fee" type="number" min="0"
                                       class="field" placeholder="₹0">
                            </template>
                        </FormField>
                        <FormField label="Per-participant rate (₹)">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="feeSettingsForm.group_item_per_participant_rate" type="number" min="0"
                                       class="field" placeholder="₹0">
                            </template>
                        </FormField>
                    </div>
                </div>

                <FormField label="Optional fee cap (₹)" hint="Maximum total due per school">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="feeSettingsForm.school_fee_cap" type="number" min="0" class="field max-w-xs">
                    </template>
                </FormField>

                <div v-if="feeSettingsForm.include_school_registration" class="space-y-2">
                    <div v-for="tier in Object.keys(feeSettingsForm.school_registration)" :key="tier" class="flex items-end gap-2">
                        <FormField :label="schoolRegistrationTierLabel(tier)" class-extra="flex-1 mb-0">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="feeSettingsForm.school_registration[tier]" type="number" min="0" class="field">
                            </template>
                        </FormField>
                        <button type="button" class="text-xs text-red-400 hover:text-red-600 mb-2.5" @click="removeSchoolRegistrationTier(tier)">Remove</button>
                    </div>
                    <div class="flex items-end gap-2">
                        <FormField label="Add tier" hint="e.g. secondary, senior_secondary, other" class-extra="flex-1 mb-0 max-w-xs">
                            <template #default="{ id }">
                                <input :id="id" v-model="newSchoolRegistrationTierKey" type="text" class="field" placeholder="tier key">
                            </template>
                        </FormField>
                        <button type="button" class="btn-secondary text-xs mb-2.5" @click="addSchoolRegistrationTier">+ Add tier</button>
                    </div>
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

            <section v-if="(feeSettingsForm.fee_model === 'item_catalog' || feeSettingsForm.fee_model === 'sports_composite') && feeSettingsForm.item_fees.length" class="card space-y-4">
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
                                    <th class="w-40 text-right">Group/team flat fee (₹)</th>
                                    <th class="w-40 text-right">Per-participant rate (₹)</th>
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
                                    <td class="text-right">
                                        <input v-if="row.participant_type === 'group' || row.participant_type === 'team'"
                                               v-model.number="row.group_item_flat_fee" type="number" min="0"
                                               class="field w-full max-w-[8rem] ml-auto text-right" placeholder="₹0">
                                        <span v-else class="text-xs text-slate-300">—</span>
                                    </td>
                                    <td class="text-right">
                                        <input v-if="row.participant_type === 'group' || row.participant_type === 'team'"
                                               v-model.number="row.group_item_per_participant_rate" type="number" min="0"
                                               class="field w-full max-w-[8rem] ml-auto text-right" placeholder="₹0">
                                        <span v-else class="text-xs text-slate-300">—</span>
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
import { useConfirm } from '@/composables/useConfirm';

const {
    feeSettingsForm, feeModels, event, classGroupLabels,
    ageGroupLabels, defaultAgeGroupFees, defaultClassGroupFees, effectiveClassGroupLabels, saveFeeSettings,
    sahodaya, ledgerAccount, classCategorySchemes,
    classCategorySchemeForm, schemeGroupForm,
    addClassCategoryScheme, removeClassCategoryScheme,
    addClassCategorySchemeGroup, removeClassCategorySchemeGroup,
    editingSchemeGroupId, schemeGroupEditForm,
    startEditSchemeGroup, cancelEditSchemeGroup, saveSchemeGroupEdit,
} = inject('eventSettings');

const { confirm } = useConfirm();

if (event.event_type === 'sports') {
    feeSettingsForm.fee_model = 'sports_composite';
}

// The scheme currently selected in the dropdown (by id) — drives both the "Manage
// Category Schemes" editor below and, via effectiveClassGroupLabels in the composable,
// the live "Fees by class category" preview as the admin switches schemes before saving.
//
// classCategorySchemes comes from inject('eventSettings'), which is built in Settings.vue
// as `{ ...toRefs(props), ...ctx }` — every key sourced from props (this one included) is
// therefore a real Vue Ref, not the plain array itself. Template expressions elsewhere in
// this file auto-unwrap refs like this transparently, but plain script code (like this
// computed's callback) does not get that treatment — calling `.find()` directly on the Ref
// throws "TypeError: ...find is not a function" and crashes this component's entire render,
// which is why the whole Fees tab was rendering blank. `.value` is required here.
const selectedScheme = computed(() => (
    (classCategorySchemes.value ?? []).find((s) => String(s.id) === String(feeSettingsForm.class_group_scheme)) ?? null
));

// Deleting a scheme or a category inside it doesn't cascade-fix anything elsewhere — any
// event still pointed at the scheme, or any student/item already tagged with the category's
// key, keeps that stale reference until someone manually reassigns it. Warn with exactly
// what's affected (events_count/event_titles come from the backend) before either delete
// actually fires, matching this codebase's existing confirm()-before-destructive-action
// pattern (see AcademicYears/Index.vue, SportsAgeGroups/Index.vue, etc.).
async function confirmDeleteScheme(scheme) {
    const count = scheme.events_count ?? 0;
    const message = count > 0
        ? `Delete "${scheme.name}"? ${count} event${count === 1 ? ' is' : 's are'} currently using it`
            + (scheme.event_titles?.length ? ` (${scheme.event_titles.join(', ')})` : '')
            + ` — you'll need to reassign ${count === 1 ? 'that event' : 'those events'} to a different scheme afterward. This cannot be undone.`
        : `Delete "${scheme.name}"? This cannot be undone.`;

    if (!(await confirm({ message, destructive: true }))) return;
    removeClassCategoryScheme(scheme.id);
}

async function confirmDeleteSchemeGroup(scheme, group) {
    const message = `Remove category "${group.label}" from "${scheme.name}"? Any students or items already placed `
        + `in this category will need to be reassigned to a different category afterward. This cannot be undone.`;

    if (!(await confirm({ message, destructive: true }))) return;
    removeClassCategorySchemeGroup(scheme.id, group.id);
}

// Display-only relabel — the stored fee_model value stays 'sports_composite' either way
// (that's what FestEventFeeResolver, FestSchoolEventFeeService, FestInvoiceService, etc.
// actually key off of; introducing a genuinely separate fee_model value would mean
// touching every one of those calculators too, and any missed spot silently bills ₹0).
// A non-sports fest (English/Science/Kids/Teacher Fest, Kalolsavam, ...) picking this
// billing model gets the same school+student+item math under a name that doesn't call
// it "sports composite".
function billingModelLabel(key, label) {
    if (key === 'sports_composite' && event.event_type !== 'sports') {
        return 'Composite (school + student + included items)';
    }
    return label;
}

// N-tier school registration map (Phase I) — feeSettingsForm.school_registration is now
// an arbitrary-keyed object (see SchoolClassCategoryResolver on the backend) instead of
// a fixed { secondary, senior_secondary } pair, so the form renders one row per key
// present and lets the admin add/remove tier rows freely.
const newSchoolRegistrationTierKey = ref('');

function schoolRegistrationTierLabel(key) {
    return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function addSchoolRegistrationTier() {
    const key = newSchoolRegistrationTierKey.value.trim().toLowerCase().replace(/\s+/g, '_');
    if (!key || key in feeSettingsForm.school_registration) {
        return;
    }
    feeSettingsForm.school_registration[key] = '';
    newSchoolRegistrationTierKey.value = '';
}

function removeSchoolRegistrationTier(key) {
    delete feeSettingsForm.school_registration[key];
}

// student_count_slab fee model's slab table (Phase J) — feeSettingsForm.student_count_slabs
// is a plain client-side array edited here and submitted whole with the rest of the fee
// settings form, same as feeSettingsForm.item_fees / head_fees above.
function addStudentCountSlab() {
    feeSettingsForm.student_count_slabs.push({ min_count: 0, max_count: '', amount: '' });
}

function removeStudentCountSlab(index) {
    feeSettingsForm.student_count_slabs.splice(index, 1);
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
        // Composite billing has no per-age-group rate — the flat default/extra item
        // fee from "Sport event billing" is what every non-overridden item bills at.
        const flat = feeSettingsForm.sport_event_fees.default_item_fee;
        if (flat !== '' && flat != null) {
            return flat;
        }
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
