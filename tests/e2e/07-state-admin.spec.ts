import { test } from '@playwright/test';
import { login } from './support/auth';
import { stateAdminPages } from './support/page-catalog';
import { assertNoCriticalIssues, auditPages } from './support/ux';

test.describe('State admin pages', () => {
    test('state admin routes pass UX audit', async ({ page }, testInfo) => {
        await login(page, 'state_admin');
        const allIssues = await auditPages(page, stateAdminPages);
        assertNoCriticalIssues(allIssues, testInfo);
    });
});
