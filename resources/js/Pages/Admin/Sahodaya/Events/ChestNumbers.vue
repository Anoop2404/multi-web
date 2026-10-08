<template>
    <SahodayaEventsLayout :title="`${event.title} — Chest Numbers`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Chest numbers"
                    :description="selectedItem
                        ? (event.event_type === 'sports'
                            ? `Chest starts at ${selectedItem.chest_no_start} · one chest number per student, shared across sports items`
                            : `Chest starts at ${selectedItem.chest_no_start} · unique chest numbers assigned per item participant`)
                        : (event.event_type === 'sports'
                            ? 'Pick an item — each student keeps a single chest number across the sports event.'
                            : 'Pick an item — view or assign chest numbers per competition item.')">
            <template #actions>
                <button v-if="event.event_type !== 'sports'" type="button" class="btn-primary text-sm" @click="assignAllMissingForEvent">
                    Assign all missing chest numbers (this phase)
                </button>
                <Link :href="numberingUrl" class="btn-secondary text-sm">Numbering settings</Link>
                <!-- Moved here from the per-item Danger Zone row below: this wipes chest
                     numbers for the WHOLE event, not just the item currently open, so it
                     belongs beside the other event-wide numbering action, not mixed in
                     with the item-scoped buttons. Still styled/confirmed as destructive. -->
                <button type="button" class="btn-secondary text-sm !text-rose-700 hover:!bg-rose-100 !bg-white border-rose-300 font-semibold" @click="clearEntireEventChests">
                    Reset All Chests (Entire Event)
                </button>
            </template>
        </PageHeader>

        <div v-if="event.event_type === 'sports'" class="card mb-5 space-y-3">
            <h3 class="font-semibold">Generate chest numbers using school ranges</h3>
            <p class="text-sm text-slate-600">School ranges take priority. Schools without a range use the custom fallback below. Existing numbers are preserved; only missing numbers are assigned for all items in this event/phase.</p>
            <div class="flex flex-wrap items-end gap-3">
                <button type="button" class="btn-secondary" @click="openSchoolRangesModal">Configure school ranges</button>
                <label class="text-xs font-semibold">Custom fallback start
                    <input v-model.number="sportsFallbackStart" type="number" min="1" class="field mt-1 w-36">
                </label>
                <button type="button" class="btn-primary" :disabled="sportsGenerating || !sportsFallbackStart || sportsFallbackStart < 1" @click="generateSportsChests">Generate all using school ranges</button>
            </div>
            <p class="text-xs text-slate-500">Fallback also applies when a school's range is full. To replace existing numbers, reset all chests first.</p>
        </div>

        <SportsSetupSubNav v-if="event.event_type === 'sports'" :sahodaya-id="sahodaya.id" :event-id="event.id" active="chest-numbers" :event="event" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="chest-numbers" class="mb-4" />

        <!-- Sport Event / Region Switcher -->
        <div v-if="childEvents.length" class="card mb-4 !py-3">
            <div class="flex flex-wrap gap-3 items-center">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ event.event_type === 'sports' ? 'Select Sport Event / Region:' : 'Select Phase / Region:' }}</label>
                <SearchableSelect :model-value="String(event.id)" @update:model-value="switchSportEvent"
                                  :options="childEventOptions" :all-option="false" class="w-64" />
            </div>
        </div>

        <div v-if="flatItems.length" class="card mb-4 !py-3">
            <ReportItemSearchSelect :items="flatItems" :model-value="selectedItemId"
                                    label="Jump to item" all-items-label="Select an item"
                                    search-placeholder="Search by item name or code…"
                                    @select="jumpToItem" />
        </div>

        <ReportHeadItemNavigator :groups="headItemGroups"
                                 :base-url="base"
                                 :selected-head-id="selectedHeadId"
                                 :selected-item-id="selectedItemId"
                                 :has-item-heads="hasItemHeads"
                                 :is-sports="event.event_type === 'sports'"
                                 :show-assigned-filter="true"
                                 :hint="event.event_type === 'sports'
                                     ? 'Select a competition item to view or assign chest numbers — each student holds one chest number for the whole sports event.'
                                     : 'Select a competition item to view or assign chest numbers for participants in that item.'"
                                 empty-heads-text="No enabled items on this event yet. Import items from the catalog first.">

            <template #default="{ item, head }">
                <div class="space-y-4 mt-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" :checked="includePending" class="rounded border-slate-300"
                                   @change="togglePending">
                            Include submitted (pending approval)
                        </label>
                        <span v-if="totalCount" class="text-xs font-semibold px-2.5 py-1 rounded-full"
                              :class="assignedCount === totalCount ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                            {{ assignedCount === totalCount ? '✓' : '⏳' }} {{ assignedCount }}/{{ totalCount }} chest numbers assigned
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap gap-2">
                            <button v-if="totalCount > assignedCount" type="button" class="btn-primary text-sm" :disabled="isSavingBulk" @click="startBulkEdit(true)">
                                Add missing chest numbers manually ({{ totalCount - assignedCount }})
                            </button>
                            <button v-if="event.event_type !== 'sports'" type="button" class="btn-secondary text-sm" @click="generate">Assign missing chest</button>
                            <button type="button" class="btn-secondary text-sm" @click="assignItemReg">Assign missing item reg</button>
                            <button v-if="selectedItemId && participants.length" type="button"
                                    :class="isBulkEditing ? 'btn-primary !bg-indigo-600' : 'btn-secondary'"
                                    class="text-sm font-semibold" @click="toggleBulkEdit">
                                {{ isBulkEditing ? '✕ Exit Bulk Edit' : '✏️ Bulk Edit' }}
                            </button>
                            <button v-if="selectedItemId && event.event_type !== 'sports'" type="button" class="btn-secondary text-sm" @click="openItemNumberingModal(item)">
                                🔢 Set starting no (this item)
                            </button>
                            <button v-if="event.event_type !== 'sports'" type="button" class="btn-secondary text-sm" @click="openBulkNumberingModal">
                                🔢 Set common starting no (all items)
                            </button>

                            <a :href="`${printUrl}${printUrl.includes('?') ? '&' : '?'}inline=1`" target="_blank" class="btn-secondary text-sm">Preview list</a>
                            <a :href="`${printUrl}${printUrl.includes('?') ? '&' : '?'}download=1`" target="_blank" class="btn-secondary text-sm">Download list (PDF)</a>
                            <a :href="csvUrl" class="btn-secondary text-sm">CSV</a>
                            <button v-if="item?.stage_type === 'on_stage' && event.chest_reveal_mode === 'stage_entry'"
                                    type="button" class="btn-secondary text-sm"
                                    :class="showGreen ? 'border-emerald-400 bg-emerald-50' : ''"
                                    @click="showGreen = !showGreen">
                                Green room ({{ greenRoom.length }})
                            </button>
                        </div>

                        <!-- Kept visually apart from the safe/utility actions above — wipes
                             already-assigned numbers, which is easy to fat-finger when it's
                             sitting in the same row as "CSV". The whole-event reset now lives
                             in the page header next to "Numbering settings" instead of here,
                             since it isn't scoped to whichever item happens to be open. -->
                        <div class="flex flex-wrap gap-2 border border-rose-200 bg-rose-50/50 rounded-lg p-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-rose-500 self-center pl-1">Danger zone</span>
                            <button v-if="selectedItemId" type="button" class="btn-secondary text-sm !text-rose-700 hover:!bg-rose-100 !bg-white border-rose-300 font-semibold" @click="clearAllChests">
                                Clear Item Chests
                            </button>
                        </div>
                    </div>

                    <div v-if="showGreen" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                        <h3 class="font-semibold text-sm mb-2 text-emerald-900">Green room</h3>
                        <div class="overflow-x-auto">
                        <table class="w-full text-sm bg-white border rounded-lg">
                            <thead class="bg-gray-50"><tr>
                                <th class="p-2 text-left">Sl No</th><th class="p-2 text-left">Chest</th><th class="p-2 text-left">Fest ID</th><th class="p-2 text-left">Name</th><th class="p-2"></th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(p, idx) in greenRoom" :key="p.id" class="border-t">
                                    <td class="p-2 text-gray-500">{{ idx + 1 }}</td>
                                    <td class="p-2 font-mono">{{ p.chest_no ?? '—' }}</td>
                                    <td class="p-2 font-mono text-xs">{{ p.fest_id ?? '—' }}</td>
                                    <td class="p-2">{{ p.name }}</td>
                                    <td class="p-2 text-right">
                                        <button @click="reveal(p.id)" class="text-indigo-600 text-xs font-semibold">Reveal</button>
                                    </td>
                                </tr>
                                <tr v-if="!greenRoom.length"><td colspan="5" class="p-3 text-gray-400 text-center">None waiting</td></tr>
                            </tbody>
                        </table>
                        </div>
                    </div>

                    <!-- Bulk Editing Toolbar -->
                    <div v-if="isBulkEditing" class="bg-indigo-50/90 border border-indigo-200 rounded-xl p-4 space-y-3 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h4 class="font-bold text-sm text-indigo-950 flex items-center gap-2">
                                    <span>{{ missingOnly ? 'Add missing chest numbers' : '✏️ Bulk Edit Mode' }}</span>
                                    <span class="text-xs font-semibold text-indigo-700 bg-indigo-100 px-2.5 py-0.5 rounded-full">
                                        {{ bulkParticipants.length }} participants
                                    </span>
                                </h4>
                                <p class="text-xs text-indigo-700 mt-0.5">
                                    {{ missingOnly ? 'Enter chest numbers in the empty boxes below, then save all together. Blank boxes stay unassigned.' : 'Type directly in the Chest and Order boxes below.' }} Use <kbd class="px-1 py-0.5 bg-white border rounded text-[11px] font-mono">Tab</kbd> to move across fields quickly.
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="btn-secondary text-xs !bg-white" @click="cancelBulkEdit" :disabled="isSavingBulk">
                                    Cancel
                                </button>
                                <button type="button" class="btn-primary text-xs !bg-indigo-600 hover:!bg-indigo-700" @click="saveBulkChanges" :disabled="isSavingBulk">
                                    {{ isSavingBulk ? 'Saving...' : '💾 Save All Changes' }}
                                </button>
                            </div>
                        </div>

                        <!-- Quick sequence helpers -->
                        <div class="flex flex-wrap items-center gap-3 pt-2.5 border-t border-indigo-200/70 text-xs">
                            <div class="flex items-center gap-1.5 bg-white px-2.5 py-1.5 rounded-lg border border-indigo-200">
                                <span class="text-slate-600 font-medium">Chest start:</span>
                                <input v-model.number="autoChestStart" type="number" min="1" class="w-16 px-1.5 py-0.5 border border-slate-300 rounded font-mono text-center text-xs" />
                                <button type="button" class="text-indigo-600 font-bold hover:underline" @click="autoFillChests">
                                    Auto-fill Chests ↓
                                </button>
                                <button type="button" class="text-slate-400 hover:text-rose-600 ml-1 text-xs" title="Clear draft chests" @click="clearDraftChests">
                                    ✕
                                </button>
                            </div>

                            <div v-if="!missingOnly" class="flex items-center gap-1.5 bg-white px-2.5 py-1.5 rounded-lg border border-indigo-200">
                                <span class="text-slate-600 font-medium">Order start:</span>
                                <input v-model.number="autoOrderStart" type="number" min="1" class="w-14 px-1.5 py-0.5 border border-slate-300 rounded font-mono text-center text-xs" />
                                <button type="button" class="text-indigo-600 font-bold hover:underline" @click="autoFillOrders">
                                    Auto-fill Orders ↓
                                </button>
                                <button type="button" class="text-slate-400 hover:text-rose-600 ml-1 text-xs" title="Clear draft orders" @click="clearDraftOrders">
                                    ✕
                                </button>
                            </div>

                            <div v-if="bulkError" class="text-rose-700 font-semibold bg-rose-50 px-2.5 py-1 rounded border border-rose-200">
                                ⚠️ {{ bulkError }}
                            </div>
                        </div>
                    </div>

                    <div class="card card--flush overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="p-3">Sl No</th><th class="p-3">Chest</th><th class="p-3">Order</th><th class="p-3">Fest ID</th><th class="p-3">Item reg</th>
                                    <th class="p-3">Participant / Team</th><th class="p-3">School</th><th class="p-3">Status</th>
                                    <th class="p-3">{{ hasTeamRows ? 'Members' : 'Team' }}</th><th class="p-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(p, idx) in participants" :key="p.id" class="border-t"
                                    :class="p.chest_no ? 'hover:bg-slate-50' : 'bg-amber-50/60 hover:bg-amber-50'">
                                    <td class="p-3 text-gray-500">{{ idx + 1 }}</td>
                                    <td class="p-3 font-mono font-bold">
                                        <div v-if="isBulkEditing && (!missingOnly || !p.chest_no)">
                                            <input type="number" min="1" :aria-label="`Chest number for ${p.name}`" :disabled="isSavingBulk"
                                                   v-model.number="bulkDrafts[p.id].chest_no"
                                                   class="w-20 rounded border-2 border-indigo-400 px-2 py-1 text-sm font-mono font-bold focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white"
                                                   placeholder="—" />
                                        </div>
                                        <div v-else-if="editingChestId === p.id" class="relative flex items-center gap-1">
                                            <input ref="chestEditInput" type="number" min="1"
                                                   v-model="chestDraft"
                                                   @keydown.enter="confirmSetChest(p)"
                                                   @keydown.escape="cancelChestEdit"
                                                   @blur="autoSaveChest(p)"
                                                   class="w-20 rounded border-2 border-indigo-400 px-2 py-1 text-sm font-mono font-normal focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                                            <button @mousedown.prevent @click="confirmSetChest(p)" title="Save" class="text-emerald-600 hover:text-emerald-700 font-normal">✓</button>
                                            <button @mousedown.prevent @click="cancelChestEdit" title="Cancel" class="text-slate-400 hover:text-slate-600 font-normal">✕</button>
                                            <p v-if="chestError" class="absolute mt-9 text-[11px] font-normal text-red-600 bg-white border border-red-200 rounded px-2 py-1 shadow-sm">{{ chestError }}</p>
                                        </div>
                                        <div v-else class="flex items-center gap-1.5">
                                            <span>{{ p.chest_no ?? '—' }}</span>
                                            <button v-if="!isBulkEditing" @click="startChestEdit(p)" title="Set chest number"
                                                    class="text-slate-300 hover:text-indigo-600 font-normal text-xs leading-none">✎</button>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div v-if="isBulkEditing && !missingOnly">
                                            <input type="number" min="1" :disabled="isSavingBulk"
                                                   v-model.number="bulkDrafts[p.id].order_no"
                                                   class="w-16 rounded border-2 border-indigo-400 px-2 py-1 text-sm font-mono font-bold focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white"
                                                   placeholder="—" />
                                        </div>
                                        <span v-else-if="isBulkEditing && missingOnly" class="font-mono">{{ p.order_no ?? '—' }}</span>
                                        <SearchableSelect v-else :model-value="p.order_no ?? ''"
                                                :options="orderOptionsFor(p.id).map((n) => ({ value: n, label: String(n) }))"
                                                :all-option="true" all-label="— No order —"
                                                @update:model-value="(value) => saveOrderNo(p.id, value)" />
                                    </td>
                                    <td class="p-3 font-mono text-xs text-[#0f3d7a]">{{ p.fest_id ?? '—' }}</td>
                                    <td class="p-3 font-mono text-xs">{{ p.item_reg ?? '—' }}</td>
                                    <td class="p-3">
                                        {{ p.name }}
                                        <span v-if="p.is_team" class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 align-middle">
                                            Team · {{ p.member_count }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-xs">{{ (p.school || '').toUpperCase() }}</td>
                                    <td class="p-3 text-xs" :class="p.reg_status === 'approved' ? 'text-emerald-700' : 'text-amber-700'">{{ p.reg_status }}</td>
                                    <td class="p-3 text-xs">{{ p.group ?? '—' }}</td>
                                    <td class="p-3 text-right whitespace-nowrap">
                                        <button v-if="p.chest_no" @click="clearChest(p.id)" class="text-red-600 text-xs mr-2">Clear</button>
                                        <button v-if="event.chest_reveal_mode === 'stage_entry' && !p.chest_revealed_at" @click="reveal(p.id)"
                                                class="text-indigo-600 text-xs">Reveal</button>
                                    </td>
                                </tr>
                                <tr v-if="!participants.length">
                                    <td colspan="10" class="p-0">
                                        <EmptyState title="No participants for this item"
                                            description="Approve registrations for this item first, then chest numbers can be generated." icon="🔢" class="py-8">
                                            <template #action>
                                                <Link :href="registrationsUrl" class="btn-primary text-xs">Review Registrations</Link>
                                            </template>
                                        </EmptyState>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="isBulkEditing && participants.length > 8" class="flex justify-end gap-2 p-3 bg-indigo-50/70 border-t border-indigo-100">
                            <button type="button" class="btn-secondary text-xs !bg-white" @click="cancelBulkEdit" :disabled="isSavingBulk">Cancel</button>
                            <button type="button" class="btn-primary text-xs !bg-indigo-600 hover:!bg-indigo-700" @click="saveBulkChanges" :disabled="isSavingBulk">
                                {{ isSavingBulk ? 'Saving...' : '💾 Save All Changes' }}
                            </button>
                        </div>
                        <p v-if="hasTeamRows" class="px-3 py-2 text-xs text-slate-500 border-t">
                            This is a team item — one chest number is shared by the whole squad. Clearing or revealing applies to every member.
                        </p>
                    </div>
                </div>
            </template>
        </ReportHeadItemNavigator>

        <!-- Per-item starting-number popup — quick alternative to the full table on the
             Settings > Numbering tab, scoped to just the item currently open here. -->
        <Modal :show="showItemNumberingModal" title="Set starting numbers"
               :subtitle="itemNumberingTitle" size="sm" @close="showItemNumberingModal = false">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Chest start #</label>
                    <input v-model.number="itemNumberingForm.chest_no_start" type="number" min="1" class="field w-full">
                </div>
                <p class="text-xs text-slate-400">Only affects numbers not yet assigned — existing chest numbers for this item are untouched.</p>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary text-sm" @click="showItemNumberingModal = false">Cancel</button>
                    <button type="button" class="btn-primary text-sm" @click="saveItemNumbering">Save</button>
                </div>
            </template>
        </Modal>

        <!-- Bulk "common starting number" popup — writes the same chest start into every
             item in the event at once; still editable per item afterward (here or on the
             Settings > Numbering tab). -->
        <Modal :show="showBulkNumberingModal" title="Set common starting no for all items"
               subtitle="Applies this chest start number to every item in the event." size="sm"
               @close="showBulkNumberingModal = false">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Chest start # (all items)</label>
                <input v-model.number="bulkChestStart" type="number" min="1" class="field w-full">
                <p class="text-xs text-slate-400 mt-2">Each item's own item-reg start number is left as-is. You can still fine-tune any single item's chest start afterward.</p>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary text-sm" @click="showBulkNumberingModal = false">Cancel</button>
                    <button type="button" class="btn-primary text-sm" @click="saveBulkNumbering">Apply to all items</button>
                </div>
            </template>
        </Modal>

        <!-- Per-School Chest Number Starting Ranges Modal -->
        <Modal :show="showSchoolRangesModal" title="Set Starting Chest Number Per School"
               subtitle="When assigning chest numbers, students from each school get continuous numbers starting from their school's allocated start number." size="lg"
               @close="showSchoolRangesModal = false">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600 bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                    <span class="font-semibold text-slate-700">Auto-fill sequence:</span>
                    <span>Start at</span>
                    <input v-model.number="quickDistributeStart" type="number" min="1" class="field text-xs w-20 !py-1" placeholder="100">
                    <span>Block size</span>
                    <input v-model.number="quickDistributeBlock" type="number" min="1" class="field text-xs w-20 !py-1" placeholder="50">
                    <button type="button" class="btn-secondary text-xs !py-1 !px-2.5 ml-auto" @click="applyQuickDistribute">
                        Auto-fill Blocks
                    </button>
                    <button type="button" class="btn-secondary text-xs !py-1 !px-2.5 text-rose-600 hover:text-rose-700" @click="clearSchoolRanges">
                        Clear All
                    </button>
                </div>

                <div v-if="!schoolRangeRows.length" class="text-center py-6 text-xs text-slate-400">
                    No approved schools found for this Sahodaya.
                </div>

                <div v-else class="max-h-80 overflow-y-auto border border-slate-200 rounded-lg">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-500 border-b uppercase tracking-wider text-[10px] font-bold sticky top-0 bg-white z-10">
                            <tr>
                                <th class="p-2.5">School</th>
                                <th class="p-2.5 w-32">Chest Start #</th>
                                <th class="p-2.5 w-32">Chest End # (opt)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, idx) in schoolRangeRows" :key="row.school_id" class="hover:bg-slate-50">
                                <td class="p-2.5 font-medium text-slate-800">{{ row.school_name }}</td>
                                <td class="p-2.5">
                                    <input v-model.number="schoolRangeRows[idx].chest_no_start" type="number" min="1" class="field text-xs w-full !py-1" placeholder="e.g. 101">
                                </td>
                                <td class="p-2.5">
                                    <input v-model.number="schoolRangeRows[idx].chest_no_end" type="number" min="1" class="field text-xs w-full !py-1" placeholder="e.g. 150">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-slate-400">
                    Leave empty for any school that should use the default continuous pool.
                </p>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary text-sm" @click="showSchoolRangesModal = false" :disabled="isSavingSchoolRanges">Cancel</button>
                    <button type="button" class="btn-primary text-sm" @click="saveSchoolRanges" :disabled="isSavingSchoolRanges">
                        {{ isSavingSchoolRanges ? 'Saving…' : 'Save School Ranges' }}
                    </button>
                </div>
            </template>
        </Modal>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportHeadItemNavigator from '@/Components/reports/ReportHeadItemNavigator.vue';
import ReportItemSearchSelect from '@/Components/reports/ReportItemSearchSelect.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import Modal from '@/Components/ui/Modal.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object,
    headItemGroups: { type: Array, default: () => [] },
    hasItemHeads: { type: Boolean, default: false },
    selectedHeadId: { type: [String, Number], default: null },
    selectedItemId: { type: [String, Number], default: null },
    selectedItem: { type: Object, default: null },
    participants: { type: Array, default: () => [] },
    greenRoom: { type: Array, default: () => [] },
    includePending: { type: Boolean, default: false },
    view: String,
    activityLogs: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    schoolChestRanges: { type: Object, default: () => ({}) },
    itemHasMarksOrAttendance: { type: Boolean, default: false },
    eventHasMarksOrAttendance: { type: Boolean, default: false },
});

function switchSportEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/chest-numbers`);
}

const childEventOptions = computed(() =>
    props.childEvents.map((ev) => ({ value: String(ev.id), label: ev.short_title || ev.title })),
);

const showGreen = ref(props.view === 'green-room');
const hasTeamRows = computed(() => props.participants.some((p) => p.is_team));
const assignedCount = computed(() => props.participants.filter((p) => p.chest_no).length);
const totalCount = computed(() => props.participants.length);
const base = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/chest-numbers`);
const registrationsUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations`);
const numberingUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/settings/numbering`);
const pageTitle = computed(() => {
    if (props.selectedItem) return `${props.event.title} — ${props.selectedItem.title}`;
    if (props.selectedHeadId) {
        const head = props.headItemGroups.find((g) =>
            props.selectedHeadId === 'other' ? g.head_id == null : String(g.head_id) === String(props.selectedHeadId));
        return `${props.event.title} — ${head?.head_name ?? 'Section'}`;
    }
    return `${props.event.title} — Chest Numbers`;
});
// Flattened once for the "Jump to item" dropdown -- an alternate, faster entry point
// alongside the existing card grid, matching the item picker already on Attendance/
// Marks (ReportItemSearchSelect), which this page didn't have before.
const flatItems = computed(() => props.headItemGroups.flatMap((g) => g.items ?? []));

