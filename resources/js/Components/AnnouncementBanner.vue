<template>
    <div v-for="a in visible" :key="a.id"
         class="announcement-banner flex items-center justify-between gap-3 px-4 py-2.5 text-sm font-medium"
         :class="`announcement-banner--${a.type}`">
        <div class="flex items-center gap-2 min-w-0">
            <SvgIcon :name="iconFor(a.type)" class="w-4 h-4 shrink-0" />
            <span class="truncate"><strong>{{ a.title }}</strong> — {{ a.body }}</span>
        </div>
        <button type="button" class="shrink-0 opacity-70 hover:opacity-100 text-lg leading-none px-1"
                aria-label="Dismiss" @click="dismiss(a.id)">&times;</button>
    </div>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SvgIcon from '@/Components/icons/SvgIcon.vue';

const STORAGE_KEY = 'dismissed-announcements';

function loadDismissed() {
    try {
        return new Set(JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'));
    } catch {
        return new Set();
    }
}

const page = usePage();
const dismissed = ref(loadDismissed());

const visible = computed(() => (page.props.announcements || []).filter(a => !dismissed.value.has(a.id)));

function dismiss(id) {
    dismissed.value.add(id);
    dismissed.value = new Set(dismissed.value);
    localStorage.setItem(STORAGE_KEY, JSON.stringify([...dismissed.value]));
}

function iconFor(type) {
    return { info: 'bell', warning: 'alert-circle', critical: 'alert-circle', maintenance: 'clock' }[type] || 'bell';
}
</script>

<style scoped>
.announcement-banner--info {
    background: #eff6ff;
    border-bottom: 2px solid #93c5fd;
    color: #1e3a5f;
}

.announcement-banner--warning,
.announcement-banner--maintenance {
    background: #fef9ec;
    border-bottom: 2px solid #d4a017;
    color: #041525;
}

.announcement-banner--critical {
    background: #fef2f2;
    border-bottom: 2px solid #dc2626;
    color: #7f1d1d;
}
</style>
