<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Fest Schedule"
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
                        📅 Event & Stage Timetable
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Chronological breakdown of assigned fest items and venues.</p>
                </div>
                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
                    {{ festDaySlots?.length ?? 0 }} Items
                </span>
            </div>

            <ul v-if="festDaySlots?.length" class="space-y-3">
                <li v-for="(slot, i) in festDaySlots" :key="i" class="flex gap-4 items-start p-4 rounded-2xl border border-slate-200/90 bg-white shadow-sm transition hover:border-[#0f3d7a]/30">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#041525] to-[#0f3d7a] text-white font-extrabold text-sm shadow-sm">
                        #{ i + 1 }
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="font-bold text-slate-900 text-base">{{ slot.event_title }}</h3>
                                <p class="text-xs font-semibold text-[#0f3d7a] mt-0.5">{{ slot.item_title }}</p>
                            </div>
                            <span v-if="slot.chest_no" class="bg-amber-100 text-amber-900 font-bold px-2.5 py-1 rounded-full text-xs border border-amber-200">
                                Chest #{{ slot.chest_no }}
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-600 mt-3 pt-3 border-t border-slate-100">
                            <span v-if="slot.level_reg" class="font-mono bg-slate-100 px-2 py-0.5 rounded text-[11px] text-slate-700">Reg: {{ slot.level_reg }}</span>
                            <span v-if="slot.stage" class="font-medium text-slate-700 flex items-center gap-1">📍 Stage: {{ slot.stage }}</span>
                            <span v-if="slot.scheduled_at" class="font-bold text-[#0f3d7a] flex items-center gap-1">
                                ⏰ {{ new Date(slot.scheduled_at).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}
                            </span>
                        </div>
                    </div>
                </li>
            </ul>
            <EmptyState v-else title="No scheduled fest items yet" description="Your fest event schedule will appear here once it's published." icon="🎭" />
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
    festDaySlots: { type: Array, default: () => [] },
});

const navItems = computed(() => teacherPortalNavItems(props.school.id));
</script>

