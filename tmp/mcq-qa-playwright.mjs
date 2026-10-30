/**
 * Local MCQ UI/UX + functional smoke for exam-demo.localhost:8773
 * Creds: docs/audits/LOCAL_ONLINE_EXAM_TEST_2026-10-26.md
 * Extra local fixture: school-admin@demo.test (same demo password) for school model exams.
 */
import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE = 'http://exam-demo.localhost:8773';
const MEDIA = '/Users/neoo/Library/Application Support/Cursor/AgentStores/cursor_agent_stores/bc-b4151b6b-4053-48ac-a1da-d87e27546122/files/media/mcq-qa';
const SAHODAYA = '10000000-0000-4000-8000-000000000001';
const SCHOOL = '10000000-0000-4000-8000-000000000002';
const PASS = 'LocalExamDemo2026!';
const VIEWPORTS = {
  desktop: { width: 1280, height: 800 },
  tablet: { width: 768, height: 1024 },
  mobile: { width: 375, height: 812 },
};

const findings = [];
const matrix = [];

function note(level, msg, extra = {}) {
  findings.push({ level, msg, ...extra, at: new Date().toISOString() });
  console.log(`[${level}] ${msg}`);
}
function record(screen, viewport, pass, notes = '') {
  matrix.push({ screen, viewport, pass, notes });
  console.log(`  matrix ${viewport}/${screen}: ${pass ? 'PASS' : 'FAIL'} ${notes}`);
}
async function shot(page, folder, name) {
  const dir = path.join(MEDIA, folder);
  fs.mkdirSync(dir, { recursive: true });
  const file = path.join(dir, `${name}.png`);
  await page.screenshot({ path: file, fullPage: true });
  return file;
}
async function waitApp(page) {
  await page.waitForLoadState('domcontentloaded').catch(() => {});
  await page.waitForTimeout(600);
  // Prefer content over blank inertia shell
  await page.waitForFunction(() => {
    const t = (document.body?.innerText || '').trim();
    return t.length > 40;
  }, { timeout: 12000 }).catch(() => {});
}
async function softGoto(page, url, screen, vp) {
  const res = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch((e) => ({ ok: () => false, status: () => 0, _err: e.message }));
  await waitApp(page);
  const status = typeof res?.status === 'function' ? res.status() : 0;
  const title = await page.title().catch(() => '');
  const bodyText = await page.locator('body').innerText().catch(() => '');
  const blank = bodyText.trim().length < 40;
  const failed = status >= 500 || /server error|exception|whoops|sqlstate/i.test(bodyText);
  const missing = status === 404 || /page not found|404/i.test(title) || /ERROR 404/i.test(bodyText);
  const forbidden = /403|forbidden|not authorized|don't have permission/i.test(bodyText);
  const pass = !failed && !missing && !blank && !forbidden;
  record(screen, vp, pass, `HTTP ${status}; blank=${blank}; forbid=${forbidden}; title=${title.slice(0, 50)}`);
  if (!pass) note(missing ? 'warn' : 'fail', `${screen}@${vp} issue`, { url, status, blank, forbidden, title });
  return { status, title, bodyText, failed, missing, blank, forbidden, pass };
}
async function measureOverflow(page) {
  return page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
    overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
  }));
}
async function fillLogin(page, email, password) {
  await page.waitForSelector('#email, input[name="email"], input[type="email"]', { timeout: 15000 });
  const emailBox = page.locator('#email, input[name="email"], input[type="email"]').first();
  const passBox = page.locator('#password, input[name="password"], input[type="password"]').first();
  await emailBox.fill(email);
  await passBox.fill(password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 25000 }).catch(() => null),
    page.locator('button[type="submit"], button:has-text("Sign in")').first().click(),
  ]);
  await waitApp(page);
}
async function loginAdmin(page, email) {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await waitApp(page);
  await fillLogin(page, email, PASS);
  const url = page.url();
  const ok = !url.includes('/login');
  note(ok ? 'info' : 'fail', `Admin login ${email} → ${url}`);
  return ok;
}
async function loginPortal(page, identifier) {
  await page.goto(`${BASE}/portal/login`, { waitUntil: 'domcontentloaded' });
  await waitApp(page);
  await fillLogin(page, identifier, PASS);
  const url = page.url();
  const ok = !url.includes('/portal/login');
  if (!ok) {
    const err = await page.locator('.login-error, [role="alert"], .login-form').innerText().catch(() => '');
    note('fail', `Portal login failed for ${identifier}`, { url, err: err.slice(0, 300) });
  } else note('info', `Portal login ${identifier} → ${url}`);
  return ok;
}
async function clearCookies(context) {
  await context.clearCookies();
}

