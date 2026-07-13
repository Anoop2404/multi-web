<template>
    <SchoolAdminLayout title="Register by Event Head" :school="school" :show-header-title="false">
        <PageHeader
            title="Register by Event Head"
            eyebrow="Sports Meet · Step 2"
            description="Choose which sports fest, then pick an Event Head to register athletes for its events."
        >
            <template #actions>
                <Link :href="`${programBase}/registration`" class="btn-secondary text-sm">← Step 1: Event registration</Link>
            </template>
        </PageHeader>

        <div class="space-y-3">
            <Link v-for="ev in events"
                  :key="ev.id"
                  :href="`${programBase}/item-registration?event=${ev.id}`"
                  class="card block hover:shadow-md transition-shadow !p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ ev.title }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5 capitalize">{{ ev.status?.replace('_', ' ') }}</p>
                    </div>
                    <span class="btn-primary text-sm !min-h-0">Open →</span>
                </div>
            </Link>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    events: { type: Array, default: () => [] },
});

const { programBase } = useSchoolProgramContext(props);
</script>
