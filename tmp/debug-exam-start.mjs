import { chromium } from 'playwright';

(async () => {
  const browser = await chromium.launch({
    headless: true,
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
  });
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();

  page.on('console', msg => console.log('BROWSER:', msg.text()));
  page.on('response', res => {
    if (res.url().includes('start') || res.url().includes('exam')) {
      console.log('HTTP:', res.status(), res.url());
    }
  });

  await page.goto('http://exam-demo.localhost:8773/portal/login', { waitUntil: 'load' });
  await page.fill('#email', 'STU/DEMO/001');
  await page.fill('#password', 'LocalExamDemo2026!');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/portal/student/**', { timeout: 15000 });

  await page.goto('http://exam-demo.localhost:8773/portal/student/10000000-0000-4000-8000-000000000002/mcq/8/exam', { waitUntil: 'load' });
  await page.waitForTimeout(2000);

  const startBtn = await page.$('button:has-text("Start Exam Now")');
  console.log('Start button exists:', !!startBtn);
  if (startBtn) {
    const isStartDisabled = await startBtn.isDisabled();
    console.log('Start button disabled before check:', isStartDisabled);

    const checkbox = await page.$('input[type="checkbox"]');
    console.log('Checkbox exists:', !!checkbox);
    if (checkbox) {
      await checkbox.check();
      await page.waitForTimeout(500);
      const isStartDisabledAfter = await startBtn.isDisabled();
      console.log('Start button disabled after check:', isStartDisabledAfter);

      await startBtn.click();
      console.log('Clicked Start button, waiting 3s...');
      await page.waitForTimeout(3000);
    }
  }

  const watermark = await page.$('.exam-watermark-overlay');
  console.log('Watermark element exists:', !!watermark);
  const pattern = await page.$('svg pattern');
  console.log('SVG pattern exists:', !!pattern);

  const bodySnippet = await page.innerText('body');
  console.log('Body snippet:', bodySnippet.slice(0, 300));

  await page.screenshot({ path: 'storage/app/mcq-web-test-evidence/debug-start-click.png' });
  await browser.close();
})();
