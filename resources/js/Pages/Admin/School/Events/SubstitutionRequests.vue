<template>
    <SchoolAdminLayout :title="`Substitution requests — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Substitution requests`" :eyebrow="programLabel"
                    :description="`Request performer changes for ${event.title}.`">
            <template #actions>
                <Link :href="`${programBase}/registration?event=${event.id}`" class="btn-secondary text-sm">← Registration</Link>
            </template>
        </PageHeader>

        <form class="card mb-6 max-w-2xl space-y-3" @submit.prevent="submit">
            <h3 class="font-semibold text-slate-900">New request</h3>
            <select v-model="form.registration_id" class="field text-sm" required @change="form.original_participant_id = ''; form.replacement_participant_id = ''">
                <option value="">Select registration</option>
                <option v-for="r in registrations" :key="r.id" :value="r.id">{{ r.item_title }}</option>
            </select>
            <select v-model="form.original_participant_id" class="field text-sm" required>
                <option value="">Performer to replace</option>
                <option v-for="p in performers" :key="p.id" :value="p.id">{{ p.name }} ({{ p.role }})</option>
            </select>
            <select v-model="form.replacement_participant_id" class="field text-sm">
                <option value="">Replacement standby (optional)</option>
                <option v-for="p in standbys" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <textarea v-model="form.reason" class="field text-sm" rows="3" placeholder="Reason for substitution" required />
            <button type="submit" class="btn-primary text-sm" :disabled="form.processing">Submit request</button>
        </form>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Original</th>
                        <th>Replacement</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in requests" :key="r.id">
                        <td>{{ r.registration?.item?.title }}</td>
                        <td>{{ r.original_participant?.student?.name || '—' }}</td>
                        <td>{{ r.replacement_participant?.student?.name || r.replacement_student?.name || '—' }}</td>
                        <td><span class="text-xs capitalize">{{ r.status }}</span></td>
                        <td class="text-xs">{{ r.created_at ? new Date(r.created_at).toLocaleString() : '—' }}</td>
                    </tr>
                    <tr v-if="!requests.length">
                        <td colspan="5" class="text-center text-slate-400 py-8">No substitution requests yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    requests: { type: Array, default: () => [] },
    registrations: { type: Array, default: () => [] },
});

const { programLabel, programBase } = useSchoolProgramContext(props);

const form = useForm({
    registration_id: '',
    original_participant_id: '',
    replacement_participant_id: '',
    replacement_student_id: '',
    reason: '',
});

const selectedRegistration = computed(() =>
    props.registrations.find((r) => String(r.id) === String(form.registration_id))
);

const performers = computed(() =>
    (selectedRegistration.value?.participants || []).filter((p) => p.role === 'performer')
);

const standbys = computed(() =>
    (selectedRegistration.value?.participants || []).filter((p) => p.role === 'standby')
);

function submit() {
    form.post(`${programBase}/events/${props.event.id}/substitution-requests`, { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>
