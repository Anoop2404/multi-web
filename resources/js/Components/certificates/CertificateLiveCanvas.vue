<template>
    <div class="certificate-live-canvas relative w-full overflow-hidden rounded-xl border border-slate-300 bg-slate-950 shadow-lg select-none" ref="wrapperRef">
        <!-- Aspect ratio container — landscape (1123x794, ~70.703%) or portrait (794x1123, ~141.435%) to match layout.orientation -->
        <div class="relative w-full bg-slate-900" :style="{ paddingBottom: `${aspectRatioPct}%` }">
            <!-- Scaled Inner Canvas Container matching Blade certificate-print.blade.php's own dimensions -->
            <div class="absolute top-0 left-0 origin-top-left bg-white transition-all overflow-hidden"
                 :style="{ width: `${canvasWidth}px`, height: `${canvasHeight}px`, transform: `scale(${scaleFactor})` }">

                <!-- Background Image Backdrop — background-size:100% 100% (stretch), matching the
                     blade's own background-size exactly rather than bg-cover's crop-to-fill, so
                     what you see here matches the real print output pixel-for-pixel. -->
                <div v-if="bgUrl" class="w-full h-full bg-no-repeat"
                     :style="{ backgroundImage: `url('${bgUrl}')`, backgroundSize: '100% 100%', backgroundPosition: 'center' }">
                </div>

                <div v-else class="w-full h-full flex flex-col items-center justify-center p-12 text-center bg-slate-100 text-slate-400 border-8 border-double border-indigo-900">
                    <span class="text-6xl mb-4">📜</span>
                    <p class="text-xl font-bold text-slate-700">Default Certificate Backdrop</p>
                    <p class="text-sm text-slate-500 mt-1">Upload a PDF or image background to view custom artwork</p>
                </div>

                <!-- Sahodaya Logo Badge Overlay (top-left) -->
                <div v-if="showLogoOverlay"
                     class="absolute flex items-center gap-1.5 bg-white/70 rounded shadow-sm"
                     style="top: 16px; left: 16px; padding: 4px 8px;">
                    <span class="text-[9px] font-bold text-slate-800 max-w-[120px] leading-tight">{{ sampleData.sahodaya_name }}</span>
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

                <!-- Participant Photo Overlay -->
                <div v-if="showPhoto"
                     class="absolute rounded-full overflow-hidden bg-white shadow-lg"
                     :style="{
                         top: `${photoLayout.top ?? 31}%`,
                         left: `${photoLayout.left ?? 50}%`,
                         width: `${photoLayout.size ?? 118}px`,
                         height: `${photoLayout.size ?? 118}px`,
                         transform: 'translateX(-50%)',
                         border: '3px solid #fdfaf0',
                     }">
                    <svg viewBox="0 0 100 100" class="w-full h-full">
                        <rect width="100" height="100" fill="#f1f5f9" />
                        <circle cx="50" cy="38" r="20" fill="#94a3b8" />
                        <path fill="#94a3b8" d="M50 63c-22 0-38 12-38 27v10h76V90c0-15-16-27-38-27z" />
                    </svg>
                </div>

                <!-- Recipient Name Overlay -->
                <div v-if="showRecipientName && recipientNameLayout"
                     class="absolute text-center text-slate-900 leading-snug whitespace-nowrap overflow-hidden text-ellipsis"
                     :style="overlayStyle(recipientNameLayout, { top: 38, left: 10, width: 80, font_size: 24, font_family: 'Montserrat', font_weight: 'bold' })">
                    {{ sampleRecipientName }}
                </div>

                <!-- Body Text Paragraph Overlay -->
                <div v-if="bodyLayout"
                     class="absolute text-center text-slate-700 leading-relaxed"
                     :style="overlayStyle(bodyLayout, { top: 48, left: 12, width: 76, font_size: 12.5, font_family: 'Montserrat' })">
                    <p v-for="(paragraph, idx) in paragraphs" :key="idx" class="mb-2" v-html="paragraph"></p>
                </div>

                <!-- Date Overlay -->
                <div v-if="showCertificateDate && dateLayout"
                     class="absolute text-slate-800"
                     :style="overlayStyle(dateLayout, { top: 72, left: 8, width: 42, font_size: 12, font_family: 'Montserrat', align: 'left' })">
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
                100% Pixel-Match Canvas ({{ canvasWidth }} × {{ canvasHeight }} A4 {{ isPortrait ? 'Portrait' : 'Landscape' }})
            </span>
            <span class="font-mono text-slate-500">Scale: {{ (scaleFactor * 100).toFixed(0) }}%</span>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

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

// Mirrors certificate-print.blade.php's own $__orientation logic: portrait swaps the
// canvas to 794x1123 instead of the default 1123x794 landscape.
const isPortrait = computed(() => props.layout?.orientation === 'portrait');
const canvasWidth = computed(() => (isPortrait.value ? 794 : 1123));
const canvasHeight = computed(() => (isPortrait.value ? 1123 : 794));
const aspectRatioPct = computed(() => (canvasHeight.value / canvasWidth.value) * 100);

const scaleFactor = computed(() => Math.max(0.2, wrapperWidth.value / canvasWidth.value));

