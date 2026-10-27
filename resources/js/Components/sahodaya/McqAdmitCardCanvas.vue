<template>
    <div ref="wrapper" class="admit-canvas" :style="{ aspectRatio: `${width} / ${height}` }">
        <div class="admit-canvas__page" :style="{ width: `${width}px`, height: `${height}px`, transform: `scale(${scale})` }">
            <template v-if="remarksArea > 0">
                <div :style="frameStyle"></div><div :style="paperStyle"></div>
            </template>
            <div ref="artwork" :style="artworkStyle">
            <img v-if="background" :src="background" class="admit-canvas__background" :style="{clipPath:remarksArea>0 && design.layout==='stse_three_up' ? 'inset(3% 5% 3% 4%)' : undefined}" alt="Admit card artwork" draggable="false">
            <div v-else class="admit-canvas__empty">Upload your admit-card background</div>
            <template v-for="(position, key) in positions" :key="key">
                <div v-if="showField(key)" :style="fieldStyle(position, key)"
                    class="admit-canvas__field" :class="{ 'is-editable': editable, 'is-selected': editable && selected === key }"
                    :role="editable ? 'button' : undefined" :tabindex="editable ? 0 : undefined" :aria-label="editable ? `Position ${labels[key] || key}` : undefined"
                    @pointerdown="beginDrag($event, key)" @pointermove="moveDrag" @pointerup="endDrag" @pointercancel="endDrag"
                    @keydown="nudge($event, key)" @click="$emit('select', key)">
                    <template v-if="key === 'student_photo'">
                        <img v-if="sample.photo_src" :src="sample.photo_src" alt="Student photo" style="width:100%;height:100%;object-fit:contain;">

                    </template>
                    <template v-else-if="['principal_signature','controller_signature'].includes(key)">
                        <img v-if="sample[`${key}_src`]" :src="sample[`${key}_src`]" alt="Principal signature" style="width:100%;height:100%;object-fit:contain;">

                    </template>
                    <template v-else-if="key.startsWith('custom_') && position.source !== 'text'"><strong v-if="position.show_label !== false && position.label">{{ position.label }}: </strong>{{ sample[position.source] || '' }}</template>
                    <template v-else>{{ values[key] ?? '' }}</template>
                </div>
            </template>
            </div>
            <div v-if="remarksArea > 0" ref="remarksElement" class="admit-remarks" :class="{'instructions-selected':editable && selected==='instructions'}" :role="editable?'button':undefined" :tabindex="editable?0:undefined" :aria-label="editable?'Select instructions text':undefined" @click="$emit('select','instructions')" @keydown.enter="$emit('select','instructions')" :style="instructionStyle">
                <strong class="admit-instruction-heading" v-if="design.remarks_title !== ''">{{ design.remarks_title || 'Remarks / Instructions' }}</strong>
                <component v-if="['numbered','bullets'].includes(design.remarks_format)" :is="design.remarks_format==='numbered'?'ol':'ul'" class="admit-instruction-list" :style="{listStyleType:design.remarks_format==='numbered'?'decimal':'disc'}"><li v-for="(point,index) in instructionPoints" :key="index">{{ point }}</li></component>
                <div v-else class="admit-remarks__text">{{ design.remarks_text }}</div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { computed, onMounted, onUpdated, onBeforeUnmount, ref } from 'vue';
