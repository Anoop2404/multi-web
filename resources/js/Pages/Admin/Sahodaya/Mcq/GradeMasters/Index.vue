<template>
    <SahodayaAdminLayout title="Talent Search grade masters" :sahodaya="sahodaya" :show-header-title="false">
        <PageHeader title="Talent Search grade masters" eyebrow="Talent Search" description="Reusable grade bands for online and offline Talent Search exams." />
        <div class="card space-y-4">
            <form @submit.prevent="createMaster" class="grid md:grid-cols-2 gap-3">
                <input v-model="form.title" class="field" placeholder="Master title" required>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_default" type="checkbox"> Default master</label>
                <textarea v-model="form.bandsJson" class="field md:col-span-2 font-mono text-xs" rows="6" placeholder='[{"label":"A+","min_percentage":95,"max_percentage":100,"is_pass":true,"rank_eligible":true}]'></textarea>
                <button type="submit" class="btn-primary md:col-span-2">Save grade master</button>
            </form>
            <div v-for="m in masters" :key="m.id" class="border border-slate-100 rounded-lg p-3">
                <p class="font-semibold">{{ m.title }} <span v-if="m.is_default" class="text-xs text-indigo-600">(default)</span></p>
                <p class="text-xs text-slate-500 mt-1">{{ m.bands?.map(b => `${b.label}: ${b.min_percentage}-${b.max_percentage}%`).join(' · ') }}</p>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({ sahodaya: Object, masters: { type: Array, default: () => [] } });
const form = reactive({ title: '', is_default: false, bandsJson: '[{"label":"A+","min_percentage":95,"max_percentage":100,"is_pass":true,"rank_eligible":true},{"label":"A","min_percentage":90,"max_percentage":94.99,"is_pass":true,"rank_eligible":true},{"label":"B","min_percentage":75,"max_percentage":89.99,"is_pass":true,"rank_eligible":true},{"label":"C","min_percentage":60,"max_percentage":74.99,"is_pass":true,"rank_eligible":true},{"label":"D","min_percentage":40,"max_percentage":59.99,"is_pass":true,"rank_eligible":false},{"label":"F","min_percentage":0,"max_percentage":39.99,"is_pass":false,"rank_eligible":false}]' });

function createMaster() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq/grade-masters`, {
        title: form.title,
        is_default: form.is_default,
        bands: JSON.parse(form.bandsJson),
    }, { preserveScroll: true });
}
</script>
