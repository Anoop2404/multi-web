/**
 * Capture portal take-exam screenshots → Agent Store media/mcq-exam-take/
 */
import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE = 'http://exam-demo.localhost:8773';
const MEDIA =
  '/Users/neoo/Library/Application Support/Cursor/AgentStores/cursor_agent_stores/bc-b4151b6b-4053-48ac-a1da-d87e27546122/files/media/mcq-exam-take';
const PASS = 'LocalExamDemo2026!';
const SCHOOL = '10000000-0000-4000-8000-000000000002';

fs.mkdirSync(MEDIA, { recursive: true });

async function shot(page, name) {
  const file = path.join(MEDIA, `${name}.png`);
  await page.screenshot({ path: file, fullPage: true });
  console.log('shot', file);
  return file;
}

async function login(page, email) {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForSelector('input[type="email"], #email, input[name="email"]', { timeout: 15000 });
  await page.locator('input[type="email"], #email, input[name="email"]').first().fill(email);
  await page.locator('input[type="password"], #password, input[name="password"]').first().fill(PASS);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null),
    page.locator('button[type="submit"]').first().click(),
  ]);
  await page.waitForTimeout(800);
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });

try {
  await login(page, 'practice@demo.test');
  await page.goto(`${BASE}/portal/student/${SCHOOL}/mcq`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1000);
  await shot(page, '01-hub-desktop');

  // Prefer an exam that is not submitted — click first Take / Continue / Start link
  const examLink = page.locator('a[href*="/mcq/"][href*="/exam"]').first();
  if (await examLink.count()) {
    await examLink.click();
    await page.waitForTimeout(1200);
  } else {
    // fallback: try registration 6 from prior QA
    await page.goto(`${BASE}/portal/student/${SCHOOL}/mcq/6/exam`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1200);
  }
  await shot(page, '02-prestart-desktop');

  const startBtn = page.getByRole('button', { name: /Start exam/i });
  if (await startBtn.count()) {
    await startBtn.click();
    await page.waitForTimeout(1500);
  }
  await shot(page, '03-attempt-desktop');

  // Answer first visible radio/checkbox if present
  const opt = page.locator('form label').filter({ has: page.locator('input[type="radio"], input[type="checkbox"]') }).first();
  if (await opt.count()) {
    await opt.click();
    await page.waitForTimeout(900);
  }
  await shot(page, '04-answered-autosave-desktop');

  await page.setViewportSize({ width: 375, height: 812 });
  await page.waitForTimeout(400);
  await shot(page, '05-attempt-mobile');

  await page.setViewportSize({ width: 768, height: 1024 });
  await page.waitForTimeout(400);
  await shot(page, '06-attempt-tablet');

  // Engine mock screens via vite if available — skip if not; document in analysis
  console.log('done');
} catch (e) {
  console.error(e);
  await shot(page, 'error-state').catch(() => {});
  process.exitCode = 1;
} finally {
  await browser.close();
}
