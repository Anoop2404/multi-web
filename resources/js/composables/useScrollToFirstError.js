import { nextTick } from 'vue';

/**
 * Scroll to the first visible validation error after an Inertia form submit fails.
 */
export function useScrollToFirstError() {
    function scrollToFirstError(formErrors = {}) {
        const keys = Object.keys(formErrors ?? {});
        if (!keys.length) {
            return;
        }

        nextTick(() => {
            const banner = document.querySelector('.validation-banner, .flash-banner--error');
            if (banner) {
                banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            for (const key of keys) {
                const el = document.querySelector(`[name="${key}"], #${CSS.escape(key)}`);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (typeof el.focus === 'function') {
                        el.focus({ preventScroll: true });
                    }
                    break;
                }
            }
        });
    }

    return { scrollToFirstError };
}
