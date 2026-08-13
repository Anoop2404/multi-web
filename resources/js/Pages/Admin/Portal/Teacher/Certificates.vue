<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Certificates & Badges"
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
                        🏅 Official Sahodaya Certificates
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Digitally verified credentials from fest competitions & training workshops.</p>
                </div>
                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-amber-50 text-amber-900 border border-amber-200">
                    {{ festCerts?.length ?? 0 }} Certificates
                </span>
            </div>

            <div v-if="festCerts?.length" class="grid gap-4 sm:grid-cols-2">
                <div v-for="(c, i) in festCerts" :key="i" class="flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white to-slate-50/50 p-5 shadow-sm transition hover:border-[#0f3d7a]/30 hover:shadow-md">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#041525] to-[#0f3d7a] text-white text-lg font-bold shadow-sm">
                                🎖️
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-bold text-slate-900 text-base leading-snug truncate">{{ c.event?.title ?? 'Fest Event' }}</h3>
                                <p v-if="c.item?.title" class="text-xs font-semibold text-[#0f3d7a] mt-0.5 truncate">{{ c.item.title }}</p>
                            </div>
                        </div>

                        <div v-if="c.uuid" class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-[11px] font-mono text-slate-400 truncate max-w-[150px]" :title="c.uuid">ID: {{ c.uuid.substring(0, 8) }}…</span>
                            <a :href="`/certificates/print/${c.uuid}`" target="_blank"
                               class="inline-flex items-center gap-1.5 text-xs font-bold text-white bg-[#0f3d7a] hover:bg-[#041525] px-3.5 py-2 rounded-xl transition shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Print / Download PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <EmptyState v-else title="No certificates yet" description="Certificates earned from fest events and training workshops will appear here." icon="🏆" />
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

