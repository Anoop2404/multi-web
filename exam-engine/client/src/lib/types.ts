// Types for the student API (exam-engine/CONTRACT.md §2.3, §4). Keep in step with the contract.

export type QuestionType = 'single' | 'multiple' | 'true_false' | 'numeric';

/** single / true_false: option key; multiple: sorted keys; numeric: the typed string. */
export type AnswerValue = string | string[];
export type AnswerMap = Record<string, AnswerValue>;
/** A diff: `null` means "cleared". */
export type AnswerChanges = Record<string, AnswerValue | null>;

export type AttemptState = 'checked_in' | 'waiting' | 'writing' | 'submitted' | 'locked' | 'ended';
export type RunState = 'armed' | 'checkin' | 'waiting' | 'live' | 'ended' | 'closed';
export type SyncAction = 'none' | 'resync' | 'submit' | 'lock';
export type ProctorEventType = 'tab_hidden' | 'window_blur' | 'fullscreen_exit' | 'offline';

export interface BroadcastMessage {
    id: number;
    at: string;
    text: string;
}

export interface ProctorEvent {
    type: ProctorEventType;
    at: string;
    seconds?: number;
}

export interface SahodayaRun {
    run: string;
    title: string;
    starts_at: string;
    state: RunState;
    /** Not in contract v1; used only when the engine sends it (see NOTES-client.md). */
    entry_mode?: 'either' | 'portal_password' | 'exam_passcode';
}

export interface SahodayaInfo {
    code: string;
    name: string;
    logo_url: string | null;
    runs: SahodayaRun[];
}

export interface ExamState {
    run: string;
    reg_id: number;
    name: string;
    hall_ticket: string;
    school_name: string;
    class_name: string;
    title: string;
    sahodaya_name: string;
    logo_url: string | null;
    state: AttemptState;
    server_time: string;
    checkin_opens_at: string;
    waiting_opens_at: string;
    starts_at: string;
    late_entry_until: string;
    deadline: string | null;
    duration_minutes: number;
    extra_minutes: number;
    supervision: 'open' | 'supervised';
    start_pin_required: boolean;
    bundle_url: string;
    bundle_version: number;
    shuffle_questions: boolean;
    seed: number;
    paper_key: string | null;
    answers: AnswerMap;
    flagged: string[];
    rev: number;
    system_check: { ok: boolean } | null;
    messages: BroadcastMessage[];
    next_sync_in: number;
    show_score_on_submit: boolean;
    submitted_at: string | null;
    receipt: string | null;
    /** Optional (not in contract v1); the client assumes 90 when absent. */
    grace_seconds?: number;
    /** Optional (not in contract v1); the client assumes 30 when absent. */
    heartbeat_seconds?: number;
}

export interface SyncRequest {
    base_rev: number;
    changes: AnswerChanges;
    flagged: string[];
    checksum: string;
    events: ProctorEvent[];
}

export interface ResyncRequest {
    answers: AnswerMap;
    flagged: string[];
}

export interface SyncResponse {
    rev: number;
    server_time: string;
    deadline: string | null;
    next_sync_in: number;
    messages: BroadcastMessage[];
    action: SyncAction;
    checksum_ok: boolean;
}

export interface SubmitRequest {
    base_rev: number;
    changes: AnswerChanges;
    checksum: string;
}

export interface SubmitResponse {
    submitted: boolean;
    submitted_at: string;
    receipt: string;
}

export interface ResultResponse {
    status: 'hidden' | 'pending' | 'ready';
    score?: number;
    max_score?: number;
    percentage?: number;
    grade?: string | null;
}

export interface SystemCheckRequest {
    user_agent: string;
    screen: string;
    online: boolean;
    latency_ms: number;
    storage_ok: boolean;
    crypto_ok: boolean;
}

export interface BundleEnvelope {
    version: number;
    iv: string;
    ciphertext: string;
}

export interface PaperOption {
    key: string;
    label: string;
    image: string | null;
}

export interface PaperQuestion {
    id: number;
    type: QuestionType;
    section: string | null;
    body: string;
    image: string | null;
    options: PaperOption[];
    show_count: number | null;
    marks: number;
    negative_mark: number;
}

export interface Paper {
    version: number;
    questions: PaperQuestion[];
}

export type LoginRequest =
    | { sahodaya: string; method: 'password'; username: string; password: string }
    | { sahodaya: string; method: 'passcode'; hall_ticket: string; passcode: string }
    | { method: 'launch'; token: string };
