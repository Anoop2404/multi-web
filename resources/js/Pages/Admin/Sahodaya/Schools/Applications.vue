<template>
    <SahodayaAdminLayout title="Pending applications" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="approvedSchoolsCount"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <PageHeader
            title="Pending school applications"
            eyebrow="Membership"
            :description="`${schools.total ?? 0} application${(schools.total ?? 0) === 1 ? '' : 's'} awaiting review`"
        >
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/schools`" class="btn-secondary text-sm">Verified schools →</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`" class="btn-primary text-sm">Verify payments →</Link>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[180px] max-w-sm">
                <input v-model="filterForm.search" type="search" placeholder="Search name…"
                       @keyup.enter="applyFilters"
                       class="field">
            </div>
            <button @click="applyFilters" class="btn-primary">Search</button>
        </div>

        <div v-if="schools.data?.length" class="space-y-3">
            <Link v-for="school in schools.data" :key="school.id"
                  :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}`"
                  class="flex items-center justify-between gap-4 bg-white rounded-2xl border border-amber-100 shadow-sm px-5 py-4 hover:border-[#0f3d7a]/30 hover:shadow transition group">
                <div>
                    <p class="font-bold text-gray-900">{{ school.name }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Applied {{ formatDate(school.created_at) }}
                        <span v-if="school.contact_email"> · {{ school.contact_email }}</span>
                    </p>
                </div>
                <span class="text-xs px-2 py-1 rounded-full font-semibold bg-amber-100 text-amber-800 shrink-0">Pending</span>
            </Link>
            <div v-if="schools.links?.length > 3" class="flex justify-center gap-1 pt-2">
                <Link v-for="link in schools.links" :key="link.label"
                      :href="link.url || '#'"
                      class="px-3 py-1 rounded text-sm"
                      :class="link.active ? 'bg-[#0f3d7a] text-white' : 'text-gray-600 hover:bg-gray-100'"
                      v-html="link.label" />
            </div>
        </div>

        <EmptyState v-else title="No pending applications" description="New school applications will appear here until membership is approved." icon="🏫" />
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String,
    approvedSchoolsCount: Number, pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number, pendingPaymentsCount: Number,
    schools: Object, filters: Object,
});

const filterForm = reactive({ search: props.filters?.search ?? '' });

function applyFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools/applications`, {
        search: filterForm.search,
    }, { preserveState: true, replace: true });
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
