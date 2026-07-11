<?php

namespace App\Support;

/**
 * Maps ERP report catalogue IDs to existing export routes/handlers.
 *
 * Event-scoped fest/sports/kalotsav reports link to the events index;
 * open an event workspace → Reports for per-event exports.
 */
class ReportRegistry
{
    /** @return list<array{id: string, label: string, module: string, classification: string, href?: string, note?: string}> */
    public static function definitions(string $sahodayaId): array
    {
        $base = "/sahodaya-admin/{$sahodayaId}";

        $rows = array_merge(
            self::schoolReports($base),
            self::studentReports($base),
            self::teacherReports($base),
            self::paymentReports($base),
            self::financeReports($base),
            self::festHubReports($base),
            self::sportsReports($base),
            self::kalotsavReports($base),
            self::mcqReports($base),
            self::trainingReports($base),
            self::boardResultReports($base),
            self::emailReports($base),
            self::auditReports($base),
            self::documentReports($base),
            self::authReports($base),
            self::calendarReports($base),
            self::dashboardReports($base),
        );

        return self::applyRunnableLinks($base, $rows);
    }

    /** @param  list<array<string, mixed>>  $rows */
    /** @return list<array<string, mixed>> */
    private static function applyRunnableLinks(string $base, array $rows): array
    {
        return array_map(function (array $row) use ($base) {
            $row['scope'] = ErpReportMeta::scope($row['id']);

            if ($row['scope'] === 'event') {
                $row['runnable'] = false;
                $row['note'] = $row['note'] ?? 'Select an event below — reports run in that event workspace.';
                $row['href'] = "{$base}/reports/hub?purpose=event";

                return $row;
            }

            if (ErpReportMeta::isRunnable($row['id'])) {
                $row['runnable'] = true;
                $row['href'] = self::runnerHref($base, $row['id']);
            }

            return $row;
        }, $rows);
    }

    public static function asyncExportThreshold(): int
    {
        return (int) config('erp.async_export_threshold', 5000);
    }

    /** @param array{id: string, label: string, module: string, classification: string, href?: string, note?: string, runnable?: bool} $row */
    private static function row(string $id, string $label, string $module, string $classification, ?string $href = null, ?string $note = null, bool $runnable = false): array
    {
        $row = compact('id', 'label', 'module', 'classification');
        if ($runnable) {
            $row['runnable'] = true;
        }
        if ($href) {
            $row['href'] = $href;
        }
        if ($note) {
            $row['note'] = $note;
        }

        return $row;
    }

    private static function runnerHref(string $base, string $id): string
    {
        return "{$base}/reports/{$id}";
    }

    /** @return list<array<string, string>> */
    private static function schoolReports(string $base): array
    {
        return [
            self::row('RPT-SCH-001', 'School list', 'schools', 'retain', "{$base}/membership/reports/export/schools"),
            self::row('RPT-SCH-002', 'Membership status by school', 'schools', 'retain', "{$base}/membership/reports"),
            self::row('RPT-SCH-003', 'Student count by school', 'schools', 'retain', "{$base}/membership/reports"),
            self::row('RPT-SCH-004', 'Teacher count by school', 'schools', 'retain', "{$base}/membership/reports"),
            self::row('RPT-SCH-005', 'School login history', 'schools', 'new'),
            self::row('RPT-SCH-006', 'School activity report', 'schools', 'new'),
            self::row('RPT-SCH-007', 'Document compliance status', 'schools', 'new', self::runnerHref($base, 'RPT-SCH-007'), null, true),
            self::row('RPT-SCH-008', 'Pending school applications', 'schools', 'retain', "{$base}/schools/applications"),
            self::row('RPT-SCH-009', 'Cluster/district summary', 'schools', 'new'),
            self::row('RPT-SCH-010', 'Office bearers list', 'schools', 'retain', "{$base}/office-bearers"),
            self::row('RPT-SCH-011', 'Coordinator assignment list', 'schools', 'new', "{$base}/users"),
            self::row('RPT-SCH-012', 'School profile export', 'schools', 'new', "{$base}/schools/export"),
            self::row('RPT-SCH-013', 'Inactive schools', 'schools', 'new', "{$base}/schools"),
            self::row('RPT-SCH-014', 'School contact directory', 'schools', 'new', "{$base}/schools/export"),
            self::row('RPT-SCH-015', 'Annual submission status', 'schools', 'retain', "{$base}/membership/submissions"),
        ];
    }

