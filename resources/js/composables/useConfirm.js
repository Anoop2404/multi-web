import { ref } from 'vue';
import Swal from 'sweetalert2';

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

            return Swal.fire({
                title: options.title ?? 'Are you sure?',
                text: options.message ?? '',
                icon: options.destructive ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonText: options.confirmLabel ?? 'Yes, proceed',
                cancelButtonText: options.cancelLabel ?? 'Cancel',
                confirmButtonColor: options.destructive ? '#dc2626' : '#0f3d7a',
                cancelButtonColor: '#94a3b8',
                customClass: {
                    popup: 'rounded-2xl font-sans shadow-xl border border-slate-100',
                    title: 'text-lg font-bold text-slate-900',
                    htmlContainer: 'text-sm text-slate-600',
                    confirmButton: 'px-4 py-2 rounded-xl text-sm font-semibold shadow-sm',
                    cancelButton: 'px-4 py-2 rounded-xl text-sm font-semibold shadow-sm',
                },
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

            return Swal.fire({
                title: options.title ?? 'Input Required',
                text: options.message ?? '',
                input: options.inputMultiline ? 'textarea' : 'text',
                inputValue: options.inputValue ?? '',
                inputPlaceholder: options.inputPlaceholder ?? '',
                inputAttributes: {
                    autocapitalize: 'off',
                },
                showCancelButton: true,
                confirmButtonText: options.confirmLabel ?? 'OK',
                cancelButtonText: options.cancelLabel ?? 'Cancel',
                confirmButtonColor: '#0f3d7a',
                cancelButtonColor: '#94a3b8',
                inputValidator: (value) => {
                    if ((options.inputRequired ?? true) && !value?.trim()) {
                        return options.inputLabel ? `${options.inputLabel} is required` : 'This field is required';
                    }
                    return null;
                },
                customClass: {
                    popup: 'rounded-2xl font-sans shadow-xl border border-slate-100',
                    title: 'text-lg font-bold text-slate-900',
                    htmlContainer: 'text-sm text-slate-600',
                    input: 'field text-sm rounded-xl',
                    confirmButton: 'px-4 py-2 rounded-xl text-sm font-semibold shadow-sm',
                    cancelButton: 'px-4 py-2 rounded-xl text-sm font-semibold shadow-sm',
                },
            }).then((res) => (res.isConfirmed ? res.value : null));
        },
    };
}
