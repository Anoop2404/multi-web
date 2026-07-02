/**
 * Full automated UX audit — visits every catalogued page, records HTTP status,
 * visible errors, console errors, and common UX issues.
 *
 * Run:  npm run test:e2e:audit
 *        php artisan e2e:ux-audit
 *
 * Reports: storage/app/ux-audit/ux-audit.json
 *           storage/app/ux-audit/ux-audit.md
 */
import { test } from '@playwright/test';
import {
    login,
    loginAndGetTenantId,
    loginPortal,
    type RoleKey,
} from './support/auth';
import { resetAuditReport, writeFullReport, getCriticalIssues } from './support/audit-report';
import {
    auditSection,
    auditSectionSimple,
    discoverFestEventId,
    discoverFestProgramId,
    discoverMcqExamId,
    discoverMcqExamIdForPortal,
    discoverMemberSchoolId,
    discoverPortalEventId,
    discoverSchoolFestEventId,
    discoverTrainingId,
} from './support/discover';
import {
    portalExamPages,
    portalFestCoordinatorEventPages,
    portalFestOpsEventPages,
    portalJudgeEventPages,
    portalPages,
    publicPages,
    sahodayaEventPages,
    sahodayaMcqPages,
    sahodayaSchoolPages,
    sahodayaStaticPages,
    sahodayaTrainingPages,
    schoolFestEventPages,
    schoolFestProgramPages,
    schoolStaticPages,
    stateAdminPages,
    superadminPages,
} from './support/page-catalog';
import { type UxIssue } from './support/ux';

/** Record issues for the final report; individual sections do not abort the full run. */
function recordSectionIssues(issues: UxIssue[], testInfo: import('@playwright/test').TestInfo): void {
    const critical = issues.filter((i) => i.severity === 'error');
    const warnings = issues.filter((i) => i.severity === 'warning');
    if (critical.length > 0) {
        testInfo.annotations.push({
            type: 'ux-errors',
            description: critical.map((i) => `[${i.code}] ${i.message}`).join(' | '),
        });
    }
    if (warnings.length > 0) {
        testInfo.annotations.push({
            type: 'ux-warnings',
            description: warnings.map((w) => w.message).join(' | '),
        });
    }
}

const SUPERADMIN_BASE = process.env.E2E_SUPERADMIN_URL ?? 'http://superadmin.test:8000';

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
    resetAuditReport();
});

test.afterAll(() => {
    writeFullReport();
});

test.describe('Full UX audit', () => {
    test('public pages', async ({ page }, testInfo) => {
        const issues = await auditSectionSimple(page, 'Public', publicPages);
        recordSectionIssues(issues, testInfo);
    });

    test('sahodaya admin — static pages', async ({ page }, testInfo) => {
        const tenantId = await loginAndGetTenantId(page, 'sahodaya');
        const issues = await auditSectionSimple(page, 'Sahodaya admin', sahodayaStaticPages(tenantId));
        recordSectionIssues(issues, testInfo);
    });

    test('sahodaya admin — fest event pages', async ({ page }, testInfo) => {
        const tenantId = await loginAndGetTenantId(page, 'sahodaya');
        const eventId = await discoverFestEventId(page, tenantId);
        test.skip(!eventId, 'No fest events — run: php artisan e2e:seed-data');

        const issues = await auditSectionSimple(
            page,
            'Sahodaya fest',
            sahodayaEventPages(tenantId, eventId!),
        );
        recordSectionIssues(issues, testInfo);
    });

    test('sahodaya admin — MCQ pages', async ({ page }, testInfo) => {
        const tenantId = await loginAndGetTenantId(page, 'sahodaya');
        const examId = await discoverMcqExamId(page, tenantId);
        test.skip(!examId, 'No MCQ exams — run: php artisan e2e:seed-data');

        const issues = await auditSectionSimple(
            page,
            'Sahodaya MCQ',
            sahodayaMcqPages(tenantId, examId!),
        );
        recordSectionIssues(issues, testInfo);
    });

    test('sahodaya admin — training pages', async ({ page }, testInfo) => {
        const tenantId = await loginAndGetTenantId(page, 'sahodaya');
        const programId = await discoverTrainingId(page, tenantId);
        test.skip(!programId, 'No training programs — run: php artisan e2e:seed-data');

        const issues = await auditSectionSimple(
            page,
            'Sahodaya training',
            sahodayaTrainingPages(tenantId, programId!),
        );
        recordSectionIssues(issues, testInfo);
    });

    test('sahodaya admin — member school detail', async ({ page }, testInfo) => {
        const tenantId = await loginAndGetTenantId(page, 'sahodaya');
        const schoolId = await discoverMemberSchoolId(page, tenantId);
        test.skip(!schoolId, 'No member schools in demo data');

        const issues = await auditSectionSimple(
            page,
            'Sahodaya schools',
            sahodayaSchoolPages(tenantId, schoolId!),
        );
        recordSectionIssues(issues, testInfo);
    });

    test('school admin — static pages', async ({ page }, testInfo) => {
        const tenantId = await loginAndGetTenantId(page, 'school');
        const issues = await auditSectionSimple(page, 'School admin', schoolStaticPages(tenantId));
        recordSectionIssues(issues, testInfo);
    });

    test('school admin — fest program & event pages', async ({ page }, testInfo) => {
        const tenantId = await loginAndGetTenantId(page, 'school');
        const programId = await discoverFestProgramId(page, tenantId);
        const eventId = await discoverSchoolFestEventId(page, tenantId);

        const pages = programId ? schoolFestProgramPages(tenantId, programId) : [];
        if (eventId) {
            pages.push(...schoolFestEventPages(tenantId, eventId));
        }

        test.skip(pages.length === 0, 'No school fest programs/events seeded');

        const issues = await auditSectionSimple(page, 'School fest', pages);
        recordSectionIssues(issues, testInfo);
    });

    test('superadmin pages', async ({ page }, testInfo) => {
        const ok = await login(page, 'superadmin', SUPERADMIN_BASE);
        test.skip(!ok, 'Superadmin login failed');
        const issues = await auditSection(page, {
            area: 'Superadmin',
            pages: superadminPages,
            absoluteBase: SUPERADMIN_BASE,
        });
        recordSectionIssues(issues, testInfo);
    });

    test('state admin pages', async ({ page }, testInfo) => {
        const ok = await login(page, 'state_admin', SUPERADMIN_BASE);
        test.skip(!ok, 'State admin login failed');
        const issues = await auditSection(page, {
            area: 'State admin',
            pages: stateAdminPages,
            absoluteBase: SUPERADMIN_BASE,
        });
        recordSectionIssues(issues, testInfo);
    });
});

