<template>
    <PortalLayout
        role-label="Student Portal"
        title="Documents"
        :subtitle="`${school.name} · hall tickets, certificates & invoices`"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="student.photo_url || ''"
    >
        <div class="rounded-2xl bg-gradient-to-br from-[#041525] to-[#0f3d7a] text-white p-5 sm:p-6 mb-5 shadow-md">
            <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-amber-300">Report center</p>
            <h2 class="mt-1 text-lg font-extrabold">All your exam documents in one place</h2>
            <p class="mt-1 text-sm text-slate-200/90">Search by exam title, then open hall ticket, certificate, invoice, or result.</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 mb-4">
            <input
                v-model="query"
                type="search"
                class="field flex-1"
                placeholder="Search by exam title…"
            >
            <select v-model="statusFilter" class="field sm:w-48">
                <option value="all">All statuses</option>
                <option value="ready">Docs available</option>
                <option value="submitted">Submitted</option>
                <option value="pending">Pending</option>
            </select>
        </div>

        <div class="space-y-3">
            <article
                v-for="row in filteredRows"
                :key="row.key"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ row.kind }}</p>
                        <h3 class="font-bold text-slate-900 text-sm sm:text-base">{{ row.title }}</h3>
                        <p v-if="row.meta" class="text-xs text-slate-500 mt-0.5">{{ row.meta }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a
                            v-for="link in row.links"
                            :key="link.href + link.label"
                            :href="link.href"
                            :target="link.external ? '_blank' : undefined"
                            rel="noopener"
                            class="inline-flex items-center rounded-xl border px-3 py-1.5 text-xs font-bold"
                            :class="link.tone === 'success'
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                        >{{ link.label }}</a>
                        <span v-if="!row.links.length" class="text-xs text-slate-400 self-center">Nothing ready yet</span>
                    </div>
                </div>
            </article>

            <p v-if="!filteredRows.length" class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-400">
                No documents match your filters.
            </p>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed, ref } from 'vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';

const props = defineProps({
    school: Object,
    student: Object,
    mcqExams: { type: Array, default: () => [] },
    festCerts: { type: Array, default: () => [] },
    admitCardEvents: { type: Array, default: () => [] },
});

const navItems = computed(() => studentPortalNavItems(props.school.id));
const query = ref('');
const statusFilter = ref('all');

const rows = computed(() => {
    const mcq = (props.mcqExams ?? []).map((exam) => {
        const id = exam.registration_route_id ?? exam.id;
        const links = [];
        if (exam.show_hall_ticket) {
            links.push({ label: 'Hall ticket', href: `/portal/student/${props.school.id}/mcq/${id}/hall-ticket`, external: true });
        }
        if (exam.certificate_url) {
            links.push({ label: 'Certificate', href: exam.certificate_url, external: true, tone: 'success' });
        }
        if (exam.invoice_url) {
            links.push({ label: 'Invoice', href: exam.invoice_url, external: true });
        }
        if (exam.show_results || exam.can_take_online || exam.can_reattempt) {
            links.push({
                label: exam.show_results && !exam.can_reattempt ? 'Result' : (exam.can_reattempt ? 'Reattempt' : 'Exam'),
                href: `/portal/student/${props.school.id}/mcq/${id}/exam`,
            });
        }
        const metaBits = [exam.lifecycle_status?.label, exam.delivery_mode];
        if (exam.venue_keycode) {
            metaBits.push(`Keycode ${exam.venue_keycode}`);
        }
        return {
            key: `mcq-${id}`,
            kind: 'Online exam',
            title: exam.exam?.title ?? exam.title ?? 'Exam',
            meta: metaBits.filter(Boolean).join(' · '),
            status: links.length ? 'ready' : (exam.status === 'submitted' ? 'submitted' : 'pending'),
            links,
        };
    });

    const fest = (props.festCerts ?? []).map((c, i) => ({
        key: `fest-cert-${c.uuid ?? i}`,
        kind: 'Fest certificate',
        title: `${c.event?.title ?? 'Event'} — ${c.item?.title ?? c.student?.name ?? 'Certificate'}`,
        meta: [c.mark?.grade && `Grade ${c.mark.grade}`, c.mark?.position && `Pos ${c.mark.position}`].filter(Boolean).join(' · '),
        status: c.uuid ? 'ready' : 'pending',
        links: c.uuid
            ? [{ label: 'Download', href: `/certificates/print/${c.uuid}`, external: true, tone: 'success' }]
            : [],
    }));

    const admits = (props.admitCardEvents ?? []).map((ev) => ({
        key: `admit-${ev.id}`,
        kind: 'Fest admit card',
        title: ev.title,
        meta: null,
        status: 'ready',
        links: [{ label: 'Admit card PDF', href: `/portal/student/${props.school.id}/fest/${ev.id}/admit-card`, external: true }],
    }));

    return [...mcq, ...fest, ...admits];
});

const filteredRows = computed(() => {
    const q = query.value.trim().toLowerCase();
    return rows.value.filter((row) => {
        if (statusFilter.value !== 'all' && row.status !== statusFilter.value) {
            return false;
        }
        if (!q) return true;
        return `${row.title} ${row.kind} ${row.meta ?? ''}`.toLowerCase().includes(q);
    });
});
</script>
