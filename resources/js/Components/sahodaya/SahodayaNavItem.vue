<template>
    <Link :href="href"
          :class="[
              'flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition w-full border-l-2',
              active
                  ? 'sa-nav-active border-[#fbbf24] bg-white/12 text-white font-semibold'
                  : 'border-transparent text-white/70 hover:bg-white/8 hover:text-white/95',
          ]">
        <SahodayaSvgIcon :name="icon" class="w-4 h-4 shrink-0" />
        <span class="flex-1 truncate">{{ label }}</span>
        <span v-if="badge > 0"
              class="bg-[#fbbf24] text-[#041525] text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">
            {{ badge > 99 ? '99+' : badge }}
        </span>
    </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaSvgIcon from './SahodayaSvgIcon.vue';

defineProps({
    href: { type: String, required: true },
    icon: { type: String, default: 'grid' },
    label: { type: String, required: true },
    active: { type: Boolean, default: false },
    badge: { type: Number, default: 0 },
});
</script>
