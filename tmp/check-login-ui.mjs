import { chromium } from 'playwright';

(async () => {
  const browser = await chromium.launch({
    headless: true,
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
  });
  const page = await browser.newPage();
  console.log('Navigating to portal/login...');
  await page.goto('http://exam-demo.localhost:8773/portal/login', { waitUntil: 'networkidle' });
  console.log('Title:', await page.title());
  const inputs = await page.$$eval('input', els => els.map(el => ({ id: el.id, name: el.name, type: el.type, placeholder: el.placeholder })));
  console.log('Inputs found:', JSON.stringify(inputs));
  await page.screenshot({ path: 'storage/app/mcq-web-test-evidence/debug-login.png' });
  await browser.close();
  console.log('Done');
})();
