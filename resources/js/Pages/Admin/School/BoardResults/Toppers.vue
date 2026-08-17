<template>
    <SchoolAdminLayout :title="pageTitle" :school="school" :show-header-title="false">
        <!-- TOP TOOLBAR & HEADER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <Link :href="`/school-admin/${school.id}/board-results?class=${boardResult.class}&academic_year=${urlEncode(boardResult.academic_year)}`" class="text-xs font-semibold text-indigo-600 hover:underline flex items-center gap-1">
                        ← Back to Results Workspace
                    </Link>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    Toppers Management — Class {{ boardResult.class }} ({{ boardResult.academic_year }})
                </h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ isClass12 ? 'Class XII (AISSCE) — 3 topper categories: Overall Stream Toppers, Subject-Wise Toppers, and 90%+ High Achievers.' : 'Class X (AISSE) — manage overall school toppers and score percentages.' }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-bold px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full border border-indigo-100 uppercase">
                    Class {{ boardResult.class }} ({{ boardResult.examination_type }})
                </span>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Overall Toppers</p>
                <p class="text-2xl font-bold text-[#0f3d7a] mt-1">{{ overallTopperCount }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ isClass12 ? 'Stream-grouped topper list' : 'Flat topper list for Class X' }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Subject Entries</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ subjectEntryCount }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ isClass12 ? 'Rows with subject marks saved' : 'Not used for Class X' }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">90%+ Achievers</p>
                <p class="text-2xl font-bold text-violet-600 mt-1">{{ achieversCount }}</p>
                <p class="text-xs text-gray-500 mt-1">Students at or above the achiever threshold</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Active Mode</p>
                <p class="text-sm font-bold text-gray-800 mt-1">{{ activeTabLabel }}</p>
                <p class="text-xs text-gray-500 mt-1">Switch tabs to manage a different topper flow</p>
            </div>
        </div>

        <!-- 3 CATEGORY NAVIGATION TABS (CLASS 12 EXCLUSIVE) -->
        <div v-if="isClass12" class="flex items-center bg-white p-1.5 rounded-2xl shadow-xs border border-gray-200 mb-6 space-x-1 max-w-2xl">
            <button
                type="button"
                @click="activeTab = 'overall'"
                class="flex-1 py-2 text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-2"
                :class="activeTab === 'overall' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
            >
                <span>🏆</span> Overall Stream Toppers ({{ overallTopperCount }})
            </button>

            <button
                type="button"
                @click="activeTab = 'subject'"
                class="flex-1 py-2 text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-2"
                :class="activeTab === 'subject' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
            >
                <span>🎯</span> Subject-Wise Mark Entry ({{ subjectEntryCount }})
            </button>

            <button
                type="button"
                @click="activeTab = 'achievers'"
                class="flex-1 py-2 text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-2"
                :class="activeTab === 'achievers' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
            >
                <span>🌟</span> 90%+ Achievers ({{ achieversCount }})
            </button>
        </div>

        <div class="max-w-5xl space-y-6">
            <!-- TAB 1: OVERALL STREAM TOPPERS -->
            <div v-if="activeTab === 'overall' || !isClass12" class="space-y-6">
                <!-- Bulk add toppers -->
                <div v-if="!editingId" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
                    <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-base">
                                Add {{ isClass12 ? 'Class XII Overall Stream Toppers' : 'Class X School Toppers' }}
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ isClass12 ? 'Enter overall top rankers for Class XII.' : 'Enter overall top rankers for Class X.' }}
                            </p>
                        </div>
                    </div>

                    <form @submit.prevent="submitBatch" class="space-y-5">
                        <div class="max-w-xs">
                            <label class="form-label mb-1 font-semibold">Total Marks (Out of)</label>
                            <div v-if="!isClass12" class="field text-sm font-bold text-indigo-700 bg-indigo-50/40 flex items-center border-indigo-100">
                                {{ classXTotal }}
                                <span class="ml-1.5 text-[10px] font-normal text-gray-400">(admin-locked)</span>
                            </div>
                            <p v-else class="text-[11px] text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 leading-snug">
                                Locked per stream — pick each topper's stream in the table below.
                            </p>
                        </div>

                        <div v-if="isClass12" class="flex flex-wrap items-center justify-between gap-3 bg-indigo-50/50 p-2.5 rounded-xl border border-indigo-100 mb-3">
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
                                        <th class="p-3">Student Name *</th>
                                        <th class="p-3">Gender *</th>
                                        <th v-if="isClass12" class="p-3">Stream *</th>
                                        <th class="p-3">CBSE Roll No</th>
                                        <th class="p-3">Marks Scored *</th>
                                        <th class="p-3">%</th>
                                        <th class="p-3">Photo (Optional)</th>
                                        <th class="p-3 text-right"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="(row, i) in batchForm.toppers" :key="i" class="hover:bg-slate-50/50">
                                        <td class="p-3"><input v-model="row.name" type="text" required class="field text-sm" placeholder="Student name" :disabled="!canEdit"></td>
                                        <td class="p-3">
                                            <select v-model="row.gender" required class="field text-sm w-28" :disabled="!canEdit">
                                                <option value="">— Select —</option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </td>
                                        <td v-if="isClass12" class="p-3">
                                            <select v-model="row.stream_key" required class="field text-sm w-32" :disabled="!canEdit">
                                                <option value="">— Select —</option>
                                                <option v-for="(label, key) in streamOptions" :key="key" :value="key">{{ label }}</option>
                                            </select>
                                            <p class="text-[10px] text-gray-400 mt-0.5" v-if="row.stream_key">Out of {{ rowTotalMarks(row) ?? '—' }}</p>
                                        </td>
                                        <td class="p-3"><input v-model="row.roll_no" type="text" class="field text-sm w-36" placeholder="CBSE Roll No" :disabled="!canEdit"></td>
                                        <td class="p-3"><input v-model.number="row.marks_obtained" type="number" min="0" :max="rowTotalMarks(row) || undefined" required class="field text-sm w-28" placeholder="Marks" :disabled="!canEdit"></td>
                                        <td class="p-3 text-indigo-600 font-bold whitespace-nowrap">{{ rowPercentage(row) }}</td>
                                        <td class="p-3"><input type="file" accept="image/*" class="text-xs w-40" :disabled="!canEdit" @change="row.photo = $event.target.files[0]"></td>
                                        <td class="p-3 text-right">
                                            <button v-if="canEdit && batchForm.toppers.length > 1" type="button" class="text-red-500 hover:text-red-700 text-xs font-semibold" @click="removeRow(i)">Remove</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="Object.keys(batchForm.errors).length" class="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-600 space-y-0.5">
                            <p v-for="(msg, key) in batchForm.errors" :key="key">• {{ msg }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <button v-if="canEdit" type="button" class="btn-secondary text-xs px-3 py-2 font-semibold" @click="addRow">+ Add Row</button>
                            <button v-if="canEdit" type="submit" class="btn-primary text-xs px-5 py-2 font-bold shadow-sm" :disabled="batchForm.processing">
                                Save {{ batchForm.toppers.length }} Overall Topper{{ batchForm.toppers.length > 1 ? 's' : '' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Edit topper (single) -->
                <div v-else class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
                    <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-base">Edit Topper Details</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Update student details, stream, rank or subject marks.</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitEdit" class="space-y-5">
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label class="form-label mb-1">Student Name *</label>
                                <input v-model="form.name" type="text" required class="field text-sm" :disabled="!canEdit">
                            </div>
                            <div>
                                <label class="form-label mb-1">Gender *</label>
                                <select v-model="form.gender" required class="field text-sm" :disabled="!canEdit">
                                    <option value="">— Select —</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div v-if="isClass12">
                                <label class="form-label mb-1">Stream *</label>
                                <select v-model="form.stream_key" required class="field text-sm" @change="onStreamChange">
                                    <option value="science">Science</option>
                                    <option value="commerce">Commerce</option>
                                    <option value="humanities">Humanities</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label mb-1">CBSE Roll No</label>
                                <input v-model="form.roll_no" type="text" class="field text-sm" :disabled="!canEdit" placeholder="CBSE examination roll number">
                            </div>
                            <div>
                                <label class="form-label mb-1">Overall Percentage (%) *</label>
                                <input v-model="form.percentage" type="number" required min="0" max="100" step="0.01" class="field text-sm font-bold text-indigo-700" :disabled="!canEdit">
                            </div>
                            <div>
                                <label class="form-label mb-1">Overall Rank</label>
                                <input v-model="form.rank" type="number" min="1" placeholder="1" class="field text-sm" :disabled="!canEdit">
                            </div>
                            <div>
                                <label class="form-label mb-1">Marks Obtained</label>
                                <input v-model="form.marks_obtained" type="number" min="0" class="field text-sm" placeholder="e.g. 485">
                            </div>
                            <div>
                                <label class="form-label mb-1">Student Photo</label>
                                <input type="file" accept="image/*" class="field text-sm" @change="form.photo = $event.target.files[0]">
                            </div>
                        </div>

                        <div v-if="isClass12 && form.stream_key" class="border-t border-gray-100 pt-5">
                            <h4 class="text-sm font-bold text-gray-800 mb-1">Subject-Wise Marks (Out of 100)</h4>
                            <p class="text-xs text-gray-500 mb-4">Enter marks for each subject. Subject toppers calculate automatically.</p>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div v-for="subject in activeSubjects" :key="subject">
                                    <label class="form-label mb-1 text-xs font-semibold text-gray-600">{{ subject }}</label>
                                    <input
                                        v-model="form.subject_marks[subject]"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="field text-sm"
                                        placeholder="—"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 pt-3">
                            <button v-if="canEdit" type="submit" class="btn-primary text-xs px-5 py-2.5 font-bold shadow-sm" :disabled="form.processing">
                                Save Changes
                            </button>
                            <button v-if="canEdit" type="button" class="btn-secondary text-xs px-4 py-2.5" @click="cancelEdit">Cancel Edit</button>
                        </div>
                    </form>
                </div>

                <!-- OVERALL TOPPERS LIST TABLE -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden divide-y divide-gray-100">
                    <div class="p-5 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm uppercase tracking-wide">
                                Overall Toppers List ({{ overallTopperCount }}{{ topperCap ? ` / ${topperCap}` : '' }})
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">Ranked list of toppers for Class {{ boardResult.class }}.</p>
                        </div>
                    </div>

                    <!-- STREAM-GROUPED LISTING FOR CLASS XII -->
                    <div v-if="isClass12 && sortedToppers.length" class="divide-y divide-gray-100">
                        <div v-for="(group, stream) in sortedToppersByStream" :key="stream" class="p-5">
                            <div class="flex items-center gap-2 mb-4">
                                <span class="text-xs font-bold uppercase tracking-wider px-3 py-1 rounded-full border"
                                      :class="{
                                          'bg-blue-50 text-blue-700 border-blue-200': stream === 'Science',
                                          'bg-emerald-50 text-emerald-700 border-emerald-200': stream === 'Commerce',
                                          'bg-purple-50 text-purple-700 border-purple-200': stream === 'Humanities',
                                          'bg-gray-50 text-gray-700 border-gray-200': !['Science','Commerce','Humanities'].includes(stream),
                                      }">
                                    📚 {{ stream }} Stream ({{ group.length }})
                                </span>
                            </div>
                            <div class="space-y-3">
                                <div v-for="t in group" :key="t.id"
                                     class="flex items-start gap-4 p-4 rounded-xl border border-gray-100 hover:bg-slate-50/60 transition shadow-2xs">
                                    <img v-if="t.photo" :src="t.photo" class="w-12 h-12 rounded-full object-cover border border-gray-200 shrink-0 shadow-xs" alt="">
                                    <div v-else class="w-12 h-12 rounded-full bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-bold shrink-0 text-base shadow-xs">
                                        {{ t.name[0] }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-bold px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-md">#{{ t.rank ?? '—' }}</span>
                                                    <h4 class="font-bold text-gray-900 text-base">{{ t.name }}</h4>
                                                </div>
                                                <p class="text-xs text-gray-500 mt-1 flex flex-wrap items-center gap-3">
                                                    <span v-if="t.roll_no" class="font-medium text-gray-700">CBSE Roll No: {{ t.roll_no }}</span>
                                                    <span v-if="t.marks_obtained && t.total_marks" class="text-gray-600">· {{ t.marks_obtained }} / {{ t.total_marks }} Marks</span>
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-xl font-bold text-emerald-600 tracking-tight">{{ t.percentage }}%</p>
                                            </div>
                                        </div>

                                        <div v-if="t.subject_marks && Object.keys(t.subject_marks).length" class="mt-3 flex flex-wrap gap-2">
                                            <span v-for="(mark, subject) in t.subject_marks" :key="subject"
                                                  class="text-xs px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 border border-slate-200/60">
                                                {{ subject }}: <strong class="font-bold text-indigo-700">{{ mark }}</strong>
                                            </span>
                                        </div>

                                        <div class="mt-3 flex items-center gap-3 text-xs flex-wrap">
                                            <a v-if="t.marksheet_url" :href="t.marksheet_url" target="_blank" class="font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1 rounded-lg border border-emerald-200">
                                                📄 Marksheet ↗
                                            </a>
                                            <span v-if="t.verification_status === 'verified'" class="font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Verified ✅</span>
                                            <span v-else-if="t.verification_status === 'rejected'" class="font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700" :title="t.rejection_reason">Rejected ❌ ({{ t.rejection_reason || 'See note' }})</span>
                                            <span v-else-if="t.marksheet_url" class="font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Pending Verification ⏳</span>
                                            <span v-else class="font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">No Marksheet Uploaded</span>
                                            <label v-if="canEdit" class="cursor-pointer font-semibold px-2.5 py-1 rounded bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200">
                                                📤 {{ t.marksheet_url ? 'Re-upload Marksheet' : 'Upload Marksheet' }}
                                                <input type="file" class="hidden" accept="image/*,application/pdf" @change="uploadStudentMarksheet(t, $event)" />
                                            </label>
                                            <button v-if="canEdit" type="button" class="text-indigo-600 font-semibold hover:underline" @click="startEdit(t)">Edit Details</button>
                                            <button v-if="canEdit" type="button" class="text-red-500 font-semibold hover:underline" @click="remove(t)">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FLAT LISTING FOR CLASS X -->
                    <div v-else-if="sortedToppers.length" class="divide-y divide-gray-100">
                        <div v-for="t in sortedToppers" :key="t.id" class="p-5 hover:bg-slate-50/50 transition">
                            <div class="flex items-start gap-4">
                                <img v-if="t.photo" :src="t.photo" class="w-12 h-12 rounded-full object-cover border border-gray-200 shrink-0 shadow-xs" alt="">
                                <div v-else class="w-12 h-12 rounded-full bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-bold shrink-0 text-base shadow-xs">
                                    {{ t.name[0] }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-md">#{{ t.rank ?? '—' }}</span>
                                                <h4 class="font-bold text-gray-900 text-base">{{ t.name }}</h4>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1 flex flex-wrap items-center gap-3">
                                                <span v-if="t.roll_no" class="font-medium text-gray-700">CBSE Roll No: {{ t.roll_no }}</span>
                                                <span v-if="t.marks_obtained && t.total_marks" class="text-gray-600">· {{ t.marks_obtained }} / {{ t.total_marks }} Marks</span>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xl font-bold text-emerald-600 tracking-tight">{{ t.percentage }}%</p>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center gap-3 text-xs flex-wrap">
                                        <a v-if="t.marksheet_url" :href="t.marksheet_url" target="_blank" class="font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1 rounded-lg border border-emerald-200">
                                            📄 Marksheet ↗
                                        </a>
                                        <span v-if="t.verification_status === 'verified'" class="font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Verified ✅</span>
                                        <span v-else-if="t.verification_status === 'rejected'" class="font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700" :title="t.rejection_reason">Rejected ❌ ({{ t.rejection_reason || 'See note' }})</span>
                                        <span v-else-if="t.marksheet_url" class="font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Pending Verification ⏳</span>
                                        <span v-else class="font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">No Marksheet Uploaded</span>
                                        <label v-if="canEdit" class="cursor-pointer font-semibold px-2.5 py-1 rounded bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200">
                                            📤 {{ t.marksheet_url ? 'Re-upload Marksheet' : 'Upload Marksheet' }}
                                            <input type="file" class="hidden" accept="image/*,application/pdf" @change="uploadStudentMarksheet(t, $event)" />
                                        </label>
                                        <button v-if="canEdit" type="button" class="text-indigo-600 font-semibold hover:underline" @click="startEdit(t)">Edit Details</button>
                                        <button v-if="canEdit" type="button" class="text-red-500 font-semibold hover:underline" @click="remove(t)">Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="p-10 text-center text-gray-400 text-xs">
                        No overall toppers recorded yet.
                    </div>
                </div>
            </div>

            <!-- TAB 2: SUBJECT-WISE MARK ENTRY (CLASS 12 EXCLUSIVE) -->
            <div v-if="isClass12 && activeTab === 'subject'" class="space-y-6">
                <!-- TOP SELECTION BAR: SUBJECT & YEAR -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-bold text-gray-700 uppercase tracking-wide">Subject:</label>
                            <select v-model="selectedSubjectOption" class="field text-xs py-1.5 w-56 font-semibold bg-white">
                                <option value="" disabled>Select Subject</option>
                                <option v-for="subj in masterSubjectList" :key="subj" :value="subj">{{ subj }}</option>
                                <option value="__custom__">+ Add Custom Subject...</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">Academic Year:</span>
                        <span class="text-xs font-bold text-indigo-700 bg-indigo-50 px-3 py-1.5 rounded-lg border border-indigo-100">
                            {{ boardResult.academic_year }}
                        </span>
                    </div>
                </div>

                <!-- Custom Subject Input if selected -->
                <div v-if="selectedSubjectOption === '__custom__'" class="bg-white rounded-xl border border-indigo-200 p-4 shadow-2xs max-w-md">
                    <label class="form-label mb-1 text-xs font-semibold text-indigo-900">Custom Subject Name *</label>
                    <input v-model="customSubjectInput" type="text" required class="field text-sm" placeholder="Enter custom subject name..." :disabled="!canEdit">
                </div>

                <!-- ADD SUBJECT TOPPER FORM -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
                    <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-base">Add Subject Top Scorer</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Enter student name, CBSE roll number, and mark scored out of 100.</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitSubjectTopper" class="grid sm:grid-cols-4 gap-4">
                        <div>
                            <label class="form-label mb-1 font-semibold">Student Name *</label>
                            <input v-model="subjectForm.name" type="text" required class="field text-sm" placeholder="Student full name" :disabled="!canEdit">
                        </div>
                        <div>
                            <label class="form-label mb-1 font-semibold">Gender *</label>
                            <select v-model="subjectForm.gender" required class="field text-sm" :disabled="!canEdit">
                                <option value="">— Select —</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1 font-semibold">CBSE Roll No *</label>
                            <input v-model="subjectForm.roll_no" type="text" required class="field text-sm" placeholder="e.g. 11182743" :disabled="!canEdit">
                        </div>
                        <div>
                            <label class="form-label mb-1 font-semibold">Mark Scored (out of 100) *</label>
                            <input v-model.number="subjectForm.marks" type="number" min="0" max="100" required class="field text-sm font-bold text-emerald-700" placeholder="e.g. 99" :disabled="!canEdit">
                        </div>

                        <div class="sm:col-span-4 flex items-center justify-between pt-2">
                            <p v-if="editingSubjectRow" class="text-xs font-semibold text-indigo-600">
                                Editing {{ editingSubjectRow.name }}'s {{ editingSubjectRow.subject }} mark — save to update, or
                                <button type="button" class="underline" @click="cancelSubjectEdit">cancel</button>.
                            </p>
                            <span v-else></span>
                            <button v-if="canEdit" type="submit" class="btn-primary text-xs px-6 py-2.5 font-bold shadow-sm" :disabled="subjectForm.processing">
                                {{ editingSubjectRow ? '💾 Update Subject Mark' : '+ Save Subject Topper' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- COMMON PROOF DOCUMENT UPLOAD -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-3">
                    <h3 class="font-bold text-gray-900 text-sm uppercase tracking-wide">Common Proof Document (PDF / Image)</h3>
                    <div v-if="boardResult.result_pdf_path" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 flex items-center justify-between shadow-2xs">
                        <div class="flex items-center gap-2 text-xs font-semibold text-emerald-800">
                            <span>✓ Proof Attached</span>
                            <a :href="`/school-admin/${school.id}/board-results/${boardResult.id}/pdf`" target="_blank" class="underline text-indigo-600 hover:text-indigo-800 font-normal">View File ↗</a>
                        </div>
                        <span class="text-[11px] text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded font-medium">Ready</span>
                    </div>
                    <p class="text-xs text-gray-500">Upload the tabulation sheet or result proof document for verification.</p>
                </div>

                <!-- DISPLAY ALL SUBJECT-WISE ENTRIES (every student, every subject — not just the top scorer) -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <h3 class="font-bold text-gray-900 text-sm mb-1 uppercase tracking-wide">Saved Subject-Wise Entries</h3>
                    <p class="text-xs text-gray-500 mb-4">Every student's marks, by subject. Click Edit to update a mark.</p>

                    <div v-if="sortedAllSubjectRows.length" class="overflow-x-auto border border-gray-200 rounded-xl">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b border-gray-200">
                                <tr>
                                    <th class="p-3 cursor-pointer select-none" @click="toggleSubjectSort('subject')">Subject{{ subjectSortArrow('subject') }}</th>
                                    <th class="p-3 cursor-pointer select-none" @click="toggleSubjectSort('name')">Student{{ subjectSortArrow('name') }}</th>
                                    <th class="p-3">Gender</th>
                                    <th class="p-3">Roll No</th>
                                    <th class="p-3 cursor-pointer select-none" @click="toggleSubjectSort('marks')">Marks{{ subjectSortArrow('marks') }}</th>
                                    <th v-if="canEdit" class="p-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="row in sortedAllSubjectRows" :key="row.subject + '-' + row.topper_id" class="hover:bg-slate-50/50">
                                    <td class="p-3">
                                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 bg-indigo-100 px-2 py-0.5 rounded">{{ row.subject }}</span>
                                    </td>
                                    <td class="p-3 font-semibold text-gray-900">{{ row.name }}</td>
                                    <td class="p-3 text-xs text-gray-500">{{ row.gender || '—' }}</td>
                                    <td class="p-3 text-xs text-gray-500">{{ row.roll_no || '—' }}</td>
                                    <td class="p-3 font-bold text-emerald-600">{{ row.marks }} / 100</td>
                                    <td v-if="canEdit" class="p-3 text-right whitespace-nowrap">
                                        <button type="button" @click="editSubjectRow(row)" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold mr-3">✎ Edit</button>
                                        <button type="button" @click="removeSubjectTopper(row)" class="text-xs text-red-500 hover:text-red-700 font-semibold">🗑 Remove</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="p-8 text-center text-gray-400 text-xs">
                        No subject-wise toppers recorded yet. Use the form above to add subject leaders.
                    </div>
                </div>
            </div>

            <!-- TAB 3: 90%+ HIGH ACHIEVERS -->
            <div v-if="isClass12 && activeTab === 'achievers'" class="space-y-6">
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-base">90% &amp; Above High Achievers</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Every student scoring 90% or above overall (Stream-based grouping).</p>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full border border-emerald-200">
                            {{ achievers90.length }} High Achiever{{ achievers90.length === 1 ? '' : 's' }}
                        </span>
                    </div>

                    <div v-if="achievers90.length" class="mt-6 space-y-6">
                        <div v-for="(rows, stream) in achievers90ByStream" :key="stream" class="border border-gray-100 rounded-xl p-4 bg-slate-50/50">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-700 mb-3 flex items-center gap-2">
                                <span>📚</span> {{ stream }} Stream ({{ rows.length }})
                            </h4>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div v-for="t in rows" :key="t.id" class="bg-white rounded-lg border border-gray-200 p-3 shadow-2xs flex items-center justify-between gap-2">
                                    <div>
                                        <p class="font-bold text-gray-900 text-sm">{{ t.name }}</p>
                                        <p v-if="t.roll_no" class="text-xs text-gray-400">CBSE Roll No: {{ t.roll_no }}</p>
                                    </div>
                                    <span class="text-sm font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-100">
                                        {{ t.percentage }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="p-10 text-center text-gray-400 text-xs">
                        No students scoring 90% or above recorded yet.
                    </div>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { computed, ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';
const { confirm, prompt } = useConfirm();

const props = defineProps({
    school:             Object,
    boardResult:        Object,
    isClass12:          { type: Boolean, default: false },
    streamOptions:      { type: Object, default: () => ({}) },
    standardSubjects:   { type: Array, default: () => [] },
    subjectsByStream:   { type: Object, default: () => ({}) },
    subjectWiseLeaders: { type: Array, default: () => [] },
    canEdit:            { type: Boolean, default: true },
    topperCap:          { type: Number, default: null },
    topperCount:        { type: Number, default: 0 },
    marksConfig:        { type: Object, default: () => ({ classX: 500, byStream: {} }) },
});

const default23Subjects = [
    'English core', 'Hindi core', 'Hindi elective', 'Malayalam', 'Sanskrit',
    'Physics', 'Chemistry', 'Biology', 'Mathematics', 'Computer science',
    'Psychology', 'Informatics practices', 'History', 'Sociology',
    'Political science', 'Economics', 'Accountancy', 'Business Studies',
    'Home science', 'Fashion studies', 'Physical education', 'Business administration', 'KTPI',
];

const masterSubjectList = computed(() =>
    props.standardSubjects?.length ? props.standardSubjects : default23Subjects
);

const pageTitle = computed(() => `Toppers — Class ${props.boardResult.class} (${props.boardResult.academic_year})`);
const overallTopperCount = computed(() => sortedToppers.value.length);
const subjectEntryCount = computed(() => allSubjectRows.value.length);
const achieversCount = computed(() => achievers90.value.length);
const activeTabLabel = computed(() => {
    if (!props.isClass12) return 'Overall';
    if (activeTab.value === 'overall') return 'Overall Toppers';
    if (activeTab.value === 'subject') return 'Subject-Wise';
    return 'High Achievers';
});

const activeTab = ref('overall');
const editingId = ref(null);
const selectedSubjectStream = ref('science');

function normalizeStreamKey(value) {
    return String(value ?? '').trim().toLowerCase();
}

function streamDisplayLabel(value) {
    const normalized = normalizeStreamKey(value);
    if (normalized === 'science') return 'Science';
    if (normalized === 'commerce') return 'Commerce';
    if (normalized === 'humanities' || normalized === 'arts') return 'Humanities';
    return String(value ?? '').trim() || 'Unspecified';
}

function urlEncode(val) {
    return encodeURIComponent(val ?? '');
}

const sortedToppers = computed(() =>
    (props.boardResult.toppers ?? [])
        .filter((topper) => (topper.entry_type ?? 'overall') === 'overall')
        .sort((a, b) => (a.rank ?? 999) - (b.rank ?? 999)),
);

/**
 * For Class XII, group overall toppers by stream (Science, Commerce, Humanities)
 * so schools can view each stream's leaders separately rather than one flat list.
 */
const sortedToppersByStream = computed(() => {
    const groups = {};
    for (const t of sortedToppers.value) {
        const stream = streamDisplayLabel(t.stream);
        (groups[stream] ??= []).push(t);
    }
    // Order streams: Science, Commerce, Humanities, then everything else
    const order = ['Science', 'Commerce', 'Humanities'];
    const ordered = {};
    for (const key of order) {
        if (groups[key]) ordered[key] = groups[key];
    }
    for (const [key, val] of Object.entries(groups)) {
        if (!order.includes(key)) ordered[key] = val;
    }
    return ordered;
});

const achievers90 = computed(() =>
    sortedToppers.value
        .filter((t) => t.percentage != null && Number(t.percentage) >= 90)
        .sort((a, b) => Number(b.percentage) - Number(a.percentage)),
);

const achievers90ByStream = computed(() => {
    const groups = {};
    for (const t of achievers90.value) {
        const key = streamDisplayLabel(t.stream) || 'Overall';
        (groups[key] ??= []).push(t);
    }
    return groups;
});

// ── Bulk add ─────────────────────────────────────────────────────────────
// Total marks is admin-locked (BoardResultMarksConfigService) — Class X shares one value,
// Class XII resolves per-row from the selected stream. Schools no longer type this in.
const classXTotal = computed(() => props.marksConfig?.classX ?? 500);

function rowTotalMarks(row) {
    if (props.isClass12) {
        return row.stream_key ? (props.marksConfig?.byStream?.[row.stream_key] ?? null) : null;
    }
    return classXTotal.value;
}

function blankRow() {
    return { name: '', gender: '', stream_key: '', roll_no: '', marks_obtained: '', photo: null };
}

const batchForm = useForm({
    toppers: [blankRow()],
});

const wouldExceedCap = computed(() =>
    props.topperCap != null && (props.topperCount + batchForm.toppers.length) > props.topperCap,
);

function addRow() {
    const lastStream = batchForm.toppers.length > 0
        ? (batchForm.toppers[batchForm.toppers.length - 1].stream_key || '')
        : '';
    batchForm.toppers.push({ name: '', gender: '', stream_key: lastStream, roll_no: '', marks_obtained: '', photo: null });
}

function setStreamForAll(streamKey) {
    if (!streamKey) return;
    batchForm.toppers.forEach(row => {
        row.stream_key = streamKey;
    });
}

function removeRow(i) {
    batchForm.toppers.splice(i, 1);
}

function rowPercentage(row) {
    const total = rowTotalMarks(row);
    if (!total || row.marks_obtained === '' || row.marks_obtained == null) return '—';
    const val = Math.round(((row.marks_obtained / total) * 100) * 100) / 100;
    return `${val}%`;
}

function submitBatch() {
    batchForm.post(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/batch`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            batchForm.reset();
            batchForm.toppers = [blankRow()];
        },
    });
}

// ── Subject Topper Form ──────────────────────────────────────────────────
const selectedSubjectOption = ref('');
const customSubjectInput = ref('');
const editingSubjectRow = ref(null);

const subjectForm = useForm({
    subject: '',
    name: '',
    gender: '',
    roll_no: '',
    marks: '',
});

// Every student's marks for every subject — not just the top scorer per subject —
// so previously-added entries stay visible and editable instead of appearing "lost".
const allSubjectRows = computed(() => {
    const out = [];
    for (const t of props.boardResult.toppers ?? []) {
        const marks = t.subject_marks || {};
        for (const [subject, mark] of Object.entries(marks)) {
            out.push({
                topper_id: t.id,
                subject,
                name: t.name,
                gender: t.gender || '',
                roll_no: t.roll_no || '',
                marks: mark,
            });
        }
    }
    return out.sort((a, b) => a.subject.localeCompare(b.subject) || b.marks - a.marks);
});

// Saved Subject-Wise Entries — sortable datatable (click a header to sort by it)
const subjectSortKey = ref('subject');
const subjectSortDir = ref('asc');

function toggleSubjectSort(key) {
    if (subjectSortKey.value === key) {
        subjectSortDir.value = subjectSortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        subjectSortKey.value = key;
        subjectSortDir.value = 'asc';
    }
}

function subjectSortArrow(key) {
    if (subjectSortKey.value !== key) return '';
    return subjectSortDir.value === 'asc' ? ' ▲' : ' ▼';
}

const sortedAllSubjectRows = computed(() => {
    const dir = subjectSortDir.value === 'asc' ? 1 : -1;
    return [...allSubjectRows.value].sort((a, b) => {
        const av = a[subjectSortKey.value];
        const bv = b[subjectSortKey.value];
        if (av == null && bv == null) return 0;
        if (av == null) return 1;
        if (bv == null) return -1;
        if (typeof av === 'string') return av.localeCompare(bv) * dir;
        return (av - bv) * dir;
    });
});

function editSubjectRow(row) {
    editingSubjectRow.value = row;
    selectedSubjectOption.value = masterSubjectList.value.includes(row.subject) ? row.subject : '__custom__';
    if (selectedSubjectOption.value === '__custom__') customSubjectInput.value = row.subject;
    subjectForm.name = row.name;
    subjectForm.gender = row.gender || '';
    subjectForm.roll_no = row.roll_no || '';
    subjectForm.marks = row.marks;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelSubjectEdit() {
    editingSubjectRow.value = null;
    subjectForm.reset();
    selectedSubjectOption.value = '';
    customSubjectInput.value = '';
}

function submitSubjectTopper() {
    const finalSubject = selectedSubjectOption.value === '__custom__'
        ? customSubjectInput.value.trim()
        : selectedSubjectOption.value;

    if (!finalSubject || !subjectForm.name || !subjectForm.gender || subjectForm.marks === '') return;

    router.post(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/subject-toppers/batch`, {
        subject: finalSubject,
        rows: [{
            topper_id: editingSubjectRow.value?.topper_id ?? null,
            original_subject: editingSubjectRow.value?.subject ?? null,
            name: subjectForm.name,
            gender: subjectForm.gender,
            roll_no: subjectForm.roll_no || null,
            marks: subjectForm.marks,
        }],
    }, {
        preserveScroll: true,
        onSuccess: () => cancelSubjectEdit(),
    });
}

async function removeSubjectTopper(row) {
    if (!(await confirm({ message: `Remove subject topper "${row.name}" for ${row.subject}?`, destructive: true }))) return;

    const existing = (props.boardResult.toppers ?? []).find((t) =>
        t.id === row.topper_id
        || (t.roll_no && row.roll_no && t.roll_no === row.roll_no)
        || t.name.toLowerCase() === row.name.toLowerCase()
    );
    if (!existing) return;

    const updatedSubjectMarks = { ...(existing.subject_marks ?? {}) };
    delete updatedSubjectMarks[row.subject];

    router.post(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/${existing.id}`, {
        ...existing,
        _method: 'put',
        subject_marks: updatedSubjectMarks,
    }, {
        preserveScroll: true,
    });
}

// ── Edit (single) ────────────────────────────────────────────────────────
const form = useForm({
    name: '',
    gender: '',
    roll_no: '',
    percentage: '',
    rank: '',
    stream_key: '',
    total_marks: '',
    marks_obtained: '',
    is_perfect_scorer: false,
    photo: null,
    subject_marks: {},
});

const activeSubjects = computed(() => props.subjectsByStream[form.stream_key] ?? []);

function blankSubjectMarks(streamKey) {
    const marks = {};
    for (const subject of props.subjectsByStream[streamKey] ?? []) {
        marks[subject] = '';
    }
    return marks;
}

function onStreamChange() {
    const existing = { ...form.subject_marks };
    form.subject_marks = blankSubjectMarks(form.stream_key);
    for (const subject of activeSubjects.value) {
        if (existing[subject] !== undefined && existing[subject] !== '') {
            form.subject_marks[subject] = existing[subject];
        }
    }
}

function streamKeyFromTopper(t) {
    if (!t.stream) return '';
    const target = normalizeStreamKey(t.stream);
    const entry = Object.entries(props.streamOptions).find(([, label]) => normalizeStreamKey(label) === target);
    return entry?.[0] ?? 'other';
}

function startEdit(t) {
    editingId.value = t.id;
    form.name = t.name;
    form.gender = t.gender ?? '';
    form.roll_no = t.roll_no ?? '';
    form.percentage = t.percentage;
    form.rank = t.rank ?? '';
    form.stream_key = streamKeyFromTopper(t);
    form.total_marks = t.total_marks ?? '';
    form.marks_obtained = t.marks_obtained ?? '';
    form.is_perfect_scorer = !!t.is_perfect_scorer;
    form.photo = null;
    form.subject_marks = blankSubjectMarks(form.stream_key);
    for (const [subject, mark] of Object.entries(t.subject_marks ?? {})) {
        form.subject_marks[subject] = mark;
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit() {
    editingId.value = null;
    form.reset();
    form.subject_marks = {};
}

function submitEdit() {
    const base = `/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers`;
    form.transform((data) => ({ ...data, _method: 'put' }))
        .post(`${base}/${editingId.value}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });
}

async function remove(t) {
    if (!(await confirm({ message: `Remove topper "${t.name}"?`, destructive: true }))) return;
    router.delete(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/${t.id}`);
}

function uploadStudentMarksheet(topper, event) {
    const file = event.target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('marksheet', file);

    router.post(
        `/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/${topper.id}/marksheet`,
        formData,
        {
            preserveScroll: true,
            onSuccess: () => {
                event.target.value = '';
            },
        }
    );
}
</script>
