<template>
    <SahodayaAdminLayout title="Membership Settings" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="max-w-3xl space-y-5">
            <!-- Quick guide -->
            <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 text-sm text-indigo-900">
                <p class="font-semibold mb-2">Where to change things</p>
                <p class="text-xs text-indigo-700/80 mb-2">Each tab has its own <strong>Save</strong> button — changes are not shared across tabs until you save that section.</p>
                <ul class="grid sm:grid-cols-2 gap-x-4 gap-y-1 text-indigo-800/90 text-xs">
                    <li><button type="button" class="underline hover:text-indigo-950" @click="activeTab = 'profile'">Profile & Rules</button> — name, prefix, academic year, contact</li>
                    <li><button type="button" class="underline hover:text-indigo-950" @click="activeTab = 'window'">Registration Window</button> — open/close dates for the active year</li>
                    <li><button type="button" class="underline hover:text-indigo-950" @click="activeTab = 'categories'">Class Master</button> — add classes 1, 2, 3… and assign categories</li>
                    <li><button type="button" class="underline hover:text-indigo-950" @click="activeTab = 'fees'">Membership Fees</button> — fee amount or slabs for {{ academicYear }}</li>
                </ul>
            </div>

            <!-- Tab bar -->
            <div class="flex border-b border-gray-200 gap-1">
                <button v-for="t in tabs" :key="t.key"
                        @click="activeTab = t.key"
                        :class="['px-4 py-2.5 text-sm font-semibold border-b-2 transition',
                                 activeTab === t.key ? 'border-[#1e1b4b] text-[#1e1b4b]' : 'border-transparent text-gray-500 hover:text-gray-700']">
                    {{ t.label }}
                </button>
            </div>

            <!-- Tab: Profile & Rules -->
            <form v-show="activeTab === 'profile'" @submit.prevent="saveProfile" class="space-y-5">
                <FormSection title="Sahodaya Identity" hint="Name and registration prefix shown on reports and membership numbers.">
                    <FormGrid>
                        <FormField label="Sahodaya Name" class-extra="sm:col-span-2">
                            <input v-model="profileForm.name" class="field" placeholder="Malappuram Sahodaya">
                        </FormField>
                        <FormField label="Registration Prefix" hint="Sahodaya code used in numbers. School membership: MLM/GHS/0001. Student: MLM/GHS/26/0001. Locked after first number is issued.">
                            <input v-model="profileForm.prefix" :disabled="profile.prefixes_locked"
                                   class="field" :class="profile.prefixes_locked ? 'bg-gray-50 cursor-not-allowed' : ''"
                                   placeholder="MLM">
                        </FormField>
                        <FormField label="CBSE Region">
                            <input v-model="profileForm.cbse_region" placeholder="e.g. Thiruvananthapuram" class="field">
                        </FormField>
                    </FormGrid>
                </FormSection>

                <FormSection title="Academic Year" hint="Controls which year schools register for, fee slabs, and reports.">
                    <FormGrid>
                        <FormField label="Active Academic Year" class-extra="sm:col-span-2"
                                   :hint="`Format: 2026-27. Calendar default without override: ${calendarYear}`">
                            <input v-model="profileForm.active_academic_year" type="text" class="field font-mono"
                                   placeholder="2026-27" pattern="\d{4}-\d{2}">
                            <div class="flex flex-wrap gap-2 mt-2">
                                <button type="button" @click="profileForm.active_academic_year = null"
                                        class="text-xs px-2.5 py-1 rounded-full border border-gray-200 text-gray-600 hover:bg-gray-50">
                                    Use calendar ({{ calendarYear }})
                                </button>
                                <button v-for="y in academicYearOptions" :key="y" type="button"
                                        @click="profileForm.active_academic_year = y"
                                        class="text-xs px-2.5 py-1 rounded-full border border-purple-200 text-purple-700 hover:bg-purple-50 font-mono">
                                    {{ y }}
                                </button>
                            </div>
                        </FormField>
                    </FormGrid>
                </FormSection>

                <FormSection title="Branding" hint="Logo shown on the registration portal and admin sidebar.">
                    <div class="flex items-center gap-5">
                        <div v-if="sahodaya.logo_url" class="w-16 h-16 rounded-full border border-gray-200 overflow-hidden shrink-0 bg-white">
                            <img :src="sahodaya.logo_url" alt="Logo"
                                 class="w-full h-full object-cover scale-[1.18]">
                        </div>
                        <div v-else class="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center text-2xl font-bold text-purple-600">
                            {{ sahodaya.name?.charAt(0) }}
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="file" accept="image/*" @change="onLogoSelected"
                                   class="text-sm text-gray-600">
                            <button type="button" @click="uploadLogo" :disabled="!logoForm.logo || logoForm.processing"
                                    class="btn-secondary">Upload</button>
                        </div>
                    </div>
                </FormSection>

                <FormSection title="Contact" hint="Displayed in website footer and membership emails.">
                    <FormGrid>
                        <FormField label="Phone">
                            <input v-model="profileForm.contact_phone" type="tel" class="field">
                        </FormField>
                        <FormField label="Email">
                            <input v-model="profileForm.contact_email" type="email" class="field">
                        </FormField>
                        <FormField label="Office Address" class-extra="sm:col-span-2">
                            <textarea v-model="profileForm.address" rows="2" class="field"></textarea>
                        </FormField>
                    </FormGrid>
                </FormSection>

                <FormSection title="Data Requirements" hint="What annual data schools must submit.">
                    <FormGrid>
                        <FormField label="Student Data Mode">
                            <select v-model="profileForm.student_data_mode" class="field">
                                <option value="full_records">Full student records (name, DOB, etc.)</option>
                                <option value="counts_only">Student counts only (by category)</option>
                                <option value="not_required">Not required</option>
                            </select>
                        </FormField>
                        <FormField label="">
                            <label class="flex items-center gap-3 cursor-pointer mt-6">
                                <input v-model="profileForm.teacher_registration_enabled" type="checkbox"
                                       class="w-4 h-4 text-purple-600 rounded">
                                <span class="text-sm font-medium text-gray-700">Teacher registration enabled</span>
                            </label>
                        </FormField>
                    </FormGrid>
                </FormSection>

                <div class="flex">
                    <button type="submit" :disabled="profileForm.processing" class="btn-primary">
                        Save Profile & Rules
                    </button>
                </div>
            </form>

            <!-- Tab: Payment Details -->
            <form v-show="activeTab === 'payment'" @submit.prevent="savePaymentDetails" class="space-y-5">
                <FormSection title="Bank & UPI Details"
                             hint="Shown to schools when they pay annual membership fees. Keep this up to date.">
                    <FormGrid>
                        <FormField label="Bank Name">
                            <input v-model="paymentForm.payment_bank_name" class="field" placeholder="e.g. State Bank of India">
                        </FormField>
                        <FormField label="Account Number">
                            <input v-model="paymentForm.payment_account_no" class="field" placeholder="12345678901">
                        </FormField>
                        <FormField label="IFSC Code">
                            <input v-model="paymentForm.payment_ifsc" class="field" placeholder="SBIN0001234">
                        </FormField>
                        <FormField label="UPI ID">
                            <input v-model="paymentForm.payment_upi" class="field" placeholder="malappuramsahodaya@upi">
                        </FormField>
                        <FormField label="Additional Instructions" class-extra="sm:col-span-2">
                            <textarea v-model="paymentForm.payment_instructions" rows="4" class="field w-full"
                                      placeholder="e.g. Include school name and registration number in the payment reference."></textarea>
                        </FormField>
                    </FormGrid>
                </FormSection>

                <FormSection title="School preview" hint="This is what schools see on the payment step.">
                    <div v-if="paymentPreview" class="bg-amber-50 border border-amber-100 rounded-xl p-4">
                        <p class="text-xs font-semibold text-amber-800 uppercase tracking-wide mb-2">How to pay</p>
                        <pre class="text-sm text-gray-800 whitespace-pre-wrap font-sans leading-relaxed">{{ paymentPreview }}</pre>
                    </div>
                    <p v-else class="text-sm text-gray-400 italic">Fill in at least one field above to show payment instructions to schools.</p>
                </FormSection>

                <div class="flex">
                    <button type="submit" :disabled="paymentForm.processing" class="btn-primary">
                        Save Payment Details
                    </button>
                </div>
            </form>

            <!-- Tab: Zoho Email -->
            <form v-show="activeTab === 'email'" @submit.prevent="saveMailSettings" class="space-y-5">
                <FormSection title="Zoho Mail (SMTP)"
                             hint="Each Sahodaya sends membership emails from its own Zoho account. Leave password blank to keep the current one.">
                    <p v-if="profile.mail_configured"
                       class="text-xs font-semibold text-green-700 bg-green-50 border border-green-100 rounded-lg px-3 py-2">
                        Zoho SMTP is configured for this Sahodaya.
                    </p>
                    <p v-else
                       class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                        Not configured — emails fall back to the platform default mail settings.
                    </p>
                    <FormGrid>
                        <FormField label="SMTP Host" hint="Zoho India: smtp.zoho.in · Global: smtp.zoho.com">
                            <input v-model="mailForm.mail_host" class="field" placeholder="smtp.zoho.in">
                        </FormField>
                        <FormField label="Port">
                            <input v-model.number="mailForm.mail_port" type="number" class="field" placeholder="587">
                        </FormField>
                        <FormField label="Encryption">
                            <select v-model="mailForm.mail_encryption" class="field">
                                <option value="tls">TLS (587)</option>
                                <option value="ssl">SSL (465)</option>
                            </select>
                        </FormField>
                        <FormField label="Zoho Email (Username)">
                            <input v-model="mailForm.mail_username" type="email" class="field" placeholder="office@yourdomain.com">
                        </FormField>
                        <FormField label="App Password" hint="Zoho app-specific password. Leave blank to keep existing.">
                            <input v-model="mailForm.mail_password" type="password" class="field" autocomplete="new-password">
                        </FormField>
                        <FormField label="From Address" hint="Defaults to Zoho username if empty">
                            <input v-model="mailForm.mail_from_address" type="email" class="field">
                        </FormField>
                        <FormField label="From Name" class-extra="sm:col-span-2">
                            <input v-model="mailForm.mail_from_name" class="field" :placeholder="sahodaya.name">
                        </FormField>
                    </FormGrid>
                </FormSection>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" :disabled="mailForm.processing" class="btn-primary">
                        Save Mail Settings
                    </button>
                    <button type="button" @click="sendTestMail" :disabled="testMailForm.processing"
                            class="btn-secondary">
                        Send Test Email
                    </button>
                    <input v-model="testMailForm.test_email" type="email"
                           placeholder="Test recipient (optional)"
                           class="field max-w-xs">
                </div>
            </form>

            <!-- Tab: Registration Form -->
            <form v-show="activeTab === 'form'" @submit.prevent="saveFormConfig" class="space-y-5">
                <FormSection title="School Registration Form"
                             hint="Choose which fields appear on the public /school-register page. Disabled fields are hidden from applicants.">
                    <div v-for="(items, groupKey) in formFieldGroups" :key="groupKey" class="space-y-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-gray-500">
                            {{ applicationFormGroups[groupKey] || groupKey }}
                        </p>
                        <div class="divide-y divide-gray-100 border border-gray-100 rounded-xl overflow-hidden">
                            <div v-for="field in items" :key="field.key"
                                 class="flex items-center justify-between gap-4 px-4 py-3 bg-white">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800">{{ field.label }}</p>
                                    <p v-if="field.hint" class="text-[11px] text-gray-400 mt-0.5">{{ field.hint }}</p>
                                </div>
                                <div class="flex items-center gap-5 shrink-0">
                                    <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                                        <input type="checkbox"
                                               v-model="formConfig.fields[field.key].enabled"
                                               class="w-4 h-4 text-purple-600 rounded">
                                        Show
                                    </label>
                                    <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer"
                                           :class="!formConfig.fields[field.key].enabled ? 'opacity-40 pointer-events-none' : ''">
                                        <input type="checkbox"
                                               v-model="formConfig.fields[field.key].required"
                                               :disabled="!formConfig.fields[field.key].enabled"
                                               class="w-4 h-4 text-purple-600 rounded">
                                        Required
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </FormSection>
                <div class="flex">
                    <button type="submit" :disabled="formConfig.processing" class="btn-primary">
                        Save Form Fields
                    </button>
                </div>
            </form>

            <!-- Tab: Fees -->
            <form v-show="activeTab === 'fees'" @submit.prevent="saveFees" class="space-y-5">
                <FormSection title="Fee Type">
                    <FormGrid>
                        <FormField label="Membership Fee Type" class-extra="sm:col-span-2">
                            <select v-model="feeForm.membership_fee_type" class="field">
                                <option value="fixed">Fixed fee (same for all schools)</option>
                                <option value="variable_by_student_count">Variable by student count (slabs)</option>
                            </select>
                        </FormField>
                        <FormField v-if="feeForm.membership_fee_type === 'fixed'" label="Fixed Amount (₹)">
                            <input v-model.number="feeForm.fixed_membership_fee_amount" type="number" step="0.01" min="0" class="field">
                        </FormField>
                    </FormGrid>
                </FormSection>

                <FormSection v-if="feeForm.membership_fee_type === 'variable_by_student_count'"
                             :title="`Fee Slabs — ${academicYear}`"
                             hint="Schools are billed the slab matching their student count.">
                    <!-- Add slab form -->
                    <form @submit.prevent="addSlab" class="flex flex-wrap gap-3 items-end mb-4">
                        <FormField label="Min Students">
                            <input v-model.number="slabForm.min_students" type="number" min="0" class="field w-24">
                        </FormField>
                        <FormField label="Max Students">
                            <input v-model.number="slabForm.max_students" type="number" min="0" class="field w-24" placeholder="∞">
                        </FormField>
                        <FormField label="Amount (₹)">
                            <input v-model.number="slabForm.amount" type="number" step="0.01" min="0" class="field w-32">
                        </FormField>
                        <button type="submit" class="btn-secondary mb-0.5">Add Slab</button>
                    </form>
                    <!-- Slabs table -->
                    <div v-if="feeSlabs.length" class="border border-gray-100 rounded-xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-left px-4 py-2.5 font-semibold text-gray-600 text-xs">Students</th>
                                    <th class="text-right px-4 py-2.5 font-semibold text-gray-600 text-xs">Amount</th>
                                    <th class="px-4 py-2.5 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="slab in feeSlabs" :key="slab.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-700">
                                        {{ slab.min_students }} – {{ slab.max_students ?? '∞' }} students
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-green-700">
                                        ₹{{ Number(slab.amount).toLocaleString('en-IN') }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button @click="removeSlab(slab)" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-gray-400 text-center py-4">No slabs defined yet.</p>
                </FormSection>

                <div class="flex">
                    <button type="submit" :disabled="feeForm.processing" class="btn-primary">
                        Save Membership Fees
                    </button>
                </div>
            </form>

            <!-- Tab: Registration Window -->
            <div v-show="activeTab === 'window'" class="space-y-5">
                <p class="text-sm text-gray-500">
                    Registration dates apply to the <strong class="text-gray-700">active academic year</strong>
                    (<span class="font-mono">{{ academicYear }}</span>).
                    Change the year under <button type="button" class="text-purple-700 underline" @click="activeTab = 'profile'">Profile & Rules</button>.
                </p>
                <FormSection :title="`Registration Window — ${academicYear}`"
                             hint="Schools can only submit annual registration during this period.">
                    <form @submit.prevent="saveWindow" class="space-y-4">
                        <FormGrid>
                            <FormField label="Opens On">
                                <input v-model="windowForm.registration_starts_at" type="date" class="field">
                            </FormField>
                            <FormField label="Closes On">
                                <input v-model="windowForm.registration_ends_at" type="date" class="field">
                            </FormField>
                        </FormGrid>
                        <div v-if="windowForm.registration_starts_at && windowForm.registration_ends_at"
                             class="bg-purple-50 border border-purple-100 rounded-xl px-4 py-3 text-sm text-purple-700 font-medium">
                            Registration open {{ new Date(windowForm.registration_starts_at).toLocaleDateString('en-IN', {day:'numeric',month:'long'}) }}
                            to {{ new Date(windowForm.registration_ends_at).toLocaleDateString('en-IN', {day:'numeric',month:'long',year:'numeric'}) }}
                        </div>
                        <button type="submit" class="btn-primary">Save Window</button>
                    </form>
                </FormSection>
            </div>

            <!-- Tab: Class Master -->
            <div v-show="activeTab === 'categories'" class="space-y-5">
                <p class="text-sm text-gray-500 bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                    Add each class (<strong>1, 2, 3, LKG, UKG…</strong>) and assign it to a <strong>category</strong> (Primary, Secondary, etc.).
                    All member schools automatically get this class list — they cannot add their own.
                </p>

                <FormSection title="Classes"
                             hint="Each row is one class. Schools pick from this list when registering students.">
                    <div v-if="masterClasses.length" class="border border-gray-100 rounded-xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="text-left px-4 py-2.5 font-semibold w-16">Sort</th>
                                    <th class="text-left px-4 py-2.5 font-semibold">Class</th>
                                    <th class="text-left px-4 py-2.5 font-semibold">Category</th>
                                    <th class="px-4 py-2.5 w-28"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="cls in masterClasses" :key="cls.id" class="hover:bg-gray-50/80">
                                    <td v-if="editingClassId === cls.id" colspan="4" class="px-4 py-3">
                                        <div class="flex flex-wrap gap-3 items-end">
                                            <FormField label="Sort order" hint="Lower = first">
                                                <input v-model.number="editClassForm.display_order" type="number" min="0" class="field w-20">
                                            </FormField>
                                            <FormField label="Class name">
                                                <input v-model="editClassForm.name" class="field w-28 font-mono" placeholder="1">
                                            </FormField>
                                            <FormField label="Category">
                                                <select v-model="editClassForm.class_category_id" class="field min-w-[10rem]">
                                                    <option v-for="cat in effectiveCategories" :key="cat.id" :value="cat.id">
                                                        {{ cat.label }}
                                                    </option>
                                                </select>
                                            </FormField>
                                            <div class="flex gap-2 pb-0.5">
                                                <button type="button" @click="saveClassEdit(cls)" class="btn-secondary text-xs">Save</button>
                                                <button type="button" @click="cancelClassEdit" class="text-xs text-gray-500">Cancel</button>
                                            </div>
                                        </div>
                                    </td>
                                    <template v-else>
                                        <td class="px-4 py-3 text-gray-400 font-mono text-xs">{{ cls.display_order }}</td>
                                        <td class="px-4 py-3 font-mono font-semibold text-gray-800">{{ cls.name }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ cls.class_category?.label || '—' }}</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <button type="button" @click="startClassEdit(cls)" class="text-xs text-purple-600 hover:underline mr-2">Edit</button>
                                            <button type="button" @click="removeClass(cls)" class="text-xs text-red-500 hover:underline">Remove</button>
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-gray-400 text-center py-6">No classes yet — add your first class below.</p>

                    <form @submit.prevent="addClass" class="flex flex-wrap gap-3 items-end mt-4 pt-4 border-t border-gray-100">
                        <FormField label="Sort order" hint="Lower = first in lists">
                            <input v-model.number="classForm.display_order" type="number" min="0" class="field w-20"
                                   :placeholder="String(nextClassSort)">
                        </FormField>
                        <FormField label="Class name" hint="e.g. LKG, 1, 2">
                            <input v-model="classForm.name" required class="field w-28 font-mono" placeholder="LKG">
                        </FormField>
                        <FormField label="Category">
                            <select v-model="classForm.class_category_id" required class="field min-w-[10rem]">
                                <option value="">Select category</option>
                                <option v-for="cat in effectiveCategories" :key="cat.id" :value="cat.id">
                                    {{ cat.sort_order }}. {{ cat.label }}
                                </option>
                            </select>
                        </FormField>
                        <button type="submit" :disabled="!classForm.class_category_id" class="btn-secondary mb-0.5">Add Class</button>
                    </form>
                </FormSection>

                <FormSection title="Class Categories"
                             hint="Group classes into categories (Primary, Secondary, etc.). Disable categories you don't use.">
                    <div class="space-y-2">
                        <div v-for="cat in globalCategories" :key="cat.id"
                             class="p-3 rounded-xl border"
                             :class="hiddenCategoryIds.includes(cat.id) ? 'border-gray-100 bg-gray-50 opacity-60' : 'border-purple-100 bg-purple-50/30'">
                            <template v-if="editingGlobalCategoryId === cat.id">
                                <div class="flex flex-wrap gap-3 items-end">
                                    <FormField label="Sort" hint="Lower = first">
                                        <input v-model.number="editGlobalCategoryForm.sort_order" type="number" min="0" class="field w-20">
                                    </FormField>
                                    <div class="pb-0.5 text-sm text-gray-600">
                                        <span class="font-mono text-xs text-gray-500 bg-white border border-gray-200 px-2 py-0.5 rounded mr-2">{{ cat.code }}</span>
                                        {{ cat.label }}
                                    </div>
                                </div>
                                <div class="flex gap-2 mt-3">
                                    <button type="button" @click="saveGlobalCategoryEdit(cat)" class="btn-secondary text-xs">Save</button>
                                    <button type="button" @click="cancelGlobalCategoryEdit" class="text-xs text-gray-500">Cancel</button>
                                </div>
                            </template>
                            <template v-else>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs font-mono text-gray-400 w-6">{{ cat.sort_order }}</span>
                                        <span class="font-mono text-xs text-gray-500 bg-white border border-gray-200 px-2 py-0.5 rounded">{{ cat.code }}</span>
                                        <span class="text-sm font-medium text-gray-700">{{ cat.label }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button type="button" @click="startGlobalCategoryEdit(cat)" class="text-xs text-purple-600 hover:underline">Edit sort</button>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox"
                                                   :checked="!hiddenCategoryIds.includes(cat.id)"
                                                   @change="toggleCategory(cat.id, !$event.target.checked)"
                                                   class="sr-only peer">
                                            <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-purple-300 rounded-full peer peer-checked:bg-purple-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </FormSection>

                <FormSection v-if="customCategories.length" title="Your Custom Categories"
                             hint="Edit or remove categories you added for this Sahodaya.">
                    <div v-for="cat in customCategories" :key="cat.id"
                         class="p-3 rounded-xl border border-gray-100 text-sm mb-2 space-y-3">
                        <template v-if="editingCategoryId === cat.id">
                            <div class="flex flex-wrap gap-3 items-end">
                                <FormField label="Sort" hint="Lower = first">
                                    <input v-model.number="editCategoryForm.sort_order" type="number" min="0" class="field w-20">
                                </FormField>
                                <FormField label="Code">
                                    <input v-model="editCategoryForm.code" class="field w-24">
                                </FormField>
                                <FormField label="Label">
                                    <input v-model="editCategoryForm.label" class="field w-48">
                                </FormField>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="saveCategoryEdit(cat)" class="btn-secondary text-xs">Save</button>
                                <button type="button" @click="cancelCategoryEdit" class="text-xs text-gray-500">Cancel</button>
                            </div>
                        </template>
                        <template v-else>
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="text-xs font-mono text-gray-400 w-6">{{ cat.sort_order }}</span>
                                    <span class="font-mono text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">{{ cat.code }}</span>
                                    <span class="font-medium text-gray-700">{{ cat.label }}</span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button type="button" @click="startCategoryEdit(cat)" class="text-xs text-purple-600 hover:underline">Edit</button>
                                    <button type="button" @click="removeCategory(cat)" class="text-xs text-red-500 hover:underline">Remove</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </FormSection>

                <FormSection title="Add Custom Category" hint="Only if you need an extra category beyond the global list (Pre-Primary, Primary, etc.).">
                    <form @submit.prevent="addCategory" class="flex flex-wrap gap-3 items-end">
                        <FormField label="Sort" hint="Lower = first">
                            <input v-model.number="categoryForm.sort_order" type="number" min="0" class="field w-20"
                                   :placeholder="String(nextCategorySort)">
                        </FormField>
                        <FormField label="Code">
                            <input v-model="categoryForm.code" class="field w-24" placeholder="PRE">
                        </FormField>
                        <FormField label="Label">
                            <input v-model="categoryForm.label" class="field w-48" placeholder="Pre-Primary">
                        </FormField>
                        <button type="submit" class="btn-secondary mb-0.5">Add Category</button>
                    </form>
                </FormSection>
            </div>

            <!-- Tab: Teaching Types -->
            <div v-show="activeTab === 'types'" class="space-y-5">
                <FormSection title="Global Teaching Types"
                             hint="Hide types that don't apply to this Sahodaya's schools.">
                    <div class="space-y-2">
                        <div v-for="t in globalTypes" :key="t.id"
                             class="flex items-center justify-between p-3 rounded-xl border"
                             :class="hiddenTypeIds.includes(t.id) ? 'border-gray-100 bg-gray-50 opacity-60' : 'border-purple-100 bg-purple-50/30'">
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-xs text-gray-500 bg-white border border-gray-200 px-2 py-0.5 rounded">{{ t.code }}</span>
                                <span class="text-sm font-medium text-gray-700">{{ t.label }}</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox"
                                       :checked="!hiddenTypeIds.includes(t.id)"
                                       @change="toggleType(t.id, !$event.target.checked)"
                                       class="sr-only peer">
                                <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-purple-300 rounded-full peer peer-checked:bg-purple-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                            </label>
                        </div>
                    </div>
                </FormSection>

                <FormSection v-if="customTypes.length" title="Custom Teaching Types">
                    <div v-for="t in customTypes" :key="t.id"
                         class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 text-sm mb-2">
                        <span class="font-mono text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">{{ t.code }}</span>
                        <span class="font-medium text-gray-700">{{ t.label }}</span>
                    </div>
                </FormSection>

                <FormSection title="Add Custom Teaching Type">
                    <form @submit.prevent="addType" class="flex flex-wrap gap-3 items-end">
                        <FormField label="Code">
                            <input v-model="typeForm.code" class="field w-24" placeholder="PRT">
                        </FormField>
                        <FormField label="Label">
                            <input v-model="typeForm.label" class="field w-48" placeholder="Primary Teacher">
                        </FormField>
                        <button type="submit" class="btn-secondary mb-0.5">Add Type</button>
                    </form>
                </FormSection>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref, computed, defineComponent, h } from 'vue';

