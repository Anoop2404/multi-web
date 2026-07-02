<template>
    <SahodayaAdminLayout title="Dashboard" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-6">

            <!-- Welcome / hero strip -->
            <div class="rounded-2xl bg-gradient-to-br from-[#041525] via-[#0a2744] to-[#0f3d7a] text-white p-6 flex flex-col sm:flex-row sm:items-center gap-4 shadow-lg">
                <div class="flex-1">
                    <p class="text-[#fbbf24] text-xs font-bold uppercase tracking-widest mb-1">Sahodaya Complex</p>
                    <h2 class="text-2xl font-extrabold mb-0.5">{{ sahodaya.name }}</h2>
                    <p class="text-white/70 text-sm">
                        CBSE Sahodaya School Complex —
                        <span class="font-semibold text-white">{{ stats.approved_schools ?? 0 }} approved member{{ (stats.approved_schools ?? 0) === 1 ? '' : 's' }}</span><template v-if="(stats.pending_schools ?? 0) > 0"> · <span class="font-semibold text-[#fbbf24]">{{ stats.pending_schools }} pending</span></template><template v-if="(stats.registered_schools ?? 0) > (stats.approved_schools ?? 0) + (stats.pending_schools ?? 0)"> · {{ stats.registered_schools }} registered</template>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 shrink-0">
                    <a v-if="websiteEnabled && publicUrl" :href="publicUrl" target="_blank"
                       class="btn-secondary border-white/20 bg-white/10 text-white hover:bg-white/20 hover:border-white/30">
                        Preview Site ↗
                    </a>
                    <Link v-if="websiteEnabled" :href="`/sahodaya-admin/${sahodaya.id}/public-content`"
                          class="btn-primary bg-[#fbbf24] text-[#041525] shadow-none hover:bg-[#f59e0b]">
                        Edit Website →
                    </Link>
                </div>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard v-if="canSee('membership')" :value="stats.approved_schools ?? 0" label="Approved Members" color="blue" icon="🏫" />
                <StatCard v-if="canSee('membership')" :value="stats.pending_schools ?? 0" label="Pending Schools" color="amber" icon="⏳" />
                <StatCard v-if="canSee('fest')" :value="stats.fest_events ?? 0" label="Fest Events" color="amber" icon="🏆" />
                <StatCard v-if="canSee('fest')" :value="stats.active_fest_events ?? 0" label="Active Fests" color="green" icon="📣"
                          :hint="`${stats.fest_registrations ?? 0} registrations`" />
                <StatCard v-if="canSee('mcq')" :value="stats.mcq_exams ?? 0" label="MCQ Exams" color="indigo" icon="📝" />
                <StatCard v-if="canSee('training')" :value="stats.training_programs ?? 0" label="Training" color="navy" icon="🎓" />
                <StatCard v-if="websiteEnabled && canSee('website')" :value="stats.office_bearers"  label="Office Bearers"   color="navy" icon="👥" />
                <StatCard v-else-if="canSee('membership')" :value="stats.total_students ?? 0" label="Active Students" color="navy" icon="👨‍🎓"
                          :hint="'From approved members only'" />
                <StatCard v-if="websiteEnabled && canSee('website')" :value="stats.circulars"        label="Circulars"        color="indigo" icon="📄" />
                <StatCard v-if="!websiteEnabled && canSee('membership')" :value="`₹${Number(stats.payments_pending_verification_amount || stats.pending_amount || 0).toLocaleString('en-IN')}`" label="Payment Pending" color="amber" icon="💳"
                          :hint="`${stats.payments_pending_verification ?? stats.pending_payments ?? 0} awaiting verification`" />
                <StatCard v-if="!websiteEnabled && canSee('membership')" :value="`₹${Number(stats.approved_amount || 0).toLocaleString('en-IN')}`" label="Approved Fees" color="green" icon="✅" />
                <StatCard v-if="canSee('membership')" :value="`₹${Number(stats.payment_not_done_amount || stats.payment_due_amount || 0).toLocaleString('en-IN')}`" label="Payment Not Done" color="navy" icon="🧾"
                          :hint="`${stats.payment_not_done ?? stats.payment_due ?? 0} schools`" />
                <StatCard v-if="websiteEnabled && canSee('membership')" :value="stats.total_students ?? 0" label="Active Students" color="navy" icon="👨‍🎓"
                          :hint="'From approved members only'" />
                <StatCard v-if="websiteEnabled && canSee('membership')" :value="`₹${Number(stats.payments_pending_verification_amount || stats.pending_amount || 0).toLocaleString('en-IN')}`" label="Payment Pending" color="amber" icon="💳"
                          :hint="`${stats.payments_pending_verification ?? stats.pending_payments ?? 0} awaiting verification`" />
                <StatCard v-if="websiteEnabled && canSee('membership')" :value="`₹${Number(stats.approved_amount || 0).toLocaleString('en-IN')}`" label="Approved Fees" color="green" icon="✅" />
            </div>

            <!-- Fest & programs ops -->
            <div v-if="canSee('fest') || canSee('mcq') || canSee('training')" class="card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="section-title text-base">Fest & program hubs</h3>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events`" class="link-brand text-xs">All events →</Link>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
                    <Link v-for="p in festOps.programs" :key="p.slug"
                          :href="programHubHref(p)"
                          class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 text-center hover:border-[#0f3d7a]/30 transition">
                        <p class="text-2xl">{{ p.icon }}</p>
                        <p class="text-sm font-semibold text-slate-900 mt-2">{{ p.label }}</p>
                    </Link>
                </div>
                <div class="grid sm:grid-cols-3 gap-3">
                    <QuickAction v-if="canSee('mcq')" :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams`"
                                 icon="📝" label="MCQ Exams" :desc="`${stats.mcq_exams ?? 0} exams`" />
                    <QuickAction v-if="canSee('training')" :href="`/sahodaya-admin/${sahodaya.id}/training`"
                                 icon="🎓" label="Teacher Training" :desc="`${stats.training_programs ?? 0} programs`" />
                    <QuickAction v-if="canSee('fest')" :href="`/sahodaya-admin/${sahodaya.id}/display-screens`"
                                 icon="📺" label="Display screens" desc="Live fest boards" />
                </div>
            </div>

            <!-- Dashboard extras -->
            <div v-if="dashboardExtras?.programStatus?.length" class="card">
                <h3 class="section-title text-base mb-4">Program status</h3>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <Link v-for="p in dashboardExtras.programStatus" :key="p.key" :href="p.hub_url"
                          class="rounded-xl border border-slate-200 p-4 hover:border-[#0f3d7a]/30 transition">
                        <p class="font-semibold text-slate-900">{{ p.label }}</p>
                        <p class="text-xs text-slate-500 mt-2">
                            {{ p.open_events }} open · {{ p.registrations }} registrations
                            <span v-if="p.results_pending" class="text-amber-700"> · {{ p.results_pending }} results pending</span>
                        </p>
                    </Link>
                </div>
            </div>

            <div v-if="dashboardExtras?.financeSummary" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div v-for="(amount, key) in dashboardExtras.financeSummary" :key="key" class="card card--muted !py-4 text-center">
                    <p class="text-lg font-bold text-green-700">₹{{ fmt(amount) }}</p>
                    <p class="text-xs text-slate-500 mt-1 capitalize">{{ key }} fees collected</p>
                </div>
            </div>

            <div v-if="dashboardExtras?.schoolActivity?.length" class="card">
                <h3 class="section-title text-base mb-3">School participation</h3>
                <div class="flex flex-wrap gap-2">
                    <span v-for="s in dashboardExtras.schoolActivity" :key="s.id"
                          class="text-xs px-2 py-1 rounded-full border"
                          :class="s.active ? 'bg-green-50 border-green-200 text-green-800' : 'bg-slate-50 border-slate-200 text-slate-500'">
                        {{ s.name }}
                    </span>
                </div>
            </div>

            <!-- Action queue -->
            <div v-if="actionQueueItems.length" class="card">
                <h3 class="section-title text-base mb-4">Action queue</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    <ActionBanner v-for="item in actionQueueItems" :key="item.key"
                                  :href="item.href" :count="item.count" :label="item.label"
                                  :color="item.color" :icon="item.icon" />
                </div>
            </div>

            <!-- Attention required -->
            <div v-if="canSee('membership') && (pendingSchoolsCount > 0 || pendingPaymentsCount > 0 || (stats.payment_not_done ?? stats.payment_due ?? 0) > 0)" class="grid sm:grid-cols-2 gap-4">
                <ActionBanner v-if="pendingSchoolsCount > 0"
                              :href="`/sahodaya-admin/${sahodaya.id}/membership/reports?tab=schools`"
                              :count="pendingSchoolsCount"
                              label="schools awaiting membership approval"
                              color="amber" icon="⏳" />
                <ActionBanner v-if="(stats.payment_not_done ?? stats.payment_due ?? 0) > 0"
                              :href="`/sahodaya-admin/${sahodaya.id}/membership/payments?status=payment-due`"
                              :count="stats.payment_not_done ?? stats.payment_due"
                              label="schools with payment not done"
                              color="navy" icon="🧾" />
                <ActionBanner v-if="(stats.payments_pending_verification ?? pendingPaymentsCount ?? 0) > 0"
                              :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`"
                              :count="stats.payments_pending_verification ?? pendingPaymentsCount"
                              label="payments pending verification"
                              color="green" icon="💳" />
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <!-- Active fest events -->
                <div v-if="canSee('fest')" class="card">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="section-title text-base flex items-center gap-2">
                            <span class="text-lg">🏆</span> Active fest events
                        </h3>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/events`" class="link-brand text-xs">All events →</Link>
                    </div>
                    <div v-if="activeEvents.length" class="divide-y divide-slate-100">
                        <div v-for="ev in activeEvents" :key="ev.id"
                             class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ ev.title }}</p>
                                <p class="text-xs text-slate-500 mt-0.5 capitalize">
                                    {{ ev.event_type?.replace(/_/g, ' ') }} · {{ ev.status?.replace(/_/g, ' ') }}
                                    <span v-if="ev.registrations_count != null"> · {{ ev.registrations_count }} reg.</span>
                                </p>
                            </div>
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${ev.id}`"
                                  class="text-xs link-brand shrink-0">Manage →</Link>
                        </div>
                    </div>
                    <EmptyState v-else title="No active fest events" description="Open a program hub to create or publish an event." icon="🏆">
                        <template #action>
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/kalotsav`" class="btn-secondary text-xs">Kalotsav hub</Link>
                        </template>
                    </EmptyState>
                </div>

                <!-- Recent Circulars -->
                <div v-if="websiteEnabled && canSee('website')" class="card">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="section-title text-base flex items-center gap-2">
                            <span class="text-lg">📄</span> Recent Circulars
                        </h3>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/circulars`" class="link-brand text-xs">View all →</Link>
                    </div>
                    <div v-if="recentCirculars.length" class="divide-y divide-slate-100">
                        <div v-for="c in recentCirculars" :key="c.id"
                             class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-sm shrink-0">📄</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate">{{ c.title }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[11px] bg-amber-50 text-amber-800 px-1.5 py-0.5 rounded font-medium">{{ c.category || 'General' }}</span>
                                    <span class="text-xs text-slate-400">{{ c.issued_date ? new Date(c.issued_date).toLocaleDateString('en-IN', {day:'2-digit',month:'short'}) : '' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <EmptyState v-else title="No circulars yet" description="Upload notices and documents for member schools." icon="📄">
                        <template #action>
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/circulars`" class="btn-secondary text-xs">Upload Circular</Link>
                        </template>
                    </EmptyState>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <h3 class="section-title text-base mb-4">Quick Actions</h3>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <QuickAction v-if="websiteEnabled && canSee('website')" :href="`/sahodaya-admin/${sahodaya.id}/public-content`"
                                 icon="✏️" label="Edit Website" desc="Content, announcements, links" />
                    <QuickAction v-if="!websiteEnabled && canSee('website')" :href="`/sahodaya-admin/${sahodaya.id}/public-content`"
                                 icon="✏️" label="Portal Content" desc="Tagline, motto, contact details" />
                    <QuickAction v-if="websiteEnabled && canSee('website')" :href="`/sahodaya-admin/${sahodaya.id}/circulars`"
                                 icon="📤" label="Upload Circular" desc="Notices and documents" />
                    <QuickAction v-if="websiteEnabled && canSee('website')" :href="`/sahodaya-admin/${sahodaya.id}/office-bearers`"
                                 icon="👥" label="Office Bearers" desc="Update leadership details" />
                    <QuickAction v-if="canSee('membership')" :href="`/sahodaya-admin/${sahodaya.id}/schools`"
                                 icon="🏫" label="Member Schools" desc="Approved member schools" />
                    <QuickAction v-if="canSee('membership')" :href="`/sahodaya-admin/${sahodaya.id}/membership/submissions`"
                                 icon="👨‍🎓" label="Student Counts" desc="View totals by school" />
                    <QuickAction v-if="canSee('membership')" :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`"
                                 icon="💳" label="Verify Payments" desc="Membership fee proofs" />
                    <QuickAction v-if="canSee('membership')" :href="`/sahodaya-admin/${sahodaya.id}/membership/reports`"
                                 icon="📊" label="Reports" desc="Summary & CSV exports" />
                    <QuickAction v-if="canSee('membership')" :href="`/sahodaya-admin/${sahodaya.id}/membership/settings`"
                                 icon="⚙️" label="Membership Config" desc="Fees, windows, rules" />
                </div>
            </div>

        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, defineComponent, h } from 'vue';

const page = usePage();
const websiteEnabled = computed(() => page.props.features?.website_enabled ?? false);
const isStaffUser = computed(() => page.props.isStaff);

const STAFF_SECTIONS = {
    website: ['website.view', 'website.manage', 'website.news'],
    membership: ['membership.view', 'membership.manage'],
    fest: ['fest.view', 'fest.manage', 'fest.marks', 'fest.registrations', 'fest.results', 'fest.settings', 'fest.finance', 'fest.certificates', 'fest.catering', 'fest.schedule'],
    mcq: ['mcq.view', 'mcq.manage', 'mcq.attendance', 'mcq.marks'],
    training: ['fest.view', 'fest.manage'],
};

function canSee(section) {
    if (!isStaffUser.value) return true;
    const perms = page.props.staffPermissions ?? [];
    const required = STAFF_SECTIONS[section];
    if (!required) return true;
    return required.some((p) => perms.includes(p));
}

const props = defineProps({
    sahodaya:                Object,
    publicUrl:               { type: String, default: null },
    pendingSchoolsCount:     { type: Number, default: 0 },
    pendingSubmissionsCount: { type: Number, default: 0 },
    pendingPaymentsCount:    { type: Number, default: 0 },
    stats:                   { type: Object, default: () => ({}) },
    actionQueue:             { type: Object, default: () => ({}) },
    recentCirculars:         { type: Array,  default: () => [] },
    activeEvents:            { type: Array, default: () => [] },
    festOps:                 { type: Object, default: () => ({ programs: [] }) },
    dashboardExtras:         { type: Object, default: () => ({}) },
});

function programHubHref(p) {
    const base = `/sahodaya-admin/${props.sahodaya?.id}`;
    if (String(p.prefix).startsWith('programs/')) {
        return `${base}/${p.prefix}`;
    }
    return `${base}/${p.prefix}`;
}

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

const actionQueueItems = computed(() => {
    const q = props.actionQueue ?? {};
    const base = `/sahodaya-admin/${props.sahodaya?.id}`;
    const map = {
        membership_data_pending: { href: `${base}/membership/reports?tab=submissions`, label: 'membership data reviews pending', color: 'amber', icon: '📋' },
        membership_payments: { href: `${base}/membership/payments`, label: 'membership payments to verify', color: 'green', icon: '💳' },
        fest_fee_proofs: { href: `${base}/fest/payments`, label: 'fest fee proofs awaiting approval', color: 'blue', icon: '🏆' },
        mcq_fee_proofs: { href: `${base}/mcq/payments`, label: 'MCQ batch fees awaiting approval', color: 'indigo', icon: '📝' },
        fest_appeals: { href: `${base}/events`, label: 'open fest appeals', color: 'amber', icon: '⚖️' },
        fest_registrations_review: { href: `${base}/events`, label: 'fest registrations to review', color: 'navy', icon: '📥' },
    };
    return Object.entries(q).map(([key, count]) => ({ key, count, ...map[key] })).filter((i) => i.label);
});

const colorMap = {
    blue:   { bg: 'bg-blue-50',   text: 'text-blue-600',   num: 'text-blue-700' },
    navy:   { bg: 'bg-[#eff6ff]', text: 'text-[#1e5aa8]',  num: 'text-[#0f3d7a]' },
    indigo: { bg: 'bg-indigo-50', text: 'text-indigo-600', num: 'text-indigo-700' },
    amber:  { bg: 'bg-amber-50',  text: 'text-amber-600',  num: 'text-amber-700' },
    green:  { bg: 'bg-green-50',  text: 'text-green-600',  num: 'text-green-700' },
};

const StatCard = defineComponent({
    props: { value: [Number, String], label: String, color: String, icon: String, hint: String },
    setup(props) {
        return () => {
            const c = colorMap[props.color] || colorMap.navy;
            return h('div', { class: `${c.bg} rounded-2xl p-5 flex items-center gap-4` }, [
                h('div', { class: `w-11 h-11 rounded-xl bg-white/60 flex items-center justify-center text-xl shrink-0 shadow-sm` }, props.icon),
                h('div', {}, [
                    h('p', { class: `text-2xl font-extrabold ${c.num}` }, props.value ?? '—'),
                    h('p', { class: `text-xs text-gray-500 font-medium mt-0.5` }, props.label),
                    props.hint ? h('p', { class: 'text-[10px] text-gray-400 mt-0.5' }, props.hint) : null,
                ]),
            ]);
        };
    },
});

const ActionBanner = defineComponent({
    props: { href: String, count: Number, label: String, color: String, icon: String },
    setup(props) {
        const bannerStyles = {
            amber: 'border-amber-200 bg-amber-50 hover:bg-amber-100',
            green: 'border-green-200 bg-green-50 hover:bg-green-100',
            blue:  'border-blue-200 bg-blue-50 hover:bg-blue-100',
        };

        return () => {
            const c = colorMap[props.color] || colorMap.amber;
            const banner = bannerStyles[props.color] || bannerStyles.amber;

            return h(Link, {
                href: props.href,
                class: `flex items-center gap-3 p-4 rounded-2xl border-2 transition ${banner}`,
            }, {
                default: () => [
                    h('div', { class: 'w-10 h-10 rounded-xl bg-white/70 flex items-center justify-center text-xl shrink-0' }, props.icon),
                    h('div', {}, [
                        h('p', { class: `text-lg font-extrabold ${c.num}` }, String(props.count)),
                        h('p', { class: 'text-xs text-gray-600' }, props.label),
                    ]),
                ],
            });
        };
    },
});

const QuickAction = defineComponent({
    props: { href: String, icon: String, label: String, desc: String },
    setup(props) {
        return () => h(Link, {
            href: props.href,
            class: 'flex items-start gap-3 p-4 rounded-2xl border border-slate-200/90 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition duration-150 hover:-translate-y-0.5 hover:border-[#0f3d7a]/30 hover:shadow-[0_4px_16px_rgba(15,61,122,0.1)] group',
        }, {
            default: () => [
                h('div', { class: 'w-10 h-10 rounded-xl bg-slate-100 group-hover:bg-[color:var(--brand-gold-soft)] flex items-center justify-center text-xl shrink-0 transition' }, props.icon),
                h('div', {}, [
                    h('p', { class: 'text-sm font-semibold text-gray-800' }, props.label),
                    h('p', { class: 'text-xs text-gray-400 mt-0.5' }, props.desc),
                ]),
            ],
        });
    },
});
</script>
