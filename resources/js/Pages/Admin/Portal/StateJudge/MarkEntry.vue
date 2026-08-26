<template>
    <PortalLayout role-label="State Judge Portal" :title="event.name" accent="amber" :nav-items="[]">
        <div class="space-y-4">
            <Link href="/portal/state-judge" class="text-sm text-indigo-600">← Back to dashboard</Link>

            <div v-if="errorMessage" class="rounded-lg bg-red-50 text-red-700 text-sm p-3">{{ errorMessage }}</div>

            <div v-for="registration in registrations" :key="registration.id" class="card space-y-2">
                <p class="text-xs font-mono text-slate-400">{{ registration.item_code }}</p>
                <div v-for="participant in registration.participants" :key="participant.id"
                     class="flex items-center justify-between gap-3 border-t pt-2 first:border-t-0 first:pt-0">
                    <div>
                        <p class="font-medium text-sm">{{ participant.student_name }}</p>
                        <p class="text-xs text-slate-500">{{ registration.school_name || registration.school_id }}</p>
                    </div>
                    <form class="flex items-center gap-2" @submit.prevent="submit(registration, participant)">
                        <input v-model="forms[participant.id].score" type="number" step="0.01" min="0" placeholder="Score"
                               class="field !py-1 !text-xs w-20">
                        <SearchableSelect v-model="forms[participant.id].grade" class="w-20"
                            :all-option="true" all-label="Grade"
                            :options="[{ value: 'A+', label: 'A+' }, { value: 'A', label: 'A' }, { value: 'B', label: 'B' }, { value: 'C', label: 'C' }]" />
                        <button type="submit" class="btn-primary !py-1 !px-3 text-xs" :disabled="submitting === participant.id">
                            {{ marks[participant.id] ? 'Update' : 'Save' }}
                        </button>
                    </form>
                </div>
            </div>
            <p v-if="registrations.length === 0" class="text-sm text-slate-400">No participants to mark for your assigned items yet.</p>
        </div>
    </PortalLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { reactive, ref } from 'vue';

const props = defineProps({
    event: Object,
    registrations: { type: Array, default: () => [] },
    marks: { type: Object, default: () => ({}) },
    assignedItems: { type: Array, default: () => [] },
});

const forms = reactive({});
for (const registration of props.registrations) {
    for (const participant of registration.participants || []) {
        const existing = props.marks?.[participant.id];
        forms[participant.id] = {
            score: existing?.score ?? '',
            grade: existing?.grade ?? '',
        };
    }
}

const submitting = ref(null);
const errorMessage = ref('');

function submit(registration, participant) {
    submitting.value = participant.id;
    errorMessage.value = '';

    router.post(`/portal/state-judge/events/${props.event.id}/marks`, {
        participant_id: participant.id,
        item_id: registration.item_id,
        item_code: registration.item_code,
        score: forms[participant.id].score || null,
        grade: forms[participant.id].grade || null,
    }, {
        preserveScroll: true,
        onFinish: () => { submitting.value = null; },
        onError: (errors) => { errorMessage.value = Object.values(errors)[0] || 'Could not save the mark.'; },
    });
}
</script>