    /** @return list<array<string, string>> */
    private static function studentReports(string $base): array
    {
        return [
            self::row('RPT-STU-001', 'School-wise student list', 'students', 'retain', "{$base}/membership/reports"),
            self::row('RPT-STU-002', 'Gender-wise summary', 'students', 'retain', "{$base}/membership/reports"),
            self::row('RPT-STU-003', 'Class-wise summary', 'students', 'retain', "{$base}/membership/reports"),
            self::row('RPT-STU-004', 'Category-wise summary', 'students', 'retain', "{$base}/membership/reports"),
            self::row('RPT-STU-005', 'Age category-wise', 'students', 'new', self::runnerHref($base, 'RPT-STU-005'), null, true),
            self::row('RPT-STU-006', 'Verification pending', 'students', 'retain', "{$base}/students/verification?verification=unverified"),
            self::row('RPT-STU-007', 'Verification completed', 'students', 'retain', "{$base}/students/verification?verification=verified"),
            self::row('RPT-STU-008', 'Inactive students', 'students', 'retain', "{$base}/students/verification"),
            self::row('RPT-STU-009', 'Login report', 'students', 'new', self::runnerHref($base, 'RPT-AUTH-001'), null, true),
            self::row('RPT-STU-010', 'Import error report', 'students', 'retain', self::runnerHref($base, 'RPT-STU-010'), null, true),
            self::row('RPT-STU-011', 'Photo missing', 'students', 'new', self::runnerHref($base, 'RPT-STU-011'), null, true),
            self::row('RPT-STU-012', 'Duplicate admission numbers', 'students', 'new', self::runnerHref($base, 'RPT-STU-012'), null, true),
            self::row('RPT-STU-013', 'New admissions monthly', 'students', 'new', self::runnerHref($base, 'RPT-STU-013'), null, true),
            self::row('RPT-STU-014', 'Alumni list', 'students', 'retain'),
            self::row('RPT-STU-015', 'Transfer/TC log', 'students', 'retain', null, 'Hidden — TC/Transfer Certificate workflow is out of product scope.'),
        ];
    }

    /** @return list<array<string, string>> */
    private static function teacherReports(string $base): array
    {
        return [
            self::row('RPT-TCH-001', 'Teacher list', 'teachers', 'retain', "{$base}/membership/reports"),
            self::row('RPT-TCH-002', 'Category-wise (teaching type)', 'teachers', 'new'),
            self::row('RPT-TCH-003', 'Subject-wise', 'teachers', 'new'),
            self::row('RPT-TCH-004', 'School-wise', 'teachers', 'retain', "{$base}/membership/reports"),
            self::row('RPT-TCH-005', 'Training history', 'teachers', 'new', "{$base}/training"),
            self::row('RPT-TCH-006', 'Verification pending', 'teachers', 'retain', "{$base}/teachers/verification"),
            self::row('RPT-TCH-007', 'Verification completed', 'teachers', 'retain', "{$base}/teachers/verification"),
            self::row('RPT-TCH-008', 'Qualification-wise', 'teachers', 'new'),
            self::row('RPT-TCH-009', 'Experience-wise', 'teachers', 'new'),
            self::row('RPT-TCH-010', 'Login report', 'teachers', 'new', self::runnerHref($base, 'RPT-AUTH-002'), null, true),
            self::row('RPT-TCH-011', 'Judge assignment list', 'teachers', 'retain', null, 'Open fest event → Staff'),
            self::row('RPT-TCH-012', 'Missing email data quality', 'teachers', 'new', self::runnerHref($base, 'RPT-TCH-012'), null, true),
        ];
    }

