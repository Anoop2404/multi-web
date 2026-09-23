<?php

namespace App\Support;

/**
 * Phase 2 of the State Kalotsav module — State permissions, deliberately separate from the
 * Sahodaya module's (TenantUserCatalog). A state officer's authority is not a tenant role.
 *
 * The separations the spec calls for are the point of having these at all:
 *   - a mark operator must not publish results,
 *   - a certificate operator must not alter results,
 *   - a report user is read-only.
 *
 * Those are impossible to express with the two roles the State had (state_admin, state_staff),
 * where state_staff was simply "GET only" enforced in middleware.
 */
class StateFestPermissions
{
    public const VIEW = 'state.fest.view';

    public const SETTINGS = 'state.fest.settings';

    public const CATALOG = 'state.fest.catalog';

    public const QUALIFIERS = 'state.fest.qualifiers';

    public const SCRUTINY = 'state.fest.scrutiny';

    public const REGISTRATIONS = 'state.fest.registrations';

    public const SCHEDULE = 'state.fest.schedule';

    public const ATTENDANCE = 'state.fest.attendance';

    public const JUDGES = 'state.fest.judges';

    public const MARKS = 'state.fest.marks';

    public const RESULTS = 'state.fest.results';

    public const APPEALS = 'state.fest.appeals';

    public const FINANCE = 'state.fest.finance';

    public const CATERING = 'state.fest.catering';

    public const CERTIFICATES = 'state.fest.certificates';

    public const REPORTS = 'state.fest.reports';

    public const AUDIT = 'state.fest.audit';

    /** Publishing is its own permission so entering marks never implies releasing results. */
    public const PUBLISH = 'state.fest.publish';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::VIEW, self::SETTINGS, self::CATALOG, self::QUALIFIERS, self::SCRUTINY,
            self::REGISTRATIONS, self::SCHEDULE, self::ATTENDANCE, self::JUDGES, self::MARKS,
            self::RESULTS, self::APPEALS, self::FINANCE, self::CATERING, self::CERTIFICATES,
            self::REPORTS, self::AUDIT, self::PUBLISH,
        ];
    }

    /**
     * Role → permissions. state_admin holds everything; every other role is defined by what it
     * deliberately lacks.
     *
     * @return array<string, list<string>>
     */
    public static function roleMatrix(): array
    {
        return [
            'state_admin' => self::all(),

            // Read-only across the module. Was previously enforced by refusing non-GET requests in
            // middleware, which could not distinguish "may not publish" from "may not look".
            'state_staff' => [
                self::VIEW, self::REPORTS,
            ],

            // Enters and corrects marks. Explicitly cannot publish or alter results.
            'state_mark_operator' => [
                self::VIEW, self::ATTENDANCE, self::MARKS,
            ],

            // Generates and reprints certificates. Explicitly cannot touch results.
            'state_certificate_operator' => [
                self::VIEW, self::CERTIFICATES, self::REPORTS,
            ],

            // Scrutiny officers work the qualifier queue, nothing downstream of it.
            'state_scrutiny_officer' => [
                self::VIEW, self::QUALIFIERS, self::SCRUTINY, self::REGISTRATIONS, self::REPORTS,
            ],

            // Report-only access for observers and auditors.
            'state_report_user' => [
                self::VIEW, self::REPORTS,
            ],
        ];
    }

    /** @return list<string> */
    public static function roles(): array
    {
        return array_keys(self::roleMatrix());
    }

    /**
     * The permissions a role holds. Unknown roles hold nothing — fail closed, like the rest of the
     * state admin.
     *
     * @return list<string>
     */
    public static function forRole(string $role): array
    {
        return self::roleMatrix()[$role] ?? [];
    }

    /** Human labels for the State Users screen. @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::VIEW          => 'View the State workspace',
            self::SETTINGS      => 'Change event settings and windows',
            self::CATALOG       => 'Manage items, categories and eligibility',
            self::QUALIFIERS    => 'Receive qualifier submissions',
            self::SCRUTINY      => 'Approve, reject and return qualifier entries',
            self::REGISTRATIONS => 'Manage State registrations, teams and substitutions',
            self::SCHEDULE      => 'Manage schedule, venues and performance order',
            self::ATTENDANCE    => 'Mark and correct attendance',
            self::JUDGES        => 'Manage judges and panels',
            self::MARKS         => 'Enter and correct marks',
            self::RESULTS       => 'Calculate and amend results',
            self::APPEALS       => 'Decide appeals',
            self::FINANCE       => 'Manage fees, remittances and receipts',
            self::CATERING      => 'Manage catering and food coupons',
            self::CERTIFICATES  => 'Generate and reissue certificates',
            self::REPORTS       => 'Run and download reports',
            self::AUDIT         => 'Read the activity log and export history',
            self::PUBLISH       => 'Publish results publicly',
        ];
    }
}
