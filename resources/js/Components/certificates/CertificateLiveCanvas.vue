<template>
    <div class="certificate-live-canvas relative w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-900 shadow-lg select-none">
        <!-- Aspect ratio container (Landscape 16:11 ~ 1.414 A4 landscape) -->
        <div class="relative w-full pb-[70.7%] bg-slate-800">
            <!-- Background Image -->
            <img v-if="bgUrl" :src="bgUrl" alt="Certificate Background"
                 class="absolute inset-0 h-full w-full object-cover">
            
            <div v-else class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center text-slate-400">
                <span class="text-3xl mb-2">📜</span>
                <p class="text-xs font-semibold text-slate-300">Default Certificate Backdrop</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Upload a PDF or image background to see custom artwork</p>
            </div>

            <!-- Participation Label Cover (if disabled) -->
            <div v-if="!showParticipationLabel && participationLabelCover"
                 class="absolute bg-white/95"
                 :style="{
                     top: `${participationLabelCover.top}%`,
                     left: `${participationLabelCover.left}%`,
                     width: `${participationLabelCover.width}%`,
                     height: `${participationLabelCover.height}%`,
                 }">
            </div>

            <!-- Recipient Name Overlay -->
            <div v-if="showRecipientName && recipientNameLayout"
                 class="absolute text-center transform -translate-x-1/2 w-full px-4 truncate transition-all"
                 :style="{
                     top: `${recipientNameLayout.top}%`,
                     left: '50%',
                     fontSize: `${scaledFontSize(recipientNameLayout.font_size)}px`,
                     fontFamily: recipientNameLayout.font_family || 'Georgia',
                     fontWeight: recipientNameLayout.font_weight || 'bold',
                     fontStyle: recipientNameLayout.font_style || 'normal',
                     color: '#0f172a',
                 }">
                {{ sampleRecipientName }}
            </div>

            <!-- Body Text Paragraph Overlay -->
            <div v-if="bodyLayout"
                 class="absolute text-center transform -translate-x-1/2 transition-all leading-relaxed"
                 :style="{
                     top: `${bodyLayout.top}%`,
                     left: '50%',
                     width: `${bodyLayout.width || 76}%`,
                     fontSize: `${scaledFontSize(bodyLayout.font_size)}px`,
                     fontFamily: bodyLayout.font_family || 'Times New Roman',
                     fontWeight: bodyLayout.font_weight || 'normal',
                     fontStyle: bodyLayout.font_style || 'normal',
                     color: '#334155',
                 }">
                <div v-html="renderedBodyHtml"></div>
            </div>

            <!-- Date Overlay -->
            <div v-if="dateLayout"
                 class="absolute transition-all"
                 :style="{
                     top: `${dateLayout.top}%`,
                     left: `${dateLayout.left}%`,
                     fontSize: `${scaledFontSize(dateLayout.font_size)}px`,
                     fontFamily: dateLayout.font_family || 'Times New Roman',
                     fontWeight: dateLayout.font_weight || 'normal',
                     fontStyle: dateLayout.font_style || 'normal',
                     color: '#475569',
                 }">
                Dated: 22nd July 2026
            </div>
        </div>

        <!-- Canvas Controls Footer -->
        <div class="bg-slate-950 px-4 py-2 flex items-center justify-between text-[11px] text-slate-400 border-t border-slate-800">
            <span class="flex items-center gap-1.5 text-emerald-400 font-semibold">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                Live Template Overlay Active
            </span>
            <span class="font-mono text-slate-500">Aspect 1.41 (A4 Landscape)</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    backgroundUrl: { type: String, default: null },
    localFileUrl: { type: String, default: null },
    layout: { type: Object, default: () => ({}) },
    bodyText: { type: String, default: '' },
    eventType: { type: String, default: 'training' },
    title: { type: String, default: '' },
});

const bgUrl = computed(() => props.localFileUrl || props.backgroundUrl);

const showRecipientName = computed(() => props.layout?.show_recipient_name !== false);
const showParticipationLabel = computed(() => props.layout?.show_participation_label !== false);
const boldVariables = computed(() => props.layout?.bold_variables !== false);

const participationLabelCover = computed(() => props.layout?.participation_label_cover);
const recipientNameLayout = computed(() => props.layout?.recipient_name);
const bodyLayout = computed(() => props.layout?.body);
const dateLayout = computed(() => props.layout?.certificate_date);

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

const renderedBodyHtml = computed(() => {
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

    return raw.replace(/\n/g, '<br>');
});

function scaledFontSize(px) {
    if (!px) return 14;
    // Scale font size proportionally for screen canvas
    return Math.max(10, Math.round(Number(px) * 0.72));
}
</script>
