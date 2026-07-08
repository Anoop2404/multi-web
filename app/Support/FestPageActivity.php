<?php

namespace App\Support;

/** Activity log page keys — one per admin screen / section. */
class FestPageActivity
{
    public const OVERVIEW = 'event.overview';
    public const ITEMS = 'event.items';
    public const ITEMS_LIST = 'event.items.list';
    public const LEVELS = 'event.levels';
    public const ACTIVITY = 'event.activity';
    public const SETTINGS = 'event.settings';
    public const REGISTRATIONS = 'event.registrations';
    public const REGISTRATIONS_IMPORT = 'event.registrations.import';
    public const ATTENDANCE = 'event.attendance';
    public const SCHEDULE = 'event.schedule';
    public const JUDGES = 'event.judges';
    public const EVENT_STAFF = 'event.event-staff';
    public const MARKS = 'event.marks';
    public const MARKS_IMPORT = 'event.marks.import';
    public const RESULTS = 'event.results';
    public const LEADERBOARD = 'event.leaderboard';
    public const CHAMPIONSHIP = 'event.championship';
    public const FEES = 'event.fees';
    public const FINANCE = 'event.finance';
    public const CERTIFICATES = 'event.certificates';
    public const ID_CARDS = 'event.id-cards';
    public const CHEST_NUMBERS = 'event.chest-numbers';
    public const COMPETITION = 'event.competition';
    public const APPEALS = 'event.appeals';
    public const ATHLETIC_RECORDS = 'event.athletic-records';
    public const HOUSES = 'event.houses';
    public const CATERING = 'event.catering';
    public const FOOD_COUPONS = 'event.food-coupons';
    public const REPORTS = 'event.reports';
    public const REPORTS_DOWNLOADS = 'event.reports.downloads';

    public const PROGRAM_OVERVIEW = 'program.overview';

    public const CATALOG_HUB = 'catalog.hub';
    public const CATALOG_MASTER = 'catalog.master';
    public const CATALOG_LIST = 'catalog.list';
    public const CATALOG_ASSIGN = 'catalog.assign';

    public static function settingsTab(string $tab): string
    {
        return self::SETTINGS.'.'.$tab;
    }

    public static function reportsPhase(string $phase): string
    {
        return self::REPORTS_DOWNLOADS.'.'.$phase;
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::OVERVIEW => 'Event overview',
            self::ITEMS => 'Items setup',
            self::ITEMS_LIST => 'Item listing',
            self::LEVELS => 'Rounds & promotion',
            self::ACTIVITY => 'Activity timeline',
            self::SETTINGS => 'Event settings',
            self::REGISTRATIONS => 'Registrations',
            self::REGISTRATIONS_IMPORT => 'Registration import',
            self::ATTENDANCE => 'Attendance',
            self::SCHEDULE => 'Schedule',
            self::JUDGES => 'Judges',
            self::EVENT_STAFF => 'Event staff',
            self::MARKS => 'Mark entry',
            self::MARKS_IMPORT => 'Import marks',
            self::RESULTS => 'Results',
            self::LEADERBOARD => 'Leaderboard',
            self::CHAMPIONSHIP => 'Championship',
            self::FEES => 'Registration fees',
            self::FINANCE => 'School invoices',
            self::CERTIFICATES => 'Certificates',
            self::ID_CARDS => 'ID cards',
            self::CHEST_NUMBERS => 'Chest numbers',
            self::APPEALS => 'Appeals',
            self::ATHLETIC_RECORDS => 'Athletic records',
            self::HOUSES => 'Houses',
            self::CATERING => 'Catering',
            self::FOOD_COUPONS => 'Food coupons',
            self::REPORTS => 'Reports',
            self::PROGRAM_OVERVIEW => 'Program overview',
            self::CATALOG_HUB => 'Catalog overview',
            self::CATALOG_MASTER => 'Catalog master',
            self::CATALOG_LIST => 'Catalog listing',
            self::CATALOG_ASSIGN => 'Catalog assign',
        ];
    }

    public static function label(?string $page): string
    {
        if ($page === null || $page === '') {
            return 'Event';
        }

        if (isset(self::labels()[$page])) {
            return self::labels()[$page];
        }

        if (str_starts_with($page, self::SETTINGS.'.')) {
            $tab = substr($page, strlen(self::SETTINGS) + 1);

            return 'Settings · '.ucfirst(str_replace('_', ' ', $tab));
        }

        if (str_starts_with($page, self::REPORTS_DOWNLOADS.'.')) {
            $phase = substr($page, strlen(self::REPORTS_DOWNLOADS) + 1);

            return 'Reports · '.ucfirst($phase).' event';
        }

        if (str_starts_with($page, 'program.')) {
            return 'Program · '.ucfirst(substr($page, 8));
        }

        if (str_starts_with($page, 'catalog.')) {
            return 'Catalog · '.ucfirst(substr($page, 8));
        }

        return str_replace(['event.', '.'], ['', ' · '], $page);
    }
}
