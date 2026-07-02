import { expect, type Page } from '@playwright/test';

export type RoleKey =
    | 'superadmin'
    | 'state_admin'
    | 'sahodaya'
    | 'sahodaya_staff'
    | 'school'
    | 'judge'
    | 'teacher'
    | 'student'
    | 'exam'
    | 'group'
    | 'festops'
    | 'house_admin'
    | 'mark_coordinator';

export const credentials: Record<RoleKey, { email: string; password: string }> = {
    superadmin: {
        email: process.env.E2E_SUPERADMIN_EMAIL ?? 'admin@sahodaya.test',
        password: process.env.E2E_SUPERADMIN_PASSWORD ?? 'password',
    },
    state_admin: {
        email: process.env.E2E_STATE_ADMIN_EMAIL ?? 'state_admin@e2e.test',
        password: process.env.E2E_STATE_ADMIN_PASSWORD ?? 'password',
    },
    sahodaya: {
        email: process.env.E2E_SAHODAYA_EMAIL ?? 'sahodaya@malappuram.test',
        password: process.env.E2E_SAHODAYA_PASSWORD ?? 'password',
    },
    sahodaya_staff: {
        email: process.env.E2E_SAHODAYA_STAFF_EMAIL ?? 'sahodaya_staff@e2e.test',
        password: process.env.E2E_SAHODAYA_STAFF_PASSWORD ?? 'password',
    },
    school: {
        email: process.env.E2E_SCHOOL_EMAIL ?? 'admin@amu-school.test',
        password: process.env.E2E_SCHOOL_PASSWORD ?? 'password',
    },
    judge: {
        email: process.env.E2E_JUDGE_EMAIL ?? 'judge@e2e.test',
        password: process.env.E2E_JUDGE_PASSWORD ?? 'password',
    },
    teacher: {
        email: process.env.E2E_TEACHER_EMAIL ?? 'teacher@e2e.test',
        password: process.env.E2E_TEACHER_PASSWORD ?? 'password',
    },
    student: {
        email: process.env.E2E_STUDENT_EMAIL ?? 'student@e2e.test',
        password: process.env.E2E_STUDENT_PASSWORD ?? 'password',
    },
    exam: {
        email: process.env.E2E_EXAM_EMAIL ?? 'exam@e2e.test',
        password: process.env.E2E_EXAM_PASSWORD ?? 'password',
    },
    group: {
        email: process.env.E2E_GROUP_EMAIL ?? 'group@e2e.test',
        password: process.env.E2E_GROUP_PASSWORD ?? 'password',
    },
    festops: {
        email: process.env.E2E_FESTOPS_EMAIL ?? 'festops@e2e.test',
        password: process.env.E2E_FESTOPS_PASSWORD ?? 'password',
    },
    house_admin: {
        email: process.env.E2E_HOUSE_ADMIN_EMAIL ?? 'house@e2e.test',
        password: process.env.E2E_HOUSE_ADMIN_PASSWORD ?? 'password',
    },
    mark_coordinator: {
        email: process.env.E2E_MARK_COORDINATOR_EMAIL ?? 'mark_coordinator@e2e.test',
        password: process.env.E2E_MARK_COORDINATOR_PASSWORD ?? 'password',
    },
};

export async function login(page: Page, role: RoleKey, baseUrl?: string): Promise<boolean> {
    const { email, password } = credentials[role];
    const loginPath = baseUrl ? `${baseUrl.replace(/\/$/, '')}/login` : '/login';

    await page.goto(loginPath);
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();

    try {
        await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 25_000 });
    } catch {
        return false;
    }

    const body = await page.locator('body').innerText();
    if (/Invalid credentials|Internal Server Error/i.test(body)) {
        return false;
    }

    return true;
}

export function extractTenantId(page: Page, panel: 'sahodaya-admin' | 'school-admin' | 'portal'): string | null {
    const patterns: Record<string, RegExp> = {
        'sahodaya-admin': /\/sahodaya-admin\/([0-9a-f-]{36})/,
        'school-admin': /\/school-admin\/([0-9a-f-]{36})/,
        portal: /\/portal\/(?:judge|teacher|exam|student|group|house-admin)\/([0-9a-f-]{36})/,
    };
    const match = page.url().match(patterns[panel]);

    return match?.[1] ?? null;
}

export async function loginAndGetTenantId(
    page: Page,
    role: 'sahodaya' | 'sahodaya_staff' | 'school',
): Promise<string> {
    const ok = await login(page, role);
    if (!ok) {
        throw new Error(`Login failed for role: ${role}`);
    }
    const panel = role === 'school' ? 'school-admin' : 'sahodaya-admin';
    const tenantId = extractTenantId(page, panel);

    if (!tenantId) {
        throw new Error(`Could not extract tenant id from ${page.url()} after ${role} login`);
    }

    return tenantId;
}

export async function loginPortal(page: Page, role: 'judge' | 'teacher' | 'student' | 'exam' | 'group'): Promise<string | null> {
    const ok = await login(page, role);
    if (!ok) {
        return null;
    }
    return extractTenantId(page, 'portal');
}

export const loginAs = login;
