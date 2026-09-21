<template>
    <div class="id-card-live-canvas relative w-full overflow-hidden select-none"
         :class="hideFooter ? 'rounded-lg border border-slate-200 bg-white shadow-sm' : 'rounded-xl border border-slate-300 bg-slate-950 shadow-lg'"
         ref="wrapperRef">
        <div class="relative w-full"
             :class="hideFooter ? 'bg-white' : 'bg-slate-900'"
             :style="{ paddingBottom: `${aspectRatioPct}%` }">
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
                         :style="mediaFieldStyle(field, { top: 8, left: 4, width: 22, height: 26 })">
                        <img v-if="cardPhotoSrc" :src="cardPhotoSrc" alt="" class="w-full h-full object-cover">
                        <svg v-else viewBox="0 0 100 100" class="w-full h-full">
                            <rect width="100" height="100" fill="#d1d5db" />
                            <text x="50" y="54" font-family="Arial" font-size="14" fill="#6b7280" text-anchor="middle">PHOTO</text>
                        </svg>
                    </div>
                    <div v-else-if="field.type === 'qr'" class="absolute bg-slate-200 flex items-center justify-center text-[8px] text-slate-500 overflow-hidden"
                         :style="mediaFieldStyle(field, { top: 4, left: 82, width: 14, height: 14 })">
                        <img v-if="cardQrSrc" :src="cardQrSrc" alt="" class="w-full h-full object-contain">
                        <span v-else>QR</span>
                    </div>
                    <div v-else-if="field.type === 'item_list' && participatingItems(field).length"
                         class="absolute flex overflow-hidden"
                         :style="itemListStyle(field)">
                        <div v-for="(column, columnIndex) in itemColumns(field)" :key="columnIndex"
                             class="flex min-w-0 flex-1 flex-col"
                             :style="columnIndex ? { paddingLeft: '1.5%' } : { paddingRight: '1.5%' }">
                            <div v-for="item in column" :key="item.number"
                                 class="flex items-center overflow-hidden whitespace-nowrap text-ellipsis"
                                 :style="{ height: `${100 / itemRowCount(field)}%` }">
                                {{ item.number }}) {{ item.text }}
                            </div>
                        </div>
                    </div>
                    <div v-else-if="field.type === 'item_row' && sampleValue(field.source)"
                         class="absolute flex items-center overflow-hidden whitespace-nowrap text-ellipsis"
                         :style="itemRowStyle(field)">
                        {{ field.row ?? 1 }}) {{ sampleValue(field.source) }}
                    </div>
                    <div v-else-if="field.type === 'shape'" class="absolute"
                         :style="shapeStyle(field)">
                    </div>
                    <div v-else-if="field.type === 'static_text'" class="absolute overflow-hidden"
                         :style="overlayStyle(field)">
                        <span :style="field.wrap ? { display: 'block', width: '100%' } : undefined">{{ field.text }}</span>
                    </div>
                    <div v-else-if="field.type === 'divider'" class="absolute"
                         :style="dividerStyle(field)">
                    </div>
                    <div v-else-if="displayValue(field) !== null" class="absolute overflow-hidden"
                         :style="overlayStyle(field)">
                        <span :style="field.wrap ? { display: 'block', width: '100%' } : undefined">{{ displayValue(field) }}</span>
                    </div>
                </template>
            </div>
        </div>

        <div v-if="!hideFooter" class="bg-slate-950 px-4 py-2 flex items-center justify-between text-[11px] text-slate-400 border-t border-slate-800">
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
    card: { type: Object, default: null },
    hideFooter: { type: Boolean, default: false },
});

const wrapperRef = ref(null);
const wrapperWidth = ref(360);
let resizeObserver = null;

function updateWidth() {
    if (wrapperRef.value) {
        wrapperWidth.value = wrapperRef.value.clientWidth || 360;
    }
}

onMounted(() => {
    updateWidth();
    if (typeof ResizeObserver !== 'undefined' && wrapperRef.value) {
        resizeObserver = new ResizeObserver(() => {
            updateWidth();
        });
        resizeObserver.observe(wrapperRef.value);
    }
    window.addEventListener('resize', updateWidth);
});

