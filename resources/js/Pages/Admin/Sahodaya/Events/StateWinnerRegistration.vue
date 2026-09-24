<template>
    <AdminLayout :title="`Register winners for State — ${program.title}`">
        <div class="max-w-6xl space-y-4">
            <div class="card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">Register our winners for State</h3>
                        <p class="text-xs text-gray-500 mt-0.5 max-w-2xl">
                            One row per State item, with our own winner sheet under it. Pick who goes;
                            nothing reaches State until you register the sheet. A winner who cannot
                            travel is recorded as declined, so the sheet shows why the next rank goes.
                        </p>
                    </div>
                    <span v-if="batch.status === 'certified'" class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                        Registered with State
                    </span>
                </div>

                <div class="mt-3 flex flex-wrap items-end gap-2">
                    <label class="text-xs">
                        <span class="block text-gray-500 mb-0.5">Category</span>
                        <select :value="filters.category || ''" class="field !py-1.5 !text-xs" @change="filter('category', $event.target.value)">
                            <option value="">All</option>
                            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </label>
                    <label class="text-xs">
                        <span class="block text-gray-500 mb-0.5">Class category</span>
                        <select :value="filters.class_group || ''" class="field !py-1.5 !text-xs" @change="filter('class_group', $event.target.value)">
                            <option value="">All</option>
                            <option v-for="g in classGroups" :key="g" :value="g">{{ g }}</option>
                        </select>
                    </label>
                    <label class="flex items-center gap-1.5 text-xs text-gray-600">
                        <input type="checkbox" :checked="!!filters.only_incomplete" @change="filter('only_incomplete', $event.target.checked ? 1 : '')">
                        Only items with seats left
                    </label>
                    <div class="ml-auto flex gap-2">
                        <button type="button" class="btn-secondary text-xs" :disabled="isCertified" @click="autoFill">Fill from the top</button>
                        <button type="button" class="btn-primary text-xs" :disabled="isCertified || !readiness.can_register" @click="register">
                            Register {{ readiness.chosen }} with State
                        </button>
                    </div>
                </div>
            </div>

            <!-- What stops registration, said plainly and per item. -->
            <div v-if="readiness.blocking.length" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                <p class="text-xs font-semibold text-red-800">Cannot register yet</p>
                <ul class="mt-1 space-y-0.5 text-xs text-red-700">
                    <li v-for="(b, i) in readiness.blocking" :key="i">{{ b }}</li>
                </ul>
            </div>
            <div v-else-if="readiness.warnings.length" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-xs font-semibold text-amber-900">{{ readiness.warnings.length }} item(s) have seats unfilled</p>
                <p class="text-[11px] text-amber-800 mt-0.5">
                    You can still register — an item we have nobody for simply sends nobody.
                </p>
                <ul class="mt-1 space-y-0.5 text-xs text-amber-800">
                    <li v-for="(w, i) in readiness.warnings.slice(0, 8)" :key="i">{{ w }}</li>
                </ul>
            </div>

            <p v-if="!sheet.length" class="card text-sm text-gray-400">
                No State items have results from this event yet.
            </p>

            <section v-for="row in sheet" :key="row.item_id" class="card">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h4 class="font-semibold text-sm text-gray-800">
                            <span class="font-mono text-xs text-gray-400">{{ row.item_code }}</span>
                            {{ row.title }}
                        </h4>
                        <p class="text-[11px] text-gray-500">
                            <span v-if="row.category">{{ row.category }} · </span>
                            <span v-if="row.class_group">{{ row.class_group }} · </span>
                            {{ row.participant_type || 'individual' }}
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold"
                          :class="row.is_over_quota ? 'bg-red-100 text-red-700'
                              : row.is_complete ? 'bg-emerald-100 text-emerald-700'
                              : 'bg-amber-100 text-amber-800'">
                        {{ row.chosen_count }} of {{ row.quota || '—' }} seat{{ row.quota === 1 ? '' : 's' }}
                        <span v-if="row.seats_left"> · {{ row.seats_left }} left</span>
                    </span>
                </div>

                <p v-if="row.unresolved_ties.length" class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-[11px] text-amber-900">
                    Position {{ row.unresolved_ties.join(', ') }} is tied and more are tied than there are
                    seats. Choose who goes — the tie is ours to break, not the State's.
                </p>

                <!-- The winner sheet itself. -->
                <table class="mt-3 w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] font-bold uppercase tracking-wider text-gray-400 border-b">
                            <th class="py-1.5 w-12">Pos</th>
                            <th class="py-1.5">Participant</th>
                            <th class="py-1.5">School</th>
                            <th class="py-1.5 w-16">Grade</th>
                            <th class="py-1.5 w-16 text-right">Score</th>
                            <th class="py-1.5 w-44"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in row.candidates" :key="c.mark_id"
                            class="border-b last:border-0"
                            :class="{ 'bg-emerald-50/60': c.is_chosen, 'opacity-60': c.is_declined }">
                            <td class="py-1.5">
                                {{ c.source_position }}
                                <span v-if="row.tied_positions.includes(c.source_position)"
                                      class="ml-0.5 text-[10px] font-semibold text-amber-600" title="Tied position">tie</span>
                            </td>
                            <td class="py-1.5">
                                {{ c.student_name }}
                                <span v-if="c.class_name" class="text-[11px] text-gray-400"> · {{ c.class_name }}</span>
                            </td>
                            <td class="py-1.5 text-gray-600">{{ c.school_name || '—' }}</td>
                            <td class="py-1.5">{{ c.grade || '—' }}</td>
                            <td class="py-1.5 text-right">{{ c.score ?? '—' }}</td>
                            <td class="py-1.5 text-right whitespace-nowrap">
                                <span v-if="c.is_chosen" class="text-[11px] font-semibold text-emerald-700">Going</span>
                                <span v-else-if="c.is_declined" class="text-[11px] text-gray-500">Declined</span>
                                <template v-else-if="!isCertified">
                                    <button type="button" class="text-[11px] font-semibold link-brand" @click="choose(row, c, 'primary')">Send</button>
                                    <button type="button" class="ml-2 text-[11px] text-gray-500 hover:underline" @click="choose(row, c, 'reserve')">Reserve</button>
                                    <button type="button" class="ml-2 text-[11px] text-gray-400 hover:text-red-600" @click="decline(c)">Can't go</button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="row.chosen.length || row.reserves.length || row.declined.length" class="mt-2 space-y-1 border-t pt-2">
                    <p v-for="s in row.chosen" :key="`c${s.id}`" class="text-[11px] text-emerald-800">
                        <strong>Going:</strong> {{ s.student_name }} ({{ s.school_name }}) — position {{ s.source_position }}
                        <button v-if="!isCertified" type="button" class="ml-1 text-gray-400 hover:text-red-600" @click="remove(s)">remove</button>
                    </p>
                    <p v-for="s in row.reserves" :key="`r${s.id}`" class="text-[11px] text-gray-600">
                        <strong>Reserve:</strong> {{ s.student_name }} ({{ s.school_name }})
                        <span v-if="s.note"> — {{ s.note }}</span>
                        <button v-if="!isCertified" type="button" class="ml-1 text-gray-400 hover:text-red-600" @click="remove(s)">remove</button>
                    </p>
                    <p v-for="s in row.declined" :key="`d${s.id}`" class="text-[11px] text-amber-800">
                        <strong>Declined:</strong> {{ s.student_name }} (position {{ s.source_position }}) — {{ s.note }}
                        <button v-if="!isCertified" type="button" class="ml-1 text-gray-400 hover:underline" @click="withdrawDecline(s)">undo</button>
                    </p>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    event: Object, program: Object, batch: Object, filters: Object,
    sheet: Array, readiness: Object, categories: Array, classGroups: Array,
    currentUserId: Number, actionUrls: Object,
});