    /** @return list<array<string, string>> */
    private static function paymentReports(string $base): array
    {
        return [
            self::row('RPT-PAY-001', 'Membership collection summary', 'payments', 'retain', "{$base}/membership/reports"),
            self::row('RPT-PAY-002', 'Pending membership payments', 'payments', 'retain', "{$base}/membership/payments?status=submitted"),
            self::row('RPT-PAY-003', 'Expired membership', 'payments', 'retain', "{$base}/membership/reports"),
            self::row('RPT-PAY-004', 'Renewed membership', 'payments', 'retain', "{$base}/membership/reports"),
            self::row('RPT-PAY-005', 'School membership history', 'payments', 'retain', "{$base}/membership/reports/export/payments"),
            self::row('RPT-PAY-006', 'Payment due (all modules)', 'payments', 'new', self::runnerHref($base, 'RPT-PAY-006'), null, true),
            self::row('RPT-PAY-007', 'Payment verified register', 'payments', 'alias', "{$base}/membership/payments?status=verified"),
            self::row('RPT-PAY-008', 'Payment rejected log', 'payments', 'new', self::runnerHref($base, 'RPT-PAY-008'), null, true),
            self::row('RPT-PAY-009', 'Receipt email delivery status', 'payments', 'new', "{$base}/finance/receipt-emails"),
            self::row('RPT-PAY-010', 'Unified offline payment hub', 'payments', 'new', "{$base}/finance/payments"),
            self::row('RPT-PAY-011', 'Proof pending verification', 'payments', 'new', "{$base}/finance/payments?status=submitted"),
            self::row('RPT-PAY-012', 'School-wise collection comparison', 'payments', 'new', "{$base}/membership/reports/export/payments-done"),
            self::row('RPT-PAY-013', 'Late fee collected', 'payments', 'new', self::runnerHref($base, 'RPT-PAY-013'), null, true),
            self::row('RPT-PAY-014', 'Waiver register', 'payments', 'new', self::runnerHref($base, 'RPT-PAY-014'), null, true),
            self::row('RPT-PAY-015', 'Invoice outstanding', 'payments', 'new', "{$base}/finance/receivables"),
            self::row('RPT-PAY-016', 'Receipt reprint log', 'payments', 'new'),
            self::row('RPT-PAY-017', 'Membership certificate issued', 'payments', 'new'),
            self::row('RPT-PAY-018', 'Fee slab configuration export', 'payments', 'new', "{$base}/membership/settings"),
            self::row('RPT-PAY-019', 'Daily collection summary', 'payments', 'new', self::runnerHref($base, 'RPT-PAY-019'), null, true),
            self::row('RPT-PAY-020', 'Module-wise payment mix', 'payments', 'new', self::runnerHref($base, 'RPT-PAY-020'), null, true),
        ];
    }

    /** @return list<array<string, string>> */
    private static function financeReports(string $base): array
    {
        return [
            self::row('RPT-FIN-001', 'Day book', 'finance', 'retain', "{$base}/ledger/reports"),
            self::row('RPT-FIN-002', 'Cash book', 'finance', 'retain', "{$base}/ledger/reports"),
            self::row('RPT-FIN-003', 'Bank book', 'finance', 'retain', "{$base}/ledger/reports"),
            self::row('RPT-FIN-004', 'General ledger', 'finance', 'retain', "{$base}/ledger/reports"),
            self::row('RPT-FIN-005', 'Trial balance', 'finance', 'new', "{$base}/finance/financial-statements"),
            self::row('RPT-FIN-006', 'Income & expenditure', 'finance', 'new', "{$base}/finance/financial-statements"),
            self::row('RPT-FIN-007', 'Balance sheet', 'finance', 'new', "{$base}/finance/financial-statements"),
            self::row('RPT-FIN-008', 'Receipt register', 'finance', 'retain', "{$base}/ledger"),
            self::row('RPT-FIN-009', 'Payment register', 'finance', 'retain', "{$base}/ledger"),
            self::row('RPT-FIN-010', 'Outstanding receivables', 'finance', 'retain', "{$base}/finance/receivables"),
            self::row('RPT-FIN-011', 'Pending fees all modules', 'finance', 'new', self::runnerHref($base, 'RPT-FIN-011'), null, true),
            self::row('RPT-FIN-012', 'Collection summary', 'finance', 'retain', "{$base}/finance"),
            self::row('RPT-FIN-013', 'Event-wise income', 'finance', 'retain', "{$base}/events", 'Per event → Fees'),
            self::row('RPT-FIN-014', 'School-wise income', 'finance', 'retain', "{$base}/finance/payments"),
            self::row('RPT-FIN-015', 'Monthly income trend', 'finance', 'new', "{$base}/finance/financial-statements"),
            self::row('RPT-FIN-016', 'Expense analysis', 'finance', 'new', "{$base}/finance/financial-statements"),
            self::row('RPT-FIN-017', 'Cost center report', 'finance', 'new'),
            self::row('RPT-FIN-018', 'Bank reconciliation status', 'finance', 'new', "{$base}/finance/bank-reconciliation"),
            self::row('RPT-FIN-019', 'Opening balance sheet', 'finance', 'new', "{$base}/ledger/opening-balances"),
            self::row('RPT-FIN-020', 'Voucher listing', 'finance', 'retain', "{$base}/ledger/export"),
        ];
    }

