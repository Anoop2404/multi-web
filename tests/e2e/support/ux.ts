import { expect, type Page, type TestInfo } from '@playwright/test';
import type { PageEntry } from './page-catalog';
import { recordPageResult } from './audit-report';
import type { PageAuditResult } from './audit-report';

export type UxIssue = {
    severity: 'error' | 'warning' | 'info';
    code: string;
    message: string;
    url: string;
};

const IGNORED_CONSOLE_PATTERNS = [
    /favicon/i,
    /vite.*hmr/i,
    /Download the React DevTools/i,
    /Failed to load resource.*\.map$/i,
    /net::ERR_BLOCKED_BY_CLIENT/i,
];

export type ConsoleCollector = {
    errors: string[];
    detach: () => void;
};

export function attachConsoleCollector(page: Page): ConsoleCollector {
    const errors: string[] = [];

    const onConsole = (msg: { type: () => string; text: () => string }) => {
        if (msg.type() !== 'error') {
            return;
        }
        const text = msg.text();
        if (IGNORED_CONSOLE_PATTERNS.some((p) => p.test(text))) {
            return;
        }
        errors.push(text);
    };

    const onPageError = (err: Error) => {
        errors.push(err.message);
    };

    page.on('console', onConsole);
    page.on('pageerror', onPageError);

    return {
        errors,
        detach: () => {
            page.off('console', onConsole);
            page.off('pageerror', onPageError);
        },
    };
}

/** @deprecated Use attachConsoleCollector */
export async function collectConsoleErrors(page: Page): Promise<string[]> {
    const collector = attachConsoleCollector(page);

    return collector.errors;
}

export async function auditPageUx(page: Page, label: string): Promise<UxIssue[]> {
    const issues: UxIssue[] = [];
    const url = page.url();

    const title = await page.title();
    if (!title || title.trim().length < 2) {
        issues.push({
            severity: 'warning',
            code: 'empty-title',
            message: `${label}: document title is empty`,
            url,
        });
    }

    if (url.includes('/login') && !label.toLowerCase().includes('login')) {
        issues.push({
            severity: 'error',
            code: 'redirected-to-login',
            message: `${label}: redirected to login (session or permission issue)`,
            url,
        });
    }

    const bodyText = await page.locator('body').innerText();
    if (bodyText.trim().length < 20) {
        issues.push({
            severity: 'error',
            code: 'empty-body',
            message: `${label}: page body appears empty`,
            url,
        });
    }

    const errorPatterns = [
        /Server Error/i,
        /500\s*\|/i,
        /Whoops/i,
        /SQLSTATE/i,
        /Undefined array key/i,
        /Class .* not found/i,
        /Internal Server Error/i,
        /This page isn.t working/i,
    ];
    for (const pattern of errorPatterns) {
        if (pattern.test(bodyText)) {
            issues.push({
                severity: 'error',
                code: 'server-error-text',
                message: `${label}: server error text visible on page`,
                url,
            });
            break;
        }
    }

    if (/403\s*\|/.test(bodyText) || /Forbidden/i.test(bodyText)) {
        issues.push({
            severity: 'warning',
            code: 'forbidden-text',
            message: `${label}: forbidden/403 text visible`,
            url,
        });
    }

    if (/COMING SOON/i.test(bodyText)) {
        issues.push({
            severity: 'warning',
            code: 'coming-soon',
            message: `${label}: placeholder "COMING SOON" visible`,
            url,
        });
    }

    const horizontalOverflow = await page.evaluate(() => {
        return document.documentElement.scrollWidth > document.documentElement.clientWidth + 2;
    });
    if (horizontalOverflow) {
        issues.push({
            severity: 'warning',
            code: 'horizontal-scroll',
            message: `${label}: horizontal overflow detected`,
            url,
        });
    }

    const nestedForms = await page.evaluate(() => {
        return document.querySelectorAll('form form').length;
    });
    if (nestedForms > 0) {
        issues.push({
            severity: 'warning',
            code: 'nested-forms',
            message: `${label}: ${nestedForms} nested form(s) detected (invalid HTML)`,
            url,
        });
    }

    const inputsWithoutLabel = await page.evaluate(() => {
        const inputs = Array.from(document.querySelectorAll('input:not([type="hidden"]), select, textarea'));
        return inputs.filter((el) => {
            const id = el.getAttribute('id');
            if (id && document.querySelector(`label[for="${id}"]`)) {
                return false;
            }
            const aria = el.getAttribute('aria-label') || el.getAttribute('aria-labelledby');
            return !aria;
        }).length;
    });
    if (inputsWithoutLabel > 3) {
        issues.push({
            severity: 'info',
            code: 'unlabeled-inputs',
            message: `${label}: ${inputsWithoutLabel} form fields lack labels`,
            url,
        });
    }

    const brokenImages = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('img')).filter((img) => {
            const src = img.getAttribute('src');
            return src && src.length > 1 && img.naturalWidth === 0;
        }).length;
    });
    if (brokenImages > 0) {
        issues.push({
            severity: 'warning',
            code: 'broken-images',
            message: `${label}: ${brokenImages} broken image(s)`,
            url,
        });
    }

    const inertiaRoot = await page.locator('#app, [data-page]').count();
    const isPublicBlade = /\/login$/.test(url) || (url.endsWith('/') && !url.includes('/admin'));
    if (inertiaRoot === 0 && !isPublicBlade && !url.includes('/login')) {
        issues.push({
            severity: 'warning',
            code: 'missing-inertia-root',
            message: `${label}: Inertia app root not found`,
            url,
        });
    }

    return issues;
}

