import { chromium } from 'playwright';

(async () => {
  const browser = await chromium.launch({
    headless: true,
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
  });
  const context = await browser.newContext();
  const page = await context.newPage();

  await page.goto('http://exam-demo.localhost:8773/portal/login', { waitUntil: 'load' });
  await page.fill('#email', 'STU/DEMO/001');
  await page.fill('#password', 'LocalExamDemo2026!');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/portal/student/**', { timeout: 15000 });

  await page.goto('http://exam-demo.localhost:8773/portal/student/10000000-0000-4000-8000-000000000002/mcq/8/exam', { waitUntil: 'load' });
  await page.waitForTimeout(3000);

  const data = await page.evaluate(() => {
    const el = document.getElementById('app');
    if (!el || !el.dataset.page) return { raw: document.body.innerHTML.slice(0, 1000) };
    const p = JSON.parse(el.dataset.page);
    return {
      component: p.component,
      started: p.props?.started,
      mark: p.props?.mark,
      exam: p.props?.exam?.title,
      questionsCount: p.props?.questions?.length,
      html: document.body.innerText.slice(0, 500)
    };
  });
  console.log('Exam page inspection:', JSON.stringify(data, null, 2));

  await page.screenshot({ path: 'storage/app/mcq-web-test-evidence/inspect-exam-screen.png' });
  await browser.close();
})();
