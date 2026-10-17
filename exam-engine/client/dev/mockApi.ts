/**
 * DEV ONLY — an in-memory fake of the engine's student API (CONTRACT §4) so the client can be
 * clicked through with `npm run dev:mock`. Never bundled into dist/.
 *
 * Sign in with: any Sahodaya code; username anything + password "demo"; or hall ticket
 * anything + passcode K7M2P-Q9RTX; or /?launch=demo. Usernames "busy", "locked", "conflict"
 * return those errors. The exam starts MOCK_START_IN seconds after the first login (default 20).
 * Controls: POST /__mock/lock | /__mock/unlock | /__mock/broadcast?text=… | /__mock/extend?minutes=5 |
 * /__mock/fail?count=3 (next N student API calls get 503) | /__mock/bump (force a resync) |
 * /__mock/end (deadline in ~15 s) | /__mock/reset | GET /__mock/state.
 */
import { createCipheriv, createHash, randomBytes } from 'node:crypto';
import type { IncomingMessage, ServerResponse } from 'node:http';
import type { Plugin } from 'vite';
import { canonicalAnswerString } from '../src/lib/checksum';

type Json = Record<string, unknown>;

const START_IN_S = Number(process.env.MOCK_START_IN ?? 20);
const DURATION_MIN = Number(process.env.MOCK_DURATION ?? 30);
const SUPERVISED = process.env.MOCK_SUPERVISED === '1';
const SHOW_SCORE = process.env.MOCK_SHOW_SCORE !== '0';
const RUN = 'demo-tenant:12';

const IMG =
    'data:image/svg+xml;base64,' +
    Buffer.from(
        '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="120"><rect width="220" height="120" fill="#eef2ff"/><polygon points="20,100 110,20 200,100" fill="none" stroke="#1d4ed8" stroke-width="4"/><text x="98" y="92" font-size="18" fill="#0f172a">θ</text></svg>',
    ).toString('base64');

const PAPER = {
    version: 3,
    questions: [
        { id: 101, type: 'single', section: 'Section A — Mathematics', body: 'What is $\\frac{3}{4} + \\frac{1}{8}$?', image: null, options: [ { key: 'a', label: '$\\frac{7}{8}$', image: null }, { key: 'b', label: '$\\frac{4}{12}$', image: null }, { key: 'c', label: '$1$', image: null }, { key: 'd', label: '$\\frac{1}{2}$', image: null } ], show_count: null, marks: 1, negative_mark: 0.25 },
        { id: 102, type: 'multiple', section: 'Section A — Mathematics', body: 'Which of these numbers are prime?', image: null, options: [ { key: 'a', label: '2', image: null }, { key: 'b', label: '9', image: null }, { key: 'c', label: '13', image: null }, { key: 'd', label: '21', image: null } ], show_count: 2, marks: 2, negative_mark: 0.5 },
        { id: 103, type: 'numeric', section: 'Section A — Mathematics', body: 'Solve for $x$: $$2x - 7 = -12$$', image: null, options: [], show_count: null, marks: 2, negative_mark: 0 },
        { id: 104, type: 'single', section: 'Section A — Mathematics', body: 'In the triangle shown, the marked angle θ is closest to:', image: IMG, options: [ { key: 'a', label: '30°', image: null }, { key: 'b', label: '60°', image: null }, { key: 'c', label: '90°', image: null } ], show_count: null, marks: 1, negative_mark: 0 },
        { id: 201, type: 'true_false', section: 'Section B — Science', body: 'Water boils at $100^{\\circ}\\mathrm{C}$ at sea level.', image: null, options: [ { key: 'a', label: 'True', image: null }, { key: 'b', label: 'False', image: null } ], show_count: null, marks: 1, negative_mark: 0 },
        { id: 202, type: 'single', section: 'Section B — Science', body: 'ജലത്തിന്റെ രാസസൂത്രം ഏതാണ്? (What is the chemical formula of water?)', image: null, options: [ { key: 'a', label: '$\\mathrm{H_2O}$', image: null }, { key: 'b', label: '$\\mathrm{CO_2}$', image: null }, { key: 'c', label: '$\\mathrm{O_2}$', image: null }, { key: 'd', label: 'NaCl', image: null } ], show_count: null, marks: 1, negative_mark: 0 },
        { id: 203, type: 'multiple', section: 'Section B — Science', body: 'Select all the mammals.', image: null, options: [ { key: 'a', label: 'Whale', image: null }, { key: 'b', label: 'Shark', image: null }, { key: 'c', label: 'Bat', image: null }, { key: 'd', label: 'Penguin', image: null }, { key: 'e', label: 'Elephant', image: null } ], show_count: null, marks: 2, negative_mark: 0 },
        { id: 204, type: 'single', section: 'Section B — Science', body: 'Pick the picture of the triangle.', image: null, options: [ { key: 'a', label: '', image: IMG }, { key: 'b', label: 'A circle', image: null } ], show_count: null, marks: 1, negative_mark: 0 },
    ],
};

