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
            <div class="border-t border-slate-100 pt-3 space-y-2">
                <label class="flex items-start gap-2 text-xs">
                    <input type="checkbox" v-model="numberingSettingsForm.auto_assign_chest_on_create" class="mt-0.5">
                    <span>Assign chest numbers as soon as a registration is created, not just on approval</span>
                </label>
                <label class="flex items-start gap-2 text-xs">
                    <input type="checkbox" v-model="numberingSettingsForm.auto_assign_on_approve" class="mt-0.5">
                    <span>Auto-assign Item Reg IDs when a registration is approved</span>
                </label>
                <label class="flex items-start gap-2 text-xs">
                    <input type="checkbox" v-model="numberingSettingsForm.auto_assign_chest_on_approve" class="mt-0.5">
                    <span>
                        Also auto-assign chest numbers at the same time
                        <span class="block text-slate-400 font-normal mt-0.5">
                            Uncheck this to leave chest numbers unassigned at approval — an item's numbers
                            then get handed out later, all at once and in shuffled order, from the
                            "Assign missing chest" button on the Chest Numbers page. Item Reg IDs above
                            are unaffected either way.
                        </span>
                    </span>
                </label>
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

            <p v-if="event.event_type === 'sports'" class="rounded-lg bg-indigo-50 p-3 text-xs text-indigo-800">
                Sports uses shared student chest numbers and optional school ranges. Per-item chest starts are disabled. Set the event fallback below or configure school ranges. Item Reg IDs remain per item.
            </p>

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
                                       :disabled="event.event_type === 'sports'" :title="event.event_type === 'sports' ? 'Sports uses student chest numbers and school ranges; item chest starts are inactive.' : ''"
                                       type="number" min="1" class="field text-xs w-full disabled:bg-slate-100 disabled:text-slate-400" placeholder="100">
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

        <!-- Section 3: Per-School Chest Number Starting Ranges (Sports Only) -->
        <form v-if="event.event_type === 'sports'" @submit.prevent="saveSchoolChestRanges" class="card !p-5 space-y-4 border border-slate-200">
            <div class="border-b border-slate-100 pb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="section-title !mb-0 flex items-center gap-2 text-base">
                        <span>🏫</span> Per-School Starting Chest Numbers
                    </h3>
                    <p class="section-desc mt-0.5">
                        Assign fixed chest number starting ranges per school (e.g., School A starting at 101, School B at 201). When chest numbers are generated, each student from that school receives continuous numbers within their school's allocated block.
                    </p>
                </div>
            </div>

            <div v-if="!schoolChestRangesForm.schools.length" class="rounded-xl border border-dashed border-slate-200 p-8 text-center text-slate-400 text-xs">
                No approved schools found for this Sahodaya.
            </div>

            <div v-else class="space-y-3">
                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600 bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                    <span class="font-semibold text-slate-700">Quick Auto-Distribute:</span>
                    <span>Start at</span>
                    <input v-model.number="quickDistributeStart" type="number" min="1" class="field text-xs w-20 !py-1" placeholder="100">
                    <span>with block size of</span>
                    <input v-model.number="quickDistributeBlock" type="number" min="1" class="field text-xs w-20 !py-1" placeholder="100">
                    <span>numbers per school</span>
                    <button type="button" class="btn-secondary text-xs !py-1 !px-2.5 ml-auto" @click="applyQuickDistribute">
                        Auto-fill Blocks
                    </button>
                    <button type="button" class="btn-secondary text-xs !py-1 !px-2.5 text-rose-600 hover:text-rose-700" @click="clearSchoolRanges">
                        Clear All
                    </button>
                </div>

                <div class="rounded-xl border border-slate-200 overflow-hidden bg-white max-h-96 overflow-y-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-500 border-b border-slate-200 uppercase tracking-wider text-[10px] font-bold sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th class="p-3.5">School Name</th>
                                <th class="p-3.5 w-36">Chest Start #</th>
                                <th class="p-3.5 w-36">Chest End # (optional)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, idx) in schoolChestRangesForm.schools" :key="row.school_id" class="hover:bg-slate-50/70 transition">
                                <td class="p-3.5 font-bold text-slate-900">{{ row.school_name }}</td>
                                <td class="p-3.5">
                                    <input v-model.number="schoolChestRangesForm.schools[idx].chest_no_start"
                                           type="number" min="1" class="field text-xs w-full" placeholder="e.g. 101">
                                </td>
                                <td class="p-3.5">
                                    <input v-model.number="schoolChestRangesForm.schools[idx].chest_no_end"
                                           type="number" min="1" class="field text-xs w-full" placeholder="e.g. 150">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end pt-2 border-t border-slate-100">
                <button type="submit" class="btn-primary text-xs !py-1.5 !px-4" :disabled="schoolChestRangesForm.processing">
                    Save School Chest Ranges
                </button>
            </div>
        </form>

        <!-- Section 4: Open Chest Numbers Link Card -->
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
import { inject, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const {
    event,
    numberingSettingsForm,
    itemNumberingForm,
    schoolChestRangesForm,
    chestUrl,
    saveNumberingSettings,
    saveItemNumbering,
    saveSchoolChestRanges,
    backfillRegs
} = inject('eventSettings');

const quickDistributeStart = ref(100);
const quickDistributeBlock = ref(50);

function applyQuickDistribute() {
    let current = Number(quickDistributeStart.value) || 100;
    const block = Number(quickDistributeBlock.value) || 50;
    for (const row of schoolChestRangesForm.schools) {
        row.chest_no_start = current;
        row.chest_no_end = current + block - 1;
        current += block;
    }
}

function clearSchoolRanges() {
    for (const row of schoolChestRangesForm.schools) {
        row.chest_no_start = '';
        row.chest_no_end = '';
    }
}
</script>
