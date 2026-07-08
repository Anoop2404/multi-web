<template>
    <SahodayaAdminLayout title="Talent Search hall ticket templates" :sahodaya="sahodaya" :show-header-title="false">
        <PageHeader title="Hall ticket templates" eyebrow="Talent Search templates" description="Reusable layouts assigned per exam before ticket generation." />
        <div class="card space-y-4">
            <form @submit.prevent="save" class="grid md:grid-cols-2 gap-3">
                <input v-model="form.title" class="field" placeholder="Template title" required>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_default" type="checkbox"> Default template</label>
                <input v-model="form.header_title" class="field md:col-span-2" placeholder="Header title">
                <textarea v-model="form.footer_note" class="field md:col-span-2" rows="2" placeholder="Footer note"></textarea>
                <button type="submit" class="btn-primary md:col-span-2">Save template</button>
            </form>
            <div v-for="t in templates" :key="t.id" class="border border-slate-100 rounded-lg p-3 text-sm">
                <p class="font-semibold">{{ t.title }} <span v-if="t.is_default" class="text-xs text-indigo-600">(default)</span></p>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({ sahodaya: Object, templates: { type: Array, default: () => [] } });
const form = reactive({ title: '', is_default: false, header_title: 'Talent Search Examination — Hall Ticket', footer_note: '' });

function save() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq/templates/hall-tickets`, {
        title: form.title,
        is_default: form.is_default,
        design_json: {
            header_title: form.header_title,
            footer_note: form.footer_note,
            show_reg_no: true,
            show_school: true,
            layout: 'standard',
        },
    }, { preserveScroll: true });
}
</script>
