<template>
    <div class="min-h-screen bg-slate-100 p-4 md:p-8 font-sans">
        <!-- Control Header (Hidden during print) -->
        <div class="max-w-[210mm] mx-auto bg-white rounded-xl shadow-sm border border-slate-200 p-4 md:p-6 mb-6 no-print">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4 mb-4">
                <div>
                    <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                        <span class="text-2xl">🏆</span> Games Competition Entry Form
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Auto-loaded for <strong class="text-slate-700">{{ event?.title || 'Sports Meet' }}</strong>. Ready to print on A4 paper.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Registered Items Selector -->
                    <div v-if="registeredItems && registeredItems.length" class="flex items-center gap-2">
                        <label class="text-xs font-semibold text-slate-700 whitespace-nowrap">Registered Game:</label>
                        <select 
                            v-model="selectedItem" 
                            @change="onItemChange" 
                            class="text-xs py-1.5 px-3 border border-slate-300 rounded-lg bg-slate-50 font-medium outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="item in registeredItems" :key="item.id" :value="item.id">
                                {{ item.title }} ({{ item.category }})
                            </option>
                        </select>
                    </div>

                    <button 
                        @click="addStudentRow" 
                        type="button" 
                        class="px-3 py-2 text-xs font-semibold rounded-lg bg-slate-800 text-white hover:bg-slate-700 transition flex items-center gap-1"
                    >
                        ➕ Add Student
                    </button>

                    <button 
                        @click="printForm" 
                        type="button" 
                        class="px-4 py-2 text-xs font-bold rounded-lg bg-blue-600 text-white hover:bg-blue-700 shadow-sm transition flex items-center gap-1.5 cursor-pointer"
                    >
                        🖨️ Print / Save PDF
                    </button>
                </div>
            </div>

            <!-- Interactive Inputs Form -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <div class="md:col-span-2">
                    <label class="block font-semibold text-slate-700 mb-1">School Name with Address</label>
                    <input 
                        v-model="form.schoolName" 
                        type="text" 
                        placeholder="e.g. Crescent Higher Secondary School, Malappuram" 
                        class="w-full px-3 py-1.5 border border-slate-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Team Manager Name & Contact</label>
                    <input 
                        v-model="form.teamManager" 
                        type="text" 
                        placeholder="e.g. Mr. Abdul Rahman (+91 98470 12345)" 
                        class="w-full px-3 py-1.5 border border-slate-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Name of the Game</label>
                    <input 
                        v-model="form.gameName" 
                        type="text" 
                        placeholder="e.g. Football / Volleyball" 
                        class="w-full px-3 py-1.5 border border-slate-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Category</label>
                    <input 
                        v-model="form.category" 
                        type="text" 
                        placeholder="e.g. Under 17 / Under 19" 
                        class="w-full px-3 py-1.5 border border-slate-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Gender</label>
                    <div class="flex items-center gap-4 py-1.5">
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" value="boys" v-model="form.gender" class="text-blue-600 focus:ring-blue-500">
                            <span class="font-medium text-slate-800">Boys</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" value="girls" v-model="form.gender" class="text-blue-600 focus:ring-blue-500">
                            <span class="font-medium text-slate-800">Girls</span>
                        </label>
                    </div>
                </div>

                <div v-if="form.regionName" class="md:col-span-3">
                    <label class="block font-semibold text-slate-700 mb-1">Region</label>
                    <input 
                        v-model="form.regionName" 
                        type="text" 
                        class="w-full max-w-sm px-3 py-1.5 border border-slate-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none font-bold"
                    />
                </div>
            </div>
        </div>

        <!-- Printable Canvas Container (Exact A4 Sheet) -->
        <div class="max-w-[210mm] mx-auto flex justify-center">
            <div class="printable-form bg-white w-[210mm] min-h-[297mm] max-w-[210mm] p-[10mm_12mm] shadow-lg relative text-slate-900 font-serif flex flex-col justify-between overflow-hidden box-border">
                
                <!-- Decorative Wave Graphics -->
                <svg class="absolute top-0 right-0 w-[240px] h-[100px] pointer-events-none z-0" viewBox="0 0 260 110" fill="none">
                    <path d="M70 0 C 130 0, 160 55, 260 65 L 260 0 Z" fill="#e3a835" />
                    <path d="M0 0 C 80 0, 130 90, 260 95 L 260 0 Z" fill="#006d77" opacity="0.95" />
                    <path d="M110 0 C 170 0, 200 45, 260 50 L 260 0 Z" fill="#055158" />
                </svg>

                <div class="relative z-10">
                    <!-- Form Header with Sahodaya Logo -->
                    <div class="text-center mb-3">
                        <div class="flex justify-center mb-1">
                            <!-- Real Sahodaya Logo if available, fallback vector emblem -->
                            <img 
                                v-if="form.sahodayaLogoUrl" 
                                :src="form.sahodayaLogoUrl" 
                                alt="Sahodaya Logo" 
                                class="h-16 max-h-16 object-contain mx-auto mb-1"
                            />
                            <svg v-else class="w-32 h-14" viewBox="0 0 200 100" xmlns="http://www.w3.org/2000/svg">
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
                        <h2 class="text-xl font-black uppercase tracking-wide text-slate-900 font-sans">
                            {{ form.sahodayaName || 'MALAPPURAM CENTRAL SAHODAYA' }}
                        </h2>
                        <p class="text-[11px] font-bold text-slate-700 font-sans mt-0.5">
                            (A Movement initiated and Guided by Central Board of Secondary Education, Delhi)
                        </p>
                        <h3 class="text-lg font-bold uppercase tracking-wider text-black mt-2 mb-2">
                            GAMES COMPETITION ENTRY FORM {{ form.academicYear || '2026-27' }}
                        </h3>
                    </div>

                    <!-- Meta Information Rows -->
                    <div class="text-xs space-y-2 mb-3">
                        <div class="flex items-baseline">
                            <span class="font-bold whitespace-nowrap">Name of the School with Address:</span>
                            <div class="flex-1 border-b border-dotted border-black ml-2 font-bold px-1 min-h-[20px]">
                                {{ form.schoolName }}
                            </div>
                        </div>

                        <div class="flex items-baseline">
                            <span class="font-bold whitespace-nowrap">Team Manager's Name and Contact No. :</span>
                            <div class="flex-1 border-b border-dotted border-black ml-2 font-bold px-1 min-h-[20px]">
                                {{ form.teamManager }}
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-baseline flex-1">
                                <span class="font-bold whitespace-nowrap">Name of the Game:</span>
                                <div class="flex-1 border-b border-dotted border-black ml-2 font-bold px-1 min-h-[20px]">
                                    {{ form.gameName }}
                                </div>
                            </div>
                            <div class="flex items-baseline w-1/3">
                                <span class="font-bold whitespace-nowrap">Category:</span>
                                <div class="flex-1 border-b border-dotted border-black ml-2 font-bold px-1 min-h-[20px]">
                                    {{ form.category }}
                                </div>
                            </div>
                            <div class="flex items-center gap-4 font-bold pl-2">
                                <span>Boys <span class="inline-block w-6 h-4 border border-black text-center text-xs leading-3">{{ form.gender === 'boys' ? '✓' : '' }}</span></span>
                                <span>Girls <span class="inline-block w-6 h-4 border border-black text-center text-xs leading-3">{{ form.gender === 'girls' ? '✓' : '' }}</span></span>
                            </div>
                        </div>

                        <!-- Display Region Name directly if event is region-based (No Checkboxes) -->
                        <div v-if="form.regionName" class="flex items-baseline pt-0.5">
                            <span class="font-bold whitespace-nowrap">Region:</span>
                            <div class="border-b border-dotted border-black ml-2 font-bold px-2 min-h-[18px]">
                                {{ form.regionName }}
                            </div>
                        </div>
                    </div>

                    <!-- Students Table -->
                    <table class="w-full border-collapse border border-black text-center text-xs mt-2">
                        <thead>
                            <tr class="bg-slate-50 font-bold border-b border-black">
                                <th class="border border-black p-1.5 w-[5%]">Sl<br>No</th>
                                <th class="border border-black p-1.5 w-[22%] text-left">Name of the Student</th>
                                <th class="border border-black p-1.5 w-[7%]">Class</th>
                                <th class="border border-black p-1.5 w-[14%]">UDISE PEN NUMBER/Adm.No.</th>
                                <th class="border border-black p-1.5 w-[11%]">Date of Birth</th>
                                <th class="border border-black p-1.5 w-[14%] text-left">Father's Name</th>
                                <th class="border border-black p-1.5 w-[14%] text-left">Mother's Name</th>
                                <th class="border border-black p-1.5 w-[13%]">Photographs attested<br><span class="font-normal text-[9px]">(Sign. & Seal Principal)</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(student, index) in displayStudents" :key="index" class="border-b border-black">
                                <td class="border border-black p-1.5 font-bold">
                                    {{ index + 1 }}
                                    <button 
                                        @click="removeStudentRow(index)" 
                                        type="button" 
                                        class="no-print text-rose-500 font-bold ml-1 hover:text-rose-700" 
                                        title="Remove row"
                                    >×</button>
                                </td>
                                <td class="border border-black p-1 text-left">
                                    <input v-model="student.name" type="text" placeholder="Student Name" class="w-full border-0 outline-none bg-transparent text-xs font-semibold">
                                </td>
                                <td class="border border-black p-1">
                                    <input v-model="student.class" type="text" placeholder="IX" class="w-full border-0 outline-none bg-transparent text-xs text-center">
                                </td>
                                <td class="border border-black p-1">
                                    <input v-model="student.udise_pen" type="text" placeholder="UDISE / Adm No" class="w-full border-0 outline-none bg-transparent text-xs text-center">
                                </td>
                                <td class="border border-black p-1">
                                    <input v-model="student.dob" type="text" placeholder="DD/MM/YYYY" class="w-full border-0 outline-none bg-transparent text-xs text-center">
                                </td>
                                <td class="border border-black p-1 text-left">
                                    <input v-model="student.father_name" type="text" placeholder="Father's Name" class="w-full border-0 outline-none bg-transparent text-xs">
                                </td>
                                <td class="border border-black p-1 text-left">
                                    <input v-model="student.mother_name" type="text" placeholder="Mother's Name" class="w-full border-0 outline-none bg-transparent text-xs">
                                </td>
                                <td class="border border-black p-1.5 align-middle">
                                    <!-- Perfectly Proportioned Passport Photo Frame -->
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

                <!-- Footer Signatures -->
                <div class="flex justify-between items-end pt-8 font-bold text-xs">
                    <div class="text-center">
                        <div class="h-10"></div>
                        <div>Team manager</div>
                    </div>

                    <div class="text-center">
                        <div class="w-20 h-12 border border-dashed border-slate-400 rounded-full mx-auto mb-1 flex items-center justify-center text-[9px] text-slate-400">
                            STAMP HERE
                        </div>
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
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

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

