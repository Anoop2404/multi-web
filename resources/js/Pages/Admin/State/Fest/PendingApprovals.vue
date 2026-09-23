<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h2 class="text-sm font-bold text-slate-900">Pending approvals</h2>
            <p class="mb-4 mt-0.5 text-xs text-slate-500">
                Entries past intake but not yet decided — pending, returned for correction, or waiting on documents.
            </p>

            <p v-if="!entries.length" class="py-10 text-center text-sm text-slate-400">Nothing is waiting on a decision.</p>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-3">Sahodaya</th>
                            <th class="py-2 px-3">School</th>
                            <th class="py-2 px-3">Item</th>
                            <th class="py-2 px-3">Participant</th>
                            <th class="py-2 px-3">Status</th>
                            <th class="py-2 px-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in entries" :key="e.id" class="border-b border-slate-100">
                            <td class="py-2 pr-3 font-medium text-slate-800">{{ e.sahodaya }}</td>
                            <td class="py-2 px-3 text-slate-600">{{ e.school }}</td>
                            <td class="py-2 px-3 font-mono text-xs">{{ e.item_code }}</td>
                            <td class="py-2 px-3 text-slate-700">{{ e.student_name }}</td>
                            <td class="py-2 px-3">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                                      :class="e.status === 'pending' ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-700'">
                                    {{ e.status.replace('_', ' ') }}
                                </span>
                                <p v-if="e.review_note" class="mt-0.5 text-xs italic text-slate-500">“{{ e.review_note }}”</p>
                            </td>
                            <td class="py-2 px-3 text-right">
                                <Link :href="e.review_url" class="text-xs font-semibold text-[color:var(--brand-blue)] hover:underline">Open</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';
defineProps({ event: Object, events: Array, sahodayas: Array, permissions: Array, entries: Array });
</script>