const props = defineProps({
    sahodaya:                Object,
    publicUrl:               { type: String, default: null },
    pendingSchoolsCount:     { type: Number, default: 0 },
    pendingSubmissionsCount: { type: Number, default: 0 },
    pendingPaymentsCount:    { type: Number, default: 0 },
    profile:                 Object,
    feeSlabs:                { type: Array, default: () => [] },
    registrationWindow:      { type: Object, default: null },
    academicYear:            String,
    calendarYear:          String,
    academicYearOptions:   { type: Array, default: () => [] },
    masterClasses:           { type: Array, default: () => [] },
    effectiveCategories:     { type: Array, default: () => [] },
    globalCategories:        { type: Array, default: () => [] },
    customCategories:        { type: Array, default: () => [] },
    hiddenCategoryIds:       { type: Array, default: () => [] },
    globalTypes:             { type: Array, default: () => [] },
    customTypes:             { type: Array, default: () => [] },
    hiddenTypeIds:           { type: Array, default: () => [] },
    applicationFormFields:   { type: Object, default: () => ({}) },
    applicationFormGroups:   { type: Object, default: () => ({}) },
});

const activeTab = ref('profile');

const tabs = [
    { key: 'profile',    label: 'Profile & Rules' },
    { key: 'payment',    label: 'Payment Details' },
    { key: 'email',      label: 'Zoho Email' },
    { key: 'form',       label: 'Registration Form' },
    { key: 'window',     label: 'Registration Window' },
    { key: 'fees',       label: 'Membership Fees' },
    { key: 'categories', label: 'Class Master' },
    { key: 'types',      label: 'Teaching Types' },
];

