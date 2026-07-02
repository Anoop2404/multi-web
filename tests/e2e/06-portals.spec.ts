import { test } from '@playwright/test';
import { loginPortal } from './support/auth';
import { portalExamPages, portalPages } from './support/page-catalog';
import { discoverMcqExamIdForPortal } from './support/discover';
import { assertNoCriticalIssues, auditPages } from './support/ux';

const portalRoles = ['judge', 'teacher', 'exam', 'student', 'group'] as const;

for (const role of portalRoles) {
    test.describe(`Portal: ${role}`, () => {
        test(`all ${role} portal pages pass UX audit`, async ({ page }, testInfo) => {
            const tenantId = await loginPortal(page, role);
            test.skip(!tenantId, `${role} portal login failed — run: php artisan e2e:provision-users`);

            const allIssues = await auditPages(page, portalPages(role, tenantId!));
            assertNoCriticalIssues(allIssues, testInfo);
        });
    });
}

test.describe('Portal: exam deep pages', () => {
    test('exam attendance and marks pages pass UX audit', async ({ page }, testInfo) => {
        const tenantId = await loginPortal(page, 'exam');
        test.skip(!tenantId, 'No MCQ exams in exam portal — run: php artisan e2e:seed-data');
        const examId = await discoverMcqExamIdForPortal(page, tenantId!);
        test.skip(!examId, 'No MCQ exams in exam portal — run: php artisan e2e:seed-data');

        const allIssues = await auditPages(page, portalExamPages(tenantId, examId!));
        assertNoCriticalIssues(allIssues, testInfo);
    });
});