onUnmounted(() => {
    if (resizeObserver) {
        resizeObserver.disconnect();
    }
    window.removeEventListener('resize', updateWidth);
});

const cardWidthMmNum = computed(() => Number(props.cardWidthMm) > 0 ? Number(props.cardWidthMm) : 96);
const cardHeightMmNum = computed(() => Number(props.cardHeightMm) > 0 ? Number(props.cardHeightMm) : 72);
const canvasWidth = computed(() => Math.round(cardWidthMmNum.value / 25.4 * 96));
const canvasHeight = computed(() => Math.round(cardHeightMmNum.value / 25.4 * 96));
const aspectRatioPct = computed(() => (canvasHeight.value / canvasWidth.value) * 100);
const scaleFactor = computed(() => Math.max(0.05, wrapperWidth.value / canvasWidth.value));

const bgUrl = computed(() => props.localFileUrl || props.backgroundUrl);

const cardPhotoSrc = computed(() => {
    if (props.card) {
        return props.card.photo_src || props.card.photo_url || props.card.photo || null;
    }
    return null;
});

const cardQrSrc = computed(() => {
    if (props.card) {
        return props.card.qr_src || null;
    }
    return null;
});

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
    gender_upper: 'FEMALE',
    school_code: 'ABC-001',
    student_reg_no: 'STU/27/10495',
    roll_no: '10495',
    student_seq_id: '10495',
    student_id: 'STU/27/10495',
    student_class: 'X',
    student_info_inline: 'CATEGORY: II\u2003\u2003ROLL NO: 10495\u2003\u2003GENDER: FEMALE',
    schedule: 'Sample schedule line',
    footer: 'Sample footer',
    items_inline: 'Painting Water Colour | Recitation - Malayalam | Essay Writing Malayalam | Light Music - Malayalam | Classical Music - Karnatic',
    participating_items: [
        'Power Point Presentation',
        'Elocution English',
        'Quiz Junior',
        'Painting on the Spot',
        'Group Song Malayalam',
        'Classical Dance Solo',
        'Debate Malayalam',
    ],
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
    if (props.card) {
        if (props.card[source] !== undefined && props.card[source] !== null && props.card[source] !== '') {
            return props.card[source];
        }
        if (source === 'student_id' && props.card.student_reg_no) {
            return props.card.student_reg_no;
        }
        if (source === 'subtitle' && props.card.school_name) {
            return props.card.school_name;
        }
        if (source === 'id_number' && (props.card.roll_no || props.card.student_seq_id || props.card.id_number)) {
            return props.card.roll_no || props.card.student_seq_id || props.card.id_number;
        }
        return '';
    }
    return SAMPLE[source] ?? null;
}

function displayValue(field = {}) {
    if (!field.text_format) return sampleValue(field.source);

    return String(field.text_format).replace(/\{([a-zA-Z0-9_]+)\}/g, (_, source) => {
        const value = sampleValue(source);
        return value == null || Array.isArray(value) ? '' : String(value);
    });
}

function shapeBackground(field = {}) {
    if (field.gradient_from && field.gradient_to) {
        return `linear-gradient(to right, ${field.gradient_from}, ${field.gradient_to})`;
    }
    return field.color || '#DCEBFB';
}

function rotationStyle(field = {}) {
    const rotation = Number(field.rotation ?? 0);
    return rotation !== 0
        ? { transform: `rotate(${rotation}deg)`, transformOrigin: 'center center' }
        : {};
}

function mediaFieldStyle(field = {}, fallback = {}) {
    return {
        top: `${field.top ?? fallback.top ?? 0}%`,
        left: `${field.left ?? fallback.left ?? 0}%`,
        width: `${field.width ?? fallback.width ?? 20}%`,
        height: `${field.height ?? fallback.height ?? 20}%`,
        ...rotationStyle(field),
    };
}

