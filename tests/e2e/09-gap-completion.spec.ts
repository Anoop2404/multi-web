import { test, expect } from '@playwright/test';
import { loginAs, loginAndGetTenantId } from './support/auth';

test.describe('Fest ops portal', () => {
    test('fest ops user reaches dashboard', async ({ page }) => {
        await loginAs(page, 'festops');
        await page.waitForURL(/fest-ops/, { timeout: 25_000 });
        await expect(page.locator('body')).not.toContainText('Internal Server Error');
        await expect(page.locator('body')).not.toContainText('403');
    });
});

test.describe('State users (superadmin)', () => {
    test('superadmin can open state users page', async ({ page }) => {
        await loginAs(page, 'superadmin');
        await page.goto('/admin/state-users');
        await expect(page.getByText('State users')).toBeVisible();
    });
});

test.describe('Portal users', () => {
    test('sahodaya admin can open portal users with edit', async ({ page }) => {
        const tenantId = await loginAndGetTenantId(page, 'sahodaya');
        await page.goto(`/sahodaya-admin/${tenantId}/users`);
        await expect(page.getByText('Portal users')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Edit' }).first()).toBeVisible();
    });

    test('school admin can open portal users with edit', async ({ page }) => {
        const tenantId = await loginAndGetTenantId(page, 'school');
        await page.goto(`/school-admin/${tenantId}/users`);
        await expect(page.getByText('Portal users')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Edit' }).first()).toBeVisible();
    });
});
