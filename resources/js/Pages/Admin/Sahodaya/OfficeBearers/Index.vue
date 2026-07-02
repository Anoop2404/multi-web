<template>
    <SahodayaAdminLayout title="Office Bearers" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount"
                         :show-header-title="false">
        <PageHeader title="Office bearers" eyebrow="Website"
                    description="Leadership profiles shown on the public Sahodaya website." />
        <div class="space-y-6">

            <!-- Add / Edit panel -->
            <div class="bg-white rounded-2xl border shadow-sm overflow-hidden"
                 :class="editing ? 'border-purple-200' : 'border-gray-100'">
                <div class="px-6 py-4 border-b flex items-center justify-between"
                     :class="editing ? 'bg-purple-50 border-purple-100' : 'bg-gray-50 border-gray-100'">
                    <h3 class="font-bold text-gray-900">{{ editing ? 'Edit Office Bearer' : 'Add New Office Bearer' }}</h3>
                    <button v-if="editing" type="button" @click="cancelEdit"
                            class="text-xs text-gray-400 hover:text-gray-600 font-semibold">✕ Cancel</button>
                </div>
                <form @submit.prevent="save" class="p-6 space-y-5">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="Full Name *">
                            <input v-model="form.name" type="text" required class="field"
                                   placeholder="Dr. Anoop Kumar">
                        </Field>
                        <Field label="Role / Designation *">
                            <div class="relative">
                                <input v-model="form.role" type="text" required class="field pr-10"
                                       placeholder="President, Secretary, Treasurer…">
                                <button type="button" @click="showRolePicker = !showRolePicker"
                                        class="absolute right-2 top-2 text-gray-400 hover:text-gray-600 text-xs px-1">▾</button>
                                <div v-if="showRolePicker"
                                     class="absolute z-10 left-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg py-1 w-56">
                                    <button v-for="r in commonRoles" :key="r" type="button"
                                            @click="form.role = r; showRolePicker = false"
                                            class="w-full text-left px-4 py-2 text-sm hover:bg-purple-50 hover:text-purple-700 transition">
                                        {{ r }}
                                    </button>
                                </div>
                            </div>
                        </Field>
                        <Field label="School Name">
                            <input v-model="form.school_name" type="text" class="field"
                                   placeholder="School they represent">
                        </Field>
                        <Field label="Phone">
                            <input v-model="form.phone" type="tel" class="field">
                        </Field>
                        <Field label="Term From (Year)">
                            <input v-model="form.term_from" type="number" min="2000" max="2099" class="field" placeholder="2025">
                        </Field>
                        <Field label="Term To (Year)">
                            <input v-model="form.term_to" type="number" min="2000" max="2099" class="field" placeholder="2026">
                        </Field>
                        <Field label="Photo">
                            <input type="file" accept="image/*" @change="form.photo = $event.target.files[0]"
                                   class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                        </Field>
                        <Field label="Display Order">
                            <input v-model="form.display_order" type="number" min="0" class="field">
                        </Field>
                    </div>
                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" :disabled="form.processing"
                                class="px-6 py-2.5 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-sm font-bold rounded-xl transition disabled:opacity-50">
                            {{ editing ? 'Save Changes' : '+ Add Bearer' }}
                        </button>
                        <button v-if="editing" type="button" @click="cancelEdit"
                                class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Grid -->
            <div v-if="bearers.length" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="b in bearers" :key="b.id"
                     class="bg-white rounded-2xl border shadow-sm p-5 flex items-start gap-4 hover:border-purple-100 transition group"
                     :class="editing === b.id ? 'border-purple-300 ring-2 ring-purple-200' : 'border-gray-100'">
                    <!-- Avatar -->
                    <div class="shrink-0">
                        <img v-if="b.photo" :src="b.photo" alt=""
                             class="w-14 h-14 rounded-xl object-cover border border-gray-100 shadow-sm">
                        <div v-else
                             class="w-14 h-14 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center text-white text-xl font-extrabold shadow-sm">
                            {{ b.name?.charAt(0) }}
                        </div>
                    </div>
                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-gray-900 truncate">{{ b.name }}</p>
                        <p class="text-xs font-semibold text-purple-600 mt-0.5">{{ b.role }}</p>
                        <p v-if="b.school_name" class="text-xs text-gray-400 truncate mt-0.5">{{ b.school_name }}</p>
                        <div v-if="b.term_from || b.phone" class="flex flex-wrap gap-2 mt-1.5">
                            <span v-if="b.term_from" class="text-[11px] bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ b.term_from }}–{{ b.term_to }}</span>
                            <a v-if="b.phone" :href="`tel:${b.phone}`" class="text-[11px] bg-purple-50 text-purple-600 px-2 py-0.5 rounded-full">{{ b.phone }}</a>
                        </div>
                    </div>
                    <!-- Actions -->
                    <div class="opacity-0 group-hover:opacity-100 transition flex flex-col gap-1 shrink-0">
                        <button @click="startEdit(b)"
                                class="text-xs text-blue-500 hover:text-blue-700 font-semibold">Edit</button>
                        <button @click="remove(b)"
                                class="text-xs text-red-400 hover:text-red-600 font-semibold">Remove</button>
                    </div>
                </div>
            </div>

            <!-- Empty -->
            <div v-else class="bg-white rounded-2xl border border-dashed border-gray-200 p-14 text-center">
                <div class="text-5xl mb-3">👥</div>
                <p class="text-gray-600 font-semibold">No office bearers added yet</p>
                <p class="text-sm text-gray-400 mt-1">Add the President, Secretary, Treasurer and other leaders above.</p>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { ref, defineComponent, h } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    sahodaya:                Object,
    publicUrl:               { type: String, default: null },
    pendingSchoolsCount:     { type: Number, default: 0 },
    pendingSubmissionsCount: { type: Number, default: 0 },
    pendingPaymentsCount:    { type: Number, default: 0 },
    bearers:                 { type: Array, default: () => [] },
});

