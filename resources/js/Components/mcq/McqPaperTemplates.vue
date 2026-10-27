<template>
    <section class="card mb-6 space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h3 class="section-title">Paper templates &amp; class assignments</h3><p class="section-desc">Build reusable papers from your common question banks. Assign a version to one or more classes.</p></div>
            <button type="button" class="btn-secondary text-sm" @click="newTemplate">+ New template</button>
        </div>
        <p class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900">Class templates are delivered by the in-app online exam (single-answer and true/false questions). The separate exam engine does not yet support class templates.</p>
        <p v-if="locked" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Candidates have started. Class assignments are locked. You can still create template versions for future exams.</p>
        <div v-if="!templates.length" class="rounded-xl border border-dashed p-6 text-sm text-slate-500">No templates yet. Create Template 1, choose its questions, then assign its classes.</div>
        <div class="grid gap-3 md:grid-cols-2">
            <article v-for="t in templates" :key="t.id" class="rounded-xl border p-4 space-y-2">
                <div class="flex justify-between gap-2"><strong>{{ t.title }}</strong><span class="text-xs text-slate-500">Version {{ t.version }} · {{ t.is_active ? 'Ready' : 'Archived' }}</span></div>
                <p class="text-sm text-slate-600">{{ t.question_count }} questions · {{ t.total_marks }} marks</p>
                <div class="flex flex-wrap gap-2"><button type="button" class="link-brand text-sm" @click="editTemplate(t)">Edit as new version</button><button type="button" class="link-brand text-sm" @click="preview = preview === t.id ? null : t.id">Preview questions</button><button v-if="t.is_active" type="button" class="text-sm text-red-700" @click="archive(t)">Archive</button></div>
                <ol v-if="preview === t.id" class="list-decimal pl-5 space-y-2 max-h-64 overflow-y-auto text-sm"><li v-for="q in t.questions" :key="q.id"><span class="text-xs text-slate-400">{{ q.section_title }} · {{ q.marks }} marks</span><p class="whitespace-pre-wrap">{{ q.body_text || 'Image question' }}</p></li></ol>
            </article>
        </div>

        <form v-if="editing" class="rounded-xl border border-indigo-200 bg-indigo-50/30 p-4 space-y-4" @submit.prevent="saveTemplate">
            <div class="flex justify-between"><h4 class="font-semibold">{{ editingId ? 'Save a new template version' : 'Create a paper template' }}</h4><button type="button" class="text-sm text-slate-500" @click="editing = false">Close editor</button></div>
            <label class="block text-sm">Template name<input v-model="templateForm.title" class="field mt-1" required placeholder="Template 1 — Junior paper" maxlength="160"></label>
            <div v-for="(section, index) in templateForm.sections" :key="index" class="rounded-lg border bg-white p-3 space-y-3">
                <div class="flex items-center justify-between gap-2"><strong class="text-sm">Section {{ index + 1 }}</strong><div class="flex gap-2"><button type="button" :disabled="index === 0" aria-label="Move section up" @click="move(templateForm.sections,index,-1)">↑</button><button type="button" :disabled="index === templateForm.sections.length - 1" aria-label="Move section down" @click="move(templateForm.sections,index,1)">↓</button><button type="button" class="text-red-700 text-sm" @click="templateForm.sections.splice(index,1)">Remove section</button></div></div>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="text-sm">Question bank<select v-model="section.bank_id" class="field mt-1" required @change="section.question_ids=[]; loadQuestions(section.bank_id)"><option value="">Choose a Ready bank</option><option v-for="b in banks" :key="b.id" :value="b.id">{{ b.title }} ({{ b.questions_count }})</option></select></label>
                    <label class="text-sm">Section heading<input v-model="section.title" class="field mt-1" placeholder="Optional heading"></label>
                    <label class="text-sm">Question selection<select v-model="section.pick_mode" class="field mt-1" @change="loadQuestions(section.bank_id)"><option value="all">All questions</option><option value="first">First N questions</option><option value="random">Random N — fixed when saved</option><option value="manual">Choose and order questions</option></select></label>
                    <label v-if="['first','random'].includes(section.pick_mode)" class="text-sm">Number of questions<input v-model.number="section.pick_count" type="number" min="1" max="1000" required class="field mt-1"></label>
                    <label class="text-sm">Marks per question<input v-model.number="section.marks" type="number" step="0.01" min="0.01" class="field mt-1" placeholder="Use each question’s marks"></label>
                    <label class="text-sm">Penalty per wrong answer<input v-model.number="section.negative_mark" type="number" step="0.01" min="0" class="field mt-1" placeholder="Use each question’s penalty"></label>
                </div>
                <p v-if="section.pick_mode === 'random'" class="text-xs text-slate-500">A random subset is chosen once for this version. Candidate shuffling changes order, not the selected subset.</p>
                <div v-if="section.pick_mode === 'manual' && section.bank_id" class="space-y-3">
                    <p v-if="loadingBank === section.bank_id" class="text-sm">Loading questions…</p>
                    <div class="max-h-64 overflow-y-auto rounded border p-2 space-y-2"><label v-for="q in questionPools[section.bank_id] || []" :key="q.id" class="flex items-start gap-2 text-sm"><input v-model="section.question_ids" :value="q.id" type="checkbox" class="mt-1"><span>{{ q.body_text || 'Image question' }} <small class="text-slate-500">({{ q.marks }} marks · Answer: {{ q.answer }})</small></span></label></div>
                    <div v-for="(id, qi) in section.question_ids" :key="id" class="flex items-center gap-2 text-sm"><span class="text-slate-400">{{ qi + 1 }}.</span><span class="flex-1">{{ questionTitle(section.bank_id,id) }}</span><button type="button" :disabled="qi === 0" aria-label="Move question up" @click="move(section.question_ids,qi,-1)">↑</button><button type="button" :disabled="qi === section.question_ids.length - 1" aria-label="Move question down" @click="move(section.question_ids,qi,1)">↓</button></div>
                </div>
            </div>
            <p v-if="bankError" class="text-sm text-red-700" role="alert">{{ bankError }}</p>
            <button type="button" class="btn-secondary text-sm" @click="addSection">+ Add section</button>
            <p v-for="(error,key) in templateForm.errors" :key="key" class="text-sm text-red-700" role="alert">{{ error }}</p>
            <div class="flex gap-2"><button type="submit" class="btn-primary text-sm" :disabled="templateForm.processing || !templateForm.sections.length">Save template version</button></div>
            <p class="text-xs text-slate-500">Saved versions include immutable question text, images, answer keys and marks. Editing a template does not update existing class assignments automatically.</p>
        </form>

        <form class="border-t pt-5 space-y-4" @submit.prevent="saveAssignments">
            <h4 class="font-semibold">Assign papers to classes</h4>
            <p class="text-sm text-slate-500">Total marks must match the selected template. Change the template’s marks before changing the class total. Passing mark is a percentage.</p>
            <p v-if="classes.length" class="text-xs text-slate-500">Registered classes: {{ classes.join(', ') }}</p>
            <datalist id="paper-class-names"><option v-for="c in classes" :key="c" :value="c" /></datalist>
            <div v-for="(row,index) in assignmentForm.assignments" :key="index" class="rounded-xl border p-4 space-y-3">
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="text-sm">Paper template<select v-model="row.template_id" class="field mt-1" required :disabled="locked" @change="useTemplate(row)"><option value="">Choose a template</option><option v-for="t in activeTemplates" :key="t.id" :value="t.id">{{ t.title }} · v{{ t.version }} · {{ t.total_marks }} marks</option></select></label>
                    <label class="text-sm">Classes — separate with commas<input v-model="row.classes_text" class="field mt-1" required :disabled="locked" placeholder="1, 2, 3 or LKG, UKG"></label>
                    <label class="text-sm">Total marks<input v-model.number="row.total_marks" type="number" step="0.01" min="0.01" class="field mt-1" required :disabled="locked"></label>
                    <label class="text-sm">Passing percentage<input v-model.number="row.pass_mark" type="number" min="0" max="100" step="0.01" class="field mt-1" :disabled="locked"></label>
                    <label class="text-sm">Question order<select v-model="row.shuffle_questions" class="field mt-1" :disabled="locked"><option value="none">Template order</option><option value="sections">Shuffle within each section</option><option value="all">Shuffle all questions</option></select></label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="row.shuffle_options" type="checkbox" :disabled="locked">Shuffle answer options per candidate</label>
                </div>
                <Link v-if="row.version && row.classes?.length" :href="`${examUrl}/paper-preview?class=${encodeURIComponent(row.classes[0])}`" class="link-brand text-sm">Preview saved class paper</Link>
                <p v-if="row.version" class="text-xs text-slate-500">Currently assigned: {{ row.title }} v{{ row.version }}. Saving assignments uses the latest version selected above.</p>
                <button v-if="!locked" type="button" class="text-sm text-red-700" @click="assignmentForm.assignments.splice(index,1)">Remove class assignment</button>
            </div>
            <button v-if="!locked" type="button" class="btn-secondary text-sm" @click="assignmentForm.assignments.push({template_id:'',classes_text:'',total_marks:null,pass_mark:0,shuffle_questions:'sections',shuffle_options:true})">+ Add class assignment</button>
            <label class="block text-sm">Classes without an assigned template<select v-model="assignmentForm.fallback" class="field mt-1" :disabled="locked"><option value="require">Block exam start until their paper is assigned</option><option value="legacy">Use the default paper sections above</option></select></label>
            <p v-if="!assignmentForm.assignments.length" class="text-xs text-slate-500">With no class assignments, the exam continues using its default paper.</p>
            <p v-for="(error,key) in assignmentForm.errors" :key="key" class="text-sm text-red-700" role="alert">{{ error }}</p>
            <button v-if="!locked" type="submit" class="btn-primary text-sm" :disabled="assignmentForm.processing">Save class assignments</button>
        </form>
    </section>
