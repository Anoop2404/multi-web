import { chromium } from 'playwright';

(async () => {
  const browser = await chromium.launch({
    headless: true,
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
  });
  const context = await browser.newContext();
  const page = await context.newPage();

  page.on('response', res => {
    if (res.url().includes('login') || res.url().includes('exam')) {
      console.log('HTTP response:', res.status(), res.url());
    }
  });

  console.log('Navigating to portal/login...');
  await page.goto('http://exam-demo.localhost:8773/portal/login', { waitUntil: 'networkidle' });

  console.log('Filling credentials...');
  await page.fill('#email', 'STU/DEMO/001');
  await page.fill('#password', 'LocalExamDemo2026!');

  console.log('Submitting login form...');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(3000);

  const appData = await page.evaluate(() => {
    const el = document.getElementById('app');
    return el ? el.dataset.page : null;
  });
  console.log('Inertia component:', appData ? JSON.parse(appData).component : 'none');
  console.log('Inertia props errors:', appData ? JSON.parse(appData).props?.errors : 'none');

  await browser.close();
})();
