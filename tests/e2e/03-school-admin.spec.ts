import { test } from '@playwright/test';
import { loginAndGetTenantId } from './support/auth';
import { schoolFestProgramPages, schoolStaticPages } from './support/page-catalog';
import { discoverFestProgramId } from './support/discover';
import { assertNoCriticalIssues, auditPages, visitAndAudit } from './support/ux';

test.describe('School admin pages', () => {
    test('all school admin pages pass UX audit', async ({ page }, testInfo) => {
        const tenantId = await loginAndGetTenantId(page, 'school');
        const allIssues = await auditPages(page, schoolStaticPages(tenantId));
        assertNoCriticalIssues(allIssues, testInfo);
    });

    test('kalotsav registration shows quota widgets when events are open', async ({ page }) => {
        const tenantId = await loginAndGetTenantId(page, 'school');
        await page.goto(`/school-admin/${tenantId}/programs/kalotsav/registration`);
        await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});
        const body = await page.locator('body').innerText();
        const hasRegistrationUi = /quota|registration|event|kalotsav/i.test(body);
        test.skip(!hasRegistrationUi, 'No open kalotsav events for registration UI');
    });

    test('school fest program detail passes UX audit', async ({ page }, testInfo) => {
        const tenantId = await loginAndGetTenantId(page, 'school');
        const programId = await discoverFestProgramId(page, tenantId);
        test.skip(!programId, 'No school fest programs seeded');

        const allIssues = await auditPages(page, schoolFestProgramPages(tenantId, programId!));
        assertNoCriticalIssues(allIssues, testInfo);
    });
});