const bgUrl = computed(() => props.localFileUrl || props.backgroundUrl);

const showRecipientName = computed(() => props.layout?.show_recipient_name !== false);
const showParticipationLabel = computed(() => props.layout?.show_participation_label !== false);
const boldVariables = computed(() => props.layout?.bold_variables !== false);
const showCertificateDate = computed(() => props.layout?.show_certificate_date !== false);
const showLogoOverlay = computed(() => props.layout?.show_logo_overlay !== false);

const showPhoto = computed(() => props.layout?.show_photo === true);
const photoLayout = computed(() => props.layout?.photo ?? {});

const participationLabelCover = computed(() => props.layout?.participation_label_cover);
const recipientNameLayout = computed(() => props.layout?.recipient_name);
const bodyLayout = computed(() => props.layout?.body);
const dateLayout = computed(() => props.layout?.certificate_date);
const uuidLayout = computed(() => props.layout?.uuid);

const fontFamilyStackMap = {
    'Montserrat': 'Montserrat, Arial, sans-serif',
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

    const style = {
        top: `${field.top ?? fallback.top ?? 0}%`,
        left: `${field.left ?? fallback.left ?? 10}%`,
        width: `${field.width ?? fallback.width ?? 80}%`,
        fontSize: `${size}px`,
        fontFamily,
        fontWeight,
        fontStyle,
    };

    if (align && ['left', 'right', 'center', 'justify'].includes(align)) {
        style.textAlign = align;
    }

    return style;
}

const sampleRecipientName = computed(() => {
    if (props.eventType === 'fest') return 'MADHAV AJITH';
    if (props.eventType === 'topper') return 'ARJUNKRISHNAN NAMBIAR';
    return 'Dr. Rajesh Kumar';
});

const sampleData = computed(() => {
    const pageSahodaya = page.props.sahodaya?.name || page.props.tenant?.name || 'Sahodaya Complex';
    return {
        recipient_name: sampleRecipientName.value,
        school_name: 'Sample Model School',
        sahodaya_name: pageSahodaya,
        program_title: props.title || 'Sahodaya Teacher Leadership Training',
        event_title: props.title || 'Annual Sports Meet 2026',
        item_title: '100m Sprint Boys (U17)',
        item_details: '100m Sprint Boys (U17)',
        event_name: props.title || 'Annual Sports Meet 2026',
        category_name: 'Category I',
        participation_type: 'Individual',
        event_dates: '21st - 23rd July 2026',
        conducted_on: '22nd July 2026',
        certificate_date: '22nd July 2026',
        venue: `${pageSahodaya} Central Complex`,
        days_attended: '2',
        training_hours: '12',
        salutation: 'Mr.',
        designation: 'Senior PGT Teacher',
        class: 'Class X',
        academic_year: '2026-27',
        percentage: '98.4%',
        rank: 'First Rank',
        achievement_line: 'secured First Place in 100m Sprint',
    };
});

// Mirrors FestCertificateService::participationItemsBoxHtml()'s current markup/styling
// closely enough to preview layout — this is authoring-preview only, never used for a
// real certificate (the server always regenerates the real box from real registrations).
const participationItemsBoxSample = '<div style="border:1px solid #d6a95c;border-radius:6px;padding:6px 10px;margin:5px auto 0;max-width:98%;background:rgba(180,83,9,0.04);">'
    + '<div style="text-align:center;font-size:0.85em;font-weight:700;letter-spacing:1.5px;color:#b45309;text-transform:uppercase;margin-bottom:5px;">&bull;&nbsp;Participated Items&nbsp;&bull;</div>'
    + '<table style="width:100%;border-collapse:collapse;"><tr>'
    + '<td style="width:50%;vertical-align:top;padding:2px 6px 2px 0;"><span style="display:block;font-size:0.95em;line-height:1.35;color:#172033;">&bull;&nbsp;<strong>100m Sprint</strong> <span style="font-size:0.86em;font-weight:400;color:#64748b;">(Category I &bull; Individual)</span></span></td>'
    + '<td style="width:50%;vertical-align:top;padding:2px 6px 2px 0;"><span style="display:block;font-size:0.95em;line-height:1.35;color:#172033;">&bull;&nbsp;<strong>Long Jump</strong> <span style="font-size:0.86em;font-weight:400;color:#64748b;">(Category I &bull; Individual)</span></span></td>'
    + '</tr></table></div>';

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

    // Special-cased like resolveFieldValues() does server-side: this is a pre-built HTML
    // blob standing in for participation_items_box, not a plain-text value, so it must
    // not be run through the <strong>-wrapping loop below like the other tokens.
    raw = raw.replace(/\{participation_items_box\}/gi, participationItemsBoxSample);

    // Replace placeholder tokens
    for (const [key, val] of Object.entries(sampleData.value)) {
        const pattern = new RegExp(`\\{${key}\\}`, 'gi');
        const formattedVal = boldVariables.value ? `<strong>${val}</strong>` : val;
        raw = raw.replace(pattern, formattedVal);
    }

    return raw.split(/\n\s*\n/).filter(p => p.trim());
});
</script>