// Forms
const profileForm = useForm({
    name: props.sahodaya?.name ?? '',
    ...props.profile,
});
const paymentForm = useForm({
    payment_bank_name:     props.profile?.payment_bank_name ?? '',
    payment_account_no:    props.profile?.payment_account_no ?? '',
    payment_ifsc:          props.profile?.payment_ifsc ?? '',
    payment_upi:           props.profile?.payment_upi ?? '',
    payment_instructions:  props.profile?.payment_instructions ?? '',
});
const mailForm = useForm({
    mail_host:         props.profile?.mail_host ?? 'smtp.zoho.in',
    mail_port:         props.profile?.mail_port ?? 587,
    mail_encryption:   props.profile?.mail_encryption ?? 'tls',
    mail_username:     props.profile?.mail_username ?? '',
    mail_password:     '',
    mail_from_address: props.profile?.mail_from_address ?? '',
    mail_from_name:    props.profile?.mail_from_name ?? '',
});
const testMailForm = useForm({ test_email: props.profile?.contact_email ?? '' });
const logoForm    = useForm({ logo: null });
const windowForm  = useForm({
    academic_year:          props.academicYear,
    registration_starts_at: props.registrationWindow?.registration_starts_at?.slice(0, 10) || '',
    registration_ends_at:   props.registrationWindow?.registration_ends_at?.slice(0, 10) || '',
});
const slabForm = useForm({ academic_year: props.academicYear, min_students: 0, max_students: null, amount: 0 });
const categoryForm = useForm({ code: '', label: '', sort_order: null });
const editCategoryForm = useForm({ code: '', label: '', sort_order: null });
const editingCategoryId = ref(null);
const editGlobalCategoryForm = useForm({ sort_order: null });
const editingGlobalCategoryId = ref(null);
const classForm = useForm({ name: '', class_category_id: '', display_order: null });
const editClassForm = useForm({ name: '', class_category_id: '', display_order: null });
const editingClassId = ref(null);
const typeForm     = useForm({ code: '', label: '' });
const formConfig   = useForm({
    fields: Object.fromEntries(
        Object.entries(props.applicationFormFields).map(([key, field]) => [
            key,
            { enabled: field.enabled, required: field.required },
        ])
    ),
});