// Fixed key/iv so a dev-server restart doesn't invalidate bundles cached in the browser.
const KEY = createHash('sha256').update('mock-paper-key').digest();
const IV = createHash('sha256').update('mock-paper-iv').digest().subarray(0, 12);
const BUNDLE = (() => {
    const c = createCipheriv('aes-256-gcm', KEY, IV);
    const body = Buffer.concat([c.update(JSON.stringify(PAPER), 'utf8'), c.final()]);
    return { version: PAPER.version, iv: IV.toString('base64'), ciphertext: Buffer.concat([body, c.getAuthTag()]).toString('base64') };
})();

interface Attempt {
    state: string;
    startsAt: number;
    startedAt: number | null;
    extra: number;
    answers: Record<string, unknown>;
    flagged: string[];
    rev: number;
    systemCheck: Json | null;
    submittedAt: number | null;
    receipt: string | null;
    locked: boolean;
    events: unknown[];
}

let attempt: Attempt | null = null;
const sessions = new Set<string>();
const messages: { id: number; at: string; text: string }[] = [];
let failNext = 0;

function fresh(): Attempt {
    return { state: 'checked_in', startsAt: Date.now() + START_IN_S * 1000, startedAt: null, extra: 0, answers: {}, flagged: [], rev: 0, systemCheck: null, submittedAt: null, receipt: null, locked: false, events: [] };
}

const iso = (ms: number) => new Date(ms).toISOString();
const deadlineMs = (a: Attempt) => a.startsAt + (DURATION_MIN + a.extra) * 60000;
const sha = (s: string) => createHash('sha256').update(s).digest('hex');

function stateOf(a: Attempt): Json {
    const now = Date.now();
    let st = a.state;
    if (st === 'writing' && a.locked) st = 'locked';
    if ((st === 'checked_in' || st === 'waiting') && now > deadlineMs(a) + 90000) st = 'ended';
    if (st === 'checked_in' && now >= a.startsAt - 3600000) st = 'waiting';
    if ((st === 'writing' || st === 'locked') && now > deadlineMs(a) + 90000) {
        a.state = 'submitted';
        a.submittedAt = now;
        a.receipt = 'DEMO-8-101-AUTO';
        st = 'submitted';
    }
    return {
        run: RUN, reg_id: 101, name: 'Asha K', hall_ticket: '12', school_name: "St. Mary's HSS", class_name: 'Class 8',
        title: 'Talent Search 2026 — Class 8', sahodaya_name: 'Demo Sahodaya', logo_url: null, state: st,
        server_time: iso(now), checkin_opens_at: iso(a.startsAt - 86400000), waiting_opens_at: iso(a.startsAt - 3600000), starts_at: iso(a.startsAt),
        late_entry_until: iso(a.startsAt + 15 * 60000), deadline: a.startedAt ? iso(deadlineMs(a)) : null, duration_minutes: DURATION_MIN, extra_minutes: a.extra,
        supervision: SUPERVISED ? 'supervised' : 'open', start_pin_required: SUPERVISED,
        bundle_url: `/api/bundle/${encodeURIComponent(RUN)}/${PAPER.version}`, bundle_version: PAPER.version,
        shuffle_questions: true, seed: 101, paper_key: a.startedAt ? KEY.toString('base64') : null,
        answers: a.answers, flagged: a.flagged, rev: a.rev, system_check: a.systemCheck, messages, next_sync_in: 10,
        show_score_on_submit: SHOW_SCORE, submitted_at: a.submittedAt ? iso(a.submittedAt) : null, receipt: a.receipt,
    };
}

