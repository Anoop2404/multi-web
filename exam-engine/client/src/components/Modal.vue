<template>
    <div class="overlay" @click.self="dismissible && emit('close')" @keydown.esc="dismissible && emit('close')">
        <div ref="box" class="dialog" role="dialog" aria-modal="true" :aria-labelledby="titleId" tabindex="-1" @keydown.tab="trap">
            <h2 :id="titleId">{{ title }}</h2>
            <slot />
            <div v-if="$slots.actions" class="actions">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

withDefaults(defineProps<{ title: string; dismissible?: boolean }>(), { dismissible: true });
const emit = defineEmits<{ close: [] }>();

const box = ref<HTMLElement | null>(null);
const titleId = `dlg-${Math.random().toString(36).slice(2, 8)}`;
let previous: HTMLElement | null = null;

const focusables = () =>
    Array.from(box.value?.querySelectorAll<HTMLElement>('button:not([disabled]), [href], input:not([disabled]), [tabindex]:not([tabindex="-1"])') ?? []);

function trap(e: KeyboardEvent) {
    const list = focusables();
    if (!list.length) return;
    const first = list[0];
    const last = list[list.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}

onMounted(async () => {
    previous = document.activeElement as HTMLElement | null;
    await nextTick();
    (focusables()[0] ?? box.value)?.focus();
});

onBeforeUnmount(() => {
    previous?.focus?.();
});
</script>
