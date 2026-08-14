<template>
    <SchoolAdminLayout title="Student records" :school="school" :show-header-title="false">
        <PageHeader title="Student records for membership" eyebrow="Membership"
                    description="Your school student list (same records used for fest registration). Submit when ready for Sahodaya review." />

        <div class="max-w-4xl space-y-5">
            <MembershipWorkflowNav :school="school"
                                   :profile="profile"
                                   :registration="registration"
                                   current="students" />

            <div class="notice-banner notice-banner--info text-sm">
                Student data is maintained under
                <Link :href="`/school-admin/${school.id}/students`" class="link-brand font-semibold">Records → Students</Link>.
                This page shows a read-only snapshot for your annual submission.
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <TrackStatusPill :status="submission.full_records_status" />
                <Link :href="`/school-admin/${school.id}/students`" class="btn-secondary text-sm">Manage students →</Link>
            </div>

            <div v-if="submission.full_records_rejection_reason" class="notice-banner notice-banner--warning text-sm">
                <p class="font-semibold">Submission rejected</p>
                <p class="mt-1">{{ submission.full_records_rejection_reason }}</p>
            </div>

            <div class="flex items-center gap-2">
                <input v-model="searchInput"
                       type="search"
                       placeholder="Search by name or reg no…"
                       class="form-input max-w-xs text-sm"
                       @input="onSearchInput" />
            </div>

            <div class="card card--flush overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Name</th>
                            <th class="p-3">Reg no</th>
                            <th class="p-3">Category</th>
                            <th class="p-3">Class</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in students.data" :key="s.id" class="border-t">
                            <td class="p-3 font-medium">{{ s.name }}</td>
                            <td class="p-3 font-mono text-xs text-slate-500">{{ s.reg_no || '—' }}</td>
                            <td class="p-3 text-xs text-slate-500">{{ s.school_class?.class_category?.label || '—' }}</td>
                            <td class="p-3">{{ s.school_class?.name || '—' }}</td>
                        </tr>
                        <tr v-if="!students.data.length">
                            <td colspan="4" class="p-8 text-center text-gray-400">
                                <template v-if="search">No students match "{{ search }}".</template>
                                <template v-else>
                                    No active students yet.
                                    <Link :href="`/school-admin/${school.id}/students`" class="link-brand font-semibold">Add students</Link>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-slate-500">
                    Showing {{ students.from ?? 0 }}–{{ students.to ?? 0 }} of {{ students.total }} matching student{{ students.total === 1 ? '' : 's' }}
                    <span v-if="!search"> ({{ studentTotal }} active in total)</span>
                </p>
                <div v-if="students.links?.length > 3" class="flex flex-wrap gap-1">
                    <Link v-for="(link, idx) in students.links"
                          :key="idx"
                          :href="link.url || '#'"
                          preserve-scroll
                          class="rounded px-2 py-1 text-xs"
                          :class="[
                              link.active ? 'bg-[#0f3d7a] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200',
                              !link.url ? 'pointer-events-none opacity-40' : '',
                          ]"
                          v-html="link.label" />
                </div>
            </div>

            <button v-if="canSubmit"
                    type="button"
                    class="btn-primary"
                    :disabled="studentTotal < 1"
                    @click="submit">
                Submit student records for Sahodaya review
            </button>
            <p v-else-if="submission.full_records_status === 'submitted'" class="text-sm text-amber-700">
                Awaiting Sahodaya approval…
            </p>
            <p v-else-if="submission.full_records_status === 'approved'" class="text-sm text-green-700">
                Student records approved.
            </p>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import MembershipWorkflowNav from '@/Components/school/MembershipWorkflowNav.vue';
import TrackStatusPill from '@/Components/ui/TrackStatusPill.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    school: Object,
    registration: Object,
    submission: Object,
    profile: { type: Object, default: null },
    students: { type: Object, default: () => ({ data: [], links: [], from: 0, to: 0, total: 0 }) },
    studentTotal: { type: Number, default: 0 },
    search: { type: String, default: '' },
});

const canSubmit = computed(() =>
    ['pending', 'rejected'].includes(props.submission?.full_records_status),
);

const searchInput = ref(props.search);
let searchTimeout = null;

// Server-side search (paginated list — a client-side filter would only ever see
// whatever happened to be on the current page). Debounced so we're not firing a
// request per keystroke.
function onSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(`/school-admin/${props.school.id}/registration/students`, {
            search: searchInput.value || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
}

function submit() {
    if (!confirm(`Submit ${props.studentTotal} student record(s) for Sahodaya review?`)) return;
    router.post(`/school-admin/${props.school.id}/registration/submit-track`, { track: 'full_records' });
}
</script>
