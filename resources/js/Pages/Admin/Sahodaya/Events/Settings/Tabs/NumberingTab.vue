<template>
    <div class="space-y-6 max-w-4xl">
        <div class="notice-banner notice-banner--info text-sm">
            <p class="font-semibold text-[#0f3d7a] mb-2">Three number types for sports &amp; fest events</p>
            <ul class="list-disc pl-4 space-y-1 text-slate-700">
                <li><strong>Event reg ID</strong> — one number per student for the whole event (1, 2, 3…). Set the start below; prefix is optional.</li>
                <li><strong>Item reg ID</strong> — per item, per participant (1, 2, 3… within each item). Each item has its own starting number.</li>
                <li><strong>Chest no.</strong> — per item, assigned when registration is approved (e.g. 100, 101… for Athletics; 50, 51… for Chess).</li>
            </ul>
        </div>

        <form @submit.prevent="saveNumberingSettings" class="card space-y-4">
            <div>
                <h3 class="section-title">Event registration ID</h3>
                <p class="section-desc">Sequential number across the whole event — same ID on every item row for that student.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <FormField label="Start number">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="numberingSettingsForm.event_reg_start" type="number" min="1" class="field">
                    </template>
                </FormField>
                <FormField label="Prefix (optional)">
                    <template #default="{ id }">
                        <input :id="id" v-model="numberingSettingsForm.event_reg_prefix" type="text" class="field" placeholder="Leave blank for 1, 2, 3…">
                    </template>
                </FormField>
            </div>
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="numberingSettingsForm.processing">Save Fest ID settings</button>
                <button type="button" class="btn-secondary" @click="backfillRegs">Backfill missing Fest IDs</button>
            </FormActions>
        </form>

        <form @submit.prevent="saveItemNumbering" class="card space-y-4">
            <div>
                <h3 class="section-title">Per-item chest &amp; item reg starts</h3>
                <p class="section-desc">
                    Set starting numbers per competition item. Chest numbers count up within each item only.
                    Default chest start (when item blank): <strong>{{ numberingSettingsForm.chest_no_start || 1 }}</strong>.
                </p>
            </div>

            <div v-if="!itemNumberingForm.items.length" class="text-sm text-slate-500">
                No enabled items — add items from the event items page first.
            </div>

            <div v-else class="overflow-x-auto border border-slate-200 rounded-xl">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="p-3">Item</th>
                            <th class="p-3 w-28">Code</th>
                            <th class="p-3 w-32">Chest start</th>
                            <th class="p-3 w-32">Item reg start</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in itemNumberingForm.items" :key="row.id" class="border-t">
                            <td class="p-3 font-medium">{{ row.title }}</td>
                            <td class="p-3 font-mono text-xs text-slate-500">{{ row.item_code || '—' }}</td>
                            <td class="p-3">
                                <input v-model.number="itemNumberingForm.items[idx].chest_no_start"
                                       type="number" min="1" class="field text-sm w-full" placeholder="100">
                            </td>
                            <td class="p-3">
                                <input v-model.number="itemNumberingForm.items[idx].item_reg_id_start"
                                       type="number" min="1" class="field text-sm w-full" placeholder="1">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <FormField label="Default chest start (fallback)">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="numberingSettingsForm.chest_no_start" type="number" min="1" class="field"
                               @change="saveNumberingSettings">
                    </template>
                </FormField>
                <FormField label="Chest prefix (optional)">
                    <template #default="{ id }">
                        <input :id="id" v-model="numberingSettingsForm.chest_no_prefix" type="text" class="field">
                    </template>
                </FormField>
            </div>

            <FormActions>
                <button type="submit" class="btn-primary" :disabled="itemNumberingForm.processing">Save per-item starts</button>
            </FormActions>
        </form>

        <section class="card space-y-3">
            <h3 class="section-title">Assign numbers</h3>
            <p class="section-desc text-sm">After approving registrations, open each item on the chest numbers page to assign chest and item reg numbers.</p>
            <Link :href="chestUrl" class="btn-primary text-sm inline-flex">Open chest numbers (by item) →</Link>
        </section>
    </div>
</template>

<script setup>
import { inject } from 'vue';
import { Link } from '@inertiajs/vue3';

const {
    numberingSettingsForm,
    itemNumberingForm,
    saveNumberingSettings,
    saveItemNumbering,
    backfillRegs,
    sahodaya,
    event,
} = inject('eventSettings');

const chestUrl = `/sahodaya-admin/${sahodaya.id}/events/${event.id}/chest-numbers`;
</script>
