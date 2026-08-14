<template>
    <SchoolAdminLayout title="Entry Form" :school="school">
        <div class="w-full max-w-7xl mx-auto py-6 px-4 md:px-8 space-y-6 font-sans">
            
            <!-- Top Controls & Sports Items Menu -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 md:p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                            <span>🏆</span> Games Competition Entry Form
                        </h1>
                        <p class="text-xs text-slate-500 mt-1">
                            Select a sport item below and click <strong>Preview PDF</strong> to view the official A4 entry form. ({{ form.regionName || 'District' }})
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
                    </div>
                </div>

                <!-- Items Menu Grid -->
                <div v-if="registeredItems && registeredItems.length">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                            <span>📋</span> Registered Sports Items ({{ registeredItems.length }})
                        </h2>
                        <span class="text-[11px] text-slate-400">Click any item card to select</span>
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
                                    class="flex-1 py-1.5 px-2 text-[10px] font-bold rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition text-center"
                                >
                                    👁️ Preview PDF
                                </button>
                                <button 
                                    @click.stop="downloadItemPdf(item.id)" 
                                    type="button" 
                                    class="flex-1 py-1.5 px-2 text-[10px] font-bold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition text-center"
                                >
                                    📥 Download
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PDF Preview Action Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center space-y-4 max-w-2xl mx-auto">
                <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center text-2xl mx-auto font-bold border border-indigo-100 shadow-xs">
                    📄
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">{{ currentItemTitle }}</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Category: <strong class="text-slate-700">{{ form.category || 'General' }}</strong> &nbsp;·&nbsp; 
                        School: <strong class="text-slate-700">{{ school.name }}</strong>
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-3 pt-3">
                    <button 
                        @click="previewSelectedPdf" 
                        type="button" 
                        class="px-6 py-2.5 text-xs font-bold rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 shadow-md transition flex items-center gap-2 cursor-pointer"
                    >
                        👁️ Preview Official Entry Form PDF
                    </button>

                    <button 
                        @click="downloadSelectedPdf" 
                        type="button" 
                        class="px-6 py-2.5 text-xs font-bold rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 shadow-md transition flex items-center gap-2 cursor-pointer"
                    >
                        📥 Download PDF
                    </button>
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
    gender: 'open',
    regionName: null,
});

const selectedItem = ref(props.selectedItemId || (props.registeredItems?.[0]?.id ?? null));

const currentItemTitle = computed(() => {
    if (!selectedItem.value) return props.event?.title || 'Games Entry Form';
    const found = (props.registeredItems || []).find(i => i.id == selectedItem.value);
    return found ? found.title : (form.value.gameName || 'Games Entry Form');
});

function selectItem(itemId) {
    selectedItem.value = itemId;
    router.get(
        `/school-admin/${props.school.id}/sports/events/${props.event.id}/games-entry-form`,
        { item_id: itemId },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function previewSelectedPdf() {
    previewItemPdf(selectedItem.value);
}

function downloadSelectedPdf() {
    downloadItemPdf(selectedItem.value);
}

function previewItemPdf(itemId) {
    const url = `/school-admin/${props.school.id}/sports/events/${props.event.id}/games-entry-form?preview=1${itemId ? `&item_id=${itemId}` : ''}`;
    window.open(url, '_blank');
}

function downloadItemPdf(itemId) {
    const url = `/school-admin/${props.school.id}/sports/events/${props.event.id}/games-entry-form?download=1${itemId ? `&item_id=${itemId}` : ''}`;
    window.location.href = url;
}
</script>
