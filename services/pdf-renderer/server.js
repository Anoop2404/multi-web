// Headless Chromium HTML-to-PDF rendering service. Called by App\Support\PdfGenerator
// (see PDF_CONVERTER_URL in config/services.php) whenever that env var is set, instead
// of the pure-PHP DomPDF fallback — DomPDF has no live layout engine, so it can't run
// the certificate templates' client-side auto-fit text pass
// (resources/views/fest/partials/certificate-fit-text-script.blade.php), which needs a
// real browser to measure rendered text before shrinking/truncating it.
const express = require('express');
const puppeteer = require('puppeteer-core');

const PORT = parseInt(process.env.PORT || '3000', 10);
const EXECUTABLE_PATH = process.env.PUPPETEER_EXECUTABLE_PATH || '/usr/bin/chromium';
const MAX_CONCURRENT_RENDERS = parseInt(process.env.PDF_RENDERER_CONCURRENCY || '4', 10);
const FIT_TEXT_TIMEOUT_MS = parseInt(process.env.PDF_RENDERER_FIT_TIMEOUT_MS || '15000', 10);
const NAV_TIMEOUT_MS = parseInt(process.env.PDF_RENDERER_NAV_TIMEOUT_MS || '60000', 10);

const app = express();
// Certificate HTML with embedAssets=true inlines logo/seal/background images as base64
// data URIs (see FestCertificateService::renderContext()) — express's 100kb JSON default
// is far too small for that.
app.use(express.json({ limit: '50mb' }));

let browser = null;
let launching = null;

async function getBrowser() {
    if (browser && browser.connected) {
        return browser;
    }
    if (launching) {
        return launching;
    }

    launching = puppeteer.launch({
        executablePath: EXECUTABLE_PATH,
        headless: true,
        // Required to run Chromium as root inside a container — this service only ever
        // renders HTML this app's own admins configured (certificate/ID-card templates),
        // the same trust boundary the previous DomPDF renderer already had.
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
    }).then((launched) => {
        browser = launched;
        launching = null;
        browser.on('disconnected', () => {
            console.error('Chromium disconnected — will relaunch on next request.');
            browser = null;
        });
        return browser;
    }).catch((err) => {
        launching = null;
        throw err;
    });

    return launching;
}

// Simple in-process concurrency gate — Chromium tabs are far lighter than whole browser
// instances, but rendering ~150 certificates in one chunk job (see
// app/Jobs/RenderCertificateChunkJob.php) shouldn't be allowed to open 150 tabs at once
// against one container's CPU/memory budget.
let active = 0;
const waiters = [];

function acquireSlot() {
    return new Promise((resolve) => {
        function tryAcquire() {
            if (active < MAX_CONCURRENT_RENDERS) {
                active += 1;
                resolve();
            } else {
                waiters.push(tryAcquire);
            }
        }
        tryAcquire();
    });
}

function releaseSlot() {
    active -= 1;
    const next = waiters.shift();
    if (next) {
        next();
    }
}

app.get('/health', (req, res) => {
    res.json({ status: 'ok', active, queued: waiters.length });
});

app.post('/render', async (req, res) => {
    const {
        html,
        landscape = false,
        printBackground = true,
        format = 'A4',
        margin,
        displayHeaderFooter = false,
        headerTemplate,
        footerTemplate,
    } = req.body || {};

    if (typeof html !== 'string' || html.length === 0) {
        res.status(422).json({ error: 'html is required' });
        return;
    }

    await acquireSlot();

    let page = null;
    try {
        const activeBrowser = await getBrowser();
        page = await activeBrowser.newPage();
        page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
        await page.setViewport({ width: 1200, height: 1000 });
        await page.setContent(html, { waitUntil: 'networkidle0', timeout: NAV_TIMEOUT_MS });

        // Give the template's own client-side fit-text pass a chance to finish shrinking/
        // truncating overflowing fields before capture. Best-effort: a template with no
        // overlay fields (or the legacy no-background design) never sets this flag at
        // all, so a timeout here is an expected, non-fatal outcome, not an error.
        try {
            await page.waitForFunction('window.__certFitDone === true', { timeout: FIT_TEXT_TIMEOUT_MS });
        } catch (waitErr) {
            console.warn('fit-text pass did not signal completion in time, capturing anyway:', waitErr.message);
        }

        const pdfOptions = {
            printBackground,
            format,
            landscape,
            margin: margin || { top: '0', bottom: '0', left: '0', right: '0' },
        };

        if (displayHeaderFooter) {
            pdfOptions.displayHeaderFooter = true;
            pdfOptions.headerTemplate = headerTemplate || '<span></span>';
            pdfOptions.footerTemplate = footerTemplate || '<span></span>';
        }

        const pdfBuffer = await page.pdf(pdfOptions);

        res.set('Content-Type', 'application/pdf');
        res.send(pdfBuffer);
    } catch (err) {
        console.error('PDF render failed:', err);
        res.status(500).json({ error: 'PDF render failed', message: err.message });
    } finally {
        if (page) {
            await page.close().catch(() => {});
        }
        releaseSlot();
    }
});

const server = app.listen(PORT, () => {
    console.log(`pdf-renderer listening on :${PORT} (concurrency=${MAX_CONCURRENT_RENDERS})`);
    getBrowser().catch((err) => console.error('Initial Chromium launch failed:', err));
});

async function shutdown() {
    server.close();
    if (browser) {
        await browser.close().catch(() => {});
    }
    process.exit(0);
}

process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
