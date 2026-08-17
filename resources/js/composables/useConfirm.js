import { ref } from 'vue';

const dialogRef = ref(null);

/** @param {import('vue').Ref|null} ref */
export function registerConfirmDialog(ref) {
    dialogRef.value = ref;
}

function resolveDialog() {
    const target = dialogRef.value;
    if (! target) return null;
    return target.value ?? target;
}

export function useConfirm() {
    return {
        /** @param {{ title?: string, message?: string, confirmLabel?: string, cancelLabel?: string, destructive?: boolean }} options */
        confirm(options = {}) {
            const dialog = resolveDialog();
            if (dialog?.ask) {
                return dialog.ask(options);
            }

            return Promise.resolve(window.confirm(options.message ?? 'Are you sure?'));
        },

        /**
         * UI/UX audit 2026-08-14 Finding 19 fix (2026-08-15): replaces raw window.prompt().
         * Resolves to the entered string, or null if the user cancels. inputRequired
         * defaults to true (matching prompt()'s own default of returning null/empty rather
         * than letting the caller submit blank) — pass inputRequired:false to allow empty.
         * @param {{ title?: string, message?: string, inputLabel?: string, inputPlaceholder?: string, inputValue?: string, inputMultiline?: boolean, inputRequired?: boolean, confirmLabel?: string, cancelLabel?: string, destructive?: boolean }} options
         * @returns {Promise<string|null>}
         */
        prompt(options = {}) {
            const dialog = resolveDialog();
            if (dialog?.ask) {
                return dialog.ask({
                    destructive: false,
                    confirmLabel: 'OK',
                    ...options,
                    input: true,
                    inputRequired: options.inputRequired ?? true,
                });
            }

            return Promise.resolve(window.prompt(options.message ?? '', options.inputValue ?? ''));
        },
    };
}
