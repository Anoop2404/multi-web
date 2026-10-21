<template>
    <div class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm" role="toolbar" :aria-label="label">
        <label class="text-[10px] font-semibold uppercase text-slate-500">Font<select :value="value.font_family || 'Arial'" @change="set('font_family',$event.target.value)" class="field !py-1.5 !text-xs mt-1 w-36" aria-label="Text font family"><option v-for="family in families" :key="family">{{ family }}</option></select></label>
        <label class="text-[10px] font-semibold uppercase text-slate-500">Size<input :value="value.size || 11" @input="set('size',Number($event.target.value))" type="number" :min="minSize" :max="maxSize" step="0.5" class="field !py-1.5 !text-xs mt-1 w-16" aria-label="Text font size"></label>
        <div class="flex gap-1 self-end" role="group" aria-label="Text emphasis">
            <button v-for="format in formats" :key="format.key" type="button" :aria-label="format.label" :aria-pressed="value[format.key]===format.on" :class="buttonClass(value[format.key]===format.on)" @click="set(format.key,value[format.key]===format.on?format.off:format.on)"><span :style="format.style">{{ format.symbol }}</span></button>
        </div>
        <div class="flex gap-1 self-end" role="group" aria-label="Text alignment">
            <button v-for="align in ['left','center','right','justify']" :key="align" type="button" :aria-label="`Align text ${align}`" :aria-pressed="(value.text_align || 'left')===align" :class="buttonClass((value.text_align || 'left')===align)" @click="set('text_align',align)">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 4h16M2 12h16"/><path :d="align==='left'?'M2 8h10M2 16h10':align==='right'?'M8 8h10M8 16h10':align==='center'?'M5 8h10M5 16h10':'M2 8h16M2 16h16'"/></svg>
            </button>
        </div>
        <label class="text-[10px] font-semibold uppercase text-slate-500">Colour<input :value="value.text_color || '#111111'" @input="set('text_color',$event.target.value)" type="color" class="block h-8 w-9 mt-1 rounded border border-slate-200" aria-label="Text colour"></label>
        <label class="text-[10px] font-semibold uppercase text-slate-500">Line spacing<input :value="value.line_height || 1.25" @input="set('line_height',Number($event.target.value))" type="number" min="1" max="2.5" step="0.1" class="field !py-1.5 !text-xs mt-1 w-16" aria-label="Text line spacing"></label>
        <label class="text-[10px] font-semibold uppercase text-slate-500">Letter spacing<input :value="value.letter_spacing || 0" @input="set('letter_spacing',Number($event.target.value))" type="number" min="-1" max="5" step="0.1" class="field !py-1.5 !text-xs mt-1 w-16" aria-label="Text letter spacing"></label>
    </div>
</template>
<script setup>
defineProps({value:{type:Object,required:true},label:{type:String,default:'Text formatting'},minSize:{type:Number,default:6},maxSize:{type:Number,default:72}});
const emit=defineEmits(['change']);
const families=['Arial','Times New Roman','Georgia','Verdana','Tahoma','Courier New'];
const formats=[{key:'font_weight',label:'Bold text',symbol:'B',on:'bold',off:'normal',style:{fontWeight:'bold'}},{key:'font_style',label:'Italic text',symbol:'I',on:'italic',off:'normal',style:{fontStyle:'italic'}},{key:'text_decoration',label:'Underline text',symbol:'U',on:'underline',off:'none',style:{textDecoration:'underline'}}];
function set(key,value){emit('change',{[key]:value});}
function buttonClass(active){return ['flex h-8 w-8 items-center justify-center rounded border text-xs font-semibold',active?'border-indigo-400 bg-indigo-50 text-indigo-700':'border-slate-200 text-slate-600 hover:bg-slate-50'];}
</script>
