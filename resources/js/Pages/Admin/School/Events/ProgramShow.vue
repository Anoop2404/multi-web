<template>
    <SchoolAdminLayout :title="event.title" :school="school" :show-header-title="false">
        <PageHeader :title="event.title" eyebrow="School events"
            description="School-level fest event details and item catalog." />


        <div class="max-w-3xl space-y-4">
            <div class="notice-banner notice-banner--info text-sm">
                <p class="font-semibold text-indigo-900">School-level event</p>
                <p class="text-indigo-700 mt-1">
                    Items include inherited state + Sahodaya catalog plus your school custom items.
                </p>
            </div>

            <div class="card card--muted text-sm space-y-2">
                <p><span class="text-gray-500">Type:</span> {{ eventTypes[event.event_type] ?? event.event_type }}</p>
                <p><span class="text-gray-500">Status:</span> {{ event.status }}</p>
                <p><span class="text-gray-500">Total items:</span> {{ event.items?.length ?? 0 }}</p>
                <p>
                    <span class="text-gray-500">Linked Sahodaya event:</span>
                    <span v-if="event.parent_event_id" class="text-green-700 font-medium">Yes</span>
                    <span v-else class="text-amber-700">Not linked</span>
                </p>
            </div>

            <form v-if="parentEvents?.length && !event.parent_event_id" @submit.prevent="linkParent" class="card space-y-2">
                <h3 class="font-semibold">Link to Sahodaya parent event</h3>
                <p class="text-xs text-slate-500">Required for promoting school-round winners to the cluster event.</p>
                <select v-model="parentEventId" class="field" required>
                    <option value="">Select Sahodaya event…</option>
                    <option v-for="p in parentEvents" :key="p.id" :value="p.id">{{ p.title }} ({{ p.level_round }})</option>
                </select>
                <button class="btn-primary text-sm">Link parent event</button>
            </form>

            <div class="card">
                <h3 class="section-title">Participation policy (school round)</h3>
                <form @submit.prevent="savePolicy" class="grid sm:grid-cols-3 gap-2 mb-4">
                    <select v-model="policyForm.preset_key" class="field sm:col-span-3">
                        <option value="">Custom limits</option>
                        <option v-for="(label, key) in participationPresets" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <input v-model.number="policyForm.max_onstage_per_student" type="number" min="0" class="field" placeholder="On-stage / student">
                    <input v-model.number="policyForm.max_offstage_per_student" type="number" min="0" class="field" placeholder="Off-stage / student">
                    <input v-model.number="policyForm.max_group_per_student" type="number" min="0" class="field" placeholder="Group / student">
                    <button class="btn-primary sm:col-span-3">Save policy</button>
                </form>
            </div>

            <div class="card">
                <h3 class="section-title">Add school custom item</h3>
                <form @submit.prevent="addItem" class="grid sm:grid-cols-2 gap-2">
                    <input v-model="itemForm.title" class="field sm:col-span-2" placeholder="Item name" required>
                    <select v-model="itemForm.class_group" class="field">
                        <option value="">Class category</option>
                        <option v-for="(label, key) in taxonomy.class_group" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <select v-model="itemForm.participant_type" class="field">
                        <option value="individual">Individual</option>
                        <option value="group">Group</option>
                        <option value="team">Team</option>
                    </select>
                    <button class="btn-primary text-sm sm:col-span-2">Add school item</button>
                </form>
            </div>

            <div v-for="(levelItems, level) in itemsByLevel" :key="level" class="card">
                <h4 class="text-sm font-semibold text-gray-700 mb-2">{{ ownerLevelLabels[level] ?? level }}</h4>
                <ul v-if="levelItems.length" class="divide-y text-sm">
                    <li v-for="item in levelItems" :key="item.id" class="py-2 flex justify-between gap-2">
                        <span>{{ item.title }}</span>
                        <button v-if="level === 'school'" type="button" @click="removeItem(item.id)" class="text-red-600 text-xs">Remove</button>
                    </li>
                </ul>
                <p v-else class="text-sm text-gray-400">None</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <Link :href="`/school-admin/${school.id}/fest-programs/${event.id}/marks`" class="btn-primary text-sm">
                    Enter marks
                </Link>
            </div>

            <Link :href="`/school-admin/${school.id}/fest-programs`" class="text-indigo-600 text-sm">← School events</Link>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({
    school: Object,
    event: Object,
    parentEvents: { type: Array, default: () => [] },
    eventTypes: Object,
    itemsByLevel: Object,
    ownerLevelLabels: Object,
    taxonomy: Object,
    participationPolicy: Object,
    participationPresets: Object,
});

const policyForm = useForm({
    preset_key: props.participationPolicy?.preset_key ?? 'cksc_school_kalakriti',
    max_onstage_per_student: props.participationPolicy?.max_onstage_per_student ?? '',
    max_offstage_per_student: props.participationPolicy?.max_offstage_per_student ?? '',
    max_group_per_student: props.participationPolicy?.max_group_per_student ?? '',
});

function savePolicy() {
    policyForm.post(`/school-admin/${props.school.id}/fest-programs/${props.event.id}/participation-policy`, { preserveScroll: true });
}

const itemForm = useForm({
    title: '',
    class_group: '',
    participant_type: 'individual',
});

function addItem() {
    itemForm.post(`/school-admin/${props.school.id}/fest-programs/${props.event.id}/items`, {
        preserveScroll: true,
        onSuccess: () => itemForm.reset({ participant_type: 'individual' }),
    });
}

function removeItem(id) {
    router.delete(`/school-admin/${props.school.id}/fest-programs/${props.event.id}/items/${id}`, { preserveScroll: true });
}

const parentEventId = ref('');

function linkParent() {
    router.post(`/school-admin/${props.school.id}/fest-programs/${props.event.id}/link-parent`, {
        parent_event_id: parentEventId.value,
    }, { preserveScroll: true });
}
</script>

