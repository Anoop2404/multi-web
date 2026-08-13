<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Certificates"
        :subtitle="school.name"
        accent="navy"
        :nav-items="navItems"
    >
        <section class="card">
            <h2 class="section-title text-base mb-3">Fest certificates</h2>
            <ul v-if="festCerts?.length" class="text-sm divide-y divide-slate-100">
                <li v-for="(c, i) in festCerts" :key="i" class="py-3 flex justify-between items-center gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900 truncate">{{ c.event?.title ?? 'Event' }}</p>
                        <p v-if="c.item?.title" class="text-xs text-slate-500 truncate">{{ c.item.title }}</p>
                    </div>
                    <a v-if="c.uuid" :href="`/certificates/print/${c.uuid}`" target="_blank" class="btn-secondary text-xs !min-h-0 !py-1.5 !px-3 shrink-0">Print ↗</a>
                </li>
            </ul>
            <EmptyState v-else title="No certificates yet" description="Certificates you've earned from fest and training will appear here." icon="🏆" />
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { computed } from 'vue';
import { teacherPortalNavItems } from '@/support/teacherPortalNav.js';

const props = defineProps({
    school: Object,
    teacher: Object,
    festCerts: { type: Array, default: () => [] },
});

const navItems = computed(() => teacherPortalNavItems(props.school.id));
</script>
