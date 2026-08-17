import { ref } from 'vue';
import { useSweetAlert } from '@/composables/useSweetAlert.js';

const dialogRef = ref(null);
const { showAlert } = useSweetAlert();

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

            return showAlert({
                title: options.title ?? 'Are you sure?',
                text: options.message ?? '',
                icon: options.destructive ? 'warning' : 'info',
                showCancelButton: true,
                confirmButtonText: options.confirmLabel ?? 'Yes, proceed',
                cancelButtonText: options.cancelLabel ?? 'Cancel',
                confirmButtonColor: options.destructive ? '#dc2626' : '#0f3d7a',
            }).then((res) => res.isConfirmed);
        },

        /**
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

            return showAlert({
                title: options.title ?? 'Input Required',
                text: options.message ?? '',
                input: options.inputMultiline ? 'textarea' : 'text',
                inputValue: options.inputValue ?? '',
                inputPlaceholder: options.inputPlaceholder ?? '',
                showCancelButton: true,
                confirmButtonText: options.confirmLabel ?? 'OK',
                cancelButtonText: options.cancelLabel ?? 'Cancel',
                confirmButtonColor: '#0f3d7a',
                inputValidator: (value) => {
                    if ((options.inputRequired ?? true) && !value?.trim()) {
                        return options.inputLabel ? `${options.inputLabel} is required` : 'This field is required';
                    }
                    return null;
                },
            }).then((res) => (res.isConfirmed ? res.value : null));
        },
    };
}
