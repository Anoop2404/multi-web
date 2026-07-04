<template>
    <div class="login-role-guide mt-6 pt-5 border-t border-white/10">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 mb-2">Who signs in where?</p>
        <ul class="space-y-2 text-xs text-white/80">
            <li v-for="row in rows" :key="row.label">
                <span class="font-semibold text-white">{{ row.label }}</span>
                <span class="text-white/60"> — {{ row.detail }}</span>
            </li>
        </ul>
    </div>
</template>

<script setup>
defineProps({
    rows: {
        type: Array,
        default: () => [
            { label: 'Sahodaya admin', detail: 'Cluster staff, event coordinators, finance' },
            { label: 'School admin', detail: 'Principal, vice principal, school office' },
            { label: 'Portal login', detail: 'Students, teachers, judges, exam staff' },
        ],
    },
});
</script>
