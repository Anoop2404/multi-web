<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Teams and squads</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        A team entry consumes one slot, whatever its size. Standbys travel with the
                        team but do not count toward it until they are substituted in.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span v-if="problems" class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700">{{ problems }} wrong size</span>
                    <select v-model="sahodayaId" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" @change="apply">
                        <option :value="null">All Sahodayas</option>
                        <option v-for="s in sahodayas" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
            </div>

            <p v-if="!teams.length" class="py-10 text-center text-sm text-slate-400">No team entries for this selection.</p>

            <ul v-else class="space-y-2">
                <li v-for="t in teams" :key="t.registration_id" class="rounded-xl border border-slate-200 p-3"
                    :class="{ 'border-rose-200 bg-rose-50/40': t.size_problem }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800">
                                {{ t.item_name || t.item_code }}
                                <span class="ml-1 font-mono text-xs text-slate-400">{{ t.item_code }}</span>
                            </p>
                            <!-- Sahodaya competes; the School the team came from is carried beside it. -->
                            <p class="text-xs text-slate-500">{{ t.sahodaya }} · {{ t.school }}</p>
                        </div>
                        <div class="text-right text-xs">
                            <p class="font-semibold" :class="t.size_problem ? 'text-rose-700' : 'text-slate-600'">
                                {{ t.competing }} competing<span v-if="t.standbys"> · {{ t.standbys }} standby</span>
                            </p>
                            <p v-if="t.min_size || t.max_size" class="text-slate-400">
                                allowed {{ t.min_size || 1 }}–{{ t.max_size || '∞' }}
                            </p>
                        </div>
                    </div>

                    <p v-if="t.size_problem" class="mt-1 text-xs font-medium text-rose-700">{{ t.size_problem }}</p>
                    <p v-if="!t.has_leader" class="mt-1 text-xs text-amber-700">No team leader named.</p>

                    <ul class="mt-2 divide-y divide-slate-100 border-t border-slate-100">
                        <li v-for="m in t.members" :key="m.id" class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                            <span class="text-sm" :class="m.withdrawn ? 'text-slate-400 line-through' : 'text-slate-700'">
                                {{ m.name }}
                                <span v-if="m.class_name" class="text-xs text-slate-400"> · {{ m.class_name }}</span>
                                <span v-if="m.chest_number" class="ml-1 font-mono text-xs text-slate-500">#{{ m.chest_number }}</span>
                                <span v-if="m.is_leader" class="ml-1 rounded bg-[color:var(--brand-navy)] px-1.5 py-0.5 text-[10px] font-bold text-white">leader</span>
                                <span v-if="m.is_standby" class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">standby</span>
                            </span>
                            <span v-if="!m.withdrawn" class="flex gap-2 text-xs">
                                <button v-if="!m.is_leader && !m.is_standby" type="button" class="font-semibold text-[color:var(--brand-blue)] hover:underline"
                                        @click="setLeader(t, m)">Make leader</button>
                                <button type="button" class="text-slate-500 hover:underline" @click="toggleStandby(t, m)">
                                    {{ m.is_standby ? 'Move into team' : 'Mark standby' }}
                                </button>
                                <button type="button" class="text-slate-500 hover:underline" @click="openSubstitute(t, m)">Substitute</button>
                            </span>
                        </li>
                    </ul>
                </li>
            </ul>
        </section>

        <section v-if="substituting" class="rounded-2xl border border-[color:var(--brand-blue)]/30 bg-white p-5">
            <h3 class="mb-1 text-sm font-bold text-slate-900">Substitute {{ substituting.member.name }}</h3>
            <p class="mb-3 text-xs text-slate-500">
                Takes effect only once a State officer approves it. The original is withdrawn rather
                than deleted, so the record shows who replaced whom.
            </p>
            <form @submit.prevent="submitSubstitution" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="lg:col-span-2">
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Substitute name</span>
                    <input v-model="subForm.substitute_name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                </label>
                <label>
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Class</span>
                    <input v-model="subForm.substitute_class" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="lg:col-span-4">
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Reason</span>
                    <input v-model="subForm.reason" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                           placeholder="Injury, illness, transfer…" required>
                </label>
                <div class="flex items-end gap-2 lg:col-span-4">
                    <button type="submit" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white">Request substitution</button>
                    <button type="button" class="text-xs text-slate-500 underline" @click="substituting = null">Cancel</button>
                </div>
            </form>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    teams: Array, filters: Object, problems: Number, actionUrls: Object, baseUrl: String,
});

const sahodayaId = ref(props.filters?.sahodaya_id ?? null);
const substituting = ref(null);
const subForm = useForm({ registration_id: null, original_participant_id: null, substitute_name: '', substitute_class: '', reason: '' });

function apply() {
    router.get(props.baseUrl, sahodayaId.value ? { sahodaya_id: sahodayaId.value } : {}, { preserveState: true, replace: true });
}
function setLeader(team, member) {
    router.post(props.actionUrls.leader, { registration_id: team.registration_id, participant_id: member.id }, { preserveScroll: true });
}
function toggleStandby(team, member) {
    router.post(props.actionUrls.standby, { registration_id: team.registration_id, participant_id: member.id, is_standby: !member.is_standby }, { preserveScroll: true });
}
function openSubstitute(team, member) {
    substituting.value = { team, member };
    subForm.reset();
    subForm.registration_id = team.registration_id;
    subForm.original_participant_id = member.id;
    subForm.substitute_class = member.class_name;
}
function submitSubstitution() {
    subForm.post(props.actionUrls.substitute, { preserveScroll: true, onSuccess: () => { substituting.value = null; } });
}
</script>
