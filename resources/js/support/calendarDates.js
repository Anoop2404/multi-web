/**
 * Calendar-date helpers — avoid timezone shifts from `new Date('YYYY-MM-DD')`.
 */

/** @returns {{ y: number, m: number, d: number } | null} */
export function parseCalendarDate(value) {
    if (! value) return null;

    const str = String(value).trim();
    const match = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (! match) return null;

    const y = Number(match[1]);
    const m = Number(match[2]);
    const d = Number(match[3]);

    if (! y || m < 1 || m > 12 || d < 1 || d > 31) return null;

    return { y, m, d };
}

/** @returns {string} */
export function calendarDateInputValue(value) {
    const parts = parseCalendarDate(value);
    if (! parts) return '';

    return `${parts.y}-${String(parts.m).padStart(2, '0')}-${String(parts.d).padStart(2, '0')}`;
}

/** @returns {string} */
export function formatCalendarDate(value, locale = 'en-IN') {
    const parts = parseCalendarDate(value);
    if (! parts) return '—';

    const utc = Date.UTC(parts.y, parts.m - 1, parts.d);

    return new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(utc);
}

/** Whole years completed as of today (calendar basis). */
export function calendarAgeYears(value) {
    const parts = parseCalendarDate(value);
    if (! parts) return null;

    const today = new Date();
    let age = today.getFullYear() - parts.y;
    const monthDiff = today.getMonth() + 1 - parts.m;
    const dayDiff = today.getDate() - parts.d;

    if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
        age -= 1;
    }

    return age;
}

/** @returns {'future' | 'valid' | 'invalid'} */
export function calendarDateStatus(value) {
    const parts = parseCalendarDate(value);
    if (! parts) return 'invalid';

    const age = calendarAgeYears(value);
    if (age === null) return 'invalid';
    if (age < 0) return 'future';

    return 'valid';
}

/** @returns {string | null} */
export function formatAgeLabel(value) {
    const age = calendarAgeYears(value);
    if (age === null) return null;
    if (age < 0) return 'Date is in the future';
    if (age === 0) return 'Less than 1 year old';
    if (age === 1) return '1 year old';

    return `${age} years old`;
}

/** @returns {string} */
export function formatDobDetail(value) {
    const label = formatCalendarDate(value);
    const age = formatAgeLabel(value);

    if (label === '—') return '—';
    if (! age) return label;

    return `${label} · ${age}`;
}