    /** @return list<array<string, string>> */
    private static function festHubReports(string $base): array
    {
        $events = "{$base}/events";

        return [
            self::row('RPT-FST-001', 'All fest events index', 'fest', 'retain', $events),
            self::row('RPT-FST-002', 'Fest payments queue', 'fest', 'retain', "{$base}/fest/payments"),
            self::row('RPT-FST-003', 'Fest appeals register', 'fest', 'retain', "{$base}/fest/appeals"),
            self::row('RPT-FST-004', 'Certificate search', 'fest', 'retain', "{$base}/events/certificates/search"),
            self::row('RPT-FST-005', 'Event reports hub', 'fest', 'retain', $events, 'Open event → Reports'),
        ];
    }

    /** @return list<array<string, string>> */
    private static function sportsReports(string $base): array
    {
        $hub = "{$base}/sports";
        $events = "{$base}/events";

        return [
            self::row('RPT-SPT-001', 'Registered students by item', 'sports', 'retain', $events, 'Sports event → Reports'),
            self::row('RPT-SPT-002', 'School registration summary', 'sports', 'retain', $hub),
            self::row('RPT-SPT-003', 'Fee collection sports', 'sports', 'retain', $events, 'Event → Fee collection report'),
            self::row('RPT-SPT-004', 'Schedule by venue', 'sports', 'retain', $events, 'Event → Item schedule'),
            self::row('RPT-SPT-005', 'Clash report', 'sports', 'retain', $events, 'Event → Schedule clashes'),
            self::row('RPT-SPT-006', 'Chest number list', 'sports', 'retain', $events, 'Event → Numbering register'),
            self::row('RPT-SPT-007', 'Attendance sheet', 'sports', 'retain', $events, 'Event → Export attendance'),
            self::row('RPT-SPT-008', 'Result sheet by item', 'sports', 'retain', $events, 'Event → Item-wise results'),
            self::row('RPT-SPT-009', 'Points table', 'sports', 'retain', $events, 'Event → Overall ranking'),
            self::row('RPT-SPT-010', 'Championship standings', 'sports', 'retain', $events, 'Event → Overall ranking'),
            self::row('RPT-SPT-011', 'Athletic records', 'sports', 'retain', "{$base}/sports/records"),
            self::row('RPT-SPT-012', 'Substitution log', 'sports', 'retain', $events),
            self::row('RPT-SPT-013', 'Eligibility exception log', 'sports', 'new'),
            self::row('RPT-SPT-014', 'Absentee list', 'sports', 'retain', $events, 'Event → Attendance export'),
            self::row('RPT-SPT-015', 'Item head wise summary', 'sports', 'retain', $events, 'Event → Head-wise participants'),
            self::row('RPT-SPT-016', 'Gender wise participation', 'sports', 'alias', $events, 'Event → Participation counts'),
            self::row('RPT-SPT-017', 'Class category participation', 'sports', 'retain', $events, 'Event → Participation counts'),
            self::row('RPT-SPT-018', 'Age group participation', 'sports', 'retain', $events, 'Event → Age group matrix'),
            self::row('RPT-SPT-019', 'School points detail', 'sports', 'retain', $events, 'Event → School detailed'),
            self::row('RPT-SPT-020', 'Individual medal tally', 'sports', 'retain', $events, 'Event → Export medal tally'),
            self::row('RPT-SPT-021', 'Heat/lane assignment', 'sports', 'new'),
            self::row('RPT-SPT-022', 'Official assignment list', 'sports', 'retain', $events, 'Event → Staff'),
            self::row('RPT-SPT-023', 'Venue utilization', 'sports', 'new'),
            self::row('RPT-SPT-024', 'Registration window status', 'sports', 'new', self::runnerHref($base, 'RPT-SPT-024'), null, true),
            self::row('RPT-SPT-025', 'Free vs paid item breakdown', 'sports', 'new', $events, 'Event → Fee collection'),
            self::row('RPT-SPT-026', 'Pending mark entry', 'sports', 'retain', $events, 'Event → Mark entry status'),
            self::row('RPT-SPT-027', 'Unpublished results', 'sports', 'retain', $events),
            self::row('RPT-SPT-028', 'Appeal register sports', 'sports', 'retain', "{$base}/fest/appeals"),
            self::row('RPT-SPT-029', 'Certificate issue log', 'sports', 'retain', "{$base}/events/certificates/search"),
            self::row('RPT-SPT-030', 'ID card issue log', 'sports', 'retain', $events, 'Event → ID cards'),
            self::row('RPT-SPT-031', 'Level-wise registration', 'sports', 'retain', $hub),
            self::row('RPT-SPT-032', 'Cluster summary', 'sports', 'retain', $events, 'Event → Overall ranking'),
            self::row('RPT-SPT-033', 'Top performers by item', 'sports', 'retain', $events, 'Event → Item-wise'),
            self::row('RPT-SPT-034', 'School championship rank', 'sports', 'retain', $events, 'Event → Overall ranking'),
            self::row('RPT-SPT-035', 'Participant count by gender/item', 'sports', 'alias', $events, 'Event → Participation counts'),
            self::row('RPT-SPT-036', 'Schedule daily bulletin', 'sports', 'new'),
            self::row('RPT-SPT-037', 'Gate entry log', 'sports', 'retain', $events),
            self::row('RPT-SPT-038', 'Food coupon distribution', 'sports', 'retain', $events, 'Event → Catering export'),
            self::row('RPT-SPT-039', 'Item fee configuration', 'sports', 'new', $hub),
            self::row('RPT-SPT-040', 'Registration approval pending', 'sports', 'retain', $events, 'Event → Pending approvals'),
            self::row('RPT-SPT-041', 'Marks verification pending', 'sports', 'retain', $events, 'Event → Mark entry status'),
            self::row('RPT-SPT-042', 'Record broken log', 'sports', 'new', "{$base}/sports/records"),
            self::row('RPT-SPT-043', 'Team event roster', 'sports', 'retain', $events, 'Event → Registration register'),
            self::row('RPT-SPT-044', 'Individual event roster', 'sports', 'alias', $events, 'Event → Registration register'),
            self::row('RPT-SPT-045', 'Export all results CSV', 'sports', 'retain', $events, 'Event → Export results'),
        ];
    }

