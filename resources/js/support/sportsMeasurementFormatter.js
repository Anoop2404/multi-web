/**
 * Sports timing and distance measurement formatter and parser.
 */

export function parseTime(val) {
    if (!val || typeof val !== 'string' && typeof val !== 'number') {
        return null;
    }

    let str = String(val).trim();
    if (!str) return null;

    // Handle "1m 23.4s" or "1m 23s"
    const naturalMatch = str.match(/^(\d+)\s*m(?:in)?(?:\s*([0-5]?\d(?:[.,]\d{1,3})?)\s*s(?:ec)?)?$/i);
    if (naturalMatch) {
        const mins = parseFloat(naturalMatch[1]);
        const secs = naturalMatch[2] ? parseFloat(naturalMatch[2].replace(',', '.')) : 0.0;
        if (secs >= 60) return { valid: false, error: 'Seconds must be under 60' };
        const total = mins * 60 + secs;
        return { valid: true, seconds: total, formatted: formatSeconds(total) };
    }

    // Strip trailing 's'
    str = str.replace(/\s*s(ec)?$/i, '');

    // hh:mm:ss[.ff]
    const hmsMatch = str.match(/^(\d{1,2}):([0-5]?\d):([0-5]?\d(?:[.,]\d{1,3})?)$/);
    if (hmsMatch) {
        const hours = parseFloat(hmsMatch[1]);
        const mins = parseFloat(hmsMatch[2]);
        const secs = parseFloat(hmsMatch[3].replace(',', '.'));
        const total = hours * 3600 + mins * 60 + secs;
        return { valid: true, seconds: total, formatted: formatSeconds(total) };
    }

    // mm:ss[.ff] or m:ss[.ff]
    const msMatch = str.match(/^(\d{1,3}):([0-5]?\d(?:[.,]\d{1,3})?)$/);
    if (msMatch) {
        const mins = parseFloat(msMatch[1]);
        const secs = parseFloat(msMatch[2].replace(',', '.'));
        const total = mins * 60 + secs;
        return { valid: true, seconds: total, formatted: formatSeconds(total) };
    }

    // Pure decimal seconds
    if (/^\d+(?:[.,]\d{1,3})?$/.test(str)) {
        const total = parseFloat(str.replace(',', '.'));
        if (isNaN(total) || total <= 0) return null;
        return { valid: true, seconds: total, formatted: formatSeconds(total) };
    }

    // Invalid format
    return { valid: false, error: 'Use seconds (12.45) or m:ss (1:02.50)' };
}

export function formatSeconds(seconds) {
    if (seconds == null || isNaN(seconds) || seconds < 0) return '';

    if (seconds >= 3600) {
        const hours = Math.floor(seconds / 3600);
        const rem = seconds - hours * 3600;
        const mins = Math.floor(rem / 60);
        const secs = rem - mins * 60;
        return `${hours}:${String(mins).padStart(2, '0')}:${secs.toFixed(2).padStart(5, '0')}`;
    }

    if (seconds >= 60) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds - mins * 60;
        return `${mins}m ${secs.toFixed(2)}s (${seconds.toFixed(2)}s)`;
    }

    return `${seconds.toFixed(2)}s`;
}

export function parseDistance(val, unit = 'm') {
    if (!val || typeof val !== 'string' && typeof val !== 'number') {
        return null;
    }

    const str = String(val).trim();
    if (!str) return null;

    const m = str.match(/^(\d+(?:[.,]\d{1,3})?)\s*(cm|m|meter|meters)?$/i);
    if (m) {
        const num = parseFloat(m[1].replace(',', '.'));
        const detectedUnit = (m[2] || unit || 'm').toLowerCase();
        const meters = detectedUnit === 'cm' ? num / 100 : num;

        if (isNaN(meters) || meters <= 0) return null;

        return {
            valid: true,
            meters,
            formatted: `${meters.toFixed(2)} m`,
        };
    }

    return { valid: false, error: 'Use valid distance (e.g. 5.45)' };
}
