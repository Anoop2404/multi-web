import { expect, test } from '@playwright/test';

const SCHOOL_BASE = process.env.E2E_STANDALONE_SCHOOL_URL
    ?? 'http://al-farooque.sahodaya.test:8000';

const viewports = [
    { name: 'phone', width: 390, height: 844 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'laptop', width: 1024, height: 768 },
    { name: 'desktop', width: 1440, height: 900 },
];

test.describe('Standalone school administration login', () => {
    for (const viewport of viewports) {
        test(`is responsive on ${viewport.name}`, async ({ page }) => {
            await page.setViewportSize(viewport);
            const response = await page.goto(`${SCHOOL_BASE}/school-login`);

            expect(response?.status()).toBe(200);
            await expect(page).toHaveTitle(/School Login — .* Administration/);
            await expect(page.getByRole('heading', { name: 'School Administration Login' })).toBeVisible();
            await expect(page.getByRole('link', { name: 'Back to school website' })).toHaveAttribute('href', '/');
            await expect(page.getByRole('button', { name: 'Sign in to dashboard' })).toBeVisible();
            await expect(page.getByText('Sahodaya')).toHaveCount(0);

            const layout = await page.evaluate(() => {
                const shell = document.querySelector('.login-shell')!.getBoundingClientRect();
                const button = document.querySelector('.login-btn')!.getBoundingClientRect();
                const firstInput = document.querySelector('.login-input')!.getBoundingClientRect();
                const steps = getComputedStyle(document.querySelector('.login-steps')!);
                const contacts = getComputedStyle(document.querySelector('.login-contacts')!);

                return {
                    horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth,
                    shellWithinViewport: shell.left >= 0 && shell.right <= window.innerWidth,
                    buttonWithinViewport: button.left >= 0 && button.right <= window.innerWidth,
                    inputWithinViewport: firstInput.left >= 0 && firstInput.right <= window.innerWidth,
                    stepsDisplay: steps.display,
                    contactsDisplay: contacts.display,
                };
            });

            expect(layout.horizontalOverflow).toBe(false);
            expect(layout.shellWithinViewport).toBe(true);
            expect(layout.buttonWithinViewport).toBe(true);
            expect(layout.inputWithinViewport).toBe(true);

            if (viewport.width < 640) {
                expect(layout.stepsDisplay).toBe('none');
                expect(layout.contactsDisplay).toBe('none');
            } else {
                expect(layout.stepsDisplay).not.toBe('none');
                expect(layout.contactsDisplay).not.toBe('none');
            }
        });
    }

    test('uses the dedicated route and a school-only access landing', async ({ page }) => {
        await page.goto(`${SCHOOL_BASE}/login`);
        await expect(page).toHaveURL(`${SCHOOL_BASE}/school-login`);

        await page.goto(`${SCHOOL_BASE}/portal`);
        await expect(page.getByRole('link', { name: /School Administration Login/i })).toHaveAttribute('href', '/school-login');
        await expect(page.getByText('Sahodaya')).toHaveCount(0);
        await expect(page.getByText('Kalotsav')).toHaveCount(0);
        await expect(page.locator('a[href="/portal/login"]')).toHaveCount(0);
    });
});

test.describe('Standalone school public website', () => {
    for (const viewport of viewports) {
        test(`navbar and public pages are responsive on ${viewport.name}`, async ({ page }) => {
            await page.setViewportSize(viewport);

            for (const path of ['/', '/about', '/faculty', '/contact', '/gallery']) {
                const response = await page.goto(`${SCHOOL_BASE}${path}`);
                expect(response?.status(), `${path} should load`).toBe(200);

                const layout = await page.evaluate(() => ({
                    horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth,
                    navWithinViewport: (() => {
                        const nav = document.querySelector('.site-main-navigation')?.getBoundingClientRect();
                        return Boolean(nav && nav.left >= 0 && nav.right <= window.innerWidth);
                    })(),
                }));

                expect(layout.horizontalOverflow, `${path} should not overflow horizontally`).toBe(false);
                expect(layout.navWithinViewport, `${path} navbar should stay inside the viewport`).toBe(true);
                await expect(page.getByText('Secretariat')).toHaveCount(0);
            }

            await page.goto(`${SCHOOL_BASE}/`);
            const desktopNav = page.locator('.site-desktop-navigation');
            const mobileToggle = page.locator('.site-mobile-toggle');

            if (viewport.width < 1280) {
                await expect(desktopNav).toBeHidden();
                await expect(mobileToggle).toBeVisible();
                await mobileToggle.click();
                await expect(page.locator('.site-mobile-navigation')).toBeVisible();
                await expect(page.locator('.site-mobile-navigation a[href="/faculty"]')).toHaveCount(1);
            } else {
                await expect(desktopNav).toBeVisible();
                await expect(mobileToggle).toBeHidden();
                await expect(page.locator('.site-desktop-navigation a[href="/faculty"]')).toHaveCount(1);
            }
        });
    }
});