    /** @return list<array<string, string>> */
    private static function kalotsavReports(string $base): array
    {
        $hub = "{$base}/kalotsav";
        $events = "{$base}/events";

        $rows = [];
        $defs = [
            ['RPT-KAL-001', 'Item-wise registration', 'retain', $events],
            ['RPT-KAL-002', 'School participation summary', 'retain', $hub],
            ['RPT-KAL-003', 'Judge assignment list', 'retain', $events],
            ['RPT-KAL-004', 'Score sheet by item', 'retain', $events],
            ['RPT-KAL-005', 'Tabulation sheet', 'retain', $events],
            ['RPT-KAL-006', 'Rank list by item', 'retain', $events],
            ['RPT-KAL-007', 'School trophy points', 'retain', $events],
            ['RPT-KAL-008', 'Appeal register', 'retain', "{$base}/fest/appeals"],
            ['RPT-KAL-009', 'Schedule by stage', 'retain', $events],
            ['RPT-KAL-010', 'Group item participants', 'retain', $events],
            ['RPT-KAL-011', 'Fee collection kalotsav', 'retain', $events],
            ['RPT-KAL-012', 'Unpublished marks pending', 'retain', $events],
            ['RPT-KAL-013', 'Certificate issue log', 'retain', "{$base}/events/certificates/search"],
            ['RPT-KAL-014', 'Individual items roster', 'alias', $events],
            ['RPT-KAL-015', 'Stage utilization', 'new', self::runnerHref($base, 'RPT-KAL-015')],
            ['RPT-KAL-016', 'Judge score detail', 'retain', $events],
            ['RPT-KAL-017', 'Multi-judge average sheet', 'retain', $events],
            ['RPT-KAL-018', 'Clash report kalotsav', 'alias', $events],
            ['RPT-KAL-019', 'Category wise items', 'retain', "{$base}/catalog"],
            ['RPT-KAL-020', 'Registration by gender', 'retain', $events],
            ['RPT-KAL-021', 'School wise points detail', 'retain', $events],
            ['RPT-KAL-022', 'Overall championship', 'retain', $events],
            ['RPT-KAL-023', 'Item head summary', 'retain', $events],
            ['RPT-KAL-024', 'Mark entry status', 'retain', $events],
            ['RPT-KAL-025', 'Result publish log', 'new', null],
            ['RPT-KAL-026', 'Participant ID list', 'retain', $events],
            ['RPT-KAL-027', 'Schedule clash kalotsav', 'alias', $events],
            ['RPT-KAL-028', 'Substitution log', 'retain', $events],
            ['RPT-KAL-029', 'Level propagation status', 'retain', $hub],
            ['RPT-KAL-030', 'State program sync log', 'new', null],
            ['RPT-KAL-031', 'Catalog item master export', 'retain', "{$base}/catalog"],
            ['RPT-KAL-032', 'Judge login activity', 'new', null],
            ['RPT-KAL-033', 'Appeal outcome summary', 'new', "{$base}/fest/appeals"],
            ['RPT-KAL-034', 'Merit certificate list', 'retain', "{$base}/events/certificates/search"],
            ['RPT-KAL-035', 'Participation certificate list', 'retain', "{$base}/events/certificates/search"],
        ];

        foreach ($defs as [$id, $label, $class, $href]) {
            $runnable = $id === 'RPT-KAL-015';
            $rows[] = self::row(
                $id,
                $label,
                'kalotsav',
                $class,
                $href,
                $href && ! $runnable ? 'Kalotsav event → Reports' : null,
                $runnable,
            );
        }

        for ($i = 36; $i <= 45; $i++) {
            $rows[] = self::row(
                sprintf('RPT-KAL-%03d', $i),
                'Kalotsav operational report '.$i,
                'kalotsav',
                $i <= 40 ? 'retain' : 'new',
                $events,
                'Open event → Reports',
            );
        }

        return $rows;
    }