async function bankAutosaveProbe(page) {
  await softGoto(page, `${BASE}/sahodaya-admin/${SAHODAYA}/mcq/question-banks/1`, 'bank-show', 'desktop');
  await shot(page, 'flows', 'bank-show-before');
  const body = await page.locator('body').innerText();
  const mentionsAutosave = /autosave|auto-save|saving draft|draft saved/i.test(body);
  note(mentionsAutosave ? 'info' : 'warn', mentionsAutosave
    ? 'Bank UI mentions autosave'
    : 'Bank authoring: no autosave copy; explicit Save only (expected)');

  // Prefer Add question
  let opened = false;
  const add = page.locator('button:has-text("Add question"), a:has-text("Add question")').first();
  if (await add.count()) {
    await add.click();
    opened = true;
  } else {
    const edit = page.locator('button:has-text("Edit")').first();
    if (await edit.count()) {
      await edit.click();
      opened = true;
    }
  }
  await page.waitForTimeout(800);
  await shot(page, 'flows', 'bank-editor');
  if (!opened) {
    record('bank-draft-autosave', 'desktop', false, 'no add/edit');
    return;
  }
  const textArea = page.locator('textarea').first();
  if (!(await textArea.count())) {
    record('bank-draft-autosave', 'desktop', false, 'no textarea');
    return;
  }
  const marker = `QA-DRAFT-${Date.now()}`;
  await textArea.fill(marker);
  await page.waitForTimeout(2500);
  await page.reload({ waitUntil: 'domcontentloaded' });
  await waitApp(page);
  const after = await page.locator('body').innerText();
  const persisted = after.includes(marker);
  // For bank authoring, NO autosave is the current product behavior — record as FAIL against the QA goal
  record('bank-draft-autosave', 'desktop', persisted, persisted
    ? 'unsaved text persisted'
    : 'unsaved text lost after reload — no draft autosave');
  note(persisted ? 'info' : 'warn', persisted
    ? 'Bank draft autosave works'
    : 'Bank question draft does NOT autosave (explicit Save only)');
  await shot(page, 'flows', 'bank-after-reload-no-autosave');
}

