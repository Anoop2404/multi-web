<template>
    <AdminLayout :title="program.title">
        <div class="grid lg:grid-cols-3 gap-6 max-w-6xl">
            <div class="lg:col-span-2 space-y-4">
                <form @submit.prevent="save" class="card space-y-3">
                    <div v-if="program.status !== 'published'" class="grid sm:grid-cols-2 gap-3">
                        <input v-model="form.title" class="field" required>
                        <select v-model="form.event_type" class="field">
                            <option v-for="(label, key) in eventTypes" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <p v-else class="text-sm text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                        Published — title and type are locked. You can still edit dates, venue, and description.
                    </p>

                    <div v-if="program.status !== 'published'">
                        <p class="text-xs font-semibold text-gray-600 mb-2">Conducts at</p>
                        <p v-if="form.event_type === 'sports'" class="text-xs text-gray-500 mb-2">
                            Sports: school and Sahodaya cluster only.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <label v-for="(label, key) in selectableLevelLabels" :key="key" class="flex items-center gap-2 text-sm">
                                <input type="checkbox" :value="key" v-model="form.conduct_levels">
                                {{ label }}
                            </label>
                        </div>
                    </div>
                    <div v-else class="flex flex-wrap gap-2">
                        <span v-for="lvl in program.conduct_levels" :key="lvl"
                              class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-800 text-xs">
                            {{ levelLabels[lvl] ?? lvl }}
                        </span>
                    </div>

                    <div v-if="form.conduct_levels.includes('state')" class="border rounded-xl p-3 bg-emerald-50 space-y-3">
                        <div>
                            <p class="text-xs font-semibold text-emerald-900">State handoff</p>
                            <p class="text-xs text-emerald-700 mt-0.5">Used when Sahodayas submit published Kalotsav qualifiers to the State workspace.</p>
                        </div>
                        <select v-model="form.state_domain_id" class="field">
                            <option value="">Create / configure state API endpoint</option>
                            <option v-for="domain in stateDomains" :key="domain.id" :value="domain.id">
                                {{ domain.name }} — {{ domain.api_base_url || domain.domain || domain.api_client_id }}
                            </option>
                        </select>
                        <div class="grid sm:grid-cols-2 gap-2">
                            <input v-model="form.state_domain.name" class="field" placeholder="State domain name">
                            <input v-model="form.state_domain.domain" class="field" placeholder="Public domain">
                            <input v-model="form.state_domain.api_base_url" class="field" placeholder="API base URL">
                            <input v-model="form.state_domain.api_client_id" class="field" placeholder="Client ID">
                            <input v-model="form.state_domain.api_client_secret" type="password" class="field sm:col-span-2" placeholder="Set / rotate shared API secret">
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3 text-xs">
                            <div class="space-y-2">
                                <p class="font-semibold text-emerald-900">Regional qualifiers</p>
                                <label v-for="pos in [1, 2, 3]" :key="`regional-${pos}`" class="inline-flex items-center gap-1 mr-3">
                                    <input type="checkbox" :value="pos" v-model="form.qualifier_policy.regional.positions">
                                    Position {{ pos }}
                                </label>
                            </div>
                            <div class="space-y-2">
                                <p class="font-semibold text-emerald-900">District qualifiers</p>
                                <label v-for="pos in [1, 2, 3]" :key="`district-${pos}`" class="inline-flex items-center gap-1 mr-3">
                                    <input type="checkbox" :value="pos" v-model="form.qualifier_policy.district.positions">
                                    Position {{ pos }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Per-level fees -->
                    <div v-if="form.conduct_levels.filter(l => l !== 'state').length" class="border rounded-xl p-3 bg-gray-50 space-y-3">
                        <div>
                            <p class="text-xs font-semibold text-gray-700">Fees by level</p>
                            <p class="text-xs text-gray-500 mt-0.5">Sahodaya cluster round uses CKSC tiered billing by default.</p>
                        </div>
                        <div v-for="lvl in form.conduct_levels.filter(l => l !== 'state')" :key="lvl"
                             class="card p-3 space-y-2">
                            <p class="text-sm font-semibold text-gray-800">{{ levelLabels[lvl] ?? lvl }}</p>
                            <p class="text-xs text-gray-500">{{ levelFeeHints[lvl] }}</p>
                            <select v-model="form.level_fees[lvl].fee_model" class="field">
                                <option v-for="(label, key) in feeTypes" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <div v-if="form.level_fees[lvl].fee_model === 'cksc_tiered'" class="grid sm:grid-cols-2 gap-2 text-xs">
                                <input v-model.number="form.level_fees[lvl].first_item" type="number" min="0" class="field" placeholder="First item (₹)">
                                <input v-model.number="form.level_fees[lvl].additional_item" type="number" min="0" class="field" placeholder="Additional item (₹)">
                            </div>
                            <div v-else-if="form.level_fees[lvl].fee_model === 'item_catalog'" class="space-y-2 text-xs">
                                <template v-if="form.event_type === 'sports'">
                                    <div class="grid sm:grid-cols-2 gap-2">
                                        <input v-for="(agLabel, agKey) in ageGroupLabels" :key="agKey"
                                               v-model.number="form.level_fees[lvl].age_group_fees[agKey]"
                                               type="number" min="0" class="field" :placeholder="agLabel + ' (₹)'">
                                    </div>
                                </template>
                                <template v-else>
                                    <select v-model="form.level_fees[lvl].class_group_scheme" class="field">
                                        <option v-for="(label, key) in classGroupSchemeOptions" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                    <div class="grid sm:grid-cols-3 gap-2">
                                        <input v-for="(cgLabel, cgKey) in classGroupLabelsForLevel(lvl)" :key="cgKey"
                                               v-model.number="form.level_fees[lvl].class_group_fees[cgKey]"
                                               type="number" min="0" class="field" :placeholder="cgLabel + ' (₹)'">
                                    </div>
                                </template>
                                <div class="grid sm:grid-cols-3 gap-2">
                                    <input v-model.number="form.level_fees[lvl].participant_type_fees.group" type="number" min="0" class="field" placeholder="Group items (₹)">
                                    <input v-model.number="form.level_fees[lvl].participant_type_fees.team" type="number" min="0" class="field" placeholder="Team items (₹)">
                                    <input v-model.number="form.level_fees[lvl].default_item_fee" type="number" min="0" class="field" placeholder="Default fallback (₹)">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Per-level participation policies -->
                    <div v-if="form.conduct_levels.filter(l => l !== 'state').length" class="border rounded-xl p-3 bg-violet-50 space-y-3">
                        <p class="text-xs font-semibold text-violet-900">Participation limits by level</p>
                        <div v-for="lvl in form.conduct_levels.filter(l => l !== 'state')" :key="`pol-${lvl}`"
                             class="card p-3 space-y-2">
                            <p class="text-sm font-semibold">{{ levelLabels[lvl] ?? lvl }}</p>
                            <select v-model="form.level_policies[lvl].preset_key" class="field">
                                <option value="">Custom</option>
                                <option v-for="(preset, key) in participationPresets" :key="key" :value="key">{{ preset.label }}</option>
                            </select>
                            <div v-if="!form.level_policies[lvl].preset_key" class="grid sm:grid-cols-3 gap-2">
                                <input v-model.number="form.level_policies[lvl].max_onstage_per_student" type="number" min="0" class="field" placeholder="On-stage/student">
                                <input v-model.number="form.level_policies[lvl].max_offstage_per_student" type="number" min="0" class="field" placeholder="Off-stage/student">
                                <input v-model.number="form.level_policies[lvl].max_group_per_student" type="number" min="0" class="field" placeholder="Group/student">
                            </div>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3">
                        <input v-model="form.registration_open" type="date" class="field" placeholder="Reg. open">
                        <input v-model="form.registration_close" type="date" class="field" placeholder="Reg. close">
                        <input v-model="form.event_start" type="date" class="field">
                        <input v-model="form.event_end" type="date" class="field">
                        <input v-model="form.venue" class="field sm:col-span-2" placeholder="Venue">
                    </div>
                    <textarea v-model="form.description" class="field" rows="3" placeholder="Description"></textarea>
                    <button class="btn-primary">Save</button>
                </form>

                <div class="card">
                    <h3 class="font-semibold mb-1">State catalog items</h3>
                    <p class="text-xs text-gray-500 mb-3">Optional — add items here before or after publish. Sahodayas and schools inherit these.</p>
                    <form @submit.prevent="addItem" class="grid sm:grid-cols-2 gap-2 mb-4">
                        <input v-model="itemForm.title" class="field sm:col-span-2" placeholder="Item name" required>
                        <input v-model.number="itemForm.fee_amount" type="number" min="0" class="field" placeholder="Item fee (₹) — optional">
                        <select v-if="form.event_type === 'sports'" v-model="itemForm.age_group" class="field">
                            <option value="">Age group</option>
                            <option v-for="(label, key) in ageGroupLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <select v-else v-model="itemForm.class_group" class="field">
                            <option value="">Class category</option>
                            <option v-for="(label, key) in taxonomy.class_group" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <select v-model="itemForm.participant_type" class="field">
                            <option value="individual">Individual</option>
                            <option value="group">Group</option>
                            <option value="team">Team</option>
                        </select>
                        <button class="btn-primary sm:col-span-2">Add state item</button>
                    </form>
                    <ul v-if="program.items?.length" class="divide-y border rounded-lg text-sm">
                        <li v-for="item in program.items" :key="item.id" class="py-2 px-3 flex justify-between gap-2">
                            <span>{{ item.title }}<span v-if="item.fee_amount != null" class="text-gray-400"> · ₹{{ item.fee_amount }}</span></span>
                            <button type="button" @click="removeItem(item.id)" class="text-red-600 text-xs">Remove</button>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-400">No state items yet — publishing without items is fine.</p>
                </div>

                <div class="card">
                    <h3 class="font-semibold mb-3">Propagation log</h3>
                    <table class="w-full text-sm" v-if="program.propagations?.length">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="pb-2">Sahodaya</th>
                                <th class="pb-2">Round</th>
                                <th class="pb-2">Tenant event ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in program.propagations" :key="row.id" class="border-t">
                                <td class="py-2">{{ row.sahodaya?.name ?? row.sahodaya_id }}</td>
                                <td class="py-2">{{ levelLabels[row.level_round] ?? row.level_round }}</td>
                                <td class="py-2 font-mono text-xs">{{ row.tenant_event_id ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-gray-400">Not published yet</p>
                </div>
            </div>

            <div class="space-y-3">
                <div class="card">
                    <p class="text-xs text-gray-500 uppercase">Status</p>
                    <p class="font-semibold text-lg capitalize">{{ program.status }}</p>
                    <form v-if="program.status !== 'published'" @submit.prevent="publish" class="mt-3">
                        <button class="btn-primary w-full px-4 py-2 rounded-lg text-sm font-medium">
                            Publish to all Sahodayas
                        </button>
                    </form>
                    <form v-else @submit.prevent="publish" class="mt-3">
                        <button class="w-full px-4 py-2 border border-emerald-600 text-emerald-700 rounded-lg text-sm">
                            Re-sync missing clusters
                        </button>
                    </form>
                </div>
                <Link href="/admin/state-programs" class="block text-sm text-indigo-600">← All state programs</Link>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link, useForm, router } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
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

function buildLevelFees(program, conductLevels) {
    const fees = {};
    for (const lvl of (conductLevels ?? []).filter(l => l !== 'state')) {
        const existing = program.level_fees?.[lvl];
        const defaults = props.levelDefaults?.[lvl] ?? { fee_model: 'none' };
        const scheme = existing?.class_group_scheme ?? 'cbse';
        const defaultCg = FestClassGroupSchemeDefaults(scheme);
        fees[lvl] = {
            fee_model: existing?.fee_model ?? defaults.fee_model ?? 'none',
            class_group_scheme: scheme,
            first_item: existing?.first_item ?? defaults.first_item ?? 350,
            additional_item: existing?.additional_item ?? defaults.additional_item ?? 100,
            class_group_fees: {
                lp: existing?.class_group_fees?.lp ?? defaultCg.lp ?? 100,
                up: existing?.class_group_fees?.up ?? defaultCg.up ?? 150,
                hs: existing?.class_group_fees?.hs ?? defaultCg.hs ?? 200,
                hss: existing?.class_group_fees?.hss ?? defaultCg.hss ?? 250,
                open: existing?.class_group_fees?.open ?? defaultCg.open ?? 200,
            },
            age_group_fees: {
                u14: existing?.age_group_fees?.u14 ?? props.defaultAgeGroupFees?.u14 ?? 150,
                u17: existing?.age_group_fees?.u17 ?? props.defaultAgeGroupFees?.u17 ?? 200,
                u19: existing?.age_group_fees?.u19 ?? props.defaultAgeGroupFees?.u19 ?? 250,
                open: existing?.age_group_fees?.open ?? props.defaultAgeGroupFees?.open ?? 200,
            },
            participant_type_fees: {
                group: existing?.participant_type_fees?.group ?? 150,
                team: existing?.participant_type_fees?.team ?? 150,
            },
            default_item_fee: existing?.default_item_fee ?? '',
        };
    }
    return fees;
}

function FestClassGroupSchemeDefaults(scheme) {
    const cbse = { lp: 100, up: 150, hs: 200, hss: 250, open: 200 };
    const sahodaya = { lp: 120, up: 160, hs: 210, hss: 260, open: 200 };
    return scheme === 'sahodaya' ? sahodaya : cbse;
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

watch(() => form.event_type, (type) => {
    if (type === 'sports') {
        form.conduct_levels = form.conduct_levels.filter((l) => l !== 'state');
        if (!form.conduct_levels.length) {
            form.conduct_levels = ['school', 'sahodaya'];
        }
    }
});

const levelFeeHints = {
    sahodaya: 'School pays Sahodaya when registering students for the cluster round.',
    school: 'Usually no fee — internal school competition before cluster round.',
};

const schemeLabels = {
    cbse: {
        lp: 'Category I — Classes III & IV',
        up: 'Category II — Classes V–VII',
        hs: 'Category III — Classes VIII–X',
        hss: 'Category IV — Classes XI & XII',
        open: 'Open / All Categories',
    },
    sahodaya: {
        lp: 'LP — Classes I–IV',
        up: 'UP — Classes V–VII',
        hs: 'HS — Classes VIII–X',
        hss: 'HSS — Classes XI & XII',
        open: 'Open / All Classes',
    },
};

function classGroupLabelsForLevel(lvl) {
    const scheme = form.level_fees[lvl]?.class_group_scheme ?? 'cbse';
    return schemeLabels[scheme] ?? schemeLabels.cbse;
}

watch(() => form.conduct_levels, (levels) => {
    for (const lvl of levels.filter(l => l !== 'state')) {
        if (!form.level_fees[lvl]) {
            const defaults = props.levelDefaults?.[lvl] ?? { fee_model: 'none' };
            const scheme = 'cbse';
            const defaultCg = FestClassGroupSchemeDefaults(scheme);
            form.level_fees[lvl] = {
                fee_model: defaults.fee_model ?? 'none',
                class_group_scheme: scheme,
                first_item: defaults.first_item ?? 350,
                additional_item: defaults.additional_item ?? 100,
                class_group_fees: {
                    lp: defaultCg.lp,
                    up: defaultCg.up,
                    hs: defaultCg.hs,
                    hss: defaultCg.hss,
                    open: defaultCg.open,
                },
                age_group_fees: {
                    u14: props.defaultAgeGroupFees?.u14 ?? 150,
                    u17: props.defaultAgeGroupFees?.u17 ?? 200,
                    u19: props.defaultAgeGroupFees?.u19 ?? 250,
                    open: props.defaultAgeGroupFees?.open ?? 200,
                },
                participant_type_fees: { group: 150, team: 150 },
                default_item_fee: '',
            };
        }
        if (!form.level_policies[lvl]) {
            form.level_policies[lvl] = {
                preset_key: lvl === 'school' ? 'cksc_school_kalakriti' : 'cksc_sahodaya_cluster',
                max_onstage_per_student: '',
                max_offstage_per_student: '',
                max_group_per_student: '',
            };
        }
    }
}, { deep: true });

const itemForm = useForm({
    title: '',
    class_group: '',
    age_group: '',
    participant_type: 'individual',
    fee_amount: null,
});

function save() {
    form.put(`/admin/state-programs/${props.program.id}`);
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

