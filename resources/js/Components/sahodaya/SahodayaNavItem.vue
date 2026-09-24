<template>
    <!--
        A raw export/download URL (PDF, CSV) returns a plain, non-Inertia response.
        Inertia's <Link> always intercepts the click and does an XHR visit regardless of
        a `target` attribute (Inertia only reads `target` when rendering the DOM, not
        when deciding whether to intercept) — the XHR then gets a response with no
        X-Inertia header and Inertia renders it inside its own error dialog instead of
        letting the browser open/download it. So a `target` link renders as a bare <a>
        here, same as every other report-download link in this app (e.g.
        ReportExportCard.vue), which lets the browser handle it natively.
    -->
    <a v-if="target" :href="href" :target="target" rel="noopener"
       :class="navClasses">
        <SahodayaSvgIcon :name="icon" class="w-4 h-4 shrink-0" />
        <span class="flex-1 truncate">{{ label }}</span>
        <span v-if="badge > 0"
              class="bg-[#fbbf24] text-[#041525] text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">
            {{ badge > 99 ? '99+' : badge }}
        </span>
    </a>
    <Link v-else :href="href" :class="navClasses">
        <SahodayaSvgIcon :name="icon" class="w-4 h-4 shrink-0" />
        <span class="flex-1 truncate">{{ label }}</span>
        <span v-if="badge > 0"
              class="bg-[#fbbf24] text-[#041525] text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">
            {{ badge > 99 ? '99+' : badge }}
        </span>
    </Link>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaSvgIcon from '@/Components/icons/SvgIcon.vue';

const props = defineProps({
    href: { type: String, required: true },
    icon: { type: String, default: 'grid' },
    label: { type: String, required: true },
    active: { type: Boolean, default: false },
    badge: { type: Number, default: 0 },
    // Set for a raw export/download URL (PDF, CSV) — see the template comment above.
    target: { type: String, default: null },
});

const navClasses = computed(() => [
    'flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition w-full border-l-2',
    props.active
        ? 'sa-nav-active border-[#fbbf24] bg-white/12 text-white font-semibold'
        : 'border-transparent text-white/70 hover:bg-white/8 hover:text-white/95',
]);
</script>
