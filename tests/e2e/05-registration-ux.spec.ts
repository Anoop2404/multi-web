import { test, expect } from '@playwright/test';

test.describe('Mobile UX', () => {
    test('login page is mobile-friendly', async ({ page }) => {
        await page.goto('/login');
        await expect(page.locator('#email')).toBeVisible();
        await expect(page.getByRole('button', { name: /sign in/i })).toBeVisible();

        const overflow = await page.evaluate(() =>
            document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
        );
        expect(overflow).toBe(false);
    });
});
