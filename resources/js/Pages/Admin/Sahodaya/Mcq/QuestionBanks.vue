<template>
    <SahodayaAdminLayout :title="`Question Banks — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="MCQ exam" description="Link teacher question banks to this exam." />
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode || 'offline'" :results-published="!!exam.results_published" active="question-banks" />

        <div v-if="(exam.delivery_mode || 'offline') === 'offline'" class="card card--muted mb-4 !py-3 px-4 text-sm text-slate-700">
            This exam is <strong>offline</strong>. Question banks are optional — link them only if you switch delivery to online on the Overview tab.
        </div>
        <div v-else-if="!exam.question_banks?.length" class="card card--accent !border-amber-200 mb-4 !py-3 px-4 text-sm text-amber-900">
            Online exam: attach at least one question bank with gradable MCQ items before students can start.
        </div>

        <input v-model="searchQuery" type="search" class="field max-w-md mb-4" placeholder="Search banks…">

        <section class="card mb-6">
            <h3 class="section-title">Linked banks</h3>
            <EmptyState v-if="!exam.question_banks?.length" title="No banks linked" description="Attach a bank from the list below." icon="📚" class="py-6" />
            <ul v-else class="divide-y">
                <li v-for="bank in filteredLinked" :key="bank.id" class="py-3 flex justify-between text-sm">
                    <span>{{ bank.title }} <span class="text-slate-400">({{ bank.questions?.length ?? 0 }} questions)</span></span>
                    <button type="button" @click="detach(bank)" class="text-red-600 text-xs">Unlink</button>
                </li>
            </ul>
        </section>

        <section class="card">
            <h3 class="section-title">Attach from schools</h3>
            <EmptyState v-if="!filteredAvailable.length" title="No banks available" description="Teachers have not created question banks yet." icon="📝" class="py-6" />
            <ul v-else class="divide-y">
                <li v-for="bank in filteredAvailable" :key="bank.id" class="py-3 flex justify-between items-center gap-3">
                    <div>
                        <p class="font-medium text-sm">{{ bank.title }}</p>
                        <p class="text-xs text-slate-500">{{ bank.subject }} · {{ bank.questions_count }} question(s)</p>
                    </div>
                    <button type="button" @click="attach(bank)" class="btn-secondary text-xs" :disabled="isLinked(bank)">
                        {{ isLinked(bank) ? 'Linked' : 'Link' }}
                    </button>
                </li>
            </ul>
        </section>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';

const props = defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, exam: Object, available: Array });
const searchQuery = ref('');

const filteredLinked = computed(() => filterBanks(props.exam.question_banks ?? []));
const filteredAvailable = computed(() => filterBanks(props.available ?? []));

function filterBanks(banks) {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return banks;
    return banks.filter((b) => [b.title, b.subject].filter(Boolean).join(' ').toLowerCase().includes(q));
}

function isLinked(bank) {
    return props.exam.question_banks?.some((b) => b.id === bank.id);
}

function attach(bank) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/question-banks`, { bank_id: bank.id }, { preserveScroll: true });
}

function detach(bank) {
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/question-banks/${bank.id}`, { preserveScroll: true });
}
</script>
