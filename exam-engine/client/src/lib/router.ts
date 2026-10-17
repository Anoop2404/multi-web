/**
 * Tiny path router: `/` → Sahodaya code entry, `/{code}` → that Sahodaya's login.
 * A `?launch=<token>` on any path is a portal "Enter exam" launch (CONTRACT §5.3).
 */
export interface Route {
    code: string | null;
    launch: string | null;
}

export function normaliseCode(raw: string | null | undefined): string {
    return String(raw ?? '')
        .trim()
        .replace(/[^A-Za-z0-9_-]/g, '')
        .toUpperCase()
        .slice(0, 32);
}

export function parseRoute(pathname: string, search: string): Route {
    const first = pathname.split('/').filter(Boolean)[0] ?? '';
    let code: string | null = null;
    try {
        code = normaliseCode(decodeURIComponent(first)) || null;
    } catch {
        code = normaliseCode(first) || null;
    }
    if (code === 'INDEX.HTML') code = null;
    let launch: string | null = null;
    const m = /[?&]launch=([^&#]*)/.exec(search);
    if (m) {
        try {
            launch = decodeURIComponent(m[1].replace(/\+/g, ' ')) || null;
        } catch {
            launch = m[1] || null;
        }
    }
    return { code, launch };
}

export function currentRoute(): Route {
    return parseRoute(window.location.pathname, window.location.search);
}

/** Navigate without a reload (and drop any ?launch so a token is never reused or bookmarked). */
export function goTo(code: string | null, replace = false): void {
    const path = code ? `/${encodeURIComponent(code)}` : '/';
    if (replace) window.history.replaceState(null, '', path);
    else window.history.pushState(null, '', path);
}

export function stripLaunchParam(): void {
    const route = currentRoute();
    window.history.replaceState(null, '', route.code ? `/${encodeURIComponent(route.code)}` : '/');
}