function jumpToItem(itemId) {
    if (!itemId) {
        router.get(base.value, {}, { preserveState: true });
        return;
    }
    const item = flatItems.value.find((it) => String(it.id) === String(itemId));
    const params = { item_id: itemId };
    if (item?.head_id != null) params.head_id = item.head_id;
    router.get(base.value, params, { preserveState: true });
}

const printUrl = computed(() =>
    props.selectedItemId ? `${base.value}/print?item_id=${props.selectedItemId}` : `${base.value}/print`,
);
const csvUrl = computed(() =>
    props.selectedItemId ? `${base.value}/csv?item_id=${props.selectedItemId}` : `${base.value}/csv`,
);

const { confirm } = useConfirm();

// preserveState: true on every action below (bulk and per-row) so this component
// instance survives each round trip instead of being torn down and remounted — that's
// what makes the per-row Saved/Error feedback below actually visible (a fresh mount
// would reset savedIds/rowErrors to empty before the user ever sees them) and avoids a
// jarring scroll/flash on every click for what's often a single-field change.
function postAction(path) {
    if (!props.selectedItemId) return;
    router.post(path, { item_id: props.selectedItemId }, { preserveScroll: true, preserveState: true });
}
function generate() { postAction(`${base.value}/generate`); }
function assignItemReg() { postAction(`${base.value}/assign-item-ids`); }
// Whole-event/whole-phase version of "Assign missing chest" above — no item_id, so it
// assigns every item still missing chest numbers, not just whichever one is currently
// open. Purely additive (never touches an already-assigned number), so no confirm
// dialog, matching the same no-confirm per-item "Assign missing chest" button.
const sportsFallbackStart = ref(props.event.numbering_settings?.chest_no_start ?? 100);
const sportsGenerating = ref(false);
function generateSportsChests() {
    sportsGenerating.value = true;
    router.post(`${base.value}/assign-missing-all`, { fallback_start: sportsFallbackStart.value }, {
        preserveScroll: true, preserveState: true,
        onFinish: () => { sportsGenerating.value = false; },
    });
}

