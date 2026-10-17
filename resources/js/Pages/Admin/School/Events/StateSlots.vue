<template>
    <SchoolAdminLayout :title="`State Slot Choices — ${programLabel}`" :school="school" :show-header-title="false">
        <div class="space-y-6 max-w-7xl mx-auto pb-12">
            <!-- Header Banner -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 sm:p-8 text-white shadow-xl border border-indigo-900/50">
                <div class="relative z-10 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                {{ eyebrow }}
                            </span>
                            <span v-if="event.state_slot_collection_approved_at" class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                ✓ Final List Approved
                            </span>
                            <span v-else-if="event.state_slot_collection_open" class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                ⚡ Confirmations Open
                            </span>
                            <span v-else class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-slate-500/20 text-slate-300 border border-slate-500/30">
                                ⏳ Not Open Yet
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                            {{ event.title }} — State Slot Choices
                        </h1>
                        <p class="mt-2 text-sm text-slate-300 max-w-2xl leading-relaxed">
                            Confirm participation for your qualified students representing the Sahodaya at the State Kalotsavam. If your school opts out, the slot automatically rolls down to the next ranked candidate in the waiting list.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary !bg-white/10 hover:!bg-white/20 !text-white !border-white/20 text-xs">
                            ← Event Reports
                        </Link>
                    </div>
                </div>

                <!-- Status alert bar inside banner -->
                <div v-if="event.state_slot_collection_approved_at" class="mt-6 flex items-center gap-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 p-3.5 text-xs text-emerald-200">
                    <span class="text-base">🔒</span>
                    <div>
                        <strong class="font-semibold text-emerald-100">State Slot List Approved &amp; Locked:</strong> The Sahodaya administration has approved the final delegation list. Slot choices are now closed.
                    </div>
                </div>
                <div v-else-if="!event.state_slot_collection_open" class="mt-6 flex items-center gap-3 rounded-xl bg-amber-500/10 border border-amber-500/20 p-3.5 text-xs text-amber-200">
                    <span class="text-base">⏳</span>
                    <div>
                        <strong class="font-semibold text-amber-100">Confirmation Window Not Open Yet:</strong> The Sahodaya has not opened the response window. Ranks and waiting list positions are shown below in preview mode.
                    </div>
                </div>
            </div>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                <button type="button" @click="activeTab = 'all'"
                        class="p-4 rounded-xl border text-left transition"
                        :class="activeTab === 'all' ? 'border-indigo-500 bg-indigo-50/50 ring-2 ring-indigo-500/20' : 'border-slate-200 bg-white hover:border-slate-300'">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Entries</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">{{ slots.length }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Top 1–5 ranked or direct</p>
                </button>

                <button type="button" @click="activeTab = 'action_required'"
                        class="p-4 rounded-xl border text-left transition"
                        :class="activeTab === 'action_required' ? 'border-amber-500 bg-amber-50/50 ring-2 ring-amber-500/20' : 'border-slate-200 bg-white hover:border-slate-300'">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold text-amber-700 uppercase tracking-wider">Action Needed</p>
                        <span v-if="actionNeededCount > 0" class="flex h-2 w-2 rounded-full bg-amber-500 animate-ping"></span>
                    </div>
                    <p class="text-2xl font-black text-amber-600 mt-1">{{ actionNeededCount }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Direct offers awaiting response</p>
                </button>

                <button type="button" @click="activeTab = 'confirmed'"
                        class="p-4 rounded-xl border text-left transition"
                        :class="activeTab === 'confirmed' ? 'border-emerald-500 bg-emerald-50/50 ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white hover:border-slate-300'">
                    <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">Confirmed</p>
                    <p class="text-2xl font-black text-emerald-600 mt-1">{{ confirmedCount }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Accepted for State</p>
                </button>

                <button type="button" @click="activeTab = 'waiting'"
                        class="p-4 rounded-xl border text-left transition"
                        :class="activeTab === 'waiting' ? 'border-purple-500 bg-purple-50/50 ring-2 ring-purple-500/20' : 'border-slate-200 bg-white hover:border-slate-300'">
                    <p class="text-xs font-semibold text-purple-700 uppercase tracking-wider">Waiting List</p>
                    <p class="text-2xl font-black text-purple-600 mt-1">{{ waitingCount }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Eligible if top ranks opt out</p>
                </button>

                <button v-if="optedOutCount > 0" type="button" @click="activeTab = 'opted_out'"
                        class="p-4 rounded-xl border text-left transition"
                        :class="activeTab === 'opted_out' ? 'border-rose-500 bg-rose-50/50 ring-2 ring-rose-500/20' : 'border-slate-200 bg-white hover:border-slate-300'">
                    <p class="text-xs font-semibold text-rose-700 uppercase tracking-wider">Opted Out</p>
                    <p class="text-2xl font-black text-rose-600 mt-1">{{ optedOutCount }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Can cancel &amp; reclaim</p>
                </button>
            </div>

            <!-- How Waiting List Works Explanation Card -->
            <div class="rounded-2xl border border-indigo-100 bg-gradient-to-r from-indigo-50/60 via-purple-50/30 to-blue-50/50 p-5 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="h-10 w-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-lg shrink-0 shadow-md">
                        ℹ️
                    </div>
                    <div class="space-y-1">
                        <h4 class="text-sm font-bold text-slate-900">How State Selection &amp; Waiting List Progression Works</h4>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Each item has a default State quota of <strong>Top 2 ranks</strong> (1st &amp; 2nd Place). Candidates ranked <strong>3rd, 4th, and 5th</strong> are placed on the official waiting list.
                            <strong>When any Top 2 rank opts out</strong>, their slot automatically rolls down in strict merit order to the next available rank (e.g. Rank 3 becomes Rank 1 in the queue). Schools on the waiting list will immediately see their status change to <em>"Offered"</em> with active confirm/opt-out buttons.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Filter and Search Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="activeTab = 'all'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                            :class="activeTab === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                        All ({{ slots.length }})
                    </button>
                    <button type="button" @click="activeTab = 'action_required'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                            :class="activeTab === 'action_required' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200'">
                        Action Needed ({{ actionNeededCount }})
                    </button>
                    <button type="button" @click="activeTab = 'confirmed'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                            :class="activeTab === 'confirmed' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200'">
                        Confirmed ({{ confirmedCount }})
                    </button>
                    <button type="button" @click="activeTab = 'waiting'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                            :class="activeTab === 'waiting' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200'">
                        Waiting List ({{ waitingCount }})
                    </button>
                    <button type="button" @click="activeTab = 'opted_out'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                            :class="activeTab === 'opted_out' ? 'bg-slate-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                        Opted Out ({{ optedOutCount }})
                    </button>
                </div>

                <div class="w-full sm:w-64">
                    <input v-model="search" type="search" placeholder="Search item, student, code…"
                           class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!filteredSlots.length" class="card text-center py-12 border-dashed border-2 border-slate-200">
                <div class="text-3xl mb-2">📋</div>
                <h3 class="text-sm font-bold text-slate-800">No entries match your view</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                    {{ search ? 'Try clearing your search query.' : 'No candidates or offers found in this category.' }}
                </p>
                <button v-if="search || activeTab !== 'all'" @click="search = ''; activeTab = 'all'" type="button"
                        class="mt-3 btn-secondary text-xs">
                    Reset filters
                </button>
            </div>

            <!-- List of Slot Items -->
            <div v-else class="space-y-4">
                <div v-for="s in filteredSlots" :key="s.key || s.id"
                     class="card overflow-hidden border border-slate-200 hover:border-indigo-300 transition shadow-sm bg-white !p-0">
                    <div class="p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <!-- Left Info Column -->
                        <div class="min-w-0 space-y-2 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ s.item_code || '—' }}
                                </span>
                                <h3 class="text-base font-bold text-slate-900">
                                    {{ s.item_title }}
                                </h3>
                                <span v-if="s.category_label" class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                                    {{ s.category_label }}
                                </span>
                                <span v-if="s.gender_label" class="text-xs text-slate-400">
                                    · {{ s.gender_label }}
                                </span>
                            </div>

                            <div class="flex items-center gap-3">
                                <img v-if="s.photo_url"
                                     :src="s.photo_url"
                                     :alt="s.student_name"
                                     class="w-11 h-11 rounded-full object-cover border-2 border-indigo-100 shadow-2xs shrink-0"
                                     @error="s.photo_url = null" />
                                <div v-else
                                     class="w-11 h-11 rounded-full bg-indigo-50 border-2 border-indigo-100 text-indigo-700 font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                    {{ initials(s.student_name) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-baseline gap-2">
                                        <span class="text-sm font-bold text-slate-900">
                                            {{ s.student_name }}
                                        </span>
                                        <span v-if="s.class_name" class="text-xs text-slate-500 font-medium">
                                            ({{ s.class_name }})
                                        </span>
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                              :class="s.source_position <= 2 ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'bg-slate-100 text-slate-700'">
                                            {{ s.direct_entry ? 'Direct Qualifier' : 'Rank ' + s.source_position }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Explanatory message based on status -->
                            <div v-if="s.school_response === 'pending'" class="rounded-lg bg-amber-50 border border-amber-200/80 p-3 text-xs text-amber-900 flex items-start gap-2">
                                <span class="text-base">🎉</span>
                                <div>
                                    <strong class="font-bold text-amber-950">Offered State Slot:</strong> This student is in the qualifying top quota! Please confirm their registration or opt out if they cannot attend.
                                </div>
                            </div>

                            <div v-else-if="s.school_response === 'waiting'" class="rounded-lg bg-purple-50 border border-purple-200/80 p-3 text-xs text-purple-900 flex items-start gap-2">
                                <span class="text-base">⏳</span>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <strong class="font-bold text-purple-950">Waiting List Candidate:</strong>
                                        <span class="px-2 py-0.2 rounded-full font-bold text-[10px] bg-purple-200 text-purple-800">
                                            {{ s.is_next_in_line ? '★ Next in line (#1 in waitlist)' : `Waitlist #${s.waitlist_position}` }}
                                        </span>
                                    </div>
                                    <p class="text-purple-800 text-[11px] leading-relaxed">
                                        Excluded from default Top {{ s.quota || 2 }} quota.
                                        <strong>If {{ s.is_next_in_line ? 'any 1' : s.opt_outs_needed }} of the higher-ranked qualifiers opt out</strong>, this student immediately earns the slot and your school will be asked to confirm!
                                    </p>
                                </div>
                            </div>

                            <div v-else-if="s.school_response === 'accepted'" class="rounded-lg bg-emerald-50 border border-emerald-200 p-2.5 text-xs text-emerald-900 flex items-center gap-2">
                                <span class="text-base">✓</span>
                                <div>
                                    Confirmed for State representation by <strong>{{ s.responded_by_name || 'School Admin' }}</strong> on {{ formatDate(s.responded_at) }}.
                                </div>
                            </div>

                            <div v-else-if="s.school_response === 'opted_out'" class="rounded-lg bg-slate-100 border border-slate-200 p-2.5 text-xs text-slate-700 space-y-0.5">
                                <div class="font-semibold text-slate-800 flex items-center gap-1.5">
                                    <span>✕</span> Opted out on {{ formatDate(s.responded_at) }} by {{ s.responded_by_name || 'School Admin' }}
                                </div>
                                <p class="text-[11px] text-slate-500 italic pl-4">Reason: "{{ s.opt_out_reason || 'No reason provided' }}"</p>
                            </div>
                        </div>

                        <!-- Right Actions / Status Badge Column -->
                        <div class="shrink-0 flex md:flex-col items-center md:items-end justify-between md:justify-center gap-3 border-t md:border-t-0 pt-3 md:pt-0 border-slate-100">
                            <!-- Status Badges -->
                            <div>
                                <span v-if="s.school_response === 'accepted'"
                                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 shadow-2xs">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Confirmed
                                </span>
                                <span v-else-if="s.school_response === 'opted_out'"
                                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-slate-200 text-slate-700 border border-slate-300">
                                    Opted Out
                                </span>
                                <span v-else-if="s.school_response === 'pending'"
                                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300 animate-pulse">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span> Response Required
                                </span>
                                <span v-else-if="s.school_response === 'waiting'"
                                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-purple-100 text-purple-900 border border-purple-200">
                                    Waiting List · Rank {{ s.source_position }}
                                </span>
                            </div>

                            <!-- Interactive Buttons for Pending Offers -->
                            <div v-if="s.can_respond" class="flex items-center gap-2">
                                <button type="button" @click="openAcceptModal(s)"
                                        class="btn-primary text-xs !py-2 !px-3.5 !bg-emerald-600 hover:!bg-emerald-700 shadow-sm font-bold flex items-center gap-1.5">
                                    <span>✓</span> Confirm Slot
                                </button>
                                <button type="button" @click="openOptOutModal(s)"
                                        class="btn-secondary text-xs !py-2 !px-3 text-rose-700 hover:!bg-rose-50 border-rose-200 font-bold flex items-center gap-1">
                                    <span>✕</span> Opt Out
                                </button>
                            </div>

                            <!-- Cancel Opt-Out Button for Opted-Out Offers -->
                            <div v-else-if="s.can_cancel_opt_out" class="flex items-center gap-2">
                                <button type="button" @click="openCancelOptOutModal(s)"
                                        class="btn-secondary text-xs !py-1.5 !px-3 text-indigo-700 hover:!bg-indigo-50 border-indigo-200 font-bold flex items-center gap-1.5 shadow-2xs transition">
                                    <span>↺</span> Cancel Opt-Out &amp; Reclaim
                                </button>
                            </div>

                            <!-- Informative helper button for waiting entries -->
                            <div v-else-if="s.school_response === 'waiting'" class="text-[11px] text-right text-slate-400">
                                <span>{{ s.is_next_in_line ? '1 opt-out gives this entry the slot' : `${s.opt_outs_needed} opt-outs needed` }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accept Confirmation Modal -->
        <Modal :show="showAcceptModal" @close="showAcceptModal = false" size="md">
            <template #header>
                <div class="flex items-center gap-2">
                    <span class="text-xl">🎉</span>
                    <h3 class="text-base font-bold text-slate-900">Confirm State Representation</h3>
                </div>
            </template>
            <div class="space-y-4">
                <div class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-200 rounded-xl">
                    <img v-if="selectedSlot?.photo_url"
                         :src="selectedSlot.photo_url"
                         :alt="selectedSlot.student_name"
                         class="w-12 h-12 rounded-full object-cover border border-slate-200 shrink-0 shadow-2xs"
                         @error="selectedSlot.photo_url = null" />
                    <div v-else
                         class="w-12 h-12 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-700 font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                        {{ initials(selectedSlot?.student_name) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-900">{{ selectedSlot?.student_name }} <span v-if="selectedSlot?.class_name" class="font-normal text-xs text-slate-500">({{ selectedSlot.class_name }})</span></p>
                        <p class="text-xs font-medium text-slate-600">{{ selectedSlot?.item_code }} — {{ selectedSlot?.item_title }}</p>
                        <p class="text-[11px] text-slate-400">{{ selectedSlot?.category_label }} · Rank {{ selectedSlot?.source_position }}</p>
                    </div>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed">
                    You are confirming that <strong class="text-slate-900">{{ selectedSlot?.student_name }}</strong> will represent the Sahodaya at the State Kalotsavam. Once confirmed, the Sahodaya committee will register this student directly on the official State portal.
                </p>
            </div>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="showAcceptModal = false" class="btn-secondary text-xs">
                        Cancel
                    </button>
                    <button type="button" @click="confirmAccept" :disabled="submitting" class="btn-primary text-xs !bg-emerald-600 hover:!bg-emerald-700 font-bold">
                        {{ submitting ? 'Confirming...' : 'Yes, Confirm Participation' }}
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Opt-Out Modal -->
        <Modal :show="showOptOutModal" @close="showOptOutModal = false" size="md">
            <template #header>
                <div class="flex items-center gap-2 text-rose-600">
                    <span class="text-xl">⚠️</span>
                    <h3 class="text-base font-bold text-slate-900">Opt Out of State Representation</h3>
                </div>
            </template>
            <form @submit.prevent="confirmOptOut" class="space-y-4">
                <div class="flex items-center gap-3 p-3 bg-rose-50/60 border border-rose-200 rounded-xl">
                    <img v-if="selectedSlot?.photo_url"
                         :src="selectedSlot.photo_url"
                         :alt="selectedSlot.student_name"
                         class="w-12 h-12 rounded-full object-cover border border-rose-200 shrink-0 shadow-2xs"
                         @error="selectedSlot.photo_url = null" />
                    <div v-else
                         class="w-12 h-12 rounded-full bg-rose-100 border border-rose-200 text-rose-700 font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                        {{ initials(selectedSlot?.student_name) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-900">{{ selectedSlot?.student_name }} <span v-if="selectedSlot?.class_name" class="font-normal text-xs text-slate-500">({{ selectedSlot.class_name }})</span></p>
                        <p class="text-xs font-medium text-slate-600">{{ selectedSlot?.item_code }} — {{ selectedSlot?.item_title }}</p>
                        <p class="text-[11px] text-slate-400">{{ selectedSlot?.category_label }} · Rank {{ selectedSlot?.source_position }}</p>
                    </div>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed">
                    If <strong class="text-slate-900">{{ selectedSlot?.student_name }}</strong> is unable to attend, opting out frees the slot so the <strong>next-ranked candidate in the waiting list (Rank 3–5)</strong> can be offered this opportunity.
                </p>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">
                        Reason for Opting Out <span class="text-rose-500">*</span>
                    </label>
                    <textarea v-model="optOutReason" rows="3" required
                              placeholder="e.g. Student unable to attend due to board exams / personal reasons..."
                              class="field w-full text-xs"></textarea>
                    <p class="text-[11px] text-slate-400 mt-1">This reason will be visible to the Sahodaya administration for records.</p>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="showOptOutModal = false" class="btn-secondary text-xs">
                        Cancel
                    </button>
                    <button type="submit" :disabled="submitting || !optOutReason.trim()" class="btn-primary text-xs !bg-rose-600 hover:!bg-rose-700 font-bold">
                        {{ submitting ? 'Recording...' : 'Confirm Opt-Out' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Cancel Opt-Out Confirmation Modal -->
        <Modal :show="showCancelOptOutModal" @close="showCancelOptOutModal = false" size="md">
            <template #header>
                <div class="flex items-center gap-2 text-indigo-600">
                    <span class="text-xl">↺</span>
                    <h3 class="text-base font-bold text-slate-900">Cancel Opt-Out &amp; Reclaim Slot</h3>
                </div>
            </template>
            <div class="space-y-4">
                <div class="flex items-center gap-3 p-3 bg-indigo-50/60 border border-indigo-100 rounded-xl">
                    <img v-if="selectedSlot?.photo_url"
                         :src="selectedSlot.photo_url"
                         :alt="selectedSlot.student_name"
                         class="w-12 h-12 rounded-full object-cover border border-indigo-200 shrink-0 shadow-2xs"
                         @error="selectedSlot.photo_url = null" />
                    <div v-else
                         class="w-12 h-12 rounded-full bg-indigo-100 border border-indigo-200 text-indigo-700 font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                        {{ initials(selectedSlot?.student_name) }}
                    </div>
                    <div class="min-w-0 text-xs">
                        <p class="font-bold text-indigo-950">{{ selectedSlot?.student_name }} <span v-if="selectedSlot?.class_name" class="font-normal text-slate-500">({{ selectedSlot.class_name }})</span></p>
                        <p class="text-indigo-700 text-[11px]">{{ selectedSlot?.item_code }} — {{ selectedSlot?.item_title }} · Rank {{ selectedSlot?.source_position }}</p>
                        <p v-if="selectedSlot?.opt_out_reason" class="text-slate-500 text-[11px] pt-1 italic">
                            Previous opt-out reason: "{{ selectedSlot.opt_out_reason }}"
                        </p>
                    </div>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">
                    This will reclaim the State nomination slot and return it to <strong>Pending Confirmation</strong>, allowing your school to confirm participation.
                </p>
            </div>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="showCancelOptOutModal = false" class="btn-secondary text-xs">
                        Keep Opted Out
                    </button>
                    <button type="button" @click="confirmCancelOptOut" :disabled="submitting" class="btn-primary text-xs !bg-indigo-600 hover:!bg-indigo-700 font-bold">
                        {{ submitting ? 'Restoring...' : 'Yes, Cancel Opt-Out & Reclaim Slot' }}
                    </button>
                </div>
            </template>
        </Modal>
    </SchoolAdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import Modal from '@/Components/ui/Modal.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    stateProgram: { type: Object, default: null },
    slots: { type: Array, default: () => [] },
    actionUrlBase: String,
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const eyebrow = computed(() => programLabel.value || 'State Representation');

const activeTab = ref('all');
const search = ref('');

const actionNeededCount = computed(() => props.slots.filter(s => s.can_respond).length);
const confirmedCount = computed(() => props.slots.filter(s => s.school_response === 'accepted').length);
const waitingCount = computed(() => props.slots.filter(s => s.school_response === 'waiting').length);
const optedOutCount = computed(() => props.slots.filter(s => s.school_response === 'opted_out').length);

const filteredSlots = computed(() => {
    return props.slots.filter(s => {
        // Tab filter
        if (activeTab.value === 'action_required' && !s.can_respond) return false;
        if (activeTab.value === 'confirmed' && s.school_response !== 'accepted') return false;
        if (activeTab.value === 'waiting' && s.school_response !== 'waiting') return false;
        if (activeTab.value === 'opted_out' && s.school_response !== 'opted_out') return false;

        // Search query
        if (search.value.trim()) {
            const q = search.value.toLowerCase().trim();
            const match = (s.item_title && s.item_title.toLowerCase().includes(q))
                || (s.item_code && s.item_code.toLowerCase().includes(q))
                || (s.student_name && s.student_name.toLowerCase().includes(q))
                || (s.category_label && s.category_label.toLowerCase().includes(q));
            if (!match) return false;
        }

        return true;
    });
});

function formatDate(v) {
    if (!v) return '';
    return new Date(v).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function initials(name) {
    if (!name) return '?';
    return name.split(/\s+/).map(w => w[0]).join('').toUpperCase().slice(0, 2);
}

// Modal handling
const showAcceptModal = ref(false);
const showOptOutModal = ref(false);
const selectedSlot = ref(null);
const optOutReason = ref('');
const submitting = ref(false);

function openAcceptModal(slot) {
    selectedSlot.value = slot;
    showAcceptModal.value = true;
}

function confirmAccept() {
    if (!selectedSlot.value) return;
    submitting.value = true;
    router.post(`${props.actionUrlBase}/${selectedSlot.value.id}/accept`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showAcceptModal.value = false;
            selectedSlot.value = null;
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
}

function openOptOutModal(slot) {
    selectedSlot.value = slot;
    optOutReason.value = '';
    showOptOutModal.value = true;
}

function confirmOptOut() {
    if (!selectedSlot.value || !optOutReason.value.trim()) return;
    submitting.value = true;
    router.post(`${props.actionUrlBase}/${selectedSlot.value.id}/opt-out`, {
        reason: optOutReason.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showOptOutModal.value = false;
            selectedSlot.value = null;
            optOutReason.value = '';
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
}

const showCancelOptOutModal = ref(false);

function openCancelOptOutModal(slot) {
    selectedSlot.value = slot;
    showCancelOptOutModal.value = true;
}

function confirmCancelOptOut() {
    if (!selectedSlot.value) return;
    submitting.value = true;
    router.post(`${props.actionUrlBase}/${selectedSlot.value.id}/cancel-opt-out`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showCancelOptOutModal.value = false;
            selectedSlot.value = null;
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
}
</script>