</template>

<script setup>
import { computed, ref, reactive, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';
const props = defineProps({examUrl:String,templates:Array,banks:Array,assignments:Array,classes:Array,fallback:String,locked:Boolean});
const { confirm } = useConfirm();
const editing = ref(false), editingId = ref(null), preview = ref(null), loadingBank = ref(null), bankError = ref('');
const questionPools = reactive({});
const activeTemplates = computed(() => props.templates.filter(t => t.is_active));
const templateForm = useForm({title:'',sections:[]});
const assignmentForm = useForm({fallback:props.fallback,assignments:props.assignments.map(r=>({...r,classes_text:r.classes.join(', ')}))});
watch(() => props.assignments, rows => { assignmentForm.assignments = rows.map(r => ({...r, classes_text:r.classes.join(', ')})); assignmentForm.fallback = props.fallback; });
function addSection(){templateForm.sections.push({bank_id:'',title:'',pick_mode:'all',pick_count:null,question_ids:[],marks:null,negative_mark:null});}
function newTemplate(){editingId.value=null;templateForm.reset();templateForm.clearErrors();editing.value=true;addSection();}
function editTemplate(t){editingId.value=t.id;templateForm.title=t.title;templateForm.sections=JSON.parse(JSON.stringify(t.sections));templateForm.clearErrors();editing.value=true;for(const s of templateForm.sections)if(s.pick_mode==='manual')loadQuestions(s.bank_id);}
function move(items,index,offset){const dest=index+offset;if(dest>=0&&dest<items.length)[items[index],items[dest]]=[items[dest],items[index]];}
async function loadQuestions(id){if(!id||questionPools[id])return;loadingBank.value=id;bankError.value='';try{const response=await fetch(`${props.examUrl}/paper-library/banks/${id}`,{headers:{Accept:'application/json'}});if(!response.ok)throw new Error();questionPools[id]=await response.json();}catch{bankError.value='Could not load questions. Select the bank again to retry.';}finally{loadingBank.value=null;}}
function questionTitle(bank,id){return questionPools[bank]?.find(q=>q.id===id)?.body_text || `Question ${id}`;}
function saveTemplate(){templateForm.transform(data=>({...data,sections:data.sections.map(s=>({...s,marks:s.marks===''?null:s.marks,negative_mark:s.negative_mark===''?null:s.negative_mark}))}));const options={preserveScroll:true,onSuccess:()=>{editing.value=false;templateForm.reset();}};if(editingId.value)templateForm.put(`${props.examUrl}/paper-templates/${editingId.value}`,options);else templateForm.post(`${props.examUrl}/paper-templates`,options);}
function useTemplate(row){const t=props.templates.find(t=>t.id===Number(row.template_id));if(t)row.total_marks=Number(t.total_marks);}
function saveAssignments(){assignmentForm.transform(data=>({...data,assignments:data.assignments.map(row=>({template_id:row.template_id,classes:row.classes_text.split(',').map(c=>c.trim()).filter(Boolean),total_marks:row.total_marks,pass_mark:row.pass_mark,shuffle_questions:row.shuffle_questions,shuffle_options:row.shuffle_options}))})).put(`${props.examUrl}/class-papers`,{preserveScroll:true});}
async function archive(t){if(await confirm({title:'Archive template?',message:'Existing class papers and attempts will remain unchanged.',confirmLabel:'Archive'}))router.delete(`${props.examUrl}/paper-templates/${t.id}`,{preserveScroll:true});}
</script>
