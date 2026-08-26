<template>
    <div class="sidebar-nav-group">
        <button type="button"
                class="w-full flex items-center gap-1.5 px-3 pt-4 pb-1 text-[11px] font-bold text-[#fbbf24]/90 uppercase tracking-widest"
                :aria-expanded="isOpen"
                @click="toggle">
            <svg class="w-3 h-3 shrink-0 transition-transform duration-150" :class="{ '-rotate-90': !isOpen }"
                 fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
            <span class="flex-1 text-left truncate">{{ group.section }}</span>
        </button>
        <div v-show="isOpen" class="space-y-0.5">
            <SahodayaNavItem v-for="item in group.items" :key="item.href"
                              :href="item.href"
                              :icon="item.icon"
                              :label="item.label"
                              :badge="item.badge ?? 0"
                              :active="itemActive(item)" />
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import SahodayaNavItem from '@/Components/sahodaya/SahodayaNavItem.vue';

const props = defineProps({
    group: { type: Object, required: true },
    itemActive: { type: Function, required: true },
    storageKey: { type: String, required: true },
    forceOpen: { type: Boolean, default: false },
});

function readStoredOpen() {
    try {
        const raw = window.localStorage.getItem(props.storageKey);
        return raw === null ? null : raw === '1';
    } catch {
        return null;
    }
}

const hasActiveItem = computed(() => props.group.items.some((item) => props.itemActive(item)));
const stored = readStoredOpen();
const manuallyOpen = ref(stored === null ? hasActiveItem.value : stored);

const isOpen = computed(() => props.forceOpen || manuallyOpen.value);

function toggle() {
    manuallyOpen.value = !manuallyOpen.value;
    try {
        window.localStorage.setItem(props.storageKey, manuallyOpen.value ? '1' : '0');
    } catch {
        // Storage unavailable (private mode, quota) — collapse state just won't persist.
    }
}
</script>
