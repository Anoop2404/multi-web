<?php

namespace App\Support;

/**
 * Column/filter metadata and runnable registry for ERP hub reports.
 */
class ErpReportMeta
{
    /** @return list<string> */
    public static function excludedIds(): array
    {
        return ['RPT-SPT-021', 'RPT-STU-015'];
    }

    /** @return array<string, string> */
    public static function aliases(): array
    {
        return [
            'RPT-STU-009' => 'RPT-AUTH-001',
            'RPT-TCH-010' => 'RPT-AUTH-002',
            'RPT-PAY-007' => 'RPT-PAY-005',
            'RPT-SPT-016' => 'RPT-SPT-017',
            'RPT-SPT-035' => 'RPT-SPT-017',
            'RPT-SPT-044' => 'RPT-SPT-043',
            'RPT-KAL-014' => 'RPT-KAL-010',
            'RPT-KAL-018' => 'RPT-SPT-005',
            'RPT-KAL-027' => 'RPT-SPT-005',
        ];
    }

    public static function resolveId(string $reportId): string
    {
        return self::aliases()[$reportId] ?? $reportId;
    }

    /** @return 'sahodaya'|'event'|'cross_event' */
    public static function scope(string $reportId): string
    {
        $id = self::resolveId($reportId);

        // RPT-FST-008/009 (functional audit 2026-08-11/12, action-plan items 12/13):
        // phase-duration/bottleneck and region-performance-comparison are both
        // deliberately cross_event — they compare ACROSS events/regions rather than
        // reporting on one pre-selected event, so (like RPT-FST-001..004) they belong
        // in the general Reports Hub, not gated behind picking an event first. IDs
        // 006/007 were already taken by existing catalog-only entries (see
        // ReportRegistry::festHubReports()), hence starting at 008.
        //
        // RPT-KAL-036..042/045 (same audit, "fix stub reports" item): these used to
        // resolve through a generic per-event fallback and were scope=event (gated out
        // of this hub entirely, so genuinely unreachable). Now that
        // FestCrossEventReportService gives each one real, distinct query logic (see
        // that class), they're promoted to cross_event so the fix is actually usable
        // from the Reports Hub rather than correct-but-unreachable. 043/044 stay
        // scope=event on purpose — they're documented, intentional duplicates of
        // RPT-KAL-005 pending post-UAT consolidation, not reports that need their own
        // hub card.
        if (in_array($id, [
            'RPT-SPT-024', 'RPT-KAL-015', 'RPT-FST-001', 'RPT-FST-002', 'RPT-FST-003', 'RPT-FST-004', 'RPT-FST-008', 'RPT-FST-009',
            'RPT-KAL-036', 'RPT-KAL-037', 'RPT-KAL-038', 'RPT-KAL-039', 'RPT-KAL-040', 'RPT-KAL-041', 'RPT-KAL-042', 'RPT-KAL-045',
        ], true)) {
            return 'cross_event';
        }

        if (str_starts_with($id, 'RPT-SPT-') || str_starts_with($id, 'RPT-KAL-') || str_starts_with($id, 'RPT-FST-')) {
            return 'event';
        }

        return 'sahodaya';
    }

    public static function isRunnable(string $reportId): bool
    {
        $id = self::resolveId($reportId);

        if (in_array($id, self::excludedIds(), true)) {
            return false;
        }

        if (! array_key_exists($id, self::definitions())) {
            return false;
        }

        return in_array(self::scope($reportId), ['sahodaya', 'cross_event'], true);
    }

    /** @return list<string> */
    public static function runnableIds(): array
    {
        return array_values(array_filter(
            array_keys(self::definitions()),
            fn (string $id) => self::isRunnable($id),
        ));
    }

