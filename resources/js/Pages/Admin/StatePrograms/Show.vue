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

                    <div class="grid sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Program Title *</label>
                            <input v-model="form.title" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-sm font-medium" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Program Status *</label>
                            <select v-model="form.status" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-sm font-medium">
                                <option value="published">🟢 Published / Active</option>
                                <option value="draft">🟡 Draft (Setup mode)</option>
                                <option value="inactive">🔴 Inactive (Disabled)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Event Type *</label>
                            <select v-model="form.event_type" :disabled="program.status === 'published'" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-sm font-medium">
                                <option v-for="(label, key) in eventTypes" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
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
                                <select v-model="itemForm.class_group" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                    <option value="">Select Category / Group</option>
                                    <option value="category_1">Category 1 — Classes 3 & 4 (LP)</option>
                                    <option value="category_2">Category 2 — Classes 5, 6 & 7 (UP)</option>
                                    <option value="category_3">Category 3 — Classes 8, 9 & 10 (HS)</option>
                                    <option value="category_4">Category 4 — Classes 11 & 12 (HSS)</option>
                                    <option value="category_5">Category 5 — Group Items (Open)</option>
                                    <option value="open">Open / All Categories</option>
                                    <option value="lp">LP (Category 1)</option>
                                    <option value="up">UP (Category 2)</option>
                                    <option value="hs">HS (Category 3)</option>
                                    <option value="hss">HSS (Category 4)</option>
                                </select>
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
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="item in program.items" :key="item.id" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="py-3 px-4 font-bold text-slate-900">
                                        {{ item.title }}
                                        <span class="block text-xs font-normal text-slate-400">Code: {{ item.item_code || 'Auto' }}</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-800 border border-slate-200/80">
                                            {{ formatClassGroup(item.class_group || item.age_group) }}
                                        </span>
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
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button" @click="openEditItemModal(item)" class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs transition border border-indigo-200/60">
                                                ✏️ Edit
                                            </button>
                                            <button type="button" @click="removeItem(item.id)" class="px-2.5 py-1 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 font-bold text-xs transition border border-red-200/60">
                                                Remove
                                            </button>
                                        </div>
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

                <!-- EDIT ITEM MODAL -->
                <div v-if="isEditingItem" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full p-6 space-y-5 border border-slate-100" @click.stop>
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">Edit Catalog Item</h3>
                                <p class="text-xs text-slate-500">Update item details, category, fees, and state qualifier settings.</p>
                            </div>
                            <button type="button" @click="closeEditItemModal" class="text-slate-400 hover:text-slate-600 text-xl font-bold">✕</button>
                        </div>

                        <form @submit.prevent="updateItem" class="space-y-4">
                            <div class="grid sm:grid-cols-2 gap-3">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Item Title *</label>
                                    <input v-model="editItemForm.title" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" required>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Item Code</label>
                                    <input v-model="editItemForm.item_code" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" placeholder="e.g. 501">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Category / Group *</label>
                                    <select v-model="editItemForm.class_group" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                        <option value="">Select Category</option>
                                        <option value="category_1">Category 1 — Classes 3 & 4 (LP)</option>
                                        <option value="category_2">Category 2 — Classes 5, 6 & 7 (UP)</option>
                                        <option value="category_3">Category 3 — Classes 8, 9 & 10 (HS)</option>
                                        <option value="category_4">Category 4 — Classes 11 & 12 (HSS)</option>
                                        <option value="category_5">Category 5 — Group Items (Open)</option>
                                        <option value="open">Open / All Categories</option>
                                        <option value="lp">LP (Category 1)</option>
                                        <option value="up">UP (Category 2)</option>
                                        <option value="hs">HS (Category 3)</option>
                                        <option value="hss">HSS (Category 4)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Discipline Category</label>
                                    <select v-model="editItemForm.category" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                        <option value="music">Music</option>
                                        <option value="dance">Dance</option>
                                        <option value="drama">Drama / Theatre</option>
                                        <option value="literary">Literary</option>
                                        <option value="fine_arts">Fine Arts</option>
                                        <option value="traditional">Traditional / Folk</option>
                                        <option value="sports">Sports</option>
                                        <option value="general">General</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Participant Type</label>
                                    <select v-model="editItemForm.participant_type" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                        <option value="individual">Individual</option>
                                        <option value="group">Group</option>
                                        <option value="team">Team</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Stage Type</label>
                                    <select v-model="editItemForm.stage_type" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                        <option value="on_stage">On Stage</option>
                                        <option value="off_stage">Off Stage</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Gender Restriction</label>
                                    <select v-model="editItemForm.gender" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium">
                                        <option value="open">Open (All)</option>
                                        <option value="male">Boys Only</option>
                                        <option value="female">Girls Only</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Qualifiers to State</label>
                                    <input v-model.number="editItemForm.qualify_count" type="number" min="1" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" placeholder="2">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Item Fee (₹)</label>
                                    <input v-model.number="editItemForm.fee_amount" type="number" min="0" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm font-medium" placeholder="Optional">
                                </div>

                                <div v-if="editItemForm.participant_type === 'group' || editItemForm.participant_type === 'team'" class="sm:col-span-2 grid sm:grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200/80">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Min Group Size</label>
                                        <input v-model.number="editItemForm.min_group_size" type="number" min="1" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Max Group Size</label>
                                        <input v-model.number="editItemForm.max_group_size" type="number" min="1" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-sm">
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                                <button type="button" @click="closeEditItemModal" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="editItemForm.processing" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition shadow-md">
                                    Save Item Changes
                                </button>
                            </div>
                        </form>
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

            <!-- TAB 4: Cluster Propagation & Sahodaya Item Visibility Controls -->
            <div v-show="activeTab === 'clusters'" class="space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Sahodaya Cluster Controls & Item Visibility</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Control program status and enable/hide specific state items per Sahodaya complex.</p>
                        </div>
                        <span class="text-xs font-bold text-slate-500">{{ allSahodayas.length }} cluster(s) available</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider font-semibold border-b border-slate-100">
                                    <th class="py-3 px-4">Sahodaya Complex</th>
                                    <th class="py-3 px-4">Deployment Status</th>
                                    <th class="py-3 px-4">Program Status</th>
                                    <!-- STATE_SAHODAYA_RULE_BOUNDARY_FIX_PLAN — Set 1, Item 3 -->
                                    <th class="py-3 px-4">Customisation</th>
                                    <th class="py-3 px-4 text-right">State Item Visibility Controls</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="cluster in allSahodayas" :key="cluster.id" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="py-3.5 px-4 font-bold text-slate-900">
                                        {{ cluster.name }}
                                        <span v-if="cluster.subdomain" class="block font-mono text-xs font-normal text-slate-400">{{ cluster.subdomain }}.truecampus.in</span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span v-if="cluster.deployed" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-200">
                                            ● Deployed
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-500 text-xs font-medium border border-slate-200">
                                            ○ Not Deployed
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <button v-if="cluster.is_enabled" type="button" @click="toggleSahodaya(cluster, false)" class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold border border-emerald-200 transition">
                                            🟢 Active — Click to Disable
                                        </button>
                                        <button v-else type="button" @click="toggleSahodaya(cluster, true)" class="px-3 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold border border-rose-200 transition">
                                            🔴 Disabled — Click to Enable
                                        </button>
                                    </td>
                                    <!-- STATE_SAHODAYA_RULE_BOUNDARY_FIX_PLAN — Set 1, Item 3 -->
                                    <td class="py-3.5 px-4">
                                        <span v-if="cluster.sahodaya_customized_at"
                                              class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 text-xs font-bold border border-amber-200"
                                              :title="`This Sahodaya has locally customised their event settings (title, dates, venue, fee, or description). Re-publishing this program will NOT overwrite their changes. Customised since: ${new Date(cluster.sahodaya_customized_at).toLocaleDateString()}.`">
                                            🔧 Customised · {{ new Date(cluster.sahodaya_customized_at).toLocaleDateString() }}
                                        </span>
                                        <span v-else-if="cluster.deployed" class="text-xs text-slate-400 font-medium">✓ Synced to State</span>
                                        <span v-else class="text-xs text-slate-300">—</span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <button v-if="cluster.deployed" type="button" @click="openSahodayaItemModal(cluster)" class="px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold border border-indigo-200 transition">
                                            🎯 Manage Sahodaya Items →
                                        </button>
                                        <span v-else class="text-xs text-slate-400 font-medium">Publish program first</span>
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

            <!-- Sahodaya Item Visibility Modal -->
            <div v-if="sahodayaModalOpen" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[85vh] flex flex-col shadow-2xl overflow-hidden border border-slate-200">
                    <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-base">🎯 {{ activeSahodaya?.name }} — Item Visibility Matrix</h3>
                            <p class="text-xs text-slate-300">Enable or hide specific state items for this Sahodaya complex.</p>
                        </div>
                        <button type="button" @click="sahodayaModalOpen = false" class="text-slate-400 hover:text-white text-xl font-bold px-2">✕</button>
                    </div>

                    <div class="p-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                        <input v-model="itemSearch" placeholder="Search state items by title or code..." class="px-3.5 py-1.5 rounded-xl border border-slate-300 text-xs font-medium w-full sm:w-64">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="bulkToggleItemsForSahodaya(true)" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition">
                                Enable All Items
                            </button>
                            <button type="button" @click="bulkToggleItemsForSahodaya(false)" class="px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition">
                                Hide All Items
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-5">
                        <div v-if="loadingItems" class="text-center py-12 text-slate-500 text-sm font-medium">
                            ⏳ Loading Sahodaya items...
                        </div>
                        <div v-else-if="!filteredSahodayaItems.length" class="text-center py-12 text-slate-400 text-sm">
                            No matching items found.
                        </div>
                        <div v-else class="grid sm:grid-cols-2 gap-3">
                            <div v-for="item in filteredSahodayaItems" :key="item.id" class="p-3.5 rounded-xl border transition flex items-center justify-between gap-3" :class="item.is_enabled ? 'bg-white border-slate-200' : 'bg-rose-50/40 border-rose-200/60 opacity-75'">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span v-if="item.item_code" class="font-mono text-[10px] font-bold bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded">{{ item.item_code }}</span>
                                        <p class="font-bold text-xs text-slate-900 truncate">{{ item.title }}</p>
                                    </div>
                                    <p class="text-[10px] text-slate-500 capitalize mt-0.5">{{ item.category || 'General' }} · {{ item.class_group || 'Open' }}</p>
                                </div>
                                <button type="button" @click="toggleItemForSahodaya(item, !item.is_enabled)" class="px-2.5 py-1 rounded-lg text-xs font-bold transition whitespace-nowrap" :class="item.is_enabled ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-rose-100 text-rose-800 hover:bg-rose-200'">
                                    {{ item.is_enabled ? '✓ Enabled' : '✕ Hidden' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                        <button type="button" @click="sahodayaModalOpen = false" class="px-5 py-2 rounded-xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800">
                            Done
                        </button>
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
    allSahodayas: { type: Array, default: () => [] },
});

