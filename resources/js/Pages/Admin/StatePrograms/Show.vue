<template>
    <AdminLayout :title="program.title">
        <div class="space-y-6 max-w-6xl mx-auto">
            <!-- Header Banner -->
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 rounded-2xl text-white shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <Link href="/admin/state-programs" class="text-xs font-semibold text-indigo-300 hover:text-white transition">
                            ← Back to State Programs
                        </Link>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold capitalize"
                              :class="program.status === 'published' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'">
                            ● {{ program.status }}
                        </span>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ program.title }}</h1>
                    <p class="text-slate-300 text-sm mt-1">
                        Academic Year: <span class="font-mono text-amber-300">{{ program.academic_year || '2026-2027' }}</span> ·
                        Type: <span class="capitalize text-white font-medium">{{ program.event_type }}</span>
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <form v-if="program.status !== 'published'" @submit.prevent="publish">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm transition shadow-lg shadow-emerald-500/20">
                            🚀 Publish to all Sahodayas
                        </button>
                    </form>
                    <form v-else @submit.prevent="publish">
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-sm transition border border-white/10 backdrop-blur">
                            🔄 Re-sync Clusters
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tabbed Navigation Bar -->
            <div class="flex items-center gap-2 border-b border-slate-200 overflow-x-auto pb-px">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    @click="activeTab = tab.id"
                    class="px-4 py-2.5 text-sm font-bold border-b-2 transition whitespace-nowrap flex items-center gap-2"
                    :class="activeTab === tab.id
                        ? 'border-indigo-600 text-indigo-600 bg-indigo-50/50 rounded-t-xl'
                        : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300'"
                >
                    <span>{{ tab.icon }}</span>
                    <span>{{ tab.label }}</span>
                    <span v-if="tab.badge !== undefined" class="px-2 py-0.5 rounded-full text-xs"
                          :class="activeTab === tab.id ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600'">
                        {{ tab.badge }}
                    </span>
                </button>
            </div>

            <!-- TAB 1: Blueprint & General Settings -->
            <div v-show="activeTab === 'general'" class="space-y-6">
                <form @submit.prevent="save" class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-5">
                    <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Program Identity & Level Conducts</h2>

                    <div v-if="program.status !== 'published'" class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Program Title *</label>
                            <input v-model="form.title" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-sm font-medium" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Competition Event Type *</label>
                            <select v-model="form.event_type" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-sm font-medium">
                                <option v-for="(label, key) in eventTypes" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                    </div>
                    <div v-else class="p-4 rounded-xl bg-amber-50 border border-amber-200/60 text-amber-800 text-xs font-medium">
                        🔒 Published program title and competition type are locked to preserve cluster integrity. You can update dates, venue, and description.
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Conduct Levels</label>
                        <div class="flex flex-wrap gap-4">
                            <label v-for="(label, key) in selectableLevelLabels" :key="key" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 bg-slate-50 px-3.5 py-2 rounded-xl border border-slate-200/80 cursor-pointer hover:bg-slate-100">
                                <input type="checkbox" :value="key" v-model="form.conduct_levels" class="rounded text-indigo-600 focus:ring-indigo-500">
                                {{ label }}
                            </label>
                        </div>
                    </div>

                    <!-- State Handoff Configuration -->
                    <div v-if="form.conduct_levels.includes('state')" class="p-5 rounded-2xl bg-slate-900 text-white space-y-4">
                        <div>
                            <p class="text-sm font-bold text-amber-400">🔌 State Intake API & Qualifier Policy</p>
                            <p class="text-xs text-slate-300 mt-0.5">Configures signed API endpoint credentials and position qualifier boundaries from Sahodayas.</p>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-3 text-slate-900">
                            <input v-model="form.state_domain.name" class="px-3.5 py-2 rounded-xl bg-slate-800 text-white border border-slate-700 text-sm placeholder-slate-400" placeholder="State domain name">
                            <input v-model="form.state_domain.domain" class="px-3.5 py-2 rounded-xl bg-slate-800 text-white border border-slate-700 text-sm placeholder-slate-400" placeholder="Public domain (e.g. state.truecampus.in)">
                            <input v-model="form.state_domain.api_base_url" class="px-3.5 py-2 rounded-xl bg-slate-800 text-white border border-slate-700 text-sm placeholder-slate-400" placeholder="API base URL">
                            <input v-model="form.state_domain.api_client_id" class="px-3.5 py-2 rounded-xl bg-slate-800 text-white border border-slate-700 text-sm placeholder-slate-400" placeholder="Client ID">
                            <input v-model="form.state_domain.api_client_secret" type="password" class="px-3.5 py-2 rounded-xl bg-slate-800 text-white border border-slate-700 text-sm placeholder-slate-400 sm:col-span-2" placeholder="Set / rotate shared API secret">
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4 text-xs pt-2 border-t border-slate-800">
                            <div>
                                <p class="font-bold text-amber-300 mb-1.5">Regional Qualifiers</p>
                                <label v-for="pos in [1, 2, 3]" :key="`regional-${pos}`" class="inline-flex items-center gap-1.5 mr-3 text-slate-200">
                                    <input type="checkbox" :value="pos" v-model="form.qualifier_policy.regional.positions" class="rounded text-amber-500">
                                    Position {{ pos }}
                                </label>
                            </div>
                            <div>
                                <p class="font-bold text-amber-300 mb-1.5">District Qualifiers</p>
                                <label v-for="pos in [1, 2, 3]" :key="`district-${pos}`" class="inline-flex items-center gap-1.5 mr-3 text-slate-200">
                                    <input type="checkbox" :value="pos" v-model="form.qualifier_policy.district.positions" class="rounded text-amber-500">
                                    Position {{ pos }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Dates & Venue -->
                    <div class="grid sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Registration Start Date</label>
                            <input v-model="form.registration_open" type="date" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Registration Close Date</label>
                            <input v-model="form.registration_close" type="date" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Event Start Date</label>
                            <input v-model="form.event_start" type="date" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Event End Date</label>
                            <input v-model="form.event_end" type="date" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">State Final Venue</label>
                            <input v-model="form.venue" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" placeholder="Main venue location">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Description & Guidelines</label>
                            <textarea v-model="form.description" rows="3" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" placeholder="Program description..."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end pt-3">
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm transition">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB 2: Master Item Catalog -->
            <div v-show="activeTab === 'items'" class="space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Master Item Catalog</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Define state catalog items. Sahodaya complexes inherit these items when published.</p>
                        </div>
                        <span class="px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold self-start">
                            {{ program.items?.length || 0 }} item(s) configured
                        </span>
                    </div>

                    <!-- Add Item Form -->
                    <form @submit.prevent="addItem" class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-4">
                        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Add New Catalog Item</h3>
                        <div class="grid sm:grid-cols-3 gap-3">
                            <input v-model="itemForm.title" class="px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium sm:col-span-2" placeholder="Item Name (e.g. Light Music, Bharatanatyam)" required>
                            <input v-model.number="itemForm.fee_amount" type="number" min="0" class="px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" placeholder="Fee (₹) — optional">
                            
                            <select v-if="form.event_type === 'sports'" v-model="itemForm.age_group" class="px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                <option value="">Age Group</option>
                                <option v-for="(label, key) in ageGroupLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <div v-else>
                                <input v-model="itemForm.class_group" list="state-class-groups" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" placeholder="Class category key (e.g. hs)">
                                <datalist id="state-class-groups">
                                    <option v-for="(label, key) in taxonomy.class_group" :key="key" :value="key">{{ label }}</option>
                                </datalist>
                            </div>

                            <select v-model="itemForm.participant_type" class="px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                <option value="individual">Individual</option>
                                <option value="group">Group</option>
                                <option value="team">Team</option>
                            </select>

                            <input v-model.number="itemForm.qualify_count" type="number" min="1" class="px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" placeholder="State Qualifiers (default 2)">
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition">
                                + Add State Item
                            </button>
                        </div>
                    </form>

                    <!-- Items List Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider font-semibold border-b border-slate-100">
                                    <th class="py-3 px-4">Item Name</th>
                                    <th class="py-3 px-4">Category / Group</th>
                                    <th class="py-3 px-4">Type</th>
                                    <th class="py-3 px-4 text-center">Fee</th>
                                    <th class="py-3 px-4 text-center">Qualifiers</th>
                                    <th class="py-3 px-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="item in program.items" :key="item.id" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="py-3 px-4 font-bold text-slate-900">
                                        {{ item.title }}
                                        <span class="block text-xs font-normal text-slate-400">Code: {{ item.item_code || 'Auto' }}</span>
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-slate-600 capitalize">
                                        {{ item.class_group || item.age_group || 'Open' }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded text-xs font-bold uppercase"
                                              :class="item.participant_type === 'individual' ? 'bg-indigo-50 text-indigo-700' : 'bg-purple-50 text-purple-700'">
                                            {{ item.participant_type }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center font-mono font-medium">
                                        {{ item.fee_amount != null ? '₹' + item.fee_amount : '—' }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-bold text-xs">
                                            Top {{ item.qualify_count ?? 2 }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <button type="button" @click="removeItem(item.id)" class="text-xs font-bold text-red-600 hover:text-red-800 transition">
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!program.items?.length">
                                    <td colspan="6" class="py-8 text-center text-slate-400">
                                        No items added yet. Sahodaya complexes will conduct State catalog items once added.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: Fees & Level Policies -->
            <div v-show="activeTab === 'fees'" class="space-y-6">
                <form @submit.prevent="save" class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-6">
                    <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Level Fee Structures & Student Limits</h2>

                    <!-- Fees by level -->
                    <div v-if="form.conduct_levels.filter(l => l !== 'state').length" class="space-y-4">
                        <div v-for="lvl in form.conduct_levels.filter(l => l !== 'state')" :key="lvl" class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">{{ levelLabels[lvl] ?? lvl }} Fee Model</h3>
                                <span class="text-xs text-slate-500">{{ levelFeeHints[lvl] }}</span>
                            </div>
                            <select v-model="form.level_fees[lvl].fee_model" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                <option v-for="(label, key) in feeTypes" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <div v-if="form.level_fees[lvl].fee_model === 'cksc_tiered'" class="grid sm:grid-cols-2 gap-3 text-xs">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">First Item Registration Fee (₹)</label>
                                    <input v-model.number="form.level_fees[lvl].first_item" type="number" min="0" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Additional Item Fee (₹)</label>
                                    <input v-model.number="form.level_fees[lvl].additional_item" type="number" min="0" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="form.conduct_levels.includes('state')" class="p-5 rounded-2xl bg-indigo-50 border border-indigo-200 space-y-3">
                        <div>
                            <h3 class="text-sm font-bold text-indigo-950 uppercase tracking-wider">State remittance rate</h3>
                            <p class="text-xs text-indigo-700">Charged to each Sahodaya for every qualifier accepted into the State event.</p>
                        </div>
                        <div class="max-w-sm">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Fee per accepted nominee/team (₹)</label>
                            <input v-model.number="form.level_fees.state.individual_amount" type="number" min="0" class="w-full px-3.5 py-2 rounded-xl border border-indigo-200 text-sm font-medium">
                        </div>
                    </div>

                    <!-- Participation Policies -->
                    <div v-if="form.conduct_levels.filter(l => l !== 'state').length" class="space-y-4 pt-4 border-t border-slate-100">
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Per-Student Participation Limits</h3>
                        <div v-for="lvl in form.conduct_levels.filter(l => l !== 'state')" :key="`pol-${lvl}`" class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3">
                            <p class="text-xs font-bold text-slate-700 uppercase">{{ levelLabels[lvl] ?? lvl }} Limit Preset</p>
                            <select v-model="form.level_policies[lvl].preset_key" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                <option value="">Custom</option>
                                <option v-for="(preset, key) in participationPresets" :key="key" :value="key">{{ preset.label }}</option>
                            </select>
                            <div v-if="!form.level_policies[lvl].preset_key" class="grid sm:grid-cols-4 gap-3 text-xs">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Max On-Stage</label>
                                    <input v-model.number="form.level_policies[lvl].max_onstage_per_student" type="number" min="0" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Max Off-Stage</label>
                                    <input v-model.number="form.level_policies[lvl].max_offstage_per_student" type="number" min="0" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Max Group</label>
                                    <input v-model.number="form.level_policies[lvl].max_group_per_student" type="number" min="0" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Max Total</label>
                                    <input v-model.number="form.level_policies[lvl].max_total_per_student" type="number" min="0" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm transition">
                            Save Fee & Policy Rules
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB 4: Cluster Propagation Log -->
            <div v-show="activeTab === 'clusters'" class="space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h2 class="text-base font-bold text-slate-900">Sahodaya Cluster Propagation Log</h2>
                        <span class="text-xs font-bold text-slate-500">{{ program.propagations?.length || 0 }} cluster(s) deployed</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider font-semibold border-b border-slate-100">
                                    <th class="py-3 px-4">Sahodaya Complex</th>
                                    <th class="py-3 px-4">Level Round</th>
                                    <th class="py-3 px-4 font-mono">Tenant Event ID</th>
                                    <th class="py-3 px-4 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="row in program.propagations" :key="row.id" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="py-3.5 px-4 font-bold text-slate-900">
                                        {{ row.sahodaya?.name ?? row.sahodaya_id }}
                                    </td>
                                    <td class="py-3.5 px-4 font-medium capitalize">
                                        {{ levelLabels[row.level_round] ?? row.level_round }}
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-xs text-slate-500">
                                        {{ row.tenant_event_id ?? '—' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold">
                                            ● Deployed
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!program.propagations?.length">
                                    <td colspan="4" class="py-8 text-center text-slate-400">
                                        Not published yet. Click "Publish to all Sahodayas" to deploy hub events to Sahodaya complexes.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 5: External Sahodayas -->
            <div v-show="activeTab === 'external'" class="space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Outside Sahodaya Complexes</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Manage code-gated access credentials for Sahodaya complexes that are not platform tenants.</p>
                        </div>
                        <Link :href="`/admin/state-programs/${program.id}/external-sahodayas`"
                              class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition">
                            Manage Credentials & Codes →
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    program: Object,
    eventTypes: Object,
    levelLabels: Object,
    feeTypes: Object,
    levelDefaults: Object,
    classGroupLabels: Object,
    classGroupSchemeOptions: Object,
    ageGroupLabels: Object,
    defaultAgeGroupFees: Object,
    participationPresets: Object,
    taxonomy: Object,
    stateDomains: Array,
    defaultQualifierPolicy: Object,
});

const activeTab = ref('general');

const tabs = computed(() => [
    { id: 'general', icon: '📋', label: 'Blueprint & Handoff' },
    { id: 'items', icon: '🎭', label: 'Master Item Catalog', badge: props.program.items?.length || 0 },
    { id: 'fees', icon: '💰', label: 'Level Fees & Policies' },
    { id: 'clusters', icon: '🏛️', label: 'Cluster Deployments', badge: props.program.propagations?.length || 0 },
    { id: 'external', icon: '🌐', label: 'Outside Sahodayas' },
]);

function buildLevelFees(program, conductLevels) {
    const fees = {};
    for (const lvl of (conductLevels ?? [])) {
        const existing = program.level_fees?.[lvl];
        const defaults = props.levelDefaults?.[lvl] ?? { fee_model: 'none' };
        if (lvl === 'state') {
            fees.state = {
                fee_model: 'per_student',
                individual_amount: existing?.individual_amount ?? existing?.per_student_amount ?? 500,
            };
            continue;
        }
        const scheme = existing?.class_group_scheme ?? 'cbse';
        fees[lvl] = {
            fee_model: existing?.fee_model ?? defaults.fee_model ?? 'none',
            class_group_scheme: scheme,
            first_item: existing?.first_item ?? defaults.first_item ?? 350,
            additional_item: existing?.additional_item ?? defaults.additional_item ?? 100,
            class_group_fees: existing?.class_group_fees ?? { lp: 100, up: 150, hs: 200, hss: 250, open: 200 },
            age_group_fees: existing?.age_group_fees ?? { u14: 150, u17: 200, u19: 250, open: 200 },
            participant_type_fees: existing?.participant_type_fees ?? { group: 150, team: 150 },
            default_item_fee: existing?.default_item_fee ?? '',
        };
    }
    return fees;
}

function buildLevelPolicies(program, conductLevels) {
    const policies = {};
    for (const lvl of (conductLevels ?? []).filter(l => l !== 'state')) {
        const existing = program.level_policies?.[lvl] ?? {};
        policies[lvl] = {
            preset_key: existing.preset_key ?? (lvl === 'school' ? 'cksc_school_kalakriti' : 'cksc_sahodaya_cluster'),
            max_onstage_per_student: existing.max_onstage_per_student ?? '',
            max_offstage_per_student: existing.max_offstage_per_student ?? '',
            max_group_per_student: existing.max_group_per_student ?? '',
            max_total_per_student: existing.max_total_per_student ?? '',
        };
    }
    return policies;
}

const form = useForm({
    title: props.program.title,
    event_type: props.program.event_type,
    conduct_levels: [...(props.program.conduct_levels ?? [])].filter(
        (l) => props.program.event_type !== 'sports' || l !== 'state'
    ),
    level_fees: buildLevelFees(props.program, props.program.conduct_levels),
    level_policies: buildLevelPolicies(props.program, props.program.conduct_levels),
    registration_open: props.program.registration_open?.slice?.(0, 10) ?? '',
    registration_close: props.program.registration_close?.slice?.(0, 10) ?? '',
    event_start: props.program.event_start?.slice?.(0, 10) ?? '',
    event_end: props.program.event_end?.slice?.(0, 10) ?? '',
    venue: props.program.venue ?? '',
    state_domain_id: props.program.state_domain_id ?? '',
    state_flow_mode: props.program.state_flow_mode ?? 'state_domain_event',
    qualifier_policy: {
        regional: {
            positions: [...(props.program.qualifier_policy?.regional?.positions ?? props.defaultQualifierPolicy?.regional?.positions ?? [1])],
        },
        district: {
            positions: [...(props.program.qualifier_policy?.district?.positions ?? props.defaultQualifierPolicy?.district?.positions ?? [1, 2])],
        },
        skip_item_flags: [...(props.program.qualifier_policy?.skip_item_flags ?? props.defaultQualifierPolicy?.skip_item_flags ?? ['mcs_only'])],
    },
    state_domain: {
        name: props.program.state_domain?.name ?? '',
        domain: props.program.state_domain?.domain ?? '',
        api_base_url: props.program.state_domain?.api_base_url ?? '',
        api_client_id: props.program.state_domain?.api_client_id ?? '',
        api_client_secret: '',
    },
    description: props.program.description ?? '',
});

const selectableLevelLabels = computed(() => {
    const keys = form.event_type === 'sports' ? ['school', 'sahodaya'] : Object.keys(props.levelLabels ?? {});
    return Object.fromEntries(keys.map((k) => [k, props.levelLabels[k]]));
});

const levelFeeHints = {
    sahodaya: 'School pays Sahodaya when registering students for the cluster round.',
    school: 'Usually no fee — internal school competition before cluster round.',
};

const itemForm = useForm({
    title: '',
    class_group: '',
    age_group: '',
    participant_type: 'individual',
    fee_amount: null,
    qualify_count: null,
});

function save() {
    form.put(`/admin/state-programs/${props.program.id}`, { preserveScroll: true });
}

function publish() {
    router.post(`/admin/state-programs/${props.program.id}/publish`);
}

function addItem() {
    itemForm.post(`/admin/state-programs/${props.program.id}/items`, {
        preserveScroll: true,
        onSuccess: () => itemForm.reset({ participant_type: 'individual', fee_amount: null }),
    });
}

function removeItem(id) {
    router.delete(`/admin/state-programs/${props.program.id}/items/${id}`, { preserveScroll: true });
}
</script>
