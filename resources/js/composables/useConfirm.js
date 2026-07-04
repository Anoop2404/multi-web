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
    };
}