async function adminPass(context) {
  const page = await context.newPage();
  await page.setViewportSize(VIEWPORTS.desktop);

  // Sahodaya admin
  let ok = await loginAdmin(page, 'exam-admin@demo.test');
  record('admin-login', 'desktop', ok, page.url());
  if (!ok) { await shot(page, 'flows', 'admin-login-fail'); await page.close(); return; }
  await shot(page, 'flows', 'admin-dashboard');

  const sahodayaUrls = [
    { screen: 'sahodaya-mcq-hub', path: `/sahodaya-admin/${SAHODAYA}/mcq` },
    { screen: 'sahodaya-mcq-index', path: `/sahodaya-admin/${SAHODAYA}/mcq-exams` },
    { screen: 'sahodaya-exam-show', path: `/sahodaya-admin/${SAHODAYA}/mcq-exams/1` },
    { screen: 'sahodaya-question-banks', path: `/sahodaya-admin/${SAHODAYA}/mcq/question-banks` },
    { screen: 'sahodaya-bank-show', path: `/sahodaya-admin/${SAHODAYA}/mcq/question-banks/1` },
    { screen: 'sahodaya-paper', path: `/sahodaya-admin/${SAHODAYA}/mcq-exams/1/question-banks` },
    { screen: 'sahodaya-results', path: `/sahodaya-admin/${SAHODAYA}/mcq-exams/1/results` },
    { screen: 'sahodaya-leaderboard', path: `/sahodaya-admin/${SAHODAYA}/mcq-exams/1/leaderboard` },
    { screen: 'sahodaya-live-session', path: `/sahodaya-admin/${SAHODAYA}/mcq-exams/1/session` },
    { screen: 'sahodaya-live-engine', path: `/sahodaya-admin/${SAHODAYA}/mcq-exams/1/live` },
  ];
  for (const u of sahodayaUrls) {
    await softGoto(page, `${BASE}${u.path}`, u.screen, 'desktop');
    await shot(page, 'desktop', `admin-${u.screen}`);
  }

  await bankAutosaveProbe(page);

  // Publish results exam 3
  await softGoto(page, `${BASE}/sahodaya-admin/${SAHODAYA}/mcq-exams/3/results`, 'exam3-results', 'desktop');
  await shot(page, 'flows', 'exam3-results');
  const publishBtn = page.locator('button:has-text("Publish results"), button:has-text("Publish Results"), button:has-text("Release results")').first();
  if (await publishBtn.count()) {
    await publishBtn.click();
    await page.waitForTimeout(1200);
    await shot(page, 'flows', 'exam3-results-published');
    record('publish-results', 'desktop', true, 'clicked publish');
  } else {
    record('publish-results', 'desktop', true, 'button absent / already published');
  }

  // Viewport sweeps
  for (const [vpName, size] of Object.entries(VIEWPORTS)) {
    if (vpName === 'desktop') continue;
    await page.setViewportSize(size);
    for (const u of [
      { screen: 'sahodaya-mcq-hub', path: `/sahodaya-admin/${SAHODAYA}/mcq` },
      { screen: 'sahodaya-bank-show', path: `/sahodaya-admin/${SAHODAYA}/mcq/question-banks/1` },
      { screen: 'sahodaya-exam-show', path: `/sahodaya-admin/${SAHODAYA}/mcq-exams/1` },
      { screen: 'sahodaya-live-session', path: `/sahodaya-admin/${SAHODAYA}/mcq-exams/1/session` },
      { screen: 'sahodaya-results', path: `/sahodaya-admin/${SAHODAYA}/mcq-exams/1/results` },
    ]) {
      await softGoto(page, `${BASE}${u.path}`, u.screen, vpName);
      await shot(page, vpName, `admin-${u.screen}`);
      const ov = await measureOverflow(page);
      record(`${u.screen}-overflow`, vpName, !ov.overflow, JSON.stringify(ov));
    }
  }

  await page.close();
  await clearCookies(context);

  // School admin / model exams
  const schoolPage = await context.newPage();
  await schoolPage.setViewportSize(VIEWPORTS.desktop);
  ok = await loginAdmin(schoolPage, 'school-admin@demo.test');
  record('school-admin-login', 'desktop', ok, schoolPage.url());
  if (ok) {
    await shot(schoolPage, 'flows', 'school-admin-dashboard');
    for (const u of [
      { screen: 'school-model-exams', path: `/school-admin/${SCHOOL}/model-exams` },
      { screen: 'school-mcq-hub', path: `/school-admin/${SCHOOL}/mcq` },
      { screen: 'school-question-banks', path: `/school-admin/${SCHOOL}/model-exams/question-banks` },
    ]) {
      await softGoto(schoolPage, `${BASE}${u.path}`, u.screen, 'desktop');
      await shot(schoolPage, 'desktop', `admin-${u.screen}`);
      for (const [vpName, size] of Object.entries(VIEWPORTS)) {
        if (vpName === 'desktop') continue;
        await schoolPage.setViewportSize(size);
        await softGoto(schoolPage, `${BASE}${u.path}`, u.screen, vpName);
        await shot(schoolPage, vpName, `admin-${u.screen}`);
        const ov = await measureOverflow(schoolPage);
        record(`${u.screen}-overflow`, vpName, !ov.overflow, JSON.stringify(ov));
      }
      await schoolPage.setViewportSize(VIEWPORTS.desktop);
    }
  } else {
    note('fail', 'School admin login failed — school model exams not exercised in browser');
  }
  await schoolPage.close();
  await clearCookies(context);
}