function assignAllMissingForEvent() {
    router.post(`${base.value}/assign-missing-all`, {}, { preserveScroll: true, preserveState: true });
}
async function clearEntireEventChests() {
    let message = `Are you sure you want to reset and clear ALL chest numbers across the ENTIRE event "${props.event.title}"?\n\nThis will wipe chest numbers for all items so numbering starts back at 100.`;
    if (props.eventHasMarksOrAttendance) {
        message += '\n\n⚠️ Marks or attendance already exist for this event. If judges have a printed sheet with the old chest numbers, it will no longer match.';
    }
    if (!(await confirm({ message, destructive: true }))) return;

    router.post(`${base.value}/clear-all`, {}, { preserveScroll: true, preserveState: true });
}
async function clearAllChests() {
    let message = `Are you sure you want to clear chest numbers for item "${props.selectedItem?.title || ''}"?`;
    if (props.itemHasMarksOrAttendance) {
        message += '\n\n⚠️ Marks or attendance already exist for this item. If a judge has a printed sheet with the old chest numbers, it will no longer match.';
    }
    if (!(await confirm({ message, destructive: props.itemHasMarksOrAttendance }))) return;

    router.post(`${base.value}/clear-all`, { item_id: props.selectedItemId }, { preserveScroll: true, preserveState: true });
}
async function clearChest(id) {
    let message = 'Clear chest number?';
    if (props.itemHasMarksOrAttendance) {
        message += '\n\n⚠️ Marks or attendance already exist for this item. If a judge has a printed sheet with the old chest number, it will no longer match.';
    }
    if (!(await confirm({ message, destructive: props.itemHasMarksOrAttendance }))) return;
    router.post(`${base.value}/${id}/clear`, {}, { preserveScroll: true, preserveState: true });
}
function reveal(id) {
    router.post(`${base.value}/${id}/reveal`, {}, { preserveScroll: true, preserveState: true });
}

