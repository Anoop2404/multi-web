<template>
    <SahodayaAdminLayout title="Circulars" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-6">

            <!-- Upload card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900">Upload New Circular</h3>
                    <button @click="showForm = !showForm"
                            class="text-xs font-semibold text-purple-600 hover:text-purple-800 transition">
                        {{ showForm ? '▲ Collapse' : '▼ Expand' }}
                    </button>
                </div>
                <form v-show="showForm" @submit.prevent="upload" class="p-6 space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="Circular Title *" class-extra="sm:col-span-2">
                            <input v-model="form.title" type="text" required class="field">
                        </Field>
                        <Field label="Circular Number">
                            <input v-model="form.circular_number" type="text" placeholder="SAH/2025/001" class="field font-mono">
                        </Field>
                        <Field label="Category">
                            <select v-model="form.category" class="field">
                                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </Field>
                        <Field label="Issue Date">
                            <input v-model="form.issued_date" type="date" class="field">
                        </Field>
                        <Field label="Academic Year">
                            <input v-model="form.academic_year" type="text" placeholder="2025-26" class="field">
                        </Field>

                        <!-- Drop zone -->
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">PDF / Document *</label>
                            <label class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed rounded-xl cursor-pointer transition"
                                   :class="dragover ? 'border-purple-400 bg-purple-50' : 'border-gray-200 hover:border-purple-300 hover:bg-gray-50'"
                                   @dragover.prevent="dragover = true"
                                   @dragleave="dragover = false"
                                   @drop.prevent="onDrop">
                                <div v-if="form.file" class="flex items-center gap-2 text-sm text-green-700 font-semibold">
                                    <span>📎</span> {{ form.file.name }}
                                    <button type="button" @click.prevent="form.file = null" class="text-red-400 hover:text-red-600">✕</button>
                                </div>
                                <div v-else class="text-center">
                                    <div class="text-2xl mb-1">📁</div>
                                    <p class="text-sm text-gray-400">Drop PDF here or <span class="text-purple-600 font-semibold">browse</span></p>
                                </div>
                                <input type="file" accept=".pdf,.doc,.docx" class="sr-only" required
                                       @change="form.file = $event.target.files[0]">
                            </label>
                        </div>
                    </div>
                    <button type="submit" :disabled="form.processing || !form.file"
                            class="px-6 py-2.5 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-sm font-bold rounded-xl transition disabled:opacity-50">
                        {{ form.processing ? 'Uploading…' : 'Upload Circular' }}
                    </button>
                </form>
            </div>

            <!-- Filter tabs -->
            <div class="flex gap-2 flex-wrap">
                <button @click="activeCategory = ''"
                        :class="activeCategory === '' ? 'bg-[#1e1b4b] text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-gray-300'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition">All ({{ circulars.length }})</button>
                <button v-for="c in categories" :key="c"
                        @click="activeCategory = c"
                        :class="activeCategory === c ? 'bg-[#1e1b4b] text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-gray-300'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition">
                    {{ c }} ({{ circulars.filter(ci => ci.category === c).length }})
                </button>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <table v-if="filtered.length" class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Title</th>
                            <th class="text-left px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Number</th>
                            <th class="text-left px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Category</th>
                            <th class="text-left px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide hidden md:table-cell">Date</th>
                            <th class="text-left px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide hidden md:table-cell">Year</th>
                            <th class="px-5 py-3 w-24"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="c in filtered" :key="c.id" class="hover:bg-gray-50/50 transition">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-red-50 flex items-center justify-center text-sm shrink-0">📄</div>
                                    <span class="font-semibold text-gray-800 line-clamp-1">{{ c.title }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 hidden sm:table-cell">
                                <span class="font-mono text-xs text-gray-400">{{ c.circular_number || '—' }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="text-[11px] font-semibold px-2 py-1 rounded-full"
                                      :class="categoryColors[c.category] ?? 'bg-gray-100 text-gray-500'">
                                    {{ c.category || 'General' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-gray-400 hidden md:table-cell">
                                {{ c.issued_date ? new Date(c.issued_date).toLocaleDateString('en-IN', {day:'2-digit',month:'short',year:'numeric'}) : '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-xs text-gray-400 hidden md:table-cell">{{ c.academic_year || '—' }}</td>
                            <td class="px-5 py-3.5 text-right space-x-3">
                                <a :href="c.file_path" target="_blank" class="text-xs text-purple-600 hover:text-purple-800 font-semibold">View ↗</a>
                                <button @click="remove(c)" class="text-xs text-red-400 hover:text-red-600 font-semibold">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="p-14 text-center">
                    <div class="text-5xl mb-3">📄</div>
                    <p class="text-gray-500 font-medium">No circulars {{ activeCategory ? `in "${activeCategory}"` : '' }}</p>
                    <p class="text-sm text-gray-400 mt-1">Upload a circular using the form above.</p>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { ref, computed, defineComponent, h } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    sahodaya:                Object,
    publicUrl:               { type: String, default: null },
    pendingSchoolsCount:     { type: Number, default: 0 },
    pendingSubmissionsCount: { type: Number, default: 0 },
    pendingPaymentsCount:    { type: Number, default: 0 },
    circulars:               { type: Array, default: () => [] },
});

const categories = ['General', 'Academic', 'Kalotsav', 'Meeting', 'Exam', 'Sports', 'Finance', 'Other'];

const categoryColors = {
    General:  'bg-gray-100 text-gray-600',
    Academic: 'bg-blue-100 text-blue-700',
    Kalotsav: 'bg-purple-100 text-purple-700',
    Meeting:  'bg-amber-100 text-amber-700',
    Exam:     'bg-orange-100 text-orange-700',
    Sports:   'bg-green-100 text-green-700',
    Finance:  'bg-teal-100 text-teal-700',
    Other:    'bg-gray-100 text-gray-500',
};

const showForm      = ref(true);
const dragover      = ref(false);
const activeCategory = ref('');

const filtered = computed(() =>
    activeCategory.value
        ? props.circulars.filter(c => c.category === activeCategory.value)
        : props.circulars
);

const form = useForm({
    title: '', circular_number: '', category: 'General',
    issued_date: '', academic_year: '', file: null,
});

function onDrop(e) {
    dragover.value = false;
    const f = e.dataTransfer.files[0];
    if (f) form.file = f;
}

function upload() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/circulars`, {
        forceFormData: true,
        onSuccess: () => { form.reset(); showForm.value = false; },
    });
}

function remove(c) {
    if (!confirm(`Delete "${c.title}"?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/circulars/${c.id}`);
}

const Field = defineComponent({
    props: { label: String, classExtra: String },
    setup(props, { slots }) {
        return () => h('div', { class: props.classExtra ?? '' }, [
            props.label ? h('label', { class: 'block text-xs font-semibold text-gray-600 mb-1.5' }, props.label) : null,
            slots.default?.(),
        ]);
    },
});
</script>

<style scoped>
@reference "../../../../../css/app.css";
.field {
    @apply w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300 bg-white;
}
</style>
