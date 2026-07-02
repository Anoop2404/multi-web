import { test } from '@playwright/test';
import { publicPages } from './support/page-catalog';
import { assertNoCriticalIssues, auditPages } from './support/ux';

test.describe('Public pages', () => {
    for (const { path, label } of publicPages) {
        test(`${label} loads without errors`, async ({ page }, testInfo) => {
            const allIssues = await auditPages(page, [{ path, label }]);
            assertNoCriticalIssues(allIssues, testInfo);
        });
    }
});
