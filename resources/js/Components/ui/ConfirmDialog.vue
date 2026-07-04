<template>
    <Teleport to="body">
        <div v-if="state.open" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="cancel" />
            <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4" role="alertdialog" aria-modal="true">
                <h2 class="text-lg font-bold text-slate-900">{{ state.title }}</h2>
                <p class="text-sm text-slate-600">{{ state.message }}</p>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="btn-secondary text-sm" @click="cancel">{{ state.cancelLabel }}</button>
                    <button type="button"
                            class="text-sm font-semibold px-4 py-2 rounded-lg text-white"
                            :class="state.destructive ? 'bg-red-600 hover:bg-red-700' : 'bg-[#0f3d7a] hover:bg-[#0a2d5c]'"
                            @click="confirm">
                        {{ state.confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { reactive } from 'vue';

const state = reactive({
    open: false,
    title: 'Confirm',
    message: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    destructive: true,
    resolve: null,
});

function ask(options = {}) {
    return new Promise((resolve) => {
        Object.assign(state, {
            open: true,
            title: options.title ?? 'Confirm',
            message: options.message ?? 'Are you sure?',
            confirmLabel: options.confirmLabel ?? 'Confirm',
            cancelLabel: options.cancelLabel ?? 'Cancel',
            destructive: options.destructive ?? true,
            resolve,
        });
    });
}

function confirm() {
    state.open = false;
    state.resolve?.(true);
}

function cancel() {
    state.open = false;
    state.resolve?.(false);
}

defineExpose({ ask });
</script>
