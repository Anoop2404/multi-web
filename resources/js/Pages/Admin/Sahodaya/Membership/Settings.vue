<template>
    <SahodayaAdminLayout title="Membership Settings" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <PageHeader
            title="Membership Settings"
            eyebrow="Configuration"
            description="Manage Sahodaya identity, registration windows, fees, class master, and payment details. Each tab saves independently."
        />

        <div class="max-w-7xl space-y-6">
            <!-- Setup checklist — only shown when required settings are incomplete -->
            <div v-if="incompleteSetup.length" class="rounded-2xl border border-amber-200 bg-amber-50/90 p-4.5 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="p-2.5 rounded-xl bg-amber-100/80 text-amber-800 shrink-0 text-xl">⚠️</div>
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-bold text-amber-950 text-sm">Required setup incomplete</p>
                            <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-amber-200/80 text-amber-900">
                                {{ incompleteSetup.length }} step(s) pending
                            </span>
                        </div>
                        <p class="text-xs text-amber-800 mt-0.5">Complete these settings before inviting schools — missing items will block registration or payments.</p>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <button v-for="item in incompleteSetup" :key="item.key"
                                    type="button"
                                    @click="activeTab = item.tab"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-amber-300 text-amber-900 text-xs font-semibold hover:bg-amber-100 transition shadow-xs">
                                <span class="font-bold text-amber-950">Go to {{ item.tabLabel }}:</span>
                                <span class="text-amber-800">{{ item.label }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else-if="setupDoneOnce" class="rounded-2xl border border-emerald-200 bg-emerald-50/80 px-4.5 py-3 flex items-center gap-2.5 text-sm font-medium text-emerald-800 shadow-xs">
                <span class="text-lg">✅</span> All required membership settings are configured for this Sahodaya.
            </div>

            <!-- Workspace Grid: Left Categorized Sidebar + Right Form Panel -->
            <div class="grid lg:grid-cols-12 gap-6 items-start">

                <!-- Left Sidebar Navigation -->
                <aside class="lg:col-span-4 xl:col-span-3 space-y-4">
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden p-3.5 sticky top-6 space-y-4">
                        <div v-for="group in tabGroups" :key="group.title" class="space-y-1.5">
                            <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                {{ group.title }}
                            </p>
                            <nav class="space-y-1">
                                <button v-for="t in group.items" :key="t.key"
                                        type="button"
                                        @click="activeTab = t.key"
                                        :class="[
                                            'w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold transition-all',
                                            activeTab === t.key
                                                ? 'bg-[#041525] text-white shadow-sm font-bold'
                                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                        ]">
                                    <span class="flex items-center gap-2.5 truncate">
                                        <span class="text-sm shrink-0">{{ tabIcons[t.key] || '⚙️' }}</span>
                                        <span class="truncate">{{ t.label }}</span>
                                    </span>
                                    <span v-if="tabsWithWarnings.has(t.key)"
                                          class="w-2 h-2 rounded-full bg-amber-400 shrink-0"></span>
                                </button>
                            </nav>
                        </div>
                    </div>
                </aside>

                <!-- Right Form Workspace -->
                <main class="lg:col-span-8 xl:col-span-9 bg-white rounded-2xl border border-slate-200/80 p-6 sm:p-7 shadow-xs min-h-[550px]">

            <!-- Tab: Profile & Rules -->
            <form v-show="activeTab === 'profile'" @submit.prevent="saveProfile" class="space-y-5">
                <FormSection title="Sahodaya Identity" hint="Name and registration prefix shown on reports and membership numbers.">
                    <FormGrid>
                        <FormField label="Sahodaya Name" class-extra="sm:col-span-2">
                            <input v-model="profileForm.name" class="field" placeholder="Malappuram Sahodaya">
                        </FormField>
                        <FormField label="Registration Prefix" :required="!profile.prefixes_locked" hint="Sahodaya code used in numbers. School membership restarts each year: KNR/26/1, KNR/26/2. Student: STU/26/0001. Locked after first number is issued.">
                            <input v-model="profileForm.prefix" :disabled="profile.prefixes_locked"
                                   class="field" :class="profile.prefixes_locked ? 'bg-gray-50 cursor-not-allowed' : ''"
                                   placeholder="MLM">
                        </FormField>
                        <FormField label="CBSE Region">
                            <input v-model="profileForm.cbse_region" placeholder="e.g. Thiruvananthapuram" class="field">
                        </FormField>
                    </FormGrid>
                </FormSection>

                <FormSection title="Academic Year" hint="Controls which year schools register for, fee slabs, and reports. Manage lifecycle under Academic Years.">
                    <div v-if="activeAcademicYearRecord" class="mb-3 flex flex-wrap items-center gap-2 text-xs">
                        <span class="px-2.5 py-1 rounded-full bg-green-100 text-green-800 font-semibold">
                            Active record: {{ activeAcademicYearRecord.label }}
                        </span>
                        <a :href="`/sahodaya-admin/${sahodaya.id}/academic-years`"
                           class="text-purple-700 underline hover:text-purple-900">
                            Open Academic Years →
                        </a>
                    </div>
                    <div v-else class="mb-3 p-3 rounded-lg bg-amber-50 border border-amber-100 text-xs text-amber-900">
                        No academic year record is active yet.
                        <a :href="`/sahodaya-admin/${sahodaya.id}/academic-years`"
                           class="ml-1 font-semibold underline hover:text-amber-950">
                            Create and activate one →
                        </a>
                    </div>
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

                <FormSection title="Branding" hint="Logo shown on the registration portal, admin sidebar, and this Sahodaya's membership receipts.">
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
                        <FormField label="" class-extra="sm:col-span-2">
                            <label class="flex items-center gap-3 cursor-pointer mt-2">
                                <input v-model="profileForm.auto_approve_submissions" type="checkbox"
                                       class="w-4 h-4 text-purple-600 rounded">
                                <span class="text-sm font-medium text-gray-700">Auto-approve submitted counts &amp; teacher records</span>
                            </label>
                            <p class="text-xs text-gray-500 mt-1">
                                Schools still submit their student counts / teacher list for the year, but payment
                                unlocks as soon as they submit — without waiting for a Sahodaya admin to manually
                                approve each track from Submissions. Turn on if you don't need to review data before
                                collecting membership fees.
                            </p>
                        </FormField>
                    </FormGrid>
                </FormSection>

                <FormSection title="Student record lock" hint="Instantly freeze student add/edit for all member schools. School leadership can still verify records; use change requests only when this is on.">
                    <FormGrid>
                        <FormField label="">
                            <label class="flex items-center gap-3 cursor-pointer mt-2">
                                <input v-model="profileForm.student_edit_lock_enabled" type="checkbox"
                                       class="w-4 h-4 text-purple-600 rounded">
                                <span class="text-sm font-medium text-gray-700">Lock student edits now (emergency freeze)</span>
                            </label>
                            <p class="text-xs text-gray-500 mt-1">When checked, schools cannot add or edit students until you turn this off. No date schedule — toggle only.</p>
                        </FormField>
                        <FormField label="">
                            <label class="flex items-center gap-3 cursor-pointer mt-2">
                                <input v-model="profileForm.require_student_verification" type="checkbox"
                                       class="w-4 h-4 text-purple-600 rounded">
                                <span class="text-sm font-medium text-gray-700">Require verified students for event registration</span>
                            </label>
                            <p class="text-xs text-gray-500 mt-1">When on, only Sahodaya-verified students can be registered for fest events (Kalotsav, Sports, Kids Fest, Custom, etc.) and Talent Search. Turn off to allow unverified students across all events and items.</p>
                        </FormField>
                    </FormGrid>
                    <p class="text-xs text-gray-500">
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/student-change-requests`" class="link-brand font-semibold">
                            Review pending change requests →
                        </Link>
                    </p>
                </FormSection>

                <FormSection title="Fest class categories" hint="Default age-category labels for Kalotsav / Sports item fees across events.">
                    <FormGrid>
                        <FormField label="Class category scheme" class-extra="sm:col-span-2">
                            <select v-model="profileForm.fest_class_group_scheme" class="field">
                                <option v-for="(label, key) in classGroupSchemeOptions" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                CBSE Kerala uses Category I–IV (classes III–XII). Sahodaya standard uses LP–HSS (classes I–XII).
                                <strong>Class master</strong> uses your Membership → Class master categories (CATEGORY1–4, etc.) for custom events and item fees.
                            </p>
                        </FormField>
                    </FormGrid>
                </FormSection>

                <FormActions>
                    <button type="submit" :disabled="profileForm.processing" class="btn-primary">
                        {{ profileForm.processing ? 'Saving…' : 'Save profile & rules' }}
                    </button>
                </FormActions>
            </form>

            <!-- Tab: Payment Details -->
            <form v-show="activeTab === 'payment'" @submit.prevent="savePaymentDetails" class="space-y-5">
                <div v-if="!paymentForm.payment_bank_name && !paymentForm.payment_account_no && !paymentForm.payment_upi"
                     class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
                    ⚠️ No payment details saved yet. Schools won't know where to pay their membership fee until you fill in at least one field below.
                </div>
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

            <!-- Tab: ZeptoMail API -->
            <form v-show="activeTab === 'email'" @submit.prevent="saveMailSettings" class="space-y-5">
                <FormSection title="ZeptoMail API"
                             hint="Uses the HTTP API (not SMTP) so you avoid ZeptoMail’s ~100 emails/day SMTP cap. Each Sahodaya uses its own token and verified domain. Leave token blank to keep the current one.">
                    <p v-if="profile.mail_configured"
                       class="text-xs font-semibold text-green-700 bg-green-50 border border-green-100 rounded-lg px-3 py-2">
                        ZeptoMail API is configured for this Sahodaya.
                    </p>
                    <p v-else
                       class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                        Not configured — emails fall back to the platform default mail settings.
                    </p>
                    <FormGrid>
                        <FormField label="ZeptoMail region">
                            <select v-model="mailForm.zeptomail_region" class="field">
                                <option value="in">India (api.zeptomail.in)</option>
                                <option value="com">Global (api.zeptomail.com)</option>
                                <option value="eu">EU (api.zeptomail.eu)</option>
                            </select>
                        </FormField>
                        <FormField label="ZeptoMail API token" class-extra="sm:col-span-2"
                                   hint="ZeptoMail → SMTP/API → Send Mail token. Paste the full Zoho-enczapikey … value.">
                            <input v-model="mailForm.mail_password" type="password" class="field" autocomplete="new-password">
                        </FormField>
                        <FormField label="From Address" hint="Verified domain address, e.g. noreply@vadakarasahodaya.in" required>
                            <input v-model="mailForm.mail_from_address" type="email" class="field" required>
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

            <!-- Tab: Receipt Template -->
            <form v-show="activeTab === 'receipt'" @submit.prevent="saveReceiptTemplate" class="space-y-5">
                <FormSection title="Membership Fee Receipt"
                             hint="Separate receipt template for this Sahodaya only. Membership receipts use this header, logo, wording, colour, and numbering when opened or emailed.">
                    <p class="text-xs text-indigo-800 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2 mb-3">
                        Next receipt number: <strong class="font-mono">{{ receiptForm.receipt_next_number }}</strong>
                        · Placeholders for purpose line:
                        <span v-for="(ph, i) in receiptPlaceholders" :key="ph" class="font-mono text-[11px]">{{ ph }}<span v-if="i < receiptPlaceholders.length - 1">, </span></span>
                    </p>
                    <div class="mb-5 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-slate-900">Receipt signatories & seal</p>
                                <p class="text-xs text-slate-500 mt-0.5">Enable representatives, set their name/designation, and upload signature images for this Sahodaya.</p>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" v-model="receiptForm.receipt_signatures_enabled" class="w-4 h-4 text-purple-600 rounded">
                                Show signatures
                            </label>
                        </div>

                        <div class="grid gap-3">
                            <div v-for="(rep, index) in receiptForm.representatives" :key="index"
                                 class="rounded-xl border border-white bg-white p-3 shadow-sm">
                                <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 cursor-pointer">
                                        <input type="checkbox" v-model="rep.enabled" class="w-4 h-4 text-purple-600 rounded">
                                        Representative {{ index + 1 }}
                                    </label>
                                    <button v-if="receiptForm.representatives.length > 1" type="button"
                                            class="text-xs text-red-600 hover:underline"
                                            @click="removeReceiptRepresentative(index)">
                                        Remove
                                    </button>
                                </div>
                                <FormGrid>
                                    <FormField label="Name">
                                        <input v-model="rep.name" class="field" placeholder="Name printed below signature">
                                    </FormField>
                                    <FormField label="Designation / position">
                                        <input v-model="rep.designation" class="field" placeholder="President / Secretary / Treasurer">
                                    </FormField>
                                    <FormField label="Signature image" class-extra="sm:col-span-2"
                                               hint="PNG/JPG/WebP, max 2 MB. Transparent PNG works best.">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <input type="file" accept="image/*"
                                                   class="text-sm text-gray-600"
                                                   @change="uploadReceiptAsset('signature', index, $event)">
                                            <span v-if="rep.signature_path" class="text-xs text-emerald-700 font-semibold">Signature uploaded</span>
                                        </div>
                                    </FormField>
                                </FormGrid>
                            </div>
                        </div>

                        <button v-if="receiptForm.representatives.length < 4" type="button"
                                class="btn-secondary text-xs"
                                @click="addReceiptRepresentative">
                            Add representative
                        </button>

                        <div class="rounded-xl border border-white bg-white p-3 shadow-sm">
                            <div class="flex flex-wrap items-center gap-5 mb-3">
                                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 cursor-pointer">
                                    <input type="checkbox" v-model="receiptForm.show_seal" class="w-4 h-4 text-purple-600 rounded">
                                    Show seal
                                </label>
                                <span v-if="receiptForm.seal_path" class="text-xs text-emerald-700 font-semibold">Seal uploaded</span>
                            </div>
                            <FormGrid>
                                <FormField label="Seal label">
                                    <input v-model="receiptForm.seal_label" class="field" placeholder="Sahodaya Seal">
                                </FormField>
                                <FormField label="Seal image" hint="PNG/JPG/WebP, max 2 MB.">
                                    <input type="file" accept="image/*"
                                           class="text-sm text-gray-600"
                                           @change="uploadReceiptAsset('seal', null, $event)">
                                </FormField>
                            </FormGrid>
                        </div>
                    </div>
                    <FormGrid>
                        <FormField label="Header title" class-extra="sm:col-span-2"
                                   hint="Leave blank to use Sahodaya name in capitals">
                            <input v-model="receiptForm.header_title" class="field" placeholder="MALAPPURAM CENTRAL SAHODAYA (MCS)">
                        </FormField>
                        <FormField label="Subtitle" class-extra="sm:col-span-2">
                            <textarea v-model="receiptForm.header_subtitle" rows="2" class="field"></textarea>
                        </FormField>
                        <FormField label="Registered office line" class-extra="sm:col-span-2"
                                   hint="Shown below the subtitle. Defaults to Profile address if empty.">
                            <textarea v-model="receiptForm.registered_office" rows="2" class="field"
                                      placeholder="Registered office : Anchamile, Pookkottumpadam"></textarea>
                        </FormField>
                        <FormField label="Society registration line" class-extra="sm:col-span-2"
                                   hint="Legal registration details printed under the office line">
                            <input v-model="receiptForm.society_registration" class="field"
                                   placeholder="Reg. Under Societies Registration Act 2025 No. MPM/109/2026">
                        </FormField>
                        <FormField label="Purpose template" class-extra="sm:col-span-2">
                            <input v-model="receiptForm.purpose_template" class="field"
                                   placeholder="Annual Sahodaya membership fee for {{academic_year}}">
                        </FormField>
                        <FormField label="Accent colour">
                            <input v-model="receiptForm.accent_color" type="color" class="field h-10 p-1">
                        </FormField>
                        <FormField label="Next receipt number">
                            <input v-model.number="receiptForm.receipt_next_number" type="number" min="1" class="field font-mono">
                        </FormField>
                        <FormField label="Receiver signature label">
                            <input v-model="receiptForm.receiver_label" class="field">
                        </FormField>
                        <FormField label="Counter signature label">
                            <input v-model="receiptForm.counter_label" class="field">
                        </FormField>
                    </FormGrid>
                    <div class="flex flex-wrap gap-6 mt-3">
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" v-model="receiptForm.show_logo" class="w-4 h-4 text-purple-600 rounded">
                            Show Sahodaya logo on receipt
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" v-model="receiptForm.auto_email_on_verify" class="w-4 h-4 text-purple-600 rounded">
                            Email receipt to school when payment is verified
                        </label>
                    </div>
                </FormSection>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" :disabled="receiptForm.processing" class="btn-primary">Save Receipt Template</button>
                    <a :href="`/sahodaya-admin/${sahodaya.id}/membership/receipt-template/preview`"
                       target="_blank" rel="noopener"
                       class="btn-secondary">Preview sample receipt ↗</a>
                </div>
            </form>

            <!-- Tab: Registration Form -->
            <form v-show="activeTab === 'form'" @submit.prevent="saveFormConfig" class="space-y-5">
                <FormSection title="School Registration Form"
                             hint="Choose which fields appear on the public /school-register page. Principal, vice principal, and events coordinator are off by default — schools add those from their admin profile after registration.">
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
                                <option value="none">No membership fee (₹0)</option>
                            </select>
                            <p v-if="feeForm.membership_fee_type === 'none'" class="text-xs text-slate-600 mt-2">
                                Schools complete annual registration without a payment step. Save this tab to apply.
                            </p>
                        </FormField>
                        <FormField v-if="feeForm.membership_fee_type === 'fixed'"
                                   label="Fixed Amount (₹)" required>
                            <input v-model.number="feeForm.fixed_membership_fee_amount" type="number" step="0.01" min="0.01" class="field"
                                   :class="(!feeForm.fixed_membership_fee_amount || feeForm.fixed_membership_fee_amount <= 0) ? 'border-amber-300 focus:ring-amber-400' : ''"
                                   required>
                            <p v-if="!feeForm.fixed_membership_fee_amount || feeForm.fixed_membership_fee_amount <= 0"
                               class="text-xs text-amber-600 mt-1">
                                Enter an amount greater than ₹0, or choose “No membership fee (₹0)” above.
                            </p>
                        </FormField>
                    </FormGrid>
                </FormSection>

                <FormSection title="Non-affiliated schools (optional)"
                             hint="Enable only if this Sahodaya admits non-affiliated schools with a different membership fee. Leave off for Sahodayas that do not need this — the registration form will hide the option.">
                    <label class="flex items-start gap-3 text-sm">
                        <input v-model="feeForm.allow_non_affiliated_schools" type="checkbox" class="mt-1 rounded border-slate-300">
                        <span>
                            <span class="font-medium text-slate-800">Allow non-affiliated schools</span>
                            <span class="block text-xs text-slate-500 mt-0.5">
                                Adds a “School type” choice on public registration. Non-affiliated schools use the fee below instead of the member fee / slabs.
                            </span>
                        </span>
                    </label>

                    <div v-if="feeForm.allow_non_affiliated_schools" class="grid sm:grid-cols-2 gap-4 pt-3">
                        <FormField label="Non-affiliated fee type">
                            <select v-model="feeForm.non_affiliated_membership_fee_type" class="field">
                                <option value="fixed">Fixed amount</option>
                                <option value="none">No fee (₹0)</option>
                            </select>
                        </FormField>
                        <FormField v-if="feeForm.non_affiliated_membership_fee_type === 'fixed'"
                                   label="Non-affiliated membership fee (₹)" required>
                            <input v-model.number="feeForm.non_affiliated_fixed_membership_fee_amount"
                                   type="number" step="0.01" min="0" class="field" placeholder="e.g. 3000">
                        </FormField>
                    </div>
                </FormSection>

                <FormSection v-if="feeForm.membership_fee_type === 'variable_by_student_count'"
                             :title="`Fee Slabs — ${academicYear}`"
                             hint="Schools are billed the slab matching their student count.">
                    <!-- Add slab -->
                    <div class="mb-4 flex flex-wrap items-end gap-3">
                        <FormField label="Min Students">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="slabForm.min_students" type="number" min="0" class="field w-24">
                            </template>
                        </FormField>
                        <FormField label="Max Students">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="slabForm.max_students" type="number" min="0" class="field w-24" placeholder="∞">
                            </template>
                        </FormField>
                        <FormField label="Amount (₹)">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="slabForm.amount" type="number" step="0.01" min="0" class="field w-32">
                            </template>
                        </FormField>
                        <FormField label="Due Date">
                            <template #default="{ id }">
                                <input :id="id" v-model="slabForm.due_date" type="date" class="field w-36">
                            </template>
                        </FormField>
                        <button type="button" @click="addSlab" class="btn-secondary mb-0.5">Add Slab</button>
                    </div>
                    <!-- Slabs table -->
                    <div v-if="feeSlabs.length" class="overflow-hidden rounded-xl border border-slate-100">
                        <table class="data-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-left px-4 py-2.5 font-semibold text-gray-600 text-xs">Students</th>
                                    <th class="text-right px-4 py-2.5 font-semibold text-gray-600 text-xs">Amount</th>
                                    <th class="text-right px-4 py-2.5 font-semibold text-gray-600 text-xs">Due</th>
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
                                    <td class="px-4 py-3 text-right text-xs text-gray-500">
                                        {{ slab.due_date ? new Date(slab.due_date).toLocaleDateString('en-IN') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button @click="removeSlab(slab)" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState v-else title="No fee slabs yet" description="Add slabs above to bill schools by student count." icon="💰" />
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
                    These windows apply to <strong class="text-gray-700">annual membership registration</strong> for
                    <span class="font-mono">{{ academicYear }}</span> — not day-to-day student record edits (those use admin verify).
                </p>
                <div v-if="!registrationWindow || (!registrationWindow.registration_starts_at && !registrationWindow.registration_ends_at)"
                     class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
                    ⚠️ No membership window configured for <strong>{{ academicYear }}</strong>. Schools can submit annual registration at any time until you set dates below.
                </div>
                <FormSection :title="`Annual membership registration — ${academicYear}`"
                             hint="When member schools can start and finish their annual Sahodaya membership submission.">
                    <FormGrid>
                        <FormField label="Registration opens" hint="Schools can begin annual registration from this date/time">
                            <input v-model="windowForm.registration_starts_at" type="datetime-local" class="field">
                        </FormField>
                        <FormField label="Registration closes" hint="No new annual registrations after this date/time">
                            <input v-model="windowForm.registration_ends_at" type="datetime-local" class="field">
                        </FormField>
                    </FormGrid>
                </FormSection>
                <FormSection :title="`Membership amendment window — ${academicYear}`"
                             hint="Optional: after a school submits, they can amend their membership data only during this period.">
                    <FormGrid>
                        <FormField label="Amendments open" hint="Corrections to submitted registration allowed from">
                            <input v-model="windowForm.edit_open" type="datetime-local" class="field">
                        </FormField>
                        <FormField label="Amendments close" hint="Membership data locked for edits after">
                            <input v-model="windowForm.edit_close" type="datetime-local" class="field">
                        </FormField>
                    </FormGrid>
                    <button type="button" @click="saveWindow" class="btn-primary mt-4">Save membership windows</button>
                </FormSection>
            </div>

            <!-- Tab: Class Categories (configure first) -->
            <div v-show="activeTab === 'class-categories'" class="space-y-5">
                <p class="text-sm text-gray-500 bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                    Set up <strong>class categories</strong> first (Pre-Primary, Primary, Secondary, etc.), then add individual classes under
                    <button type="button" class="underline font-semibold text-purple-700" @click="activeTab = 'class-master'">Class Master</button>.
                    Lower sort numbers appear first in lists.
                </p>

                <FormSection title="Class Categories"
                             hint="Group classes into categories. Disable categories you don't use. Edit sort order to control display sequence.">
                    <div class="space-y-2">
                        <div v-for="cat in sortedGlobalCategories" :key="cat.id"
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
                                            <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-[#041525]/20 rounded-full peer peer-checked:bg-[#041525] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </FormSection>

                <FormSection v-if="customCategories.length" title="Your Custom Categories"
                             hint="Edit or remove categories you added for this Sahodaya.">
                    <div v-for="cat in sortedCustomCategories" :key="cat.id"
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

            <!-- Tab: Class Master -->
            <div v-show="activeTab === 'class-master'" class="space-y-5">
                <p class="text-sm text-gray-500 bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                    Add each class (<strong>1, 2, 3, LKG, UKG…</strong>) and assign it to a category from
                    <button type="button" class="underline font-semibold text-purple-700" @click="activeTab = 'class-categories'">Class Categories</button>.
                    All member schools automatically get this class list — they cannot add their own.
                </p>

                <FormSection title="Classes"
                             hint="Each row is one class. Schools pick from this list when registering students.">
                    <div v-if="sortedMasterClasses.length" class="overflow-hidden rounded-xl border border-slate-100">
                        <table class="data-table">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="text-left px-4 py-2.5 font-semibold w-16">Sort</th>
                                    <th class="text-left px-4 py-2.5 font-semibold">Class</th>
                                    <th class="text-left px-4 py-2.5 font-semibold">Category</th>
                                    <th class="px-4 py-2.5 w-28"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="cls in sortedMasterClasses" :key="cls.id" class="hover:bg-gray-50/80">
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
                    <EmptyState v-else title="No classes yet" description="Add your first class below — all member schools inherit this list." icon="📚" />

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
                                <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-[#041525]/20 rounded-full peer peer-checked:bg-[#041525] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
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

            <!-- Tab: Subject Master -->
            <div v-show="activeTab === 'subjects'" class="space-y-5">
                <p class="text-sm text-gray-500">
                    Subjects defined here are used when schools register their teachers. Schools pick from this list instead of typing subject names.
                </p>

                <FormSection v-if="globalSubjects.length" title="Standard Subjects"
                             hint="Provided by the platform and available to all schools. Add or edit Sahodaya-specific subjects below.">
                    <div class="flex flex-wrap gap-2">
                        <span v-for="s in globalSubjects" :key="s.id"
                              class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-gray-200 bg-gray-50 text-sm text-gray-700">
                            <span class="font-mono text-xs text-gray-500">{{ s.code }}</span>{{ s.label }}
                        </span>
                    </div>
                </FormSection>

                <FormSection title="Your Sahodaya Subjects">
                    <div v-if="customSubjects.length" class="space-y-2 mb-4">
                        <div v-for="s in customSubjects" :key="s.id"
                             class="p-3 rounded-xl border border-purple-100 bg-purple-50/30 text-sm">
                            <template v-if="editingSubjectId === s.id">
                                <div class="flex flex-wrap gap-3 items-end">
                                    <FormField label="Sort" hint="Lower = first">
                                        <input v-model.number="editSubjectForm.sort_order" type="number" min="0" class="field w-20">
                                    </FormField>
                                    <FormField label="Code" :error="editSubjectForm.errors.code">
                                        <input v-model="editSubjectForm.code" class="field w-28">
                                    </FormField>
                                    <FormField label="Subject" :error="editSubjectForm.errors.label">
                                        <input v-model="editSubjectForm.label" class="field w-56">
                                    </FormField>
                                    <label class="flex items-center gap-2 text-xs text-gray-600 mb-2 cursor-pointer">
                                        <input v-model="editSubjectForm.is_active" type="checkbox" class="rounded text-purple-600">
                                        Active
                                    </label>
                                </div>
                                <div class="flex gap-2 mt-3">
                                    <button type="button" @click="saveSubjectEdit(s)" class="btn-secondary text-xs" :disabled="editSubjectForm.processing">Save</button>
                                    <button type="button" @click="cancelSubjectEdit" class="text-xs text-gray-500">Cancel</button>
                                </div>
                            </template>
                            <template v-else>
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="text-xs font-mono text-gray-400 w-6">{{ s.sort_order ?? 0 }}</span>
                                        <span class="font-mono text-xs text-gray-500 bg-white border border-gray-200 px-2 py-0.5 rounded">{{ s.code }}</span>
                                        <span class="font-medium text-gray-700">{{ s.label }}</span>
                                        <span v-if="!s.is_active" class="text-xs text-amber-600">(inactive)</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button type="button" class="text-purple-600 text-xs hover:underline" @click="startSubjectEdit(s)">Edit</button>
                                        <button type="button" class="text-red-500 text-xs hover:underline" @click="removeSubject(s)">Remove</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400 mb-4">No custom subjects yet.</p>

                    <form @submit.prevent="addSubject" class="flex flex-wrap gap-3 items-end">
                        <FormField label="Sort" hint="Lower = first">
                            <input v-model.number="subjectForm.sort_order" type="number" min="0" class="field w-20" :placeholder="String(nextSubjectSort)">
                        </FormField>
                        <FormField label="Code" :error="subjectForm.errors.code">
                            <input v-model="subjectForm.code" class="field w-28" placeholder="MAL">
                        </FormField>
                        <FormField label="Subject" :error="subjectForm.errors.label">
                            <input v-model="subjectForm.label" class="field w-56" placeholder="Malayalam">
                        </FormField>
                        <button type="submit" class="btn-secondary mb-0.5" :disabled="subjectForm.processing">Add Subject</button>
                    </form>
                </FormSection>
            </div>

            <!-- Tab: Age Categories -->
            <div v-show="activeTab === 'age-categories'" class="space-y-5">
                <p class="text-sm text-gray-500">
                    Under-N age bands (U14, U17, Open…) used for item eligibility and fees across every program — not just Sports Meet.
                    These are managed on one shared page; changes here apply everywhere age category is used.
                </p>

                <FormSection title="Sahodaya-wide age reference date"
                             hint="Single default used everywhere age category is computed (student lists, item eligibility) unless a specific event sets its own override. Leave blank to fall back to 31 Dec of the competition year.">
                    <form @submit.prevent="saveAgeCutoff" class="flex flex-wrap items-end gap-3">
                        <FormField label="Reference date">
                            <input v-model="ageCutoffForm.sports_age_cutoff_date" type="date" class="field">
                        </FormField>
                        <button type="submit" class="btn-secondary mb-0.5" :disabled="ageCutoffForm.processing">Save</button>
                        <button v-if="ageCutoffForm.sports_age_cutoff_date" type="button" class="text-xs text-gray-500 mb-2"
                                @click="ageCutoffForm.sports_age_cutoff_date = ''; saveAgeCutoff()">
                            Clear
                        </button>
                    </form>
                </FormSection>

                <FormSection title="Age Categories">
                    <div v-if="ageCategories.length" class="space-y-2 mb-4">
                        <div v-for="g in ageCategories" :key="g.id"
                             class="p-3 rounded-xl border border-purple-100 bg-purple-50/30 text-sm">
                            <template v-if="editingAgeCategoryId === g.id">
                                <div class="flex flex-wrap gap-3 items-end">
                                    <FormField label="Label" :error="editAgeCategoryForm.errors.label">
                                        <input v-model="editAgeCategoryForm.label" class="field w-40">
                                    </FormField>
                                    <FormField label="Under age" v-if="g.group_key !== 'open'" :error="editAgeCategoryForm.errors.under_age">
                                        <input v-model.number="editAgeCategoryForm.under_age" type="number" min="1" max="99" class="field w-20">
                                    </FormField>
                                    <FormField label="Sort" hint="Lower = first">
                                        <input v-model.number="editAgeCategoryForm.sort_order" type="number" min="0" class="field w-20">
                                    </FormField>
                                    <FormField label="Default fee (₹)">
                                        <input v-model.number="editAgeCategoryForm.default_fee" type="number" min="0" step="0.01" class="field w-28">
                                    </FormField>
                                    <label class="flex items-center gap-2 text-xs text-gray-600 mb-2 cursor-pointer">
                                        <input v-model="editAgeCategoryForm.is_active" type="checkbox" class="rounded text-purple-600">
                                        Active
                                    </label>
                                </div>
                                <div class="flex gap-2 mt-3">
                                    <button type="button" @click="saveAgeCategoryEdit(g)" class="btn-secondary text-xs" :disabled="editAgeCategoryForm.processing">Save</button>
                                    <button type="button" @click="editingAgeCategoryId = null" class="text-xs text-gray-500">Cancel</button>
                                </div>
                            </template>
                            <template v-else>
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="text-xs font-mono text-gray-400 w-6">{{ g.sort_order ?? 0 }}</span>
                                        <span class="font-mono text-xs text-gray-500 bg-white border border-gray-200 px-2 py-0.5 rounded">{{ g.group_key }}</span>
                                        <span class="font-medium text-gray-700">{{ g.label }}</span>
                                        <span v-if="g.under_age != null" class="text-xs text-gray-500">Under {{ g.under_age }}</span>
                                        <span v-if="g.default_fee != null" class="text-xs text-gray-500">₹{{ g.default_fee }}</span>
                                        <span v-if="!g.is_active" class="text-xs text-amber-600">(inactive)</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button type="button" class="text-purple-600 text-xs hover:underline" @click="startAgeCategoryEdit(g)">Edit</button>
                                        <button type="button" class="text-red-500 text-xs hover:underline" @click="removeAgeCategory(g)">Remove</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400 mb-4">No age categories yet.</p>

                    <form @submit.prevent="addAgeCategory" class="flex flex-wrap gap-3 items-end">
                        <FormField label="Key" hint="e.g. u14, u17, open">
                            <input v-model="ageCategoryForm.group_key" class="field font-mono w-24" placeholder="u16" required pattern="^(open|u\d{1,2})$">
                        </FormField>
                        <FormField label="Label" :error="ageCategoryForm.errors.label">
                            <input v-model="ageCategoryForm.label" class="field w-40" placeholder="Under 16" required>
                        </FormField>
                        <FormField label="Under age">
                            <input v-model.number="ageCategoryForm.under_age" type="number" min="1" max="99" class="field w-20" :disabled="ageCategoryForm.group_key === 'open'">
                        </FormField>
                        <FormField label="Default fee (₹)">
                            <input v-model.number="ageCategoryForm.default_fee" type="number" min="0" step="0.01" class="field w-28">
                        </FormField>
                        <button type="submit" class="btn-secondary mb-0.5" :disabled="ageCategoryForm.processing">Add Category</button>
                    </form>
                </FormSection>
            </div>
                </main>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

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
    activeAcademicYearRecord:{ type: Object, default: null },
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
    globalSubjects:          { type: Array, default: () => [] },
    customSubjects:          { type: Array, default: () => [] },
    applicationFormFields:   { type: Object, default: () => ({}) },
    applicationFormGroups:   { type: Object, default: () => ({}) },
    receiptTemplate:         { type: Object, default: () => ({}) },
    receiptPlaceholders:     { type: Array, default: () => [] },
    receiptNextNumber:       { type: Number, default: 1 },
    classGroupSchemeOptions: { type: Object, default: () => ({}) },
    ageCategories:           { type: Array, default: () => [] },
    globalAgeCutoffDate:     { type: String, default: null },
});

const tabGroups = [
    {
        title: 'Identity & Access',
        items: [
            { key: 'profile', label: 'Profile & Rules' },
            { key: 'window', label: 'Registration Window' },
            { key: 'form', label: 'Registration Form' },
        ],
    },
    {
        title: 'Fees & Receipts',
        items: [
            { key: 'payment', label: 'Payment Details' },
            { key: 'fees', label: 'Membership Fees' },
            { key: 'receipt', label: 'Receipt Template' },
            { key: 'email', label: 'ZeptoMail API' },
        ],
    },
    {
        title: 'Class & Academic Masters',
        items: [
            { key: 'class-categories', label: 'Class Categories' },
            { key: 'class-master', label: 'Class Master' },
            { key: 'types', label: 'Teaching Types' },
            { key: 'subjects', label: 'Subject Master' },
        ],
    },
    {
        title: 'Fest & Program Config',
        items: [
            { key: 'age-categories', label: 'Age Categories' },
        ],
    },
];

const tabIcons = {
    profile: '🏢',
    window: '📅',
    form: '📝',
    payment: '💳',
    fees: '💰',
    receipt: '📄',
    email: '✉️',
    'class-categories': '🏷️',
    'class-master': '🏫',
    types: '👨‍🏫',
    subjects: '📚',
    'age-categories': '🏆',
};

const tabs = tabGroups.flatMap((g) => g.items);

const tabKeys = tabs.map((t) => t.key);

function resolveInitialTab() {
    const fromUrl = new URLSearchParams(window.location.search).get('tab');
    const legacyTabMap = { categories: 'class-categories' };
    const normalize = (tab) => legacyTabMap[tab] ?? tab;
    if (fromUrl) {
        const tab = normalize(fromUrl);
        if (tabKeys.includes(tab)) {
            return tab;
        }
    }
    const stored = sessionStorage.getItem(`sah-settings-tab-${props.sahodaya?.id}`);
    if (stored) {
        const tab = normalize(stored);
        if (tabKeys.includes(tab)) {
            return tab;
        }
    }
    return 'profile';
}

const activeTab = ref(resolveInitialTab());

watch(activeTab, (tab) => {
    sessionStorage.setItem(`sah-settings-tab-${props.sahodaya?.id}`, tab);
});

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
    zeptomail_region:  props.profile?.zeptomail_region ?? 'in',
    mail_password:     '',
    mail_from_address: props.profile?.mail_from_address ?? '',
    mail_from_name:    props.profile?.mail_from_name ?? '',
});
const testMailForm = useForm({ test_email: props.profile?.contact_email ?? '' });
const receiptForm = useForm({
    ...props.receiptTemplate,
    receipt_signatures_enabled: props.receiptTemplate?.receipt_signatures_enabled ?? true,
    representatives: normalizeReceiptRepresentatives(props.receiptTemplate?.representatives),
    show_seal: props.receiptTemplate?.show_seal ?? false,
    seal_label: props.receiptTemplate?.seal_label ?? 'Sahodaya Seal',
    seal_path: props.receiptTemplate?.seal_path ?? null,
    receipt_next_number: props.receiptNextNumber,
});
const receiptAssetForm = useForm({
    asset_type: '',
    signature_index: null,
    file: null,
});
const logoForm    = useForm({ logo: null });
const windowForm  = useForm({
    academic_year:          props.academicYear,
    registration_starts_at: props.registrationWindow?.registration_starts_local || '',
    registration_ends_at:   props.registrationWindow?.registration_ends_local || '',
    edit_open:              props.registrationWindow?.edit_open_local || '',
    edit_close:             props.registrationWindow?.edit_close_local || '',
});
const slabForm = useForm({ academic_year: props.academicYear, min_students: 0, max_students: null, amount: 0, due_date: '' });
const categoryForm = useForm({ code: '', label: '', sort_order: null });
const editCategoryForm = useForm({ code: '', label: '', sort_order: null });
const editingCategoryId = ref(null);
const editGlobalCategoryForm = useForm({ sort_order: null });
const editingGlobalCategoryId = ref(null);
const classForm = useForm({ name: '', class_category_id: '', display_order: null });
const editClassForm = useForm({ name: '', class_category_id: '', display_order: null });
const editingClassId = ref(null);
const typeForm     = useForm({ code: '', label: '' });
const subjectForm  = useForm({ code: '', label: '', sort_order: null });
const editSubjectForm = useForm({ code: '', label: '', sort_order: null, is_active: true });
const editingSubjectId = ref(null);

// Age categories reuse the general, cross-program /sports-age-groups backend
// (SportsAgeGroupController) rather than duplicating validation here.
const ageCategoryForm = useForm({ group_key: '', label: '', under_age: null, default_fee: null, sort_order: 100 });
const editAgeCategoryForm = useForm({ label: '', under_age: null, sort_order: 0, default_fee: null, is_active: true });
const editingAgeCategoryId = ref(null);
const ageCutoffForm = useForm({ sports_age_cutoff_date: props.globalAgeCutoffDate ?? '' });
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
    allow_non_affiliated_schools: !!props.profile?.allow_non_affiliated_schools,
    non_affiliated_membership_fee_type: props.profile?.non_affiliated_membership_fee_type ?? 'fixed',
    non_affiliated_fixed_membership_fee_amount: props.profile?.non_affiliated_fixed_membership_fee_amount ?? 0,
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

// ─── Setup checklist ──────────────────────────────────────────────────────────
// Each item: { key, label, tab, tabLabel, done }
const setupChecklist = computed(() => {
    const p = props.profile ?? {};
    const regWin = props.registrationWindow;
    // Use reactive form state so checklist / dots update as the user fills fields without saving
    const hasPayment = !!(
        paymentForm.payment_bank_name ||
        paymentForm.payment_account_no ||
        paymentForm.payment_upi
    );
    // Fee: use the fee form's reactive state for immediate feedback
    const feeOk = feeForm.membership_fee_type === 'none'
        ? true
        : (feeForm.membership_fee_type === 'variable_by_student_count'
        ? (props.feeSlabs?.length > 0)
        : ((feeForm.fixed_membership_fee_amount ?? 0) > 0));
    const windowOk = !!(regWin?.registration_starts_at && regWin?.registration_ends_at);
    const receiptTemplateOk = Object.values(props.profile?.receipt_template_json ?? {})
        .some((value) => value !== null && value !== '' && value !== undefined);

    return [
        {
            key: 'prefix',
            label: 'Set a Sahodaya registration prefix (e.g. MLM) — required for all membership numbers.',
            tab: 'profile',
            tabLabel: 'Profile & Rules',
            done: !!profileForm.prefix,
        },
        {
            key: 'academic_year',
            label: 'Activate an academic year — required for fees, windows, and reports.',
            tab: 'profile',
            tabLabel: 'Profile & Rules',
            done: !!props.activeAcademicYearRecord,
        },
        {
            key: 'fee',
            label: feeForm.membership_fee_type === 'none'
                ? 'No membership fee — schools register without payment.'
                : (feeForm.membership_fee_type === 'variable_by_student_count'
                ? 'Add at least one fee slab for the active academic year.'
                : 'Set a fixed membership fee amount greater than ₹0, or choose “No membership fee (₹0)”.'),
            tab: 'fees',
            tabLabel: 'Membership Fees',
            done: feeOk,
        },
        {
            key: 'window',
            label: 'Set annual membership registration open/close dates for the active academic year.',
            tab: 'window',
            tabLabel: 'Registration Window',
            done: windowOk,
        },
        {
            key: 'payment',
            label: 'Add bank or UPI payment details so schools know where to remit fees.',
            tab: 'payment',
            tabLabel: 'Payment Details',
            done: hasPayment,
        },
        {
            key: 'receipt',
            label: 'Configure this Sahodaya’s membership receipt template, numbering, and signature labels.',
            tab: 'receipt',
            tabLabel: 'Receipt Template',
            done: receiptTemplateOk,
        },
        {
            key: 'classes',
            label: 'Add at least one class to the Class Master (LKG, 1, 2, 3…).',
            tab: 'class-master',
            tabLabel: 'Class Master',
            done: (props.masterClasses?.length ?? 0) > 0,
        },
        {
            key: 'mail',
            label: 'Configure ZeptoMail API so notifications go from your Sahodaya email, not the platform default.',
            tab: 'email',
            tabLabel: 'ZeptoMail API',
            done: !!props.profile?.mail_configured,
        },
    ];
});

const incompleteSetup = computed(() => setupChecklist.value.filter(i => !i.done));

// True once at least one item is done (avoids "All configured" on a fresh install)
const setupDoneOnce = computed(() => {
    const list = setupChecklist.value;
    return list.length > 0 && list.every(i => i.done);
});

const tabsWithWarnings = computed(() => {
    const set = new Set();
    for (const item of incompleteSetup.value) {
        set.add(item.tab);
    }
    return set;
});
// ──────────────────────────────────────────────────────────────────────────────

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

const nextSubjectSort = computed(() => {
    if (! props.customSubjects?.length) return 1;
    return Math.max(...props.customSubjects.map(s => s.sort_order ?? 0)) + 1;
});

const sortedGlobalCategories = computed(() =>
    [...(props.globalCategories ?? [])].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || String(a.label).localeCompare(String(b.label))),
);

const sortedCustomCategories = computed(() =>
    [...(props.customCategories ?? [])].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || String(a.label).localeCompare(String(b.label))),
);

const sortedMasterClasses = computed(() =>
    [...(props.masterClasses ?? [])].sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0) || String(a.name).localeCompare(String(b.name))),
);

function saveProfile() { profileForm.put(`/sahodaya-admin/${props.sahodaya.id}/membership/settings`); }
function savePaymentDetails() {
    paymentForm.put(`/sahodaya-admin/${props.sahodaya.id}/membership/payment-details`);
}
function saveMailSettings() {
    mailForm.put(`/sahodaya-admin/${props.sahodaya.id}/membership/mail-settings`, {
        onSuccess: () => mailForm.mail_password = '',
    });
}
function saveReceiptTemplate() {
    receiptForm.put(`/sahodaya-admin/${props.sahodaya.id}/membership/receipt-template`);
}
function normalizeReceiptRepresentatives(representatives = []) {
    const base = Array.isArray(representatives) && representatives.length
        ? representatives
        : [
            { enabled: true, name: '', designation: 'Receiver Signature', signature_path: null },
            { enabled: true, name: '', designation: 'Counter Signature', signature_path: null },
        ];

    return base.slice(0, 4).map((rep) => ({
        enabled: rep.enabled ?? true,
        name: rep.name ?? '',
        designation: rep.designation ?? 'Authorised Signatory',
        signature_path: rep.signature_path ?? null,
    }));
}
function addReceiptRepresentative() {
    if (receiptForm.representatives.length >= 4) return;
    receiptForm.representatives.push({
        enabled: true,
        name: '',
        designation: 'Authorised Signatory',
        signature_path: null,
    });
}
function removeReceiptRepresentative(index) {
    receiptForm.representatives.splice(index, 1);
}
function uploadReceiptAsset(assetType, signatureIndex, event) {
    const file = event.target.files?.[0] ?? null;
    if (!file) return;

    receiptAssetForm.asset_type = assetType;
    receiptAssetForm.signature_index = signatureIndex;
    receiptAssetForm.file = file;
    receiptAssetForm.post(`/sahodaya-admin/${props.sahodaya.id}/membership/receipt-template/assets`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            if (assetType === 'seal') {
                receiptForm.show_seal = true;
            }
        },
        onFinish: () => {
            receiptAssetForm.reset();
            event.target.value = '';
        },
    });
}
function sendTestMail() {
    testMailForm.post(`/sahodaya-admin/${props.sahodaya.id}/membership/mail-settings/test`);
}
function saveFormConfig() { formConfig.put(`/sahodaya-admin/${props.sahodaya.id}/membership/application-form`); }
function saveWindow()  { windowForm.put(`/sahodaya-admin/${props.sahodaya.id}/membership/registration-window`); }
function addSlab()     { slabForm.post(`/sahodaya-admin/${props.sahodaya.id}/membership/fee-slabs`, { onSuccess: () => slabForm.reset('min_students', 'max_students', 'amount', 'due_date') }); }
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
function addSubject() {
    subjectForm.post(`/sahodaya-admin/${props.sahodaya.id}/membership/subjects`, {
        onSuccess: () => subjectForm.reset(),
    });
}
function startSubjectEdit(subject) {
    editingSubjectId.value = subject.id;
    editSubjectForm.code = subject.code;
    editSubjectForm.label = subject.label;
    editSubjectForm.sort_order = subject.sort_order ?? 0;
    editSubjectForm.is_active = subject.is_active !== false;
    editSubjectForm.clearErrors();
}
function cancelSubjectEdit() {
    editingSubjectId.value = null;
    editSubjectForm.reset();
    editSubjectForm.clearErrors();
}
function saveSubjectEdit(subject) {
    editSubjectForm.put(`/sahodaya-admin/${props.sahodaya.id}/membership/subjects/${subject.id}`, {
        preserveScroll: true,
        onSuccess: () => cancelSubjectEdit(),
    });
}
function removeSubject(s) {
    if (!confirm(`Remove subject "${s.label}"?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/membership/subjects/${s.id}`);
}
const ageGroupsBase = `/sahodaya-admin/${props.sahodaya.id}/sports-age-groups`;

function addAgeCategory() {
    ageCategoryForm.post(ageGroupsBase, {
        preserveScroll: true,
        onSuccess: () => ageCategoryForm.reset(),
    });
}
function startAgeCategoryEdit(g) {
    editingAgeCategoryId.value = g.id;
    editAgeCategoryForm.label = g.label;
    editAgeCategoryForm.under_age = g.under_age;
    editAgeCategoryForm.sort_order = g.sort_order ?? 0;
    editAgeCategoryForm.default_fee = g.default_fee;
    editAgeCategoryForm.is_active = g.is_active !== false;
    editAgeCategoryForm.clearErrors();
}
function saveAgeCategoryEdit(g) {
    editAgeCategoryForm.put(`${ageGroupsBase}/${g.id}`, {
        preserveScroll: true,
        onSuccess: () => { editingAgeCategoryId.value = null; },
    });
}
function removeAgeCategory(g) {
    if (!confirm(`Remove ${g.label}? In-use categories will be deactivated instead.`)) return;
    router.delete(`${ageGroupsBase}/${g.id}`, { preserveScroll: true });
}
function saveAgeCutoff() {
    ageCutoffForm.put(`${ageGroupsBase}/global-cutoff`, { preserveScroll: true });
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
</script>
