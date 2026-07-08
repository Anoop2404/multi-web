<template>
    <SahodayaAdminLayout title="Talent Search certificate templates" :sahodaya="sahodaya" :show-header-title="false">
        <PageHeader title="Certificate templates" eyebrow="Talent Search templates" description="Reusable certificate layouts for published Talent Search results." />
        <div class="card space-y-4">
            <form @submit.prevent="save" class="grid md:grid-cols-2 gap-3">
                <input v-model="form.title" class="field" placeholder="Template title" required>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_default" type="checkbox"> Default template</label>
                <textarea v-model="form.body" class="field md:col-span-2" rows="4" placeholder="Certificate body with placeholders: {student_name}, {exam_title}, {grade}, {rank}"></textarea>
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
const form = reactive({ title: '', is_default: false, body: 'This certifies that {student_name} participated in {exam_title} and achieved grade {grade}.' });

function save() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq/templates/certificates`, {
        title: form.title,
        is_default: form.is_default,
        design_json: { body: form.body },
    }, { preserveScroll: true });
}
</script>
