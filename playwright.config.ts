import { defineConfig, devices } from '@playwright/test';

const SAHODAYA_BASE = process.env.E2E_SAHODAYA_URL ?? 'http://malappuramsahodaya.test:8000';
const SUPERADMIN_BASE = process.env.E2E_SUPERADMIN_URL ?? 'http://superadmin.test:8000';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'tests/e2e/report', open: 'never' }],
        ['./tests/e2e/support/ux-reporter.ts'],
    ],
    timeout: 60_000,
    expect: { timeout: 10_000 },
    use: {
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        actionTimeout: 15_000,
        navigationTimeout: 30_000,
    },
    projects: [
        {
            name: 'full-ux-audit',
            testMatch: /00-full-ux-audit/,
            use: { ...devices['Desktop Chrome'], baseURL: SAHODAYA_BASE },
        },
        {
            name: 'public',
            testMatch: /01-public/,
            use: { ...devices['Desktop Chrome'], baseURL: SAHODAYA_BASE },
        },
        {
            name: 'sahodaya-admin',
            testMatch: /02-sahodaya/,
            use: { ...devices['Desktop Chrome'], baseURL: SAHODAYA_BASE },
        },
        {
            name: 'school-admin',
            testMatch: /03-school/,
            use: { ...devices['Desktop Chrome'], baseURL: SAHODAYA_BASE },
        },
        {
            name: 'superadmin',
            testMatch: /04-superadmin/,
            use: { ...devices['Desktop Chrome'], baseURL: SUPERADMIN_BASE },
        },
        {
            name: 'mobile-school',
            testMatch: /05-registration/,
            use: { ...devices['Pixel 7'], baseURL: SAHODAYA_BASE },
        },
        {
            name: 'portals',
            testMatch: /06-portals/,
            use: { ...devices['Desktop Chrome'], baseURL: SAHODAYA_BASE },
        },
        {
            name: 'state-admin',
            testMatch: /07-state-admin/,
            use: { ...devices['Desktop Chrome'], baseURL: SUPERADMIN_BASE },
        },
        {
            name: 'sahodaya-staff',
            testMatch: /08-sahodaya-staff/,
            use: { ...devices['Desktop Chrome'], baseURL: SAHODAYA_BASE },
        },
        {
            name: 'fest-features',
            testMatch: /10-fest-features/,
            use: { ...devices['Desktop Chrome'], baseURL: SAHODAYA_BASE },
        },
    ],
});
