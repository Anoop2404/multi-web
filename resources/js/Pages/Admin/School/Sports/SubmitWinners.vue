<template>
    <SchoolAdminLayout title="Submit sports winners" :school="school" :show-header-title="false">
        <PageHeader title="Submit sports winners" eyebrow="Sports meet"
                    description="Promote school-round winners to the linked Sahodaya sports event." />

        <p class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            Athletes who share the same rank (tie) appear together. Select all tied athletes if you want each to qualify,
            or pick one member of a team — the whole team is promoted.
        </p>

        <EmptyState v-if="!events.length" title="No school sports events ready"
                    description="Create a school sports event, link it to a Sahodaya parent event, enter ranks on marks, then return here."
                    icon="🏅" class="mb-6">
            <template #action>
                <a :href="schoolAdminHref(school.id, 'fest-programs')" class="btn-primary">School events</a>
            </template>
        </EmptyState>

        <div v-for="block in events" :key="block.school_event.id" class="card mb-6 space-y-4">
            <div>
                <h3 class="section-title">{{ block.school_event.title }}</h3>
                <p class="section-desc">Sahodaya event: {{ block.target_event.title }} ({{ block.target_event.status }})</p>
            </div>

            <form @submit.prevent="submit(block)">
                <div v-for="item in block.items" :key="item.item_id" class="border border-slate-100 rounded-xl overflow-hidden mb-4">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 font-medium text-sm">{{ item.item_title }}</div>
                    <div v-if="!item.winners.length" class="px-4 py-3 text-sm text-slate-500">
                        No ranked winners yet — enter position on the school mark entry page first (shared ranks allowed).
                    </div>
                    <div v-else class="divide-y divide-slate-100">
                        <div v-for="group in rankGroups(item.winners)" :key="group.position" class="px-4 py-3">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-bold text-slate-700">#{{ group.position }}</span>
                                    <span v-if="group.shared"
                                          class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-100">
                                        Shared rank
                                    </span>
                                </div>
                                <button v-if="group.selectable.length > 1" type="button"
                                        class="text-xs font-semibold text-indigo-700 hover:underline"
                                        @click="selectRank(block.school_event.id, group)">
                                    Select all at #{{ group.position }}
                                </button>
                            </div>
                            <ul class="space-y-2">
                                <li v-for="w in group.winners" :key="w.mark_id" class="flex items-center gap-3 text-sm">
                                    <input v-if="!w.already_submitted" type="checkbox" :value="w.mark_id"
                                           v-model="selections[block.school_event.id]" class="rounded" />
                                    <span v-else class="w-4 h-4 rounded bg-emerald-100 inline-block shrink-0" title="Submitted"></span>
                                    <span class="flex-1">{{ w.participant_name }}</span>
                                    <span v-if="w.already_submitted" class="text-xs text-emerald-700 font-medium">Submitted</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-primary" :disabled="!selections[block.school_event.id]?.length">
                    Submit selected winners
                </button>
            </form>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { schoolAdminHref } from '@/support/schoolProgramNav.js';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    school: Object,
    events: { type: Array, default: () => [] },
});

const selections = reactive(
    Object.fromEntries(props.events.map((e) => [e.school_event.id, []])),
);

function rankGroups(winners) {
    const map = new Map();
    for (const w of winners ?? []) {
        const pos = w.position ?? 0;
        if (!map.has(pos)) {
            map.set(pos, []);
        }
        map.get(pos).push(w);
    }

    return [...map.entries()]
        .sort(([a], [b]) => a - b)
        .map(([position, groupWinners]) => ({
            position,
            winners: groupWinners,
            shared: groupWinners.length > 1,
            selectable: groupWinners.filter((w) => !w.already_submitted),
        }));
}

function selectRank(eventId, group) {
    const current = new Set(selections[eventId] ?? []);
    for (const w of group.selectable) {
        current.add(w.mark_id);
    }
    selections[eventId] = [...current];
}

function submit(block) {
    const markIds = selections[block.school_event.id] ?? [];
    if (!markIds.length) return;

    router.post(schoolAdminHref(props.school.id, 'sports', 'submit-winners'), {
        school_event_id: block.school_event.id,
        mark_ids: markIds,
    }, { preserveScroll: true, onSuccess: () => { selections[block.school_event.id] = []; } });
}
</script>
