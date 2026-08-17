// Zero-dependency SweetAlert modal engine — works out-of-the-box in all environments
export function useSweetAlert() {
    function createBackdrop() {
        let el = document.getElementById('custom-swal-container');
        if (!el) {
            el = document.createElement('div');
            el.id = 'custom-swal-container';
            el.className = 'fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-[#041525]/60 backdrop-blur-sm transition-opacity duration-200';
            document.body.appendChild(el);
        }
        return el;
    }

    function removeBackdrop() {
        const el = document.getElementById('custom-swal-container');
        if (el) {
            el.remove();
        }
    }

    function showAlert({
        title = '',
        text = '',
        icon = 'info',
        confirmButtonText = 'OK',
        cancelButtonText = null,
        showCancelButton = false,
        input = null,
        inputValue = '',
        inputPlaceholder = '',
        inputValidator = null,
        confirmButtonColor = '#0f3d7a',
    }) {
        return new Promise((resolve) => {
            const container = createBackdrop();
            container.innerHTML = '';

            const card = document.createElement('div');
            card.className = 'bg-white rounded-2xl shadow-2xl border border-slate-100 max-w-md w-full p-6 text-center transform scale-100 transition-transform duration-200 font-sans';

            // Icon SVG / Emoji
            const iconContainer = document.createElement('div');
            iconContainer.className = 'mx-auto mb-4 flex items-center justify-center w-14 h-14 rounded-full text-2xl';

            if (icon === 'error') {
                iconContainer.className += ' bg-rose-100 text-rose-600';
                iconContainer.innerHTML = '✕';
            } else if (icon === 'warning') {
                iconContainer.className += ' bg-amber-100 text-amber-600';
                iconContainer.innerHTML = '⚠️';
            } else if (icon === 'success') {
                iconContainer.className += ' bg-emerald-100 text-emerald-600';
                iconContainer.innerHTML = '✓';
            } else {
                iconContainer.className += ' bg-indigo-100 text-indigo-600';
                iconContainer.innerHTML = 'ℹ';
            }

            const titleEl = document.createElement('h3');
            titleEl.className = 'text-lg font-bold text-slate-900 mb-2 leading-snug';
            titleEl.textContent = title || (icon === 'error' ? 'Error' : icon === 'warning' ? 'Notice' : 'Information');

            const textEl = document.createElement('p');
            textEl.className = 'text-sm text-slate-600 mb-5 leading-relaxed';
            textEl.textContent = text;

            let inputEl = null;
            let errorEl = null;

            if (input === 'text' || input === 'textarea') {
                errorEl = document.createElement('p');
                errorEl.className = 'text-xs text-rose-600 mb-2 hidden text-left font-medium';

                if (input === 'textarea') {
                    inputEl = document.createElement('textarea');
                    inputEl.className = 'w-full px-3 py-2 border border-slate-300 rounded-xl text-sm mb-4 focus:ring-2 focus:ring-indigo-500 focus:outline-none';
                    inputEl.rows = 3;
                } else {
                    inputEl = document.createElement('input');
                    inputEl.type = 'text';
                    inputEl.className = 'w-full px-3 py-2 border border-slate-300 rounded-xl text-sm mb-4 focus:ring-2 focus:ring-indigo-500 focus:outline-none';
                }
                inputEl.value = inputValue || '';
                inputEl.placeholder = inputPlaceholder || '';
            }

            const buttonRow = document.createElement('div');
            buttonRow.className = 'flex items-center justify-center gap-3 pt-2';

            if (showCancelButton || cancelButtonText) {
                const cancelBtn = document.createElement('button');
                cancelBtn.type = 'button';
                cancelBtn.className = 'px-4 py-2 rounded-xl text-sm font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors';
                cancelBtn.textContent = cancelButtonText || 'Cancel';
                cancelBtn.onclick = () => {
                    removeBackdrop();
                    resolve({ isConfirmed: false, value: null });
                };
                buttonRow.appendChild(cancelBtn);
            }

            const confirmBtn = document.createElement('button');
            confirmBtn.type = 'button';
            confirmBtn.className = 'px-5 py-2 rounded-xl text-sm font-semibold text-white transition-colors shadow-sm';
            confirmBtn.style.backgroundColor = confirmButtonColor;
            confirmBtn.textContent = confirmButtonText || 'OK';

            confirmBtn.onclick = () => {
                if (inputEl && inputValidator) {
                    const validationErr = inputValidator(inputEl.value);
                    if (validationErr) {
                        errorEl.textContent = validationErr;
                        errorEl.classList.remove('hidden');
                        inputEl.focus();
                        return;
                    }
                }
                removeBackdrop();
                resolve({ isConfirmed: true, value: inputEl ? inputEl.value : true });
            };

            buttonRow.appendChild(confirmBtn);

            card.appendChild(iconContainer);
            card.appendChild(titleEl);
            card.appendChild(textEl);
            if (errorEl) card.appendChild(errorEl);
            if (inputEl) card.appendChild(inputEl);
            card.appendChild(buttonRow);

            container.appendChild(card);

            if (inputEl) {
                setTimeout(() => inputEl.focus(), 50);
            }
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
        destructive = false,
    }) {
        return showAlert({
            title,
            text,
            icon: destructive ? 'warning' : icon,
            showCancelButton: true,
            confirmButtonText,
            cancelButtonText,
            confirmButtonColor: destructive ? '#dc2626' : '#0f3d7a',
        }).then((res) => res.isConfirmed);
    }

    return {
        showAlert,
        showError,
        showWarning,
        showSuccess,
        confirmAction,
    };
}
