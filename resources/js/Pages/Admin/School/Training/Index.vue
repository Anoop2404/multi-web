<template>
    <SchoolAdminLayout title="Teacher Training" :school="school" :show-header-title="false">
        <PageHeader title="Teacher Training" eyebrow="Teacher training"
            description="Register teachers for Sahodaya training programs." />


        <div class="space-y-4">
            <p class="text-sm text-gray-500">Register teachers for Sahodaya training programs.</p>
            <div v-for="program in programs" :key="program.id" class="card">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                    <div>
                        <h3 class="font-semibold">{{ program.title }}</h3>
                        <p v-if="program.description" class="text-sm text-gray-600 mt-1">{{ program.description }}</p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ program.status }}
                            <template v-if="program.fee_type !== 'none' && program.fee_amount">
                                <span class="mx-1">·</span>
                                <span class="font-semibold text-indigo-700">Fee: ₹{{ program.fee_amount }}</span>
                            </template>
                        </p>
                    </div>
                </div>

                <ul v-if="registrations[program.id]?.length" class="text-sm divide-y mb-3">
                    <li v-for="r in registrations[program.id]" :key="r.id" class="py-2">
                        <div class="flex justify-between items-start gap-2 flex-wrap">
                            <span>{{ r.teacher?.name }}</span>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-gray-400 text-xs capitalize">{{ r.status }}</span>
                                <!-- Fee status -->
                                <template v-if="program.fee_type !== 'none' && program.fee_amount">
                                    <span v-if="r.fee_receipt?.status === 'approved'" class="text-xs font-semibold text-green-700 bg-green-50 px-2 py-0.5 rounded">Fee paid</span>
                                    <span v-else-if="r.fee_receipt?.status === 'uploaded'" class="text-xs text-yellow-700">Fee pending approval</span>
                                    <span v-else-if="r.fee_receipt?.status === 'rejected'" class="text-xs text-red-600">Fee rejected — re-upload</span>
                                </template>
                            </div>
                        </div>
                        <!-- Fee upload form -->
                        <form v-if="program.fee_type !== 'none' && program.fee_amount && needsFeeUpload(r)"
                              @submit.prevent="uploadFee(r)" class="flex flex-wrap gap-2 items-center mt-2">
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png"
                                   @change="e => feeFiles[r.id] = e.target.files[0]"
                                   class="text-xs" required>
                            <input v-model="feeRefs[r.id]" class="field text-xs max-w-xs" placeholder="Transaction ref (optional)">
                            <button class="btn-primary text-xs !min-h-0 !px-2 !py-1">Upload proof</button>
                        </form>
                    </li>
                </ul>

                <form v-if="['published','ongoing'].includes(program.status)"
                      @submit.prevent="register(program)" class="flex flex-wrap gap-2 items-end">
                    <select v-model="forms[program.id]" class="field max-w-xs" required>
                        <option value="">Select teacher</option>
                        <option v-for="t in (eligibleByProgram[program.id] || [])" :key="t.id" :value="t.id">{{ t.name }}<template v-if="t.category"> ({{ t.category }})</template></option>
                    </select>
                    <button class="btn-primary">Register</button>
                </form>
            </div>
            <p v-if="!programs.length" class="text-center text-gray-400 py-8">No training programs available.</p>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({ school: Object, programs: Array, registrations: Object, eligibleByProgram: Object });
const forms    = reactive({});
const feeFiles = reactive({});
const feeRefs  = reactive({});
for (const p of props.programs) forms[p.id] = '';

function needsFeeUpload(registration) {
    const s = registration.fee_receipt?.status;
    return !s || s === 'rejected';
}

function register(program) {
    router.post(`/school-admin/${props.school.id}/training`, {
        program_id: program.id,
        teacher_id: forms[program.id],
    }, { preserveScroll: true, onSuccess: () => { forms[program.id] = ''; } });
}

function uploadFee(registration) {
    const file = feeFiles[registration.id];
    if (!file) return;

    const fd = new FormData();
    fd.append('payment_proof', file);
    if (feeRefs[registration.id]) fd.append('transaction_ref', feeRefs[registration.id]);

    router.post(`/school-admin/${props.school.id}/training/${registration.id}/payment`, fd, {
        preserveScroll: true,
        onSuccess: () => { feeFiles[registration.id] = null; feeRefs[registration.id] = ''; },
    });
}
</script>

