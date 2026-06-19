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
                       class="px-4 py-2 bg-white/15 hover:bg-white/25 border border-white/20 rounded-xl text-sm font-semibold transition flex items-center gap-1.5">
                        Preview Site ↗
                    </a>
                    <Link v-if="websiteEnabled" :href="`/sahodaya-admin/${sahodaya.id}/public-content`"
                          class="px-4 py-2 bg-[#fbbf24] hover:bg-[#f59e0b] text-[#041525] rounded-xl text-sm font-semibold transition">
                        Edit Website →
                    </Link>
                </div>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <template v-if="!websiteEnabled">
                    <StatCard :value="stats.approved_schools ?? 0" label="Approved Members" color="blue" icon="🏫" />
                    <StatCard :value="stats.pending_schools ?? 0" label="Pending Schools" color="amber" icon="⏳" />
                    <StatCard :value="stats.total_students ?? 0" label="Active Students" color="navy" icon="👨‍🎓"
                              :hint="'From approved members only'" />
                    <StatCard :value="`₹${Number(stats.pending_amount || 0).toLocaleString('en-IN')}`" label="Pending Approval Fees" color="amber" icon="💳"
                              :hint="`${stats.pending_payments ?? 0} awaiting verification`" />
                    <StatCard :value="`₹${Number(stats.approved_amount || 0).toLocaleString('en-IN')}`" label="Approved Fees" color="green" icon="✅" />
                    <StatCard :value="`₹${Number(stats.payment_due_amount || 0).toLocaleString('en-IN')}`" label="Payment Not Done" color="navy" icon="🧾"
                              :hint="`${stats.payment_due ?? 0} schools`" />
                </template>
                <template v-else>
                    <StatCard :value="stats.approved_schools ?? 0" label="Approved Members" color="blue" icon="🏫" />
                    <StatCard :value="stats.pending_schools ?? 0" label="Pending Schools" color="amber" icon="⏳" />
                    <StatCard :value="stats.office_bearers"  label="Office Bearers"   color="navy" icon="👥" />
                    <StatCard :value="stats.circulars"        label="Circulars"        color="indigo" icon="📄" />
                    <StatCard :value="stats.kalotsav_events"  label="Kalotsav Events" color="amber"  icon="🏆" />
                </template>
            </div>

            <!-- Attention required -->
            <div v-if="pendingSchoolsCount > 0 || pendingPaymentsCount > 0 || (stats.payment_due ?? 0) > 0" class="grid sm:grid-cols-2 gap-4">
                <ActionBanner v-if="pendingSchoolsCount > 0"
                              :href="`/sahodaya-admin/${sahodaya.id}/membership/reports?tab=schools`"
                              :count="pendingSchoolsCount"
                              label="schools awaiting membership approval"
                              color="amber" icon="⏳" />
                <ActionBanner v-if="(stats.payment_due ?? 0) > 0"
                              :href="`/sahodaya-admin/${sahodaya.id}/membership/payments?status=payment-due`"
                              :count="stats.payment_due"
                              label="schools registered but payment not done"
                              color="amber" icon="🧾" />
                <ActionBanner v-if="pendingPaymentsCount > 0"
                              :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`"
                              :count="pendingPaymentsCount"
                              label="payments pending verification"
                              color="green" icon="💳" />
            </div>

            <div v-if="websiteEnabled" class="grid lg:grid-cols-2 gap-6">
                <!-- Active Kalotsav -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-900 flex items-center gap-2">
                            <span class="text-lg">🏆</span> Active Event
                        </h3>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/kalotsav`"
                              class="text-xs font-semibold text-[#0f3d7a] hover:text-[#041525] transition">View all →</Link>
                    </div>
                    <div v-if="activeKalotsav">
                        <p class="text-base font-bold text-gray-900">{{ activeKalotsav.name }}</p>
                        <div class="flex flex-wrap gap-3 mt-2 text-xs text-gray-500">
                            <span class="flex items-center gap-1">📅 {{ activeKalotsav.event_date ? new Date(activeKalotsav.event_date).toLocaleDateString('en-IN', {day:'numeric',month:'short',year:'numeric'}) : 'Date TBD' }}</span>
                            <span v-if="activeKalotsav.venue" class="flex items-center gap-1">📍 {{ activeKalotsav.venue }}</span>
                            <span class="flex items-center gap-1">📚 {{ activeKalotsav.academic_year }}</span>
                        </div>
                        <div class="flex gap-2 mt-4">
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/kalotsav/${activeKalotsav.id}`"
                                  class="px-4 py-2 bg-[#0f3d7a] text-white text-xs font-bold rounded-xl hover:bg-[#1a4f8c] transition">
                                Manage Event →
                            </Link>
                        </div>
                    </div>
                    <div v-else class="text-center py-6">
                        <p class="text-gray-400 text-sm mb-3">No active Kalotsav event</p>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/kalotsav`"
                              class="inline-flex px-4 py-2 bg-[#eff6ff] text-[#0f3d7a] text-xs font-semibold rounded-xl hover:bg-[#dbeafe] transition">
                            Create Event
                        </Link>
                    </div>
                </div>

                <!-- Recent Circulars -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-900 flex items-center gap-2">
                            <span class="text-lg">📄</span> Recent Circulars
                        </h3>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/circulars`"
                              class="text-xs font-semibold text-[#0f3d7a] hover:text-[#041525] transition">View all →</Link>
                    </div>
                    <div v-if="recentCirculars.length" class="divide-y divide-gray-50">
                        <div v-for="c in recentCirculars" :key="c.id"
                             class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="w-8 h-8 rounded-lg bg-[#eff6ff] flex items-center justify-center text-sm shrink-0">📄</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate">{{ c.title }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[11px] bg-amber-50 text-amber-800 px-1.5 py-0.5 rounded font-medium">{{ c.category || 'General' }}</span>
                                    <span class="text-xs text-gray-400">{{ c.issued_date ? new Date(c.issued_date).toLocaleDateString('en-IN', {day:'2-digit',month:'short'}) : '' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-6">
                        <p class="text-gray-400 text-sm mb-3">No circulars uploaded yet</p>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/circulars`"
                              class="inline-flex px-4 py-2 bg-[#eff6ff] text-[#0f3d7a] text-xs font-semibold rounded-xl hover:bg-[#dbeafe] transition">
                            Upload Circular
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h3 class="font-bold text-gray-900 mb-4">Quick Actions</h3>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <QuickAction v-if="websiteEnabled" :href="`/sahodaya-admin/${sahodaya.id}/public-content`"
                                 icon="✏️" label="Edit Website" desc="Content, announcements, links" />
                    <QuickAction v-if="!websiteEnabled" :href="`/sahodaya-admin/${sahodaya.id}/public-content`"
                                 icon="✏️" label="Portal Content" desc="Tagline, motto, contact details" />
                    <QuickAction v-if="websiteEnabled" :href="`/sahodaya-admin/${sahodaya.id}/circulars`"
                                 icon="📤" label="Upload Circular" desc="Notices and documents" />
                    <QuickAction v-if="websiteEnabled" :href="`/sahodaya-admin/${sahodaya.id}/office-bearers`"
                                 icon="👥" label="Office Bearers" desc="Update leadership details" />
                    <QuickAction :href="`/sahodaya-admin/${sahodaya.id}/schools`"
                                 icon="🏫" label="Member Schools" desc="Approved member schools" />
                    <QuickAction :href="`/sahodaya-admin/${sahodaya.id}/membership/submissions`"
                                 icon="👨‍🎓" label="Student Counts" desc="View totals by school" />
                    <QuickAction :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`"
                                 icon="💳" label="Verify Payments" desc="Membership fee proofs" />
                    <QuickAction :href="`/sahodaya-admin/${sahodaya.id}/membership/reports`"
                                 icon="📊" label="Reports" desc="Summary & CSV exports" />
                    <QuickAction :href="`/sahodaya-admin/${sahodaya.id}/membership/settings`"
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

defineProps({
    sahodaya:                Object,
    publicUrl:               { type: String, default: null },
    pendingSchoolsCount:     { type: Number, default: 0 },
    pendingSubmissionsCount: { type: Number, default: 0 },
    pendingPaymentsCount:    { type: Number, default: 0 },
    stats:                   { type: Object, default: () => ({}) },
    recentCirculars:         { type: Array,  default: () => [] },
    activeKalotsav:          { type: Object, default: null },
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
            class: 'flex items-start gap-3 p-4 rounded-xl border border-gray-100 hover:border-[#bfdbfe] hover:bg-[#eff6ff]/60 transition group',
        }, {
            default: () => [
                h('div', { class: 'w-10 h-10 rounded-xl bg-[#eff6ff] group-hover:bg-[#dbeafe] flex items-center justify-center text-xl shrink-0 transition' }, props.icon),
                h('div', {}, [
                    h('p', { class: 'text-sm font-semibold text-gray-800' }, props.label),
                    h('p', { class: 'text-xs text-gray-400 mt-0.5' }, props.desc),
                ]),
            ],
        });
    },
});
</script>