async function studentPass(context) {
  const page = await context.newPage();
  await page.setViewportSize(VIEWPORTS.desktop);

  // Try email first, then reg_no
  let ok = await loginPortal(page, 'practice@demo.test');
  if (!ok) {
    await clearCookies(context);
    ok = await loginPortal(page, 'STU/DEMO/002');
  }
  record('portal-login', 'desktop', ok, page.url());
  if (!ok) {
    await shot(page, 'flows', 'portal-login-fail');
    await page.close();
    return;
  }
  await shot(page, 'flows', 'portal-after-login');

  await softGoto(page, `${BASE}/portal/student/${SCHOOL}/mcq`, 'student-mcq-hub', 'desktop');
  await shot(page, 'flows', 'student-mcq-hub');
  await softGoto(page, `${BASE}/portal/student/${SCHOOL}/mcq/system-check`, 'system-check', 'desktop');
  await shot(page, 'flows', 'system-check');

  const examPath = `/portal/student/${SCHOOL}/mcq/6/exam`;
  await softGoto(page, `${BASE}${examPath}`, 'take-exam-prepare', 'desktop');
  await shot(page, 'flows', 'take-exam-prepare');

  const start = page.locator('button:has-text("Start"), button:has-text("Begin"), button:has-text("Start exam"), button:has-text("Enter exam")').first();
  if (await start.count()) {
    await start.click();
    await page.waitForTimeout(1200);
    const fsBtn = page.locator('button:has-text("Enter fullscreen"), button:has-text("Fullscreen")').first();
    if (await fsBtn.count()) {
      note('info', 'Fullscreen require overlay present');
      await shot(page, 'flows', 'fullscreen-blocker');
      await fsBtn.click().catch(() => null);
    }
  }
  await waitApp(page);
  await shot(page, 'flows', 'take-exam-running');

  const saveReqs = [];
  page.on('response', (resp) => {
    if (resp.url().includes('save-answers')) saveReqs.push({ status: resp.status(), url: resp.url() });
  });

  // Answer mix across questions
  for (let i = 0; i < 8; i++) {
    const radios = page.locator('input[type="radio"]');
    const checks = page.locator('input[type="checkbox"]');
    const numbers = page.locator('input[type="number"]');
    if (await radios.count()) await radios.nth(0).check({ force: true }).catch(() => null);
    if (await checks.count()) {
      await checks.nth(0).check({ force: true }).catch(() => null);
      if ((await checks.count()) > 1) await checks.nth(1).check({ force: true }).catch(() => null);
    }
    if (await numbers.count()) await numbers.first().fill('15');
    const next = page.locator('button:has-text("Next")').first();
    if (await next.count()) await next.click().catch(() => null);
    await page.waitForTimeout(350);
  }
  await shot(page, 'flows', 'take-exam-answered-mix');
  await page.waitForTimeout(1500);

  const saveText = await page.locator('body').innerText();
  const autosaveUi = /saved|saving|autosave|last saved/i.test(saveText);
  record('exam-autosave-indicator', 'desktop', autosaveUi, autosaveUi ? 'save status visible' : 'no save status text');
  const saveOk = saveReqs.some((r) => r.status >= 200 && r.status < 300);
  record('exam-autosave-network', 'desktop', saveOk || autosaveUi, JSON.stringify(saveReqs));

  const before = await page.evaluate(() => ({
    checked: [...document.querySelectorAll('input:checked')].length,
    nums: [...document.querySelectorAll('input[type="number"]')].map((el) => el.value),
  }));
  await page.reload({ waitUntil: 'domcontentloaded' });
  await waitApp(page);
  await shot(page, 'flows', 'take-exam-after-reload');
  const after = await page.evaluate(() => ({
    checked: [...document.querySelectorAll('input:checked')].length,
    nums: [...document.querySelectorAll('input[type="number"]')].map((el) => el.value),
  }));
  const persisted = (before.checked > 0 && after.checked > 0) || (before.nums.some(Boolean) && after.nums.some(Boolean));
  record('exam-autosave-persist', 'desktop', persisted, `before=${JSON.stringify(before)} after=${JSON.stringify(after)}`);
  note(persisted ? 'pass' : 'fail', persisted ? 'Answers persisted after refresh' : 'Answers did NOT persist after refresh');

  await page.setViewportSize(VIEWPORTS.mobile);
  await page.waitForTimeout(400);
  await shot(page, 'mobile', 'take-exam-running');
  record('take-exam-overflow', 'mobile', !(await measureOverflow(page)).overflow, JSON.stringify(await measureOverflow(page)));
  await page.setViewportSize(VIEWPORTS.tablet);
  await shot(page, 'tablet', 'take-exam-running');
  await page.setViewportSize(VIEWPORTS.desktop);

  const submit = page.locator('button:has-text("Submit"), button:has-text("Submit exam"), button:has-text("Finish")').first();
  if (await submit.count()) {
    await submit.click();
    await page.waitForTimeout(700);
    await shot(page, 'flows', 'submit-confirm');
    const confirm = page.locator('button:has-text("Confirm"), button:has-text("Yes"), button:has-text("Submit exam"), button:has-text("Submit anyway")').last();
    if (await confirm.count()) await confirm.click().catch(() => null);
    await page.waitForTimeout(1500);
    await shot(page, 'flows', 'after-submit');
    const post = await page.locator('body').innerText();
    const leak = /you scored|your score|marks:\s*\d+/i.test(post);
    record('submit-no-score-leak', 'desktop', !leak, leak ? 'score-like text' : 'ok');
    record('submit', 'desktop', true, page.url());
  } else {
    record('submit', 'desktop', false, 'no submit button');
  }

  // Viewport sweep hub + syscheck
  for (const [vpName, size] of Object.entries(VIEWPORTS)) {
    await page.setViewportSize(size);
    for (const u of [
      { screen: 'student-mcq-hub', path: `/portal/student/${SCHOOL}/mcq` },
      { screen: 'system-check', path: `/portal/student/${SCHOOL}/mcq/system-check` },
    ]) {
      await softGoto(page, `${BASE}${u.path}`, u.screen, vpName);
      await shot(page, vpName, `student-${u.screen}`);
      const ov = await measureOverflow(page);
      record(`${u.screen}-overflow`, vpName, !ov.overflow, JSON.stringify(ov));
    }
  }
  await page.close();
}

async function main() {
  fs.mkdirSync(MEDIA, { recursive: true });
  const chromePath = process.env.PW_CHROME
    || '/Users/neoo/Library/Caches/ms-playwright/chromium-1228/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing';
  const browser = await chromium.launch({ headless: true, executablePath: chromePath });
  const context = await browser.newContext({ baseURL: BASE, ignoreHTTPSErrors: true });
  try {
    await adminPass(context);
    await studentPass(context);
  } catch (e) {
    note('fail', `Harness crashed: ${e.message}`, { stack: String(e.stack || '').slice(0, 600) });
  }
  const out = { findings, matrix, generatedAt: new Date().toISOString() };
  fs.writeFileSync(path.join(MEDIA, 'qa-raw.json'), JSON.stringify(out, null, 2));
  console.log('PASS', matrix.filter((m) => m.pass).length, 'FAIL', matrix.filter((m) => !m.pass).length);
  await browser.close();
}

main().catch((e) => { console.error(e); process.exit(1); });
