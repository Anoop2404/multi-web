<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/50" @click="onBackdropClick" />
            <div class="modal-shell relative" :class="sizeClass">
                <div v-if="title || subtitle || $slots.header" class="modal-head">
                    <div class="min-w-0">
                        <slot name="header">
                            <h2 v-if="title" class="text-lg font-bold text-slate-900 truncate">{{ title }}</h2>
                            <p v-if="subtitle" class="text-sm text-slate-500 mt-0.5">{{ subtitle }}</p>
                        </slot>
                    </div>
                    <button v-if="!persistent" type="button"
                            class="text-slate-400 hover:text-slate-600 transition shrink-0 ml-4"
                            aria-label="Close" @click="close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <slot />
                </div>
                <div v-if="$slots.footer" class="modal-foot">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm | md | lg | xl
    closeOnBackdrop: { type: Boolean, default: true },
    // Disables Esc/backdrop close — for flows where dismissing accidentally matters (e.g. impersonation confirm).
    persistent: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const sizeClass = computed(() => ({
    sm: 'w-full max-w-sm',
    md: 'w-full max-w-md',
    lg: 'w-full max-w-2xl',
    xl: 'w-full max-w-4xl',
}[props.size] ?? 'w-full max-w-md'));

function close() {
    if (props.persistent) return;
    emit('close');
}

function onBackdropClick() {
    if (!props.closeOnBackdrop || props.persistent) return;
    emit('close');
}

function onKeydown(event) {
    if (event.key === 'Escape' && props.show) {
        close();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>
