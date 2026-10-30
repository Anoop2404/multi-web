/**
 * Smoke: venue keycode join → start → strict tab auto-submit → reattempt.
 * Demo: exam-demo.localhost:8773, keycode A32EA2DF (practice@demo.test / STU/DEMO/002).
 */
import { chromium } from 'playwright';

const BASE = process.env.DEMO_BASE || 'http://exam-demo.localhost:8773';
const KEYCODE = process.env.DEMO_KEYCODE || 'A32EA2DF';
const SCHOOL_ID = '10000000-0000-4000-8000-000000000002';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
const log = (m) => console.log(`[smoke] ${m}`);

try {
  await page.goto(`${BASE}/attend-exam?code=${KEYCODE}`, { waitUntil: 'networkidle' });
  log(`attend page: ${page.url()}`);
  await page.waitForSelector('#keycode', { timeout: 15000 });
  await page.fill('#keycode', KEYCODE);
  await Promise.all([
    page.waitForURL(/\/portal\/student\/.+\/mcq\/\d+\/exam/, { timeout: 20000 }),
    page.click('button[type="submit"]'),
  ]);
  log(`after keycode: ${page.url()}`);

  const examMatch = page.url().match(/mcq\/(\d+)\/exam/);
  if (!examMatch) throw new Error('Did not land on exam page');
  const regId = examMatch[1];
  log(`registration=${regId}`);

  // Accept rules + start if pre-start
  const accept = page.locator('input[type="checkbox"]').first();
  if (await accept.count()) {
    await accept.check({ force: true });
  }
  const startBtn = page.getByRole('button', { name: /start exam|start/i }).first();
  if (await startBtn.isVisible().catch(() => false)) {
    await Promise.all([
      page.waitForURL(/\/mcq\/\d+\/exam/, { timeout: 15000 }),
      startBtn.click(),
    ]);
    log(`started: ${page.url()}`);
  }

  // Strict tab: fire two visibility-hidden style proctor posts via page context
  const csrf = await page.evaluate(() => document.querySelector('meta[name="csrf-token"]')?.content);
  for (let i = 1; i <= 2; i++) {
    const res = await page.evaluate(async ({ schoolId, regId, csrf, i }) => {
      const r = await fetch(`/portal/student/${schoolId}/mcq/${regId}/proctor-event`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ type: 'tab_hidden' }),
      });
      const body = await r.json().catch(() => ({}));
      return { status: r.status, body, i };
    }, { schoolId: SCHOOL_ID, regId, csrf, i });
    log(`proctor #${i}: ${JSON.stringify(res)}`);
    if (res.body?.auto_submitted) {
      if (res.body.redirect) {
        await page.goto(res.body.redirect, { waitUntil: 'networkidle' });
      } else {
        await page.reload({ waitUntil: 'networkidle' });
      }
      break;
    }
  }

  log(`after strict tab: ${page.url()}`);
  const bodyText = await page.locator('body').innerText();
  if (!/submitted|result|score|reattempt/i.test(bodyText)) {
    throw new Error('Expected result/submitted UI after auto-submit');
  }
  log('auto-submit UI ok');

  // Reattempt from result page if button present
  const reattempt = page.getByRole('button', { name: /reattempt|start reattempt/i }).first();
  if (await reattempt.isVisible().catch(() => false)) {
    await Promise.all([
      page.waitForURL(/\/mcq\/\d+\/exam/, { timeout: 15000 }),
      reattempt.click(),
    ]);
    log(`after reattempt: ${page.url()}`);
    const prestart = await page.getByText(/before you start|accept the exam rules/i).count();
    if (!prestart) throw new Error('Expected pre-start after reattempt');
    log('reattempt pre-start ok');
  } else {
    // Fallback POST
    await page.goto(`${BASE}/portal/student/${SCHOOL_ID}/mcq/${regId}/exam`, { waitUntil: 'networkidle' });
    const btn = page.getByRole('button', { name: /reattempt|start reattempt/i }).first();
    if (await btn.isVisible().catch(() => false)) {
      await Promise.all([
        page.waitForURL(/\/mcq\/\d+\/exam/, { timeout: 15000 }),
        btn.click(),
      ]);
      log(`fallback reattempt: ${page.url()}`);
    } else {
      log('WARNING: no reattempt button visible (may have exhausted attempts)');
    }
  }

  console.log('SMOKE_OK');
} catch (e) {
  console.error('SMOKE_FAIL', e);
  await page.screenshot({ path: 'tmp/smoke-fail.png', fullPage: true }).catch(() => {});
  process.exitCode = 1;
} finally {
  await browser.close();
}