const formFieldGroups = computed(() => {
    const groups = {};
    for (const [key, field] of Object.entries(props.applicationFormFields)) {
        if (field.locked || key === 'password_confirmation') continue;
        const group = field.group || 'other';
        if (!groups[group]) groups[group] = [];
        groups[group].push({ key, ...field });
    }
    return groups;
});

const feeForm = useForm({
    membership_fee_type: props.profile?.membership_fee_type ?? 'fixed',
    fixed_membership_fee_amount: props.profile?.fixed_membership_fee_amount ?? 0,
});

const paymentPreview = computed(() => {
    const lines = [
        paymentForm.payment_bank_name ? `Bank: ${paymentForm.payment_bank_name}` : '',
        paymentForm.payment_account_no ? `Account: ${paymentForm.payment_account_no}` : '',
        paymentForm.payment_ifsc ? `IFSC: ${paymentForm.payment_ifsc}` : '',
        paymentForm.payment_upi ? `UPI: ${paymentForm.payment_upi}` : '',
        paymentForm.payment_instructions || '',
    ].filter(Boolean);

    return lines.join('\n');
});

const nextClassSort = computed(() => {
    if (! props.masterClasses?.length) return 1;
    return Math.max(...props.masterClasses.map(c => c.display_order ?? 0)) + 1;
});

