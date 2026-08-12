<template>
    <SchoolAdminLayout title="Principal Verification" :school="school" :show-header-title="false">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Principal Verification</h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    The Principal or an authorized Vice Principal reviews, signs, and submits each certified result package to Sahodaya.
                </p>
            </div>

            <div class="flex items-center gap-3 self-start md:self-auto">
                <select :value="academicYear" @change="switchYear($event.target.value)" class="field text-xs py-1.5 font-medium pl-3 pr-8 w-32">
                    <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label">{{ ay.label }}</option>
                </select>
            </div>
        </div>

        <div v-if="!cards.length" class="text-center py-20 bg-white rounded-2xl border border-gray-200 shadow-sm">
            <span class="text-4xl">🛡️</span>
            <h3 class="text-lg font-bold text-gray-900 mt-4">No board results found</h3>
            <p class="text-sm text-gray-500 mt-1 max-w-sm mx-auto">
                No Class X or Class XII result has been created for {{ academicYear }} yet.
            </p>
        </div>

        <div v-else class="grid md:grid-cols-2 gap-4">
            <div v-for="card in cards" :key="card.board_result_id" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Class {{ card.class }} · {{ card.examination_type }}</h2>
                        <p class="text-xs text-gray-500">{{ card.academic_year }}</p>
                    </div>
                    <span
                        class="text-[10px] font-bold uppercase px-2.5 py-1 rounded-full"
                        :class="statusPillClass(card.package?.status)"
                    >
                        {{ card.package ? card.package.status_label : 'Not started' }}
                    </span>
                </div>

                <div class="p-5 space-y-3">
                    <div v-if="card.package" class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Signed reports</span>
                        <span class="font-bold text-gray-900">{{ card.package.signed_count }} of {{ card.package.required_count || '—' }}</span>
                    </div>
                    <div v-if="card.package" class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Package version</span>
                        <span class="font-bold text-gray-900">v{{ card.package.version }}</span>
                    </div>
                    <div v-if="card.package?.submitted_at" class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Submitted</span>
                        <span class="font-medium text-gray-700">{{ formatDate(card.package.submitted_at) }}</span>
                    </div>

                    <button
                        type="button"
                        @click="openAndStartReview(card)"
                        class="btn-primary w-full justify-center mt-2 flex items-center gap-2 cursor-pointer"
                        :disabled="submittingId === card.board_result_id"
                    >
                        <span>📄</span> {{ submittingId === card.board_result_id ? 'Preparing Reports...' : (!card.package || card.package.status === 'draft' ? 'Open & Review Reports' : card.primary_action) }}
                    </button>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({
    school: Object,
    cards: Array,
    academicYear: String,
    academicYearOptions: Array,
    selectedClass: { type: Number, default: null },
    filters: Object,
});

const submittingId = ref(null);

function openAndStartReview(card) {
    router.get(`/school-admin/${props.school.id}/board-results/${card.board_result_id}/principal-verification`);
}

function switchYear(year) {
    router.get(`/school-admin/${props.school.id}/board-results/principal-verification`, { academic_year: year }, { preserveState: true });
}

function statusPillClass(status) {
    if (!status) return 'bg-gray-100 text-gray-500';
    if (status === 'published' || status === 'approved' || status === 'sahodaya_verified') return 'bg-emerald-100 text-emerald-700';
    if (status === 'submitted_to_sahodaya') return 'bg-indigo-100 text-indigo-700';
    if (status === 'sahodaya_returned' || status === 'leadership_changes_requested') return 'bg-rose-100 text-rose-700';
    return 'bg-amber-100 text-amber-700';
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>
