<template>
    <PortalLayout
        role-label="Student Portal"
        title="Device check"
        :subtitle="`${school.name} · practice before exam day`"
        accent="navy"
        :nav-items="navItems"
    >
        <div class="card max-w-xl mx-auto space-y-4">
            <div>
                <h2 class="font-semibold text-base text-slate-900">Practice system check</h2>
                <p class="text-sm text-slate-600 mt-1">
                    Run this on the device you will use for the exam. It does not start an exam or detect extra monitors.
                </p>
            </div>

            <button type="button" class="btn-primary text-sm" :disabled="running" @click="run">
                {{ running ? 'Checking…' : (result ? 'Run again' : 'Run check') }}
            </button>

            <ul v-if="result" class="space-y-2 text-sm" role="status">
                <li
                    v-for="item in result.items"
                    :key="item.id"
                    class="flex gap-3 items-start rounded-lg border px-3 py-2"
                    :class="rowClass(item.status)"
                >
                    <span class="font-semibold shrink-0 w-24">{{ item.label }}</span>
                    <span>{{ item.detail }}</span>
                </li>
            </ul>

            <p v-if="result" class="text-sm font-medium" :class="result.ok ? 'text-emerald-700' : 'text-amber-800'">
                {{ result.ok ? 'Ready enough to take a portal exam on this device.' : 'Fix the failed items before exam day if you can.' }}
            </p>

            <div class="flex flex-wrap gap-2 pt-2">
                <a :href="hubUrl" class="btn-secondary text-sm">← Online exams</a>
            </div>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';
import { runPortalSystemCheck } from '@/support/portalSystemCheck.js';
import { computed, ref } from 'vue';

const props = defineProps({
    school: Object,
    student: Object,
    hubUrl: String,
});

const navItems = computed(() => studentPortalNavItems(props.school.id));
const running = ref(false);
const result = ref(null);

function rowClass(status) {
    if (status === 'ok') return 'border-emerald-100 bg-emerald-50/50 text-emerald-950';
    if (status === 'warn') return 'border-amber-100 bg-amber-50/60 text-amber-950';
    return 'border-red-100 bg-red-50 text-red-950';
}

async function run() {
    running.value = true;
    try {
        result.value = await runPortalSystemCheck();
    } finally {
        running.value = false;
    }
}
</script>
