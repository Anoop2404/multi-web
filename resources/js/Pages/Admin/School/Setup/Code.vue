<template>
    <SchoolAdminLayout title="School Code" :school="school" :show-header-title="false">
        <PageHeader title="School Code" eyebrow="Students"
            description="Student records, teachers, and portal access." />


        <div class="max-w-2xl space-y-6">
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-5 text-sm text-blue-900 space-y-2">
                <p class="font-semibold">What is the school code?</p>
                <p class="text-blue-800/90 leading-relaxed">
                    A short unique code for your school within this Sahodaya — usually 2–4 letters from your school name
                    (e.g. <span class="font-mono font-semibold">AMU</span> for AMU Residential School).
                </p>
                <p class="text-blue-800/90 leading-relaxed">
                    It is used in Sahodaya numbers — school membership <span class="font-mono">SAHODAYA/YY/1</span> and student <span class="font-mono">STU/YY/0001</span>.
                    <span v-if="regNoExample" class="block mt-2 font-mono text-xs bg-white/70 border border-blue-100 rounded px-2 py-1.5 inline-block">
                        Example student reg no: {{ regNoExample }}
                    </span>
                </p>
            </div>

            <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-5">
                <div v-if="codeLocked && schoolCode" class="space-y-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Your school code</p>
                    <p class="text-3xl font-mono font-bold text-gray-900">{{ schoolCode }}</p>
                    <p class="text-xs text-gray-500">Locked after the first student registration number is issued — contact Sahodaya admin to change.</p>
                    <Link :href="`/school-admin/${school.id}`"
                          class="inline-block mt-2 text-sm text-blue-600 hover:underline">← Back to dashboard</Link>
                </div>

                <form v-else @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="form-label mb-1.5">School code *</label>
                        <input v-model="form.school_prefix" type="text" required maxlength="10"
                               class="w-full max-w-xs border border-gray-200 rounded-lg px-4 py-2.5 text-sm font-mono uppercase focus:outline-none focus:ring-2"
                               placeholder="AMU"
                               @input="form.school_prefix = form.school_prefix.toUpperCase().replace(/[^A-Z0-9]/g, '')">
                        <p class="text-xs text-gray-400 mt-1.5">
                            Letters and numbers only, max 10 characters. Must be unique within your Sahodaya.
                            <button v-if="suggestedCode && !form.school_prefix" type="button"
                                    class="text-blue-600 hover:underline ml-1"
                                    @click="form.school_prefix = suggestedCode">
                                Use suggested: {{ suggestedCode }}
                            </button>
                        </p>
                        <p v-if="form.errors.school_prefix" class="text-xs text-red-500 mt-1">{{ form.errors.school_prefix }}</p>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" :disabled="form.processing"
                                class="btn-primary disabled:opacity-50">
                            Save school code
                        </button>
                        <Link v-if="schoolCode" :href="`/school-admin/${school.id}`" class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </Link>
                    </div>
                </form>
            </section>

            <div v-if="schoolCode && !codeLocked" class="text-sm text-gray-500">
                Next: <Link :href="`/school-admin/${school.id}/students?register=1`" class="text-blue-600 hover:underline">register students</Link>.
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    school:         Object,
    schoolCode:     { type: String, default: null },
    codeLocked:     { type: Boolean, default: false },
    suggestedCode:  { type: String, default: '' },
    regNoExample:   { type: String, default: null },
    sahodayaPrefix: { type: String, default: null },
});

const form = useForm({
    school_prefix: props.schoolCode || props.suggestedCode || '',
});

function submit() {
    form.post(`/school-admin/${props.school.id}/setup/code`);
}
</script>
