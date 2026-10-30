/**
 * Capture portal exam-page redesign screenshots → Agent Store media/mcq-exam-page/
 */
import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE = 'http://exam-demo.localhost:8773';
const MEDIA =
  '/Users/neoo/Library/Application Support/Cursor/AgentStores/cursor_agent_stores/bc-b4151b6b-4053-48ac-a1da-d87e27546122/files/media/mcq-exam-page';
const PASS = 'LocalExamDemo2026!';
const SCHOOL = '10000000-0000-4000-8000-000000000002';

fs.mkdirSync(MEDIA, { recursive: true });

async function shot(page, name, fullPage = false) {
  const file = path.join(MEDIA, `${name}.png`);
  await page.screenshot({ path: file, fullPage });
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
  await page.goto(`${BASE}/portal/student/${SCHOOL}/mcq/6/exam`, { waitUntil: 'networkidle', timeout: 45000 });
  await page.waitForTimeout(1000);
  await shot(page, '01-prestart-desktop');

  const startBtn = page.getByRole('button', { name: /Start exam/i });
  if (await startBtn.count()) {
    await startBtn.click();
    await page.waitForTimeout(1800);
  }
  // Dismiss optional fullscreen if present
  const notNow = page.getByRole('button', { name: /Not now/i });
  if (await notNow.count()) {
    await notNow.click();
    await page.waitForTimeout(300);
  }
  await shot(page, '02-attempt-desktop');

  const opt = page.locator('form label').filter({ has: page.locator('input[type="radio"], input[type="checkbox"]') }).first();
  if (await opt.count()) {
    await opt.click();
    await page.waitForTimeout(1000);
  }
  await shot(page, '03-answered-desktop');

  const nextBtn = page.getByRole('button', { name: /Next/i });
  if (await nextBtn.count()) {
    await nextBtn.click();
    await page.waitForTimeout(600);
  }
  await shot(page, '04-q2-nav-desktop');

  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForTimeout(500);
  await shot(page, '05-attempt-mobile');

  const mid = page.getByRole('button', { name: /of \d+/i }).or(page.locator('nav[aria-label="Question navigation"] button').nth(1));
  if (await mid.count()) {
    await mid.first().click();
    await page.waitForTimeout(500);
    await shot(page, '06-palette-mobile');
    const close = page.getByRole('button', { name: /Close/i });
    if (await close.count()) await close.click();
  }

  await page.setViewportSize({ width: 768, height: 1024 });
  await page.waitForTimeout(400);
  await shot(page, '07-attempt-tablet');

  console.log('done');
} catch (e) {
  console.error(e);
  await shot(page, 'error-state').catch(() => {});
  process.exitCode = 1;
} finally {
  await browser.close();
}
