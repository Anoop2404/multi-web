<template>
    <SahodayaAdminLayout :title="`${exam.title} — Centre codes`" :sahodaya="sahodaya" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${exam.title} — School centre codes`" eyebrow="Talent Search exam" description="List, assign and edit examination centre codes for this exam.">
            <template #actions><a :href="base" class="btn-secondary">← Exam overview</a><button class="btn-secondary" @click="print">Print list</button></template>
        </PageHeader>
        <McqExamSubNav class="no-print" :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode" active="centre-codes" />
        <section class="card mb-5 no-print">
            <h3 class="section-title">Auto-assign centre codes</h3>
            <p class="section-desc">Schools are numbered alphabetically. Existing codes are preserved by default; occupied codes are skipped.</p>
            <form @submit.prevent="preview" class="flex flex-wrap gap-3 items-end mt-4">
                <label class="text-sm">Code prefix<input v-model="form.prefix" maxlength="16" class="field block mt-1" placeholder="Optional, e.g. STSE-"></label>
                <label class="text-sm">Starting number<input v-model.number="form.start" type="number" min="0" max="999999999" required class="field block mt-1"></label>
                <label class="text-sm">Minimum digits<input v-model.number="form.digits" type="number" min="0" max="8" required class="field block mt-1"></label>
                <label class="text-sm">Schools<select v-model="form.scope" class="field block mt-1"><option value="registered">Schools with active registrations</option><option value="all">All member schools</option></select></label>
                <label class="text-sm flex gap-2 items-center py-2"><input v-model="form.replace" type="checkbox">Replace existing codes</label>
                <button class="btn-primary" :disabled="form.processing">Preview assignment</button>
            </form>
            <p v-for="(message,key) in form.errors" :key="key" class="text-sm text-rose-600 mt-2">{{ message }}</p>
        </section>
        <section v-if="assignmentPreview" class="card mb-5 border-indigo-200 no-print">
            <h3 class="section-title">Assignment preview · {{ assignmentPreview.length }} schools</h3>
            <p v-if="!previewCurrent" class="text-sm text-amber-700">Settings changed. Preview again before applying.</p>
            <div class="max-h-80 overflow-auto mt-3"><table class="w-full text-sm"><thead><tr><th class="p-2 text-left">School</th><th class="p-2 text-left">Current</th><th class="p-2 text-left">Proposed</th></tr></thead><tbody><tr v-for="school in assignmentPreview" :key="school.id" class="border-t"><td class="p-2">{{ school.name }}</td><td class="p-2 font-mono">{{ school.code || '—' }}</td><td class="p-2 font-mono font-bold">{{ school.proposed }}</td></tr></tbody></table></div>
            <button class="btn-primary mt-4" :disabled="form.processing || !previewCurrent || !assignmentPreview.length" @click="assign">{{ form.processing ? 'Assigning…' : 'Apply centre codes' }}</button>
        </section>
        <section class="card">
            <p v-for="(message,key) in editForm.errors" :key="key" class="text-sm text-rose-600 mb-2">{{ message }}</p>
            <div class="flex flex-wrap items-center gap-3 mb-4 no-print"><input v-model="search" class="field" placeholder="Search school or centre code" aria-label="Search schools"><select v-model="status" class="field" aria-label="Code assignment status"><option value="all">All schools</option><option value="assigned">Assigned codes</option><option value="missing">Missing codes</option></select><span class="text-sm text-slate-500">{{ assignedCount }} assigned / {{ schools.length }} schools</span></div>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left"><th class="p-2">#</th><th class="p-2">School</th><th class="p-2">Candidates</th><th class="p-2">Centre code</th><th class="p-2 no-print">Edit</th></tr></thead><tbody><tr v-for="(school,index) in filtered" :key="school.id" class="border-t"><td class="p-2">{{ index+1 }}</td><td class="p-2">{{ school.name }}</td><td class="p-2">{{ school.count }}</td><td class="p-2 font-mono font-bold">{{ school.code || 'Not assigned' }}</td><td class="p-2 no-print"><button class="text-indigo-700 font-semibold" @click="edit(school)">Edit code</button></td></tr><tr v-if="!filtered.length"><td colspan="5" class="p-4 text-slate-500">No schools match.</td></tr></tbody></table></div>
        </section>
    </SahodayaAdminLayout>
</template>
<script setup>
import {computed,ref} from 'vue';
import {useForm} from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import {useConfirm} from '@/composables/useConfirm';
const props=defineProps({sahodaya:Object,exam:Object,publicUrl:String,pendingPaymentsCount:Number,schools:Array,numbering:Object,assignmentPreview:{type:Array,default:null}});
const base=`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}`;
const form=useForm({...props.numbering});
const editForm=useForm({school_id:'',code:''});
const search=ref(''),status=ref('all');
const assignedCount=computed(()=>props.schools.filter(s=>s.code).length);
const filtered=computed(()=>props.schools.filter(s=>(`${s.name} ${s.code}`).toLowerCase().includes(search.value.toLowerCase())&&(status.value==='all'||(status.value==='assigned'?!!s.code:!s.code))));
const previewCurrent=computed(()=>Object.keys(props.numbering).every(key=>String(form[key])===String(props.numbering[key])));
const {confirm,prompt}=useConfirm();
function preview(){form.transform(data=>({...data,replace:data.replace?1:0,preview:1})).get(`${base}/centre-codes`,{preserveScroll:true});}
async function assign(){if(!previewCurrent.value)return;if(!(await confirm({title:'Assign school centre codes?',message:`Apply the previewed centre codes for this exam to ${props.assignmentPreview.length} schools?${form.replace?' Existing codes will be replaced; reprint affected hall tickets.':''}`,confirmLabel:'Apply codes',destructive:form.replace})))return;form.transform(data=>({...data})).post(`${base}/centre-codes/assign`,{preserveScroll:true});}
async function edit(school){const code=await prompt({title:`Centre code — ${school.name}`,message:'Enter the centre code for this exam. Leave empty to clear it.',inputValue:school.code||'',inputRequired:false});if(code===null)return;editForm.school_id=school.id;editForm.code=code;editForm.post(`${base}/school-centre-code`,{preserveScroll:true});}
function print(){window.print();}
</script>
<style scoped>
@media print {.no-print{display:none!important}table{font-size:11px}tr{break-inside:avoid}}
</style>
