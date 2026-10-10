<template>
    <SahodayaEventsLayout :title="`${event.title} — Individual Championship`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Individual Championship`" eyebrow="Scoring &amp; Honours"
                    :description="event.event_type === 'sports'
                        ? 'Individual championship points, Best Athlete honours, and builder rules.'
                        : 'Individual championship points, crowned honours (Kalaprathibha &amp; Kalathilakam), and builder rules.'" />
        <SportsSetupSubNav v-if="event.event_type === 'sports'" :sahodaya-id="sahodaya.id" :event-id="event.id" :event="event" active="championship" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="championship" class="mb-4" />
        <FestEventWorkflowStepper :sahodaya-id="sahodaya.id" :event-id="event.id"
                                  :event-type="event.event_type" :current-step="'operations'" />

        <!-- Actions & Sync Bar -->
        <div class="mt-4 p-4 rounded-xl border bg-gradient-to-r from-amber-500/10 via-indigo-500/10 to-transparent border-amber-200/80 flex flex-wrap items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-amber-500 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                    👑
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">
                        {{ event.event_type === 'sports' ? 'Sports Individual Championship & Honours' : 'Individual Championship Honours' }}
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Title honours ({{ championshipConfig.male_title }} / {{ championshipConfig.female_title }}) auto-calculated from published marks.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-secondary text-xs flex items-center gap-1.5" @click="showBuilderConfig = !showBuilderConfig">
                    <span>⚙️</span> {{ showBuilderConfig ? 'Hide Builder Rules' : 'Configure Builder Rules' }}
                </button>
                <button type="button" class="btn-primary text-xs bg-amber-600 hover:bg-amber-700 border-amber-600 flex items-center gap-1.5"
                        @click="syncToTrophies">
                    <span>🏆</span> Sync Champions to Trophy Distribution
                </button>
                <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/trophies`" class="btn-secondary text-xs">
                    View Trophy Distribution →
                </a>
            </div>
        </div>

        <!-- Disabled notice -->
        <div v-if="championshipConfig.disabled" class="mt-4 p-4 rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm">
            ⚠️ Individual Championship is <strong>disabled</strong> for this event. No championship rankings or title honours will be computed or shown.
        </div>

        <!-- Top Honours Podium / Crowned Champions Cards -->
        <div v-if="championsSummary && (championsSummary.category_champions?.length || championsSummary.overall_male_champion || championsSummary.overall_champion)" class="mt-6 mb-6">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                <span>🏅</span> Crowned Champions ({{ championshipConfig.male_title }} &amp; {{ championshipConfig.female_title }})
            </h3>

            <!-- Overall Champions (if any) -->
            <div v-if="championsSummary.overall_male_champion || championsSummary.overall_female_champion || championsSummary.overall_champion"
                 class="grid gap-4 mb-4"
                 :class="championshipConfig.group_by_gender ? 'grid-cols-1 md:grid-cols-2' : 'grid-cols-1'">
                <div v-if="championsSummary.overall_male_champion"
                     class="card !p-4 bg-gradient-to-br from-indigo-900 via-slate-900 to-indigo-950 text-white border-0 shadow-md relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 font-black">👑</div>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <img v-if="championsSummary.overall_male_champion.student.photo"
                                 :src="championsSummary.overall_male_champion.student.photo"
                                 class="w-14 h-14 rounded-full object-cover border-2 border-amber-400">
                            <div v-else class="w-14 h-14 rounded-full bg-indigo-700 flex items-center justify-center font-bold text-lg border-2 border-amber-400">
                                {{ championsSummary.overall_male_champion.student.name.charAt(0) }}
                            </div>
                            <span class="absolute -top-1 -right-1 text-xs">👑</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-400 text-slate-950 tracking-wider">
                                Overall {{ championshipConfig.male_title }}
                            </span>
                            <h4 class="text-sm font-bold truncate mt-1">{{ championsSummary.overall_male_champion.student.name }}</h4>
                            <p class="text-xs text-slate-300 truncate">{{ championsSummary.overall_male_champion.school }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-xl font-black text-amber-300 font-mono">{{ championsSummary.overall_male_champion.points }}</div>
                            <div class="text-[10px] text-slate-400 uppercase tracking-wide">Points</div>
                        </div>
                    </div>
                </div>

                <div v-if="championsSummary.overall_female_champion"
                     class="card !p-4 bg-gradient-to-br from-rose-900 via-slate-900 to-rose-950 text-white border-0 shadow-md relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 font-black">👑</div>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <img v-if="championsSummary.overall_female_champion.student.photo"
                                 :src="championsSummary.overall_female_champion.student.photo"
                                 class="w-14 h-14 rounded-full object-cover border-2 border-amber-400">
                            <div v-else class="w-14 h-14 rounded-full bg-rose-700 flex items-center justify-center font-bold text-lg border-2 border-amber-400">
                                {{ championsSummary.overall_female_champion.student.name.charAt(0) }}
                            </div>
                            <span class="absolute -top-1 -right-1 text-xs">👑</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-400 text-slate-950 tracking-wider">
                                Overall {{ championshipConfig.female_title }}
                            </span>
                            <h4 class="text-sm font-bold truncate mt-1">{{ championsSummary.overall_female_champion.student.name }}</h4>
                            <p class="text-xs text-slate-300 truncate">{{ championsSummary.overall_female_champion.school }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-xl font-black text-amber-300 font-mono">{{ championsSummary.overall_female_champion.points }}</div>
                            <div class="text-[10px] text-slate-400 uppercase tracking-wide">Points</div>
                        </div>
                    </div>
                </div>

                <!-- Unified Overall Champion (genderless mode) -->
                <div v-if="championsSummary.overall_champion"
                     class="card !p-4 bg-gradient-to-br from-indigo-900 via-slate-900 to-indigo-950 text-white border-0 shadow-md relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 font-black">👑</div>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <img v-if="championsSummary.overall_champion.student.photo"
                                 :src="championsSummary.overall_champion.student.photo"
                                 class="w-14 h-14 rounded-full object-cover border-2 border-amber-400">
                            <div v-else class="w-14 h-14 rounded-full bg-indigo-700 flex items-center justify-center font-bold text-lg border-2 border-amber-400">
                                {{ championsSummary.overall_champion.student.name.charAt(0) }}
                            </div>
                            <span class="absolute -top-1 -right-1 text-xs">👑</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-400 text-slate-950 tracking-wider">
                                Overall {{ championshipConfig.male_title }}
                            </span>
                            <h4 class="text-sm font-bold truncate mt-1">{{ championsSummary.overall_champion.student.name }}</h4>
                            <p class="text-xs text-slate-300 truncate">{{ championsSummary.overall_champion.school }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-xl font-black text-amber-300 font-mono">{{ championsSummary.overall_champion.points }}</div>
                            <div class="text-[10px] text-slate-400 uppercase tracking-wide">Points</div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Category Champions Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <div v-for="cat in championsSummary.category_champions" :key="cat.category"
                     class="card !p-3.5 bg-white border border-slate-200/80 shadow-xs hover:border-slate-300 transition-all">
                    <div class="border-b border-slate-100 pb-2 mb-2 flex items-center justify-between">
                        <span class="font-bold text-xs text-slate-800 uppercase tracking-wider">{{ cat.category_label }}</span>
                        <span class="text-[10px] text-slate-400 font-mono">Category</span>
                    </div>

                    <!-- Gender-split mode: Boy Champion / Girl Champion -->
                    <template v-if="championshipConfig.group_by_gender">
                        <div class="mb-3">
                            <div class="text-[10px] font-bold text-indigo-700 uppercase tracking-wide flex items-center gap-1 mb-1">
                                <span>★</span> {{ championshipConfig.male_title }} (Boys)
                            </div>
                            <div v-if="cat.male_champion" class="flex items-center gap-2 cursor-pointer group" @click="viewStudentBreakdown(cat.male_champion.student.id)">
                                <div class="w-8 h-8 rounded-full bg-indigo-50 border border-indigo-200 flex items-center justify-center font-bold text-xs text-indigo-800 shrink-0">
                                    {{ cat.male_champion.student.name.charAt(0) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-xs text-slate-800 truncate group-hover:text-indigo-600 transition-colors">
                                        {{ cat.male_champion.student.name }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 truncate">{{ cat.male_champion.school }}</div>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="font-bold font-mono text-xs text-slate-900">{{ cat.male_champion.points }} pts</span>
                                </div>
                            </div>
                            <div v-else class="text-[11px] text-slate-400 italic">Pending marks</div>
                        </div>

                        <div>
                            <div class="text-[10px] font-bold text-rose-700 uppercase tracking-wide flex items-center gap-1 mb-1">
                                <span>★</span> {{ championshipConfig.female_title }} (Girls)
                            </div>
                            <div v-if="cat.female_champion" class="flex items-center gap-2 cursor-pointer group" @click="viewStudentBreakdown(cat.female_champion.student.id)">
                                <div class="w-8 h-8 rounded-full bg-rose-50 border border-rose-200 flex items-center justify-center font-bold text-xs text-rose-800 shrink-0">
                                    {{ cat.female_champion.student.name.charAt(0) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-xs text-slate-800 truncate group-hover:text-rose-600 transition-colors">
                                        {{ cat.female_champion.student.name }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 truncate">{{ cat.female_champion.school }}</div>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="font-bold font-mono text-xs text-slate-900">{{ cat.female_champion.points }} pts</span>
                                </div>
                            </div>
                            <div v-else class="text-[11px] text-slate-400 italic">Pending marks</div>
                        </div>
                    </template>

                    <!-- Genderless mode: single unified champion -->
                    <template v-else>
                        <div v-if="cat.champion" class="flex items-center gap-2 cursor-pointer group" @click="viewStudentBreakdown(cat.champion.student.id)">
                            <div class="w-8 h-8 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center font-bold text-xs text-amber-800 shrink-0">
                                {{ cat.champion.student.name.charAt(0) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-xs text-slate-800 truncate group-hover:text-amber-600 transition-colors">
                                    {{ cat.champion.student.name }}
                                </div>
                                <div class="text-[10px] text-slate-400 truncate">{{ cat.champion.school }}</div>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="font-bold font-mono text-xs text-slate-900">{{ cat.champion.points }} pts</span>
                            </div>
                        </div>
                        <div v-else-if="cat.male_champion" class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-indigo-50 border border-indigo-200 flex items-center justify-center font-bold text-xs text-indigo-800 shrink-0">
                                {{ cat.male_champion.student.name.charAt(0) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-xs text-slate-800 truncate">{{ cat.male_champion.student.name }}</div>
                                <div class="text-[10px] text-slate-400 truncate">{{ cat.male_champion.school }}</div>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="font-bold font-mono text-xs text-slate-900">{{ cat.male_champion.points }} pts</span>
                            </div>
                        </div>
                        <div v-else class="text-[11px] text-slate-400 italic">Pending marks</div>

                        <div v-if="cat.runner_up" class="mt-2 flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-[10px] text-slate-600 shrink-0">
                                {{ cat.runner_up.student.name.charAt(0) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-[11px] text-slate-600 truncate">{{ cat.runner_up.student.name }}</div>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="font-mono text-[11px] text-slate-600">{{ cat.runner_up.points }} pts</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Championship Builder Configuration Panel (Collapsible) -->
        <div v-if="showBuilderConfig" class="card mb-6 border-indigo-200 bg-indigo-50/30 p-5 space-y-4 animate-in fade-in duration-200">
            <div class="flex items-center justify-between border-b border-indigo-100 pb-3">
                <div>
                    <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                        <span>⚙️</span> Individual Championship Builder Rules
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Customize titles, scoring caps (e.g. Best 3 items per student), group item weights, and qualification criteria.
                    </p>
                </div>
                <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-800" @click="showBuilderConfig = false">
                    Close
                </button>
            </div>

            <form @submit.prevent="saveBuilderConfig" class="space-y-4 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="lbl">Boys Champion Title</label>
                        <input v-model="configForm.male_title" type="text" class="fld" placeholder="Kalaprathibha" required>
                    </div>
                    <div>
                        <label class="lbl">Girls Champion Title</label>
                        <input v-model="configForm.female_title" type="text" class="fld" placeholder="Kalathilakam" required>
                    </div>
                    <div>
                        <label class="lbl">Runner-Up Title</label>
                        <input v-model="configForm.runner_up_title" type="text" class="fld" placeholder="Runner Up" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="lbl">Max Counting Items Per Student</label>
                        <select v-model.number="configForm.max_counting_items" class="fld">
                            <option :value="0">Unlimited (All items count)</option>
                            <option :value="2">Best 2 items count</option>
                            <option :value="3">Best 3 items count (Kalotsav Standard)</option>
                            <option :value="4">Best 4 items count</option>
                            <option :value="5">Best 5 items count</option>
                        </select>
                        <p class="text-[10px] text-slate-500 mt-1">If capped, only student's top N scoring items add to championship total.</p>
                    </div>
                    <div>
                        <label class="lbl">Group / Team Items Rule</label>
                        <select v-model="configForm.multi_person_mode" class="fld">
                            <option value="tie_break_only">Tie-break only (Do not add to points)</option>
                            <option value="include_weighted">Include with Weight / Percentage</option>
                            <option value="exclude">Exclude completely from championship</option>
                        </select>
                    </div>
                    <div v-if="configForm.multi_person_mode === 'include_weighted'">
                        <label class="lbl">Group Points Weight (%)</label>
                        <input v-model.number="configForm.group_weight_percent" type="number" min="1" max="100" class="fld">
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-6 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer font-medium text-red-700">
                        <input type="checkbox" v-model="configForm.disabled" class="rounded text-red-600 focus:ring-red-500">
                        <span>Disable Individual Championship entirely</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-700">
                        <input type="checkbox" v-model="configForm.group_by_gender" class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span>Separate category and overall rankings by gender (Boys &amp; Girls)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-700">
                        <input type="checkbox" v-model="configForm.must_have_first_place" class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span>Must have at least one 1st place to qualify as Champion</span>
                    </label>
                </div>

                <fieldset class="pt-3">
                    <legend class="lbl">Disable individual championship categories</legend>
                    <p class="text-xs text-slate-500 mb-2">Selected categories are excluded from category and overall individual rankings.</p>
                    <div class="flex flex-wrap gap-4">
                        <label v-for="(label, key) in championshipCategoryLabels" :key="key" class="flex items-center gap-2">
                            <input v-model="configForm.excluded_individual_categories" type="checkbox" :value="key" class="rounded text-indigo-600">
                            {{ label }}
                        </label>
                    </div>
                </fieldset>
                <div class="pt-3 border-t border-indigo-100 flex items-center justify-end gap-2">
                    <button type="submit" class="btn-primary text-xs" :disabled="configForm.processing">
                        Save Builder Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Stats cards -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-slate-800">{{ activeLeaderboard.length }}</p>
                <p class="text-xs text-slate-500 mt-1">Ranked students</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-indigo-700">{{ stats.top_points }} pts</p>
                <p class="text-xs text-slate-500 mt-1">Highest points</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ stats.total_points }} pts</p>
                <p class="text-xs text-slate-500 mt-1">Total distributed</p>
            </div>
        </div>

        <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
            <p class="text-sm text-gray-600">
                {{ scope === 'cumulative' ? 'Summed live across every phase of this hub, straight off published item results.' : 'Computed live from this phase\'s published item results — no recalculation needed, just refresh.' }}
            </p>
        </div>

        <div v-if="usesPhases" class="flex gap-2 mb-4">
            <button type="button" class="btn-secondary text-xs" :class="{ '!bg-indigo-600 !text-white': scope === 'phase' }" @click="scope = 'phase'">
                This phase
            </button>
            <button type="button" class="btn-secondary text-xs" :class="{ '!bg-indigo-600 !text-white': scope === 'cumulative' }" @click="scope = 'cumulative'">
                Cumulative — all phases
            </button>
        </div>

        <!-- Filters panel -->
        <div class="card mb-4 space-y-3">
            <div class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Filter by category</label>
                    <SearchableSelect v-model="filterCategory" class="mt-1 w-44" :options="categoryOptionsFromLeaderboard"
                                      :all-option="true" all-label="All categories" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Filter by gender</label>
                    <SearchableSelect v-model="filterGender" class="mt-1 w-36"
                                      :options="[{ value: 'male', label: 'Boys' }, { value: 'female', label: 'Girls' }]"
                                      :all-option="true" all-label="All genders" />
                </div>
            </div>
        </div>

        <!-- Category merge rules -->
        <div class="card mb-6 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="section-title !mb-0">Category merge rules</h3>
                    <p class="section-desc mt-0.5">
                        Tally two or more categories together as one bucket instead of scoring them separately — e.g. merge "Category 3" into "Open".
                        Applies to this school/team overall scoreboard for any target category; the individual leaderboard above only honors a merge whose target is LP, UP, HS, HSS, or Open.
                    </p>
                </div>
                <button type="button" class="btn-secondary text-xs whitespace-nowrap" @click="addingMergeRule = !addingMergeRule">
                    {{ addingMergeRule ? 'Cancel' : '+ Add merge rule' }}
                </button>
            </div>

            <div v-if="mergeGroups.length" class="space-y-2">
                <div v-for="(group, idx) in mergeGroups" :key="idx"
                     class="flex flex-wrap items-center gap-2 text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">
                    <span v-for="src in group.sources" :key="src" class="px-2 py-0.5 rounded-full bg-white border border-slate-200 text-xs font-medium text-slate-700">
                        {{ labelFor(src) }}
                    </span>
                    <span class="text-slate-400">→</span>
                    <span class="px-2 py-0.5 rounded-full bg-indigo-50 border border-indigo-200 text-xs font-bold text-indigo-700">{{ labelFor(group.target) }}</span>
                    <button type="button" class="ml-auto text-xs font-semibold text-rose-600" @click="removeMergeGroup(idx)">Remove</button>
                </div>
            </div>
            <p v-else class="text-xs text-slate-400">No merge rules — every category scores on its own.</p>

            <div v-if="addingMergeRule" class="border-t border-slate-200 pt-3 space-y-3">
                <div>
                    <p class="text-xs font-semibold text-gray-600 mb-1.5">Categories to merge (pick 2 or more)</p>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="opt in categoryOptions" :key="opt.value"
                                class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full border cursor-pointer"
                                :class="newGroupSources.includes(opt.value) ? 'bg-indigo-50 border-indigo-300 text-indigo-800' : 'bg-white border-slate-200 text-slate-600'">
                            <input type="checkbox" class="rounded" :value="opt.value" v-model="newGroupSources">
                            {{ opt.label }}
                        </label>
                    </div>
                </div>
                <div v-if="newGroupSources.length >= 2">
                    <p class="text-xs font-semibold text-gray-600 mb-1.5">Merge into which one?</p>
                    <SearchableSelect v-model="newGroupTarget" class="w-56"
                                      :options="categoryOptions.filter(o => newGroupSources.includes(o.value))"
                                      :all-option="false" placeholder="Choose target category" />
                </div>
                <button type="button" class="btn-primary text-xs" :disabled="newGroupSources.length < 2 || !newGroupTarget" @click="saveMergeGroup">
                    Save merge rule
                </button>
            </div>
        </div>

        <!-- Overall exclusions -->
        <div class="card mb-6 space-y-3">
            <div>
                <h3 class="section-title !mb-0">Exclude from overall school total</h3>
                <p class="section-desc mt-0.5">
                    Leave a category's points out of the combined "All Categories" school scoreboard total on the public portal.
                    The category's own scoreboard tab still works as normal — only the combined total skips it.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <label v-for="opt in categoryOptions" :key="opt.value"
                       class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full border cursor-pointer"
                       :class="excludedCategories.includes(opt.value) ? 'bg-rose-50 border-rose-300 text-rose-800' : 'bg-white border-slate-200 text-slate-600'">
                    <input type="checkbox" class="rounded" :value="opt.value" v-model="excludedCategories" @change="saveExcludedCategories">
                    {{ opt.label }}
                </label>
                <p v-if="!categoryOptions.length" class="text-xs text-slate-400">No categories found for this event yet.</p>
            </div>
        </div>

        <!-- Leaderboard Table with clickable row breakdown -->
        <div class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b"><tr>
                        <th class="p-3 text-left">Rank (category · gender)</th>
                        <th class="p-3 text-left">Student</th>
                        <th class="p-3 text-left">School</th>
                        <th class="p-3 text-left">Category</th>
                        <th class="p-3 text-right">Points</th>
                        <th class="p-3 text-right">1st Places</th>
                        <th class="p-3 text-right">Group Tiebreak</th>
                        <th class="p-3 text-right">Overall rank</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="row in filteredLeaderboard" :key="row.student.id"
                            class="border-t hover:bg-slate-50 cursor-pointer transition-colors"
                            @click="viewStudentBreakdown(row.student.id)">
                            <td class="p-3 font-bold">
                                <span v-if="row.rank === 1" class="text-amber-500 font-black mr-1">👑</span>
                                #{{ row.rank }}
                            </td>
                            <td class="p-3">
                                <span class="font-medium text-slate-800 hover:text-indigo-600">{{ row.student.name }}</span>
                                <span class="text-xs text-gray-400 font-mono ml-2">{{ row.student.reg_no }}</span>
                            </td>
                            <td class="p-3 text-slate-600">{{ row.school }}</td>
                            <td class="p-3 text-xs text-indigo-700 font-medium">{{ rowCategoryLabel(row.category) }} · <span class="uppercase">{{ row.gender }}</span></td>
                            <td class="p-3 text-right font-mono font-bold text-slate-900">{{ row.points }}</td>
                            <td class="p-3 text-right font-mono text-slate-600">{{ row.firsts || 0 }}</td>
                            <td class="p-3 text-right font-mono text-slate-400 text-xs">{{ row.group_points || 0 }}</td>
                            <td class="p-3 text-right font-mono text-xs text-slate-400">#{{ row.overall_rank }}</td>
                        </tr>
                        <tr v-if="!filteredLeaderboard.length"><td colspan="8" class="p-8 text-gray-400 text-center">No matching leaderboard entries</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Student Score Breakdown Drawer / Modal -->
        <div v-if="studentBreakdownModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-6 border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-start justify-between pb-3 border-b border-slate-100 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">{{ studentBreakdownData?.student?.name }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ studentBreakdownData?.student?.school }} · Reg: {{ studentBreakdownData?.student?.reg_no }}
                        </p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-600 text-xl font-bold" @click="studentBreakdownModal = false">×</button>
                </div>

                <div v-if="loadingBreakdown" class="py-12 text-center text-slate-400 text-xs">
                    Loading student item scores...
                </div>
                <div v-else class="space-y-4">
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                            <div class="text-base font-black text-slate-800">{{ studentBreakdownData?.total_points }}</div>
                            <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Championship Pts</div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-200">
                            <div class="text-base font-black text-amber-700">{{ studentBreakdownData?.firsts_count }}</div>
                            <div class="text-[10px] uppercase font-bold text-amber-600 tracking-wider">1st Places</div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-indigo-50 border border-indigo-200">
                            <div class="text-base font-black text-indigo-700">{{ studentBreakdownData?.group_tiebreak_points }}</div>
                            <div class="text-[10px] uppercase font-bold text-indigo-600 tracking-wider">Group Tiebreak</div>
                        </div>
                    </div>

                    <div class="border rounded-xl overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase text-[10px]">
                                <tr>
                                    <th class="p-2.5">Item</th>
                                    <th class="p-2.5">Type</th>
                                    <th class="p-2.5 text-center">Position</th>
                                    <th class="p-2.5 text-center">Grade</th>
                                    <th class="p-2.5 text-right">Points</th>
                                    <th class="p-2.5 text-center">Scoring Rule</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="it in studentBreakdownData?.items" :key="it.item_id">
                                    <td class="p-2.5">
                                        <div class="font-bold text-slate-800">{{ it.title }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ it.item_code }}</div>
                                    </td>
                                    <td class="p-2.5">
                                        <span class="px-2 py-0.5 rounded text-[10px] uppercase font-semibold"
                                              :class="it.is_multi_person ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'">
                                            {{ it.participant_type }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-center font-bold">
                                        <span v-if="it.position">#{{ it.position }}</span>
                                        <span v-else class="text-slate-300">—</span>
                                    </td>
                                    <td class="p-2.5 text-center font-mono font-bold text-slate-700">
                                        {{ it.grade || '—' }}
                                    </td>
                                    <td class="p-2.5 text-right font-mono font-bold text-slate-900">
                                        {{ it.raw_points }}
                                    </td>
                                    <td class="p-2.5 text-center">
                                        <span v-if="it.is_counted" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            Counted
                                        </span>
                                        <span v-else-if="it.is_tie_break" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-100 text-indigo-700">
                                            Tiebreak
                                        </span>
                                        <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-500">
                                            Exceeded Cap
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!studentBreakdownData?.items?.length">
                                    <td colspan="6" class="p-6 text-center text-slate-400">No marks recorded yet for this student.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import FestEventWorkflowStepper from '@/Components/sahodaya/FestEventWorkflowStepper.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    leaderboard: Array,
    championsSummary: { type: Object, default: () => ({}) },
    championshipConfig: { type: Object, default: () => ({ male_title: 'Kalaprathibha', female_title: 'Kalathilakam', runner_up_title: 'Runner Up', disabled: false, max_counting_items: 0, multi_person_mode: 'tie_break_only', group_weight_percent: 100, must_have_first_place: false, minimum_points: 0, group_by_gender: true }) },
    activityLogs: { type: Array, default: () => [] },
    categoryOptions: { type: Array, default: () => [] },
    championshipCategoryLabels: { type: Object, default: () => ({}) },
    categoryMergeGroups: { type: Array, default: () => [] },
    excludedOverallCategories: { type: Array, default: () => [] },
    usesPhases: { type: Boolean, default: false },
    cumulativeLeaderboard: { type: Array, default: () => [] },
});

const scope = ref('phase');
const activeLeaderboard = computed(() => (scope.value === 'cumulative' ? props.cumulativeLeaderboard : props.leaderboard));

const showBuilderConfig = ref(false);
const configForm = useForm({
    male_title: props.championshipConfig.male_title || 'Kalaprathibha',
    female_title: props.championshipConfig.female_title || 'Kalathilakam',
    runner_up_title: props.championshipConfig.runner_up_title || 'Runner Up',
    max_counting_items: props.championshipConfig.max_counting_items || 0,
    multi_person_mode: props.championshipConfig.multi_person_mode || 'tie_break_only',
    group_weight_percent: props.championshipConfig.group_weight_percent || 100,
    must_have_first_place: props.championshipConfig.must_have_first_place || false,
    minimum_points: props.championshipConfig.minimum_points || 0,
    excluded_item_categories: props.championshipConfig.excluded_item_categories || [],
    excluded_individual_categories: props.championshipConfig.excluded_individual_categories || [],
    disabled: props.championshipConfig.disabled || false,
    group_by_gender: props.championshipConfig.group_by_gender ?? true,
});

function saveBuilderConfig() {
    configForm.put(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/championship/config`, {
        preserveScroll: true,
        onSuccess: () => {
            showBuilderConfig.value = false;
        },
    });
}