const nextCategorySort = computed(() => {
    const orders = [
        ...(props.customCategories ?? []).map(c => c.sort_order ?? 0),
        ...(props.globalCategories ?? []).map(c => c.sort_order ?? 0),
    ];
    return orders.length ? Math.max(...orders) + 1 : 0;
});

function saveProfile() { profileForm.put(`/sahodaya-admin/${props.sahodaya.id}/membership/settings`); }
function savePaymentDetails() {
    paymentForm.put(`/sahodaya-admin/${props.sahodaya.id}/membership/payment-details`);
}
function saveMailSettings() {
    mailForm.put(`/sahodaya-admin/${props.sahodaya.id}/membership/mail-settings`, {
        onSuccess: () => mailForm.mail_password = '',
    });
}
function sendTestMail() {
    testMailForm.post(`/sahodaya-admin/${props.sahodaya.id}/membership/mail-settings/test`);
}
function saveFormConfig() { formConfig.put(`/sahodaya-admin/${props.sahodaya.id}/membership/application-form`); }
function saveWindow()  { windowForm.put(`/sahodaya-admin/${props.sahodaya.id}/membership/registration-window`); }
function addSlab()     { slabForm.post(`/sahodaya-admin/${props.sahodaya.id}/membership/fee-slabs`, { onSuccess: () => slabForm.reset('min_students', 'max_students', 'amount') }); }
function removeSlab(s) { router.delete(`/sahodaya-admin/${props.sahodaya.id}/membership/fee-slabs/${s.id}`); }

