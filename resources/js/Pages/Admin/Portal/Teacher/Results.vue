<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Fest results"
        :subtitle="school.name"
        accent="navy"
        :nav-items="navItems"
    >
        <section class="card">
            <h2 class="section-title text-base mb-3">Published results</h2>
            <ul v-if="festResults?.length" class="text-sm divide-y divide-slate-100">
                <li v-for="(r, i) in festResults" :key="i" class="py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ r.event_title }} — {{ r.item_title }}</p>
                        <p v-if="!(r.grade || r.position || r.score)" class="text-xs text-slate-400 mt-0.5">Results not yet recorded</p>
                    </div>
                    <div v-if="r.grade || r.position || r.score" class="flex items-center gap-1.5 shrink-0">
                        <span v-if="r.grade" class="status-pill status-pill--published">Grade {{ r.grade }}</span>
                        <span v-if="r.position" class="status-pill status-pill--completed">Pos {{ r.position }}</span>
                        <span v-if="r.score" class="text-xs font-semibold text-slate-600">{{ r.score }} pts</span>
                        <span v-if="r.chest_no" class="text-xs text-slate-400">#{{ r.chest_no }}</span>
                    </div>
                </li>
            </ul>
            <EmptyState v-else title="No published fest results yet" description="Results appear here once your fest coordinator publishes them." icon="🏆" />
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
    festResults: { type: Array, default: () => [] },
});

const navItems = computed(() => teacherPortalNavItems(props.school.id));
</script>
