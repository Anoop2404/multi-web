<template>
    <SahodayaEventsLayout :title="`${event.title} — Item heads`" :sahodaya="sahodaya" :event="event" :show-header-title="false">
        <PageHeader :title="`${event.title} — Item heads`" eyebrow="Sports catalog"
                    description="Heads group sports items for ID cards, registration windows, and competition dates. Set dates here once — they apply to all linked items.">
            <template #actions>
                <Link :href="taxonomyMastersUrl" class="btn-secondary text-sm">Category masters →</Link>
                <button type="button" class="btn-secondary text-sm" @click="syncHeads">Sync from catalog</button>
            </template>
        </PageHeader>

        <form @submit.prevent="createHead" class="card mb-6 space-y-4">
            <p class="text-xs text-slate-500">
                Each head runs like its own independent event — set its fees and policy here, at creation.
            </p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">
                <FormField label="Head name">
                    <input v-model="form.name" class="field" required placeholder="e.g. Chess">
                </FormField>
                <FormField label="Sport discipline">
                    <select v-model="form.sport_discipline" class="field">
                        <option value="">Any</option>
                        <option v-for="(label, key) in disciplines" :key="key" :value="key">{{ label }}</option>
                    </select>
                </FormField>
                <label class="flex items-center gap-2 text-sm pb-2">
                    <input type="checkbox" v-model="form.is_team_heading"> ID card heading
                </label>
            </div>
            <div class="border-t border-slate-100 pt-4 space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fees &amp; policy for this head</p>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <FormField label="School fee (₹)" hint="Once per school">
                        <input v-model.number="form.school_registration_fee" type="number" min="0" class="field" placeholder="0">
                    </FormField>
                    <FormField label="Student fee (₹)" hint="Per student under this head">
                        <input v-model.number="form.student_registration_fee" type="number" min="0" class="field" placeholder="0">
                    </FormField>
                    <FormField label="Team fee (₹)" hint="Per team entry">
                        <input v-model.number="form.team_registration_fee" type="number" min="0" class="field" placeholder="0">
                    </FormField>
                    <FormField label="Free quota (items/student)" hint="0 = no free items">
                        <input v-model.number="form.included_items_per_student" type="number" min="0" class="field" placeholder="0">
                    </FormField>
                    <FormField label="Free quota (teams/student)" hint="0 = no free teams">
                        <input v-model.number="form.included_teams" type="number" min="0" class="field" placeholder="0">
                    </FormField>
                    <FormField label="Max participants" hint="Leave blank for no cap">
                        <input v-model.number="form.max_participants" type="number" min="0" class="field" placeholder="—">
                    </FormField>
                    <FormField label="Max teams" hint="Leave blank for no cap">
                        <input v-model.number="form.max_teams" type="number" min="0" class="field" placeholder="—">
                    </FormField>
                    <FormField label="Students eligible">
                        <select v-model="form.verification_policy" class="field">
                            <option value="all_students">All students</option>
                            <option value="verified_only">Verified students only</option>
                        </select>
                    </FormField>
                    <FormField label="Approval">
                        <select v-model="form.approval_policy" class="field">
                            <option value="auto">Auto (on full payment)</option>
                            <option value="manual">Manual review</option>
                        </select>
                    </FormField>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary" :disabled="form.processing">Add head</button>
            </div>
        </form>

        <div class="space-y-4">
            <div v-for="head in headRows" :key="head.id" class="card space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <Link :href="headOpsUrl(head.id)" class="font-semibold text-slate-900 hover:text-indigo-700">
                            {{ head.name }}
                        </Link>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ head.items?.length ?? 0 }} linked item(s)
                            · <Link :href="headOpsUrl(head.id)" class="text-indigo-600 hover:underline">Open item listing →</Link>
                        </p>
                    </div>
                    <span v-if="head.is_team_heading" class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">ID card heading</span>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <FormField label="Registration opens">
                        <input v-model="head.reg_start" type="date" class="field text-sm">
                    </FormField>
                    <FormField label="Registration closes">
                        <input v-model="head.reg_end" type="date" class="field text-sm">
                    </FormField>
                    <FormField label="Competition start">
                        <input v-model="head.competition_start" type="date" class="field text-sm">
                    </FormField>
                    <FormField label="Competition end">
                        <input v-model="head.competition_end" type="date" class="field text-sm">
                    </FormField>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-xs text-slate-600">
                        <input v-model="head.apply_to_items" type="checkbox" class="rounded border-slate-300">
                        Apply dates to all items under this head
                    </label>
                    <button type="button" class="btn-secondary text-xs py-1.5 px-3"
                            :disabled="savingHeadId === head.id"
                            @click="saveHeadWindow(head)">
                        {{ savingHeadId === head.id ? 'Saving…' : 'Save head window' }}
                    </button>
                </div>

                <ul class="text-sm text-slate-600 space-y-1 border-t border-slate-100 pt-3">
                    <li v-for="item in head.items" :key="item.id">
                        <Link :href="itemOpsUrl(head.id, item.id)" class="text-indigo-700 hover:underline">{{ item.title }}</Link>
                    </li>
                    <li v-if="!head.items?.length" class="text-slate-400 italic">No items linked — assign head in Settings → Registration or catalog assign.</li>
                </ul>
            </div>
            <EmptyState v-if="!headRows.length" title="No item heads" description="Sync from catalog or add a head above." icon="📂" />
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    heads: { type: Array, default: () => [] },
    disciplines: { type: Object, default: () => ({}) },
    taxonomyMastersUrl: String,
});

const form = useForm({
    name: '',
    sport_discipline: '',
    is_team_heading: true,
    school_registration_fee: '',
    student_registration_fee: '',
    team_registration_fee: '',
    included_items_per_student: 0,
    included_teams: 0,
    verification_policy: 'all_students',
    approval_policy: 'auto',
    max_participants: '',
    max_teams: '',
});
const savingHeadId = ref(null);

function toDateInput(value) {
    if (!value) return '';
    return String(value).slice(0, 10);
}

function mapHead(head) {
    return {
        ...head,
        reg_start: toDateInput(head.reg_start),
        reg_end: toDateInput(head.reg_end),
        competition_start: toDateInput(head.competition_start),
        competition_end: toDateInput(head.competition_end),
        apply_to_items: true,
    };
}

const headRows = ref((props.heads ?? []).map(mapHead));

const competitionBase = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/competition`;

function headOpsUrl(headId) {
    return `${competitionBase}?head_id=${headId}`;
}

function itemOpsUrl(headId, itemId) {
    return `${competitionBase}?head_id=${headId}&item_id=${itemId}`;
}

function createHead() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/item-heads`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function syncHeads() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/item-heads/sync`, {}, { preserveScroll: true });
}

function saveHeadWindow(head) {
    savingHeadId.value = head.id;
    router.patch(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/item-heads/${head.id}/windows`, {
        reg_start: head.reg_start || null,
        reg_end: head.reg_end || null,
        competition_start: head.competition_start || null,
        competition_end: head.competition_end || null,
        apply_to_items: head.apply_to_items,
    }, {
        preserveScroll: true,
        onFinish: () => { savingHeadId.value = null; },
    });
}
</script>