    /** @return list<array<string, string>> */
    private static function mcqReports(string $base): array
    {
        return [
            self::row('RPT-MCQ-001', 'Registration by school', 'mcq', 'retain', "{$base}/mcq-exams", 'Open exam → Reports'),
            self::row('RPT-MCQ-002', 'Registration by tier', 'mcq', 'retain', "{$base}/mcq-exams"),
            self::row('RPT-MCQ-003', 'Talent Search fee collection', 'mcq', 'retain', "{$base}/mcq/payments"),
            self::row('RPT-MCQ-004', 'Hall ticket issued', 'mcq', 'retain', "{$base}/mcq-exams"),
            self::row('RPT-MCQ-005', 'Attendance sheet', 'mcq', 'retain', "{$base}/mcq-exams"),
            self::row('RPT-MCQ-006', 'Exam session log', 'mcq', 'new', self::runnerHref($base, 'RPT-MCQ-006'), null, true),
            self::row('RPT-MCQ-007', 'Result sheet tier-wise', 'mcq', 'retain', "{$base}/mcq-exams"),
            self::row('RPT-MCQ-008', 'Rank list', 'mcq', 'retain', "{$base}/mcq-exams"),
            self::row('RPT-MCQ-009', 'Question analysis', 'mcq', 'new'),
            self::row('RPT-MCQ-010', 'Absent/incomplete attempts', 'mcq', 'retain', "{$base}/mcq-exams"),
            self::row('RPT-MCQ-011', 'School performance summary', 'mcq', 'new', self::runnerHref($base, 'RPT-MCQ-011'), null, true),
            self::row('RPT-MCQ-012', 'Tier cutoff marks', 'mcq', 'new', "{$base}/mcq-exams"),
            self::row('RPT-MCQ-013', 'Exam IP audit', 'mcq', 'new', self::runnerHref($base, 'RPT-MCQ-013'), null, true),
            self::row('RPT-MCQ-014', 'Question bank export', 'mcq', 'retain', "{$base}/mcq/question-banks"),
            self::row('RPT-MCQ-015', 'Registration window status', 'mcq', 'new', "{$base}/mcq"),
            self::row('RPT-MCQ-016', 'Result analysis', 'mcq', 'new', "{$base}/mcq-exams"),
            self::row('RPT-MCQ-017', 'Malpractice register', 'mcq', 'new', "{$base}/mcq-exams"),
        ];
    }

