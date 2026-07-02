import { test, expect } from '@playwright/test';
import { login, loginAndGetTenantId } from './support/auth';

test.describe('Fest features — houses, finance, leaderboard', () => {
    test('school admin can open houses page', async ({ page }) => {
        const tenantId = await loginAndGetTenantId(page, 'school');
        await page.goto(`/school-admin/${tenantId}/houses`);
        await expect(page.locator('body')).not.toContainText('Internal Server Error');
        await expect(page.getByText('School houses')).toBeVisible();
    });

    test('house admin reaches portal dashboard', async ({ page }) => {
        await login(page, 'house_admin');
        await page.waitForURL(/house-admin/, { timeout: 25_000 });
        await expect(page.locator('body')).not.toContainText('403');
        await expect(page.locator('body')).not.toContainText('Internal Server Error');
    });

    test('sahodaya admin can open finance and leaderboard hub', async ({ page }) => {
        const tenantId = await loginAndGetTenantId(page, 'sahodaya');
        await page.goto(`/sahodaya-admin/${tenantId}/events`);
        await expect(page.locator('body')).not.toContainText('Internal Server Error');

        const eventLink = page.locator(`a[href*="/sahodaya-admin/${tenantId}/events/"]`).first();
        if (await eventLink.count()) {
            const href = await eventLink.getAttribute('href');
            const match = href?.match(/events\/(\d+)/);
            if (match) {
                const eventId = match[1];
                await page.goto(`/sahodaya-admin/${tenantId}/events/${eventId}/finance`);
                await expect(page.locator('body')).not.toContainText('Internal Server Error');

                await page.goto(`/sahodaya-admin/${tenantId}/events/${eventId}/leaderboard`);
                await expect(page.locator('body')).not.toContainText('Internal Server Error');

                await page.goto(`/sahodaya-admin/${tenantId}/events/${eventId}/athletic-records`);
                await expect(page.locator('body')).not.toContainText('Internal Server Error');

                await page.goto(`/sahodaya-admin/${tenantId}/events/${eventId}/food-coupons`);
                await expect(page.locator('body')).not.toContainText('Internal Server Error');
            }
        }
    });
});
