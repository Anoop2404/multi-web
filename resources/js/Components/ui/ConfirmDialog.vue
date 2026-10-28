<template>
    <Teleport to="body">
        <div v-if="state.open" class="fixed inset-0 z-[99999] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="cancel" />
            <div
                ref="panelRef"
                class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4"
                role="alertdialog"
                aria-modal="true"
                :aria-labelledby="titleId"
                :aria-describedby="messageId"
                tabindex="-1"
                @keydown="onKeydown"
            >
                <h2 :id="titleId" class="text-lg font-bold text-slate-900">{{ state.title }}</h2>
                <p :id="messageId" class="text-sm text-slate-600">{{ state.message }}</p>
                <div v-if="state.input" class="space-y-1">
                    <label v-if="state.inputLabel" class="text-xs font-semibold text-slate-500" :for="inputId">{{ state.inputLabel }}</label>
                    <textarea
                        v-if="state.inputMultiline"
                        :id="inputId"
                        ref="inputRef"
                        v-model="state.inputValue"
                        :placeholder="state.inputPlaceholder"
                        rows="3"
                        class="field w-full text-sm"
                    />
                    <input
                        v-else
                        :id="inputId"
                        ref="inputRef"
                        v-model="state.inputValue"
                        type="text"
                        :placeholder="state.inputPlaceholder"
                        class="field w-full text-sm"
                        @keydown.enter="confirm"
                    >
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button ref="cancelBtnRef" type="button" class="btn-secondary text-sm" @click="cancel">{{ state.cancelLabel }}</button>
                    <button
                        ref="confirmBtnRef"
                        type="button"
                        class="text-sm font-semibold px-4 py-2 rounded-lg text-white"
                        :class="state.destructive ? 'bg-red-600 hover:bg-red-700' : 'bg-[#0f3d7a] hover:bg-[#0a2d5c]'"
                        :disabled="state.input && state.inputRequired && !state.inputValue"
                        @click="confirm"
                    >
                        {{ state.confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { nextTick, reactive, ref, watch } from 'vue';

/**
 * Also backs useConfirm.js's prompt() (not just confirm()) — state.input turns on the
 * text-input mode below, resolving with the entered string (or null if cancelled)
 * instead of the plain true/false a bare confirm() resolves with.
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

const panelRef = ref(null);
const inputRef = ref(null);
const cancelBtnRef = ref(null);
const confirmBtnRef = ref(null);
const titleId = 'confirm-dialog-title';
const messageId = 'confirm-dialog-message';
const inputId = 'confirm-dialog-input';
let previouslyFocused = null;

function ask(options = {}) {
    previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
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

watch(() => state.open, async (open) => {
    if (! open) return;
    await nextTick();
    const focusTarget = state.input
        ? (inputRef.value?.value ?? inputRef.value)
        : (state.destructive ? cancelBtnRef.value : confirmBtnRef.value);
    const el = focusTarget?.$el ?? focusTarget;
    if (el instanceof HTMLElement) {
        el.focus();
    } else {
        panelRef.value?.focus();
    }
});

function focusables() {
    const root = panelRef.value;
    if (! root) return [];
    return [...root.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])')]
        .filter((el) => el instanceof HTMLElement && ! el.hasAttribute('disabled'));
}

function onKeydown(e) {
    if (e.key === 'Escape') {
        e.preventDefault();
        cancel();
        return;
    }
    if (e.key !== 'Tab') return;
    const nodes = focusables();
    if (! nodes.length) return;
    const first = nodes[0];
    const last = nodes[nodes.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (! e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}

function close(result) {
    state.open = false;
    state.resolve?.(result);
    nextTick(() => {
        previouslyFocused?.focus?.();
        previouslyFocused = null;
    });
}

function confirm() {
    if (state.input && state.inputRequired && ! state.inputValue) return;
    close(state.input ? state.inputValue : true);
}

function cancel() {
    close(state.input ? null : false);
}

defineExpose({ ask });
</script>
