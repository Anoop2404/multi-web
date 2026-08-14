<template>
    <SchoolAdminLayout :title="pageTitle" :school="school" :show-header-title="false">
        <!-- TOP SEGMENTED NAVIGATION (CLASS X vs CLASS XII) -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    Board Examination Results
                </h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    Manage Class X (AISSE) &amp; Class XII (AISSCE) board results, upload proof documents, and submit for Sahodaya verification.
                </p>
            </div>
            <InlineAlert :message="alertMessage" type="error" @dismiss="alertMessage = ''" class="w-full mt-3" />

            <div class="flex items-center gap-3 self-start md:self-auto print:hidden">
                <button
                    type="button"
                    @click="printReport"
                    class="btn-secondary text-xs py-1.5 font-bold flex items-center gap-1.5"
                >
                    <span>🖨</span> Print Rank Report
                </button>

                <!-- Class segmented switch -->
                <div class="flex items-center bg-gray-100 p-1 rounded-xl border border-gray-200/80">
                    <Link
                        :href="`/school-admin/${school.id}/board-results?class=10`"
                        class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all"
                        :class="selectedClass === 10 ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    >
                        Class X (AISSE)
                    </Link>
                    <Link
                        :href="`/school-admin/${school.id}/board-results?class=12`"
                        class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all"
                        :class="selectedClass === 12 ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    >
                        Class XII (AISSCE)
                    </Link>
                </div>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6 print:hidden">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Selected Class</p>
                <p class="text-2xl font-bold text-[#0f3d7a] mt-1">Class {{ selectedClass ?? searchClass }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ (selectedClass ?? searchClass) == 12 ? 'AISSCE flow with stream-wise toppers' : 'AISSE flow with one shared topper pool' }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Saved Results</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ results.length }}</p>
                <p class="text-xs text-gray-500 mt-1">Draft, submitted, approved, or published rows</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Current Toppers</p>
                <p class="text-2xl font-bold text-violet-600 mt-1">{{ activeResultContext?.topperCount ?? activeResult?.toppers?.length ?? 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Rows saved in the active board result</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Next Action</p>
                <p class="text-sm font-bold text-gray-800 mt-1">{{ workflowLabel }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ workflowHint }}</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 mb-6 print:hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Board Result Workflow</p>
                    <p class="text-sm text-gray-700 mt-0.5">Use this path to keep the school submission complete and reviewer-friendly.</p>
                </div>
                <span class="text-xs font-semibold px-3 py-1 rounded-full border" :class="statusClass(activeResult?.status || 'draft')">
                    {{ activeResult?.status || 'draft' }}
                </span>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <div v-for="step in workflowSteps" :key="step.key" class="rounded-xl border p-3"
                     :class="step.active ? 'border-[#0f3d7a] bg-[#0f3d7a]/5' : 'border-gray-200 bg-gray-50/60'">
                    <p class="text-[11px] font-bold uppercase tracking-wide" :class="step.active ? 'text-[#0f3d7a]' : 'text-gray-500'">{{ step.label }}</p>
                    <p class="text-xs mt-1" :class="step.active ? 'text-gray-700' : 'text-gray-500'">{{ step.hint }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <!-- WORKING YEAR SELECTOR BAR -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm">
                        {{ selectedClass === 12 ? '12' : '10' }}
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Selected Examination</p>
                        <p class="text-sm font-bold text-gray-800">
                            Class {{ selectedClass ?? searchClass }} ({{ (selectedClass ?? searchClass) == 12 ? 'AISSCE' : 'AISSE' }})
                        </p>
                    </div>
                </div>

                <form @submit.prevent="search" class="flex items-center gap-3 flex-wrap">
                    <div v-if="(selectedClass ?? searchClass) == 12" class="flex items-center gap-2">
                        <label class="text-xs font-semibold text-gray-600 whitespace-nowrap">Stream:</label>
                        <select v-model="searchStream" class="field text-xs py-1.5 w-36 font-semibold bg-white">
                            <option value="science">Science</option>
                            <option value="commerce">Commerce</option>
                            <option value="humanities">Humanities</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold text-gray-600 whitespace-nowrap">Academic Year:</label>
                        <select v-model="searchYear" required class="field text-xs py-1.5 w-48 font-medium">
                            <option value="" disabled>Select Academic Year</option>
                            <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label">
                                {{ academicYearOptionLabel(ay) }}
                            </option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary text-xs px-4 py-1.5 font-semibold">
                        Search
                    </button>
                </form>
            </div>

            <!-- LOCKED ACADEMIC YEAR NOTICE -->
            <div v-if="selectedAcademicYear && !isEntryWindowOpen" class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6 flex items-center gap-3 text-amber-900 print:hidden">
                <span class="text-2xl">🔒</span>
                <div>
                    <h4 class="font-extrabold text-sm">Data Entry Closed for {{ selectedAcademicYear }}</h4>
                    <p class="text-xs text-amber-800 mt-0.5">
                        Board result submission and modifications are only permitted during the active academic year entry window set by Sahodaya Admin. Historical data is read-only.
                    </p>
                </div>
            </div>

            <!-- MAIN WORKSPACE CARD -->
            <div v-if="selectedAcademicYear" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden divide-y divide-gray-100">
                <!-- Workspace Title Bar -->
                <div class="p-5 bg-gradient-to-r from-slate-50 to-white flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold text-gray-900">
                                {{ activeResult ? 'Edit' : 'Create' }} Result — {{ selectedAcademicYear }}
                            </h2>
                            <span v-if="activeResult" class="text-xs px-2.5 py-0.5 rounded-full font-semibold capitalize border" :class="statusClass(activeResult.status)">
                                {{ activeResult.status }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">Fill in aggregate performance data and toppers below.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link v-if="activeResult" :href="`/school-admin/${school.id}/board-results/${activeResult.id}/toppers`" class="btn-primary text-xs px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold flex items-center gap-1.5 shadow-sm border-none">
                            <span>🎯</span> Manage Stream &amp; Subject-Wise Toppers →
                        </Link>
                    </div>
                </div>

                <!-- Rejection Banner if rejected -->
                <div v-if="activeResult?.status === 'rejected'" class="p-4 bg-red-50 border-l-4 border-red-500 text-xs text-red-700">
                    <p class="font-bold text-red-800">Result Submission Rejected by Sahodaya</p>
                    <p class="mt-1">{{ activeResult.rejection_reason || 'Please review and update the summary or proof document, then resubmit for verification.' }}</p>
                </div>

                <form @submit.prevent="submit(false)" class="p-6 space-y-8" enctype="multipart/form-data">
                    <!-- SECTION 1: Aggregate Summary Stats -->
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center">1</span>
                            <h3 class="font-bold text-gray-800 text-sm">Summary Performance Statistics</h3>
                        </div>

                        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="form-label mb-1">Total Appeared (Optional)</label>
                                <input v-model.number="form.total_appeared" type="number" min="0" class="field text-sm" placeholder="e.g. 120" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1">Total Passed (Optional)</label>
                                <input v-model.number="form.pass_count" type="number" min="0" class="field text-sm" placeholder="e.g. 115" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1 font-semibold text-gray-700">Pass % (Calculated)</label>
                                <div class="relative">
                                    <input v-model="form.pass_percent" type="number" step="0.01" min="0" max="100" class="field text-sm font-bold text-emerald-700 bg-emerald-50/40" placeholder="e.g. 95.83" :disabled="!canEditActive">
                                    <span class="absolute right-3 top-2.5 text-xs text-emerald-600 font-bold">%</span>
                                </div>
                            </div>
                            <div>
                                <label class="form-label mb-1 font-semibold text-gray-800">Total Marks (Out of)</label>
                                <div v-if="!isXii" class="field text-sm font-bold text-indigo-700 bg-indigo-50/40 flex items-center border-indigo-100">
                                    {{ classXTotal }}
                                    <span class="ml-1.5 text-[10px] font-normal text-gray-400">(admin-locked)</span>
                                </div>
                                <p v-else class="text-[11px] text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 leading-snug">
                                    Locked per stream — pick each topper's stream in the table below.
                                </p>
                            </div>

                            <div>
                                <label class="form-label mb-1">Distinctions Count</label>
                                <input v-model.number="form.distinctions" type="number" min="0" class="field text-sm" placeholder="0" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1">First Class Count</label>
                                <input v-model.number="form.first_class" type="number" min="0" class="field text-sm" placeholder="0" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1">Highest Mark (%)</label>
                                <input v-model.number="form.highest_mark" type="number" step="0.01" min="0" max="100" class="field text-sm" placeholder="e.g. 98.4" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1">Average Mark (%)</label>
                                <input v-model.number="form.average_mark" type="number" step="0.01" min="0" max="100" class="field text-sm" placeholder="e.g. 78.2" :disabled="!canEditActive">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="form-label mb-1">School Remarks / Notes</label>
                            <textarea v-model="form.remarks" rows="2" class="field text-sm" placeholder="Optional notes for Sahodaya reviewers" :disabled="!canEditActive"></textarea>
                        </div>
                    </div>

                    <!-- SECTION 2: Proof Upload & Verification Status -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-2 border-b border-gray-100 pb-2">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center">2</span>
                                <h3 class="font-bold text-gray-800 text-sm">CBSE Tabulation Sheet / Proof Document</h3>
                            </div>
                            <span v-if="activeResult?.status" class="text-xs px-2.5 py-0.5 rounded-full font-extrabold capitalize border" :class="statusClass(activeResult.status)">
                                Status: {{ activeResult.status }}
                            </span>
                        </div>

                        <!-- Rejection Warning Banner if Rejected -->
                        <div v-if="activeResult?.status === 'rejected' && activeResult.rejection_reason" class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-800 flex items-start gap-3 shadow-xs">
                            <span class="text-lg leading-none">⚠️</span>
                            <div>
                                <p class="font-bold text-red-900">Submission Rejected by Sahodaya Admin</p>
                                <p class="mt-1 font-medium text-red-700">Reason: {{ activeResult.rejection_reason }}</p>
                                <p class="mt-1.5 text-[11px] text-red-600 font-semibold">Please attach an updated tabulation sheet or corrected proof PDF below and resubmit.</p>
                            </div>
                        </div>

                        <!-- Drag & Drop Upload Zone -->
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label mb-1 text-xs font-bold text-slate-700">Official CBSE Tabulation Sheet (Required)</label>

                                <!-- File Preview Box if proof exists -->
                                <div v-if="activeResult?.result_pdf_path || form.result_pdf" class="rounded-xl border border-emerald-300 bg-gradient-to-r from-emerald-50 to-teal-50 p-4 mb-3 shadow-sm">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-lg shrink-0 shadow-xs">
                                                📄
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs font-bold text-emerald-950 truncate">
                                                    {{ form.result_pdf?.name || activeProofLabel }}
                                                </p>
                                                <p class="text-[11px] text-emerald-700 font-medium flex items-center gap-2 mt-0.5">
                                                    <span>{{ form.result_pdf ? `${(form.result_pdf.size / (1024*1024)).toFixed(2)} MB (New file)` : activeProofTypeLabel }}</span>
                                                    <span v-if="activeResult?.uploads?.length" class="bg-emerald-200 text-emerald-900 text-[10px] px-1.5 py-0.2 rounded font-extrabold">
                                                        v{{ activeResult.uploads[0].version }}
                                                    </span>
                                                </p>
                                            </div>
                                        </div>

                                        <button v-if="activeResult?.result_pdf_path" type="button" class="px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs rounded-lg transition shadow-xs shrink-0" @click="openProofPreview(activeResult)">
                                            👁 Preview PDF
                                        </button>
                                    </div>
                                </div>

                                <!-- Drag and Drop Box -->
                                <div
                                    class="border-2 border-dashed rounded-2xl p-5 text-center transition cursor-pointer"
                                    :class="[
                                        dragOver ? 'border-indigo-500 bg-indigo-50/80 scale-[0.99]' : 'border-indigo-200 bg-indigo-50/20 hover:bg-indigo-50/50 hover:border-indigo-400',
                                        !canEditActive ? 'opacity-60 cursor-not-allowed' : ''
                                    ]"
                                    @dragover.prevent="dragOver = true"
                                    @dragleave.prevent="dragOver = false"
                                    @drop.prevent="handleDrop"
                                    @click="triggerFileInput"
                                >
                                    <input ref="fileInputRef" type="file" accept="application/pdf,.doc,.docx,.xls,.xlsx,image/png,image/jpeg,image/jpg,image/webp" class="hidden" :disabled="!canEditActive" @change="onProofFileSelected">
                                    <div class="w-10 h-10 mx-auto rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-lg mb-2 shadow-xs">
                                        📥
                                    </div>
                                    <p class="text-xs font-bold text-slate-800">
                                        {{ form.result_pdf ? 'Click or drag to replace proof document' : 'Click to select or drag & drop CBSE Tabulation Sheet' }}
                                    </p>
                                    <p class="text-[11px] text-slate-500 mt-1 font-medium">
                                        Supports PDF, DOCX, XLSX, JPG, PNG up to 20MB
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label class="form-label mb-1 text-xs font-bold text-slate-700">Additional Attachments (Optional)</label>
                                <div class="border-2 border-dashed border-slate-200 hover:border-slate-300 rounded-2xl p-5 text-center bg-slate-50/50 hover:bg-slate-50 transition cursor-pointer" @click="triggerExtraFiles">
                                    <input ref="extraFilesRef" type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,image/png,image/jpeg,image/jpg,image/webp" class="hidden" :disabled="!canEditActive" @change="form.attachments = Array.from($event.target.files || [])">
                                    <div class="w-10 h-10 mx-auto rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-lg mb-2 shadow-xs">
                                        📎
                                    </div>
                                    <p class="text-xs font-bold text-slate-800">
                                        {{ form.attachments?.length ? `${form.attachments.length} additional file(s) selected` : 'Attach extra summary or breakdown sheets' }}
                                    </p>
                                    <p class="text-[11px] text-slate-500 mt-1 font-medium">
                                        Optional supporting Excel or Word documents
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: School Toppers -->
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center">3</span>
                                <h3 class="font-bold text-gray-800 text-sm">School Toppers</h3>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link :href="`/school-admin/${school.id}/board-results/full-a1-achievers?class=${selectedClass ?? searchClass}`"
                                      class="btn-secondary text-xs px-3.5 py-1.5 font-bold flex items-center gap-1.5 shadow-sm text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border-emerald-200">
                                    <span>🏅</span> Full A1 Achievers →
                                </Link>
                                <Link v-if="(selectedClass ?? searchClass) == 12" :href="`/school-admin/${school.id}/board-results/subject-toppers`"
                                      class="btn-primary text-xs px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold flex items-center gap-1.5 shadow-sm border-none">
                                    <span>🎯</span> Open Subject-Wise Toppers Page →
                                </Link>
                                <span class="text-xs text-gray-500">{{ isXii ? 'Out of marks — set by stream' : `Out of ${classXTotal} marks` }}</span>
                            </div>
                        </div>

                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">
                            📌 Enter your top students here. Also add <strong>every</strong> student scoring 90% or above — they will be included in the Sahodaya Board Results reports and 90%+ Achievers list.
                        </p>

                        <div v-if="isXii" class="flex flex-wrap items-center justify-between gap-3 bg-indigo-50/50 p-2.5 rounded-xl border border-indigo-100 mb-3">
                            <span class="text-xs font-semibold text-slate-700">⚡ Quick Stream Fill for All Rows:</span>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button v-for="(label, key) in streamOptions" :key="key" type="button"
                                        @click="setStreamForAll(key)"
                                        class="btn-secondary text-[11px] py-1 px-2.5 !bg-white hover:!bg-indigo-50 border-slate-200">
                                    Set all to {{ label }}
                                </button>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-xl overflow-hidden shadow-xs">
                            <table class="w-full text-sm">
                                <thead class="text-left text-xs uppercase font-bold text-gray-500 bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="p-3">Student Name</th>
                                        <th class="p-3">Gender</th>
                                        <th v-if="isXii" class="p-3">Stream</th>
                                        <th class="p-3">CBSE Roll No</th>
                                        <th class="p-3">Marks Scored</th>
                                        <th class="p-3">%</th>
                                        <th class="p-3">Marksheet PDF</th>
                                        <th class="p-3 text-right"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="(row, i) in form.toppers" :key="i" class="hover:bg-slate-50/50">
                                        <td class="p-3"><input v-model="row.name" type="text" placeholder="Student name" class="field text-sm" :disabled="!canEditActive"></td>
                                        <td class="p-3">
                                            <select v-model="row.gender" class="field text-sm w-28" :disabled="!canEditActive">
                                                <option value="">— Select —</option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </td>
                                        <td v-if="isXii" class="p-3">
                                            <select v-model="row.stream_key" class="field text-sm w-32" :disabled="!canEditActive">
                                                <option value="">— Select —</option>
                                                <option v-for="(label, key) in streamOptions" :key="key" :value="key">{{ label }}</option>
                                            </select>
                                            <p class="text-[10px] text-gray-400 mt-0.5" v-if="row.stream_key">Out of {{ rowTotalMarks(row) ?? '—' }}</p>
                                        </td>
                                        <td class="p-3"><input v-model="row.roll_no" type="text" placeholder="CBSE Roll No" class="field text-sm w-36" :disabled="!canEditActive"></td>
                                        <td class="p-3"><input v-model.number="row.marks_obtained" type="number" min="0" :max="rowTotalMarks(row) || undefined" placeholder="Marks" class="field text-sm w-28" :disabled="!canEditActive"></td>
                                        <td class="p-3 text-indigo-600 font-bold whitespace-nowrap">{{ rowPercentage(row) }}</td>
                                        <td class="p-3"><input type="file" accept=".pdf,image/*" class="text-xs w-48" :disabled="!canEditActive" @change="row.marksheet = $event.target.files[0]"></td>
                                        <td class="p-3 text-right">
                                            <button v-if="canEditActive && form.toppers.length > 1" type="button" class="text-red-500 hover:text-red-700 text-xs font-semibold" @click="removeRow(i)">Remove</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button v-if="canEditActive" type="button" class="btn-secondary text-xs mt-3 px-3 py-1.5 font-semibold" @click="addRow">+ Add Topper Row</button>
                    </div>

                    <!-- Errors alert -->
                    <div v-if="Object.keys(form.errors).length" class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-600 space-y-1">
                        <p class="font-bold text-red-800">Please review the following errors:</p>
                        <p v-for="(msg, key) in form.errors" :key="key">• {{ msg }}</p>
                    </div>

                    <!-- FOOTER ACTION TOOLBAR -->
                    <div class="border-t border-gray-100 pt-5 flex flex-wrap items-center justify-between gap-4">
                        <div v-if="canEditActive" class="flex flex-wrap items-center gap-3">
                            <button type="button" @click="submit(false)" :disabled="form.processing"
                                    class="btn-secondary text-sm px-5 py-2.5 font-semibold">
                                Save Draft
                            </button>
                            <button type="button" @click="submit(true)" :disabled="form.processing"
                                    class="btn-primary text-sm px-6 py-2.5 font-bold shadow-md bg-emerald-600 hover:bg-emerald-700 border-none">
                                Save &amp; Submit for Verification
                            </button>
                        </div>
                        <div v-else class="text-xs text-amber-700 bg-amber-50 px-3 py-2 rounded-lg border border-amber-200">
                            {{ activeResultContext?.editLockReason || `This result is ${activeResult?.status} and locked from editing.` }}
                        </div>
                    </div>
                </form>
            </div>

            <div v-else class="p-12 text-center text-gray-400 text-sm card bg-white">
                Select an academic year above and click "Search" to begin.
            </div>

            <!-- SAVED RESULTS HISTORY TABLE -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
                <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Saved Results History</h3>
                        <p class="text-xs text-gray-500 mt-0.5">All saved board results for Class {{ selectedClass ?? searchClass }}.</p>
                    </div>
                </div>

                <div v-if="results.length" class="border border-gray-200 rounded-xl overflow-hidden shadow-2xs">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase font-bold text-gray-500 bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="p-3">Academic Year</th>
                                <th class="p-3">Class</th>
                                <th class="p-3">Appeared</th>
                                <th class="p-3">Passed</th>
                                <th class="p-3">Pass %</th>
                                <th class="p-3">Highest %</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="r in results" :key="r.id" class="hover:bg-slate-50/60 transition">
                                <td class="p-3 font-bold text-gray-900">{{ r.academic_year }}</td>
                                <td class="p-3">Class {{ r.class }}</td>
                                <td class="p-3">{{ r.total_appeared }}</td>
                                <td class="p-3">{{ r.pass_count }}</td>
                                <td class="p-3 font-bold text-emerald-600">{{ r.pass_percent }}%</td>
                                <td class="p-3 font-bold text-indigo-600">{{ r.highest_mark ? `${r.highest_mark}%` : '—' }}</td>
                                <td class="p-3">
                                    <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold capitalize border" :class="statusClass(r.status)">
                                        {{ r.status }}
                                    </span>
                                </td>
                                <td class="p-3 text-right">
                                    <button type="button" @click="loadResult(r)" class="text-xs font-semibold text-indigo-600 hover:underline">
                                        Open Result →
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="p-8 text-center text-gray-400 text-xs">
                    No saved board results recorded yet for Class {{ selectedClass ?? searchClass }}.
                </div>
            </div>
        </div>

        <div v-if="proofPreview" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/70" @click="closeProofPreview"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-[85vh] overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Board result proof</p>
                        <h3 class="font-bold text-slate-900 truncate">{{ proofPreview.label }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ proofPreview.typeLabel }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a :href="proofPreview.viewUrl" target="_blank" rel="noopener" class="btn-secondary text-xs">
                            Open in new tab
                        </a>
                        <button type="button" class="btn-ghost text-sm" @click="closeProofPreview">Close</button>
                    </div>
                </div>
                <div class="flex-1 bg-slate-50 overflow-hidden">
                    <img v-if="proofPreview.kind === 'image'" :src="proofPreviewUrl" alt="Proof preview" class="w-full h-full object-contain bg-slate-50">
                    <iframe v-else :src="proofPreviewUrl" class="w-full h-full bg-slate-50" title="Board result proof preview"></iframe>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InlineAlert from '@/Components/ui/InlineAlert.vue';

const alertMessage = ref('');

const props = defineProps({
    school: Object,
    results: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    auditHistory: { type: Array, default: () => [] },
    topperCap: { type: Number, default: 5 },
    selectedClass: { type: Number, default: null },
    academicYearOptions: { type: Array, default: () => [] },
    selectedAcademicYear: { type: String, default: null },
    streamOptions: { type: Object, default: () => ({}) },
    activeResult: { type: Object, default: null },
    activeResultContext: { type: Object, default: null },
    marksConfig: { type: Object, default: () => ({ classX: 500, byStream: {} }) },
});

function academicYearOptionLabel(year) {
    if (year.entry_status === 'open') return `${year.label} (Entry Open)`;
    if (year.entry_status === 'upcoming') return `${year.label} (Entry Opens ${year.board_entry_starts_at})`;
    if (year.entry_status === 'closed') return `${year.label} (Entry Closed)`;
    return year.label;
}

const pageTitle = computed(() => {
    if (props.selectedClass === 12) return 'Class XII Board Results';
    if (props.selectedClass === 10) return 'Class X Board Results';
    return 'Board Results';
});

const proofPreview = ref(null);
const proofPreviewUrl = computed(() => proofPreview.value?.viewUrl ? withPreview(proofPreview.value.viewUrl) : null);
const activeProofLabel = computed(() => proofLabelFor(props.activeResult));
const activeProofTypeLabel = computed(() => proofTypeLabelFor(props.activeResult));

// ── Step 1: search ──────────────────────────────────────────────────────
const searchYear = ref(props.selectedAcademicYear ?? '');
const searchClass = ref(props.selectedClass ? String(props.selectedClass) : '10');
const searchStream = ref('science');

const dragOver = ref(false);
const fileInputRef = ref(null);
const extraFilesRef = ref(null);

function triggerFileInput() {
    if (!canEditActive.value) return;
    fileInputRef.value?.click();
}

function triggerExtraFiles() {
    if (!canEditActive.value) return;
    extraFilesRef.value?.click();
}

function onProofFileSelected(e) {
    const file = e.target.files?.[0];
    if (file) {
        form.result_pdf = file;
    }
}

function handleDrop(e) {
    dragOver.value = false;
    if (!canEditActive.value) return;
    const file = e.dataTransfer?.files?.[0];
    if (file) {
        form.result_pdf = file;
    }
}

function search() {
    router.get(`/school-admin/${props.school.id}/board-results`, {
        class: props.selectedClass ?? searchClass.value,
        academic_year: searchYear.value,
    }, { preserveState: true, preserveScroll: true });
}

function loadResult(r) {
    router.get(`/school-admin/${props.school.id}/board-results`, {
        class: r.class,
        academic_year: r.academic_year,
    }, { preserveScroll: true });
}

function openProofPreview(result) {
    const viewUrl = `/school-admin/${props.school.id}/board-results/${result.id}/pdf`;
    const latest = result?.uploads?.[0];
    proofPreview.value = {
        label: proofLabelFor(result),
        kind: proofKindFor(result),
        typeLabel: proofTypeLabelFor(result),
        viewUrl,
        fileName: latest?.file_name || null,
    };
}

function closeProofPreview() {
    proofPreview.value = null;
}

const activeYearOption = computed(() => {
    return (props.academicYearOptions || []).find(ay => ay.label === props.selectedAcademicYear);
});

const isEntryWindowOpen = computed(() => {
    if (!activeYearOption.value) return true;
    return activeYearOption.value.entry_status === 'open';
});

const canEditActive = computed(() => {
    if (!isEntryWindowOpen.value) return false;
    if (!props.activeResult) return true;
    // Prefer the server-provided context value which uses the model's isEditable()
    // (handles draft, rejected, AND recently-submitted results within the window).
    if (props.activeResultContext?.canEdit !== undefined) {
        return props.activeResultContext.canEdit;
    }
    return ['draft', 'rejected'].includes(props.activeResult.status);
});

const workflowProgress = computed(() => {
    if (!props.selectedAcademicYear) return 1;
    if (!props.activeResult) return 2;
    if (!props.activeResult.result_pdf_path) return 3;
    if ((props.activeResult.toppers?.length ?? 0) === 0) return 4;
    if (props.activeResult.status === 'submitted') return 5;
    if (['verified', 'approved', 'published'].includes(props.activeResult.status)) return 5;
    return 4;
});

const workflowSteps = computed(() => [
    { key: 'year', label: '1. Year', hint: 'Pick the academic year before entering anything.', active: workflowProgress.value >= 1 },
    { key: 'result', label: '2. Result', hint: 'Create or reopen the board result for this class.', active: workflowProgress.value >= 2 },
    { key: 'proof', label: '3. Proof', hint: 'Upload the result PDF before submission.', active: workflowProgress.value >= 3 },
    { key: 'toppers', label: '4. Toppers', hint: 'Add overall and subject toppers.', active: workflowProgress.value >= 4 },
    { key: 'submit', label: '5. Review', hint: 'Send the finished result to Sahodaya.', active: workflowProgress.value >= 5 },
]);

const workflowLabel = computed(() => {
    if (!props.selectedAcademicYear) return 'Choose academic year';
    if (!props.activeResult) return 'Create result';
    if (!props.activeResult.result_pdf_path) return 'Upload proof';
    if ((props.activeResult.toppers?.length ?? 0) === 0) return 'Add toppers';
    if (props.activeResult.status === 'draft' || props.activeResult.status === 'rejected') return 'Submit for review';
    if (props.activeResult.status === 'submitted') return 'Awaiting Sahodaya';
    if (props.activeResult.status === 'verified') return 'Verify next step';
    if (props.activeResult.status === 'approved') return 'Ready to publish';
    return 'Locked / published';
});

const workflowHint = computed(() => {
    if (!props.selectedAcademicYear) return 'Start by selecting the year and class.';
    if (!props.activeResult) return 'Save the board result, then add proof and topper rows.';
    if (!props.activeResult.result_pdf_path) return 'Attach the CBSE proof document so verification can begin.';
    if ((props.activeResult.toppers?.length ?? 0) === 0) return 'Use the toppers page to add overall and subject-wise rows.';
    return 'Use the status button when you are ready to move it to Sahodaya.';
});

// Admin-locked "out of" marks — schools no longer type this in. Class X is one shared
// value; Class XII varies per stream, so each topper row resolves its own total once a
// stream is picked (see marksConfig.byStream, keyed by the same keys as streamOptions).
const isXii = computed(() => Number(props.selectedClass ?? searchClass.value) === 12);
const classXTotal = computed(() => props.marksConfig?.classX ?? 500);

function rowTotalMarks(row) {
    if (isXii.value) {
        return row.stream_key ? (props.marksConfig?.byStream?.[row.stream_key] ?? null) : null;
    }
    return classXTotal.value;
}

function resolveStreamKey(raw) {
    if (!raw) return searchStream.value || 'science';
    const lower = String(raw).toLowerCase().trim();
    for (const [key, label] of Object.entries(props.streamOptions ?? {})) {
        if (key.toLowerCase() === lower || String(label).toLowerCase() === lower) {
            return key;
        }
    }
    return raw;
}

function blankRow() {
    return { name: '', gender: '', stream_key: searchStream.value || 'science', roll_no: '', marks_obtained: '', photo: null };
}

function resultToFormData(r) {
    const loadedToppers = (r?.toppers && r.toppers.length > 0)
        ? r.toppers.map(t => ({
            id: t.id,
            name: t.name || '',
            gender: t.gender || '',
            stream_key: resolveStreamKey(t.stream || (t.exam_stream?.slug)),
            roll_no: t.roll_no || '',
            marks_obtained: t.marks_obtained ?? t.total_marks ?? '',
            photo: null,
        }))
        : [blankRow()];

    return {
        class: props.selectedClass ? String(props.selectedClass) : searchClass.value,
        academic_year: props.selectedAcademicYear ?? '',
        total_appeared: r?.total_appeared ?? '',
        pass_count: r?.pass_count ?? '',
        pass_percent: r?.pass_percent ?? '',
        distinctions: r?.distinctions ?? '',
        first_class: r?.first_class ?? '',
        highest_mark: r?.highest_mark ?? '',
        average_mark: r?.average_mark ?? '',
        total_marks: r?.total_marks || props.marksConfig?.classX || 500,
        remarks: r?.remarks ?? '',
        result_pdf: null,
        attachments: [],
        toppers: loadedToppers,
    };
}

const form = useForm(resultToFormData(props.activeResult));

watch(() => [props.activeResult, props.selectedAcademicYear], () => {
    Object.assign(form, resultToFormData(props.activeResult));
    form.clearErrors();
});

// Auto-calculate Pass % when total_appeared & pass_count are entered
watch(() => [form.total_appeared, form.pass_count], ([appeared, passed]) => {
    if (appeared && Number(appeared) > 0 && passed !== '' && passed != null) {
        const calculated = Math.round((Number(passed) / Number(appeared)) * 10000) / 100;
        if (calculated >= 0 && calculated <= 100) {
            form.pass_percent = calculated;
        }
    }
});

// Auto-suggest highest mark from toppers if blank
watch(() => form.toppers, (rows) => {
    if (form.highest_mark === '' || form.highest_mark == null) {
        const percentages = rows
            .filter((r) => r.marks_obtained !== '' && r.marks_obtained != null && rowTotalMarks(r))
            .map((r) => Math.round((r.marks_obtained / rowTotalMarks(r)) * 10000) / 100);
        if (percentages.length > 0) {
            const maxPerc = Math.max(...percentages);
            if (maxPerc > 0) {
                form.highest_mark = maxPerc;
            }
        }
    }
}, { deep: true });

// Prefer the per-result cap (correctly scoped to this result's own class) over the
// page-load default, which previously could reflect the wrong class's quota.
const effectiveTopperCap = computed(() => props.activeResultContext?.topperCap ?? props.topperCap);

const wouldExceedCap = computed(() => {
    if (!effectiveTopperCap.value) return false;
    const existingCount = props.activeResult?.toppers?.length ?? 0;
    const validNew = form.toppers.filter((r) => r.name && r.marks_obtained !== '').length;
    return (existingCount + validNew) > effectiveTopperCap.value;
});

function addRow() {
    const lastStream = form.toppers.length > 0
        ? (form.toppers[form.toppers.length - 1].stream_key || searchStream.value || '')
        : (searchStream.value || '');
    form.toppers.push({ name: '', gender: '', stream_key: lastStream, roll_no: '', marks_obtained: '', photo: null });
}

function setStreamForAll(streamKey) {
    if (!streamKey) return;
    form.toppers.forEach(row => {
        row.stream_key = streamKey;
    });
}

function removeRow(i) {
    form.toppers.splice(i, 1);
}

function rowPercentage(row) {
    const total = rowTotalMarks(row);
    if (!total || row.marks_obtained === '' || row.marks_obtained == null) return '—';
    const val = Math.round(((row.marks_obtained / total) * 100) * 100) / 100;
    return `${val}%`;
}

function statusClass(s) {
    switch (s) {
        case 'verified': case 'approved': case 'published':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'submitted':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'rejected':
            return 'bg-red-50 text-red-700 border-red-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
}

function submit(submitForReview) {
    const payload = {
        ...form.data(),
        submit_for_review: submitForReview,
    };

    if (props.activeResult) {
        router.post(`/school-admin/${props.school.id}/board-results/${props.activeResult.id}`, {
            ...payload,
            _method: 'put',
        }, { forceFormData: true });
    } else {
        router.post(`/school-admin/${props.school.id}/board-results`, payload, {
            forceFormData: true,
        });
    }
}

// ── Step 3: Class XII Embedded Subject-Wise Entry ──────────────────────────
const section3Tab = ref('overall');
const selectedSubjectOption = ref('');
const customSubjectInput = ref('');
const subjectForm = ref({ name: '', roll_no: '', marks: '' });

const default23Subjects = [
    'English core', 'Hindi core', 'Hindi elective', 'Malayalam', 'Sanskrit',
    'Physics', 'Chemistry', 'Biology', 'Mathematics', 'Computer science',
    'Psychology', 'Informatics practices', 'History', 'Sociology',
    'Political science', 'Economics', 'Accountancy', 'Business Studies',
    'Home science', 'Fashion studies', 'Physical education', 'Business administration', 'KTPI'
];

const masterSubjects = computed(() =>
    props.activeResultContext?.standardSubjects?.length
        ? props.activeResultContext.standardSubjects
        : default23Subjects
);

const subjectWiseLeaders = computed(() =>
    props.activeResultContext?.subjectWiseLeaders ?? []
);

function saveSubjectTopper() {
    const finalSubject = selectedSubjectOption.value === '__custom__'
        ? customSubjectInput.value.trim()
        : selectedSubjectOption.value;

    if (!finalSubject || !subjectForm.value.name || subjectForm.value.marks === '') return;

    if (!props.activeResult) {
        alertMessage.value = 'Please click \"Save Draft\" first to initialize this board result before adding subject toppers.';
        return;
    }

    const existing = (props.activeResult.toppers ?? []).find(
        (t) => t.name.toLowerCase() === subjectForm.value.name.toLowerCase()
    );

    if (existing) {
        const currentSubjectMarks = { ...(existing.subject_marks ?? {}) };
        currentSubjectMarks[finalSubject] = subjectForm.value.marks;

        router.post(`/school-admin/${props.school.id}/board-results/${props.activeResult.id}/toppers/${existing.id}`, {
            ...existing,
            _method: 'put',
            subject_marks: currentSubjectMarks,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedSubjectOption.value = '';
                customSubjectInput.value = '';
                subjectForm.value = { name: '', roll_no: '', marks: '' };
            },
        });
    } else {
        const subjectMarks = {};
        subjectMarks[finalSubject] = subjectForm.value.marks;

        router.post(`/school-admin/${props.school.id}/board-results/${props.activeResult.id}/toppers/single`, {
            name: subjectForm.value.name,
            roll_no: subjectForm.value.roll_no,
            percentage: subjectForm.value.marks,
            marks_obtained: subjectForm.value.marks,
            total_marks: 100,
            subject_marks: subjectMarks,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedSubjectOption.value = '';
                customSubjectInput.value = '';
                subjectForm.value = { name: '', roll_no: '', marks: '' };
            },
        });
    }
}

function removeSubjectTopper(row) {
    if (!confirm(`Remove subject topper "${row.name}" for ${row.subject}?`)) return;

    const existing = (props.activeResult.toppers ?? []).find(
        (t) => t.name.toLowerCase() === row.name.toLowerCase()
    );

    if (!existing) return;

    const updatedSubjectMarks = { ...(existing.subject_marks ?? {}) };
    delete updatedSubjectMarks[row.subject];

    router.post(`/school-admin/${props.school.id}/board-results/${props.activeResult.id}/toppers/${existing.id}`, {
        ...existing,
        _method: 'put',
        subject_marks: updatedSubjectMarks,
    }, {
        preserveScroll: true,
    });
}

function printReport() {
    window.print();
}

function proofLabelFor(result) {
    const upload = result?.uploads?.[0];
    return upload?.file_name || (result?.result_pdf_path ? basename(result.result_pdf_path) : 'Latest proof');
}

function proofKindFor(result) {
    const upload = result?.uploads?.[0];
    const name = upload?.file_name || result?.result_pdf_path || '';
    const ext = String(name).split('.').pop()?.toLowerCase();

    if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) return 'image';
    if (ext === 'pdf') return 'pdf';
    if (['doc', 'docx', 'xls', 'xlsx'].includes(ext)) return 'document';
    return 'file';
}

function proofTypeLabelFor(result) {
    switch (proofKindFor(result)) {
        case 'image': return 'Image proof';
        case 'pdf': return 'PDF proof';
        case 'document': return 'Document proof';
        default: return 'Proof file';
    }
}

function withPreview(url) {
    return `${url}${url.includes('?') ? '&' : '?'}preview=1`;
}

function basename(path) {
    return String(path || '').split('/').pop() || 'Latest proof';
}
</script>
