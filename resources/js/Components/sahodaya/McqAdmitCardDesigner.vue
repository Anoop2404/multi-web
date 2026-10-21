<template>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm mb-6">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <div><p class="text-[10px] font-bold uppercase tracking-[.16em] text-indigo-600">Exam workspace · Admit cards</p><h2 class="text-lg font-bold text-slate-900 mt-1">Design {{ exam.title }} admit cards</h2><p class="text-xs text-slate-500 mt-1">Changes are saved to this exam only.</p></div>
            <div class="flex items-center gap-2"><span v-if="saved" role="status" class="text-xs text-emerald-700">Design saved</span><a :href="previewUrl" target="_blank" rel="noopener" class="btn-secondary text-sm">Saved print preview ↗</a><button class="btn-primary text-sm" type="button" :disabled="saving || !!uploadError || remarksOverflow" @click="$emit('save')">{{ saving ? 'Saving…' : saveLabel }}</button></div>
        </header>
        <div class="grid xl:grid-cols-[310px_minmax(0,1fr)_240px]">
            <aside class="p-5 border-b xl:border-b-0 xl:border-r border-slate-200 space-y-5">
                <div><h3 class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-3">1. Choose a design</h3>
                    <button v-for="option in layouts" :key="option.value" type="button" @click="chooseLayout(option.value)" class="w-full text-left rounded-xl border p-3 mb-2 transition" :class="design.layout === option.value ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-slate-200 hover:border-indigo-300'"><span class="block text-sm font-semibold">{{ option.label }}</span><span class="block text-xs text-slate-500 mt-1">{{ option.description }}</span></button>
                </div>
                <div v-if="design.layout === 'custom_background'" class="space-y-2"><label class="text-sm font-semibold" for="exam-admit-background">Background artwork</label><input id="exam-admit-background" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-xs" @change="uploadBackground"><p class="text-xs text-slate-500">Portrait PNG, JPG or WebP · up to 2 MB. Use the same proportions as the preview.</p><p v-if="uploadError" role="alert" class="text-xs text-red-600">{{ uploadError }}</p></div>
                <div class="rounded-xl bg-slate-50 p-3 text-xs space-y-2"><strong class="block text-slate-700">Data on each card</strong><p>Date: {{ sample.exam_date || 'Set the exam schedule' }} <span class="text-slate-400">· Sahodaya schedule</span></p><p>Centre: Sahodaya venue or the school's centre for this exam.</p><p>Signature: uploaded by the school.</p></div>
                <div v-if="isArtwork" class="border-t pt-4 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">A4 sheet &amp; die size</h3>
                    <label class="block text-xs text-slate-600">Sheet preset<select class="field mt-1" :value="preset" @change="applyPreset($event.target.value)"><option value="custom">Custom die size</option><option value="one">1 card · full sheet</option><option value="two">2 stacked cards</option><option value="three">3 stacked cards</option><option value="four">4 cards · 2 × 2</option><option value="six">6 cards · 2 × 3</option><option value="columns">3 portrait cards side by side</option></select></label>
                    <label class="block text-xs text-slate-600">A4 orientation<select v-model="design.page_orientation" class="field mt-1"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select></label>
                    <div class="grid grid-cols-2 gap-2"><label class="text-xs text-slate-600">Die width (mm)<input v-model.number="design.card_width_mm" type="number" min="20" :max="geometry.pageWidth-20" step="0.5" class="field mt-1"></label><label class="text-xs text-slate-600">Die height (mm)<input v-model.number="design.card_height_mm" type="number" min="20" :max="geometry.pageHeight-20" step="0.5" class="field mt-1"></label></div>
                    <label class="block text-xs text-slate-600">Gap between cards (mm)<input v-model.number="design.card_gap_mm" class="field mt-1" type="number" min="0" max="15" step="0.5"></label>
                    <label class="block text-xs text-slate-600">Background fit<select v-model="design.background_fit" class="field mt-1"><option value="contain">Full image · keep proportions</option><option value="stretch">Fill the die · stretch image</option></select></label>
                    <p class="text-xs text-slate-500">{{ geometry.columns }} across × {{ geometry.rows }} down = <strong>{{ geometry.perPage }} {{ geometry.perPage===1 ? 'card' : 'cards' }} per A4</strong>. 10 mm page margins.</p>
                </div>
                <div v-if="isArtwork" class="border-t pt-4 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Remarks / Instructions</h3>
                    <div class="flex gap-2"><button type="button" class="btn-secondary text-xs" @click="applyPreset('one')">Full A4 page</button><button type="button" class="btn-secondary text-xs" @click="applyPreset('two')">2 per page</button></div>
                    <label class="block text-xs text-slate-600">Section heading<input v-model="design.remarks_title" class="field mt-1" maxlength="100" placeholder="Rules & Instructions"></label>
                    <label class="block text-xs text-slate-600">Instruction format<select v-model="design.remarks_format" class="field mt-1"><option value="paragraph">Paragraph</option><option value="numbered">Numbered points</option><option value="bullets">Bullet points</option></select></label>
                    <label class="block text-xs text-slate-600">Instruction font size<input v-model.number="design.remarks_font_size" class="field mt-1" type="number" min="8" max="18" step="0.5"></label>
                    <label class="block text-xs text-slate-600">Extra content<textarea v-model="design.remarks_text" class="field mt-1" rows="8" maxlength="3000" placeholder="Write one rule per line. Add 10 or more points as needed."></textarea></label>
                    <label v-if="(design.remarks_text || '').trim()" class="block text-xs text-slate-600">Remarks area height (mm)<input v-model.number="design.remarks_height_mm" class="field mt-1" type="number" min="15" :max="Math.min(257,geometry.height-20)"></label>
                    <p v-if="['numbered','bullets'].includes(design.remarks_format)" class="text-xs text-slate-500">{{ instructionCount }} points · one non-empty line per point · numbering is automatic.</p>
                    <button v-if="(design.remarks_text || '').trim()" type="button" class="btn-secondary text-xs" @click="fitInstructions">Fit all points in this card</button>
                    <p class="text-xs text-slate-500">A separate area appears only when you add content. Choose a full page or two per page for longer instructions.</p><p v-if="remarksOverflow" role="alert" class="text-xs text-red-600">The remarks do not fit in this area. Increase its height, shorten the text, or choose a full A4 page before saving.</p>
                </div>
                <slot name="settings" />
            </aside>
            <div class="bg-slate-100/80 p-5 sm:p-8 min-w-0">
                <div class="flex items-center justify-between text-xs text-slate-500 mb-5"><strong class="font-semibold">{{ isArtwork ? (viewMode==='sheet'?'A4 sheet preview':'Live card canvas') : 'Live preview' }}</strong><span>{{ isArtwork ? `${geometry.width.toFixed(1)} × ${geometry.height.toFixed(1)} mm` : 'A4 layout' }}</span></div>
                <div v-if="isArtwork" class="flex justify-center gap-1 mb-4"><button v-for="view in ['card','sheet']" :key="view" type="button" class="px-3 py-1.5 rounded-lg text-xs font-semibold" :class="viewMode===view?'bg-white shadow-sm text-indigo-700':'text-slate-500'" @click="viewMode=view">{{ view==='card'?'Edit card':'Preview A4 sheet' }}</button></div>
                <McqAdmitSheetPreview v-if="isArtwork && viewMode==='sheet'" :design="design" :sample="sample" :background="background" @remarks-overflow="remarksOverflow=$event" @remarks-required-height="requiredRemarksHeight=$event" />
                <div v-else-if="isArtwork" class="mx-auto max-w-[540px] shadow-lg"><McqAdmitCardCanvas :design="design" :sample="sample" :background="background" editable :selected="selected" @select="selected=$event" @move="moveField" @remarks-overflow="remarksOverflow=$event" @remarks-required-height="requiredRemarksHeight=$event" /></div>
                <McqHallTicketPreview v-else :design="design" :sample="sample" :logo-url="logoUrl" />
                <p v-if="isArtwork" class="text-center text-xs text-slate-500 mt-5">{{ viewMode==='sheet'?'Dashed lines show die boundaries. The image stays inside each card.':'Select a field to position it. Drag to move; arrow keys fine-tune.' }}</p>
                <div class="mt-6 rounded-xl border border-slate-200 bg-white px-4 py-3 flex items-center justify-between gap-3 text-xs"><div><strong class="block text-slate-700">{{ isArtwork ? `${geometry.perPage} ${geometry.perPage===1?'card':'cards'} per sheet` : design.layout === 'compact' ? 'Compact cards' : 'One card per sheet' }}</strong><span class="text-slate-500">{{ isArtwork ? `${design.page_orientation === 'portrait' ? 'Portrait' : 'Landscape'} A4 · print at 100%` : 'Portrait A4' }}</span></div><div v-if="isArtwork" :style="{display:'grid',gridTemplateColumns:`repeat(${Math.min(geometry.columns,6)},1fr)`,gap:'2px'}" aria-label="Cards on the A4 sheet"><span v-for="n in Math.min(geometry.perPage,12)" :key="n" class="h-8 w-5 rounded-sm border border-indigo-300 bg-indigo-50"></span></div></div>
            </div>
            <aside class="p-5 border-t xl:border-t-0 xl:border-l border-slate-200 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">2. Align fields</h3>
                <template v-if="isArtwork">
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-xs font-semibold text-slate-600"><input v-model="design.show_student_photo" type="checkbox" @change="selectPhoto"> Include student photo</label>
                        <p class="text-xs text-slate-500">Uses each student's saved profile photo. Missing images leave no placeholder.</p>
                        <button type="button" class="btn-secondary text-xs w-full" :disabled="(design.custom_fields || []).length >= 20" @click="addCustomField">+ Add custom field</button>
                    </div>
                    <div class="space-y-1"><button v-for="(label,key) in labels" :key="key" type="button" @click="selected=key;viewMode='card'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-semibold" :class="selected===key ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-50'">{{ label }}</button></div>
                    <div v-if="position" class="border-t border-slate-100 pt-4 space-y-3"><p class="text-sm font-bold">{{ labels[selected] }}</p><div v-if="customField" class="space-y-2"><label class="block text-xs">Field label<input v-model="customField.label" class="field mt-1" maxlength="80"></label><label class="block text-xs">Content<select v-model="customField.source" class="field mt-1"><option v-for="(label,key) in customSources" :key="key" :value="key">{{ label }}</option></select></label><label v-if="customField.source==='text'" class="block text-xs">Custom text<textarea v-model="customField.text" class="field mt-1" maxlength="500" rows="2"></textarea></label><p v-else class="text-xs text-slate-500">This field fills automatically for each student.</p><button type="button" class="text-xs font-semibold text-red-600" @click="removeCustomField">Remove this field</button></div><div class="grid grid-cols-2 gap-2"><label class="text-xs text-slate-600">Left %<input v-model.number="position.x" class="field mt-1" type="number" min="0" max="95" step="0.1"></label><label class="text-xs text-slate-600">Top %<input v-model.number="position.y" class="field mt-1" type="number" min="0" max="92" step="0.1"></label><label class="text-xs text-slate-600">Width %<input v-model.number="position.width" class="field mt-1" type="number" min="1" :max="100-position.x" step="0.5"></label><label v-if="!['principal_signature','student_photo'].includes(selected)" class="text-xs text-slate-600">Text size<input v-model.number="position.size" class="field mt-1" type="number" min="6" max="72" step="0.5"></label><label v-if="selected==='student_photo'" class="text-xs text-slate-600">Height %<input v-model.number="position.height" class="field mt-1" type="number" min="1" :max="100-position.y" step="0.5"></label></div><div v-if="!['principal_signature','student_photo'].includes(selected)" class="space-y-3 border-t pt-3"><label class="block text-xs text-slate-600">Font family<select :value="position.font_family || 'Arial'" @change="position.font_family=$event.target.value" class="field mt-1"><option v-for="family in fontFamilies" :key="family" :value="family">{{ family }}</option></select></label><div class="grid grid-cols-2 gap-2"><label class="text-xs text-slate-600">Weight<select :value="position.font_weight || 'normal'" @change="position.font_weight=$event.target.value" class="field mt-1"><option value="normal">Normal</option><option value="bold">Bold</option></select></label><label class="text-xs text-slate-600">Style<select :value="position.font_style || 'normal'" @change="position.font_style=$event.target.value" class="field mt-1"><option value="normal">Regular</option><option value="italic">Italic</option></select></label></div><label class="block text-xs text-slate-600">Text alignment<select :value="position.text_align || 'left'" @change="position.text_align=$event.target.value" class="field mt-1"><option value="left">Left</option><option value="center">Centre</option><option value="right">Right</option></select></label></div><button type="button" class="text-xs font-semibold text-indigo-600" @click="resetField">Reset this field</button></div>
                    <label class="flex gap-2 text-xs text-slate-600 border-t pt-4"><input v-model="design.show_signature" type="checkbox"> Show school signature</label>
                    <button type="button" class="btn-secondary text-xs w-full" @click="resetAll">Reset field alignment</button>
                </template>
                <p v-else class="text-xs text-slate-500">Choose STSE or custom artwork to position fields on the card.</p>
            </aside>
        </div>
        <div v-if="Object.keys(errors).length" role="alert" class="border-t border-red-200 bg-red-50 p-4 text-sm text-red-700"><p v-for="(error,key) in errors" :key="key">{{ error }}</p></div>
    </section>