import { overlayPositions, customFieldValue } from '@/support/mcqAdmitFields';
import { printGeometry, artworkBox, remarksHeight, instructionBox } from '@/support/mcqAdmitGeometry';
const props = defineProps({ design: Object, sample: { type: Object, default: () => ({}) }, background: String, editable: Boolean, selected: String });
const emit = defineEmits(['select', 'move', 'remarks-overflow','remarks-required-height']);
const remarksElement=ref(null);
function checkRemarks(){const element=remarksElement.value;emit('remarks-overflow', Boolean(element && element.scrollHeight > element.clientHeight + 1));emit('remarks-required-height',element?Math.ceil((element.scrollHeight+2)*25.4/96 + instruction.value.bottom):0);}
onUpdated(checkRemarks);
const instructionPoints=computed(()=>String(props.design.remarks_text||'').split(/\r\n|\r|\n/).map(line=>line.trim()).filter(Boolean));
const remarksArea=computed(()=>remarksHeight(props.design));
const geometry = computed(()=>printGeometry(props.design));
const box = computed(()=>artworkBox(props.design));
const instruction=computed(()=>instructionBox(props.design));
const mm=value=>`${value*96/25.4}px`;
const frameStyle=computed(()=>({position:'absolute',inset:0,background:props.design.layout==='stse_three_up'?'#f1f2f5':props.design.remarks_paper_color||'#ffffff'}));
const paperStyle=computed(()=>({position:'absolute',left:mm(instruction.value.paperX),top:mm(instruction.value.paperY),width:mm(instruction.value.paperWidth),height:mm(instruction.value.paperHeight),background:props.design.remarks_paper_color||'#ffffff'}));
const instructionStyle=computed(()=>({left:mm(instruction.value.x),bottom:mm(instruction.value.bottom),width:mm(instruction.value.width),height:mm(instruction.value.height),fontSize:`${props.design.remarks_font_size||11}px`,color:props.design.remarks_text_color||'#111111',fontFamily:props.design.remarks_text_style?.font_family||'Arial',fontWeight:props.design.remarks_text_style?.font_weight||'normal',fontStyle:props.design.remarks_text_style?.font_style||'normal',textDecoration:props.design.remarks_text_style?.text_decoration||'none',textAlign:props.design.remarks_text_style?.text_align||'left',lineHeight:props.design.remarks_text_style?.line_height||1.4,letterSpacing:`${props.design.remarks_text_style?.letter_spacing||0}px`,zIndex:60,'--instruction-theme':props.design.remarks_theme_color||'#101b6f'}));
const width = computed(()=>geometry.value.width * 96 / 25.4);
const height = computed(()=>geometry.value.height * 96 / 25.4);
const artwork = ref(null);
const artworkStyle = computed(()=>({position:'absolute',left:`${box.value.x * 96 / 25.4}px`,top:`${box.value.y * 96 / 25.4}px`,width:`${box.value.width * 96 / 25.4}px`,height:`${box.value.height * 96 / 25.4}px`}));
const wrapper = ref(null);
const wrapperWidth = ref(340);
const scale = computed(()=>wrapperWidth.value / width.value);
let observer;
let drag;
const positions=computed(()=>overlayPositions(props.design));
const baseLabels = {student_photo:'Student photo', registration:'Registration number', name:'Student name', class:'Class', date:'Examination date', centre:'Examination centre', principal_signature:'Principal signature', controller_signature:'Controller signature' };
const labels=computed(()=>({...baseLabels,...Object.fromEntries((props.design.custom_fields||[]).map(field=>[field.id,field.label||'Custom field']))}));
const values = computed(() => ({ registration:props.sample.hall_ticket_no, name:props.sample.participant_name || props.sample.student_name, class:props.sample.class_name, date:props.sample.exam_time || props.sample.exam_date, centre:props.sample.venue, ...Object.fromEntries((props.design.custom_fields||[]).map(field=>[field.id,customFieldValue(field,props.sample)])) }));
function showField(key){
    if(key==='student_photo')return props.design.show_student_photo && (props.sample.photo_src || (props.editable && props.selected===key));
    if(key==='date')return props.design.show_exam_date !== false;
    if(key==='controller_signature')return props.design.show_controller_sig !== false && (props.sample.controller_signature_src || (props.editable && props.selected===key));
    if(key==='principal_signature')return props.design.show_signature && (props.sample[`${key}_src`] || (props.editable && props.selected===key));
    return true;
}
function fieldStyle(p, key) {
    return { position:'absolute', left:`${p.x}%`, top:`${p.y}%`, width:`${p.width || 50}%`, height:key === 'student_photo' ? `${p.height || 17}%` : ['principal_signature','controller_signature'].includes(key) ? `${p.height || 6}%` : undefined, fontSize:`${p.size * box.value.height / 121.384615}px`, lineHeight:p.line_height || 1.25, letterSpacing:`${(p.letter_spacing || 0) * box.value.height / 121.384615}px`,textDecoration:p.text_decoration || 'none',zIndex:p.layer || 2, fontFamily:p.font_family || 'Arial', fontWeight:p.font_weight || 'normal', fontStyle:p.font_style || 'normal', textAlign:p.text_align || 'left', overflowWrap:'anywhere',whiteSpace:'pre-wrap', color:p.text_color || '#111111' };
}
function beginDrag(event, key) {
    if (!props.editable || event.button !== 0) return;
    event.preventDefault();
    emit('select', key);
    const p = positions.value[key];
    drag = { key, startX:event.clientX, startY:event.clientY, x:p.x, y:p.y, rect:artwork.value.getBoundingClientRect() };
    event.currentTarget.setPointerCapture(event.pointerId);
}
function moveDrag(event) {
    if (!drag) return;
    const x = Math.max(0, Math.min(95, drag.x + (event.clientX-drag.startX)/drag.rect.width*100));
    const y = Math.max(0, Math.min(92, drag.y + (event.clientY-drag.startY)/drag.rect.height*100));
    emit('move', drag.key, { x:Math.round(x*10)/10, y:Math.round(y*10)/10 });
}
function endDrag() { drag = null; }
function nudge(event, key) {
    if (!props.editable || !['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key)) return;
    event.preventDefault();
    const p=positions.value[key], step=event.shiftKey ? 1 : 0.1;
    emit('move',key,{x:Math.max(0,Math.min(95,+(p.x + (event.key==='ArrowRight'?step:event.key==='ArrowLeft'?-step:0)).toFixed(1))),y:Math.max(0,Math.min(92,+(p.y + (event.key==='ArrowDown'?step:event.key==='ArrowUp'?-step:0)).toFixed(1)))});
}
onMounted(() => { observer = new ResizeObserver(([entry]) => { wrapperWidth.value=entry.contentRect.width; }); observer.observe(wrapper.value); checkRemarks(); });
onBeforeUnmount(() => observer?.disconnect());
</script>
<style scoped>
.admit-canvas{position:relative;width:100%;margin:auto;overflow:hidden;background:white}.admit-canvas__page{position:absolute;inset:0;transform-origin:top left}.admit-canvas__background{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}.admit-canvas__empty{position:absolute;inset:0;display:grid;place-items:center;background:#f8fafc;border:1px dashed #cbd5e1;color:#64748b;font-size:12px}.admit-canvas__field{outline:1px solid transparent}.is-editable{cursor:move;touch-action:none;user-select:none}.is-editable:hover{outline:1px dashed #818cf8}.is-selected{outline:1px solid #4f46e5;background:#eef2ff66}.instructions-selected{outline:1px solid #4f46e5;outline-offset:3px}.admit-remarks{position:absolute;box-sizing:border-box;padding:8px 0;font:11px/1.4 Arial,Helvetica,sans-serif;overflow:hidden}.admit-instruction-heading{display:block;color:var(--instruction-theme);font-size:1.05em;letter-spacing:.045em;text-transform:uppercase;padding-bottom:5px;border-bottom:1px solid var(--instruction-theme)}.admit-instruction-list li::marker{color:var(--instruction-theme);font-weight:bold}.admit-instruction-list{margin:4px 0 0;padding-left:20px;list-style-position:outside}.admit-instruction-list li{padding-left:2px;margin:0 0 2px;overflow-wrap:anywhere}.admit-remarks__text{white-space:pre-wrap;overflow-wrap:anywhere;margin-top:4px}
</style>
