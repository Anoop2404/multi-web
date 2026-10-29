<template>
    <PortalLayout
        role-label="Student Portal"
        title="Fest & Sports Certificates"
        :subtitle="`${school.name} · Student ID ${student.reg_no}`"
        accent="navy"
        :nav-items="navItems"
    >
        <!-- Activity Sub-navigation Tabs -->
        <div class="flex flex-wrap items-center gap-2 mb-6 border-b border-slate-200/80 pb-3">
            <a
                :href="`/portal/student/${school.id}/fest/schedule`"
                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 hover:text-slate-900 transition"
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
                class="px-4 py-2 rounded-xl text-xs font-extrabold bg-[#0f3d7a] text-white shadow-sm"
            >
                📜 Certificates
            </a>
        </div>

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900 tracking-tight">Verified Fest &amp; Sports Certificates</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Official printable certificates with tamper-proof QR code verification</p>
                </div>
                <span class="text-xs font-bold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">
                    {{ festCerts?.length ?? 0 }} Certificate{{ (festCerts?.length ?? 0) === 1 ? '' : 's' }}
                </span>
            </div>

            <div v-if="festCerts?.length" class="grid gap-3 sm:grid-cols-2">
                <article
                    v-for="(c, i) in festCerts"
                    :key="i"
                    class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm space-y-3 hover:border-emerald-300 transition"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <span class="inline-flex items-center gap-1 text-[10px] font-extrabold uppercase tracking-wider text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200 mb-1">
                                ✓ Verified Credential
                            </span>
                            <h3 class="font-bold text-slate-900 text-sm sm:text-base leading-snug">
                                {{ c.event?.title ?? 'Fest Event' }}
                            </h3>
                            <p class="text-xs text-slate-600 font-medium mt-0.5">
                                {{ c.item?.title ?? c.student?.name }}
                            </p>
                        </div>
                    </div>

                    <div v-if="c.mark?.grade || c.mark?.position" class="flex flex-wrap items-center gap-2 text-xs pt-1">
                        <span v-if="c.mark?.grade" class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-800 font-bold border border-emerald-200">
                            Grade {{ c.mark.grade }}
                        </span>
                        <span v-if="c.mark?.position" class="px-2 py-0.5 rounded-lg bg-amber-50 text-amber-900 font-bold border border-amber-200">
                            Position {{ c.mark.position }}
                        </span>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex justify-end">
                        <a
                            v-if="c.uuid"
                            :href="`/certificates/print/${c.uuid}`"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-extrabold shadow-sm transition"
                        >
                            <span>Download PDF</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </a>
                        <span v-else class="text-xs text-slate-400">Processing certificate generation...</span>
                    </div>
                </article>
            </div>

            <!-- Empty State -->
            <div v-else class="rounded-3xl border border-dashed border-slate-200 bg-white p-10 text-center space-y-3">
                <span class="text-3xl">📜</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">No certificates generated yet</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        Official certificates are automatically issued once the results and positions are approved.
                    </p>
                </div>
                <div class="pt-2">
                    <a :href="`/portal/student/${school.id}/results`" class="btn-secondary text-xs">
                        Check published results →
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
    festCerts: { type: Array, default: () => [] },
});

const navItems = computed(() => studentPortalNavItems(props.school.id));
</script>
