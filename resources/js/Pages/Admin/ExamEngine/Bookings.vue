<template>
    <AdminLayout title="Online exam bookings">
        <PageHeader
            title="Online exam bookings"
            eyebrow="Exam engine capacity"
            :description="`Every Sahodaya's online exams on the exam engine, by day. Platform capacity is ${fmt(capacity)} students live at once; slots above ${fmt(scaleOutThreshold)} need extra engine nodes before the exam.`"
        >
            <template #actions>
                <Link :href="rangeUrl(range.prev)" class="btn-secondary text-sm" preserve-scroll>← Earlier</Link>
                <Link :href="rangeUrl(null)" class="btn-secondary text-sm">Today</Link>
                <Link :href="rangeUrl(range.next)" class="btn-secondary text-sm" preserve-scroll>Later →</Link>
            </template>
        </PageHeader>

        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold tabular-nums">{{ totals.active }}</p>
                <p class="text-xs text-slate-500">Active bookings</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold tabular-nums">{{ fmt(totals.students) }}</p>
                <p class="text-xs text-slate-500">Students booked</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold tabular-nums" :class="totals.overbooked_slots ? 'text-red-700' : 'text-slate-400'">{{ totals.overbooked_slots }}</p>
                <p class="text-xs text-slate-500">Overbooked slots</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold tabular-nums" :class="totals.scale_out_slots ? 'text-amber-700' : 'text-slate-400'">{{ totals.scale_out_slots }}</p>
                <p class="text-xs text-slate-500">Slots needing scale-out</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-sm font-semibold text-slate-700">{{ range.from }} → {{ range.to }}</p>
                <label class="mt-1 inline-flex items-center gap-1.5 text-xs text-slate-500">
                    <input v-model="showInactive" type="checkbox"> Show closed / cancelled
                </label>
            </div>
        </div>

        <EmptyState
            v-if="!days.length"
            title="No online exams booked in this period"
            description="Bookings appear when a Sahodaya schedules an online exam on the exam engine."
            icon="🗓️"
        />

        <div v-for="day in days" :key="day.date" class="mb-6">
            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-base font-semibold text-slate-900">{{ day.label }}</h3>
                <p class="text-xs text-slate-500">
                    Peak {{ fmt(day.peak) }} of {{ fmt(capacity) }} students
                    <span v-if="day.flagged" class="ml-1 font-semibold text-amber-700">· needs attention</span>
                </p>
            </div>

            <div v-if="day.slots.length" class="mb-3 space-y-2">
                <div
                    v-for="slot in day.slots"
                    :key="slot.starts_at"
                    class="rounded-xl border px-4 py-3"
                    :class="slot.overbooked ? 'border-red-300 bg-red-50' : (slot.needs_scale_out ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white')"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ slot.window }}</p>
                            <p class="text-xs text-slate-500">{{ slot.exams.map((e) => `${e.title} (${fmt(e.expected_count)})`).join(' · ') }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="font-semibold tabular-nums text-slate-800">{{ fmt(slot.students) }} / {{ fmt(slot.capacity) }} live at once</span>
                            <span v-if="slot.overbooked" class="rounded-full bg-red-600 px-2 py-0.5 font-semibold text-white">Overbooked</span>
                            <span v-if="slot.needs_scale_out" class="rounded-full bg-amber-500 px-2 py-0.5 font-semibold text-white">Needs scale-out · {{ slot.nodes }} nodes</span>
                            <span v-if="!slot.overbooked && !slot.needs_scale_out" class="rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800">Within baseline</span>
                        </div>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full" :class="slot.overbooked ? 'bg-red-600' : (slot.needs_scale_out ? 'bg-amber-500' : 'bg-emerald-500')" :style="{ width: `${Math.min(100, slot.utilisation ?? 0)}%` }" />
                    </div>
                </div>
            </div>

            <div class="card card--flush overflow-x-auto">
                <table class="data-table min-w-[760px] text-sm">
                    <thead>
                        <tr>
                            <th>Live window</th>
                            <th>Sahodaya</th>
                            <th>Exam</th>
                            <th class="text-right">Expected</th>
                            <th>Entry</th>
                            <th>Status</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="booking in visibleBookings(day)" :key="booking.id" :class="booking.active ? '' : 'opacity-60'">
                            <td class="whitespace-nowrap font-medium">{{ booking.window }}</td>
                            <td>{{ booking.sahodaya }}</td>
                            <td>{{ booking.title }}</td>
                            <td class="text-right tabular-nums">{{ fmt(booking.expected_count) }}</td>
                            <td class="text-xs">{{ entryLabel(booking.entry_mode) }}</td>
                            <td><span class="rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="statusClass(booking.status)">{{ booking.status }}</span></td>
                            <td class="text-right"><a :href="booking.monitor_url" class="text-xs font-semibold text-indigo-700 hover:underline">Live monitor</a></td>
                        </tr>
                        <tr v-if="!visibleBookings(day).length">
                            <td colspan="7" class="py-4 text-center text-xs text-slate-500">Only closed or cancelled bookings on this day.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    range: { type: Object, required: true },
    capacity: { type: Number, default: 0 },
    scaleOutThreshold: { type: Number, default: 0 },
    days: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
});

const showInactive = ref(false);

function fmt(n) {
    return Number(n ?? 0).toLocaleString('en-IN');
}

function rangeUrl(from) {
    const params = new URLSearchParams();
    if (from) params.set('from', from);
    if (props.range.days && props.range.days !== 14) params.set('days', String(props.range.days));
    const qs = params.toString();
    return `/admin/exam-engine/bookings${qs ? `?${qs}` : ''}`;
}

function visibleBookings(day) {
    return showInactive.value ? day.bookings : day.bookings.filter((b) => b.active);
}

function entryLabel(mode) {
    return { either: 'Password or passcode', portal_password: 'Portal password only', exam_passcode: 'Passcode only' }[mode] ?? mode;
}

function statusClass(status) {
    return {
        booked: 'bg-indigo-100 text-indigo-800',
        armed: 'bg-emerald-100 text-emerald-800',
        closed: 'bg-slate-200 text-slate-700',
        cancelled: 'bg-slate-100 text-slate-500',
    }[status] ?? 'bg-slate-100 text-slate-600';
}
</script>
