<template>
    <div>
        <button type="button" @click="open = true" class="px-4 py-2 rounded-xl border border-gray-200 bg-white text-sm font-bold text-gray-700 shadow-sm">Responsive preview</button>
        <Teleport to="body">
            <div v-if="open" class="fixed inset-0 z-[70] bg-gray-950/80 p-3 sm:p-6" role="dialog" aria-modal="true" aria-label="Responsive website preview">
                <div class="h-full rounded-2xl bg-gray-100 overflow-hidden flex flex-col">
                    <div class="p-3 bg-white border-b flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2"><strong class="text-sm text-gray-900">Draft preview</strong><span class="text-xs text-gray-400">{{ device === 'mobile' ? '390 px' : device === 'tablet' ? '768 px' : 'Full width' }}</span></div>
                        <div class="flex items-center gap-2"><div class="rounded-xl bg-gray-100 p-1"><button v-for="option in devices" :key="option.id" @click="device = option.id" class="px-3 py-1.5 rounded-lg text-xs font-bold" :class="device === option.id ? 'bg-white text-purple-700 shadow-sm' : 'text-gray-500'">{{ option.label }}</button></div><a :href="url" target="_blank" class="px-3 py-2 text-xs font-bold text-purple-700">New tab ↗</a><button @click="open = false" class="w-9 h-9 rounded-xl bg-gray-100 text-gray-600" aria-label="Close preview">×</button></div>
                    </div>
                    <div class="flex-1 overflow-auto p-3 sm:p-6 flex justify-center"><iframe :src="url" title="Sahodaya website draft" class="h-full bg-white shadow-2xl transition-all duration-300" :style="{ width: widths[device], minWidth: widths[device] }"></iframe></div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
<script setup>
import { ref } from 'vue';
defineProps({ url: { type: String, required: true } });
const open = ref(false); const device = ref('desktop');
const devices = [{ id: 'mobile', label: 'Mobile' }, { id: 'tablet', label: 'Tablet' }, { id: 'desktop', label: 'Desktop' }];
const widths = { mobile: '390px', tablet: '768px', desktop: '100%' };
</script>
