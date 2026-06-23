<template>
    <SahodayaAdminLayout :title="`${event.title} — Results`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="flex flex-wrap justify-between gap-3 mb-4">
            <form @submit.prevent="promote" class="flex flex-wrap gap-2 items-center">
                <select v-model="promoteForm.next_event_id" class="field" required>
                    <option value="">Promote winners to…</option>
                    <option v-for="e in nextEvents" :key="e.id" :value="e.id">{{ e.title }} ({{ e.status }})</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm">Promote qualifiers</button>
            </form>
            <button @click="publish" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium">Publish Results</button>
        </div>

        <div class="grid lg:grid-cols-2 gap-4 mb-4">
            <ol class="bg-white border rounded-xl divide-y">
                <li v-for="row in scoreboard" :key="row.school_id" class="p-4 flex justify-between">
                    <span><strong>#{{ row.rank }}</strong> {{ row.school_name }}</span>
                    <span class="font-mono">{{ row.total_points }} pts</span>
                </li>
                <li v-if="!scoreboard.length" class="p-4 text-gray-400 text-sm">No results yet</li>
            </ol>

            <div class="bg-white border rounded-xl p-4">
                <h3 class="font-semibold text-sm mb-2">Promoted participants</h3>
                <ul class="text-sm divide-y max-h-64 overflow-y-auto">
                    <li v-for="q in qualifications" :key="q.id" class="py-2">
                        {{ q.participant?.student?.name ?? 'Participant' }} — {{ q.item?.title }}
                        <span class="text-gray-400 text-xs">→ {{ q.next_level_event?.title }}</span>
                    </li>
                    <li v-if="!qualifications.length" class="py-2 text-gray-400">None yet</li>
                </ul>
            </div>
        </div>

        <div class="bg-white border rounded-xl p-4">
            <h3 class="font-semibold text-sm mb-3">Exports</h3>
            <div class="flex flex-wrap gap-2">
                <a v-for="link in exportLinks" :key="link.type" :href="link.href"
                   class="px-3 py-2 border rounded-lg text-sm hover:border-indigo-300">{{ link.label }} ↓</a>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, scoreboard: Array, qualifications: Array, nextEvents: Array,
});

const promoteForm = useForm({ next_event_id: '' });

const exportLinks = computed(() => {
    const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/export`;
    return [
        { type: 'registrations', label: 'Registrations', href: `${base}/registrations` },
        { type: 'results', label: 'Results', href: `${base}/results` },
        { type: 'attendance', label: 'Attendance', href: `${base}/attendance` },
        { type: 'fees', label: 'Fees', href: `${base}/fees` },
    ];
});

function publish() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results/publish`, {}, { preserveScroll: true });
}

function promote() {
    promoteForm.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results/promote`, {
        preserveScroll: true,
        onSuccess: () => promoteForm.reset(),
    });
}
</script>

<style scoped>
@reference "../../../../../css/app.css";
.field { @apply border border-gray-200 rounded-lg px-3 py-2 text-sm min-w-[200px]; }
</style>
