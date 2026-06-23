<template>
    <SchoolAdminLayout title="Students" :school="school">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600">
                    <strong class="text-[#0f3d7a]">{{ students.total ?? 0 }}</strong>
                    {{ (students.total ?? 0) === 1 ? 'student' : 'students' }}
                    <span v-if="hasActiveFilters" class="text-gray-400"> · filtered</span>
                </p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="openImportModal"
                            class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm font-medium hover:bg-gray-50 transition">
                        Import CSV
                    </button>
                    <button type="button" @click="openRegisterModal"
                            :disabled="!schoolClasses.length"
                            :title="!schoolClasses.length ? 'Classes are configured by your Sahodaya' : ''"
                            class="sa-btn-primary inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
                        + Register Student
                    </button>
                </div>
            </div>

            <div v-if="!school.school_prefix"
                 class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg text-sm">
                Set your
                <Link :href="`/school-admin/${school.id}/setup/code`" class="font-semibold underline">school code</Link>
                before managing students.
            </div>

            <div v-else-if="!schoolClasses.length"
                 class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg text-sm">
                No classes are configured for your Sahodaya yet. Please contact your Sahodaya admin to set up the class master under Configuration.
            </div>

            <SahodayaDataTable
                :columns="columns"
                :links="students.links"
                :meta="{ from: students.from, to: students.to, total: students.total }"
                :sort="filters.sort"
                :dir="filters.dir"
                :has-rows="!!students.data?.length"
                empty="No students found."
                @sort="toggleSort"
            >
                <template #toolbar>
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Category</label>
                                <select v-model="filterForm.class_category_id" @change="onCategoryChange"
                                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20 focus:border-[#0f3d7a]/40">
                                    <option :value="null">All categories</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Class</label>
                                <select v-model="filterForm.school_class_id"
                                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20 focus:border-[#0f3d7a]/40">
                                    <option :value="null">All classes</option>
                                    <option v-for="c in filteredClasses" :key="c.id" :value="c.id">Class {{ c.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Status</label>
                                <select v-model="filterForm.status"
                                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20 focus:border-[#0f3d7a]/40">
                                    <option value="active">Active</option>
                                    <option value="all">All statuses</option>
                                    <option value="transferred">Transferred</option>
                                    <option value="graduated">Graduated</option>
                                    <option value="withdrawn">Withdrawn</option>
                                </select>
                            </div>
                            <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-1">
                                <button type="button" @click="applyFilters"
                                        class="flex-1 bg-[#0f3d7a] hover:bg-[#1a4f8c] text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                                    Apply
                                </button>
                                <button v-if="hasActiveFilters" type="button" @click="clearFilters"
                                        class="px-3 py-2 rounded-lg text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition">
                                    Clear
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-2 sm:items-end">
                            <div class="flex-1 max-w-md">
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Search</label>
                                <input v-model="filterForm.search" type="search" placeholder="Name, reg no, email, roll no…"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20 focus:border-[#0f3d7a]/40"
                                       @keyup.enter="applyFilters">
                            </div>
                            <button type="button" @click="applyFilters"
                                    class="sm:mb-0.5 px-4 py-2 rounded-lg border border-[#bfdbfe] bg-[#eff6ff] text-[#0f3d7a] text-sm font-semibold hover:bg-[#dbeafe] transition">
                                Search
                            </button>
                        </div>
                    </div>
                </template>

                <tr v-for="student in students.data" :key="student.id" class="hover:bg-gray-50/80">
                    <td class="px-4 py-3 w-14">
                        <button type="button" @click="openEditModal(student)"
                                class="relative w-10 h-10 rounded-full overflow-hidden border border-gray-200 bg-gray-100 flex items-center justify-center hover:ring-2 hover:ring-[#0f3d7a]/20 transition"
                                title="Edit student">
                            <img v-if="student.photo_url" :src="student.photo_url" :alt="student.name"
                                 class="w-full h-full object-cover">
                            <span v-else class="text-xs text-gray-400 font-semibold">{{ initials(student.name) }}</span>
                        </button>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ student.name }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ student.reg_no || student.admission_number || '—' }}</td>
                    <td class="px-4 py-3 text-xs text-gray-600 capitalize">{{ formatGender(student.gender) }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ formatDate(student.dob) }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ student.parent_email || '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ student.school_class?.name || '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium capitalize"
                              :class="statusClass(student.status)">{{ student.status }}</span>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <button type="button" @click="openEditModal(student)"
                                class="text-xs font-semibold text-[#0f3d7a] hover:underline mr-3">Edit</button>
                        <button v-if="!student.user_id" type="button" @click="openPortalModal(student)"
                                class="text-xs font-semibold text-indigo-600 hover:underline mr-3">Portal</button>
                        <button type="button" @click="remove(student)"
                                class="text-xs text-red-400 hover:text-red-600 hover:underline">Remove</button>
                    </td>
                </tr>
            </SahodayaDataTable>

            <p v-if="!students.data?.length && school.school_prefix && schoolClasses.length"
               class="text-center text-sm text-gray-500 -mt-2">
                <button type="button" @click="openRegisterModal" class="text-[#0f3d7a] font-semibold hover:underline">
                    Register your first student
                </button>
            </p>
        </div>

        <!-- Edit student modal -->
        <div v-if="showEdit && editingStudent" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="closeEditModal"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-[#f0f9ff] to-white">
                    <div>
                        <h3 class="font-bold text-[#041525]">Edit Student</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Update profile, class, gender, and contact details</p>
                    </div>
                    <button type="button" @click="closeEditModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>

                <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-full overflow-hidden border border-gray-200 bg-gray-100 shrink-0">
                            <img v-if="editPhotoPreview || editingStudent.photo_url"
                                 :src="editPhotoPreview || editingStudent.photo_url"
                                 :alt="editForm.name" class="w-full h-full object-cover">
                            <span v-else class="w-full h-full flex items-center justify-center text-sm text-gray-400 font-semibold">
                                {{ initials(editForm.name || editingStudent.name) }}
                            </span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Profile photo</label>
                            <input type="file" accept="image/*" @change="onEditPhotoChange"
                                   class="w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#f0f9ff] file:text-[#0f3d7a]">
                            <p class="text-xs text-gray-400 mt-1">JPG or PNG, max 2 MB</p>
                            <p v-if="editForm.errors.photo" class="text-xs text-red-500 mt-1">{{ editForm.errors.photo }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Reg. No.</label>
                        <input :value="editingStudent.admission_number || '—'" type="text" readonly
                               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-gray-50 text-gray-500 font-mono cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Full name *</label>
                        <input v-model="editForm.name" type="text" required
                               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20">
                        <p v-if="editForm.errors.name" class="text-xs text-red-500 mt-1">{{ editForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Class *</label>
                        <select v-model="editForm.school_class_id" required
                                class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20">
                            <option value="">Select class</option>
                            <option v-for="c in schoolClassesSorted" :key="c.id" :value="c.id">
                                {{ formatClassOption(c) }}
                            </option>
                        </select>
                        <p v-if="editForm.errors.school_class_id" class="text-xs text-red-500 mt-1">{{ editForm.errors.school_class_id }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Gender *</label>
                            <select v-model="editForm.gender" required
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20">
                                <option value="">Select gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                            <p v-if="editForm.errors.gender" class="text-xs text-red-500 mt-1">{{ editForm.errors.gender }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Date of birth <span class="font-normal text-gray-400">(optional)</span></label>
                            <input v-model="editForm.dob" type="date"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20">
                            <p v-if="editForm.errors.dob" class="text-xs text-red-500 mt-1">{{ editForm.errors.dob }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Email <span class="font-normal text-gray-400">(optional)</span></label>
                        <input v-model="editForm.parent_email" type="email" placeholder="student@example.com"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20">
                        <p v-if="editForm.errors.parent_email" class="text-xs text-red-500 mt-1">{{ editForm.errors.parent_email }}</p>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="closeEditModal" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                        <button type="submit" :disabled="editForm.processing"
                                class="sa-btn-primary px-5 py-2.5 rounded-lg text-sm font-semibold disabled:opacity-50">
                            Save changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Register student modal -->
        <div v-if="showRegister" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="closeRegisterModal"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-[#f0f9ff] to-white">
                    <div>
                        <h3 class="font-bold text-[#041525]">Register Student</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Sahodaya reg no. assigned automatically</p>
                    </div>
                    <button type="button" @click="closeRegisterModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>

                <div v-if="!schoolClasses.length" class="p-6 text-sm text-amber-800">
                    Classes are set by your Sahodaya. Contact your Sahodaya admin if no classes appear here.
                </div>

                <form v-else @submit.prevent="submitRegister" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Class *</label>
                        <select v-model="registerForm.school_class_id" required
                                class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20">
                            <option value="">Select class</option>
                            <option v-for="c in schoolClassesSorted" :key="c.id" :value="c.id">
                                {{ formatClassOption(c) }}
                            </option>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Classes are assigned by your Sahodaya (e.g. Class 10 in Secondary).</p>
                        <p v-if="registerForm.errors.school_class_id" class="text-xs text-red-500 mt-1">{{ registerForm.errors.school_class_id }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Full name *</label>
                        <input v-model="registerForm.name" type="text" required placeholder="Rahul Kumar" autofocus
                               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20">
                        <p v-if="registerForm.errors.name" class="text-xs text-red-500 mt-1">{{ registerForm.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Gender *</label>
                            <select v-model="registerForm.gender" required
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20">
                                <option value="">Select gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                            <p v-if="registerForm.errors.gender" class="text-xs text-red-500 mt-1">{{ registerForm.errors.gender }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Date of birth <span class="font-normal text-gray-400">(optional)</span></label>
                            <input v-model="registerForm.dob" type="date"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20">
                            <p v-if="registerForm.errors.dob" class="text-xs text-red-500 mt-1">{{ registerForm.errors.dob }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Portal email <span class="font-normal text-gray-400">(optional)</span></label>
                        <input v-model="registerForm.email" type="email" placeholder="student@example.com"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20">
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="registerForm.create_login" type="checkbox"> Create student portal login
                    </label>
                    <div v-if="registerForm.create_login">
                        <input v-model="registerForm.password" type="password" placeholder="Min 8 characters" minlength="8"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm">
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="closeRegisterModal" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                        <button type="submit" :disabled="registerForm.processing"
                                class="sa-btn-primary px-5 py-2.5 rounded-lg text-sm font-semibold disabled:opacity-50">
                            Register Student
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Portal login modal -->
        <div v-if="showPortal && portalStudent" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="closePortalModal"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md border p-6 space-y-4">
                <h3 class="font-bold">Portal login — {{ portalStudent.name }}</h3>
                <input v-model="portalForm.email" type="email" placeholder="Email" class="w-full border rounded-lg px-3 py-2 text-sm" required>
                <input v-model="portalForm.password" type="password" placeholder="Password (min 8)" class="w-full border rounded-lg px-3 py-2 text-sm" required>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="closePortalModal" class="text-sm text-gray-500">Cancel</button>
                    <button type="button" @click="submitPortal" :disabled="portalForm.processing"
                            class="sa-btn-primary px-4 py-2 rounded-lg text-sm font-semibold">Create login</button>
                </div>
            </div>
        </div>

        <!-- Import CSV modal -->
        <div v-if="showImport" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="closeImportModal"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg border border-gray-100 overflow-hidden max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-[#f0f9ff] to-white shrink-0">
                    <div>
                        <h3 class="font-bold text-[#041525]">Import Students</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Bulk upload from CSV (opens in Excel)</p>
                    </div>
                    <button type="button" @click="closeImportModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>

                <div class="p-6 space-y-4 overflow-y-auto">
                    <div class="bg-[#f0f9ff] border border-[#dbeafe] rounded-xl p-4 text-sm text-[#041525] space-y-2">
                        <p class="font-semibold">Required columns</p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1 text-xs">
                            <li><strong>full_name</strong> — student’s full name</li>
                            <li><strong>class_name</strong> — must match your Sahodaya class list exactly</li>
                            <li><strong>email</strong> — optional contact email</li>
                        </ul>
                    </div>

                    <div v-if="classNames.length" class="text-xs text-gray-500">
                        <span class="font-semibold text-gray-600">Your class names:</span>
                        {{ classNames.join(', ') }}
                    </div>
                    <div v-else class="text-sm text-amber-800">
                        Contact your Sahodaya admin to configure classes before importing.
                    </div>

                    <a :href="`/school-admin/${school.id}/students/import/template`"
                       class="inline-flex items-center gap-2 text-sm font-semibold text-[#0f3d7a] hover:underline">
                        ↓ Download sample CSV (Excel compatible)
                    </a>

                    <form @submit.prevent="submitImport" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">CSV file *</label>
                            <input type="file" accept=".csv,.txt,text/csv" required @change="onImportFile"
                                   class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#f0f9ff] file:text-[#0f3d7a]">
                            <p class="text-xs text-gray-400 mt-1">Save your Excel sheet as CSV before uploading.</p>
                            <p v-if="importForm.errors.file" class="text-xs text-red-500 mt-1">{{ importForm.errors.file }}</p>
                        </div>

                        <div v-if="importResult?.errors?.length" class="bg-red-50 border border-red-100 rounded-lg p-3 space-y-1 max-h-36 overflow-y-auto">
                            <p class="text-xs font-semibold text-red-700">Import issues</p>
                            <ul class="text-xs text-red-600 space-y-0.5">
                                <li v-for="(err, i) in importResult.errors" :key="i">
                                    Row {{ err.row }}: {{ err.message }}
                                </li>
                            </ul>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-1">
                            <button type="button" @click="closeImportModal" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                            <button type="submit" :disabled="importForm.processing || !classNames.length"
                                    class="sa-btn-primary px-5 py-2.5 rounded-lg text-sm font-semibold disabled:opacity-50">
                                Import students
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    school:     Object,
    students:   Object,
    filters:    Object,
    categories: { type: Array, default: () => [] },
    classes:    { type: Array, default: () => [] },
    classNames: { type: Array, default: () => [] },
});

const page = usePage();
const showRegister = ref(false);
const showImport = ref(false);
const showEdit = ref(false);
const showPortal = ref(false);
const portalStudent = ref(null);
const editingStudent = ref(null);
const editPhotoPreview = ref(null);

const importResult = computed(() => page.props.flash?.importResult ?? null);

const columns = [
    { key: 'photo',        label: 'Photo',  sortable: false, class: 'w-14' },
    { key: 'name',         label: 'Name',   sortable: true },
    { key: 'reg_no',       label: 'Reg No', sortable: false },
    { key: 'gender',       label: 'Gender', sortable: false },
    { key: 'dob',          label: 'DOB',    sortable: false },
    { key: 'parent_email', label: 'Email',  sortable: true },
    { key: 'class',        label: 'Class',  sortable: true },
    { key: 'status',       label: 'Status', sortable: true },
    { key: 'actions',      label: '',       sortable: false, align: 'right' },
];

const filterForm = reactive({
    class_category_id: props.filters?.class_category_id ?? null,
    school_class_id:   props.filters?.school_class_id ?? null,
    status:            props.filters?.status ?? 'active',
    search:            props.filters?.search ?? '',
});

const registerForm = useForm({
    school_class_id: '',
    name:            '',
    gender:          '',
    dob:             '',
    email:           '',
    create_login:    false,
    password:        '',
});

const portalForm = useForm({ email: '', password: '' });

const importForm = useForm({ file: null });

const editForm = useForm({
    school_class_id: '',
    name:            '',
    gender:          '',
    dob:             '',
    parent_email:    '',
    photo:           null,
});

const schoolClasses = computed(() =>
    props.classes.filter(c => c.is_active !== false),
);

const schoolClassesSorted = computed(() =>
    [...schoolClasses.value].sort((a, b) =>
        (a.display_order ?? 0) - (b.display_order ?? 0)
        || String(a.name).localeCompare(String(b.name), undefined, { numeric: true }),
    ),
);

const filteredClasses = computed(() => {
    if (!filterForm.class_category_id) return schoolClasses.value;
    return schoolClasses.value.filter(c => Number(c.class_category_id) === Number(filterForm.class_category_id));
});

const hasActiveFilters = computed(() =>
    filterForm.class_category_id != null
    || filterForm.school_class_id != null
    || filterForm.status !== 'active'
    || !!filterForm.search
);

watch(() => props.filters, (f) => {
    if (!f) return;
    filterForm.class_category_id = f.class_category_id ?? null;
    filterForm.school_class_id   = f.school_class_id ?? null;
    filterForm.status            = f.status ?? 'active';
    filterForm.search            = f.search ?? '';
}, { deep: true });

function formatClassOption(schoolClass) {
    const cat = props.categories.find(c => Number(c.id) === Number(schoolClass.class_category_id));
    return cat ? `Class ${schoolClass.name} (${cat.label})` : `Class ${schoolClass.name}`;
}

function classesInCategory(categoryId) {
    return schoolClasses.value.filter(c => Number(c.class_category_id) === Number(categoryId));
}

function listParams(overrides = {}) {
    return {
        class_category_id: props.filters?.class_category_id ?? null,
        school_class_id:   props.filters?.school_class_id ?? null,
        status:            props.filters?.status ?? 'active',
        search:            props.filters?.search ?? '',
        sort:              props.filters?.sort ?? 'name',
        dir:               props.filters?.dir ?? 'asc',
        ...overrides,
    };
}

function applyFilters() {
    router.get(`/school-admin/${props.school.id}/students`, {
        class_category_id: filterForm.class_category_id,
        school_class_id:   filterForm.school_class_id,
        status:            filterForm.status,
        search:            filterForm.search,
        sort:              props.filters?.sort ?? 'name',
        dir:               props.filters?.dir ?? 'asc',
    }, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    filterForm.class_category_id = null;
    filterForm.school_class_id   = null;
    filterForm.status            = 'active';
    filterForm.search            = '';
    router.get(`/school-admin/${props.school.id}/students`, listParams({
        class_category_id: null,
        school_class_id:   null,
        status:            'active',
        search:            '',
    }), { preserveState: true, preserveScroll: true });
}

function toggleSort(key) {
    const sortable = { name: 'name', parent_email: 'parent_email', class: 'class', status: 'status' };
    const sortKey = sortable[key];
    if (!sortKey) return;

    const nextDir = props.filters?.sort === sortKey && props.filters?.dir === 'asc' ? 'desc' : 'asc';
    router.get(`/school-admin/${props.school.id}/students`, listParams({
        class_category_id: filterForm.class_category_id,
        school_class_id:   filterForm.school_class_id,
        status:            filterForm.status,
        search:            filterForm.search,
        sort: sortKey,
        dir:  nextDir,
    }), { preserveState: true, preserveScroll: true });
}

function openRegisterModal() {
    registerForm.reset();
    registerForm.clearErrors();
    showRegister.value = true;
}

function closeRegisterModal() {
    showRegister.value = false;
    clearModalQuery();
}

function openImportModal() {
    importForm.reset();
    importForm.clearErrors();
    showImport.value = true;
}

function closeImportModal() {
    showImport.value = false;
    clearModalQuery();
}

function clearModalQuery() {
    const url = new URL(window.location.href);
    if (url.searchParams.has('register') || url.searchParams.has('import') || url.searchParams.has('edit')) {
        url.searchParams.delete('register');
        url.searchParams.delete('import');
        url.searchParams.delete('edit');
        window.history.replaceState({}, '', url.pathname + url.search);
    }
}

function openEditModal(student) {
    editingStudent.value = student;
    editPhotoPreview.value = null;
    editForm.clearErrors();
    editForm.school_class_id = student.school_class_id ?? student.school_class?.id ?? '';
    editForm.name = student.name ?? '';
    editForm.gender = student.gender ?? '';
    editForm.dob = dobInputValue(student.dob);
    editForm.parent_email = student.parent_email ?? '';
    editForm.photo = null;
    showEdit.value = true;
}

function closeEditModal() {
    showEdit.value = false;
    editingStudent.value = null;
    editPhotoPreview.value = null;
    editForm.reset();
    clearModalQuery();
}

function onEditPhotoChange(event) {
    const file = event.target.files?.[0] ?? null;
    editForm.photo = file;
    if (editPhotoPreview.value) URL.revokeObjectURL(editPhotoPreview.value);
    editPhotoPreview.value = file ? URL.createObjectURL(file) : null;
}

function submitEdit() {
    editForm
        .transform(data => ({ ...data, _method: 'put' }))
        .post(`/school-admin/${props.school.id}/students/${editingStudent.value.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => closeEditModal(),
        });
}

function submitRegister() {
    registerForm.post(`/school-admin/${props.school.id}/students`, {
        preserveScroll: true,
        onSuccess: () => {
            closeRegisterModal();
            registerForm.reset();
        },
    });
}

function openPortalModal(student) {
    portalStudent.value = student;
    portalForm.email = student.email || student.parent_email || '';
    portalForm.password = '';
    portalForm.clearErrors();
    showPortal.value = true;
}

function closePortalModal() {
    showPortal.value = false;
    portalStudent.value = null;
    portalForm.reset();
}

function submitPortal() {
    portalForm.post(`/school-admin/${props.school.id}/students/${portalStudent.value.id}/portal-login`, {
        preserveScroll: true,
        onSuccess: () => closePortalModal(),
    });
}

function onImportFile(event) {
    importForm.file = event.target.files[0] ?? null;
}

function submitImport() {
    importForm.post(`/school-admin/${props.school.id}/students/import`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            if (!usePage().props.flash?.error) {
                closeImportModal();
                importForm.reset();
            }
        },
    });
}

function onCategoryChange() {
    const stillValid = filteredClasses.value.some(c => c.id === filterForm.school_class_id);
    if (!stillValid) filterForm.school_class_id = null;
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('register') === '1') openRegisterModal();
    if (params.get('import') === '1' || importResult.value) openImportModal();
    const editId = params.get('edit');
    if (editId) {
        const student = props.students?.data?.find(s => String(s.id) === editId);
        if (student) openEditModal(student);
    }
});

function statusClass(status) {
    return {
        active:      'bg-green-100 text-green-700',
        transferred: 'bg-amber-100 text-amber-700',
        graduated:   'bg-blue-100 text-blue-700',
        withdrawn:   'bg-gray-100 text-gray-600',
    }[status] ?? 'bg-gray-100 text-gray-600';
}

function remove(student) {
    if (!confirm(`Remove student "${student.name}"?`)) return;
    router.delete(`/school-admin/${props.school.id}/students/${student.id}`);
}

function initials(name) {
    return (name || '?').split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

function formatGender(gender) {
    if (!gender) return '—';
    return gender.charAt(0).toUpperCase() + gender.slice(1);
}

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function dobInputValue(value) {
    if (!value) return '';
    const str = String(value);
    return str.length >= 10 ? str.slice(0, 10) : str;
}
</script>

<style scoped>
.sa-btn-primary {
    background: linear-gradient(135deg, #0f3d7a, #1e5aa8);
    color: #fff;
    box-shadow: 0 2px 8px rgba(15, 61, 122, 0.25);
}

.sa-btn-primary:hover:not(:disabled) {
    background: linear-gradient(135deg, #1a4f8c, #2563eb);
}
</style>
