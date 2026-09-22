import { expect, test } from '@playwright/test';
import { loginAndGetTenantId } from './support/auth';

test.describe('School rich text editor', () => {
    test('supports visual and HTML editing without mobile overflow', async ({ page }) => {
        const tenantId = await loginAndGetTenantId(page, 'school');
        await page.goto(`/school-admin/${tenantId}/news/create`);

        const editor = page.getByRole('textbox', { name: 'Content' });
        await expect(editor).toBeVisible();
        await editor.fill('A formatted school update');
        await page.getByRole('button', { name: 'Bold' }).click();

        await page.getByRole('button', { name: 'HTML source' }).click();
        const source = page.locator('textarea[spellcheck="false"]');
        await expect(source).toBeVisible();
        await expect(source).toHaveValue(/A formatted school update/);

        await page.getByRole('button', { name: 'Visual editor' }).click();
        await expect(editor).toBeVisible();

        await page.setViewportSize({ width: 390, height: 844 });
        await expect(page.getByRole('toolbar', { name: 'Text formatting' })).toBeVisible();
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    });
});
