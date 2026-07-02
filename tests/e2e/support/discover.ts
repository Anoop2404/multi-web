import type { Page } from '@playwright/test';
import type { PageEntry } from './page-catalog';
import { attachConsoleCollector, auditPages, visitAndAudit, type UxIssue } from './ux';

async function firstMatch(page: Page, selector: string, pattern: RegExp): Promise<string | null> {
    const links = page.locator(selector);
    const count = await links.count();
    for (let i = 0; i < count; i++) {
        const href = await links.nth(i).getAttribute('href');
        const match = href?.match(pattern);
        if (match?.[1]) {
            return match[1];
        }
    }

    return null;
}

export async function discoverFestEventId(page: Page, tenantId: string): Promise<string | null> {
    await page.goto(`/sahodaya-admin/${tenantId}/programs/kalotsav`);
    await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});

    return firstMatch(page, 'a[href*="/events/"]', /\/events\/(\d+)/);
}

export async function discoverMcqExamId(page: Page, tenantId: string): Promise<string | null> {
    await page.goto(`/sahodaya-admin/${tenantId}/mcq-exams`);
    await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});

    return firstMatch(page, 'a[href*="/mcq-exams/"]', /\/mcq-exams\/(\d+)/);
}

export async function discoverTrainingId(page: Page, tenantId: string): Promise<string | null> {
    await page.goto(`/sahodaya-admin/${tenantId}/training`);
    await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});

    return firstMatch(page, 'a[href*="/training/"]', /\/training\/(\d+)/);
}

export async function discoverMemberSchoolId(page: Page, tenantId: string): Promise<string | null> {
    await page.goto(`/sahodaya-admin/${tenantId}/schools`);
    await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});

    return firstMatch(page, 'a[href*="/schools/"]', /\/schools\/([0-9a-f-]{36})/);
}

export async function discoverFestProgramId(page: Page, tenantId: string): Promise<string | null> {
    await page.goto(`/school-admin/${tenantId}/fest-programs`);
    await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});

    return firstMatch(page, 'a[href*="/fest-programs/"]', /\/fest-programs\/(\d+)/);
}

export async function discoverMcqExamIdForPortal(page: Page, tenantId: string): Promise<string | null> {
    await page.goto(`/portal/exam/${tenantId}`);
    await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});

    return firstMatch(page, 'a[href*="/exams/"]', /\/exams\/(\d+)/);
}

export async function discoverPortalEventId(
    page: Page,
    portalBase: string,
    linkPattern: RegExp,
): Promise<string | null> {
    await page.goto(portalBase);
    await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});

    return firstMatch(page, 'a[href*="/events/"]', linkPattern);
}

export async function discoverSchoolFestEventId(page: Page, tenantId: string): Promise<string | null> {
    await page.goto(`/school-admin/${tenantId}/programs/kalotsav/registration`);
    await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});

    return firstMatch(page, 'a[href*="/reports/"], a[href*="/fest/"]', /\/(?:reports|fest)\/(\d+)/);
}

export type AuditSectionOptions = {
    area: string;
    pages: PageEntry[];
    absoluteBase?: string;
};

export async function auditSection(page: Page, options: AuditSectionOptions): Promise<UxIssue[]> {
    const collector = attachConsoleCollector(page);
    const allIssues: UxIssue[] = [];

    for (const entry of options.pages) {
        const absoluteUrl = options.absoluteBase ? `${options.absoluteBase}${entry.path}` : undefined;
        allIssues.push(
            ...(await visitAndAudit(page, entry.path, entry.label, collector, {
                area: options.area,
                gated: entry.gated,
                record: true,
                absoluteUrl,
            })),
        );
        collector.errors.length = 0;
    }

    collector.detach();

    return allIssues;
}

export async function auditSectionSimple(page: Page, area: string, pages: PageEntry[]): Promise<UxIssue[]> {
    const collector = attachConsoleCollector(page);
    const issues = await auditPages(page, pages, collector, { area, record: true });
    collector.detach();

    return issues;
}
