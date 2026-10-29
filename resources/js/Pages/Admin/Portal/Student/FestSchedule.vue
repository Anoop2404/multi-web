<template>
    <PortalLayout
        role-label="Student Portal"
        title="Fest & Sports Timetable"
        :subtitle="`${school.name} · Student ID ${student.reg_no}`"
        accent="navy"
        :nav-items="navItems"
    >
        <!-- Activity Sub-navigation Tabs -->
        <div class="flex flex-wrap items-center gap-2 mb-6 border-b border-slate-200/80 pb-3">
            <a
                :href="`/portal/student/${school.id}/fest/schedule`"
                class="px-4 py-2 rounded-xl text-xs font-extrabold bg-[#0f3d7a] text-white shadow-sm"
            >
                📅 Timetable &amp; Stages
            </a>
            <a
                :href="`/portal/student/${school.id}/results`"
                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 hover:text-slate-900 transition"
            >
                🏆 Fest Results
            </a>
            <a
                :href="`/portal/student/${school.id}/sports-results`"
                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 hover:text-slate-900 transition"
            >
                🏃 Sports Results
            </a>
            <a
                :href="`/portal/student/${school.id}/fest-registrations`"
                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 hover:text-slate-900 transition"
            >
                📝 Registrations
            </a>
            <a
                :href="`/portal/student/${school.id}/certificates`"
                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 hover:text-slate-900 transition"
            >
                📜 Certificates
            </a>
        </div>

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900 tracking-tight">Your Scheduled Competition Stages</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Report to your assigned venue stage 15 minutes before your performance order</p>
                </div>
                <span class="text-xs font-bold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">
                    {{ festDaySlots?.length ?? 0 }} Slot{{ (festDaySlots?.length ?? 0) === 1 ? '' : 's' }}
                </span>
            </div>

            <div v-if="festDaySlots?.length" class="grid gap-3 sm:grid-cols-2">
                <article
                    v-for="(slot, i) in festDaySlots"
                    :key="i"
                    class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm space-y-3 hover:border-[#0f3d7a]/30 transition"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="font-bold text-slate-900 text-sm sm:text-base leading-snug">
                                {{ slot.event_title }}
                            </h3>
                            <p class="text-xs text-slate-600 font-medium mt-0.5">{{ slot.item_title }}</p>
                        </div>
                        <span
                            v-if="slot.stage"
                            class="text-xs font-extrabold px-3 py-1 rounded-xl bg-blue-50 text-[#0f3d7a] border border-blue-200 shrink-0"
                        >
                            {{ slot.stage }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-100">
                        <div v-if="slot.chest_no" class="flex items-center gap-1.5 font-bold text-[#0f3d7a]">
                            <span class="text-slate-400 font-normal">Chest:</span>
                            <span class="bg-blue-50 px-2 py-0.5 rounded-md">#{{ slot.chest_no }}</span>
                        </div>
                        <div v-if="slot.order" class="flex items-center gap-1.5 font-bold text-slate-800">
                            <span class="text-slate-400 font-normal">Order:</span>
                            <span>#{{ slot.order }}</span>
                        </div>
                        <div v-if="slot.level_reg" class="flex items-center gap-1.5 text-slate-500">
                            <span>Reg:</span>
                            <span class="font-mono">{{ slot.level_reg }}</span>
                        </div>
                    </div>

                    <div v-if="slot.scheduled_at" class="pt-2 border-t border-slate-100 flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                        <span>🕒</span>
                        <span>{{ formatWhen(slot.scheduled_at) }}</span>
                    </div>
                </article>
            </div>

            <!-- Empty State -->
            <div v-else class="rounded-3xl border border-dashed border-slate-200 bg-white p-10 text-center space-y-3">
                <span class="text-3xl">🎭</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">No scheduled items found</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        Stage allocations and performance orders have not been released for your entries yet. Check back closer to fest day.
                    </p>
                </div>
                <div class="pt-2">
                    <a :href="`/portal/student/${school.id}/fest-registrations`" class="btn-secondary text-xs">
                        Check event registrations →
                    </a>
                </div>
            </div>
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';

const props = defineProps({
    school: Object,
    student: Object,
    festDaySlots: { type: Array, default: () => [] },
});

const navItems = computed(() => studentPortalNavItems(props.school.id));

function formatWhen(iso) {
    try {
        return new Date(iso).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return iso;
    }
}
</script>
