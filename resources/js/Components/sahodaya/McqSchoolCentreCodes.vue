<template>
    <section class="card mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="section-title">School examination centre codes</h3><a :href="`/sahodaya-admin/${sahodayaId}/mcq-exams/${examId}/centre-codes`" class="btn-secondary text-sm">Centre code list &amp; auto-assign →</a></div>
        <p class="section-desc">Assign each school a code for this exam. The Centre code field on hall tickets fills automatically.</p>
        <form @submit.prevent="save" class="flex flex-wrap items-end gap-3 mt-4">
            <label class="text-sm">School<select v-model="form.school_id" required class="field block mt-1" @change="loadCode"><option value="" disabled>Select school</option><option v-for="school in schools" :key="school.id" :value="school.id">{{ school.name }}{{ school.code ? ` — ${school.code}` : '' }}</option></select></label>
            <label class="text-sm">Centre code<input v-model="form.code" maxlength="40" class="field block mt-1" placeholder="e.g. STSE-001"></label>
            <button class="btn-primary" :disabled="form.processing || !form.school_id">{{ form.processing ? 'Saving…' : 'Save centre code' }}</button>
        </form>
        <p v-for="(message, key) in form.errors" :key="key" class="text-sm text-rose-600 mt-2">{{ message }}</p>
        <p class="text-xs text-slate-500 mt-2">Codes must be unique within this exam. Leave blank and save to clear an assignment.</p>
    </section>
</template>
<script setup>
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ sahodayaId: String, examId: [String,Number], schools: { type:Array, default:()=>[] } });
const form = useForm({ school_id:'', code:'' });
function loadCode(){ form.code=props.schools.find(s=>s.id===form.school_id)?.code||''; form.clearErrors(); }
function save(){ form.post(`/sahodaya-admin/${props.sahodayaId}/mcq-exams/${props.examId}/school-centre-code`,{preserveScroll:true}); }
</script>