const activeTab = ref('general');
const sahodayaModalOpen = ref(false);
const activeSahodaya = ref(null);
const sahodayaItems = ref([]);
const loadingItems = ref(false);
const itemSearch = ref('');

function toggleSahodaya(cluster, enabled) {
    router.post(`/admin/state-programs/${props.program.id}/sahodaya/${cluster.id}/toggle`, {
        enabled: enabled,
    }, { preserveScroll: true });
}

async function openSahodayaItemModal(cluster) {
    activeSahodaya.value = cluster;
    sahodayaModalOpen.value = true;
    loadingItems.value = true;
    try {
        const res = await fetch(`/admin/state-programs/${props.program.id}/sahodaya/${cluster.id}/items`);
        const json = await res.json();
        sahodayaItems.value = json.items || [];
    } catch (e) {
        sahodayaItems.value = [];
    } finally {
        loadingItems.value = false;
    }
}

function toggleItemForSahodaya(item, enabled) {
    item.is_enabled = enabled;
    router.post(`/admin/state-programs/${props.program.id}/sahodaya/${activeSahodaya.value.id}/items/item/${item.id}/toggle`, {
        enabled: enabled,
    }, { preserveScroll: true });
}

function bulkToggleItemsForSahodaya(enabled) {
    sahodayaItems.value.forEach(i => i.is_enabled = enabled);
    router.post(`/admin/state-programs/${props.program.id}/sahodaya/${activeSahodaya.value.id}/items/bulk-toggle`, {
        enabled: enabled,
    }, { preserveScroll: true });
}

