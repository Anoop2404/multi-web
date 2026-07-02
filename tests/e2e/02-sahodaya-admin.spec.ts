import { test } from '@playwright/test';
import { loginAndGetTenantId } from './support/auth';
import {
    sahodayaEventPages,
    sahodayaMcqPages,
    sahodayaSchoolPages,
    sahodayaStaticPages,
    sahodayaTrainingPages,
} from './support/page-catalog';
import {
    discoverFestEventId,
    discoverMcqExamId,
    discoverMemberSchoolId,
    discoverTrainingId,
} from './support/discover';
import { assertNoCriticalIssues, auditPages, collectConsoleErrors } from './support/ux';

test.describe('Sahodaya admin pages', () => {
    test('all static admin pages pass UX audit', async ({ page }, testInfo) => {
        const consoleErrors = await collectConsoleErrors(page);
        const tenantId = await loginAndGetTenantId(page, 'sahodaya');
        const allIssues = await auditPages(page, sahodayaStaticPages(tenantId), consoleErrors);
        assertNoCriticalIssues(allIssues, testInfo);
    });

    test('fest event sub-pages pass UX audit', async ({ page }, testInfo) => {
        const tenantId = await loginAndGetTenantId(page, 'sahodaya');
        const eventId = await discoverFestEventId(page, tenantId);
        test.skip(!eventId, 'No fest events — run: php artisan e2e:seed-data');

        const allIssues = await auditPages(page, sahodayaEventPages(tenantId, eventId!));
        assertNoCriticalIssues(allIssues, testInfo);
    });

    test('MCQ exam sub-pages pass UX audit', async ({ page }, testInfo) => {
        const tenantId = await loginAndGetTenantId(page, 'sahodaya');
        const examId = await discoverMcqExamId(page, tenantId);
        test.skip(!examId, 'No MCQ exams — run: php artisan e2e:seed-data');

        const allIssues = await auditPages(page, sahodayaMcqPages(tenantId, examId!));
        assertNoCriticalIssues(allIssues, testInfo);
    });

    test('training program pages pass UX audit', async ({ page }, testInfo) => {
        const tenantId = await loginAndGetTenantId(page, 'sahodaya');
        const programId = await discoverTrainingId(page, tenantId);
        test.skip(!programId, 'No training programs — run: php artisan e2e:seed-data');

        const allIssues = await auditPages(page, sahodayaTrainingPages(tenantId, programId!));
        assertNoCriticalIssues(allIssues, testInfo);
    });

    test('member school detail passes UX audit', async ({ page }, testInfo) => {
        const tenantId = await loginAndGetTenantId(page, 'sahodaya');
        const schoolId = await discoverMemberSchoolId(page, tenantId);
        test.skip(!schoolId, 'No member schools in demo data');

        const allIssues = await auditPages(page, sahodayaSchoolPages(tenantId, schoolId!));
        assertNoCriticalIssues(allIssues, testInfo);
    });
});