function send(res: ServerResponse, status: number, body: unknown, headers: Record<string, string> = {}) {
    res.statusCode = status;
    res.setHeader('Content-Type', 'application/json');
    res.setHeader('Cache-Control', 'no-store');
    for (const [k, v] of Object.entries(headers)) res.setHeader(k, v);
    res.end(JSON.stringify(body));
}

const err = (res: ServerResponse, status: number, code: string, message: string, headers: Record<string, string> = {}) => send(res, status, { error: code, message }, headers);

async function readBody(req: IncomingMessage): Promise<Json> {
    const chunks: Buffer[] = [];
    for await (const c of req) chunks.push(c as Buffer);
    try {
        return JSON.parse(Buffer.concat(chunks).toString('utf8') || '{}');
    } catch {
        return {};
    }
}

function cookie(req: IncomingMessage): string | null {
    const m = /(?:^|;\s*)exam_session=([^;]+)/.exec(req.headers.cookie ?? '');
    return m && sessions.has(m[1]) ? m[1] : null;
}

function applyChanges(a: Attempt, changes: Record<string, unknown>) {
    for (const [k, v] of Object.entries(changes ?? {})) {
        if (v === null) delete a.answers[k];
        else a.answers[k] = v;
    }
}

function syncReply(a: Attempt, action: string, ok = true): Json {
    return { rev: a.rev, server_time: iso(Date.now()), deadline: a.startedAt ? iso(deadlineMs(a)) : null, next_sync_in: 10, messages, action, checksum_ok: ok };
}

