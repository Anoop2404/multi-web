/** Retrofit aria-label from placeholder for fields missing explicit labels. */
export function enhanceFormAccessibility(root = document.body) {
    if (!root?.querySelectorAll) {
        return;
    }

    root.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((el) => {
        if (el.getAttribute('aria-label') || el.getAttribute('aria-labelledby')) {
            return;
        }

        const id = el.getAttribute('id');
        if (id && root.querySelector(`label[for="${CSS.escape(id)}"]`)) {
            return;
        }

        const label = el.getAttribute('placeholder') || el.getAttribute('name');
        if (label) {
            el.setAttribute('aria-label', label.replace(/_/g, ' '));
        }
    });
}