</template>
<script setup>
import { computed, ref, onBeforeUnmount } from 'vue';
import {customSources} from '@/support/mcqAdmitFields';
import { printGeometry } from '@/support/mcqAdmitGeometry';
import McqAdmitSheetPreview from './McqAdmitSheetPreview.vue';
import McqAdmitCardCanvas from './McqAdmitCardCanvas.vue';
import McqHallTicketPreview from './McqHallTicketPreview.vue';
const props=defineProps({design:Object,defaults:Object,exam:Object,sample:Object,backgroundUrl:String,logoUrl:String,previewUrl:String,saving:Boolean,saved:Boolean,saveLabel:{type:String,default:'Save exam design'},errors:{type:Object,default:()=>({})}});
const emit=defineEmits(['save','background']);
const geometry=computed(()=>printGeometry(props.design));
const presets={one:[190,277,'portrait'],two:[190,136,'portrait'],three:[190,90,'portrait'],four:[93,136,'portrait'],six:[93,90,'portrait'],columns:[90,121.384615,'landscape']};
const preset=computed(()=>Object.entries(presets).find(([,v])=>Math.abs(props.design.card_width_mm-v[0])<.01&&Math.abs(props.design.card_height_mm-v[1])<.01&&props.design.page_orientation===v[2])?.[0]||'custom');
function applyPreset(key){viewMode.value='sheet';const p=presets[key];if(p)Object.assign(props.design,{card_width_mm:p[0],card_height_mm:p[1],page_orientation:p[2],card_gap_mm:3});}
const fontFamilies=['Arial','Times New Roman','Georgia','Verdana','Tahoma','Courier New'];
const remarksOverflow=ref(false);
const requiredRemarksHeight=ref(35);
const instructionCount=computed(()=>String(props.design.remarks_text||'').split(/\r\n|\r|\n/).filter(line=>line.trim()).length);
function fitInstructions(){props.design.remarks_height_mm=Math.min(257,geometry.value.height-20,Math.max(15,requiredRemarksHeight.value));}
if(!props.design.remarks_format)props.design.remarks_format='paragraph';
if(props.design.remarks_title===undefined)props.design.remarks_title='Remarks / Instructions';
if(!props.design.remarks_font_size)props.design.remarks_font_size=11;
const viewMode=ref('sheet');
const selected=ref('name'),uploadError=ref(''),localBackground=ref(null);
const labels=computed(()=>({registration:'Registration number',name:'Student name',class:'Class',date:'Examination date',centre:'Examination centre',principal_signature:'Principal signature',...(props.design.show_student_photo?{student_photo:'Student photo'}:{}),...Object.fromEntries((props.design.custom_fields||[]).map(field=>[field.id,field.label||'Custom field']))}));
const customField=computed(()=>(props.design.custom_fields||[]).find(field=>field.id===selected.value));
function selectPhoto(){if(!props.design.field_positions.student_photo)props.design.field_positions.student_photo={...props.defaults.field_positions.student_photo};if(props.design.show_student_photo){selected.value='student_photo';viewMode.value='card';}else if(selected.value==='student_photo')selected.value='name';}
function addCustomField(){if(!Array.isArray(props.design.custom_fields))props.design.custom_fields=[];if(props.design.custom_fields.length>=20)return;const field={id:'custom_'+crypto.randomUUID().replaceAll('-',''),label:'Custom field',source:'text',text:'Custom text',x:10,y:35,width:60,size:10};props.design.custom_fields.push(field);selected.value=field.id;viewMode.value='card';}
function removeCustomField(){props.design.custom_fields=props.design.custom_fields.filter(field=>field.id!==selected.value);selected.value='name';}

