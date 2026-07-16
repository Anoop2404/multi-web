<template>
    <SahodayaEventsLayout :title="`${event.title} — Athletic Records`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Athletic Records`" eyebrow="Operations"
                    description="Track athletic records and record breaks." />
        <p class="text-sm text-gray-600 mb-4 bg-amber-50 border border-amber-100 rounded-xl px-4 py-3">
            <strong>Record tracking</strong> is {{ event.record_tracking_enabled ? 'ON' : 'OFF' }}.
            Enable it in <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/settings`" class="text-indigo-600 underline">Event Settings → Records</Link>.
            When a sports mark beats the standing record, a <strong>special prize</strong> is auto-awarded (label: {{ event.default_record_prize_label || 'Record Break Prize' }}).
        </p>

        <FestEventWorkflowStepper :sahodaya-id="sahodaya.id" :event-id="event.id"
                                  :event-type="event.event_type" :current-step="'operations'" class="mb-5" />

        <div class="grid lg:grid-cols-2 gap-4">
            <div class="space-y-4">
                <h3 class="font-semibold text-sm">Standing records</h3>
                <form @submit.prevent="saveRecord" class="bg-white border rounded-xl p-4 grid sm:grid-cols-2 gap-3 shadow-sm">
                    <select v-model="recordForm.item_id" class="field sm:col-span-2" required>
                        <option value="">Sports item</option>
                        <option v-for="item in sportsItems" :key="item.id" :value="item.id">{{ item.title }}</option>
                    </select>
                    <select v-model="recordForm.class_group" class="field">
                        <option v-for="(label, key) in classGroups" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <select v-model="recordForm.gender" class="field">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="open">Open</option>
                    </select>
                    <select v-model="recordForm.record_direction" class="field">
                        <option value="lower_better">Lower is better (track)</option>
                        <option value="higher_better">Higher is better (jump/throw)</option>
                    </select>
                    <input v-model="recordForm.record_value" class="field" placeholder="Value (e.g. 12.4 or 1:23.5)" required>
                    <input v-model="recordForm.record_unit" class="field" placeholder="Unit (s, m)">
                    <input v-model="recordForm.holder_name" class="field sm:col-span-2" placeholder="Holder name (optional)">
                    <button class="btn-primary text-xs w-full sm:col-span-2 py-2.5">Set / update record</button>
                </form>

                <ul class="bg-white border rounded-xl divide-y text-sm shadow-sm">
                    <li v-for="r in records" :key="r.id" class="p-3 flex justify-between items-start gap-2">
                        <div>
                            <p class="font-medium text-slate-800">{{ r.item?.title }}</p>
                            <p class="text-xs text-gray-500">{{ classGroups[r.class_group] }} · {{ r.gender }} · {{ r.record_value }} {{ r.record_unit }}</p>
                            <p v-if="r.holder_name" class="text-xs text-gray-400 mt-0.5">{{ r.holder_name }}</p>
                            <div class="flex items-center gap-2 mt-1.5">
                                <Link :href="marksUrl(r.item)" class="text-[11px] text-indigo-600 hover:underline">
                                    Go to mark entry →
                                </Link>
                            </div>
                        </div>
                        <button @click="removeRecord(r.id)" class="text-red-600 hover:text-red-700 text-xs shrink-0 font-medium">Remove</button>
                    </li>
                    <li v-if="!records.length" class="p-6 text-center text-gray-400">No records yet.</li>
                </ul>
            </div>

            <div>
                <h3 class="font-semibold text-sm mb-3">Record breaks &amp; prizes</h3>
                <ul class="bg-white border rounded-xl divide-y text-sm shadow-sm">
                    <li v-for="b in breaks" :key="b.id" class="p-3">
                        <p class="font-medium text-slate-800">{{ b.item?.title }}</p>
                        <p class="text-xs text-gray-600">
                            {{ b.participant?.student?.name }} · {{ b.previous_value }} → <strong>{{ b.new_value }}</strong> {{ b.record_unit }}
                        </p>
                        <p class="text-xs mt-1.5 flex flex-wrap gap-x-2 gap-y-1">
                            <span class="font-semibold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-100">{{ b.prize_label }}</span>
                            <button @click="togglePrize(b.id)" class="text-indigo-600 hover:underline">
                                {{ b.prize_awarded ? 'Prize given ✓' : 'Mark prize given' }}
                            </button>
                            <a v-if="b.certificate_uuid" :href="`/certificates/print/${b.certificate_uuid}`" target="_blank"
                               class="text-emerald-700 underline font-medium hover:text-emerald-800">Prize certificate</a>
                            <Link :href="marksUrl(b.item)" class="text-slate-500 hover:text-slate-600 hover:underline">
                                View marks
                            </Link>
                        </p>
                        <p class="text-[10px] text-gray-400 mt-1">{{ formatDateTime(b.broken_at) }}</p>
                    </li>
                    <li v-if="!breaks.length" class="p-6 text-center text-gray-400">No record breaks yet.</li>
                </ul>
            </div>
        </div>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import FestEventWorkflowStepper from '@/Components/sahodaya/FestEventWorkflowStepper.vue';
import { formatDateTime } from '@/support/calendarDates.js';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, records: Array, breaks: Array, classGroups: Object,
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const sportsItems = computed(() => (props.event.items || []).filter(i => i.category === 'sports' || i.sport_discipline));
const recordForm = useForm({
    item_id: '', class_group: 'open', gender: 'open',
    record_direction: 'lower_better', record_value: '', record_unit: 's',
    holder_name: '',
});

function saveRecord() {
    recordForm.post(`${base}/athletic-records`, { preserveScroll: true, onSuccess: () => recordForm.reset('record_value', 'holder_name') });
}
function removeRecord(id) {
    if (confirm('Remove this record?')) router.delete(`${base}/athletic-records/${id}`, { preserveScroll: true });
}
function togglePrize(id) {
    router.post(`${base}/record-breaks/${id}/toggle-prize`, {}, { preserveScroll: true });
}

function marksUrl(item) {
    if (!item?.id) return '';
    return `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks?item_id=${item.id}`;
}
</script>

