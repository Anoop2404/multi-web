import Swal from 'sweetalert2';

export function useSweetAlert() {
    function showAlert({ title, text, icon = 'info', confirmButtonText = 'OK' }) {
        return Swal.fire({
            title: title || (icon === 'error' ? 'Error' : icon === 'warning' ? 'Notice' : 'Information'),
            text,
            icon,
            confirmButtonText,
            confirmButtonColor: '#0f3d7a',
            customClass: {
                popup: 'rounded-2xl font-sans shadow-xl border border-slate-100',
                title: 'text-lg font-bold text-slate-900',
                htmlContainer: 'text-sm text-slate-600',
                confirmButton: 'px-5 py-2 rounded-xl text-sm font-semibold shadow-sm',
            },
        });
    }

    function showError(message, title = 'Registration Error') {
        return showAlert({ title, text: message, icon: 'error' });
    }

    function showWarning(message, title = 'Registration Notice') {
        return showAlert({ title, text: message, icon: 'warning' });
    }

    function showSuccess(message, title = 'Success') {
        return showAlert({ title, text: message, icon: 'success' });
    }

    function confirmAction({
        title = 'Are you sure?',
        text = '',
        icon = 'warning',
        confirmButtonText = 'Yes, proceed',
        cancelButtonText = 'Cancel',
    }) {
        return Swal.fire({
            title,
            text,
            icon,
            showCancelButton: true,
            confirmButtonText,
            cancelButtonText,
            confirmButtonColor: '#0f3d7a',
            cancelButtonColor: '#94a3b8',
            customClass: {
                popup: 'rounded-2xl font-sans shadow-xl border border-slate-100',
                title: 'text-lg font-bold text-slate-900',
                htmlContainer: 'text-sm text-slate-600',
                confirmButton: 'px-4 py-2 rounded-xl text-sm font-semibold shadow-sm',
                cancelButton: 'px-4 py-2 rounded-xl text-sm font-semibold shadow-sm',
            },
        }).then((result) => result.isConfirmed);
    }

    return {
        showAlert,
        showError,
        showWarning,
        showSuccess,
        confirmAction,
    };
}