const filteredSahodayaItems = computed(() => {
    if (!itemSearch.value) return sahodayaItems.value;
    const term = itemSearch.value.toLowerCase();
    return sahodayaItems.value.filter(i => (i.title || '').toLowerCase().includes(term) || (i.item_code || '').toLowerCase().includes(term));
});

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
    status: props.program.status ?? 'draft',
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

function formatClassGroup(code) {
    if (!code) return 'Open / All';
    const normalized = String(code).toLowerCase().trim();
    const map = {
        'category_1': 'Category 1 (Classes 3-4)',
        'category_2': 'Category 2 (Classes 5-7)',
        'category_3': 'Category 3 (Classes 8-10)',
        'category_4': 'Category 4 (Classes 11-12)',
        'category_5': 'Category 5 (Group)',
        'lp': 'Category 1 (LP)',
        'up': 'Category 2 (UP)',
        'hs': 'Category 3 (HS)',
        'hss': 'Category 4 (HSS)',
        'open': 'Open / All',
    };
    if (map[normalized]) return map[normalized];
    return normalized.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

const editingItem = ref(null);
const isEditingItem = ref(false);

const editItemForm = useForm({
    title: '',
    item_code: '',
    category: 'general',
    class_group: '',
    age_group: '',
    participant_type: 'individual',
    stage_type: 'on_stage',
    gender: 'open',
    qualify_count: 2,
    fee_amount: null,
    min_group_size: null,
    max_group_size: null,
});

function openEditItemModal(item) {
    editingItem.value = item;
    editItemForm.title = item.title ?? '';
    editItemForm.item_code = item.item_code ?? '';
    editItemForm.category = item.category ?? 'general';
    editItemForm.class_group = item.class_group ?? '';
    editItemForm.age_group = item.age_group ?? '';
    editItemForm.participant_type = item.participant_type ?? 'individual';
    editItemForm.stage_type = item.stage_type ?? 'on_stage';
    editItemForm.gender = item.gender ?? 'open';
    editItemForm.qualify_count = item.qualify_count ?? 2;
    editItemForm.fee_amount = item.fee_amount ?? null;
    editItemForm.min_group_size = item.min_group_size ?? null;
    editItemForm.max_group_size = item.max_group_size ?? null;
    isEditingItem.value = true;
}

function closeEditItemModal() {
    isEditingItem.value = false;
    editingItem.value = null;
    editItemForm.reset();
}

function updateItem() {
    if (!editingItem.value) return;
    editItemForm.put(`/admin/state-programs/${props.program.id}/items/${editingItem.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeEditItemModal(),
    });
}

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