const isCertified = computed(() => props.batch.status === 'certified');

function filter(key, value) {
    const next = { ...props.filters, [key]: value || undefined };
    router.get(props.actionUrls.index, next, { preserveState: true, preserveScroll: true, replace: true });
}

function choose(row, candidate, type) {
    // Guarded here as well as on the server so the common mistake gets a sentence rather than a 422.
    if (type === 'primary' && row.quota && row.chosen_count >= row.quota) {
        alert(`${row.item_code} already has its ${row.quota} seat(s) filled. Remove someone first, or add this candidate as a reserve.`);
        return;
    }
    router.post(props.actionUrls.choose, {
        candidate, nomination_type: type, priority_order: row.chosen_count + 1,
    }, { preserveScroll: true });
}

function remove(selection) {
    router.delete(`${props.actionUrls.remove}/${selection.id}`, { preserveScroll: true });
}

function decline(candidate) {
    const reason = window.prompt(`Why can ${candidate.student_name} not go to State?`);
    if (!reason || !reason.trim()) return;
    router.post(props.actionUrls.decline, { candidate, reason }, { preserveScroll: true });
}

function withdrawDecline(selection) {
    router.delete(`${props.actionUrls.withdrawDecline}/${selection.id}`, { preserveScroll: true });
}

function autoFill() {
    if (!confirm('Fill every empty seat with the top of that item\'s sheet? Tied positions are left for you to decide.')) return;
    router.post(props.actionUrls.autoFill, {}, { preserveScroll: true });
}

function register() {
    if (!confirm(`Register ${props.readiness.chosen} winner(s) with State? They enter the State's scrutiny queue.`)) return;
    router.post(props.actionUrls.register, {}, { preserveScroll: true });
}
</script>