async function handle(req: IncomingMessage, res: ServerResponse, next: () => void) {
    const url = new URL(req.url ?? '/', 'http://x');
    const path = url.pathname;

    if (path.startsWith('/__mock/')) {
        const a = (attempt ??= fresh());
        const what = path.slice(8);
        if (what === 'lock') a.locked = true;
        else if (what === 'unlock') a.locked = false;
        else if (what === 'broadcast') messages.push({ id: messages.length + 1, at: iso(Date.now()), text: url.searchParams.get('text') ?? 'Hello from the exam team.' });
        else if (what === 'extend') a.extra += Number(url.searchParams.get('minutes') ?? 5);
        else if (what === 'fail') failNext = Number(url.searchParams.get('count') ?? 3);
        else if (what === 'reset') attempt = fresh();
        else if (what === 'bump') a.rev += 1; // next client sync has a stale base_rev → resync
        else if (what === 'end') a.startsAt = Date.now() - DURATION_MIN * 60000 + 15000;
        return send(res, 200, { ok: true, attempt: a });
    }
    if (!path.startsWith('/api/')) return next();
    if (failNext > 0 && path !== '/api/bundle') {
        failNext--;
        return err(res, 503, 'busy', 'Busy (mock)', { 'Retry-After': '3' });
    }

    const method = req.method ?? 'GET';
    if (method !== 'GET' && req.headers['x-exam-client'] !== '1') return err(res, 403, 'forbidden', 'missing X-Exam-Client');

    if (method === 'GET' && path.startsWith('/api/sahodaya/')) {
        const code = decodeURIComponent(path.slice('/api/sahodaya/'.length)).toUpperCase();
        if (code === 'NONE') return err(res, 404, 'not_found', 'No exams');
        const a = (attempt ??= fresh());
        return send(res, 200, { code, name: `${code} Sahodaya (demo)`, logo_url: null, runs: [ { run: RUN, title: 'Talent Search 2026 — Class 8', starts_at: iso(a.startsAt), state: Date.now() >= a.startsAt ? 'live' : 'waiting' } ] });
    }
    if (method === 'GET' && path.startsWith('/api/bundle/')) {
        res.setHeader('Cache-Control', 'public, max-age=31536000, immutable');
        return send(res, 200, BUNDLE, { 'Cache-Control': 'public, max-age=31536000, immutable' });
    }
    if (method === 'POST' && path === '/api/login') {
        const body = await readBody(req);
        await new Promise((r) => setTimeout(r, 400));
        const user = String(body.username ?? '').toLowerCase();
        if (user === 'busy') return err(res, 503, 'busy', 'Login queue full', { 'Retry-After': '4' });
        if (user === 'locked') return err(res, 423, 'locked', 'Too many attempts', { 'Retry-After': '600' });
        if (user === 'conflict') return err(res, 409, 'device_conflict', 'Active on another device');
        const ok =
            (body.method === 'password' && body.password === 'demo') ||
            (body.method === 'passcode' && body.passcode === 'K7M2PQ9RTX') ||
            (body.method === 'launch' && body.token === 'demo');
        if (!ok) return err(res, 401, 'unauthorized', "Details don't match");
        const token = randomBytes(16).toString('hex');
        sessions.add(token);
        const a = (attempt ??= fresh());
        res.setHeader('Set-Cookie', `exam_session=${token}; Path=/; HttpOnly; SameSite=Lax`);
        return send(res, 200, { state: stateOf(a) });
    }

    const sid = cookie(req);
    if (!sid) return err(res, 401, 'unauthorized', 'Not signed in');
    const a = (attempt ??= fresh());

    if (method === 'GET' && path === '/api/state') return send(res, 200, stateOf(a));
    if (method === 'POST' && path === '/api/logout') {
        sessions.delete(sid);
        res.setHeader('Set-Cookie', 'exam_session=; Path=/; Max-Age=0');
        return send(res, 200, { ok: true });
    }
    if (method === 'POST' && path === '/api/system-check') {
        a.systemCheck = { ok: true, ...(await readBody(req)) };
        return send(res, 200, { ok: true });
    }
    if (method === 'POST' && path === '/api/start') {
        const body = await readBody(req);
        const now = Date.now();
        if (a.state === 'writing') return send(res, 200, stateOf(a));
        if (now < a.startsAt) return err(res, 409, 'not_open_yet', 'Not started');
        if (now > a.startsAt + 15 * 60000) return err(res, 409, 'closed', 'Late entry over');
        if (SUPERVISED && body.pin !== '1234') return err(res, 403, 'wrong_pin', 'Wrong PIN');
        a.state = 'writing';
        a.startedAt = now;
        return send(res, 200, stateOf(a));
    }
    if (method === 'POST' && (path === '/api/sync' || path === '/api/resync')) {
        const body = await readBody(req);
        const s = stateOf(a);
        if (s.state === 'submitted' || s.state === 'ended') return send(res, 200, syncReply(a, 'submit'));
        if (a.locked) return send(res, 200, syncReply(a, 'lock'));
        if (path === '/api/resync') {
            a.answers = (body.answers as Record<string, unknown>) ?? {};
            a.flagged = (body.flagged as string[]) ?? [];
            a.rev++;
            return send(res, 200, syncReply(a, 'none'));
        }
        if (Array.isArray(body.events) && body.events.length) a.events.push(...body.events);
        if (body.base_rev !== a.rev) return send(res, 200, syncReply(a, 'resync', false));
        const trial = { ...a.answers };
        for (const [k, v] of Object.entries((body.changes as Json) ?? {})) {
            if (v === null) delete trial[k];
            else trial[k] = v;
        }
        if (sha(canonicalAnswerString(trial as Record<string, string>)) !== body.checksum) return send(res, 200, syncReply(a, 'resync', false));
        applyChanges(a, body.changes as Json);
        a.flagged = (body.flagged as string[]) ?? a.flagged;
        a.rev++;
        return send(res, 200, syncReply(a, Date.now() > deadlineMs(a) ? 'submit' : 'none'));
    }
    if (method === 'POST' && path === '/api/submit') {
        const body = await readBody(req);
        if (!a.receipt) {
            if (body.base_rev === a.rev) applyChanges(a, body.changes as Json);
            a.state = 'submitted';
            a.submittedAt = Date.now();
            a.receipt = `DEMO-8-101-${randomBytes(2).toString('hex').toUpperCase()}`;
        }
        return send(res, 200, { submitted: true, submitted_at: iso(a.submittedAt as number), receipt: a.receipt });
    }
    if (method === 'GET' && path === '/api/result') {
        if (!SHOW_SCORE) return send(res, 200, { status: 'hidden' });
        if (!a.submittedAt || Date.now() - a.submittedAt < 12000) return send(res, 200, { status: 'pending' });
        return send(res, 200, { status: 'ready', score: 7.5, max_score: 11, percentage: 68.18, grade: 'B' });
    }
    return err(res, 404, 'not_found', 'No such endpoint (mock)');
}

export function mockApiPlugin(): Plugin {
    return {
        name: 'exam-mock-api',
        apply: 'serve',
        configureServer(server) {
            server.middlewares.use((req, res, next) => {
                handle(req, res, next).catch((e) => err(res, 500, 'server', String(e)));
            });
        },
    };
}
