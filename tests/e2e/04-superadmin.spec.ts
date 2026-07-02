import { test } from '@playwright/test';
import { login } from './support/auth';
import { superadminPages } from './support/page-catalog';
import { assertNoCriticalIssues, auditPages } from './support/ux';

test.describe('Superadmin pages', () => {
    test('superadmin panel pages pass UX audit', async ({ page }, testInfo) => {
        await login(page, 'superadmin');
        const allIssues = await auditPages(page, superadminPages);
        assertNoCriticalIssues(allIssues, testInfo);
    });
});