    /** @return array{columns: list<array{key: string, label: string}>, filters: list<array{key: string, label: string, type: string}>} */
    public static function meta(string $reportId): array
    {
        $id = self::resolveId($reportId);
        $def = self::definitions()[$id] ?? null;

        if (! $def) {
            return ['columns' => [], 'filters' => []];
        }

        return self::buildMeta($def['columns'], $def['filters'] ?? []);
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $filterKeys
     * @return array{columns: list<array{key: string, label: string}>, filters: list<array{key: string, label: string, type: string}>}
     */
    public static function buildMeta(array $columns, array $filterKeys = []): array
    {
        $builtColumns = array_map(fn (string $key) => [
            'key'   => $key,
            'label' => ucwords(str_replace('_', ' ', $key)),
        ], $columns);

        $filters = array_map(fn (string $key) => [
            'key'   => $key,
            'label' => ucwords(str_replace('_', ' ', $key)),
            'type'  => self::filterType($key),
        ], $filterKeys);

        return ['columns' => $builtColumns, 'filters' => $filters];
    }

    private static function filterType(string $key): string
    {
        return match ($key) {
            'from', 'to', 'date_from', 'date_to' => 'date',
            'event_id', 'school_id', 'head_id', 'exam_id', 'class' => 'select',
            default => 'text',
        };
    }

    /** @return array<string, array{columns: list<string>, filters?: list<string>}> */
    public static function definitions(): array
    {
        static $defs = null;

        if ($defs !== null) {
            return $defs;
        }

        $festCols = ['event', 'school', 'item', 'head', 'participants', 'status'];
        $festFilters = ['event_id', 'school_id'];

        $defs = [
            // Schools
            'RPT-SCH-001' => ['columns' => ['school', 'membership_status', 'payment_status', 'students', 'classes', 'joined']],
            'RPT-SCH-002' => ['columns' => ['school', 'membership_status', 'registration_status', 'payment_status']],
            'RPT-SCH-003' => ['columns' => ['school', 'student_count', 'active_count']],
            'RPT-SCH-004' => ['columns' => ['school', 'teacher_count']],
            'RPT-SCH-005' => ['columns' => ['school', 'user', 'role', 'last_login_at', 'email'], 'filters' => ['from', 'to']],
            'RPT-SCH-006' => ['columns' => ['school', 'action', 'description', 'user', 'created_at'], 'filters' => ['from', 'to']],
            'RPT-SCH-007' => ['columns' => ['school', 'required_types', 'approved', 'pending', 'expired', 'compliance']],
            'RPT-SCH-008' => ['columns' => ['school', 'status', 'submitted_at', 'contact_email', 'phone']],
            'RPT-SCH-009' => ['columns' => ['cluster', 'schools', 'students', 'teachers']],
            'RPT-SCH-010' => ['columns' => ['name', 'designation', 'school', 'email', 'phone']],
            'RPT-SCH-011' => ['columns' => ['school', 'coordinator_name', 'coordinator_email', 'coordinator_phone', 'portal_user']],
            'RPT-SCH-012' => ['columns' => ['school', 'code', 'affiliation', 'email', 'phone', 'students', 'classes', 'joined']],
            'RPT-SCH-013' => ['columns' => ['school', 'membership_status', 'is_active', 'last_activity']],
            'RPT-SCH-014' => ['columns' => ['school', 'code', 'email', 'phone', 'address', 'principal', 'coordinator']],
            'RPT-SCH-015' => ['columns' => ['school', 'academic_year', 'records_status', 'counts_status', 'teacher_status']],

            // Students
            'RPT-STU-001' => ['columns' => ['school', 'name', 'reg_no', 'class', 'gender', 'status'], 'filters' => ['school_id']],
            'RPT-STU-002' => ['columns' => ['school', 'gender', 'count']],
            'RPT-STU-003' => ['columns' => ['school', 'class', 'count']],
            'RPT-STU-004' => ['columns' => ['school', 'category', 'count']],
            'RPT-STU-005' => ['columns' => ['school', 'student', 'reg_no', 'class', 'dob', 'age', 'age_category']],
            'RPT-STU-006' => ['columns' => ['school', 'name', 'reg_no', 'class', 'status'], 'filters' => ['school_id']],
            'RPT-STU-007' => ['columns' => ['school', 'name', 'reg_no', 'verified_at'], 'filters' => ['school_id']],
            'RPT-STU-008' => ['columns' => ['school', 'name', 'reg_no', 'status'], 'filters' => ['school_id']],
            'RPT-STU-010' => ['columns' => ['school', 'date', 'imported', 'skipped', 'errors', 'description']],
            'RPT-STU-011' => ['columns' => ['school', 'name', 'reg_no', 'class', 'status'], 'filters' => ['school_id']],
            'RPT-STU-012' => ['columns' => ['school', 'admission_number', 'count', 'students']],
            'RPT-STU-013' => ['columns' => ['month', 'school', 'count'], 'filters' => ['from', 'to']],
            'RPT-STU-014' => ['columns' => ['school', 'name', 'batch_year', 'email', 'organisation', 'approved']],
            'RPT-STU-015' => ['columns' => ['school', 'student', 'class', 'status', 'requested_at', 'issued_date']],

            // Teachers
            'RPT-TCH-001' => ['columns' => ['school', 'name', 'reg_no', 'designation', 'email', 'status'], 'filters' => ['school_id']],
            'RPT-TCH-002' => ['columns' => ['teaching_type', 'count']],
            'RPT-TCH-003' => ['columns' => ['subject', 'count']],
            'RPT-TCH-004' => ['columns' => ['school', 'count']],
            'RPT-TCH-005' => ['columns' => ['teacher', 'school', 'program', 'status', 'completed_at']],
            'RPT-TCH-006' => ['columns' => ['school', 'name', 'reg_no', 'status'], 'filters' => ['school_id']],
            'RPT-TCH-007' => ['columns' => ['school', 'name', 'reg_no', 'verified_at'], 'filters' => ['school_id']],
            'RPT-TCH-008' => ['columns' => ['qualification', 'count']],
            'RPT-TCH-009' => ['columns' => ['experience_band', 'count']],
            'RPT-TCH-012' => ['columns' => ['school', 'name', 'reg_no', 'mobile', 'status'], 'filters' => ['school_id']],

            // Payments
            'RPT-PAY-001' => ['columns' => ['metric', 'value']],
            'RPT-PAY-002' => ['columns' => ['school', 'amount', 'status', 'submitted_at']],
            'RPT-PAY-003' => ['columns' => ['school', 'renewal_status', 'membership_status']],
            'RPT-PAY-004' => ['columns' => ['school', 'academic_year', 'amount', 'verified_at']],
            'RPT-PAY-005' => ['columns' => ['school', 'academic_year', 'amount', 'status', 'method', 'verified_at'], 'filters' => ['status']],
            'RPT-PAY-006' => ['columns' => ['school', 'source', 'program', 'amount', 'status', 'updated_at'], 'filters' => ['status']],
            'RPT-PAY-008' => ['columns' => ['school', 'module', 'amount', 'reason', 'rejected_at']],
            'RPT-PAY-009' => ['columns' => ['receipt_number', 'school', 'email_status', 'emailed_at', 'error', 'resend_count']],
            'RPT-PAY-010' => ['columns' => ['school', 'module', 'program', 'amount', 'status', 'submitted_at'], 'filters' => ['status']],
            'RPT-PAY-011' => ['columns' => ['school', 'module', 'amount', 'status', 'submitted_at']],
            'RPT-PAY-012' => ['columns' => ['school', 'membership', 'fest', 'mcq', 'training', 'total']],
            'RPT-PAY-013' => ['columns' => ['school', 'academic_year', 'amount', 'base_amount', 'late_fee', 'verified_at'], 'filters' => ['academic_year']],
            'RPT-PAY-014' => ['columns' => ['receipt_number', 'school', 'amount', 'waiver_amount', 'waiver_reason', 'waived_at']],
            'RPT-PAY-015' => ['columns' => ['school', 'source', 'amount', 'due_date', 'status']],
            'RPT-PAY-016' => ['columns' => ['receipt_number', 'school', 'resend_count', 'last_emailed_at', 'status']],
            'RPT-PAY-017' => ['columns' => ['school', 'academic_year', 'payment_status', 'registration_status', 'verified_at']],
            'RPT-PAY-018' => ['columns' => ['academic_year', 'min_students', 'max_students', 'amount']],
            'RPT-PAY-019' => ['columns' => ['date', 'membership', 'fest', 'mcq', 'training', 'total'], 'filters' => ['from', 'to']],
            'RPT-PAY-020' => ['columns' => ['module', 'count', 'amount', 'pct']],

            // Finance
            'RPT-FIN-001' => ['columns' => ['date', 'voucher', 'account', 'debit', 'credit', 'narration'], 'filters' => ['from', 'to']],
            'RPT-FIN-002' => ['columns' => ['date', 'voucher', 'debit', 'credit', 'balance'], 'filters' => ['from', 'to']],
            'RPT-FIN-003' => ['columns' => ['date', 'voucher', 'debit', 'credit', 'balance'], 'filters' => ['from', 'to']],
            'RPT-FIN-004' => ['columns' => ['date', 'voucher', 'debit', 'credit', 'balance'], 'filters' => ['from', 'to']],
            'RPT-FIN-005' => ['columns' => ['code', 'account', 'type', 'opening', 'debit', 'credit', 'balance']],
            'RPT-FIN-006' => ['columns' => ['category', 'amount']],
            'RPT-FIN-007' => ['columns' => ['section', 'amount']],
            'RPT-FIN-008' => ['columns' => ['receipt_number', 'date', 'reviewed_at', 'reviewed_by', 'school', 'amount', 'status']],
            'RPT-FIN-009' => ['columns' => ['voucher', 'date', 'payee', 'amount', 'status']],
            'RPT-FIN-010' => ['columns' => ['school', 'source', 'amount', 'status']],
            'RPT-FIN-011' => ['columns' => ['school', 'source', 'program', 'amount', 'status', 'updated_at'], 'filters' => ['status']],
            'RPT-FIN-012' => ['columns' => ['module', 'collected', 'pending']],
            'RPT-FIN-013' => ['columns' => ['event', 'collected', 'pending', 'schools_paid']],
            'RPT-FIN-014' => ['columns' => ['school', 'collected', 'pending']],
            'RPT-FIN-015' => ['columns' => ['month', 'income', 'expense', 'net']],
            'RPT-FIN-016' => ['columns' => ['account', 'category', 'amount'], 'filters' => ['from', 'to']],
            'RPT-FIN-017' => ['columns' => ['cost_center', 'debit', 'credit', 'net']],
            'RPT-FIN-018' => ['columns' => ['bank_account', 'statement_date', 'status', 'matched', 'unmatched']],
            'RPT-FIN-019' => ['columns' => ['account', 'opening_balance', 'financial_year']],
            'RPT-FIN-020' => ['columns' => ['voucher_no', 'date', 'type', 'amount', 'narration'], 'filters' => ['from', 'to']],
            'RPT-FIN-021' => ['columns' => ['issue', 'receipt_id', 'receipt_status', 'receipt_amount', 'ledger_amount', 'feeable_type', 'feeable_id', 'payment_date']],

            // Fest hub
            'RPT-FST-001' => ['columns' => ['event', 'type', 'status', 'starts', 'registrations'], 'filters' => ['event_id']],
            'RPT-FST-002' => ['columns' => ['event', 'school', 'amount', 'status'], 'filters' => ['event_id', 'school_id']],
            'RPT-FST-003' => ['columns' => ['event', 'school', 'item', 'status', 'created_at'], 'filters' => ['event_id', 'school_id']],
            'RPT-FST-004' => ['columns' => ['event', 'school', 'participant', 'cert_type', 'generated_at'], 'filters' => ['event_id']],
            'RPT-FST-005' => ['columns' => ['event', 'type', 'status', 'export_count_note']],
            // LIFE audit action-plan item 12: FestEventPhase tracks current status but no
            // history of when it entered that status — this reports "how long has this
            // phase been sitting in its current (non-completed) status", a live-bottleneck
            // view, not full historical duration analytics (that would need a phase
            // status-transition log this app doesn't have).
            'RPT-FST-008' => ['columns' => ['event', 'phase', 'status', 'items_assigned', 'hours_in_current_status'], 'filters' => ['event_id']],
            // Action-plan item 13: side-by-side region comparison for partitioned hubs —
            // distinct from the existing per-event region tiles (which show one hub at a
            // time inside its own workspace).
            'RPT-FST-009' => ['columns' => ['event', 'region', 'schools_participating', 'registrations', 'total_points', 'avg_points_per_school'], 'filters' => ['event_id']],

            // Sports (cross-event summaries)
            'RPT-SPT-001' => ['columns' => $festCols, 'filters' => $festFilters],
            'RPT-SPT-002' => ['columns' => ['event', 'school', 'registrations', 'approved', 'pending'], 'filters' => ['event_id']],
            'RPT-SPT-003' => ['columns' => ['event', 'school', 'amount', 'status'], 'filters' => ['event_id']],
            'RPT-SPT-004' => ['columns' => ['event', 'item', 'stage', 'scheduled_at'], 'filters' => ['event_id', 'from', 'to']],
            'RPT-SPT-005' => ['columns' => ['event', 'school', 'item_a', 'item_b', 'conflict_at'], 'filters' => ['event_id']],
            'RPT-SPT-006' => ['columns' => ['event', 'school', 'student', 'chest_no', 'item'], 'filters' => $festFilters],
            'RPT-SPT-007' => ['columns' => ['event', 'item', 'school', 'participants', 'present'], 'filters' => $festFilters],
            'RPT-SPT-008' => ['columns' => ['event', 'item', 'school', 'participant', 'mark', 'rank'], 'filters' => $festFilters],
            'RPT-SPT-009' => ['columns' => ['event', 'school', 'points', 'rank'], 'filters' => ['event_id']],
            'RPT-SPT-010' => ['columns' => ['event', 'school', 'points', 'rank'], 'filters' => ['event_id']],
            'RPT-SPT-011' => ['columns' => ['event', 'item', 'record', 'holder', 'value']],
            'RPT-SPT-012' => ['columns' => ['event', 'school', 'item', 'reason', 'status']],
            'RPT-SPT-013' => ['columns' => ['event', 'school', 'student', 'item', 'reason', 'created_at'], 'filters' => $festFilters],
            'RPT-SPT-014' => ['columns' => ['event', 'item', 'school', 'participant', 'status'], 'filters' => $festFilters],
            'RPT-SPT-015' => ['columns' => ['event', 'head', 'participants', 'schools'], 'filters' => ['event_id', 'head_id']],
            'RPT-SPT-017' => ['columns' => ['event', 'class_group', 'participants'], 'filters' => ['event_id']],
            'RPT-SPT-018' => ['columns' => ['event', 'age_group', 'school', 'count'], 'filters' => ['event_id']],
            'RPT-SPT-019' => ['columns' => ['event', 'school', 'item', 'points'], 'filters' => $festFilters],
            'RPT-SPT-020' => ['columns' => ['event', 'school', 'gold', 'silver', 'bronze', 'total'], 'filters' => ['event_id']],
            'RPT-SPT-022' => ['columns' => ['event', 'official', 'role', 'item', 'school']],
            'RPT-SPT-023' => ['columns' => ['event', 'stage', 'slots', 'hours'], 'filters' => ['event_id']],
            'RPT-SPT-024' => ['columns' => ['event', 'type', 'status', 'registration_opens', 'registration_closes', 'registrations'], 'filters' => ['event_id']],
            'RPT-SPT-025' => ['columns' => ['event', 'free_items', 'paid_items', 'total_fee'], 'filters' => ['event_id']],
            'RPT-SPT-026' => ['columns' => ['event', 'item', 'marks_entered', 'total', 'pct'], 'filters' => ['event_id']],
            'RPT-SPT-027' => ['columns' => ['event', 'item', 'published', 'pending'], 'filters' => ['event_id']],
            'RPT-SPT-028' => ['columns' => ['event', 'school', 'item', 'status', 'outcome']],
            'RPT-SPT-029' => ['columns' => ['event', 'school', 'participant', 'cert_type', 'generated_at']],
            'RPT-SPT-030' => ['columns' => ['event', 'school', 'participant', 'id_type', 'issued_at']],
            'RPT-SPT-031' => ['columns' => ['event', 'level', 'schools', 'registrations']],
            'RPT-SPT-032' => ['columns' => ['event', 'cluster', 'schools', 'points']],
            'RPT-SPT-033' => ['columns' => ['event', 'item', 'participant', 'school', 'mark', 'rank'], 'filters' => $festFilters],
            'RPT-SPT-034' => ['columns' => ['event', 'school', 'rank', 'points'], 'filters' => ['event_id']],
            'RPT-SPT-036' => ['columns' => ['event', 'date', 'item', 'stage', 'time'], 'filters' => ['event_id', 'from']],
            // Always empty — see FestCrossEventReportService's doc on RPT-SPT-037; no
            // gate-scanning feature exists in this app yet.
            'RPT-SPT-037' => ['columns' => ['event', 'participant', 'school', 'scanned_at'], 'filters' => ['event_id']],
            'RPT-SPT-038' => ['columns' => ['event', 'school', 'orders', 'amount'], 'filters' => ['event_id']],
            'RPT-SPT-039' => ['columns' => ['event', 'item', 'fee_amount', 'participant_type'], 'filters' => ['event_id']],
            'RPT-SPT-040' => ['columns' => ['event', 'school', 'item', 'status', 'submitted_at'], 'filters' => $festFilters],
            'RPT-SPT-041' => ['columns' => ['event', 'item', 'verified', 'pending'], 'filters' => ['event_id']],
            'RPT-SPT-042' => ['columns' => ['event', 'item', 'participant', 'old_record', 'new_record', 'broken_at']],
            'RPT-SPT-043' => ['columns' => ['event', 'school', 'item', 'participants'], 'filters' => $festFilters],
            'RPT-SPT-045' => ['columns' => ['event', 'item', 'school', 'participant', 'mark', 'rank'], 'filters' => ['event_id']],

            // Kalotsav (mirror sports where applicable)
            'RPT-KAL-001' => ['columns' => $festCols, 'filters' => $festFilters],
            'RPT-KAL-002' => ['columns' => ['event', 'school', 'registrations', 'approved'], 'filters' => ['event_id']],
            'RPT-KAL-003' => ['columns' => ['event', 'judge', 'item', 'school']],
            'RPT-KAL-004' => ['columns' => ['event', 'item', 'participant', 'mark', 'rank'], 'filters' => $festFilters],
            'RPT-KAL-005' => ['columns' => ['event', 'item', 'participants', 'avg_mark'], 'filters' => ['event_id']],
            'RPT-KAL-006' => ['columns' => ['event', 'item', 'rank', 'participant', 'school', 'mark'], 'filters' => $festFilters],
            'RPT-KAL-007' => ['columns' => ['event', 'school', 'points', 'rank'], 'filters' => ['event_id']],
            'RPT-KAL-008' => ['columns' => ['event', 'school', 'item', 'status', 'outcome']],
            'RPT-KAL-009' => ['columns' => ['event', 'item', 'stage', 'scheduled_at'], 'filters' => ['event_id']],
            'RPT-KAL-010' => ['columns' => ['event', 'item', 'school', 'participants'], 'filters' => $festFilters],
            'RPT-KAL-011' => ['columns' => ['event', 'school', 'amount', 'status'], 'filters' => ['event_id']],
            'RPT-KAL-012' => ['columns' => ['event', 'item', 'published', 'pending'], 'filters' => ['event_id']],
            'RPT-KAL-013' => ['columns' => ['event', 'school', 'participant', 'cert_type', 'generated_at']],
            'RPT-KAL-015' => ['columns' => ['event', 'type', 'status', 'registration_opens', 'registration_closes', 'registrations'], 'filters' => ['event_id']],
            'RPT-KAL-016' => ['columns' => ['event', 'item', 'judge', 'participant', 'score'], 'filters' => $festFilters],
            'RPT-KAL-017' => ['columns' => ['event', 'item', 'participant', 'avg_score', 'rank'], 'filters' => $festFilters],
            'RPT-KAL-019' => ['columns' => ['category', 'items', 'enabled']],
            'RPT-KAL-020' => ['columns' => ['event', 'gender', 'participants'], 'filters' => ['event_id']],
            'RPT-KAL-021' => ['columns' => ['event', 'school', 'item', 'points'], 'filters' => $festFilters],
            'RPT-KAL-022' => ['columns' => ['event', 'school', 'rank', 'points'], 'filters' => ['event_id']],
            'RPT-KAL-023' => ['columns' => ['event', 'head', 'participants'], 'filters' => ['event_id', 'head_id']],
            'RPT-KAL-024' => ['columns' => ['event', 'item', 'marks_entered', 'total'], 'filters' => ['event_id']],
            'RPT-KAL-025' => ['columns' => ['event', 'item', 'published_at', 'published_by']],
            'RPT-KAL-026' => ['columns' => ['event', 'school', 'participant', 'reg_no'], 'filters' => $festFilters],
            'RPT-KAL-028' => ['columns' => ['event', 'school', 'item', 'reason', 'status']],
            'RPT-KAL-029' => ['columns' => ['program', 'schools', 'status', 'updated_at']],
            'RPT-KAL-030' => ['columns' => ['event', 'action', 'items', 'status', 'synced_at']],
            'RPT-KAL-031' => ['columns' => ['code', 'title', 'category', 'participant_type', 'fee']],
            'RPT-KAL-032' => ['columns' => ['judge', 'event', 'last_login_at', 'assignments']],
            'RPT-KAL-033' => ['columns' => ['event', 'school', 'item', 'status', 'outcome', 'resolved_at']],
            'RPT-KAL-034' => ['columns' => ['event', 'school', 'participant', 'cert_type', 'generated_at']],
            'RPT-KAL-035' => ['columns' => ['event', 'school', 'participant', 'cert_type', 'generated_at']],
            // RPT-KAL-036..045 (functional audit 2026-08-11/12, "fix stub reports" item):
            // real column shapes matching FestCrossEventReportService's actual dispatch
            // (kalRowsBySuffix()) — the previous defs here didn't even match what
            // eventMetrics() actually returned (['event','metric','value']), let alone a
            // real report. See docs/erp/REPORT_CATALOGUE.md for each label's origin.
            'RPT-KAL-036' => ['columns' => ['event', 'school', 'amount', 'status'], 'filters' => ['event_id']], // Fee pending schools
            'RPT-KAL-037' => ['columns' => ['event', 'school', 'item', 'status', 'submitted_at'], 'filters' => ['event_id']], // Registration approval queue
            'RPT-KAL-038' => ['columns' => ['event', 'item', 'max_per_school', 'registered_schools', 'registrations', 'utilization_pct'], 'filters' => ['event_id']], // Item capacity utilization
            'RPT-KAL-039' => ['columns' => ['event', 'stage', 'slots', 'hours'], 'filters' => ['event_id']], // Time slot occupancy
            'RPT-KAL-040' => ['columns' => ['event', 'item', 'school', 'participant', 'mark', 'rank'], 'filters' => $festFilters], // School trophy detail (alias of school points detail)
            'RPT-KAL-041' => ['columns' => ['event', 'item', 'grade', 'count'], 'filters' => ['event_id']], // Grade-wise results
            'RPT-KAL-042' => ['columns' => ['event', 'item', 'school', 'participant', 'mark', 'rank'], 'filters' => $festFilters], // Public result export
            'RPT-KAL-043' => ['columns' => ['event', 'item', 'participants', 'avg_mark'], 'filters' => ['event_id']], // Legacy tabulation alias A (= RPT-KAL-005)
            'RPT-KAL-044' => ['columns' => ['event', 'item', 'participants', 'avg_mark'], 'filters' => ['event_id']], // Legacy tabulation alias B (= RPT-KAL-005)
            'RPT-KAL-045' => ['columns' => ['event', 'school', 'item', 'participants', 'status'], 'filters' => ['event_id']], // Full fest registration dump

            // MCQ
            'RPT-MCQ-001' => ['columns' => ['exam', 'school', 'registered', 'approved'], 'filters' => ['exam_id']],
            'RPT-MCQ-002' => ['columns' => ['exam', 'tier', 'registered'], 'filters' => ['exam_id']],
            'RPT-MCQ-003' => ['columns' => ['exam', 'school', 'amount', 'status'], 'filters' => ['exam_id']],
            'RPT-MCQ-004' => ['columns' => ['exam', 'student', 'school', 'hall_ticket_no'], 'filters' => ['exam_id']],
            'RPT-MCQ-005' => ['columns' => ['exam', 'student', 'school', 'attendance_status'], 'filters' => ['exam_id']],
            'RPT-MCQ-006' => ['columns' => ['exam', 'student', 'school', 'started_at', 'submitted_at', 'duration_minutes', 'status'], 'filters' => ['exam_id']],
            'RPT-MCQ-007' => ['columns' => ['exam', 'tier', 'student', 'school', 'score', 'rank'], 'filters' => ['exam_id']],
            'RPT-MCQ-008' => ['columns' => ['exam', 'rank', 'student', 'school', 'score'], 'filters' => ['exam_id']],
            'RPT-MCQ-009' => ['columns' => ['exam', 'question', 'attempts', 'correct_pct'], 'filters' => ['exam_id']],
            'RPT-MCQ-010' => ['columns' => ['exam', 'student', 'school', 'status'], 'filters' => ['exam_id']],
            'RPT-MCQ-011' => ['columns' => ['school', 'exam', 'registered', 'present', 'examined', 'avg_score', 'pass_rate', 'top_10', 'top_50', 'ranked'], 'filters' => ['exam_id']],
            'RPT-MCQ-012' => ['columns' => ['exam', 'tier', 'cutoff_score', 'promoted_count'], 'filters' => ['exam_id']],
            'RPT-MCQ-013' => ['columns' => ['exam', 'student', 'school', 'action', 'ip_address', 'created_at'], 'filters' => ['exam_id']],
            'RPT-MCQ-014' => ['columns' => ['bank', 'questions', 'created_at']],
            'RPT-MCQ-015' => ['columns' => ['exam', 'status', 'registration_opens', 'registration_closes', 'result_date', 'registered']],
            'RPT-MCQ-016' => ['columns' => ['exam', 'examined', 'pass_rate', 'mean_score', 'median_score'], 'filters' => ['exam_id']],
            'RPT-MCQ-017' => ['columns' => ['hall_ticket', 'participant', 'school', 'status', 'note'], 'filters' => ['exam_id']],

            // Training
            'RPT-TRN-001' => ['columns' => ['program', 'status', 'capacity', 'enrolled', 'fee']],
            'RPT-TRN-002' => ['columns' => ['program', 'school', 'nominations', 'confirmed']],
            'RPT-TRN-003' => ['columns' => ['program', 'teacher', 'school', 'reason', 'status', 'created_at']],
            'RPT-TRN-004' => ['columns' => ['program', 'school', 'teacher', 'amount', 'status']],
            'RPT-TRN-005' => ['columns' => ['program', 'teacher', 'school', 'attendance_status']],
            'RPT-TRN-006' => ['columns' => ['program', 'teacher', 'school', 'certificate_issued_at']],
            'RPT-TRN-007' => ['columns' => ['teacher', 'school', 'program', 'status', 'hours', 'year']],
            'RPT-TRN-008' => ['columns' => ['program', 'registrations', 'feedback_submitted', 'avg_rating', 'completed', 'completion_pct']],
            'RPT-TRN-009' => ['columns' => ['program', 'capacity', 'enrolled', 'utilization_pct']],
            'RPT-TRN-010' => ['columns' => ['school', 'program', 'teacher', 'amount', 'status']],
            'RPT-TRN-011' => ['columns' => ['program', 'teacher', 'school', 'status', 'submitted_at']],
            'RPT-TRN-012' => ['columns' => ['program', 'resource_person', 'role', 'sessions', 'honorarium', 'status']],
            'RPT-TRN-013' => ['columns' => ['school', 'teachers', 'hours', 'sessions_present', 'year']],
            'RPT-TRN-014' => ['columns' => ['program', 'teacher_type', 'participants', 'confirmed', 'waitlisted']],
            'RPT-TRN-015' => ['columns' => ['program', 'subject', 'participants', 'confirmed']],
            'RPT-TRN-016' => ['columns' => ['program', 'session', 'date', 'present', 'late', 'absent', 'with_permission']],

            // Board results (FRD-21)
            'RPT-BRD-001' => ['columns' => ['school', 'class', 'examination_type', 'academic_year', 'appeared', 'passed', 'pass_percent', 'distinctions', 'highest_mark', 'status'], 'filters' => ['academic_year', 'class']],
            'RPT-BRD-002' => ['columns' => ['rank', 'school', 'class', 'examination_type', 'score', 'pass_percent', 'scope'], 'filters' => ['academic_year', 'class']],
            'RPT-BRD-003' => ['columns' => ['school', 'class', 'examination_type', 'academic_year', 'pass_percent', 'appeared', 'passed'], 'filters' => ['academic_year', 'class']],
            'RPT-BRD-004' => ['columns' => ['rank', 'student', 'school', 'admission_no', 'roll_no', 'percentage', 'marks_obtained', 'total_marks'], 'filters' => ['academic_year']],
            'RPT-BRD-005' => ['columns' => ['stream', 'rank', 'student', 'school', 'percentage', 'admission_no', 'roll_no'], 'filters' => ['academic_year']],

            // Email
            'RPT-EML-001' => ['columns' => ['recipient', 'template_key', 'status', 'sent_at', 'error'], 'filters' => ['from', 'to']],
            'RPT-EML-002' => ['columns' => ['recipient', 'template_key', 'failed_at', 'error']],
            'RPT-EML-003' => ['columns' => ['receipt_number', 'school', 'status', 'emailed_at', 'error']],
            'RPT-EML-004' => ['columns' => ['template_key', 'sent', 'failed', 'skipped', 'total']],
            'RPT-EML-005' => ['columns' => ['month', 'template_key', 'sent', 'failed', 'total'], 'filters' => ['from', 'to']],

            // Audit
            'RPT-AUD-001' => ['columns' => ['action', 'description', 'user', 'created_at'], 'filters' => ['from', 'to']],
            'RPT-AUD-002' => ['columns' => ['action', 'username', 'ip_address', 'created_at'], 'filters' => ['from', 'to']],
            'RPT-AUD-003' => ['columns' => ['action', 'description', 'amount', 'created_at'], 'filters' => ['from', 'to']],
            'RPT-AUD-004' => ['columns' => ['report', 'user', 'filters', 'downloaded_at'], 'filters' => ['from', 'to']],
            'RPT-AUD-005' => ['columns' => ['username', 'ip_address', 'description', 'created_at'], 'filters' => ['from', 'to']],

            // Documents
            'RPT-DOC-001' => ['columns' => ['school', 'document_type', 'status', 'submitted_at']],
            'RPT-DOC-002' => ['columns' => ['name', 'required', 'active', 'validity_days']],
            'RPT-DOC-003' => ['columns' => ['school', 'document_type', 'valid_to', 'days_remaining', 'status']],
            'RPT-DOC-004' => ['columns' => ['school', 'document_type', 'rejection_reason', 'reviewed_at']],

            // Auth
            'RPT-AUTH-001' => ['columns' => ['school', 'student', 'reg_no', 'username', 'last_login_at', 'email'], 'filters' => ['school_id']],
            'RPT-AUTH-002' => ['columns' => ['school', 'teacher', 'reg_no', 'username', 'last_login_at', 'email'], 'filters' => ['school_id']],
            'RPT-AUTH-003' => ['columns' => ['school', 'student', 'reg_no', 'class', 'has_portal'], 'filters' => ['school_id']],
            'RPT-AUTH-004' => ['columns' => ['school', 'teacher', 'reg_no', 'has_portal', 'email'], 'filters' => ['school_id']],
            'RPT-AUTH-005' => ['columns' => ['username', 'ip_address', 'description', 'created_at'], 'filters' => ['from', 'to']],

            // Calendar
            'RPT-CAL-001' => ['columns' => ['program', 'title', 'starts', 'ends', 'status'], 'filters' => ['from', 'to']],
            'RPT-CAL-002' => ['columns' => ['program', 'opens', 'closes', 'status']],
            'RPT-CAL-003' => ['columns' => ['event', 'type', 'starts', 'registration_closes']],
            'RPT-CAL-004' => ['columns' => ['exam', 'scheduled_at', 'venue', 'status']],

            // Dashboard
            'RPT-DSH-001' => ['columns' => ['metric', 'value']],
            'RPT-DSH-002' => ['columns' => ['school', 'students', 'teachers', 'documents_pending', 'payment_status']],
            'RPT-DSH-003' => ['columns' => ['metric', 'value']],
            'RPT-DSH-004' => ['columns' => ['event', 'status', 'registrations_today', 'pending_marks', 'pending_payments']],
            'RPT-DSH-005' => ['columns' => ['school', 'registration_status', 'students', 'teachers', 'payment_status']],
            // Action-plan item 11: turnaround time (submission -> decision) per approval
            // workflow, aggregated since no per-workflow SLA analytics existed anywhere.
            'RPT-DSH-006' => ['columns' => ['workflow', 'count', 'avg_hours', 'min_hours', 'max_hours']],
            // Action-plan item 14: personalized "my pending approvals" queue. There is no
            // per-item assignee column anywhere in this schema, so "mine" is interpreted as
            // "everything my role is actually allowed to act on" (finance-only sees
            // membership payments; admin/staff see the operational queues) rather than a
            // literal assigned_to filter — see ErpReportController for how the acting
            // user is threaded into this report specifically.
            'RPT-DSH-007' => ['columns' => ['workflow', 'school', 'reference', 'submitted_at', 'waiting_hours']],
        ];

        return $defs;
    }
}