const selectedItem = ref(props.selectedItemId || (props.registeredItems?.[0]?.id ?? null));

const students = ref((props.initialStudents && props.initialStudents.length) ? props.initialStudents : [
    { name: '', class: '', udise_pen: '', dob: '', father_name: '', mother_name: '', photo_url: null },
    { name: '', class: '', udise_pen: '', dob: '', father_name: '', mother_name: '', photo_url: null },
    { name: '', class: '', udise_pen: '', dob: '', father_name: '', mother_name: '', photo_url: null },
    { name: '', class: '', udise_pen: '', dob: '', father_name: '', mother_name: '', photo_url: null },
]);

const displayStudents = computed(() => {
    const list = [...students.value];
    while (list.length < 4) {
        list.push({ name: '', class: '', udise_pen: '', dob: '', father_name: '', mother_name: '', photo_url: null });
    }
    return list;
});

function addStudentRow() {
    students.value.push({ name: '', class: '', udise_pen: '', dob: '', father_name: '', mother_name: '', photo_url: null });
}

function removeStudentRow(index) {
    if (students.value.length > 1) {
        students.value.splice(index, 1);
    }
}

function onItemChange() {
    if (selectedItem.value && props.event?.id) {
        router.get(window.location.pathname, { item_id: selectedItem.value }, { preserveState: true, preserveScroll: true });
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
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
    }
}
</style>