function shapeStyle(field = {}) {
    return {
        ...mediaFieldStyle(field, { top: 0, left: 0, width: 20, height: 10 }),
        borderRadius: `${field.radius ?? 0}mm`,
        background: shapeBackground(field),
    };
}

function dividerStyle(field = {}) {
    const vertical = (field.orientation ?? 'vertical') === 'vertical';
    return {
        top: `${field.top ?? 0}%`,
        left: `${field.left ?? 0}%`,
        borderColor: field.color || '#cbd5e1',
        borderLeftWidth: vertical ? '1px' : '0',
        borderTopWidth: vertical ? '0' : '1px',
        borderStyle: 'solid',
        height: vertical ? `${field.height ?? 10}%` : '0',
        width: vertical ? '0' : `${field.width ?? 10}%`,
        ...rotationStyle(field),
    };
}

function itemRowStyle(field = {}) {
    return {
        ...overlayStyle({
            font_size: 9,
            font_family: 'Arial',
            font_weight: 'normal',
            color: '#12345a',
            ...field,
        }),
        height: `${field.height ?? 2.4}%`,
        display: 'flex',
        alignItems: 'center',
    };
}

function participatingItems(field = {}) {
    const value = sampleValue(field.source || 'participating_items');
    const maxItems = Math.max(1, Math.min(7, Number(field.max_items ?? 7)));

    return (Array.isArray(value) ? value : [])
        .map(item => String(item || '').trim())
        .filter(Boolean)
        .slice(0, maxItems)
        .map((text, index) => ({ number: index + 1, text }));
}

function itemColumnCount(field = {}) {
    return Math.max(1, Math.min(2, Number(field.columns ?? 2)));
}

function itemRowCount(field = {}) {
    const maxItems = Math.max(1, Math.min(7, Number(field.max_items ?? 7)));

    return Math.max(1, Math.ceil(maxItems / itemColumnCount(field)));
}

function itemColumns(field = {}) {
    const items = participatingItems(field);
    const columns = itemColumnCount(field);

    // Fill across each row: 1 | 2, then 3 | 4, then 5 | 6, then 7.
    return Array.from({ length: columns }, (_, columnIndex) =>
        items.filter((_, itemIndex) => itemIndex % columns === columnIndex),
    );
}

function itemListStyle(field = {}) {
    return {
        ...overlayStyle({
            font_size: 10,
            font_family: 'Arial',
            font_weight: 'normal',
            color: '#ffffff',
            ...field,
        }),
        height: `${field.height ?? 10.6}%`,
        display: 'flex',
        alignItems: undefined,
    };
}

function overlayStyle(field = {}) {
    const size = Math.max(6, Math.min(96, Number(field.font_size ?? 10)));
    const weight = (field.font_weight ?? 'normal') === 'bold' ? '700' : '400';
    const wrap = Boolean(field.wrap);
    return {
        top: `${field.top ?? 0}%`,
        left: `${field.left ?? 0}%`,
        width: `${field.width ?? 80}%`,
        height: field.height != null ? `${field.height}%` : undefined,
        fontSize: `${size}px`,
        fontFamily: field.font_family || 'Arial, Helvetica, sans-serif',
        fontWeight: weight,
        fontStyle: (field.font_style ?? 'normal') === 'italic' ? 'italic' : 'normal',
        lineHeight: field.line_height != null ? String(field.line_height) : '1.25',
        textAlign: ['left', 'right', 'center', 'justify'].includes(field.align) ? field.align : undefined,
        color: field.color || '#1e293b',
        whiteSpace: wrap ? 'normal' : 'nowrap',
        textOverflow: wrap ? 'clip' : 'ellipsis',
        overflowWrap: wrap ? 'anywhere' : undefined,
        display: wrap ? 'flex' : undefined,
        alignItems: wrap ? 'center' : undefined,
        transform: Number(field.rotation ?? 0) !== 0 ? `rotate(${Number(field.rotation)}deg)` : undefined,
        transformOrigin: Number(field.rotation ?? 0) !== 0 ? 'center center' : undefined,
    };
}
</script>
