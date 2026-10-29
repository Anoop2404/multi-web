<template>
    <PortalLayout
        role-label="Student Portal"
        title="Document &amp; Certificate Vault"
        :subtitle="`${school.name} · Student ID ${student.reg_no}`"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="student.photo_url || ''"
    >
        <!-- Vault Header Banner -->
        <section class="rounded-3xl bg-gradient-to-br from-[#041525] via-[#0f3d7a] to-[#1e5aa8] text-white p-5 sm:p-6 mb-6 shadow-md relative overflow-hidden">
            <div class="relative flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1.5 max-w-xl">
                    <span class="inline-flex items-center gap-1 text-[10px] font-extrabold uppercase tracking-wider text-amber-300 bg-white/10 px-2.5 py-0.5 rounded-full border border-white/15">
                        📂 Digital Document Center
                    </span>
                    <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight">
                        Your Official Documents &amp; Certificates
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-200/90 leading-relaxed">
                        Download tamper-proof hall tickets, merit certificates, admit cards, and fee receipts.
                    </p>
                </div>
                <div class="text-right shrink-0">
                    <span class="text-xs font-bold text-slate-300 bg-black/25 px-3 py-1.5 rounded-xl border border-white/10">
                        {{ rows.length }} Total Record{{ rows.length === 1 ? '' : 's' }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Search & Filter Controls -->
        <div class="flex flex-col sm:flex-row gap-3 mb-5">
            <div class="relative flex-1">
                <input
                    v-model="query"
                    type="search"
                    class="field pl-9 text-sm rounded-xl"
                    placeholder="Search by exam or fest title…"
                    aria-label="Search documents"
                >
                <span class="absolute left-3 top-3 text-slate-400 text-sm">🔍</span>
            </div>

            <div class="flex flex-wrap gap-2 sm:w-auto">
                <select
                    v-model="kindFilter"
                    class="field sm:w-44 text-xs font-bold rounded-xl"
                    aria-label="Filter by document type"
                >
                    <option value="all">All Document Types</option>
                    <option value="Online exam">Online Exams</option>
                    <option value="Fest certificate">Fest Certificates</option>
                    <option value="Fest admit card">Admit Cards</option>
                </select>

                <select
                    v-model="statusFilter"
                    class="field sm:w-40 text-xs font-bold rounded-xl"
                    aria-label="Filter by status"
                >
                    <option value="all">All Statuses</option>
                    <option value="ready">Ready to Download</option>
                    <option value="submitted">Submitted</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
        </div>

        <!-- Document List -->
        <div class="space-y-3 mb-8">
            <article
                v-for="row in filteredRows"
                :key="row.key"
                class="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-sm transition hover:shadow-md hover:border-[#0f3d7a]/30"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <span
                            class="inline-block text-[10px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded-full"
                            :class="kindBadgeClass(row.kind)"
                        >
                            {{ row.kind }}
                        </span>
                        <h2 class="font-extrabold text-slate-900 text-sm sm:text-base leading-snug">
                            {{ row.title }}
                        </h2>
                        <p v-if="row.meta" class="text-xs text-slate-500 font-medium">{{ row.meta }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0 pt-2 sm:pt-0">
                        <a
                            v-for="link in row.links"
                            :key="link.href + link.label"
                            :href="link.href"
                            :target="link.external ? '_blank' : undefined"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold transition shadow-xs"
                            :class="link.tone === 'success'
                                ? 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-emerald-600/10'
                                : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
                        >
                            <svg v-if="link.tone === 'success'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>{{ link.label }}</span>
                        </a>
                        <span v-if="!row.links.length" class="text-xs text-slate-400 self-center italic">Nothing available yet</span>
                    </div>
                </div>
            </article>

            <!-- Empty Search State -->
            <div v-if="!filteredRows.length" class="rounded-3xl border border-dashed border-slate-200 bg-white p-10 text-center space-y-3">
                <span class="text-3xl">📂</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">No documents found</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        No passes or certificates match your search filters. Try clearing your query.
                    </p>
                </div>
                <div class="pt-2">
                    <button
                        type="button"
                        class="btn-secondary text-xs"
                        @click="query = ''; statusFilter = 'all'; kindFilter = 'all';"
                    >
                        Reset filters
                    </button>
                </div>
            </div>
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
const kindFilter = ref('all');

function kindBadgeClass(kind) {
    if (kind === 'Online exam') return 'bg-blue-50 text-[#0f3d7a] border border-blue-200';
    if (kind === 'Fest certificate') return 'bg-emerald-50 text-emerald-800 border border-emerald-200';
    if (kind === 'Fest admit card') return 'bg-amber-50 text-amber-900 border border-amber-200';
    return 'bg-slate-100 text-slate-700';
}

const rows = computed(() => {
    const mcq = (props.mcqExams ?? []).map((exam) => {
        const id = exam.registration_route_id ?? exam.id;
        const links = [];
        if (exam.show_hall_ticket) {
            links.push({ label: 'Download Hall Ticket', href: `/portal/student/${props.school.id}/mcq/${id}/hall-ticket`, external: true });
        }
        if (exam.certificate_url) {
            links.push({ label: 'Download Certificate', href: exam.certificate_url, external: true, tone: 'success' });
        }
        if (exam.invoice_url) {
            links.push({ label: 'Receipt / Invoice', href: exam.invoice_url, external: true });
        }
        if (exam.show_results || exam.can_take_online || exam.can_reattempt) {
            links.push({
                label: exam.show_results && !exam.can_reattempt ? 'View Result' : (exam.can_reattempt ? 'Reattempt' : 'Take Exam'),
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
            ? [{ label: 'Download Certificate', href: `/certificates/print/${c.uuid}`, external: true, tone: 'success' }]
            : [],
    }));

    const admits = (props.admitCardEvents ?? []).map((ev) => ({
        key: `admit-${ev.id}`,
        kind: 'Fest admit card',
        title: ev.title,
        meta: 'Authorized Student Hall Pass',
        status: 'ready',
        links: [{ label: 'Download Admit Card PDF', href: `/portal/student/${props.school.id}/fest/${ev.id}/admit-card`, external: true }],
    }));

    return [...mcq, ...fest, ...admits];
});

const filteredRows = computed(() => {
    const q = query.value.trim().toLowerCase();
    return rows.value.filter((row) => {
        if (kindFilter.value !== 'all' && row.kind !== kindFilter.value) {
            return false;
        }
        if (statusFilter.value !== 'all' && row.status !== statusFilter.value) {
            return false;
        }
        if (!q) return true;
        return `${row.title} ${row.kind} ${row.meta ?? ''}`.toLowerCase().includes(q);
    });
});
</script>
