<?php

namespace App\Support;

/**
 * Phase 8 of the State Kalotsav module — the State report registry.
 *
 * Scoped deliberately: these are the reports reachable from the State sidebar and from a workspace
 * tab, mirroring what the Sahodaya module actually exposes in its own navigation (11 named report
 * pages plus a hub), not the full inventory of ~100 export permutations. A report nobody can reach
 * from a menu is a maintenance cost with no user.
 *
 * Every report declares the phase whose data it needs. A report whose phase is not built yet is
 * listed as unavailable with the reason, rather than shipping a screen that renders an empty table —
 * the same courtesy the spec asks for with sports-only reports, which are marked "not applicable to
 * State Kalotsav" rather than silently appearing.
 *
 * Parity contract: FestReportCatalog is the reference for what a fest report catalog should cover.
 * StateFestReportCatalogParityTest asserts that every Sahodaya report reachable from a menu has a
 * State counterpart here — either implemented, or explicitly declared not applicable — so a State
 * report can never go silently missing.
 */
class StateFestReportCatalog
{
    public const GROUP_OVERVIEW = 'overview';

    public const GROUP_BEFORE = 'before';

    public const GROUP_DURING = 'during';

    public const GROUP_AFTER = 'after';

    public const GROUP_PRINT = 'print';

    public const GROUP_FINANCE = 'finance';

    /** @return array<string, string> */
    public static function groups(): array
    {
        return [
            self::GROUP_OVERVIEW => 'Overview',
            self::GROUP_BEFORE   => 'Before Event',
            self::GROUP_DURING   => 'During Event',
            self::GROUP_AFTER    => 'After Event',
            self::GROUP_PRINT    => 'Certificates & Print',
            self::GROUP_FINANCE  => 'Finance & Audit',
        ];
    }

