import { test } from '@playwright/test';
import { login } from './support/auth';
import { sahodayaStaticPages } from './support/page-catalog';
import { assertNoCriticalIssues, auditPages } from './support/ux';

test.describe('Sahodaya staff', () => {
    test('sahodaya staff can access admin pages', async ({ page }, testInfo) => {
        await login(page, 'sahodaya_staff');
        const match = page.url().match(/\/sahodaya-admin\/([0-9a-f-]{36})/);
        const tenantId = match?.[1];
        if (!tenantId) {
            throw new Error('Sahodaya staff login failed: ' + page.url());
        }

        const allIssues = await auditPages(page, sahodayaStaticPages(tenantId));
        assertNoCriticalIssues(allIssues, testInfo);
    });
});
