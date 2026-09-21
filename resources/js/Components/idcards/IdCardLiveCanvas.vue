<template>
    <div class="id-card-live-canvas relative w-full overflow-hidden rounded-xl border border-slate-300 bg-slate-950 shadow-lg select-none" ref="wrapperRef">
        <div class="relative w-full bg-slate-900" :style="{ paddingBottom: `${aspectRatioPct}%` }">
            <div class="absolute top-0 left-0 origin-top-left bg-white transition-all overflow-hidden"
                 :style="{ width: `${canvasWidth}px`, height: `${canvasHeight}px`, transform: `scale(${scaleFactor})` }">

                <img v-if="bgUrl" :src="bgUrl" alt="" class="absolute inset-0 w-full h-full object-cover">
                <div v-else class="w-full h-full flex flex-col items-center justify-center p-12 text-center bg-slate-100 text-slate-400 border-8 border-double border-indigo-900">
                    <span class="text-6xl mb-4">🪪</span>
                    <p class="text-xl font-bold text-slate-700">No background yet</p>
                    <p class="text-sm text-slate-500 mt-1">Upload a PDF or image background to view custom artwork</p>
                </div>

                <template v-for="(field, idx) in fields" :key="idx">
                    <div v-if="field.type === 'photo'" class="absolute overflow-hidden rounded-full bg-slate-200"
                         :style="{ top: `${field.top ?? 8}%`, left: `${field.left ?? 4}%`, width: `${field.width ?? 22}%`, height: `${field.height ?? 26}%` }">
                        <svg viewBox="0 0 100 100" class="w-full h-full">
                            <rect width="100" height="100" fill="#d1d5db" />
                            <text x="50" y="54" font-family="Arial" font-size="14" fill="#6b7280" text-anchor="middle">PHOTO</text>
                        </svg>
                    </div>
                    <div v-else-if="field.type === 'qr'" class="absolute bg-slate-200 flex items-center justify-center text-[8px] text-slate-500"
                         :style="{ top: `${field.top ?? 4}%`, left: `${field.left ?? 82}%`, width: `${field.width ?? 14}%`, height: `${field.height ?? 14}%` }">
                        QR
                    </div>
                    <div v-else-if="field.type === 'item_row' && sampleValue(field.source)"
                         class="absolute flex items-center overflow-hidden whitespace-nowrap text-ellipsis"
                         :style="{ top: `${field.top ?? 0}%`, left: `${field.left ?? 8}%`, width: `${field.width ?? 84}%`, height: '2.2%', fontSize: '9px', fontWeight: 'bold', color: '#12345a' }">
                        {{ field.row ?? 1 }}) {{ sampleValue(field.source) }}
                    </div>
                    <div v-else-if="field.type === 'shape'" class="absolute"
                         :style="{ top: `${field.top ?? 0}%`, left: `${field.left ?? 0}%`, width: `${field.width ?? 20}%`, height: `${field.height ?? 10}%`, borderRadius: `${field.radius ?? 0}mm`, background: shapeBackground(field) }">
                    </div>
                    <div v-else-if="field.type === 'static_text'" class="absolute leading-tight overflow-hidden whitespace-nowrap text-ellipsis"
                         :style="overlayStyle(field)">
                        {{ field.text }}
                    </div>
                    <div v-else-if="field.type === 'divider'" class="absolute"
                         :style="dividerStyle(field)">
                    </div>
                    <div v-else-if="sampleValue(field.source) !== null" class="absolute leading-tight overflow-hidden whitespace-nowrap text-ellipsis"
                         :style="overlayStyle(field)">
                        {{ sampleValue(field.source) }}
                    </div>
                </template>
            </div>
        </div>

        <div class="bg-slate-950 px-4 py-2 flex items-center justify-between text-[11px] text-slate-400 border-t border-slate-800">
            <span class="flex items-center gap-1.5 text-emerald-400 font-semibold">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                Live preview ({{ canvasWidth }} × {{ canvasHeight }}px, {{ cardWidthMm }}×{{ cardHeightMm }}mm)
            </span>
            <span class="font-mono text-slate-500">Scale: {{ (scaleFactor * 100).toFixed(0) }}%</span>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    backgroundUrl: { type: String, default: null },
    localFileUrl: { type: String, default: null },
    fields: { type: Array, default: () => [] },
    cardWidthMm: { type: [Number, String], default: 96 },
    cardHeightMm: { type: [Number, String], default: 72 },
});

