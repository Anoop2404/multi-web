<template>
    <div
        class="mcq-hall-ticket-preview"
        :style="{
            border: `2px solid ${design.primary_color}`,
            padding: design.layout === 'compact' ? '12px' : '16px',
            fontFamily: 'Arial, sans-serif',
            color: '#111',
            maxWidth: '720px',
        }"
    >
        <div class="flex justify-between items-start gap-3 mb-2.5">
            <div class="flex gap-2.5 items-start min-w-0">
                <img v-if="logoPreviewUrl" :src="logoPreviewUrl" alt="" class="w-12 h-12 object-contain shrink-0">
                <div class="min-w-0">
                    <p class="font-bold m-0" :style="{ color: design.primary_color, fontSize: design.layout === 'compact' ? '12px' : '13px' }">
                        {{ sample.exam_title }}
                    </p>
                    <p class="text-[10px] text-slate-500 mt-0.5">{{ design.header_title }}</p>
                </div>
            </div>
            <div class="text-right shrink-0">
                <span class="text-[9px] text-slate-500 block">Reg. No.</span>
                <div class="font-black leading-none" :style="{ color: design.accent_color, fontSize: design.layout === 'compact' ? '22px' : '28px' }">
                    {{ sample.hall_ticket_no }}
                </div>
            </div>
        </div>
        <table class="w-full text-xs border-collapse">
            <tbody>
                <tr><td class="py-1 border-b border-dotted border-slate-300 text-slate-500 w-[38%]">Student name</td><td class="py-1 border-b border-dotted border-slate-300 font-semibold">{{ sample.student_name }}</td></tr>
                <tr v-if="design.show_reg_no && sample.student_reg_no"><td class="py-1 border-b border-dotted border-slate-300 text-slate-500">School admission no.</td><td class="py-1 border-b border-dotted border-slate-300">{{ sample.student_reg_no }}</td></tr>
                <tr v-if="design.show_school && sample.school_name"><td class="py-1 border-b border-dotted border-slate-300 text-slate-500">School</td><td class="py-1 border-b border-dotted border-slate-300">{{ sample.school_name }}</td></tr>
                <tr><td class="py-1 border-b border-dotted border-slate-300 text-slate-500">Date & time</td><td class="py-1 border-b border-dotted border-slate-300">{{ sample.scheduled_at_label }}</td></tr>
                <tr v-if="sample.hall_room"><td class="py-1 border-b border-dotted border-slate-300 text-slate-500">Hall / room</td><td class="py-1 border-b border-dotted border-slate-300">{{ sample.hall_room }}</td></tr>
                <tr v-if="sample.seat_no"><td class="py-1 border-b border-dotted border-slate-300 text-slate-500">Seat</td><td class="py-1 border-b border-dotted border-slate-300">{{ sample.seat_no }}</td></tr>
            </tbody>
        </table>
        <p v-if="sample.hall_instructions" class="text-[10px] text-slate-700 mt-2"><strong>Instructions:</strong> {{ sample.hall_instructions }}</p>
        <p v-if="design.footer_note" class="text-[10px] text-slate-700 mt-2">{{ design.footer_note }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    design: { type: Object, required: true },
    sample: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    logoPreview: { type: String, default: null },
});

const logoPreviewUrl = computed(() => props.logoPreview || props.logoUrl || null);
</script>
