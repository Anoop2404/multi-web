/**
 * Lightweight device check for the student portal take-exam path.
 * Mirrors exam-engine systemCheck.ts without engine /api/state latency.
 */

function browserSupported() {
    try {
        return (
            typeof fetch === 'function'
            && typeof Promise !== 'undefined'
            && typeof TextEncoder !== 'undefined'
            && typeof AbortController !== 'undefined'
            && typeof localStorage !== 'undefined'
        );
    } catch {
        return false;
    }
}

async function cryptoSelfTest() {
    try {
        if (! window.isSecureContext || ! crypto?.subtle) return false;
        const raw = crypto.getRandomValues(new Uint8Array(32));
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const key = await crypto.subtle.importKey('raw', raw, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
        const sealed = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, new TextEncoder().encode('ok'));
        const opened = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, sealed);
        return new TextDecoder().decode(opened) === 'ok';
    } catch {
        return false;
    }
}

function storageSelfTest() {
    try {
        const key = 'mcq:syscheck';
        localStorage.setItem(key, '1');
        const ok = localStorage.getItem(key) === '1';
        localStorage.removeItem(key);
        return ok;
    } catch {
        return false;
    }
}

/**
 * @returns {Promise<{ items: Array<{id:string,label:string,status:'ok'|'warn'|'fail',detail:string}>, ok: boolean }>}
 */
export async function runPortalSystemCheck() {
    const items = [];

    const browserOk = browserSupported();
    items.push({
        id: 'browser',
        label: 'Browser',
        status: browserOk ? 'ok' : 'fail',
        detail: browserOk ? 'Supported' : 'Use a current Chrome, Edge, Safari, or Firefox.',
    });

    const cryptoOk = await cryptoSelfTest();
    items.push({
        id: 'crypto',
        label: 'Secure context',
        status: cryptoOk ? 'ok' : (window.isSecureContext ? 'warn' : 'fail'),
        detail: cryptoOk
            ? 'Crypto APIs OK'
            : (window.isSecureContext ? 'Limited crypto — prefer HTTPS + modern browser' : 'Open this site over HTTPS'),
    });

    const storageOk = storageSelfTest();
    items.push({
        id: 'storage',
        label: 'Local storage',
        status: storageOk ? 'ok' : 'fail',
        detail: storageOk ? 'Can save draft answers' : 'Storage blocked — drafts may not persist',
    });

    const w = window.screen?.width || 0;
    const h = window.screen?.height || 0;
    const screenOk = w >= 320 && h >= 480;
    items.push({
        id: 'screen',
        label: 'Screen',
        status: screenOk ? 'ok' : 'warn',
        detail: `${w}×${h}${screenOk ? '' : ' — small screen; zoom carefully'}`,
    });

    const online = typeof navigator.onLine === 'boolean' ? navigator.onLine : true;
    items.push({
        id: 'online',
        label: 'Network',
        status: online ? 'ok' : 'fail',
        detail: online ? 'Online' : 'Offline — reconnect before starting',
    });

    const fs = typeof document.documentElement.requestFullscreen === 'function';
    items.push({
        id: 'fullscreen',
        label: 'Fullscreen API',
        status: fs ? 'ok' : 'warn',
        detail: fs ? 'Available if the exam requires it' : 'Fullscreen not available in this browser',
    });

    items.push({
        id: 'tips',
        label: 'Exam tips',
        status: 'ok',
        detail: 'Stable power · Chrome/Edge preferred · one device · avoid extra tabs',
    });

    const ok = items.every((i) => i.status !== 'fail');
    return { items, ok };
}