    /** @return list<array<string, string>> */
    private static function trainingReports(string $base): array
    {
        return [
            self::row('RPT-TRN-001', 'Program list', 'training', 'retain', "{$base}/training"),
            self::row('RPT-TRN-002', 'Nominations by school', 'training', 'retain', "{$base}/training"),
            self::row('RPT-TRN-003', 'Eligibility rejection log', 'training', 'new', self::runnerHref($base, 'RPT-TRN-003'), null, true),
            self::row('RPT-TRN-004', 'Fee collection training', 'training', 'retain', "{$base}/training"),
            self::row('RPT-TRN-005', 'Attendance register', 'training', 'retain', "{$base}/training"),
            self::row('RPT-TRN-006', 'Certificate issued', 'training', 'retain', "{$base}/training"),
            self::row('RPT-TRN-007', 'Teacher training history', 'training', 'new', "{$base}/training"),
            self::row('RPT-TRN-008', 'Feedback summary', 'training', 'new'),
            self::row('RPT-TRN-009', 'Capacity utilization', 'training', 'new', self::runnerHref($base, 'RPT-TRN-009'), null, true),
            self::row('RPT-TRN-010', 'Pending financial summary', 'training', 'new', self::runnerHref($base, 'RPT-TRN-010'), null, true),
            self::row('RPT-TRN-011', 'Nomination approval queue', 'training', 'retain', "{$base}/training"),
            self::row('RPT-TRN-012', 'Resource person assignment', 'training', 'new', "{$base}/training"),
            self::row('RPT-TRN-013', 'School-wise CPD hours', 'training', 'new', self::runnerHref($base, 'RPT-TRN-013'), null, true),
            self::row('RPT-TRN-014', 'Teacher-type wise participation', 'training', 'new', self::runnerHref($base, 'RPT-TRN-014'), null, true),
            self::row('RPT-TRN-015', 'Subject-wise participation', 'training', 'new', self::runnerHref($base, 'RPT-TRN-015'), null, true),
            self::row('RPT-TRN-016', 'Day-wise participation', 'training', 'new', self::runnerHref($base, 'RPT-TRN-016'), null, true),
        ];
    }

    /** @return list<array<string, string>> */
    private static function boardResultReports(string $base): array
    {
        return [
            self::row('RPT-BRD-001', 'School Result Summary', 'board_results', 'new', self::runnerHref($base, 'RPT-BRD-001'), null, true),
            self::row('RPT-BRD-002', 'Overall Ranking', 'board_results', 'new', self::runnerHref($base, 'RPT-BRD-002'), null, true),
            self::row('RPT-BRD-003', 'Pass % Report', 'board_results', 'new', self::runnerHref($base, 'RPT-BRD-003'), null, true),
            self::row('RPT-BRD-004', 'Class X Merit Register', 'board_results', 'new', self::runnerHref($base, 'RPT-BRD-004'), null, true),
            self::row('RPT-BRD-005', 'Stream Merit Register', 'board_results', 'new', self::runnerHref($base, 'RPT-BRD-005'), null, true),
        ];
    }

