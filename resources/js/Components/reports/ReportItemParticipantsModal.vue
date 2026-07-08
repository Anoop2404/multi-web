<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#041525]/50" @click="close"></div>
        <div class="relative card w-full max-w-4xl max-h-[85vh] overflow-hidden flex flex-col shadow-xl !p-0">
            <div class="px-5 py-4 border-b border-slate-100 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="font-bold text-slate-900 truncate">{{ itemTitle || 'Participants' }}</h3>
                    <p v-if="headName" class="text-xs text-slate-500 mt-0.5">{{ headName }}</p>
                    <p v-if="participants.length" class="text-xs text-slate-500 mt-1">{{ participants.length }} student(s)</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 text-xl leading-none shrink-0" @click="close">×</button>
            </div>

            <div class="px-5 py-3 overflow-y-auto flex-1">
                <p v-if="loading" class="text-sm text-slate-500 py-8 text-center">Loading participants…</p>
                <p v-else-if="error" class="text-sm text-red-600 py-4">{{ error }}</p>
                <p v-else-if="!participants.length" class="text-sm text-slate-500 py-8 text-center">
                    No participants registered for this item yet.
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="pl-4">Participant</th>
                                <th>Fest ID</th>
                                <th>Item reg</th>
                                <th>Chest</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(p, idx) in participants" :key="p.student_id ?? idx">
                                <td class="pl-4">
                                    <ReportStudentCell :name="p.name"
                                                       :reg-no="p.reg_no"
                                                       :class-label="p.class"
                                                       :photo-url="p.photo_url" />
                                </td>
                                <td class="font-mono text-xs">{{ p.fest_id ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ p.item_reg ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ p.chest_no ?? '—' }}</td>
                                <td>
                                    <span class="status-pill text-xs capitalize"
                                          :class="p.status === 'approved' ? 'status-pill--published' : 'status-pill--open'">
                                        {{ p.status ?? '—' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="px-5 py-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 bg-slate-50/80">
                <ReportDownloadButtons v-if="participants.length"
                                       :pdf-url="pdfUrl"
                                       :xls-url="exportUrl" />
                <button type="button" class="btn-secondary text-sm ml-auto" @click="close">Close</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import ReportStudentCell from '@/Components/reports/ReportStudentCell.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    fetchUrl: { type: String, default: null },
    itemTitle: { type: String, default: '' },
    headName: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const error = ref('');
const participants = ref([]);
const pdfUrl = ref(null);
const exportUrl = ref(null);

function close() {
    emit('close');
}

async function load() {
    if (!props.fetchUrl) return;
    loading.value = true;
    error.value = '';
    participants.value = [];
    pdfUrl.value = null;
    exportUrl.value = null;
    try {
        const res = await fetch(props.fetchUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('Could not load participants.');
        const data = await res.json();
        participants.value = data.participants ?? [];
        pdfUrl.value = data.pdf_url ?? null;
        exportUrl.value = data.export_url ?? null;
    } catch (e) {
        error.value = e.message || 'Failed to load participants.';
    } finally {
        loading.value = false;
    }
}

watch(() => [props.open, props.fetchUrl], ([isOpen]) => {
    if (isOpen) load();
}, { immediate: true });
</script>
