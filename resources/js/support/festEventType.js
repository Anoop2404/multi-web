// 'kalolsavam' is the canonical event_type for a Kalotsav-type fest, but
// fest_events.event_type is free text (no DB enum — see
// database/migrations/tenant/2026_08_14_000002_widen_fest_event_type_columns.php)
// and a per-tenant migration normalizes legacy 'kalotsav'/'kalotsavam' rows to
// 'kalolsavam' — a migration that isn't guaranteed to have run on every tenant
// DB. Mirrors App\Models\FestEvent::scopeOfType()'s tolerant whereIn() so a
// bare `=== 'kalolsavam'` check here doesn't silently miss legacy-spelled rows.
const KALOLSAVAM_EVENT_TYPES = ['kalotsav', 'kalotsavam', 'kalolsavam'];

export function isKalolsavamEvent(event) {
    return KALOLSAVAM_EVENT_TYPES.includes(event?.event_type);
}
