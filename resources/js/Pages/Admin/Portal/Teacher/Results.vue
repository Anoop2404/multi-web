<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Fest Results"
        :subtitle="school.name"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="teacher?.photo_url"
        show-avatar-placeholder
    >
        <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                        🏆 Published Competition Results
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Verified grades, ranks, and point achievements.</p>
                </div>
                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-blue-50 text-[#0f3d7a] border border-blue-200">
                    {{ festResults?.length ?? 0 }} Results
                </span>
            </div>

            <div v-if="festResults?.length" class="grid gap-3 sm:grid-cols-2">
                <div v-for="(r, i) in festResults" :key="i" class="flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm transition hover:border-[#0f3d7a]/30">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-xs font-semibold text-slate-500">{{ r.event_title }}</p>
                                <h3 class="font-bold text-slate-900 text-base mt-0.5">{{ r.item_title }}</h3>
                            </div>
                            <span v-if="r.chest_no" class="bg-slate-100 text-slate-700 font-mono text-xs px-2 py-0.5 rounded font-bold shrink-0">
                                #{ r.chest_no }
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                        <p v-if="!(r.grade || r.position || r.score)" class="text-xs text-slate-400 font-medium">Evaluation in progress</p>
                        <div v-else class="flex flex-wrap items-center gap-2">
                            <span v-if="r.position" class="inline-flex items-center gap-1 font-extrabold text-xs px-2.5 py-1 rounded-full bg-amber-100 text-amber-900 border border-amber-300">
                                🥇 Rank #{{ r.position }}
                            </span>
                            <span v-if="r.grade" class="inline-flex items-center gap-1 font-bold text-xs px-2.5 py-1 rounded-full bg-blue-50 text-[#0f3d7a] border border-blue-200">
                                Grade {{ r.grade }}
                            </span>
                            <span v-if="r.score" class="font-extrabold text-xs text-slate-700 bg-slate-100 px-2 py-1 rounded-lg">
                                {{ r.score }} pts
                            </span>
                        </div>
                    </div>
                </div>
            </div>
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