// Order No — same field/endpoint as the Mark Entry page (FestMarkEntryController::
// setOrderNo()), just editable from here too since organizers often set it while
// they're already assigning chest numbers. Options run 1..N for the current item's
// participant count, same dynamic-per-item behavior as Mark Entry — and already-taken
// numbers (by any OTHER row) are dropped from the list so a colliding pick is never
// offered in the first place.
function orderOptionsFor(currentParticipantId) {
    const count = props.participants.length;
    const taken = new Set(
        props.participants
            .filter((p) => p.id !== currentParticipantId)
            .map((p) => p.order_no)
            .filter((n) => n !== null && n !== undefined)
    );

    return Array.from({ length: count }, (_, i) => i + 1).filter((n) => !taken.has(n));
}

function saveOrderNo(id, value) {
    const orderNo = value === '' || value === null || value === undefined ? null : Number(value);
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks/${id}/order-no`,
        { order_no: orderNo },
        { preserveScroll: true, preserveState: true },
    );
}

// Manual chest-number override, alongside the auto-assign buttons above — for matching
// a number already printed on a badge, or any other one-off exception. Click-to-edit in
// place (like Order's SearchableSelect) rather than an always-on input in every row —
// that cluttered the CHEST column and clipped its own placeholder at the width the
// column needs — and rather than a modal dialog, which would round-trip away from the
// row and lose the surrounding context (who this is, what's already taken nearby).
// editingChestId is singular: only one row edits at a time, so a single template ref is
// enough — Vue re-mounts the input fresh for whichever row is currently open.
const editingChestId = ref(null);
const chestDraft = ref('');
const chestError = ref('');
const chestEditInput = ref(null);

async function startChestEdit(participant) {
    editingChestId.value = participant.id;
    chestDraft.value = participant.chest_no ? String(participant.chest_no) : '';
    chestError.value = '';
    await nextTick();
    const el = Array.isArray(chestEditInput.value) ? chestEditInput.value[0] : chestEditInput.value;
    el?.focus();
    el?.select();
}

function cancelChestEdit() {
    editingChestId.value = null;
    chestDraft.value = '';
    chestError.value = '';
}

// Blur (clicking anywhere else) auto-saves a real edit — the Save/Cancel buttons are
// still there for anyone who wants to be explicit, and both use @mousedown.prevent so
// clicking them never steals focus first and fires this blur handler behind their back.
// Escape already exits editing (and clears editingChestId) before blur lands, so the
// early return below is what makes Escape a true cancel rather than a save.
function autoSaveChest(participant) {
    if (editingChestId.value !== participant.id) {
        return;
    }

    const raw = chestDraft.value?.toString().trim();
    const currentValue = participant.chest_no ? String(participant.chest_no) : '';
    if (!raw || raw === currentValue) {
        cancelChestEdit();
        return;
    }

    confirmSetChest(participant);
}

function confirmSetChest(participant) {
    const chestNo = Number(chestDraft.value);
    if (!chestDraft.value || !Number.isInteger(chestNo) || chestNo < 1) {
        chestError.value = 'Enter a valid chest number.';
        return;
    }

    // Same-item participants are already loaded on screen — catching an obvious
    // collision against them here means no server round trip for the common case.
    // Anything scoped beyond this item (e.g. sibling sports items sharing a head) is
    // still enforced server-side by FestChestNumberService::setChest().
    const collision = props.participants.find((other) => other.id !== participant.id && other.chest_no === chestNo);
    if (collision) {
        chestError.value = `Already used by ${collision.name}.`;
        return;
    }

    router.post(`${base.value}/${participant.id}/set`, { chest_no: chestNo }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => cancelChestEdit(),
        onError: (errors) => {
            chestError.value = errors.chest_no || 'Could not set chest number.';
        },
    });
}

// Bulk Edit Mode
const isBulkEditing = ref(false);
const missingOnly = ref(false);
const bulkParticipants = computed(() => missingOnly.value
    ? props.participants.filter((p) => !p.chest_no)
    : props.participants);
watch(() => [props.selectedItemId, props.includePending], () => {
    cancelBulkEdit();
    if (props.selectedItemId && props.participants.some((p) => !p.chest_no)) startBulkEdit(true);
});
const isSavingBulk = ref(false);
const bulkError = ref('');
const bulkDrafts = reactive({});
const autoChestStart = ref(100);
const autoOrderStart = ref(1);

onMounted(() => {
    if (props.selectedItemId && props.participants.some((p) => !p.chest_no)) {
        startBulkEdit(true);
    }
});

function toggleBulkEdit() {
    if (isBulkEditing.value) {
        cancelBulkEdit();
    } else {
        startBulkEdit();
    }
}

function startBulkEdit(onlyMissing = false) {
    if (isSavingBulk.value) return;
    cancelBulkEdit();
    missingOnly.value = onlyMissing;
    cancelChestEdit();
    isBulkEditing.value = true;
    bulkError.value = '';

    const existingChests = props.participants
        .map((p) => Number(p.chest_no))
        .filter((n) => Number.isInteger(n) && n > 0);
    if (existingChests.length) {
        autoChestStart.value = Math.min(...existingChests);
    } else {
        autoChestStart.value = 100;
    }
    autoOrderStart.value = 1;

    for (const p of props.participants) {
        bulkDrafts[p.id] = {
            chest_no: p.chest_no ?? null,
            order_no: p.order_no ?? null,
        };
    }
}

function cancelBulkEdit() {
    if (isSavingBulk.value) return;
    isBulkEditing.value = false;
    bulkError.value = '';
    for (const key of Object.keys(bulkDrafts)) {
        delete bulkDrafts[key];
    }
}

function autoFillChests() {
    let current = Number(autoChestStart.value) || 100;
    const taken = new Set(missingOnly.value ? props.participants.filter((p) => p.chest_no).map((p) => Number(p.chest_no)) : []);
    for (const p of bulkParticipants.value) {
        while (taken.has(current)) current++;
        if (!bulkDrafts[p.id]) bulkDrafts[p.id] = {};
        bulkDrafts[p.id].chest_no = current++;
    }
}

function autoFillOrders() {
    let current = Number(autoOrderStart.value) || 1;
    for (const p of props.participants) {
        if (!bulkDrafts[p.id]) bulkDrafts[p.id] = {};
        bulkDrafts[p.id].order_no = current++;
    }
}

function clearDraftChests() {
    for (const p of bulkParticipants.value) {
        if (bulkDrafts[p.id]) bulkDrafts[p.id].chest_no = null;
    }
}

function clearDraftOrders() {
    for (const p of props.participants) {
        if (bulkDrafts[p.id]) bulkDrafts[p.id].order_no = null;
    }
}

function saveBulkChanges() {
    bulkError.value = '';

    if (isSavingBulk.value) return;
    for (const p of bulkParticipants.value) {
        const value = bulkDrafts[p.id]?.chest_no;
        if (value !== null && value !== undefined && value !== '' && (!Number.isInteger(Number(value)) || Number(value) < 1)) {
            bulkError.value = `Enter a valid chest number for ${p.name}.`;
            return;
        }
    }

    // Check for duplicate chest numbers in drafts
    const chests = Object.values(bulkDrafts)
        .map((d) => d.chest_no)
        .filter((c) => c !== null && c !== undefined && c !== '');
    const chestCounts = {};
    for (const c of chests) {
        chestCounts[c] = (chestCounts[c] || 0) + 1;
        if (chestCounts[c] > 1) {
            bulkError.value = `Duplicate chest number #${c} detected in your changes.`;
            return;
        }
    }

    // Check for duplicate order numbers in drafts
    const orders = (missingOnly.value ? [] : Object.values(bulkDrafts))
        .map((d) => d.order_no)
        .filter((o) => o !== null && o !== undefined && o !== '');
    const orderCounts = {};
    for (const o of orders) {
        orderCounts[o] = (orderCounts[o] || 0) + 1;
        if (orderCounts[o] > 1) {
            bulkError.value = `Duplicate order number ${o} detected in your changes.`;
            return;
        }
    }

    const updates = bulkParticipants.value.filter((p) => !missingOnly.value || bulkDrafts[p.id]?.chest_no).map((p) => ({
        id: p.id,
        chest_no: bulkDrafts[p.id]?.chest_no ? Number(bulkDrafts[p.id].chest_no) : null,
        order_no: bulkDrafts[p.id]?.order_no ? Number(bulkDrafts[p.id].order_no) : null,
    }));

    if (!updates.length) {
        bulkError.value = 'Enter at least one chest number before saving.';
        return;
    }
    isSavingBulk.value = true;
    router.post(
        `${base.value}/bulk-update`,
        {
            item_id: props.selectedItemId,
            updates,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isBulkEditing.value = false;
                isSavingBulk.value = false;
            },
            onFinish: () => { isSavingBulk.value = false; },
            onError: (errors) => {
                isSavingBulk.value = false;
                bulkError.value = errors.bulk || Object.values(errors)[0] || 'Could not save bulk changes.';
            },
        }
    );
}

