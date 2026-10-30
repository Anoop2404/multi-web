import { chromium } from 'playwright';
import path from 'path';
import fs from 'fs';
import { execSync } from 'child_process';

const DB_PATH = path.resolve('storage/app/local-online-demo/online-exam-demo.sqlite');
const EVIDENCE_DIR = path.resolve('storage/app/mcq-web-test-evidence');

if (!fs.existsSync(EVIDENCE_DIR)) {
  fs.mkdirSync(EVIDENCE_DIR, { recursive: true });
}

function runSql(sql) {
  execSync(`sqlite3 "${DB_PATH}" "${sql.replace(/"/g, '\\"')}"`);
}

function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

const VIEWPORTS = [
  { id: '1920', name: 'Desktop FHD Wide', width: 1920, height: 1080, isMobile: false },
  { id: '1280', name: 'Desktop Standard Laptop', width: 1280, height: 800, isMobile: false },
  { id: '1024', name: 'Tablet Landscape (iPad Air/Pro)', width: 1024, height: 768, isMobile: false },
  { id: '768',  name: 'Tablet Portrait (iPad)', width: 768, height: 1024, isMobile: false },
  { id: '390',  name: 'Mobile Standard (iPhone 14/15/16)', width: 390, height: 844, isMobile: true },
  { id: '360',  name: 'Mobile Compact (Android/SE)', width: 360, height: 740, isMobile: true }
];

(async () => {
  console.log('=== Starting Multi-Viewport Responsive Verification ===');

  const browser = await chromium.launch({
    headless: true,
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
  });

  const context = await browser.newContext({
    viewport: { width: 1280, height: 800 },
    deviceScaleFactor: 2
  });

  const page = await context.newPage();

  // Log in as student
  console.log('Logging in as student STU/DEMO/001...');
  await page.goto('http://exam-demo.localhost:8773/portal/login', { waitUntil: 'load' });
  await page.waitForSelector('#email', { timeout: 10000 });
  await page.fill('#email', 'STU/DEMO/001');
  await page.fill('#password', 'LocalExamDemo2026!');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/portal/student/**', { timeout: 15000 });
  console.log('Login successful.');

  const examUrl = 'http://exam-demo.localhost:8773/portal/student/10000000-0000-4000-8000-000000000002/mcq/8/exam';

  const results = [];

  for (const vp of VIEWPORTS) {
    console.log(`\n======================================================`);
    console.log(`Testing Viewport: ${vp.name} (${vp.width}x${vp.height})`);
    console.log(`======================================================`);

    await page.setViewportSize({ width: vp.width, height: vp.height });

    // --- PHASE A: EXAM HALL VIEW ---
    console.log(`[${vp.id}] Setting Registration 8 to active 'started' state in IST...`);
    runSql("UPDATE mcq_registrations SET status = 'started', submitted_at = NULL, started_at = datetime('now', '+330 minutes') WHERE id = 8;");

    await page.goto(examUrl, { waitUntil: 'load' });
    await page.waitForTimeout(1000);

    // If pre-exam rules modal is shown, accept and continue
    const startBtn = await page.$('button:has-text("Start Exam Now")');
    if (startBtn) {
      console.log(`[${vp.id}] Accepting pre-exam rules modal...`);
      await page.click('label:has(input[type="checkbox"])');
      await sleep(300);
      await page.click('button:has-text("Start Exam Now")');
      await sleep(2000);
    }

    // Wait for watermark and exam hall layout
    await page.waitForSelector('#candidate-watermark', { state: 'attached', timeout: 10000 });
    await sleep(600);

    // Verify Horizontal Overflow
    const overflowExam = await page.evaluate(() => {
      return {
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
        hasOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth
      };
    });

    console.log(`[${vp.id}] Exam Hall Layout: scrollWidth=${overflowExam.scrollWidth}, clientWidth=${overflowExam.clientWidth}, overflow=${overflowExam.hasOverflow}`);

    const examHallShot = path.join(EVIDENCE_DIR, `vp-${vp.id}-exam-hall.png`);
    await page.screenshot({ path: examHallShot });
    console.log(`[${vp.id}] Saved Exam Hall Shot: ${examHallShot}`);

    // If mobile, test bottom sheet / drawer
    let drawerShot = null;
    if (vp.isMobile) {
      const drawerBtn = await page.$('button[aria-label*="Tap to open full question palette"]');
      if (drawerBtn) {
        console.log(`[${vp.id}] Opening mobile question drawer...`);
        await drawerBtn.click();
        await sleep(600);
        drawerShot = path.join(EVIDENCE_DIR, `vp-${vp.id}-drawer.png`);
        await page.screenshot({ path: drawerShot });
        console.log(`[${vp.id}] Saved Mobile Drawer Shot: ${drawerShot}`);

        // Close drawer with Done button
        const closeBtn = await page.$('button:has-text("Done ✕")');
        if (closeBtn) {
          await closeBtn.click();
        } else {
          await page.keyboard.press('Escape');
        }
        await sleep(400);
      }
    }

    // --- PHASE B: RESULT REVIEW VIEW ---
    console.log(`[${vp.id}] Setting Registration 8 to 'submitted' state in IST...`);
    runSql("UPDATE mcq_registrations SET status = 'submitted', submitted_at = datetime('now', '+330 minutes') WHERE id = 8;");

    await page.goto(examUrl, { waitUntil: 'load' });
    await page.waitForTimeout(1500);

    // Check for Result page header
    await page.waitForSelector('text=Assessment Report', { timeout: 10000 });
    await sleep(600);

    const overflowResult = await page.evaluate(() => {
      return {
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
        hasOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth
      };
    });

    console.log(`[${vp.id}] Result Page Layout: scrollWidth=${overflowResult.scrollWidth}, clientWidth=${overflowResult.clientWidth}, overflow=${overflowResult.hasOverflow}`);

    const resultShot = path.join(EVIDENCE_DIR, `vp-${vp.id}-result-review.png`);
    await page.screenshot({ path: resultShot, fullPage: false });
    console.log(`[${vp.id}] Saved Result Review Shot: ${resultShot}`);

    results.push({
      id: vp.id,
      name: vp.name,
      dimensions: `${vp.width}x${vp.height}`,
      examOverflow: overflowExam.hasOverflow,
      resultOverflow: overflowResult.hasOverflow,
      examHallShot,
      drawerShot,
      resultShot
    });
  }

  console.log('\n======================================================');
  console.log('SUMMARY OF MULTI-VIEWPORT VERIFICATION:');
  console.log('======================================================');
  console.table(results.map(r => ({
    Viewport: r.name,
    Resolution: r.dimensions,
    'Exam Hall Overflow': r.examOverflow ? 'FAIL (Overflow)' : 'PASS (Zero Overflow)',
    'Result Page Overflow': r.resultOverflow ? 'FAIL (Overflow)' : 'PASS (Zero Overflow)',
    'Drawer Tested': r.drawerShot ? 'PASS (Captured)' : 'N/A'
  })));

  await browser.close();
  console.log('Multi-viewport check finished successfully!');
})();