const editing        = ref(null);
const showRolePicker = ref(false);

const commonRoles = [
    'President', 'Vice President', 'General Secretary', 'Joint Secretary',
    'Treasurer', 'Sports Co-ordinator', 'Academic Co-ordinator', 'IT Coordinator',
    'Cultural Co-ordinator', 'Executive Member',
];

const form = useForm({
    name: '', role: '', school_name: '', phone: '',
    term_from: '', term_to: '', display_order: 0, photo: null,
});

function startEdit(b) {
    editing.value      = b.id;
    form.name          = b.name;
    form.role          = b.role;
    form.school_name   = b.school_name ?? '';
    form.phone         = b.phone ?? '';
    form.term_from     = b.term_from ?? '';
    form.term_to       = b.term_to ?? '';
    form.display_order = b.display_order ?? 0;
    form.photo         = null;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit() { editing.value = null; form.reset(); }

function save() {
    if (editing.value) {
        form.transform(d => ({ ...d, _method: 'PUT' }))
            .post(`/sahodaya-admin/${props.sahodaya.id}/office-bearers/${editing.value}`, {
                forceFormData: true,
                onSuccess: () => { editing.value = null; form.reset(); },
            });
    } else {
        form.post(`/sahodaya-admin/${props.sahodaya.id}/office-bearers`, {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    }
}

function remove(b) {
    if (!confirm(`Remove "${b.name}" from office bearers?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/office-bearers/${b.id}`);
}

const Field = defineComponent({
    props: { label: String },
    setup(props, { slots }) {
        return () => h('div', {}, [
            props.label ? h('label', { class: 'block text-xs font-semibold text-gray-600 mb-1.5' }, props.label) : null,
            slots.default?.(),
        ]);
    },
});
</script>

