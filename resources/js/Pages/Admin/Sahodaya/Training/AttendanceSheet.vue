<template>
    <SahodayaAdminLayout :title="`${program.title} — Attendance sheet`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="program.title" eyebrow="Attendance sheet"
                    description="Blank printable sheet for physical venue verification — empty mark boxes to tick by hand.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/attendance`"
                      class="btn-secondary text-sm">
                    ← Mark attendance
                </Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/attendance/report`"
                      class="btn-secondary text-sm">
                    Attendance report
                </Link>
                <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/attendance/sheet/pdf`"
                   class="btn-primary text-sm">
                    Download sheet PDF
                </a>
            </template>
        </PageHeader>

        <div class="card mb-4 bg-slate-50 border-slate-200 text-sm text-slate-700">
            <p class="font-semibold text-slate-900">What this is</p>
            <p class="mt-1">A blank attendance sheet with Sahodaya branding, event name, participant list, and empty boxes for physical marking at the venue.</p>
            <p class="mt-2 text-xs text-slate-500">For filled present/absent data, use <strong>Attendance report</strong>.</p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
            <div class="card text-center">
                <p class="text-2xl font-bold">{{ attendeeCount }}</p>
                <p class="text-xs text-gray-500">Participants on sheet</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold">{{ sessionCount }}</p>
                <p class="text-xs text-gray-500">Session columns</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-indigo-700">Blank</p>
                <p class="text-xs text-gray-500">Mark boxes</p>
            </div>
        </div>

        <div class="card text-sm text-gray-600">
            <p class="font-semibold text-gray-900 mb-2">Sheet includes</p>
            <ul class="list-disc pl-5 space-y-1">
                <li>Sahodaya logo and name</li>
                <li>Training / event title, dates, venue</li>
                <li>Sl No, Teacher name, Category, School</li>
                <li>Blank mark box per session + signature column</li>
                <li>Prepared / Verified / Seal lines</li>
            </ul>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    attendeeCount: { type: Number, default: 0 },
    sessionCount: { type: Number, default: 0 },
});
</script>
