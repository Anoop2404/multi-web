<template>
    <SahodayaAdminLayout title="Circulars" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount"
                         :show-header-title="false">
        <div class="max-w-full overflow-x-hidden">
        <PageHeader
            title="Circulars"
            eyebrow="Membership"
            description="Upload PDF circulars for member schools. Track acknowledgements per school."
        >
            <template #actions>
                <button type="button" class="btn-secondary text-sm" @click="showForm = !showForm">
                    {{ showForm ? 'Hide upload form' : 'Upload circular' }}
                </button>
            </template>
        </PageHeader>

        <form v-show="showForm" @submit.prevent="upload" class="card mb-6 space-y-4">
            <h3 class="section-title">Upload new circular</h3>
            <FormGrid>
                <FormField label="Circular title" class-extra="sm:col-span-2" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.title" type="text" required class="field">
                    </template>
                </FormField>
                <FormField label="Circular number">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.circular_number" type="text" placeholder="SAH/2025/001" class="field font-mono">
                    </template>
                </FormField>
                <FormField label="Category">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.category" class="field">
                            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Issue date">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.issued_date" type="date" class="field">
                    </template>
                </FormField>
                <FormField label="Academic year">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.academic_year" type="text" placeholder="2025-26" class="field">
                    </template>
                </FormField>
                <FormField label="PDF / document" class-extra="sm:col-span-2" required>
                    <template #default="{ id }">
                        <label :for="id"
                               class="flex flex-col items-center justify-center w-full min-h-[7rem] border-2 border-dashed rounded-xl cursor-pointer transition"
                               :class="dragover ? 'border-violet-400 bg-violet-50' : 'border-slate-200 hover:border-violet-300 hover:bg-slate-50'"
                               @dragover.prevent="dragover = true"
                               @dragleave="dragover = false"
                               @drop.prevent="onDrop">
                            <div v-if="form.file" class="flex items-center gap-2 text-sm text-emerald-700 font-semibold">
                                <span>{{ form.file.name }}</span>
                                <button type="button" @click.prevent="form.file = null" class="text-red-500 hover:text-red-700">Remove</button>
                            </div>
                            <div v-else class="text-center px-4">
                                <p class="text-sm text-slate-500">Drop PDF here or <span class="text-violet-600 font-semibold">browse</span></p>
                            </div>
                            <input :id="id" type="file" accept=".pdf,.doc,.docx" class="sr-only" required
                                   @change="form.file = $event.target.files[0]">
                        </label>
                    </template>
                </FormField>
            </FormGrid>
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="form.processing || !form.file">
                    {{ form.processing ? 'Uploading…' : 'Upload circular' }}
                </button>
            </FormActions>
        </form>

        <div class="flex flex-wrap gap-2 mb-4">
            <button type="button" @click="activeCategory = ''"
                    :class="activeCategory === '' ? 'btn-primary !py-1.5 !px-3 text-xs' : 'btn-secondary !py-1.5 !px-3 text-xs'">
                All ({{ circulars.length }})
            </button>
            <button v-for="c in categories" :key="c" type="button"
                    @click="activeCategory = c"
                    :class="activeCategory === c ? 'btn-primary !py-1.5 !px-3 text-xs' : 'btn-secondary !py-1.5 !px-3 text-xs'">
                {{ c }} ({{ circulars.filter(ci => ci.category === c).length }})
            </button>
        </div>

        <div class="flex flex-wrap gap-3 items-center mb-4">
            <input v-model="searchQuery" type="search" class="field flex-1 min-w-[12rem] max-w-md"
                   placeholder="Search title or number…" autocomplete="off">
            <button v-if="searchQuery.trim()" type="button" class="btn-secondary text-sm" @click="searchQuery = ''">Clear</button>
        </div>

        <p class="text-xs text-slate-500 mb-3">
            {{ filtered.length }} circular{{ filtered.length === 1 ? '' : 's' }} shown
        </p>

        <div class="form-section overflow-hidden !p-0">
            <EmptyState v-if="!filtered.length" title="No circulars"
                        :description="searchQuery.trim() ? 'Try another search term.' : 'Upload a circular using the form above.'"
                        icon="📄" class="p-10" />
            <div v-else class="overflow-x-auto">
                <table class="data-table min-w-[640px]">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th class="hidden sm:table-cell">Number</th>
                            <th>Category</th>
                            <th class="hidden md:table-cell">Date</th>
                            <th class="hidden lg:table-cell">Ack</th>
                            <th class="w-28 text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in filtered" :key="c.id">
                            <td>
                                <p class="font-medium text-slate-900 line-clamp-2">{{ c.title }}</p>
                            </td>
                            <td class="hidden sm:table-cell font-mono text-xs text-slate-500">{{ c.circular_number || '—' }}</td>
                            <td>
                                <span class="text-[11px] font-semibold px-2 py-1 rounded-full"
                                      :class="categoryColors[c.category] ?? 'bg-slate-100 text-slate-600'">
                                    {{ c.category || 'General' }}
                                </span>
                            </td>
                            <td class="hidden md:table-cell text-xs text-slate-500">
                                {{ c.issued_date ? new Date(c.issued_date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—' }}
                            </td>
                            <td class="hidden lg:table-cell text-xs">
                                <span class="font-semibold" :class="c.ack_count >= c.school_count ? 'text-emerald-600' : 'text-amber-600'">
                                    {{ c.ack_count ?? 0 }}/{{ c.school_count ?? 0 }}
                                </span>
                            </td>
                            <td class="text-right whitespace-nowrap space-x-2">
                                <a :href="c.file_path" target="_blank" rel="noopener" class="link-brand text-xs">View</a>
                                <button type="button" @click="remove(c)" class="text-xs text-red-600 hover:text-red-800">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number,
    pendingPaymentsCount: Number,
    circulars: { type: Array, default: () => [] },
});

const categories = ['General', 'Academic', 'Kalotsav', 'Meeting', 'Exam', 'Sports', 'Finance', 'Other'];

const categoryColors = {
    General: 'bg-slate-100 text-slate-600',
    Academic: 'bg-blue-100 text-blue-700',
    Kalotsav: 'bg-purple-100 text-purple-700',
    Meeting: 'bg-amber-100 text-amber-700',
    Exam: 'bg-orange-100 text-orange-700',
    Sports: 'bg-green-100 text-green-700',
    Finance: 'bg-teal-100 text-teal-700',
    Other: 'bg-slate-100 text-slate-500',
};

const showForm = ref(false);
const dragover = ref(false);
const activeCategory = ref('');
const searchQuery = ref('');

const filtered = computed(() => {
    let rows = props.circulars;
    if (activeCategory.value) {
        rows = rows.filter((c) => c.category === activeCategory.value);
    }
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return rows;
    }
    return rows.filter((c) =>
        [c.title, c.circular_number, c.category, c.academic_year].filter(Boolean).join(' ').toLowerCase().includes(q),
    );
});

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
</script>