function saveFees() {
    feeForm.put(`/sahodaya-admin/${props.sahodaya.id}/membership/fees`);
}
function toggleCategory(id, hidden) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/membership/category-overrides`, {
        class_category_id: id, is_hidden: hidden,
    });
}
function toggleType(id, hidden) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/membership/type-overrides`, {
        teaching_type_id: id, is_hidden: hidden,
    });
}
function addCategory() {
    categoryForm.post(`/sahodaya-admin/${props.sahodaya.id}/membership/custom-categories`, {
        onSuccess: () => categoryForm.reset(),
    });
}
function startGlobalCategoryEdit(cat) {
    editingGlobalCategoryId.value = cat.id;
    editGlobalCategoryForm.sort_order = cat.sort_order;
}
function cancelGlobalCategoryEdit() {
    editingGlobalCategoryId.value = null;
    editGlobalCategoryForm.reset();
}
function saveGlobalCategoryEdit(cat) {
    editGlobalCategoryForm.put(`/sahodaya-admin/${props.sahodaya.id}/membership/global-categories/${cat.id}/sort`, {
        onSuccess: () => cancelGlobalCategoryEdit(),
    });
}
function startCategoryEdit(cat) {
    editingCategoryId.value = cat.id;
    editCategoryForm.code = cat.code;
    editCategoryForm.label = cat.label;
    editCategoryForm.sort_order = cat.sort_order;
}
function cancelCategoryEdit() {
    editingCategoryId.value = null;
    editCategoryForm.reset();
}
function saveCategoryEdit(cat) {
    editCategoryForm.put(`/sahodaya-admin/${props.sahodaya.id}/membership/custom-categories/${cat.id}`, {
        onSuccess: () => cancelCategoryEdit(),
    });
}
function removeCategory(cat) {
    if (! confirm(`Remove category "${cat.label}"?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/membership/custom-categories/${cat.id}`);
}
function addClass() {
    classForm.post(`/sahodaya-admin/${props.sahodaya.id}/membership/classes`, {
        onSuccess: () => classForm.reset(),
    });
}
function startClassEdit(cls) {
    editingClassId.value = cls.id;
    editClassForm.name = cls.name;
    editClassForm.class_category_id = cls.class_category_id;
    editClassForm.display_order = cls.display_order;
}
function cancelClassEdit() {
    editingClassId.value = null;
    editClassForm.reset();
}
function saveClassEdit(cls) {
    editClassForm.put(`/sahodaya-admin/${props.sahodaya.id}/membership/classes/${cls.id}`, {
        onSuccess: () => cancelClassEdit(),
    });
}
function removeClass(cls) {
    if (! confirm(`Remove class "${cls.name}"?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/membership/classes/${cls.id}`);
}
function addType() {
    typeForm.post(`/sahodaya-admin/${props.sahodaya.id}/membership/custom-teaching-types`, {
        onSuccess: () => typeForm.reset(),
    });
}
function uploadLogo() {
    logoForm.post(`/sahodaya-admin/${props.sahodaya.id}/membership/logo`, {
        forceFormData: true,
        onSuccess: () => logoForm.reset(),
    });
}
function onLogoSelected(e) {
    logoForm.logo = e.target.files[0] ?? null;
}

// Utility sub-components
const FormSection = defineComponent({
    props: { title: String, hint: String },
    setup(props, { slots }) {
        return () => h('div', { class: 'bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4' }, [
            h('div', { class: 'mb-1' }, [
                h('h3', { class: 'font-bold text-gray-900' }, props.title),
                props.hint ? h('p', { class: 'text-xs text-gray-400 mt-0.5' }, props.hint) : null,
            ]),
            slots.default?.(),
        ]);
    },
});

const FormGrid = defineComponent({
    setup(_, { slots }) {
        return () => h('div', { class: 'grid sm:grid-cols-2 gap-4' }, slots.default?.());
    },
});

const FormField = defineComponent({
    props: { label: String, hint: String, classExtra: String },
    setup(props, { slots }) {
        return () => h('div', { class: props.classExtra ?? '' }, [
            props.label ? h('label', { class: 'block text-xs font-semibold text-gray-600 mb-1.5' }, props.label) : null,
            slots.default?.(),
            props.hint ? h('p', { class: 'text-[11px] text-gray-400 mt-1' }, props.hint) : null,
        ]);
    },
});
</script>

<style scoped>
@reference "../../../../../css/app.css";
.field {
    @apply w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300 bg-white;
}
.btn-primary {
    @apply px-6 py-2.5 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-sm font-bold rounded-xl transition disabled:opacity-50;
}
.btn-secondary {
    @apply px-4 py-2.5 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 text-sm font-bold rounded-xl transition;
}
</style>