function syncToTrophies() {
    if (!confirm('Sync crowned individual champions into the Trophy Distribution template?')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/championship/sync-to-trophies`, {}, {
        preserveScroll: true,
    });
}

const studentBreakdownModal = ref(false);
const studentBreakdownData = ref(null);
const loadingBreakdown = ref(false);

function viewStudentBreakdown(studentId) {
    if (!studentId) return;
    loadingBreakdown.value = true;
    studentBreakdownModal.value = true;
    fetch(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/championship/students/${studentId}/breakdown`)
        .then(res => res.json())
        .then(data => {
            studentBreakdownData.value = data;
            loadingBreakdown.value = false;
        })
        .catch(() => {
            loadingBreakdown.value = false;
        });
}

const filterCategory = ref('');
const filterGender = ref('');

const categories = computed(() => {
    const set = new Set();
    for (const row of activeLeaderboard.value ?? []) {
        if (row.category) set.add(row.category.toLowerCase());
    }
    return [...set];
});

const categoryOptionsFromLeaderboard = computed(() => categories.value.map(cat => ({ value: cat, label: rowCategoryLabel(cat) })));

const mergeGroups = ref(props.categoryMergeGroups.map(g => ({ ...g })));
const addingMergeRule = ref(false);
const newGroupSources = ref([]);
const newGroupTarget = ref(null);