// Per-item / bulk "starting number" popups — a quicker alternative to the full table
// on Settings > Numbering (still there, unchanged), reusing that exact same endpoint
// (FestEventSettingsController::updateItemNumbering) so both stay in sync.
const showItemNumberingModal = ref(false);
const itemNumberingForm = reactive({ chest_no_start: null });
const itemNumberingTitle = ref('');
let itemNumberingItemId = null;
// Kept but not shown in the form — updateItemNumbering() writes both fields together
// per row, so this item's own item-reg start has to be resent unchanged or it'd be
// nulled out by a chest-only save.
let itemNumberingItemRegStart = null;

function openItemNumberingModal(item) {
    if (!item?.id) return;
    itemNumberingItemId = item.id;
    itemNumberingTitle.value = item.title ?? '';
    itemNumberingForm.chest_no_start = item.chest_no_start ?? null;
    itemNumberingItemRegStart = item.item_reg_id_start ?? null;
    showItemNumberingModal.value = true;
}

function saveItemNumbering() {
    router.put(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/item-numbering`, {
        items: [{
            id: itemNumberingItemId,
            chest_no_start: itemNumberingForm.chest_no_start || null,
            item_reg_id_start: itemNumberingItemRegStart,
        }],
    }, {
        preserveScroll: true,
        onSuccess: () => { showItemNumberingModal.value = false; },
    });
}

const showBulkNumberingModal = ref(false);
const bulkChestStart = ref(null);

function openBulkNumberingModal() {
    bulkChestStart.value = null;
    showBulkNumberingModal.value = true;
}

// Every item keeps its own item_reg_id_start untouched — only chest_no_start gets the
// common value, since updateItemNumbering() overwrites both fields together per row.
function saveBulkNumbering() {
    if (!bulkChestStart.value) return;

    const items = props.headItemGroups
        .flatMap((g) => g.items ?? [])
        .map((it) => ({
            id: it.id,
            chest_no_start: bulkChestStart.value,
            item_reg_id_start: it.item_reg_id_start ?? null,
        }));

    if (!items.length) return;

    router.put(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/item-numbering`, { items }, {
        preserveScroll: true,
        onSuccess: () => { showBulkNumberingModal.value = false; },
    });
}

