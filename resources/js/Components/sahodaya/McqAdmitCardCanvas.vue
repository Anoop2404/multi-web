<template>
    <div ref="wrapper" class="admit-canvas" :style="{ aspectRatio: `${width} / ${height}` }">
        <div class="admit-canvas__page" :style="{ width: `${width}px`, height: `${height}px`, transform: `scale(${scale})` }">
            <div ref="artwork" :style="artworkStyle">
            <img v-if="background" :src="background" class="admit-canvas__background" alt="Admit card artwork" draggable="false">
            <div v-else class="admit-canvas__empty">Upload your admit-card background</div>
            <template v-for="(position, key) in positions" :key="key">
                <div v-if="(key !== 'principal_signature' || design.show_signature) && (key !== 'student_photo' || design.show_student_photo)" :style="fieldStyle(position, key)"
                    class="admit-canvas__field" :class="{ 'is-editable': editable, 'is-selected': editable && selected === key }"
                    :role="editable ? 'button' : undefined" :tabindex="editable ? 0 : undefined" :aria-label="editable ? `Position ${labels[key] || key}` : undefined"
                    @pointerdown="beginDrag($event, key)" @pointermove="moveDrag" @pointerup="endDrag" @pointercancel="endDrag"
                    @keydown="nudge($event, key)" @click="$emit('select', key)">
                    <template v-if="key === 'student_photo'">
                        <img v-if="sample.photo_src" :src="sample.photo_src" alt="Student photo" style="width:100%;height:100%;object-fit:contain;">
                        <span v-else-if="editable || sample.is_preview" class="signature-placeholder">Student photo</span>
                    </template>
                    <template v-else-if="key === 'principal_signature'">
                        <img v-if="sample.principal_signature_src" :src="sample.principal_signature_src" alt="Principal signature" style="width:100%;height:100%;object-fit:contain;">
                        <span v-else-if="editable" class="signature-placeholder">School signature</span>
                    </template>
                    <template v-else>{{ values[key] ?? '' }}</template>
                </div>
            </template>
            </div>
        </div>
    </div>
</template>
<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { overlayPositions, customFieldValue } from '@/support/mcqAdmitFields';
import { printGeometry, artworkBox } from '@/support/mcqAdmitGeometry';
const props = defineProps({ design: Object, sample: { type: Object, default: () => ({}) }, background: String, editable: Boolean, selected: String });
const emit = defineEmits(['select', 'move']);
const geometry = computed(()=>printGeometry(props.design));
const box = computed(()=>artworkBox(props.design));
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
const baseLabels = {student_photo:'Student photo', registration:'Registration number', name:'Student name', class:'Class', date:'Examination date', centre:'Examination centre', principal_signature:'Principal signature' };
const labels=computed(()=>({...baseLabels,...Object.fromEntries((props.design.custom_fields||[]).map(field=>[field.id,field.label||'Custom field']))}));
const values = computed(() => ({ registration:props.sample.hall_ticket_no, name:props.sample.participant_name || props.sample.student_name, class:props.sample.class_name, date:props.sample.exam_date, centre:props.sample.venue, ...Object.fromEntries((props.design.custom_fields||[]).map(field=>[field.id,customFieldValue(field,props.sample)])) }));
function fieldStyle(p, key) {
    return { position:'absolute', left:`${p.x}%`, top:`${p.y}%`, width:`${p.width || 50}%`, height:key === 'student_photo' ? `${p.height || 17}%` : key === 'principal_signature' ? '6%' : undefined, fontSize:`${p.size * box.value.height / 121.384615}px`, lineHeight:'1.25', fontFamily:p.font_family || 'Arial', fontWeight:p.font_weight || 'normal', fontStyle:p.font_style || 'normal', textAlign:p.text_align || 'left', overflowWrap:'anywhere', color:'#111' };
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
onMounted(() => { observer = new ResizeObserver(([entry]) => { wrapperWidth.value=entry.contentRect.width; }); observer.observe(wrapper.value); });
onBeforeUnmount(() => observer?.disconnect());
</script>
<style scoped>
.admit-canvas{position:relative;width:100%;margin:auto;overflow:hidden;background:white}.admit-canvas__page{position:absolute;inset:0;transform-origin:top left}.admit-canvas__background{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}.admit-canvas__empty{position:absolute;inset:0;display:grid;place-items:center;background:#f8fafc;border:1px dashed #cbd5e1;color:#64748b;font-size:12px}.admit-canvas__field{outline:1px solid transparent}.is-editable{cursor:move;touch-action:none;user-select:none}.is-editable:hover{outline:1px dashed #818cf8}.is-selected{outline:1px solid #4f46e5;background:#eef2ff66}.signature-placeholder{display:flex;align-items:center;justify-content:center;width:100%;height:100%;border:1px dashed #94a3b8;font-size:8px;color:#475569;background:#ffffffb3}
</style>
