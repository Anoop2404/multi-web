<template>
    <SchoolAdminLayout title="Dashboard" :school="school">
        <div class="space-y-6 max-w-4xl">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-3">
                <h2 class="text-lg font-bold text-gray-900">Welcome to your school portal</h2>
                <p class="text-sm text-gray-600 leading-relaxed">
                    Use this panel to manage your school's student records and complete
                    <strong>annual Sahodaya membership registration</strong> each academic year.
                </p>
                <ul class="text-sm text-gray-600 space-y-1.5 list-disc list-inside">
                    <li><strong>Students</strong> — class-wise records using classes set by your Sahodaya</li>
                    <li><strong>Annual registration</strong> — submit counts/teachers and membership payment to Sahodaya</li>
                </ul>
            </div>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
                <h3 class="font-bold text-gray-800">Get started</h3>
                <ol class="space-y-3">
                    <li class="flex items-start gap-3 text-sm">
                        <span class="shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                              :class="setup.hasSchoolCode ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'">
                            {{ setup.hasSchoolCode ? '✓' : '1' }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-800">Set your school code</p>
                            <p class="text-gray-500 text-xs mt-0.5">
                                A short code unique within your Sahodaya — used in student registration numbers
                                <span v-if="setup.regNoExample" class="font-mono">(e.g. {{ setup.regNoExample }})</span>.
                            </p>
                            <p v-if="setup.hasSchoolCode" class="text-xs font-mono text-green-700 mt-1">{{ setup.schoolCode }}</p>
                            <Link v-else :href="`/school-admin/${school.id}/setup/code`"
                                  class="inline-block mt-2 text-xs font-semibold text-blue-600 hover:underline">
                                Set school code →
                            </Link>
                        </div>
                    </li>

                    <li class="flex items-start gap-3 text-sm" :class="!setup.hasSchoolCode && 'opacity-50'">
                        <span class="shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                              :class="setup.studentCount > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                            {{ setup.studentCount > 0 ? '✓' : '2' }}
                        </span>
                        <div>
                            <p class="font-semibold text-gray-800">Register students</p>
                            <p class="text-gray-500 text-xs mt-0.5">
                                Classes (1–12, etc.) are provided by your Sahodaya — just pick the class when registering.
                                Or <Link :href="`/school-admin/${school.id}/students?import=1`" class="text-blue-600 hover:underline">import from CSV</Link>.
                            </p>
                            <Link v-if="setup.hasSchoolCode" :href="`/school-admin/${school.id}/students?register=1`"
                                  class="inline-block mt-2 text-xs font-semibold text-blue-600 hover:underline">
                                {{ setup.studentCount > 0 ? `View students (${setup.studentCount})` : 'Register first student' }} →
                            </Link>
                        </div>
                    </li>

                    <li class="flex items-start gap-3 text-sm" :class="!setup.hasSchoolCode && 'opacity-50'">
                        <span class="shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                              :class="setup.hasRegistration ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                            {{ setup.hasRegistration ? '✓' : '3' }}
                        </span>
                        <div>
                            <p class="font-semibold text-gray-800">Annual membership — {{ setup.academicYear }}</p>
                            <p class="text-gray-500 text-xs mt-0.5">Submit data and payment proof for Sahodaya verification.</p>
                            <Link v-if="setup.hasSchoolCode" :href="`/school-admin/${school.id}/registration`"
                                  class="inline-block mt-2 text-xs font-semibold text-blue-600 hover:underline">
                                {{ setup.hasRegistration ? 'Continue registration' : 'Begin annual registration' }} →
                            </Link>
                        </div>
                    </li>
                </ol>
            </div>

            <div v-if="setup.studentCount > 0" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div v-for="stat in stats" :key="stat.label"
                     class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">{{ stat.label }}</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ stat.value }}</p>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    school: Object,
    stats:  { type: Array, default: () => [] },
    setup:  { type: Object, required: true },
});
</script>