function togglePending(e) {
    if (!props.selectedItemId) return;
    const params = { item_id: props.selectedItemId, include_pending: e.target.checked ? 1 : undefined };
    if (props.selectedHeadId != null) params.head_id = props.selectedHeadId;
    router.get(base.value, params, { preserveState: true, preserveScroll: true });
}

// Per-School Chest Number Starting Ranges Modal
const showSchoolRangesModal = ref(false);
const isSavingSchoolRanges = ref(false);
const schoolRangeRows = ref([]);
const quickDistributeStart = ref(100);
const quickDistributeBlock = ref(50);

function openSchoolRangesModal() {
    schoolRangeRows.value = (props.schools ?? []).map((s) => {
        const existing = props.schoolChestRanges?.[s.id] ?? {};
        return {
            school_id: s.id,
            school_name: s.name,
            chest_no_start: existing.chest_no_start ?? '',
            chest_no_end: existing.chest_no_end ?? '',
        };
    });
    showSchoolRangesModal.value = true;
}

function applyQuickDistribute() {
    let current = Number(quickDistributeStart.value) || 100;
    const block = Number(quickDistributeBlock.value) || 50;
    for (const row of schoolRangeRows.value) {
        row.chest_no_start = current;
        row.chest_no_end = current + block - 1;
        current += block;
    }
}

function clearSchoolRanges() {
    for (const row of schoolRangeRows.value) {
        row.chest_no_start = '';
        row.chest_no_end = '';
    }
}

function saveSchoolRanges() {
    isSavingSchoolRanges.value = true;
    router.put(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/school-chest-ranges`,
        { schools: schoolRangeRows.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showSchoolRangesModal.value = false;
                isSavingSchoolRanges.value = false;
            },
            onFinish: () => {
                isSavingSchoolRanges.value = false;
            },
        }
    );
}
</script>
