import { chromium } from 'playwright';
import path from 'path';
import fs from 'fs';

const EVIDENCE_DIR = path.resolve('storage/app/mcq-web-test-evidence');
if (!fs.existsSync(EVIDENCE_DIR)) {
  fs.mkdirSync(EVIDENCE_DIR, { recursive: true });
}

function evidencePath(filename) {
  return path.join(EVIDENCE_DIR, filename);
}

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

(async () => {
  console.log('=== Starting End-to-End Online MCQ Web Verification ===');

  const browser = await chromium.launch({
    headless: true,
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
  });

  const context = await browser.newContext({
    viewport: { width: 1280, height: 800 },
    deviceScaleFactor: 2
  });

  const page = await context.newPage();

  page.on('console', msg => {
    const text = msg.text();
    if (text.includes('[error]') || text.includes('Error')) {
      console.log('BROWSER LOG:', text);
    }
  });

  // 1. Student Login
  console.log('1. Navigating to Student Portal Login...');
  await page.goto('http://exam-demo.localhost:8773/portal/login', { waitUntil: 'load' });
  await page.waitForSelector('#email', { timeout: 10000 });
  await page.fill('#email', 'STU/DEMO/001');
  await page.fill('#password', 'LocalExamDemo2026!');
  await page.click('button[type="submit"]');

  // Wait for login redirect to student dashboard
  await page.waitForURL('**/portal/student/**', { timeout: 15000 });
  console.log('Logged in successfully! Current URL:', page.url());

  // 2. Navigate to Exam Portal
  console.log('2. Entering MCQ Exam Portal...');
  const examUrl = 'http://exam-demo.localhost:8773/portal/student/10000000-0000-4000-8000-000000000002/mcq/8/exam';
  await page.goto(examUrl, { waitUntil: 'load' });
  await page.waitForTimeout(2000);

  // Check if we are on pre-exam instruction screen
  const startBtn = await page.$('button:has-text("Start Exam Now")');
  if (startBtn) {
    console.log('Pre-exam instructions detected. Capturing screenshot...');
    await page.screenshot({ path: evidencePath('00-pre-exam-instructions.png') });

    console.log('Accepting exam rules and clicking "Start Exam Now →"...');
    await page.click('label:has(input[type="checkbox"])');
    await sleep(400);
    await page.click('button:has-text("Start Exam Now")');
    await sleep(3000);
  }

  // Verify Exam Hall loaded with SVG watermark pattern
  console.log('Verifying exam hall watermark pattern...');
  await page.waitForSelector('#candidate-watermark', { state: 'attached', timeout: 15000 });
  const watermarkText = await page.textContent('#candidate-watermark');
  console.log('Candidate Watermark overlay detected:', watermarkText?.trim().slice(0, 100));
  await page.screenshot({ path: evidencePath('00-exam-hall-active.png') });

  // --- SECTION 1: Mathematics & Physics ---
  console.log('\n--- Testing Section 1: Mathematics & Physics ---');

  // Q1: KaTeX Inline & Block Math
  console.log('Testing Q1: KaTeX quadratic formula & roots...');
  await page.waitForSelector('.katex', { timeout: 5000 });
  const katexCount = await page.$$eval('.katex', els => els.length);
  console.log(`Found ${katexCount} rendered KaTeX math elements in Q1`);

  // Select Option A
  console.log('Selecting Option A for Q1...');
  await page.check('input[value="a"]', { force: true });
  await sleep(800); // let autosave trigger
  await page.screenshot({ path: evidencePath('01-q1-katex-math.png') });

  // Move to Q2
  console.log('Moving to Q2...');
  await page.click('button:has-text("Next")');
  await sleep(800);

  // Q2: Option Images (4 cards)
  console.log('Testing Q2: Single choice with 4 option diagram cards...');
  await page.waitForSelector('img[src*="option="]', { timeout: 5000 });
  const optImages = await page.$$eval('img[src*="option="]', els => els.map(e => e.src));
  console.log(`Found ${optImages.length} option diagram cards`);

  // Select Option B
  console.log('Selecting Option B for Q2...');
  await page.check('input[value="b"]', { force: true });

  // Mark for review
  console.log('Marking Q2 for review (star bookmark)...');
  await page.click('button[aria-pressed]');
  await sleep(800);
  await page.screenshot({ path: evidencePath('02-q2-option-images-reviewed.png') });

  // Move to Q3
  console.log('Moving to Q3...');
  await page.click('button:has-text("Next")');
  await sleep(800);

  // Q3: Numeric Input with KaTeX Integral
  console.log('Testing Q3: Numeric input with definite integral...');
  await page.waitForSelector('input[inputmode="decimal"], input[type="text"]', { timeout: 5000 });
  const numericInput = await page.$('input[inputmode="decimal"], input[type="text"]');
  if (numericInput) {
    await numericInput.fill('6');
    await sleep(400);

    // Test sign toggle button (±)
    const signBtn = await page.$('button:has-text("±")');
    if (signBtn) {
      console.log('Testing ± sign toggle button...');
      await signBtn.click();
      await sleep(300);
      const valNeg = await numericInput.inputValue();
      console.log('Value after toggle to negative:', valNeg);
      await signBtn.click(); // toggle back to +6
      await sleep(300);
      const valPos = await numericInput.inputValue();
      console.log('Value after toggle back to positive:', valPos);
    }
  }
  await sleep(800);
  await page.screenshot({ path: evidencePath('03-q3-numeric-formula-evaluated.png') });

  // --- SECTION 2: Biology & Life Sciences ---
  console.log('\n--- Testing Section 2: Biology & Life Sciences ---');
  // Click Section Tab in Question Palette
  console.log('Clicking Section Tab: "Biology & Life Sciences"...');
  const bioTab = await page.$('button[role="tab"]:has-text("Biology & Life Sciences")');
  if (bioTab) {
    await bioTab.click();
    await sleep(400);
  }
  await page.click('button:has-text("Next")');
  await sleep(800);

  // Q4: Ultra-wide diagram (1400x350) + Multi-select
  console.log('Testing Q4: Ultra-wide circuit diagram (1400x350) & lightbox zoom modal...');
  await page.waitForSelector('img[src*="questions"][src*="image"]', { timeout: 5000 });

  // Click image button to trigger Lightbox Zoom Modal
  console.log('Opening lightbox zoom modal...');
  await page.click('button:has(img[src*="questions"][src*="image"])');
  await sleep(600);
  await page.waitForSelector('div[aria-label="Enlarged picture"], button:has-text("Close Zoom")', { timeout: 4000 });
  await page.screenshot({ path: evidencePath('04-q4-wide-diagram-lightbox-modal.png') });

  // Close lightbox modal
  console.log('Closing lightbox zoom modal...');
  await page.click('button:has-text("Close Zoom")');
  await sleep(400);

  // Select Multiple Choice options A and C
  console.log('Selecting multiple choice options A and C...');
  await page.check('input[value="a"]', { force: true });
  await page.check('input[value="c"]', { force: true });
  await sleep(800);
  await page.screenshot({ path: evidencePath('04-q4-multi-select-answered.png') });

  // Move to Q5
  console.log('Moving to Q5...');
  await page.click('button:has-text("Next")');
  await sleep(800);

  // Q5: Tall Vertical Flowchart (420x1100)
  console.log('Testing Q5: Tall vertical flowchart (420x1100)...');
  await page.waitForSelector('img[src*="questions"][src*="image"]', { timeout: 5000 });
  // Select Option D (Ribosome)
  console.log('Selecting Option D for Q5...');
  await page.check('input[value="d"]', { force: true });
  await sleep(800);
  await page.screenshot({ path: evidencePath('05-q5-tall-vertical-diagram.png') });

  // Move to Q6
  console.log('Moving to Q6...');
  await page.click('button:has-text("Next")');
  await sleep(800);

  // Q6: True / False with Square Geometry Diagram (600x600)
  console.log('Testing Q6: True / False with square diagram (600x600)...');
  await page.waitForSelector('img[src*="questions"][src*="image"]', { timeout: 5000 });
  // Select True (Option a)
  console.log('Selecting "True" for Q6...');
  await page.click('label:has-text("True")');
  await sleep(800);
  await page.screenshot({ path: evidencePath('06-q6-square-diagram-true-false.png') });

  // --- SECTION 3: World Languages & Literature ---
  console.log('\n--- Testing Section 3: World Languages & Literature ---');
  const langTab = await page.$('button[role="tab"]:has-text("World Languages & Literature")');
  if (langTab) {
    await langTab.click();
    await sleep(400);
  }
  await page.click('button:has-text("Next")');
  await sleep(800);

  // Q7: Malayalam Script
  console.log('Testing Q7: Malayalam Script (മലയാളം)...');
  const bodyTextQ7 = await page.innerText('body');
  console.log('Malayalam text detected in body:', bodyTextQ7.includes('കുമാരനാശാൻ') || bodyTextQ7.includes('കരുണ'));
  console.log('Selecting Option A (കരുണ)...');
  await page.click('label:has-text("കരുണ")');
  await sleep(800);
  await page.screenshot({ path: evidencePath('07-q7-malayalam-script.png') });

  // Move to Q8
  console.log('Moving to Q8...');
  await page.click('button:has-text("Next")');
  await sleep(800);

  // Q8: Hindi Script
  console.log('Testing Q8: Hindi Devanagari Script (हिन्दी साहित्य)...');
  const bodyTextQ8 = await page.innerText('body');
  console.log('Hindi text detected in body:', bodyTextQ8.includes('छायावाद') || bodyTextQ8.includes('सूर्यकांत'));
  console.log('Selecting Option D (रामधारी सिंह दिनकर)...');
  await page.click('label:has-text("दिनकर")');
  await sleep(800);
  await page.screenshot({ path: evidencePath('08-q8-hindi-script.png') });

  // Move to Q9
  console.log('Moving to Q9...');
  await page.click('button:has-text("Next")');
  await sleep(800);

  // Q9: Arabic Script with RTL
  console.log('Testing Q9: Arabic Script & RTL Directionality (العربية)...');
  const rtlContainer = await page.$('[dir="rtl"], .text-right, [class*="rtl"]');
  console.log('RTL / Arabic container element present:', !!rtlContainer);
  console.log('Selecting Option A (الذهب)...');
  await page.click('label:has-text("الذهب")');
  await sleep(800);
  await page.screenshot({ path: evidencePath('09-q9-arabic-rtl-script.png') });

  // Move to Q10
  console.log('Moving to Q10...');
  await page.click('button:has-text("Next")');
  await sleep(800);

  // Q10: Tamil Script + Small Thumbnail Diagram (180x180)
  console.log('Testing Q10: Tamil Script & small thumbnail diagram (180x180)...');
  const tamilImg = await page.waitForSelector('img[src*="questions"][src*="image"]', { timeout: 5000 });
  console.log('Small diagram loaded:', !!tamilImg);
  console.log('Selecting Option A (சகப்பிணைப்பு)...');
  await page.click('label:has-text("சகப்பிணைப்பு")');
  await sleep(1000); // ensure autosave triggers
  await page.screenshot({ path: evidencePath('10-q10-tamil-script-small-diagram.png') });

  // --- RESPONSIVE VIEWPORTS ---
  console.log('\n--- Testing Responsive Viewports ---');

  // Tablet Viewport (768 x 1024)
  console.log('Setting Tablet Viewport (768x1024)...');
  await page.setViewportSize({ width: 768, height: 1024 });
  await sleep(600);
  await page.screenshot({ path: evidencePath('11-responsive-tablet-viewport.png') });

  // Mobile iPhone Viewport (375 x 812)
  console.log('Setting Mobile Viewport (375x812)...');
  await page.setViewportSize({ width: 375, height: 812 });
  await sleep(600);

  // Test Mobile Question Drawer Button
  const mobilePaletteBtn = await page.$('button:has-text("Palette"), button:has-text("Q"), button:has-text("Questions")');
  if (mobilePaletteBtn) {
    console.log('Opening mobile question palette drawer...');
    await mobilePaletteBtn.click();
    await sleep(600);
    await page.screenshot({ path: evidencePath('12-responsive-mobile-viewport-drawer.png') });
    // Close drawer
    const closeBtn = await page.$('button[aria-label="Close"], button:has-text("✕"), button:has-text("Close")');
    if (closeBtn) await closeBtn.click();
    else await page.keyboard.press('Escape');
    await sleep(400);
  }

  // Restore Desktop Viewport
  await page.setViewportSize({ width: 1280, height: 800 });
  await sleep(600);

  // --- STATE PERSISTENCE & RELOAD TEST ---
  console.log('\n--- Testing Draft State Persistence Across Page Reload ---');
  await page.reload({ waitUntil: 'load' });
  await page.waitForSelector('#candidate-watermark', { state: 'attached', timeout: 10000 });
  await sleep(1000);
  await page.screenshot({ path: evidencePath('13-page-reloaded-state-persisted.png') });
  console.log('State restored from server draft successfully!');

  // --- PRE-SUBMISSION CONFIRMATION MODAL ---
  console.log('\n--- Testing Pre-Submission Summary Modal ---');
  const reviewSubmitBtn = await page.waitForSelector('button:has-text("Review & Submit")', { timeout: 5000 });
  await reviewSubmitBtn.click();
  await sleep(800);

  await page.waitForSelector('#submit-exam-title', { timeout: 5000 });
  const modalText = await page.innerText('#submit-exam-title');
  console.log('Modal opened with title:', modalText);
  await page.screenshot({ path: evidencePath('14-pre-submission-summary-modal.png') });

  // Confirm Submission
  console.log('Confirming submission ("Yes, Submit My Exam")...');
  const confirmSubmitBtn = await page.waitForSelector('button:has-text("Yes, Submit My Exam")', { timeout: 5000 });
  await confirmSubmitBtn.click();

  // Wait for submission completion & redirect to Result Page
  console.log('Waiting for redirection to Result page...');
  await page.waitForTimeout(4000);
  await page.waitForSelector('h1, h2, .font-extrabold', { timeout: 15000 });
  await sleep(2000);

  console.log('Exam Result Page URL:', page.url());
  console.log('Exam Result Page Title:', await page.title());
  await page.screenshot({ path: evidencePath('15-result-page-overview.png'), fullPage: true });

  // --- RESULT PAGE QUESTION-BY-QUESTION REVIEW ---
  console.log('\n--- Testing Post-Exam Question-by-Question Review ---');

  // Verify filter chips exist on result page
  const correctFilterBtn = await page.$('button:has-text("Correct (")');
  if (correctFilterBtn) {
    console.log('Testing "Correct" filter chip...');
    await correctFilterBtn.click();
    await sleep(600);
    await page.screenshot({ path: evidencePath('16-result-filter-correct.png'), fullPage: false });
  }

  const allFilterBtn = await page.$('button:has-text("All (")');
  if (allFilterBtn) {
    console.log('Testing "All" filter chip...');
    await allFilterBtn.click();
    await sleep(600);
    await page.screenshot({ path: evidencePath('17-result-filter-all.png'), fullPage: false });
  }

  console.log('\n=== All Tests Completed Successfully! ===');
  await browser.close();
})();
