<template>
    <div
        class="mcq-hall-ticket-preview select-none"
        :style="{
            border: `1.5px solid ${currentPrimary}`,
            padding: design.layout === 'compact' ? '10px 12px' : '14px 16px',
            fontFamily: 'Arial, Helvetica, sans-serif',
            color: '#1e293b',
            background: '#ffffff',
            maxWidth: '740px',
            margin: '0 auto',
            fontSize: design.layout === 'compact' ? '11px' : '12px',
            lineHeight: '1.35',
        }"
    >
        <!-- 1. DUAL HEADER: SCHOOL & BOARD -->
        <div
            class="flex items-center justify-between pb-2 mb-2 gap-2.5"
            :style="{ borderBottom: `2px solid ${currentPrimary}` }"
        >
            <div class="w-14 h-14 flex items-center justify-center shrink-0">
                <img v-if="logoPreviewUrl" :src="logoPreviewUrl" alt="Logo" class="max-w-full max-h-full object-contain">
                <div v-else class="w-full h-full border border-dashed border-slate-400 rounded-full flex items-center justify-center text-[8px] text-slate-500 font-bold uppercase">
                    Logo
                </div>
            </div>

            <div class="flex-1 text-center px-2">
                <h1 class="m-0 font-extrabold uppercase tracking-wide text-sm sm:text-base leading-tight" :style="{ color: currentPrimary }">
                    {{ design.organization_name || 'SAHODAYA SCHOOLS COMPLEX' }}
                </h1>
                <p v-if="design.sub_heading" class="text-[10.5px] font-semibold text-slate-700 m-0 mt-0.5">
                    {{ design.sub_heading }}
                </p>
                <p v-if="design.sub_title" class="text-[9px] text-slate-500 m-0 mt-0.5">
                    {{ design.sub_title }}
                </p>
            </div>

            <div class="w-14 h-14 flex items-center justify-center shrink-0">
                <div class="w-full h-full border border-dashed border-slate-400 rounded-full flex items-center justify-center text-[8px] text-slate-500 font-bold uppercase">
                    Seal
                </div>
            </div>
        </div>

        <!-- 2. INVERTED TITLE BAR -->
        <div
            class="flex items-center justify-between px-3 py-1.5 font-bold uppercase tracking-wider text-[11px] mb-2 text-white"
            :style="{ background: currentPrimary }"
        >
            <div class="w-1/3 text-left">{{ design.title_left || 'Hall Ticket — Theory' }}</div>
            <div class="w-1/3 text-center text-slate-100 truncate px-1">{{ sample.exam_title || 'MCQ Examination' }}</div>
            <div class="w-1/3 text-right text-slate-200">{{ sample.class_name || 'Class 10' }}</div>
        </div>

        <!-- 3. CANDIDATE PARTICULARS + QR + PHOTO -->
        <div class="flex items-start gap-3 pb-2 mb-2 border-b border-slate-300">
            <div class="flex-1">
                <table class="w-full text-[11px] border-collapse">
                    <tbody>
                        <tr v-if="design.show_roll_no !== false">
                            <td class="w-32 py-0.5 font-semibold text-slate-600">Roll / Ticket No</td>
                            <td class="w-3 text-center font-bold text-slate-500">:</td>
                            <td class="py-0.5 text-sm font-black tracking-wider" :style="{ color: currentAccent }">
                                {{ sample.hall_ticket_no || '10001' }}
                            </td>
                        </tr>
                        <tr v-if="design.show_reg_no !== false && (sample.secondary_value || sample.student_reg_no || sample.admission_no)">
                            <td class="py-0.5 font-semibold text-slate-600">{{ sample.secondary_label || 'Admission No' }}</td>
                            <td class="w-3 text-center font-bold text-slate-500">:</td>
                            <td class="py-0.5 font-bold text-slate-900 uppercase">{{ sample.secondary_value || sample.student_reg_no || sample.admission_no }}</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 font-semibold text-slate-600">Candidate Name</td>
                            <td class="w-3 text-center font-bold text-slate-500">:</td>
                            <td class="py-0.5 font-bold text-slate-900 uppercase">{{ sample.participant_name || sample.student_name || 'Sample Student' }}</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 font-semibold text-slate-600">Class / Section</td>
                            <td class="w-3 text-center font-bold text-slate-500">:</td>
                            <td class="py-0.5 font-bold text-slate-900 uppercase">
                                {{ sample.class_name || 'Class 10' }} <template v-if="sample.section_name">- {{ sample.section_name }}</template>
                            </td>
                        </tr>
                        <tr v-if="design.show_school !== false && sample.school_name">
                            <td class="py-0.5 font-semibold text-slate-600">School Name</td>
                            <td class="w-3 text-center font-bold text-slate-500">:</td>
                            <td class="py-0.5 font-semibold text-slate-900">{{ sample.school_name }}</td>
                        </tr>
                        <tr v-if="sample.father_name">
                            <td class="py-0.5 font-semibold text-slate-600">Father's Name</td>
                            <td class="w-3 text-center font-bold text-slate-500">:</td>
                            <td class="py-0.5 font-semibold text-slate-900 uppercase">{{ sample.father_name }}</td>
                        </tr>
                        <tr v-if="sample.academic_year">
                            <td class="py-0.5 font-semibold text-slate-600">Academic Year</td>
                            <td class="w-3 text-center font-bold text-slate-500">:</td>
                            <td class="py-0.5 font-semibold text-slate-900">{{ sample.academic_year }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Verification QR -->
            <div v-if="design.show_qr !== false" class="w-20 text-center shrink-0">
                <div class="w-20 h-20 border border-slate-300 p-1 rounded bg-white flex items-center justify-center">
                    <img v-if="sample.qr_src" :src="sample.qr_src" alt="QR" class="w-full h-full object-contain">
                    <span v-else class="text-[8px] text-slate-400">QR Code</span>
                </div>
                <span class="block text-[7.5px] font-bold text-slate-500 uppercase mt-0.5">Scan to Verify</span>
            </div>

            <!-- Candidate Photo -->
            <div v-if="design.show_photo !== false" class="w-20 text-center shrink-0">
                <div class="w-20 h-24 border border-slate-700 bg-slate-50 flex items-center justify-center text-[8px] text-slate-400 uppercase text-center p-1 leading-tight">
                    Affix Passport<br>Photograph
                </div>
            </div>
        </div>

        <!-- 4. AUDIT & GENERATION STRIP -->
        <div v-if="design.show_audit_bar !== false" class="flex border border-slate-700 mb-2 bg-slate-50 text-[9.5px] font-semibold">
            <div class="w-1/2 p-1 px-2 border-r border-slate-700 text-slate-700">
                <strong>Hall Ticket Downloaded:</strong> {{ sample.generated_at || '22-09-2026 21:30:00' }}
            </div>
            <div class="w-1/2 p-1 px-2 text-slate-700">
                <strong>Security Ref:</strong> {{ sample.security_ref || 'SEC-VERIFIED' }}
            </div>
        </div>

        <!-- 5. EXAM CENTER & SEATING -->
        <div v-if="design.show_center_box !== false" class="border border-slate-700 flex mb-2">
            <div class="w-28 bg-slate-100 border-r border-slate-700 flex items-center justify-center text-center font-bold text-[10.5px] text-slate-900 p-1.5 uppercase">
                Exam Center<br>& Seating
            </div>
            <div class="flex-1 p-1.5 px-2 text-[10.5px] leading-snug">
                <div class="font-extrabold" :style="{ color: currentPrimary }">Center Code: {{ sample.center_code || 'CEN-4201' }}</div>
                <div class="font-bold text-slate-900">{{ sample.center_name || 'Designated Examination Centre' }}</div>
                <div class="text-slate-600">{{ sample.center_address || 'Main Campus Examination Hall, Alappuzha' }}</div>
                <div class="mt-1 font-bold">
                    Hall / Room: <span :style="{ color: currentAccent }">{{ sample.hall_room || 'Hall A - Room 102' }}</span>
                    &nbsp;|&nbsp;
                    Seat No: <span :style="{ color: currentAccent }">{{ sample.seat_no || '24' }}</span>
                </div>
            </div>
        </div>

        <!-- 6. CLASS MCQ EXAM DETAILS MATRIX (PER CLASS, NOT MULTI-SUBJECT) -->
        <table v-if="design.show_exam_schedule !== false" class="w-full border-collapse border border-slate-700 mb-2 text-[10.5px]">
            <thead>
                <tr class="text-white text-center font-bold tracking-wider" :style="{ background: currentPrimary }">
                    <th colspan="2" class="p-1 border border-slate-700 text-[11px]">
                        MCQ EXAMINATION SCHEDULE & PAPER SPECIFICATIONS
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="w-1/4 p-1 px-2 border border-slate-700 font-bold bg-slate-50 text-slate-700">Class Level / Scope</td>
                    <td class="p-1 px-2 border border-slate-700 font-bold text-slate-900">
                        {{ sample.class_name || 'Class 10' }}
                        <span class="inline-block bg-sky-100 text-sky-800 font-bold px-1.5 py-0.5 rounded text-[9px] ml-1.5">
                            Single Session Composite Test
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="p-1 px-2 border border-slate-700 font-bold bg-slate-50 text-slate-700">Examination Paper</td>
                    <td class="p-1 px-2 border border-slate-700 font-semibold text-slate-900">
                        {{ sample.paper_name || sample.exam_title || 'MCQ Aptitude & Science Assessment' }}
                        <span class="text-slate-500 font-normal ml-1">({{ sample.exam_code || 'MCQ-X-2026' }})</span>
                    </td>
                </tr>
                <tr>
                    <td class="p-1 px-2 border border-slate-700 font-bold bg-slate-50 text-slate-700">Date of Examination</td>
                    <td class="p-1 px-2 border border-slate-700 font-extrabold" :style="{ color: currentPrimary }">
                        {{ sample.exam_date || 'Saturday, 28 October 2026' }}
                    </td>
                </tr>
                <tr>
                    <td class="p-1 px-2 border border-slate-700 font-bold bg-slate-50 text-slate-700">Exam Timings & Duration</td>
                    <td class="p-1 px-2 border border-slate-700 font-bold text-slate-900">
                        {{ sample.exam_time || sample.scheduled_at_label || '10:00 AM – 11:30 AM' }} &nbsp;
                        (Duration: {{ sample.duration_minutes || 90 }} Minutes)
                    </td>
                </tr>
                <tr>
                    <td class="p-1 px-2 border border-slate-700 font-bold bg-slate-50 text-slate-700">Reporting & Gate Closure</td>
                    <td class="p-1 px-2 border border-slate-700 font-semibold text-slate-900">
                        Reporting Time: <strong class="text-emerald-700">{{ sample.report_time_label || '09:15 AM' }}</strong>
                        &nbsp;|&nbsp;
                        Gate Closure: <strong :style="{ color: currentAccent }">{{ sample.gate_closure_label || '09:45 AM' }} Strictly</strong>
                    </td>
                </tr>
                <tr>
                    <td class="p-1 px-2 border border-slate-700 font-bold bg-slate-50 text-slate-700">Paper Pattern & Mode</td>
                    <td class="p-1 px-2 border border-slate-700 font-semibold text-slate-900">
                        Total Questions: <strong>{{ sample.total_questions || 60 }} MCQs</strong> &nbsp;|&nbsp;
                        Max Marks: <strong>{{ sample.max_marks || 60 }}</strong> &nbsp;|&nbsp;
                        Mode: <strong>{{ design.exam_mode_label || 'OMR Answer Sheet (Pen & Paper)' }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- 7. MCQ INSTRUCTIONS -->
        <div v-if="instructionsList.length" class="border border-slate-300 bg-slate-50/50 p-2 mb-2">
            <div class="text-[10.5px] font-extrabold uppercase tracking-wide mb-1" :style="{ color: currentAccent }">
                {{ design.instructions_title || 'Important Instructions for MCQ / OMR Examination' }}
            </div>
            <ol class="m-0 pl-4 text-[9px] text-slate-800 leading-tight space-y-0.5">
                <li v-for="(inst, idx) in instructionsList" :key="idx">{{ inst }}</li>
            </ol>
        </div>

        <!-- 8. TRI-SIGNATORY AUTHENTICATION -->
        <div v-if="design.show_signature !== false" class="flex justify-between items-end mt-4 pt-1 border-t border-dashed border-slate-400">
            <div v-if="design.show_candidate_sig !== false" class="w-[30%] text-center">
                <div class="border-t border-slate-700 pt-1 mt-6 text-[9.5px] font-bold text-slate-800 uppercase">
                    Candidate's Signature
                </div>
                <div class="text-[7.5px] text-slate-500">(In presence of Invigilator)</div>
            </div>

            <div v-if="design.show_invigilator_sig !== false" class="w-[30%] text-center">
                <div class="border-t border-slate-700 pt-1 mt-6 text-[9.5px] font-bold text-slate-800 uppercase">
                    Invigilator's Signature
                </div>
                <div class="text-[7.5px] text-slate-500">(Verified Photo & OMR Code)</div>
            </div>

            <div v-if="design.show_controller_sig !== false" class="w-[30%] text-center">
                <div class="border-t border-slate-700 pt-1 mt-6 text-[9.5px] font-bold text-slate-800 uppercase">
                    {{ design.signatory_title || 'Controller of Examinations' }}
                </div>
                <div class="text-[7.5px] text-slate-500">(Official Seal & Authentication)</div>
            </div>
        </div>

        <!-- 9. DISCLAIMER & FOOTER NOTE -->
        <p v-if="design.footer_note" class="text-[9.5px] text-slate-700 text-center font-semibold mt-2 mb-0.5">
            {{ design.footer_note }}
        </p>

        <p v-if="design.disclaimer" class="text-[8px] text-slate-500 text-center border-t border-slate-200 pt-1 mt-1 mb-0 leading-tight">
            <strong>Disclaimer:</strong> {{ design.disclaimer }}
        </p>
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

const currentPrimary = computed(() => props.design.primary_color || '#1e3a8a');
const currentAccent = computed(() => props.design.accent_color || '#b91c1c');
const logoPreviewUrl = computed(() => props.logoPreview || props.logoUrl || null);

const instructionsList = computed(() => {
    if (Array.isArray(props.design.instructions) && props.design.instructions.length) {
        return props.design.instructions;
    }
    return [
        'Candidates must report at the examination centre strictly at or before the Reporting Time. No entry after Gate Closure.',
        'Candidate must bring this printed Hall Ticket along with their School Identity Card or valid Photo ID.',
        'Use ONLY Blue or Black Ballpoint Pen to darken bubbles on the OMR sheet. Gel pens and pencils are prohibited.',
        'Completely darken one circle per question (⚫). Half-filled or multiple marked responses will receive zero marks.',
        'Verify that Question Booklet Code and OMR Sheet Code match before beginning.',
        'Mobile phones, calculators, smartwatches, and electronic gadgets are strictly banned inside the examination hall.',
        'Rough calculations must be performed strictly in the designated space in the Question Booklet.',
        'Candidates must sign the attendance roster in presence of the room invigilator.'
    ];
});
</script>