const portalRoleMap: { role: RoleKey; portalKey: string }[] = [
    { role: 'judge', portalKey: 'judge' },
    { role: 'teacher', portalKey: 'teacher' },
    { role: 'exam', portalKey: 'exam' },
    { role: 'student', portalKey: 'student' },
    { role: 'group', portalKey: 'group' },
    { role: 'festops', portalKey: 'festops' },
    { role: 'house_admin', portalKey: 'house_admin' },
];

for (const { role, portalKey } of portalRoleMap) {
    test.describe(`Portal: ${portalKey}`, () => {
        test(`${portalKey} dashboard pages`, async ({ page }, testInfo) => {
            let tenantId: string | null;
            if (role === 'festops' || role === 'house_admin') {
                const ok = await login(page, role);
                test.skip(!ok, `${role} login failed — run: php artisan e2e:provision-users`);
                const match = page.url().match(/\/portal\/[^/]+\/([0-9a-f-]{36})/);
                tenantId = match?.[1] ?? null;
            } else {
                tenantId = await loginPortal(page, role as 'judge' | 'teacher' | 'student' | 'exam' | 'group');
            }
            test.skip(!tenantId, `${portalKey} login failed — run: php artisan e2e:provision-users`);

            const issues = await auditSectionSimple(
                page,
                `Portal ${portalKey}`,
                portalPages(portalKey, tenantId),
            );
            recordSectionIssues(issues, testInfo);
        });
    });
}

test.describe('Portal deep pages', () => {
    test('exam attendance and marks', async ({ page }, testInfo) => {
        const tenantId = await loginPortal(page, 'exam');
        test.skip(!tenantId, 'Exam portal login failed');
        const examId = await discoverMcqExamIdForPortal(page, tenantId);
        test.skip(!examId, 'No MCQ exams in exam portal');

        const issues = await auditSectionSimple(page, 'Portal exam', portalExamPages(tenantId, examId!));
        recordSectionIssues(issues, testInfo);
    });

    test('judge mark entry', async ({ page }, testInfo) => {
        const tenantId = await loginPortal(page, 'judge');
        test.skip(!tenantId, 'Judge portal login failed');
        const eventId = await discoverPortalEventId(
            page,
            `/portal/judge/${tenantId}`,
            /\/events\/(\d+)/,
        );
        test.skip(!eventId, 'No judge event assignments');

        const issues = await auditSectionSimple(
            page,
            'Portal judge',
            portalJudgeEventPages(tenantId, eventId!),
        );
        recordSectionIssues(issues, testInfo);
    });

    test('fest ops event pages', async ({ page }, testInfo) => {
        const ok = await login(page, 'festops');
        test.skip(!ok, 'Fest ops login failed');
        const match = page.url().match(/\/portal\/fest-ops\/([0-9a-f-]{36})/);
        const tenantId = match?.[1];
        test.skip(!tenantId, 'Fest ops login did not reach portal');

        const eventId = await discoverPortalEventId(
            page,
            `/portal/fest-ops/${tenantId}`,
            /\/events\/(\d+)/,
        );
        test.skip(!eventId, 'No fest ops event assignments');

        const issues = await auditSectionSimple(
            page,
            'Portal fest ops',
            portalFestOpsEventPages(tenantId!, eventId!),
        );
        recordSectionIssues(issues, testInfo);
    });

    test('mark coordinator pages', async ({ page }, testInfo) => {
        const ok = await login(page, 'mark_coordinator');
        test.skip(!ok, 'Mark coordinator login failed');
        const match = page.url().match(/\/portal\/fest-coordinator\/([0-9a-f-]{36})/);
        test.skip(!match, 'Mark coordinator login did not reach portal');
        const tenantId = match![1];
        const eventId = await discoverPortalEventId(
            page,
            `/portal/fest-coordinator/${tenantId}`,
            /\/events\/(\d+)/,
        );
        test.skip(!eventId, 'No mark coordinator event assignments');

        const issues = await auditSectionSimple(
            page,
            'Portal mark coordinator',
            portalFestCoordinatorEventPages(tenantId, eventId!),
        );
        recordSectionIssues(issues, testInfo);
    });
});

test('summary — report and assert no critical issues', async () => {
    const report = writeFullReport();

    if (process.env.E2E_AUDIT_NO_FAIL === '1') {
        console.log(
            `[ux-audit] ${report.summary.pagesVisited} pages, ${report.summary.errors} errors (no-fail mode)`,
        );

        return;
    }

    const critical = getCriticalIssues();
    if (critical.length > 0) {
        const summary = critical.map((i) => `[${i.code}] ${i.message}`).join('\n');
        throw new Error(`${critical.length} critical UX issue(s) found:\n${summary}`);
    }
});
