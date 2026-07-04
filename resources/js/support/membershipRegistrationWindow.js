/** Resolve registration window start/end for display (V2 columns with V1 fallback). */
export function windowDisplayStart(window) {
    return window?.display_starts_at
        ?? window?.add_open
        ?? window?.registration_starts_at
        ?? null;
}

export function windowDisplayEnd(window) {
    return window?.display_ends_at
        ?? window?.add_close
        ?? window?.registration_ends_at
        ?? null;
}

export function windowClosingDays(window) {
    const end = windowDisplayEnd(window);
    if (!end) return null;
    const ms = new Date(end).getTime() - Date.now();
    if (ms < 0) return null;
    return Math.ceil(ms / (1000 * 60 * 60 * 24));
}

export function windowClosingSoon(window, { maxDays = 3 } = {}) {
    const days = windowClosingDays(window);
    return days != null && days <= maxDays && days >= 0;
}
