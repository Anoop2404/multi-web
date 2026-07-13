<template>
    <SahodayaEventsLayout :title="event.title" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Items`" eyebrow="Event items setup"
                    description="Enable items, add custom entries, import from master catalog.">
            <template #actions>
                <Link v-if="isSports" :href="`${base}/setup`" class="btn-secondary text-xs">Setup hub</Link>
                <Link :href="`${base}/items/list`" class="btn-secondary text-xs">Item listing</Link>
                <Link :href="catalogUrl" class="btn-secondary text-xs">Assign from catalog</Link>
            </template>
        </PageHeader>

        <SportsSetupSubNav v-if="isSports" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="items" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="items" />

        <div class="space-y-5">
                <div class="form-section">
                    <div class="form-section-head">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="form-section-title">Event items</h3>
                                <p class="form-section-hint">
                                    Create or edit items under their {{ isSports ? 'Event Heads' : 'item heads' }}. Manage dropdown masters in
                                    <Link :href="taxonomyMastersUrl" class="link-brand">Item category masters →</Link>
                                    or import from
                                    <Link :href="catalogUrl" class="link-brand">Items & fees catalog →</Link>
                                </p>
                                <p v-if="selectedHeadLabel" class="mt-2 text-xs font-semibold text-emerald-700">
                                    Showing items under: {{ selectedHeadLabel }}
                                    <button type="button" class="ml-2 underline" @click="setHeadFilter('')">Show all heads</button>
                                </p>
                            </div>
                            <button v-if="catalogSummary?.enabled" type="button" @click="importCatalog" class="btn-secondary text-xs">
                                Import enabled ({{ catalogSummary.enabled }})
                            </button>
                        </div>
                    </div>
                    <div class="form-section-body">
                        <form @submit.prevent="addItem" class="grid sm:grid-cols-2 gap-3 mb-5">
                            <div v-if="isSports && itemHeads.length" class="sm:col-span-2 rounded-xl border border-indigo-100 bg-indigo-50/60 p-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-bold uppercase tracking-wide text-indigo-700">Event Head</span>
                                    <button type="button"
                                            class="text-xs px-2.5 py-1 rounded-full border transition-colors"
                                            :class="!selectedHeadFilter ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-indigo-700 border-indigo-200'"
                                            @click="setHeadFilter('')">
                                        All
                                    </button>
                                    <button v-for="h in itemHeads" :key="h.id" type="button"
                                            class="text-xs px-2.5 py-1 rounded-full border transition-colors"
                                            :class="String(selectedHeadFilter) === String(h.id) ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-indigo-700 border-indigo-200'"
                                            @click="setHeadFilter(h.id)">
                                        {{ h.name }}
                                    </button>
                                    <button type="button"
                                            class="text-xs px-2.5 py-1 rounded-full border transition-colors"
                                            :class="selectedHeadFilter === 'other' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-indigo-700 border-indigo-200'"
                                            @click="setHeadFilter('other')">
                                        Unassigned
                                    </button>
                                </div>
                                <p class="mt-2 text-xs text-indigo-800/80">
                                    Selecting a head filters the list and preselects the head when adding a new item.
                                </p>
                            </div>
                            <FormField label="Item name" class-extra="sm:col-span-2">
                                <input v-model="itemForm.title" class="field" placeholder="Item name" required>
                            </FormField>
                            <FormField v-if="isArts" label="Stage type">
                                <select v-model="itemForm.stage_type" class="field">
                                    <option value="">Stage type</option>
                                    <option value="on_stage">On Stage</option>
                                    <option value="off_stage">Off Stage</option>
                                </select>
                            </FormField>
                            <FormField v-if="!isSports" label="Category">
                                <select v-model="itemForm.category" class="field">
                                    <option value="">Category</option>
                                    <option v-for="(label, key) in taxonomy.arts_category" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </FormField>
                            <template v-if="isSports">
                                <FormField v-if="itemHeads.length" label="Event Head">
                                    <select v-model="itemForm.head_id" class="field">
                                        <option value="">None</option>
                                        <option v-for="h in itemHeads" :key="h.id" :value="h.id">{{ h.name }}</option>
                                    </select>
                                </FormField>
                                <FormField label="Venue type">
                                    <select v-model="itemForm.venue_type" class="field">
                                        <option value="">Venue type</option>
                                        <option v-for="(label, key) in taxonomy.venue_type" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </FormField>
                                <FormField label="Format">
                                    <select v-model="itemForm.competition_format" class="field">
                                        <option value="">Format</option>
                                        <option v-for="(label, key) in taxonomy.competition_format" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </FormField>
                                <FormField label="Discipline">
                                    <select v-model="itemForm.sport_discipline" class="field">
                                        <option value="">Discipline</option>
                                        <option v-for="(label, key) in taxonomy.sport_discipline" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </FormField>
                            </template>
                            <FormField v-if="isSports" label="Age group">
                                <select v-model="itemForm.age_group" class="field">
                                    <option value="">Age group</option>
                                    <option v-for="(label, key) in taxonomy.age_group" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </FormField>
                            <FormField v-else-if="event.event_type === 'kids_fest'" label="Kids Fest band">
                                <select v-model="itemForm.kids_band" class="field">
                                    <option value="">Kids Fest band</option>
                                    <option v-for="(label, key) in taxonomy.kids_band" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </FormField>
                            <FormField v-else label="Class category">
                                <select v-model="itemForm.class_group" class="field">
                                    <option value="">Class category</option>
                                    <option v-for="(label, key) in taxonomy.class_group" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </FormField>
                            <FormField label="Gender">
                                <select v-model="itemForm.gender" class="field">
                                    <option value="open">Open</option>
                                    <option v-for="(label, key) in taxonomy.gender" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </FormField>
                            <FormField label="Participant type">
                                <select v-model="itemForm.participant_type" class="field">
                                    <option v-for="(label, key) in taxonomy.participant_type" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </FormField>
                            <FormField label="Result method">
                                <select v-model="itemForm.result_method" class="field">
                                    <option value="">Default</option>
                                    <option v-for="(label, key) in (taxonomy.result_method || {})" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </FormField>
                            <FormField v-if="!isSports && competitionAreas.length" label="Competition area">
                                <select v-model="itemForm.area_id" class="field">
                                    <option value="">None</option>
                                    <option v-for="a in competitionAreas" :key="a.id" :value="a.id">{{ a.name }}</option>
                                </select>
                            </FormField>
                            <FormField label="Tie-break on promote" hint="When ranks tie at the qualifier cutoff">
                                <select v-model="itemForm.tiebreak_mode" class="field">
                                    <option v-for="(label, key) in tiebreakModes" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </FormField>
                            <FormField label="Fee override (₹)" class-extra="sm:col-span-2" hint="Optional per-item fee">
                                <input v-model.number="itemForm.fee_amount" type="number" min="0" class="field" placeholder="Leave blank for default">
                            </FormField>
                            <FormField v-if="isSports" label="Free quota" class-extra="sm:col-span-2">
                                <CheckboxField v-model="itemForm.quota_eligible"
                                               label="Waivable by the head's free quota (Sports composite billing)" />
                            </FormField>
                            <template v-if="['team', 'group', 'pair', 'trio'].includes(itemForm.participant_type)">
                                <p class="sm:col-span-2 form-label">Squad / roster rules</p>
                                <FormField label="Min on field"><input v-model.number="itemForm.min_playing" type="number" min="1" class="field"></FormField>
                                <FormField label="Max substitutes"><input v-model.number="itemForm.max_subs" type="number" min="0" class="field"></FormField>
                                <FormField label="Max squad"><input v-model.number="itemForm.max_squad" type="number" min="1" class="field"></FormField>
                                <FormField label="Min to register"><input v-model.number="itemForm.min_squad" type="number" min="1" class="field"></FormField>
                                <FormField label="Standbys" class-extra="sm:col-span-2" hint="No fee or certificate"><input v-model.number="itemForm.standbys" type="number" min="0" class="field"></FormField>
                            </template>
                            <div class="sm:col-span-2">
                                <button type="submit" class="btn-primary w-full sm:w-auto">Add Sahodaya item</button>
                            </div>
                        </form>

                        <div v-if="flatItemCount" class="sticky top-0 z-10 -mx-1 px-1 py-2 mb-3 bg-white/95 backdrop-blur border-b border-slate-100">
                            <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 px-3 py-2.5 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <input v-model="searchQuery" type="search"
                                           class="field field--sm flex-1 min-w-[8rem] max-w-sm !py-1.5 !text-sm"
                                           placeholder="Search items…" autocomplete="off">
                                    <span class="text-xs text-slate-500 whitespace-nowrap tabular-nums">
                                        <template v-if="hasActiveFilters">{{ filteredItemCount }} / {{ flatItemCount }}</template>
                                        <template v-else>{{ flatItemCount }} items</template>
                                    </span>
                                    <button v-if="hasActiveFilters" type="button"
                                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 shrink-0"
                                            @click="clearFilters">
                                        Clear
                                    </button>
                                </div>

                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                    <div v-if="isSports && ageGroupOptions.length" class="flex flex-wrap items-center gap-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400 shrink-0">Age</span>
                                        <button type="button"
                                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                                :class="!filterAgeGroup
                                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                                @click="filterAgeGroup = ''">All</button>
                                        <button v-for="opt in ageGroupOptions" :key="opt.key" type="button"
                                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                                :class="filterAgeGroup === opt.key
                                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                                @click="filterAgeGroup = opt.key">{{ opt.label }}</button>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400 shrink-0">Gender</span>
                                        <button type="button"
                                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                                :class="!filterGender
                                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                                @click="filterGender = ''">All</button>
                                        <button type="button"
                                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap inline-flex items-center gap-1"
                                                :class="filterGender === 'male'
                                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                                @click="filterGender = 'male'">
                                            <FestItemMetaIcons gender="male" bare class="shrink-0" />
                                            Boys
                                        </button>
                                        <button type="button"
                                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap inline-flex items-center gap-1"
                                                :class="filterGender === 'female'
                                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                                @click="filterGender = 'female'">
                                            <FestItemMetaIcons gender="female" bare class="shrink-0" />
                                            Girls
                                        </button>
                                        <button type="button"
                                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                                :class="filterGender === 'open'
                                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                                @click="filterGender = 'open'">Open</button>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400 shrink-0">Status</span>
                                        <button type="button"
                                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                                :class="!filterEnabled
                                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                                @click="filterEnabled = ''">All</button>
                                        <button type="button"
                                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                                :class="filterEnabled === 'on'
                                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                                @click="filterEnabled = 'on'">On</button>
                                        <button type="button"
                                                class="text-xs px-2.5 py-1 rounded-full border transition-colors whitespace-nowrap"
                                                :class="filterEnabled === 'off'
                                                    ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                                @click="filterEnabled = 'off'">Off</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-for="(levelItems, level) in filteredItemsByLevel" :key="level" class="mb-4">
                            <p v-if="levelItems.length" class="text-xs font-bold uppercase tracking-wide text-slate-500 bg-slate-50 px-3 py-1.5 rounded-lg mb-2">
                                {{ ownerLevelLabels[level] ?? level }}
                            </p>
                            <ul v-if="levelItems.length" class="divide-y divide-slate-100 rounded-xl border border-slate-200/80 overflow-hidden">
                                <li v-for="item in levelItems" :key="item.id"
                                    class="flex flex-wrap items-start justify-between gap-3 bg-white px-4 py-3 text-sm"
                                    :class="item.is_enabled === false ? 'opacity-60' : ''">
                                    <div class="flex gap-3 min-w-0 flex-1">
                                        <label v-if="item.owner_level !== 'state'" class="flex items-start gap-1.5 shrink-0 pt-0.5">
                                            <input type="checkbox" :checked="item.is_enabled !== false"
                                                   @change="toggleItemEnabled(item, $event.target.checked)">
                                            <span class="text-xs text-slate-500">On</span>
                                        </label>
                                        <span class="min-w-0 flex items-start gap-2">
                                            <FestItemMetaIcons :gender="item.gender" :participant-type="item.participant_type" class="mt-0.5 shrink-0" />
                                            <span>
                                            <span :class="item.is_enabled === false ? 'text-slate-400 line-through' : ''">
                                                <span v-if="item.item_code" class="font-mono text-xs text-slate-400 mr-1">{{ item.item_code }}</span>
                                                {{ item.title }}
                                            </span>
                                            <span class="text-slate-400 text-xs block mt-0.5">
                                                {{ itemTags(item) }}
                                                <span v-if="item.quota_eligible"
                                                      class="ml-1 inline-flex items-center rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 align-middle">
                                                    Free quota
                                                </span>
                                            </span>
                                            <details v-if="itemDetails(item).length" class="mt-2 group/details">
                                                <summary class="text-[11px] font-semibold text-indigo-600 cursor-pointer select-none list-none flex items-center gap-1">
                                                    <span class="group-open/details:hidden">Show details</span>
                                                    <span class="hidden group-open/details:inline">Hide details</span>
                                                </summary>
                                                <dl class="mt-1.5 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-3 gap-y-1 text-[11px] text-slate-500">
                                                    <div v-for="field in itemDetails(item)" :key="field.label" class="min-w-0">
                                                        <dt class="text-slate-400 truncate">{{ field.label }}</dt>
                                                        <dd class="font-medium text-slate-700 truncate" :title="field.value">{{ field.value }}</dd>
                                                    </div>
                                                </dl>
                                            </details>
                                            </span>
                                        </span>
                                    </div>
                                    <div v-if="item.owner_level !== 'state'" class="flex gap-1 shrink-0">
                                        <button type="button" @click="openEditItem(item)" class="btn-ghost text-xs text-indigo-600">Edit</button>
                                        <button type="button" @click="removeItem(item.id)" class="btn-ghost text-xs text-red-600">Remove</button>
                                    </div>
                                    <span v-else class="text-xs text-amber-600 shrink-0">State</span>
                                </li>
                            </ul>
                        </div>

                        <EmptyState v-if="!flatItemCount" title="No items yet" :description="`Enable items in the master catalog, then import them into this event.`" icon="📋" />
                        <EmptyState v-else-if="hasActiveFilters && !filteredItemCount" title="No matches"
                                    description="No items match your filters. Try clearing or adjusting them." icon="🔍" class="py-8" />
                    </div>
                </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />

        <div v-if="editingItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="editingItem = null">
            <form @submit.prevent="saveEditItem" class="card w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-xl space-y-4">
                <h3 class="section-title">Edit item</h3>
                <FormGrid>
                    <FormField label="Item name" class-extra="sm:col-span-2">
                        <input v-model="editForm.title" class="field" required>
                    </FormField>
                    <FormField label="Enabled for this event">
                        <CheckboxField v-model="editForm.is_enabled" label="Schools can register for this item" />
                    </FormField>
                    <template v-if="isSports">
                        <FormField v-if="itemHeads.length" label="Event Head">
                            <select v-model="editForm.head_id" class="field">
                                <option value="">None</option>
                                <option v-for="h in itemHeads" :key="h.id" :value="h.id">{{ h.name }}</option>
                            </select>
                        </FormField>
                        <FormField label="Age group">
                            <select v-model="editForm.age_group" class="field">
                                <option value="">Age group</option>
                                <option v-for="(label, key) in taxonomy.age_group" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Gender">
                            <select v-model="editForm.gender" class="field">
                                <option value="open">Open</option>
                                <option v-for="(label, key) in taxonomy.gender" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Venue type">
                            <select v-model="editForm.venue_type" class="field">
                                <option value="">Venue type</option>
                                <option v-for="(label, key) in taxonomy.venue_type" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Format">
                            <select v-model="editForm.competition_format" class="field">
                                <option value="">Format</option>
                                <option v-for="(label, key) in taxonomy.competition_format" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Discipline">
                            <select v-model="editForm.sport_discipline" class="field">
                                <option value="">Discipline</option>
                                <option v-for="(label, key) in taxonomy.sport_discipline" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                    </template>
                    <FormField v-else-if="event.event_type === 'kids_fest'" label="Kids Fest band">
                        <select v-model="editForm.kids_band" class="field">
                            <option value="">Kids Fest band</option>
                            <option v-for="(label, key) in taxonomy.kids_band" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField v-else label="Class category">
                        <select v-model="editForm.class_group" class="field">
                            <option value="">Class category</option>
                            <option v-for="(label, key) in taxonomy.class_group" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField v-if="!isSports" label="Category">
                        <select v-model="editForm.category" class="field">
                            <option value="">Category</option>
                            <option v-for="(label, key) in taxonomy.arts_category" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField label="Participant type">
                        <select v-model="editForm.participant_type" class="field">
                            <option v-for="(label, key) in taxonomy.participant_type" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField label="Result method">
                        <select v-model="editForm.result_method" class="field">
                            <option value="">Default</option>
                            <option v-for="(label, key) in (taxonomy.result_method || {})" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField v-if="!isSports && competitionAreas.length" label="Competition area">
                        <select v-model="editForm.area_id" class="field">
                            <option value="">None</option>
                            <option v-for="a in competitionAreas" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </FormField>
                    <template v-if="['team', 'group', 'pair', 'trio'].includes(editForm.participant_type)">
                        <p class="sm:col-span-2 form-label">Squad / roster rules</p>
                        <FormField label="Min on field">
                            <input v-model.number="editForm.min_playing" type="number" min="1" class="field">
                        </FormField>
                        <FormField label="Max substitutes">
                            <input v-model.number="editForm.max_subs" type="number" min="0" class="field">
                        </FormField>
                        <FormField label="Max squad">
                            <input v-model.number="editForm.max_squad" type="number" min="1" class="field">
                        </FormField>
                        <FormField label="Min to register">
                            <input v-model.number="editForm.min_squad" type="number" min="1" class="field">
                        </FormField>
                        <FormField label="Standbys" class-extra="sm:col-span-2" hint="No fee or certificate">
                            <input v-model.number="editForm.standbys" type="number" min="0" class="field">
                        </FormField>
                        <p class="sm:col-span-2 text-xs text-slate-500">For simple group items (e.g. group dance), use member count instead:</p>
                        <FormField label="Min members">
                            <input v-model.number="editForm.min_group_size" type="number" min="1" class="field">
                        </FormField>
                        <FormField label="Max members">
                            <input v-model.number="editForm.max_group_size" type="number" min="1" class="field">
                        </FormField>
                    </template>
                    <FormField label="Qualifiers to next level">
                        <input v-model.number="editForm.qualify_count" type="number" min="1" class="field">
                    </FormField>
                    <FormField label="Tie-break on promote" hint="Applied when ranks tie at the cutoff">
                        <select v-model="editForm.tiebreak_mode" class="field">
                            <option v-for="(label, key) in tiebreakModes" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField label="Max per school">
                        <input v-model.number="editForm.max_per_school" type="number" min="1" class="field">
                    </FormField>
                    <FormField label="Fee override (₹)" class-extra="sm:col-span-2">
                        <input v-model.number="editForm.fee_amount" type="number" min="0" class="field" placeholder="Leave blank for default">
                    </FormField>
                    <FormField v-if="isSports" label="Free quota" class-extra="sm:col-span-2">
                        <CheckboxField v-model="editForm.quota_eligible"
                                       label="Waivable by the head's free quota (Sports composite billing)" />
                    </FormField>
                </FormGrid>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="editingItem = null" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="editForm.processing">Save changes</button>
                </div>
            </form>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import FestItemMetaIcons from '@/Components/sahodaya/FestItemMetaIcons.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import { festItemListingDetails, festItemSearchHaystack, festItemTagsLine } from '@/support/festItemListingMeta.js';
import { normalizeFestItemGender } from '@/support/festItemEligibility.js';

const SPORTS_AGE_ORDER = ['u8', 'u10', 'u11', 'u12', 'u14', 'u17', 'u19', 'open'];

const tiebreakModes = {
    none: 'Top N by position (default)',
    include_all_ties: 'Include all tied at cutoff',
    exclude_ties: 'Skip contested ranks that overflow',
    lot_draw: 'Lot draw among ties at cutoff',
    manual: 'Block promote until resolved manually',
};

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, groupedItems: Object, taxonomy: Object,
    itemHeads: { type: Array, default: () => [] },
    competitionAreas: { type: Array, default: () => [] },
    taxonomyMastersUrl: String,
    catalogSummary: Object, catalogUrl: String,
    levelLabels: Object, itemsByLevel: Object, ownerLevelLabels: Object,
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const page = usePage();
const isArts = computed(() => ['kalolsavam', 'kids_fest'].includes(props.event.event_type));
const isSports = computed(() => props.event.event_type === 'sports');
const searchQuery = ref('');
const filterAgeGroup = ref('');
const filterGender = ref('');
const filterEnabled = ref('');
const selectedHeadFilter = ref(initialHeadFilter());

const flatItemCount = computed(() => Object.values(props.itemsByLevel ?? {}).flat().length);

const ageGroupOptions = computed(() => {
    if (!isSports.value) return [];
    const keys = new Set();
    for (const items of Object.values(props.itemsByLevel ?? {})) {
        for (const item of items) {
            if (item.age_group) keys.add(item.age_group);
        }
    }
    return [...keys]
        .sort((a, b) => {
            const ai = SPORTS_AGE_ORDER.indexOf(String(a).toLowerCase());
            const bi = SPORTS_AGE_ORDER.indexOf(String(b).toLowerCase());
            return (ai < 0 ? 99 : ai) - (bi < 0 ? 99 : bi);
        })
        .map((key) => ({
            key,
            label: props.taxonomy?.age_group?.[key] ?? String(key).toUpperCase(),
        }));
});

const hasActiveFilters = computed(() =>
    Boolean(searchQuery.value.trim() || filterAgeGroup.value || filterGender.value || filterEnabled.value || selectedHeadFilter.value)
);

const selectedHeadLabel = computed(() => {
    if (!selectedHeadFilter.value) return '';
    if (selectedHeadFilter.value === 'other') return 'Unassigned items';
    return props.itemHeads.find((h) => String(h.id) === String(selectedHeadFilter.value))?.name ?? '';
});

function itemMetaOptions() {
    return { taxonomy: props.taxonomy, eventType: props.event.event_type };
}

function itemDetails(item) {
    return festItemListingDetails(item, itemMetaOptions());
}

function itemTags(item) {
    const line = festItemTagsLine(item, itemMetaOptions());
    if (item.is_enabled === false) {
        return `${line} · Disabled for this event`;
    }
    return line;
}

function itemMatchesSearch(item, q) {
    const haystack = festItemSearchHaystack(item, itemMetaOptions());
    const terms = q.split(/\s+/).filter(Boolean);
    return terms.every((term) => haystack.includes(term));
}

function itemMatchesFilters(item) {
    const q = searchQuery.value.trim().toLowerCase();
    if (q && !itemMatchesSearch(item, q)) return false;
    if (selectedHeadFilter.value === 'other' && item.head_id) return false;
    if (selectedHeadFilter.value && selectedHeadFilter.value !== 'other' && String(item.head_id ?? '') !== String(selectedHeadFilter.value)) return false;
    if (filterAgeGroup.value && item.age_group !== filterAgeGroup.value) return false;
    if (filterGender.value && normalizeFestItemGender(item.gender) !== filterGender.value) return false;
    if (filterEnabled.value === 'on' && item.is_enabled === false) return false;
    if (filterEnabled.value === 'off' && item.is_enabled !== false) return false;
    return true;
}

const filteredItemsByLevel = computed(() => {
    const source = props.itemsByLevel ?? {};
    if (!hasActiveFilters.value) return source;
    const out = {};
    for (const [level, items] of Object.entries(source)) {
        const filtered = items.filter(itemMatchesFilters);
        if (filtered.length) out[level] = filtered;
    }
    return out;
});

const filteredItemCount = computed(() => Object.values(filteredItemsByLevel.value).flat().length);

function clearFilters() {
    searchQuery.value = '';
    filterAgeGroup.value = '';
    filterGender.value = '';
    filterEnabled.value = '';
    setHeadFilter('');
}

const itemForm = useForm({
    title: '', participant_type: 'individual', result_method: '', stage_type: '', venue_type: '', head_id: selectedHeadFilter.value === 'other' ? '' : selectedHeadFilter.value,
    competition_format: '', sport_discipline: '', class_group: '', age_group: '', kids_band: '', gender: 'open',
    category: '', area_id: '', tiebreak_mode: 'none',
    min_playing: null, max_subs: null, max_squad: null, min_squad: null, standbys: null,
    fee_amount: null, quota_eligible: false,
});
const editingItem = ref(null);
const editForm = useForm({
    title: '', is_enabled: true, gender: 'open', class_group: '', age_group: '', kids_band: '',
    venue_type: '', sport_discipline: '', competition_format: '', participant_type: 'individual', result_method: '', head_id: '',
    category: '', area_id: '', tiebreak_mode: 'none',
    qualify_count: null, max_per_school: null, fee_amount: null, quota_eligible: false,
    min_playing: null, max_subs: null, max_squad: null, min_squad: null, standbys: null,
    min_group_size: null, max_group_size: null,
});

function addItem() {
    itemForm.post(`${base}/items`, {
        preserveScroll: true,
        onSuccess: () => {
            itemForm.title = '';
            itemForm.participant_type = 'individual';
            itemForm.stage_type = '';
            itemForm.venue_type = '';
            itemForm.head_id = selectedHeadFilter.value === 'other' ? '' : selectedHeadFilter.value;
            itemForm.competition_format = '';
            itemForm.sport_discipline = '';
            itemForm.class_group = '';
            itemForm.age_group = '';
            itemForm.kids_band = '';
            itemForm.gender = 'open';
            itemForm.category = '';
            itemForm.area_id = '';
            itemForm.tiebreak_mode = 'none';
            itemForm.min_playing = null;
            itemForm.max_subs = null;
            itemForm.max_squad = null;
            itemForm.min_squad = null;
            itemForm.standbys = null;
            itemForm.fee_amount = null;
            itemForm.quota_eligible = false;
        },
    });
}

function initialHeadFilter() {
    const query = String(page.url ?? '').split('?')[1] ?? '';
    const value = new URLSearchParams(query).get('head_id') ?? '';
    return value === 'other' ? 'other' : value;
}

function setHeadFilter(value) {
    selectedHeadFilter.value = value === null || value === undefined ? '' : String(value);
    if (selectedHeadFilter.value !== 'other') {
        itemForm.head_id = selectedHeadFilter.value;
    } else {
        itemForm.head_id = '';
    }
}
function importCatalog() {
    if (!confirm(`Import ${props.catalogSummary?.enabled ?? 0} enabled item(s) from your Sahodaya catalog?`)) return;
    router.post(`${base}/items/import-catalog`, {}, { preserveScroll: true });
}
function removeItem(id) {
    router.delete(`${base}/items/${id}`, { preserveScroll: true });
}
function openEditItem(item) {
    const c = item.criteria_json ?? {};
    editingItem.value = item;
    editForm.clearErrors();
    editForm.title = item.title;
    editForm.is_enabled = item.is_enabled !== false;
    editForm.gender = item.gender ?? 'open';
    editForm.class_group = item.class_group ?? '';
    editForm.age_group = item.age_group ?? '';
    editForm.kids_band = item.kids_band ?? '';
    editForm.venue_type = item.venue_type ?? '';
    editForm.sport_discipline = item.sport_discipline ?? '';
    editForm.competition_format = item.competition_format ?? '';
    editForm.category = item.category ?? '';
    editForm.head_id = item.head_id ?? '';
    editForm.area_id = item.area_id ?? '';
    editForm.participant_type = item.participant_type ?? 'individual';
    editForm.result_method = item.result_method ?? '';
    editForm.qualify_count = item.qualify_count ?? null;
    editForm.tiebreak_mode = item.tiebreak_mode || 'none';
    editForm.max_per_school = item.max_per_school ?? null;
    editForm.fee_amount = item.fee_amount ?? null;
    editForm.quota_eligible = item.quota_eligible ?? false;
    editForm.min_playing = c.min_playing ?? null;
    editForm.max_subs = c.max_subs ?? null;
    editForm.max_squad = c.max_squad ?? item.max_group_size ?? null;
    editForm.min_squad = c.min_squad ?? item.min_group_size ?? null;
    editForm.standbys = c.standbys ?? null;
    editForm.min_group_size = item.min_group_size ?? null;
    editForm.max_group_size = item.max_group_size ?? null;
}
function saveEditItem() {
    editForm.put(`${base}/items/${editingItem.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editingItem.value = null; },
    });
}
function toggleItemEnabled(item, enabled) {
    router.put(`${base}/items/${item.id}`, {
        title: item.title,
        is_enabled: enabled,
        gender: item.gender,
        class_group: item.class_group,
        age_group: item.age_group,
        kids_band: item.kids_band,
        qualify_count: item.qualify_count,
        max_per_school: item.max_per_school,
        fee_amount: item.fee_amount,
    }, { preserveScroll: true, preserveState: true });
}
</script>
