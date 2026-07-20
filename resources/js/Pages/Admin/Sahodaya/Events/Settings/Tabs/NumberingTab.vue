<template>
    <div class="space-y-6">
        <!-- Guidance Banner Card -->
        <div class="rounded-xl border border-indigo-200/80 bg-indigo-50/50 p-4 text-xs text-indigo-950 shadow-sm space-y-1.5">
            <p class="font-bold text-indigo-900 flex items-center gap-1.5 text-sm">
                <span>🔢</span> Chest Numbering &amp; Event Registration ID Scheme
            </p>
            <ul class="list-disc pl-4 space-y-1 text-indigo-900/80 leading-relaxed">
                <li><strong>Event Reg ID</strong>: Unique ID per student across the whole event (1, 2, 3…).</li>
                <li><strong>Item Reg ID</strong>: Per-item sequential number (1, 2, 3… per competition item).</li>
                <li><strong>Chest Number</strong>: Assigned upon registration approval (e.g. 100, 101… for Athletics; 50, 51… for Chess).</li>
            </ul>
        </div>

        <!-- Section 1: Event Registration ID -->
        <form @submit.prevent="saveNumberingSettings" class="card !p-5 space-y-4 border border-slate-200">
            <div class="border-b border-slate-100 pb-3">
                <h3 class="section-title !mb-0 flex items-center gap-2 text-base">
                    <span>🪪</span> Event Registration ID Scheme
                </h3>
                <p class="section-desc mt-0.5">Sequential ID across the event — same ID on every item row for that student.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="form-label text-xs">Start Number *</label>
                    <input v-model.number="numberingSettingsForm.event_reg_start" type="number" min="1" class="field text-xs" required placeholder="1">
                </div>
                <div>
                    <label class="form-label text-xs">Prefix (optional)</label>
                    <input v-model="numberingSettingsForm.event_reg_prefix" type="text" class="field text-xs" placeholder="e.g. ATH-, REG- or blank for 1, 2, 3…">
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" class="btn-secondary text-xs" @click="backfillRegs">Backfill Missing Fest IDs</button>
                <button type="submit" class="btn-primary text-xs !py-1.5 !px-4" :disabled="numberingSettingsForm.processing">
                    Save ID Settings
                </button>
            </div>
        </form>

        <!-- Section 2: Per-Item Chest Starts -->
        <form @submit.prevent="saveItemNumbering" class="card !p-5 space-y-4 border border-slate-200">
            <div class="border-b border-slate-100 pb-3">
                <h3 class="section-title !mb-0 flex items-center gap-2 text-base">
                    <span>🔢</span> Per-Item Chest &amp; Item Reg Starting Ranges
                </h3>
                <p class="section-desc mt-0.5">
                    Set starting chest and registration numbers per competition item. Default fallback chest start: <strong>{{ numberingSettingsForm.chest_no_start || 1 }}</strong>.
                </p>
            </div>

            <div v-if="!itemNumberingForm.items.length" class="rounded-xl border border-dashed border-slate-200 p-8 text-center text-slate-400 text-xs">
                No enabled competition items found. Add items from the event items page first.
            </div>

            <div v-else class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-slate-500 border-b border-slate-200 uppercase tracking-wider text-[10px] font-bold">
                        <tr>
                            <th class="p-3.5">Item Title</th>
                            <th class="p-3.5 w-28">Item Code</th>
                            <th class="p-3.5 w-36">Chest Start #</th>
                            <th class="p-3.5 w-36">Item Reg Start #</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(row, idx) in itemNumberingForm.items" :key="row.id" class="hover:bg-slate-50/70 transition">
                            <td class="p-3.5 font-bold text-slate-900">{{ row.title }}</td>
                            <td class="p-3.5 font-mono text-slate-500 text-[11px]">{{ row.item_code || '—' }}</td>
                            <td class="p-3.5">
                                <input v-model.number="itemNumberingForm.items[idx].chest_no_start"
                                       type="number" min="1" class="field text-xs w-full" placeholder="100">
                            </td>
                            <td class="p-3.5">
                                <input v-model.number="itemNumberingForm.items[idx].item_reg_id_start"
                                       type="number" min="1" class="field text-xs w-full" placeholder="1">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 pt-2">
                <div>
                    <label class="form-label text-xs">Default Chest Start (Fallback)</label>
                    <input v-model.number="numberingSettingsForm.chest_no_start" type="number" min="1" class="field text-xs" placeholder="1" @change="saveNumberingSettings">
                </div>
                <div>
                    <label class="form-label text-xs">Chest Prefix (optional)</label>
                    <input v-model="numberingSettingsForm.chest_no_prefix" type="text" class="field text-xs" placeholder="e.g. C-">
                </div>
            </div>

            <div class="flex justify-end pt-2 border-t border-slate-100">
                <button type="submit" class="btn-primary text-xs !py-1.5 !px-4" :disabled="itemNumberingForm.processing">
                    Save Per-Item Starts
                </button>
            </div>
        </form>

        <!-- Section 3: Open Chest Numbers Link Card -->
        <section class="card !p-5 space-y-3 border border-slate-200 bg-gradient-to-r from-indigo-50/50 to-white">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="section-title !mb-0 text-base">Assign Chest Numbers</h3>
                    <p class="section-desc mt-0.5">After approving registrations, open the chest numbers page to review and auto-generate student numbers.</p>
                </div>
                <Link :href="chestUrl" class="btn-primary text-xs !py-2 !px-4 shrink-0">
                    Open Chest Numbers Page →
                </Link>
            </div>
        </section>
    </div>
</template>

<script setup>
import { inject } from 'vue';
import { Link } from '@inertiajs/vue3';

const { numberingSettingsForm, itemNumberingForm, chestUrl, saveNumberingSettings, saveItemNumbering, backfillRegs } = inject('eventSettings');
</script>