    /**
     * The reports the State module exposes.
     *
     * `available` false means the data it needs belongs to a phase not yet built; `blocked_by` says
     * which, so the hub can explain itself instead of showing an empty report.
     *
     * Every report takes the module's standard filters — Sahodaya first, School second — unless it
     * says otherwise.
     *
     * @return list<array<string, mixed>>
     */
    public static function reports(): array
    {
        return [
            // ── Before the event: who is coming ─────────────────────────────────────────────
            [
                'id' => 'registration-master',
                'label' => 'State Registration Master List',
                'description' => 'Every approved State registration, with the Sahodaya that sent it and the School it came from.',
                'group' => self::GROUP_BEFORE,
                'formats' => ['pdf', 'xls', 'csv'],
                'filters' => ['sahodaya_id', 'school_id', 'item_id', 'origin'],
                'available' => true,
            ],
            [
                'id' => 'sahodaya-participation',
                'label' => 'Sahodaya-wise Registration Summary',
                'description' => 'One row per Sahodaya: schools, participants, items entered and approval status.',
                'group' => self::GROUP_BEFORE,
                'formats' => ['pdf', 'xls', 'csv'],
                'filters' => ['origin'],
                'available' => true,
            ],
            [
                'id' => 'sahodaya-school-matrix',
                'label' => 'Sahodaya × School Participation Matrix',
                'description' => 'School contributions inside each Sahodaya — the drill-down behind the summary.',
                'group' => self::GROUP_BEFORE,
                'formats' => ['xls', 'csv'],
                'filters' => ['sahodaya_id', 'origin'],
                'available' => true,
            ],
            [
                'id' => 'item-counts',
                'label' => 'Item List & Registration Counts',
                'description' => 'Every item with how many Sahodayas entered it and how many participants.',
                'group' => self::GROUP_BEFORE,
                'formats' => ['pdf', 'xls', 'csv'],
                'filters' => ['sahodaya_id'],
                'available' => true,
            ],
            [
                'id' => 'item-participants',
                'label' => 'Item-wise Participant List',
                'description' => 'Participants per item, showing Sahodaya and School for each.',
                'group' => self::GROUP_BEFORE,
                'formats' => ['pdf', 'xls', 'csv'],
                'filters' => ['item_id', 'sahodaya_id', 'school_id'],
                'available' => true,
            ],
            [
                'id' => 'student-wise',
                'label' => 'Student-wise Participation Report',
                'description' => 'One row per participant with every item they are entered for.',
                'group' => self::GROUP_BEFORE,
                'formats' => ['pdf', 'xls', 'csv'],
                'filters' => ['sahodaya_id', 'school_id', 'search'],
                'available' => true,
            ],
            [
                'id' => 'unique-participants',
                'label' => 'Unique Participant Counts',
                'description' => 'Distinct participants per Sahodaya, against total entries — the gap is multi-item participation.',
                'group' => self::GROUP_BEFORE,
                'formats' => ['xls', 'csv'],
                'filters' => ['origin'],
                'available' => true,
            ],
            [
                'id' => 'slot-usage',
                'label' => 'Sahodaya Slot Usage',
                'description' => 'Allowance, used and available per Sahodaya per item, with breaches highlighted.',
                'group' => self::GROUP_BEFORE,
                'formats' => ['pdf', 'xls', 'csv'],
                'filters' => ['sahodaya_id', 'item_id'],
                'available' => true,
            ],
            [
                'id' => 'slot-violations',
                'label' => 'Slot Violations',
                'description' => 'Only the Sahodaya/item pairs that have exceeded their allowance.',
                'group' => self::GROUP_BEFORE,
                'formats' => ['pdf', 'xls', 'csv'],
                'filters' => [],
                'available' => true,
            ],
            [
                'id' => 'pending-approvals',
                'label' => 'Pending Approval Register',
                'description' => 'Entries received but not yet approved for State conduct.',
                'group' => self::GROUP_BEFORE,
                'formats' => ['pdf', 'xls', 'csv'],
                'filters' => ['sahodaya_id', 'origin'],
                'available' => true,
            ],

            // ── Scheduling: needs Phase 5 ───────────────────────────────────────────────────
            [
                'id' => 'item-schedule',
                'label' => 'Item Venue & Time Schedule',
                'group' => self::GROUP_BEFORE,
                'formats' => ['pdf', 'csv'],
                'filters' => ['date', 'venue_id'],
                'description' => 'Every scheduled item with its date, reporting and start time, and stage.',
                'available' => true,
            ],
            [
                'id' => 'schedule-clashes',
                'label' => 'Schedule Clash Report',
                'group' => self::GROUP_BEFORE,
                'formats' => ['pdf', 'csv'],
                'filters' => ['sahodaya_id'],
                'description' => 'Participants entered for overlapping items, stages double-booked, and Sahodaya overlaps.',
                'available' => true,
            ],

            // ── Printed sheets ──────────────────────────────────────────────────────────────
            // These are not tables of data but paper the event runs on, so they carry
            // kind => 'sheet': one section per item, in performance order, with room to write.
            [
                'id' => 'attendance-sheet',
                'label' => 'Attendance Sheet by Item',
                'group' => self::GROUP_PRINT,
                'kind' => 'sheet',
                'formats' => ['pdf'],
                'filters' => ['item_id', 'sahodaya_id'],
                'description' => 'One signature row per entry, in chest-number order, grouped by item.',
                'available' => true,
            ],
            [
                'id' => 'timesheet',
                'label' => 'Stage Timesheet',
                'group' => self::GROUP_PRINT,
                'kind' => 'sheet',
                'formats' => ['pdf'],
                'filters' => ['item_id', 'sahodaya_id'],
                'description' => 'Start and finish times per competitor, for the stage to fill in.',
                'available' => true,
            ],
            [
                'id' => 'judge-sheet',
                'label' => 'Judge Score Sheet',
                'group' => self::GROUP_PRINT,
                'kind' => 'sheet',
                'formats' => ['pdf'],
                'filters' => ['item_id'],
                'description' => 'Chest numbers only — the panel does not see which Sahodaya a performance came from.',
                'available' => true,
            ],
            [
                'id' => 'green-room-sheet',
                'label' => 'Green Room Call List',
                'group' => self::GROUP_PRINT,
                'kind' => 'sheet',
                'formats' => ['pdf'],
                'filters' => ['item_id', 'sahodaya_id'],
                'description' => 'Reporting order per item, for the green room to tick off arrivals.',
                'available' => true,
            ],
            [
                'id' => 'participant-cards',
                'label' => 'ID / Admit Cards',
                'group' => self::GROUP_PRINT,
                'kind' => 'cards',
                'formats' => ['pdf'],
                'filters' => ['item_id', 'sahodaya_id'],
                'description' => 'One card per participant with chest number, Sahodaya and School — eight to a page.',
                'available' => true,
            ],

            // ── During the event ────────────────────────────────────────────────────────────
            [
                'id' => 'attendance-status',
                'label' => 'Live Attendance Status',
                'group' => self::GROUP_DURING,
                'formats' => ['pdf', 'xls', 'csv'],
                'filters' => ['sahodaya_id', 'item_id'],
                'available' => true,
            ],
            [
                'id' => 'mark-entry-status',
                'label' => 'Mark Entry Status',
                'group' => self::GROUP_DURING,
                'formats' => ['xls', 'csv'],
                'filters' => ['item_id'],
                'available' => true,
            ],

            // ── After the event: needs Phase 7 ──────────────────────────────────────────────
            [
                'id' => 'overall-sahodaya-ranking',
                'label' => 'Overall Sahodaya Ranking',
                'group' => self::GROUP_AFTER,
                'formats' => ['pdf', 'xls', 'csv'],
                'filters' => [],
                'description' => 'Points, medal counts and rank per Sahodaya, from published results.',
                'available' => true,
            ],
            [
                'id' => 'category-sahodaya-points',
                'label' => 'Category-wise Sahodaya Points',
                'group' => self::GROUP_AFTER,
                'formats' => ['pdf', 'xls'],
                'filters' => [],
                'description' => 'Sahodaya points broken down by item category.',
                'available' => true,
            ],
            [
                'id' => 'school-contribution',
                'label' => 'School Contribution to Sahodaya Points',
                'group' => self::GROUP_AFTER,
                'formats' => ['pdf', 'xls'],
                'filters' => ['sahodaya_id'],
                'description' => 'Which Schools earned a Sahodaya\'s points.',
                'available' => true,
            ],
            [
                'id' => 'item-wise-results',
                'label' => 'Item-wise Top Results',
                'group' => self::GROUP_AFTER,
                'formats' => ['pdf', 'xls', 'csv'],
                'filters' => ['item_id'],
                'description' => 'Positions and grades per item, for published items.',
                'available' => true,
            ],
            [
                'id' => 'individual-championship',
                'label' => 'Individual Championship',
                'group' => self::GROUP_AFTER,
                'formats' => ['pdf', 'xls'],
                'filters' => ['category'],
                'description' => 'Highest-scoring participants, with their School and Sahodaya.',
                'available' => true,
            ],

            // ── Finance ─────────────────────────────────────────────────────────────────────
            [
                'id' => 'sahodaya-fee-summary',
                'label' => 'Sahodaya Fee Summary',
                'description' => 'What each Sahodaya owes and has paid. The State bills a fixed fee per Sahodaya, not per participant.',
                'group' => self::GROUP_FINANCE,
                'formats' => ['pdf', 'xls', 'csv'],
                'filters' => ['sahodaya_id'],
                'available' => true,
            ],

            // ── Logistics ───────────────────────────────────────────────────────────────────
            [
                'id' => 'catering-summary',
                'label' => 'Catering Summary',
                'group' => self::GROUP_FINANCE,
                'formats' => ['pdf', 'xls', 'csv'],
                'filters' => [],
                'description' => 'Meals entitled and issued per sitting, with the variance the kitchen is billed on.',
                'available' => true,
            ],
            [
                'id' => 'duty-roster',
                'label' => 'Volunteer & Official Duty Roster',
                'group' => self::GROUP_FINANCE,
                'formats' => ['pdf', 'xls', 'csv'],
                'filters' => [],
                'description' => 'Who is on duty, where, session by session.',
                'available' => true,
            ],

            // ── Certificates: needs Phase 9 ─────────────────────────────────────────────────
            [
                'id' => 'certificate-tally',
                'label' => 'Certificate Tally',
                'group' => self::GROUP_PRINT,
                'formats' => ['pdf', 'xls'],
                'filters' => ['sahodaya_id'],
                'description' => 'Certificates issued by Sahodaya, School, item and type, with stale ones flagged.',
                'available' => true,
            ],
        ];
    }

    /**
     * Sahodaya reports with no State counterpart, and why. Asserted by the parity test so a report
     * cannot be dropped by accident — only on purpose, with a reason recorded here.
     *
     * @return array<string, string>
     */
    public static function notApplicable(): array
    {
        return [
            'house-wise'        => 'Houses are a school-level construct. Not applicable to State Kalotsav.',
            'athletic-records'  => 'Sports only. Not applicable to State Kalotsav.',
            'school-invoices'   => 'The State bills the Sahodaya, not the School — see the Sahodaya Fee Summary.',
        ];
    }

    public static function find(string $id): ?array
    {
        foreach (self::reports() as $report) {
            if ($report['id'] === $id) {
                return $report;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    public static function available(): array
    {
        return array_values(array_filter(self::reports(), fn (array $r) => $r['available'] ?? false));
    }

    public static function isAvailable(string $id): bool
    {
        return (bool) (self::find($id)['available'] ?? false);
    }
}
