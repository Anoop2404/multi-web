<template>
    <SahodayaAdminLayout title="Sidebar visibility" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <PageHeader
            title="Sidebar visibility"
            eyebrow="Settings"
            description="Hide fest programs and menu sections that are not running this season. Hidden items stay accessible via direct links and All events."
        />

        <form @submit.prevent="save" class="max-w-2xl space-y-6">
            <section class="card space-y-4">
                <div>
                    <h3 class="section-title">Fest programs</h3>
                    <p class="section-desc mt-1">Turn off programs you are not conducting now. Schools will not see them in their sidebar either.</p>
                </div>
                <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200/80 overflow-hidden">
                    <li v-for="slug in programSlugs" :key="slug"
                        class="flex items-center justify-between gap-4 px-4 py-3 bg-white">
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ programLabels[slug] ?? slug }}</p>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600 cursor-pointer shrink-0">
                            <input type="checkbox" v-model="form.programs[slug]" class="rounded border-slate-300">
                            <span>{{ form.programs[slug] ? 'Visible' : 'Hidden' }}</span>
                        </label>
                    </li>
                </ul>
            </section>

            <section class="card space-y-4">
                <div>
                    <h3 class="section-title">Menu sections</h3>
                    <p class="section-desc mt-1">Hide whole sidebar groups (membership, Talent Search, finance, etc.) when not in use.</p>
                </div>
                <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200/80 overflow-hidden">
                    <li v-for="(label, key) in menuLabels" :key="key"
                        class="flex items-center justify-between gap-4 px-4 py-3 bg-white">
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ label }}</p>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600 cursor-pointer shrink-0">
                            <input type="checkbox" v-model="form.menus[key]" class="rounded border-slate-300">
                            <span>{{ form.menus[key] ? 'Visible' : 'Hidden' }}</span>
                        </label>
                    </li>
                </ul>
            </section>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Save visibility' }}
                </button>
                <p v-if="form.recentlySuccessful" class="text-sm text-emerald-700">Saved.</p>
            </div>
        </form>
    </SahodayaAdminLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    visibility: { type: Object, required: true },
    programLabels: { type: Object, required: true },
    menuLabels: { type: Object, required: true },
    programSlugs: { type: Array, required: true },
});

const form = useForm({
    programs: { ...props.visibility.programs },
    menus: { ...props.visibility.menus },
});

function save() {
    form.put(`/sahodaya-admin/${props.sahodaya.id}/settings/nav-visibility`, {
        preserveScroll: true,
    });
}
</script>