const layouts=[{value:'stse_three_up',label:'STSE default artwork',description:'Original STSE branding'},{value:'custom_background',label:'Custom background',description:'Upload artwork and place your fields'},{value:'standard',label:'Standard admit card',description:'Detailed full-page card'},{value:'compact',label:'Compact admit card',description:'Existing compact layout'}];
const isArtwork=computed(()=>['stse_three_up','custom_background'].includes(props.design.layout));
const background=computed(()=>props.design.layout==='stse_three_up'?'/images/mcq/stse-preliminary-background.png':localBackground.value||props.backgroundUrl);
const position=computed(()=>customField.value || props.design.field_positions?.[selected.value]);
function chooseLayout(value){props.design.layout=value;}
function moveField(key,values){const p=(props.design.custom_fields||[]).find(field=>field.id===key)||props.design.field_positions[key];Object.assign(p,values);p.width=Math.min(p.width,100-p.x);}
function resetField(){if(customField.value)Object.assign(customField.value,{x:10,y:35,width:60,size:10});else props.design.field_positions[selected.value]={...props.defaults.field_positions[selected.value]};}
function resetAll(){props.design.field_positions=JSON.parse(JSON.stringify(props.defaults.field_positions));for(const field of props.design.custom_fields||[])Object.assign(field,{x:10,y:35,width:60,size:10});}
function uploadBackground(event){const file=event.target.files?.[0];if(!file)return;if(!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>2097152){uploadError.value='Choose PNG, JPG or WebP up to 2 MB.';event.target.value='';return;}uploadError.value='';if(localBackground.value)URL.revokeObjectURL(localBackground.value);localBackground.value=URL.createObjectURL(file);const image=new Image();image.onload=()=>{props.design.background_aspect_ratio=image.naturalWidth/image.naturalHeight;};image.src=localBackground.value;emit('background',file);}
onBeforeUnmount(()=>{if(localBackground.value)URL.revokeObjectURL(localBackground.value);});
</script>
