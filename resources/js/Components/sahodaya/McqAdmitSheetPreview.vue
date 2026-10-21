<template>
    <div ref="wrapper" class="relative w-full overflow-hidden bg-white shadow-md" :style="{aspectRatio:`${geometry.pageWidth}/${geometry.pageHeight}`}">
        <div class="absolute top-0 left-0 origin-top-left bg-white" :style="{width:`${geometry.pageWidth*px}px`,height:`${geometry.pageHeight*px}px`,padding:`${10*px}px`,transform:`scale(${scale})`}">
            <div :style="{display:'grid',gridTemplateColumns:`repeat(${geometry.columns},${geometry.width*px}px)`,gap:`${geometry.gap*px}px`}">
                <div v-for="n in geometry.perPage" :key="n" :style="{width:`${geometry.width*px}px`,height:`${geometry.height*px}px`,outline:'1px dashed #cbd5e1'}"><McqAdmitCardCanvas :design="design" :sample="sample" :background="background" @remarks-overflow="$emit('remarks-overflow',$event)" @remarks-required-height="$emit('remarks-required-height',$event)" /></div>
            </div>
        </div>
    </div>
</template>
<script setup>
import {computed,onMounted,onBeforeUnmount,ref} from 'vue';
import {printGeometry} from '@/support/mcqAdmitGeometry';
import McqAdmitCardCanvas from './McqAdmitCardCanvas.vue';
defineEmits(['remarks-overflow','remarks-required-height']);
const props=defineProps({design:Object,sample:Object,background:String});
const px=96/25.4,wrapper=ref(null),availableWidth=ref(600);
const geometry=computed(()=>printGeometry(props.design));
const scale=computed(()=>availableWidth.value/(geometry.value.pageWidth*px));
let observer;
onMounted(()=>{observer=new ResizeObserver(([entry])=>availableWidth.value=entry.contentRect.width);observer.observe(wrapper.value);});
onBeforeUnmount(()=>observer?.disconnect());
</script>
