<template>
    <div class="certificate-live-canvas relative w-full overflow-hidden rounded-xl border border-slate-300 bg-slate-950 shadow-lg select-none" ref="wrapperRef">
        <!-- Aspect ratio container (1123 x 794 A4 Landscape = ~70.703%) -->
        <div class="relative w-full pb-[70.703%] bg-slate-900">
            <!-- Scaled Inner 1123x794 Canvas Container matching Blade certificate.blade.php -->
            <div class="absolute top-0 left-0 w-[1123px] h-[794px] origin-top-left bg-white transition-all overflow-hidden"
                 :style="{ transform: `scale(${scaleFactor})` }">
                
                <!-- Background Image Backdrop -->
                <div v-if="bgUrl" class="w-full h-full bg-cover bg-center bg-no-repeat"
                     :style="{ backgroundImage: `url('${bgUrl}')` }">
                </div>

                <div v-else class="w-full h-full flex flex-col items-center justify-center p-12 text-center bg-slate-100 text-slate-400 border-8 border-double border-indigo-900">
                    <span class="text-6xl mb-4">📜</span>
                    <p class="text-xl font-bold text-slate-700">Default Certificate Backdrop</p>
                    <p class="text-sm text-slate-500 mt-1">Upload a PDF or image background to view custom artwork</p>
                </div>

                <!-- Participation Label Cover (if disabled) -->
                <div v-if="!showParticipationLabel && participationLabelCover"
                     class="absolute bg-white/95"
                     :style="{
                         top: `${participationLabelCover.top ?? 28}%`,
                         left: `${participationLabelCover.left ?? 18}%`,
                         width: `${participationLabelCover.width ?? 64}%`,
                         height: `${participationLabelCover.height ?? 7}%`,
                     }">
                </div>

                <!-- Recipient Name Overlay -->
                <div v-if="showRecipientName && recipientNameLayout"
                     class="absolute text-center text-slate-900 leading-snug whitespace-nowrap overflow-hidden text-ellipsis"
                     :style="overlayStyle(recipientNameLayout, { top: 38, left: 10, width: 80, font_size: 28, font_family: 'Georgia', font_weight: 'bold' })">
                    {{ sampleRecipientName }}
                </div>

                <!-- Body Text Paragraph Overlay -->
                <div v-if="bodyLayout"
                     class="absolute text-center text-slate-700 leading-relaxed"
                     :style="overlayStyle(bodyLayout, { top: 48, left: 12, width: 76, font_size: 13, font_family: 'Times New Roman' })">
                    <p v-for="(paragraph, idx) in paragraphs" :key="idx" class="mb-2" v-html="paragraph"></p>
                </div>

                <!-- Date Overlay -->
                <div v-if="dateLayout"
                     class="absolute text-slate-800"
                     :style="overlayStyle(dateLayout, { top: 72, left: 8, width: 42, font_size: 12, font_family: 'Times New Roman', align: 'left' })">
                    <strong v-if="boldVariables">Date : </strong>
                    <span v-else>Date : </span>
                    <strong v-if="boldVariables">22 July 2026</strong>
                    <span v-else>22 July 2026</span>
                </div>

                <!-- Verification UUID Overlay -->
                <div v-if="uuidLayout"
                     class="absolute text-slate-400 text-center tracking-wide text-[8px]"
                     :style="overlayStyle(uuidLayout, { top: 92, left: 5, width: 90, font_size: 8, font_family: 'Arial' })">
                    Verification: Sample-Demo-UUID-12345
                </div>
            </div>
        </div>

        <!-- Canvas Footer -->
        <div class="bg-slate-950 px-4 py-2 flex items-center justify-between text-[11px] text-slate-400 border-t border-slate-800">
            <span class="flex items-center gap-1.5 text-emerald-400 font-semibold">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                100% Pixel-Match Canvas (1123 × 794 A4)
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
    layout: { type: Object, default: () => ({}) },
    bodyText: { type: String, default: '' },
    eventType: { type: String, default: 'training' },
    title: { type: String, default: '' },
});

const wrapperRef = ref(null);
const wrapperWidth = ref(560);

function updateWidth() {
    if (wrapperRef.value) {
        wrapperWidth.value = wrapperRef.value.clientWidth || 560;
    }
}

onMounted(() => {
    updateWidth();
    window.addEventListener('resize', updateWidth);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateWidth);
});

const scaleFactor = computed(() => Math.max(0.2, wrapperWidth.value / 1123));