function labelFor(key) {
    return props.categoryOptions.find(o => o.value === key)?.label ?? key;
}

function rowCategoryLabel(key) {
    return props.championshipCategoryLabels[key] ?? key;
}

function removeMergeGroup(idx) {
    mergeGroups.value.splice(idx, 1);
    saveMergeGroups();
}

function saveMergeGroup() {
    mergeGroups.value.push({ target: newGroupTarget.value, sources: [...newGroupSources.value] });
    newGroupSources.value = [];
    newGroupTarget.value = null;
    addingMergeRule.value = false;
    saveMergeGroups();
}

function saveMergeGroups() {
    router.put(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/championship/category-merge`, {
        groups: mergeGroups.value,
    }, { preserveScroll: true });
}

const excludedCategories = ref([...props.excludedOverallCategories]);

function saveExcludedCategories() {
    router.put(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/championship/excluded-overall-categories`, {
        categories: excludedCategories.value,
    }, { preserveScroll: true });
}

const stats = computed(() => {
    const points = (activeLeaderboard.value ?? []).map(r => Number(r.points || 0));
    return {
        top_points: points.length ? Math.max(...points) : 0,
        total_points: points.reduce((a, b) => a + b, 0),
    };
});

const filteredLeaderboard = computed(() => {
    let list = activeLeaderboard.value ?? [];
    if (filterCategory.value) {
        list = list.filter(r => String(r.category).toLowerCase() === filterCategory.value.toLowerCase());
    }
    if (filterGender.value) {
        list = list.filter(r => String(r.gender).toLowerCase() === filterGender.value.toLowerCase());
    }
    return list;
});
</script>

<style scoped>
.lbl { display: block; margin-bottom: 0.25rem; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: rgb(100 116 139); }
.fld { width: 100%; border-radius: 0.5rem; border: 1px solid rgb(203 213 225); padding: 0.4rem 0.6rem; font-size: 0.8125rem; }
.fld:focus { outline: none; border-color: rgb(99 102 241); ring: 2px rgb(99 102 241 / 20%); }
</style>