    /** @return list<array<string, string>> */
    private static function emailReports(string $base): array
    {
        return [
            self::row('RPT-EML-001', 'Email delivery log', 'email', 'new', "{$base}/finance/email-delivery"),
            self::row('RPT-EML-002', 'Failed emails pending retry', 'email', 'new', "{$base}/finance/email-delivery?status=failed"),
            self::row('RPT-EML-003', 'Receipt email status', 'email', 'new', "{$base}/finance/receipt-emails"),
            self::row('RPT-EML-004', 'Template usage counts', 'email', 'new', self::runnerHref($base, 'RPT-EML-004'), null, true),
            self::row('RPT-EML-005', 'Emails by module (monthly)', 'email', 'new', self::runnerHref($base, 'RPT-EML-005'), null, true),
        ];
    }

    /** @return list<array<string, string>> */
    private static function auditReports(string $base): array
    {
        return [
            self::row('RPT-AUD-001', 'Platform audit trail', 'audit', 'retain', '/admin/audit-logs'),
            self::row('RPT-AUD-002', 'Auth events summary', 'audit', 'retain', '/admin/audit-logs?category=auth'),
            self::row('RPT-AUD-003', 'Finance audit extract', 'audit', 'retain', '/admin/audit-logs?category=finance'),
            self::row('RPT-AUD-004', 'Export activity log', 'audit', 'new', '/admin/audit-logs'),
            self::row('RPT-AUD-005', 'Failed login attempts', 'audit', 'retain', '/admin/audit-logs?category=auth'),
        ];
    }

    /** @return list<array<string, string>> */
    private static function documentReports(string $base): array
    {
        return [
            self::row('RPT-DOC-001', 'Document review queue', 'documents', 'new', "{$base}/documents/review"),
            self::row('RPT-DOC-002', 'Document type configuration', 'documents', 'new', "{$base}/documents/types"),
            self::row('RPT-DOC-003', 'Expiring documents (30 days)', 'documents', 'new', self::runnerHref($base, 'RPT-DOC-003'), null, true),
            self::row('RPT-DOC-004', 'Rejected documents log', 'documents', 'new', self::runnerHref($base, 'RPT-DOC-004'), null, true),
        ];
    }

    /** @return list<array<string, string>> */
    private static function authReports(string $base): array
    {
        return [
            self::row('RPT-AUTH-001', 'Student login report', 'auth', 'new', self::runnerHref($base, 'RPT-AUTH-001'), null, true),
            self::row('RPT-AUTH-002', 'Teacher login report', 'auth', 'new', self::runnerHref($base, 'RPT-AUTH-002'), null, true),
            self::row('RPT-AUTH-003', 'Never logged in students', 'auth', 'new', self::runnerHref($base, 'RPT-AUTH-003'), null, true),
            self::row('RPT-AUTH-004', 'Never logged in teachers', 'auth', 'new', self::runnerHref($base, 'RPT-AUTH-004'), null, true),
            self::row('RPT-AUTH-005', 'Failed login attempts', 'auth', 'retain', "{$base}/auth/login-audit"),
        ];
    }

    /** @return list<array<string, string>> */
    private static function calendarReports(string $base): array
    {
        return [
            self::row('RPT-CAL-001', 'Aggregated program calendar', 'calendar', 'new', "{$base}/calendar"),
            self::row('RPT-CAL-002', 'Membership registration windows', 'calendar', 'retain', "{$base}/calendar"),
            self::row('RPT-CAL-003', 'Fest event dates', 'calendar', 'retain', "{$base}/calendar"),
            self::row('RPT-CAL-004', 'Talent Search exam schedule', 'calendar', 'retain', "{$base}/calendar"),
        ];
    }

    /** @return list<array<string, string>> */
    private static function dashboardReports(string $base): array
    {
        return [
            self::row('RPT-DSH-001', 'Sahodaya KPI snapshot', 'dashboard', 'new', self::runnerHref($base, 'RPT-DSH-001'), null, true),
            self::row('RPT-DSH-002', 'School dashboard export', 'dashboard', 'new'),
            self::row('RPT-DSH-003', 'Finance dashboard export', 'dashboard', 'new', self::runnerHref($base, 'RPT-DSH-003'), null, true),
            self::row('RPT-DSH-004', 'Event ops daily brief', 'dashboard', 'new', "{$base}/events"),
            self::row('RPT-DSH-005', 'Registration funnel export', 'dashboard', 'new', self::runnerHref($base, 'RPT-DSH-005'), null, true),
        ];
    }
}