const wrapperRef = ref(null);
const wrapperWidth = ref(360);

function updateWidth() {
    if (wrapperRef.value) {
        wrapperWidth.value = wrapperRef.value.clientWidth || 360;
    }
}

onMounted(() => {
    updateWidth();
    window.addEventListener('resize', updateWidth);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateWidth);
});

const cardWidthMmNum = computed(() => Number(props.cardWidthMm) > 0 ? Number(props.cardWidthMm) : 96);
const cardHeightMmNum = computed(() => Number(props.cardHeightMm) > 0 ? Number(props.cardHeightMm) : 72);
const canvasWidth = computed(() => Math.round(cardWidthMmNum.value / 25.4 * 96));
const canvasHeight = computed(() => Math.round(cardHeightMmNum.value / 25.4 * 96));
const aspectRatioPct = computed(() => (canvasHeight.value / canvasWidth.value) * 100);
const scaleFactor = computed(() => Math.max(0.2, wrapperWidth.value / canvasWidth.value));

const bgUrl = computed(() => props.localFileUrl || props.backgroundUrl);

// Mirrors the sample card IdCardTemplateController::sampleCard() renders server-side
// for the Preview links, so this authoring canvas shows the same placeholder values.
const SAMPLE = {
    name: 'LAKSHMI PRIYA VENKATARAMAN NAIR',
    subtitle: 'Sample School Name',
    detail: 'Sample Item Title',
    item_label: 'Sample Item',
    role_label: 'STUDENT',
    id_number: 'SAMPLE-0001',
    secondary_value: 'Sample',
    chest_number: '000',
    category: 'III',
    gender: 'Sample',
    school_code: 'ABC-001',
    student_reg_no: 'STU/26/0001',
    student_class: 'X',
    schedule: 'Sample schedule line',
    footer: 'Sample footer',
    item_row_1: 'Power Point Presentation',
    item_row_2: 'Elocution English',
    item_row_3: 'Quiz Junior',
    item_row_4: 'Painting on the Spot',
    item_row_5: 'Group Song Malayalam',
    item_row_6: 'Classical Dance Solo',
    item_row_7: 'Debate Malayalam',
};

function sampleValue(source) {
    if (!source) return null;
    return SAMPLE[source] ?? null;
}

function shapeBackground(field = {}) {
    if (field.gradient_from && field.gradient_to) {
        return `linear-gradient(to right, ${field.gradient_from}, ${field.gradient_to})`;
    }
    return field.color || '#DCEBFB';
}

function dividerStyle(field = {}) {
    const vertical = (field.orientation ?? 'vertical') === 'vertical';
    return {
        top: `${field.top ?? 0}%`,
        left: `${field.left ?? 0}%`,
        borderColor: '#cbd5e1',
        borderLeftWidth: vertical ? '1px' : '0',
        borderTopWidth: vertical ? '0' : '1px',
        borderStyle: 'solid',
        height: vertical ? `${field.height ?? 10}%` : '0',
        width: vertical ? '0' : `${field.width ?? 10}%`,
    };
}

function overlayStyle(field = {}) {
    const size = Math.max(6, Math.min(96, Number(field.font_size ?? 10)));
    const weight = (field.font_weight ?? 'normal') === 'bold' ? '700' : '400';
    return {
        top: `${field.top ?? 0}%`,
        left: `${field.left ?? 0}%`,
        width: `${field.width ?? 80}%`,
        fontSize: `${size}px`,
        fontFamily: field.font_family || 'Arial, Helvetica, sans-serif',
        fontWeight: weight,
        fontStyle: (field.font_style ?? 'normal') === 'italic' ? 'italic' : 'normal',
        textAlign: ['left', 'right', 'center', 'justify'].includes(field.align) ? field.align : undefined,
        color: field.color || '#1e293b',
    };
}
</script>