const bgUrl = computed(() => props.localFileUrl || props.backgroundUrl);

const showRecipientName = computed(() => props.layout?.show_recipient_name !== false);
const showParticipationLabel = computed(() => props.layout?.show_participation_label !== false);
const boldVariables = computed(() => props.layout?.bold_variables !== false);

const participationLabelCover = computed(() => props.layout?.participation_label_cover);
const recipientNameLayout = computed(() => props.layout?.recipient_name);
const bodyLayout = computed(() => props.layout?.body);
const dateLayout = computed(() => props.layout?.certificate_date);
const uuidLayout = computed(() => props.layout?.uuid);

const fontFamilyStackMap = {
    'Georgia': 'Georgia, "Times New Roman", Times, serif',
    'Arial': 'Arial, Helvetica, sans-serif',
    'Helvetica': 'Helvetica, Arial, sans-serif',
    'Verdana': 'Verdana, Geneva, sans-serif',
    'Courier New': '"Courier New", Courier, monospace',
    'Palatino Linotype': '"Palatino Linotype", Palatino, "Book Antiqua", serif',
    'Garamond': 'Garamond, "Times New Roman", Times, serif',
    'Times New Roman': '"Times New Roman", Times, serif',
};

function overlayStyle(field = {}, fallback = {}) {
    const size = Math.max(6, Math.min(96, Number(field.font_size ?? fallback.font_size ?? 13)));
    const familyKey = field.font_family ?? fallback.font_family ?? 'Times New Roman';
    const fontFamily = fontFamilyStackMap[familyKey] || '"Times New Roman", Times, serif';
    const fontWeight = (field.font_weight ?? fallback.font_weight ?? 'normal') === 'bold' ? '700' : '400';
    const fontStyle = (field.font_style ?? fallback.font_style ?? 'normal') === 'italic' ? 'italic' : 'normal';
    const align = field.align ?? fallback.align ?? 'center';

    return {
        top: `${field.top ?? fallback.top ?? 0}%`,
        left: `${field.left ?? fallback.left ?? 10}%`,
        width: `${field.width ?? fallback.width ?? 80}%`,
        fontSize: `${size}px`,
        fontFamily,
        fontWeight,
        fontStyle,
        textAlign: align,
    };
}

const sampleRecipientName = computed(() => {
    if (props.eventType === 'fest') return 'MADHAV AJITH';
    if (props.eventType === 'topper') return 'ARJUNKRISHNAN NAMBIAR';
    return 'Dr. Rajesh Kumar';
});

const sampleData = computed(() => ({
    recipient_name: sampleRecipientName.value,
    school_name: 'Chinmaya Vidyalaya Kannur',
    sahodaya_name: 'Kannur Sahodaya',
    program_title: props.title || 'Sahodaya Teacher Leadership Training',
    event_title: props.title || 'Annual Sports Meet 2026',
    item_title: '100m Sprint Boys (U17)',
    event_dates: '21st - 23rd July 2026',
    conducted_on: '22nd July 2026',
    certificate_date: '22nd July 2026',
    venue: 'Sahodaya Central Complex',
    days_attended: '2',
    training_hours: '12',
    salutation: 'Mr.',
    designation: 'Senior PGT Teacher',
    class: 'Class X',
    academic_year: '2026-27',
    percentage: '98.4%',
    rank: 'First Rank',
    achievement_line: 'secured First Place in 100m Sprint',
}));

const paragraphs = computed(() => {
    let raw = props.bodyText;
    if (!raw) {
        if (props.eventType === 'fest') {
            raw = 'This is to certify that {recipient_name} of {school_name} has participated in {event_title} for {item_title} held on {event_dates}.';
        } else if (props.eventType === 'topper') {
            raw = 'Hearty Congratulations to {recipient_name} of {school_name} for outstanding academic excellence in Class X with {percentage} ({rank}).';
        } else {
            raw = 'This is to certify that {salutation} {recipient_name}, {designation} of {school_name}, has successfully completed {program_title} conducted on {conducted_on}.';
        }
    }

    // Replace placeholder tokens
    for (const [key, val] of Object.entries(sampleData.value)) {
        const pattern = new RegExp(`\\{${key}\\}`, 'gi');
        const formattedVal = boldVariables.value ? `<strong>${val}</strong>` : val;
        raw = raw.replace(pattern, formattedVal);
    }

    return raw.split(/\n\s*\n/).filter(p => p.trim());
});
</script>
