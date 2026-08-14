<template>
    <SchoolAdminLayout title="Entry Form" :school="school">
        <div class="w-full max-w-7xl mx-auto py-6 px-4 md:px-8 space-y-6 font-sans">
            
            <!-- Top Controls & Sports Items Menu (Hidden on Print) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 md:p-6 no-print space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                            <span>🏆</span> Games Competition Entry Form
                        </h1>
                        <p class="text-xs text-slate-500 mt-1">
                            Official entry form preview and export ({{ form.regionName || 'District' }})
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <button 
                            @click="previewSelectedPdf" 
                            type="button" 
                            class="px-4 py-2 text-xs font-bold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm transition flex items-center gap-1.5 cursor-pointer"
                        >
                            👁️ Preview PDF
                        </button>

                        <button 
                            @click="downloadSelectedPdf" 
                            type="button" 
                            class="px-4 py-2 text-xs font-bold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm transition flex items-center gap-1.5 cursor-pointer"
                        >
                            📥 Download PDF
                        </button>

                        <button 
                            @click="printForm" 
                            type="button" 
                            class="px-4 py-2 text-xs font-bold rounded-lg bg-slate-800 text-white hover:bg-slate-700 shadow-sm transition flex items-center gap-1.5 cursor-pointer"
                        >
                            🖨️ Print Form
                        </button>
                    </div>
                </div>

                <!-- Items Menu Bar -->
                <div v-if="registeredItems && registeredItems.length">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                            <span>📋</span> Registered Sports Items Menu ({{ registeredItems.length }})
                        </h2>
                        <span class="text-[11px] text-slate-400">Click any item card to load its form</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3.5">
                        <div 
                            v-for="item in registeredItems" 
                            :key="item.id"
                            @click="selectItem(item.id)"
                            :class="[
                                'p-3.5 rounded-xl border text-left cursor-pointer transition relative group flex flex-col justify-between',
                                selectedItem == item.id 
                                    ? 'border-blue-600 bg-blue-50/70 ring-2 ring-blue-500/20 shadow-sm' 
                                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/80'
                            ]"
                        >
                            <div>
                                <div class="flex items-center justify-between gap-1 mb-1.5">
                                    <span class="font-bold text-xs text-slate-900 line-clamp-1">
                                        {{ item.title }}
                                    </span>
                                    <span 
                                        :class="[
                                            'text-[10px] px-2 py-0.5 rounded font-semibold uppercase shrink-0',
                                            item.gender === 'girls' ? 'bg-pink-100 text-pink-700' : 'bg-blue-100 text-blue-700'
                                        ]"
                                    >
                                        {{ item.gender }}
                                    </span>
                                </div>

                                <div class="text-[11px] text-slate-500 flex items-center gap-2">
                                    <span>Cat: <strong>{{ item.category }}</strong></span>
                                    <span>•</span>
                                    <span class="text-slate-700 font-medium">{{ item.registered_count || 0 }} Athletes</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 mt-3 pt-2.5 border-t border-slate-200/60">
                                <button 
                                    @click.stop="previewItemPdf(item.id)" 
                                    type="button" 
                                    class="flex-1 py-1.5 px-2 text-[10px] font-bold rounded-lg bg-slate-100 text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition text-center"
                                >
                                    👁️ PDF
                                </button>
                                <button 
                                    @click.stop="downloadItemPdf(item.id)" 
                                    type="button" 
                                    class="flex-1 py-1.5 px-2 text-[10px] font-bold rounded-lg bg-slate-100 text-slate-700 hover:bg-emerald-50 hover:text-emerald-600 transition text-center"
                                >
                                    📥 Download
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Official Entry Form Preview Container (Full Width on Screen) -->
            <div class="w-full flex justify-center">
                <div class="printable-form bg-white w-full rounded-2xl shadow-sm border border-slate-200 p-6 md:p-10 relative text-slate-900 font-sans flex flex-col justify-between overflow-hidden box-border">
                    
                    <!-- Decorative Wave Graphics -->
                    <svg class="absolute top-0 right-0 w-[280px] h-[110px] pointer-events-none z-0" viewBox="0 0 260 110" fill="none">
                        <path d="M70 0 C 130 0, 160 55, 260 65 L 260 0 Z" fill="#e3a835" />
                        <path d="M0 0 C 80 0, 130 90, 260 95 L 260 0 Z" fill="#006d77" opacity="0.95" />
                        <path d="M110 0 C 170 0, 200 45, 260 50 L 260 0 Z" fill="#055158" />
                    </svg>

                    <div class="relative z-10">
                        <!-- Form Header with Sahodaya Logo -->
                        <div class="text-center mb-4">
                            <div class="flex justify-center mb-2">
                                <img 
                                    v-if="form.sahodayaLogoUrl" 
                                    :src="form.sahodayaLogoUrl" 
                                    alt="Sahodaya Logo" 
                                    class="h-16 max-h-16 object-contain mx-auto mb-1"
                                />
                                <svg v-else class="w-36 h-16" viewBox="0 0 200 100" xmlns="http://www.w3.org/2000/svg">
                                    <g transform="translate(100, 75)">
                                        <path d="M 0 0 L -80 -55 A 95 95 0 0 1 -60 -70 Z" fill="#22c55e"/>
                                        <path d="M 0 0 L -55 -72 A 95 95 0 0 1 -30 -85 Z" fill="#84cc16"/>
                                        <path d="M 0 0 L -25 -87 A 95 95 0 0 1 0 -92 Z" fill="#eab308"/>
                                        <path d="M 0 0 L 5 -92 A 95 95 0 0 1 30 -85 Z" fill="#f97316"/>
                                        <path d="M 0 0 L 35 -83 A 95 95 0 0 1 60 -70 Z" fill="#ef4444"/>
                                        <path d="M 0 0 L 65 -68 A 95 95 0 0 1 82 -50 Z" fill="#a855f7"/>
                                        <path d="M 0 0 L 85 -48 A 95 95 0 0 1 95 -25 Z" fill="#3b82f6"/>
                                        <circle cx="-50" cy="-45" r="7" fill="#15803d"/>
                                        <circle cx="-25" cy="-58" r="7" fill="#ca8a04"/>
                                        <circle cx="0" cy="-63" r="7" fill="#ea580c"/>
                                        <circle cx="25" cy="-58" r="7" fill="#dc2626"/>
                                        <circle cx="50" cy="-45" r="7" fill="#2563eb"/>
                                        <path d="M -50 0 A 50 50 0 0 1 50 0 Z" fill="#eab308"/>
                                    </g>
                                </svg>
                            </div>
                            <h2 class="text-[17px] font-bold uppercase tracking-wide text-slate-900 font-sans">
                                {{ form.sahodayaName || 'MALAPPURAM CENTRAL SAHODAYA' }}
                            </h2>
                            <p class="text-[9.5px] font-bold text-slate-800 font-sans mt-0.5">
                                (A Movement initiated and Guided by Central Board of Secondary Education, Delhi)
                            </p>
                            <h3 class="text-[16px] font-bold uppercase tracking-wider text-slate-900 font-sans mt-2.5 mb-3 underline decoration-1 underline-offset-4">
                                GAMES COMPETITION ENTRY FORM {{ form.academicYear || '2026-27' }}
                            </h3>
                        </div>

                        <!-- Meta Information Rows -->
                        <div class="text-[11px] space-y-2 mb-3 font-sans">
                            <div class="flex items-baseline">
                                <span class="font-bold whitespace-nowrap text-slate-900">Name of the School with Address :&nbsp;</span>
                                <div class="flex-1 border-b border-dotted border-black ml-1 font-bold px-2 min-h-[22px] text-slate-900">
                                    {{ form.schoolName }}
                                </div>
                            </div>

                            <div class="flex items-baseline">
                                <span class="font-bold whitespace-nowrap text-slate-900">Team Manager's Name and Contact No. :&nbsp;</span>
                                <div class="flex-1 border-b border-dotted border-black ml-1 font-bold px-2 min-h-[22px] text-slate-900">
                                    {{ form.teamManager || '________________________________________' }}
                                </div>
                            </div>

                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-2 md:gap-4">
                                <div class="flex items-baseline flex-1">
                                    <span class="font-bold whitespace-nowrap text-slate-900">Name of the Game :&nbsp;</span>
                                    <div class="flex-1 border-b border-dotted border-black ml-1 font-bold px-2 min-h-[22px] text-slate-900">
                                        {{ form.gameName }}
                                    </div>
                                </div>
                                <div class="flex items-baseline md:w-1/3">
                                    <span class="font-bold whitespace-nowrap text-slate-900">Category :&nbsp;</span>
                                    <div class="flex-1 border-b border-dotted border-black ml-1 font-bold px-2 min-h-[22px] text-slate-900">
                                        {{ form.category }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-4 font-bold pl-1 text-slate-900">
                                    <span>Boys <span class="inline-block w-6 h-4 border border-black text-center text-[11px] leading-3 bg-white">{{ checkBoys ? '✓' : '' }}</span></span>
                                    <span>Girls <span class="inline-block w-6 h-4 border border-black text-center text-[11px] leading-3 bg-white">{{ checkGirls ? '✓' : '' }}</span></span>
                                </div>
                            </div>

                            <div class="flex items-baseline pt-0.5">
                                <span class="font-bold whitespace-nowrap text-slate-900">Region :&nbsp;</span>
                                <div class="border-b border-dotted border-black ml-1 font-bold px-2 min-h-[20px] text-slate-900">
                                    {{ form.regionName || 'District' }}
                                </div>
                            </div>
                        </div>

                        <!-- Students Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse border border-black text-center text-[10px] mt-2 font-sans table-fixed">
                                <thead>
                                    <tr class="bg-slate-100 font-bold border-b border-black text-slate-900">
                                        <th class="border border-black p-2 w-[4%] font-bold">Sl<br>No</th>
                                        <th class="border border-black p-2 w-[22%] text-left font-bold">NAME OF THE STUDENT</th>
                                        <th class="border border-black p-2 w-[6%] font-bold">CLASS</th>
                                        <th class="border border-black p-2 w-[15%] font-bold">UDISE PEN NUMBER/ADM.NO.</th>
                                        <th class="border border-black p-2 w-[11%] font-bold">DATE OF BIRTH</th>
                                        <th class="border border-black p-2 w-[14%] text-left font-bold">FATHER'S NAME</th>
                                        <th class="border border-black p-2 w-[14%] text-left font-bold">MOTHER'S NAME</th>
                                        <th class="border border-black p-2 w-[14%] font-bold">PHOTOGRAPHS ATTESTED<br><span class="font-normal text-[8px] text-slate-700">(SIGN. & SEAL PRINCIPAL)</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(student, index) in displayStudents" :key="index" class="border-b border-black">
                                        <td class="border border-black p-2 font-bold text-slate-900 w-[4%]">
                                            {{ index + 1 }}
                                        </td>
                                        <td class="border border-black p-2 text-left font-bold text-slate-900 w-[22%] uppercase">
                                            {{ student.name || '—' }}
                                        </td>
                                        <td class="border border-black p-2 text-center font-medium text-slate-800 w-[6%]">
                                            {{ student.class || '—' }}
                                        </td>
                                        <td class="border border-black p-2 text-center font-medium text-slate-800 w-[15%]">
                                            {{ student.udise_pen || '—' }}
                                        </td>
                                        <td class="border border-black p-2 text-center font-medium text-slate-800 w-[11%]">
                                            {{ student.dob || '—' }}
                                        </td>
                                        <td class="border border-black p-2 text-left font-normal text-slate-800 w-[14%] uppercase">
                                            {{ student.father_name || '—' }}
                                        </td>
                                        <td class="border border-black p-2 text-left font-normal text-slate-800 w-[14%] uppercase">
                                            {{ student.mother_name || '—' }}
                                        </td>
                                        <td class="border border-black p-2 align-middle w-[14%]">
                                            <div class="w-[30mm] h-[38mm] mx-auto border border-dashed border-slate-400 rounded-sm flex flex-col items-center justify-center text-[9px] text-slate-500 bg-slate-50 overflow-hidden p-0.5 box-border">
                                                <img 
                                                    v-if="student.photo_url" 
                                                    :src="student.photo_url" 
                                                    alt="Student Photo" 
                                                    class="w-full h-full object-cover rounded-xs"
                                                />
                                                <template v-else>
                                                    <div class="font-medium text-slate-600 text-center leading-tight">Affix Photo</div>
                                                    <div class="text-[8px] text-slate-400 text-center mt-0.5">(Attested)</div>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Footer Signatures -->
                    <div class="flex justify-between items-end pt-10 font-bold text-[11px] font-sans text-slate-900">
                        <div class="text-center">
                            <div class="h-10"></div>
                            <div>Team manager</div>
                        </div>

                        <div class="text-center">
                            <div class="h-10"></div>
                            <div>School Seal</div>
                        </div>

                        <div class="text-center">
                            <div class="h-10"></div>
                            <div>Sign & Seal of Principal</div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({
    school: Object,
    event: Object,
    form: Object,
    initialStudents: Array,
    registeredItems: Array,
    selectedItemId: [Number, String],
});

const form = ref(props.form || {
    sahodayaName: 'MALAPPURAM CENTRAL SAHODAYA',
    sahodayaLogoUrl: null,
    academicYear: '2026-27',
    schoolName: props.school?.name || '',
    teamManager: '',
    gameName: '',
    category: '',
    gender: 'boys',
    regionName: null,
});

const checkBoys = computed(() => {
    const g = String(form.value.gender ?? '').toLowerCase();
    return !['girls', 'girl', 'female', 'f'].includes(g);
});

const checkGirls = computed(() => {
    const g = String(form.value.gender ?? '').toLowerCase();
    return !['boys', 'boy', 'male', 'm'].includes(g);
});

const selectedItem = ref(props.selectedItemId || (props.registeredItems?.[0]?.id ?? null));

const students = ref((props.initialStudents && props.initialStudents.length) ? props.initialStudents : []);

const displayStudents = computed(() => {
    const list = [...students.value];
    while (list.length < 4) {
        list.push({ name: '', class: '', udise_pen: '', dob: '', father_name: '', mother_name: '', photo_url: null });
    }
    return list;
});

function selectItem(itemId) {
    if (itemId && props.event?.id) {
        selectedItem.value = itemId;
        router.get(window.location.pathname, { item_id: itemId }, { preserveState: true, preserveScroll: true });
    }
}

function previewItemPdf(itemId) {
    const url = `${window.location.pathname}?item_id=${itemId}&preview=1`;
    window.open(url, '_blank');
}

function downloadItemPdf(itemId) {
    const url = `${window.location.pathname}?item_id=${itemId}&download=1`;
    window.location.href = url;
}

function previewSelectedPdf() {
    if (selectedItem.value) {
        previewItemPdf(selectedItem.value);
    } else {
        const url = `${window.location.pathname}?preview=1`;
        window.open(url, '_blank');
    }
}

function downloadSelectedPdf() {
    if (selectedItem.value) {
        downloadItemPdf(selectedItem.value);
    } else {
        const url = `${window.location.pathname}?download=1`;
        window.location.href = url;
    }
}

function printForm() {
    window.print();
}
</script>

<style scoped>
@media print {
    @page {
        size: A4 portrait;
        margin: 8mm;
    }
    .no-print {
        display: none !important;
    }
    body {
        background: white;
    }
    .printable-form {
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        width: 210mm !important;
        max-width: 210mm !important;
        padding: 10mm 12mm !important;
        margin: 0 auto !important;
    }
}
</style>