export type VisitOptions = {
    gated?: boolean;
    area?: string;
    record?: boolean;
    absoluteUrl?: string;
};

export async function visitAndAudit(
    page: Page,
    path: string,
    label: string,
    consoleCollector?: ConsoleCollector,
    options: VisitOptions = {},
): Promise<UxIssue[]> {
    const started = Date.now();
    const target = options.absoluteUrl ?? path;
    const response = await page.goto(target, { waitUntil: 'domcontentloaded' });
    const status = response?.status() ?? 0;

    const issues: UxIssue[] = [];
    if (status >= 500) {
        issues.push({
            severity: 'error',
            code: 'http-5xx',
            message: `${label}: HTTP ${status}`,
            url: page.url(),
        });
        maybeRecord(options, path, label, page.url(), status, started, issues, []);

        return issues;
    }
    if (status === 403 && options.gated) {
        maybeRecord(options, path, label, page.url(), status, started, issues, []);

        return issues;
    }
    if (status === 404 && options.gated) {
        maybeRecord(options, path, label, page.url(), status, started, issues, []);

        return issues;
    }
    if (status >= 400 && status !== 403) {
        issues.push({
            severity: 'error',
            code: 'http-4xx',
            message: `${label}: HTTP ${status}`,
            url: page.url(),
        });
    }

    await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});

    issues.push(...(await auditPageUx(page, label)));

    const consoleSnapshot = consoleCollector ? [...consoleCollector.errors] : [];
    if (consoleSnapshot.length > 0 && !(options.gated && (status === 403 || status === 404))) {
        for (const err of consoleSnapshot) {
            if (/404 \(Not Found\)/.test(err) && options.gated) {
                continue;
            }
            issues.push({
                severity: 'error',
                code: 'console-error',
                message: `${label}: ${err.slice(0, 200)}`,
                url: page.url(),
            });
        }
    }

    maybeRecord(options, path, label, page.url(), status, started, issues, consoleSnapshot);

    return issues;
}

function maybeRecord(
    options: VisitOptions,
    path: string,
    label: string,
    url: string,
    httpStatus: number,
    started: number,
    issues: UxIssue[],
    consoleErrors: string[] = [],
): void {
    if (options.record === false || !options.area) {
        return;
    }

    recordPageResult({
        area: options.area,
        path,
        label,
        url,
        httpStatus,
        durationMs: Date.now() - started,
        issues,
        consoleErrors: consoleErrors,
    });
}

export async function auditPages(
    page: Page,
    pages: PageEntry[],
    consoleCollector?: ConsoleCollector,
    options: Omit<VisitOptions, 'gated'> = {},
): Promise<UxIssue[]> {
    const allIssues: UxIssue[] = [];
    for (const entry of pages) {
        allIssues.push(
            ...(await visitAndAudit(page, entry.path, entry.label, consoleCollector, {
                ...options,
                gated: entry.gated,
            })),
        );
    }

    return allIssues;
}

export function assertNoCriticalIssues(issues: UxIssue[], testInfo: TestInfo): void {
    const critical = issues.filter((i) => i.severity === 'error');
    if (critical.length > 0) {
        const summary = critical.map((i) => `[${i.code}] ${i.message} (${i.url})`).join('\n');
        throw new Error(`UX audit failures:\n${summary}`);
    }

    const warnings = issues.filter((i) => i.severity === 'warning');
    if (warnings.length > 0) {
        testInfo.annotations.push({
            type: 'ux-warnings',
            description: warnings.map((w) => w.message).join(' | '),
        });
    }
}

export async function expectHealthyPage(page: Page): Promise<void> {
    await expect(page.locator('body')).toBeVisible();
    const text = await page.locator('body').innerText();
    expect(text).not.toMatch(/Server Error|SQLSTATE|Class .* not found/);
}

export type { PageAuditResult };
