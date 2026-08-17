<template>
    <Teleport to="body">
        <div v-if="state.open" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="cancel" />
            <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4" role="alertdialog" aria-modal="true">
                <h2 class="text-lg font-bold text-slate-900">{{ state.title }}</h2>
                <p class="text-sm text-slate-600">{{ state.message }}</p>
                <div v-if="state.input" class="space-y-1">
                    <label v-if="state.inputLabel" class="text-xs font-semibold text-slate-500">{{ state.inputLabel }}</label>
                    <textarea v-if="state.inputMultiline" v-model="state.inputValue" :placeholder="state.inputPlaceholder" rows="3" class="field w-full text-sm" />
                    <input v-else v-model="state.inputValue" type="text" :placeholder="state.inputPlaceholder" class="field w-full text-sm" @keydown.enter="confirm">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="btn-secondary text-sm" @click="cancel">{{ state.cancelLabel }}</button>
                    <button type="button"
                            class="text-sm font-semibold px-4 py-2 rounded-lg text-white"
                            :class="state.destructive ? 'bg-red-600 hover:bg-red-700' : 'bg-[#0f3d7a] hover:bg-[#0a2d5c]'"
                            :disabled="state.input && state.inputRequired && !state.inputValue"
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

/**
 * UI/UX audit 2026-08-14 Finding 19 fix: this dialog originally only supported a plain
 * confirm/cancel choice. Extended 2026-08-15 with an optional text-input mode (state.input)
 * so it can also replace raw window.prompt() calls, not just window.confirm() — see
 * useConfirm.js's new prompt() export. Resolves with the entered string (or null if
 * cancelled) when in input mode, otherwise the existing boolean behavior is unchanged.
 */
const state = reactive({
    open: false,
    title: 'Confirm',
    message: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    destructive: true,
    resolve: null,
    input: false,
    inputValue: '',
    inputLabel: '',
    inputPlaceholder: '',
    inputMultiline: false,
    inputRequired: false,
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
            input: options.input ?? false,
            inputValue: options.inputValue ?? '',
            inputLabel: options.inputLabel ?? '',
            inputPlaceholder: options.inputPlaceholder ?? '',
            inputMultiline: options.inputMultiline ?? false,
            inputRequired: options.inputRequired ?? false,
        });
    });
}

function confirm() {
    if (state.input && state.inputRequired && !state.inputValue) return;
    state.open = false;
    state.resolve?.(state.input ? state.inputValue : true);
}

function cancel() {
    state.open = false;
    state.resolve?.(state.input ? null : false);
}

defineExpose({ ask });
</script>
