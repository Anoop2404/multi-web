<template>
    <SahodayaAdminLayout :title="event.name" :sahodaya="sahodaya">
        <div class="space-y-6">
            <!-- Event header card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">{{ event.name }}</h2>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500 mt-1">
                            <span v-if="event.academic_year">{{ event.academic_year }}</span>
                            <span v-if="event.venue">📍 {{ event.venue }}</span>
                            <span v-if="event.event_date">
                                📅 {{ new Date(event.event_date).toLocaleDateString('en-IN') }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span :class="event.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-400'"
                              class="text-xs px-2 py-1 rounded-full font-semibold">
                            {{ event.is_active ? '● Active' : '○ Inactive' }}
                        </span>
                        <span v-if="event.results_published"
                              class="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded-full font-semibold">
                            Results Published
                        </span>
                    </div>
                </div>

                <!-- Event settings -->
                <div class="mt-4 pt-4 border-t border-gray-50 flex items-center gap-4">
                    <button @click="toggleActive"
                            class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition"
                            :class="event.is_active
                                ? 'border-gray-200 text-gray-500 hover:bg-gray-50'
                                : 'border-green-200 text-green-600 hover:bg-green-50'">
                        {{ event.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                    <button @click="togglePublish"
                            class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition"
                            :class="event.results_published
                                ? 'border-gray-200 text-gray-500 hover:bg-gray-50'
                                : 'border-blue-200 text-blue-600 hover:bg-blue-50'">
                        {{ event.results_published ? 'Unpublish Results' : 'Publish Results' }}
                    </button>
                    <button @click="deleteEvent"
                            class="text-xs text-red-400 hover:text-red-600 transition ml-auto">
                        Delete Event
                    </button>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Left: Categories + Results entry -->
                <div class="lg:col-span-2 space-y-5">

                    <!-- Add Category -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h3 class="font-bold text-gray-800 mb-3">Add Category / Item</h3>
                        <form @submit.prevent="addCategory" class="flex flex-wrap gap-3 items-end">
                            <div class="flex-1 min-w-40">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Category Name *</label>
                                <input v-model="catForm.name" type="text" required placeholder="Classical Dance Solo"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                            </div>
                            <div class="w-32">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Group</label>
                                <input v-model="catForm.group" type="text" placeholder="A / B / Junior"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                            </div>
                            <div class="w-28">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Max Points</label>
                                <input v-model="catForm.max_points" type="number" min="0" placeholder="5"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                            </div>
                            <button type="submit" :disabled="catForm.processing"
                                    class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-purple-700 transition disabled:opacity-50 shrink-0">
                                Add
                            </button>
                        </form>
                    </div>

                    <!-- Categories with results entry -->
                    <div v-for="cat in event.categories" :key="cat.id"
                         class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-100">
                            <div>
                                <span class="font-semibold text-gray-800 text-sm">{{ cat.name }}</span>
                                <span v-if="cat.group" class="ml-2 text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded-full">
                                    {{ cat.group }}
                                </span>
                                <span v-if="cat.max_points" class="ml-1 text-xs text-gray-400">
                                    (max {{ cat.max_points }} pts)
                                </span>
                            </div>
                            <button @click="deleteCategory(cat)"
                                    class="text-xs text-red-400 hover:text-red-600">Remove</button>
                        </div>

                        <!-- Results for this category -->
                        <div class="p-4">
                            <!-- Existing results -->
                            <div v-if="cat.results?.length" class="mb-3 space-y-1.5">
                                <div v-for="r in cat.results" :key="r.id"
                                     class="flex items-center gap-3 text-sm py-1.5 px-2 rounded-lg hover:bg-gray-50">
                                    <span class="font-semibold text-gray-700 w-4">{{ r.position }}</span>
                                    <span class="flex-1 text-gray-600">{{ r.school_name }}</span>
                                    <span class="text-purple-600 font-semibold w-12 text-right">{{ r.points }} pts</span>
                                    <span v-if="r.grade" class="text-xs text-gray-400 w-8">{{ r.grade }}</span>
                                    <button @click="deleteResult(r)"
                                            class="text-xs text-red-300 hover:text-red-500">✕</button>
                                </div>
                            </div>

                            <!-- Quick result entry -->
                            <form @submit.prevent="addResult(cat)" class="flex flex-wrap gap-2 items-end">
                                <div class="w-full">
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">School *</label>
                                    <select v-model="resultForms[cat.id].school_tenant_id"
                                            @change="onSchoolChange(cat.id)"
                                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300 bg-white">
                                        <option value="">— Select school —</option>
                                        <option v-for="s in memberSchools" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </select>
                                </div>
                                <div class="flex gap-2 w-full">
                                    <div class="w-20">
                                        <label class="block text-xs font-semibold text-gray-500 mb-1">Pos</label>
                                        <input v-model="resultForms[cat.id].position" type="text" placeholder="1st"
                                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                                    </div>
                                    <div class="w-20">
                                        <label class="block text-xs font-semibold text-gray-500 mb-1">Points</label>
                                        <input v-model="resultForms[cat.id].points" type="number" min="0" step="0.5"
                                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                                    </div>
                                    <div class="w-20">
                                        <label class="block text-xs font-semibold text-gray-500 mb-1">Grade</label>
                                        <input v-model="resultForms[cat.id].grade" type="text" placeholder="A"
                                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                                    </div>
                                    <div class="flex items-end">
                                        <button type="submit"
                                                class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-purple-700 transition">
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div v-if="!event.categories?.length"
                         class="bg-white rounded-xl border border-dashed border-gray-200 p-8 text-center text-gray-400 text-sm">
                        No categories yet. Add categories above to start entering results.
                    </div>
                </div>

                <!-- Right: Scoreboard -->
                <div class="space-y-4">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-50">
                            <h3 class="font-bold text-gray-800">Scoreboard</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Overall school points</p>
                        </div>
                        <div v-if="scoreboard.length" class="divide-y divide-gray-50">
                            <div v-for="(row, i) in scoreboard" :key="row.school_tenant_id"
                                 class="flex items-center gap-3 px-5 py-3">
                                <span class="text-lg font-bold"
                                      :class="i === 0 ? 'text-yellow-500' : i === 1 ? 'text-gray-400' : i === 2 ? 'text-amber-600' : 'text-gray-300'">
                                    {{ i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `#${i + 1}` }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-700 truncate">{{ row.school_name }}</p>
                                </div>
                                <span class="text-sm font-bold text-purple-700">{{ row.total_points }}</span>
                            </div>
                        </div>
                        <div v-else class="px-5 py-8 text-center text-sm text-gray-400">
                            No results entered yet.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { reactive } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    sahodaya:      Object,
    event:         Object,
    memberSchools: { type: Array, default: () => [] },
    scoreboard:    { type: Array, default: () => [] },
});

// ── Category form ────────────────────────────────────────────
const catForm = useForm({
    name:       '',
    group:      '',
    max_points: '',
});

function addCategory() {
    catForm.post(`/sahodaya-admin/${props.sahodaya.id}/kalotsav/${props.event.id}/categories`, {
        onSuccess: () => catForm.reset(),
    });
}

function deleteCategory(cat) {
    if (!confirm(`Remove category "${cat.name}"? All its results will also be deleted.`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/kalotsav/${props.event.id}/categories/${cat.id}`);
}

// ── Per-category result forms ─────────────────────────────────
const resultForms = reactive({});
if (props.event.categories) {
    props.event.categories.forEach(cat => {
        resultForms[cat.id] = {
            school_tenant_id: '',
            school_name:      '',
            position:         '',
            points:           '',
            grade:            '',
        };
    });
}

function onSchoolChange(catId) {
    const schoolId = resultForms[catId].school_tenant_id;
    const school   = props.memberSchools.find(s => s.id === schoolId);
    resultForms[catId].school_name = school?.name ?? '';
}

function addResult(cat) {
    const data = { ...resultForms[cat.id], kalotsav_category_id: cat.id };
    if (!data.school_tenant_id) return;

    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/kalotsav/${props.event.id}/results`,
        data,
        {
            preserveScroll: true,
            onSuccess: () => {
                resultForms[cat.id].school_tenant_id = '';
                resultForms[cat.id].school_name      = '';
                resultForms[cat.id].position         = '';
                resultForms[cat.id].points           = '';
                resultForms[cat.id].grade            = '';
            },
        }
    );
}

function deleteResult(result) {
    router.delete(
        `/sahodaya-admin/${props.sahodaya.id}/kalotsav/${props.event.id}/results/${result.id}`,
        { preserveScroll: true }
    );
}

// ── Event status toggles ──────────────────────────────────────
function toggleActive() {
    router.put(
        `/sahodaya-admin/${props.sahodaya.id}/kalotsav/${props.event.id}`,
        { ...props.event, is_active: !props.event.is_active },
        { preserveScroll: true }
    );
}

function togglePublish() {
    router.put(
        `/sahodaya-admin/${props.sahodaya.id}/kalotsav/${props.event.id}`,
        { ...props.event, results_published: !props.event.results_published },
        { preserveScroll: true }
    );
}

function deleteEvent() {
    if (!confirm(`Delete event "${props.event.name}" and ALL its categories and results?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/kalotsav/${props.event.id}`);
}
</script>
