<?php

namespace App\Support;

/**
 * Official CBSE Class XII (AISSCE) subject codes, as published by the board
 * (academic electives + core languages actually used by BoardExamSubjects'
 * standard subject list and the exam_streams seed data). Keyed lower-case so
 * lookups are case-insensitive — matches the same normalization used by
 * TopperSubjectMarkService::resolveOrCreateSubjectId() (#161).
 *
 * Scope: Class XII only. Class X (AISSE) uses a different code set (e.g.
 * Mathematics is 041 at both levels, but Science/Social Science at Class X —
 * 086/087 — don't exist as Class XII subjects) and isn't covered here.
 */
class CbseSubjectCodes
{
    /** @var array<string, string> lower-cased label => official CBSE code */
    private const CLASS_12_CODES = [
        // Languages
        'english core' => '301',
        'english elective' => '001',
        'hindi core' => '302',
        'hindi elective' => '002',
        'urdu core' => '303',
        'urdu elective' => '003',
        'sanskrit' => '322',
        'sanskrit core' => '322',
        'sanskrit elective' => '022',
        'malayalam' => '112',
        'tamil' => '106',
        'telugu' => '107',
        'kannada' => '115',
        'marathi' => '109',
        'gujarati' => '110',
        'bengali' => '105',
        'punjabi' => '104',
        'odia' => '113',
        'assamese' => '114',
        'french' => '118',
        'german' => '120',

        // Academic electives
        'history' => '027',
        'political science' => '028',
        'geography' => '029',
        'economics' => '030',
        'psychology' => '037',
        'sociology' => '039',
        // 'philosophy' intentionally omitted — could not verify an official CBSE code for
        // it from public sources; it's also not in CBSE's own Class XII subject list, which
        // suggests the "Philosophy" entry seeded into the Humanities stream by
        // 2026_08_06_000002_board_results_cbse_stream_consolidation.php may not be a real
        // CBSE subject at all. Worth confirming directly against cbse.gov.in before shipping.
        'mathematics' => '041',
        'applied mathematics' => '241',
        'physics' => '042',
        'chemistry' => '043',
        'biology' => '044',
        'biotechnology' => '045',
        'engineering graphics' => '046',
        'physical education' => '048',
        'painting' => '049',
        'graphics' => '050',
        'sculpture' => '051',
        'applied art' => '052',
        'applied / commercial art' => '052',
        'business studies' => '054',
        'accountancy' => '055',
        'home science' => '064',
        'informatics practices' => '065',
        'entrepreneurship' => '066',
        'knowledge tradition & practices of india' => '073',
        'knowledge tradition and practices of india' => '073',
        'ktpi' => '073',
        'legal studies' => '074',
        'national cadet corps' => '076',
        'computer science' => '083',
        'fashion studies' => '837',
        'business administration' => '833',
        'mass media studies' => '835',
        'library & information science' => '836',
        'artificial intelligence' => '843',
    ];

    /**
     * Official CBSE Class X (AISSE) subject codes. Deliberately kept separate from
     * CLASS_12_CODES — some labels share text but not code (e.g. "Sanskrit" is 322 at
     * Class XII but Class X only offers Communicative Sanskrit, 122), so a single flat
     * map would be wrong for one level or the other. Display-only for now: not written
     * into the shared `subjects.code` column (see the migration comment) because that
     * table isn't scoped by class, and some labels genuinely differ in code by class.
     */
    private const CLASS_10_CODES = [
        'english' => '184',
        'english language & literature' => '184',
        'english language and literature' => '184',
        'hindi' => '002',
        'hindi course a' => '002',
        'hindi course-a' => '002',
        'hindi course b' => '085',
        'hindi course-b' => '085',
        'malayalam' => '012',
        'sanskrit' => '122',
        'communicative sanskrit' => '122',
        'urdu course a' => '003',
        'urdu course b' => '303',
        'mathematics' => '041',
        'mathematics standard' => '041',
        'mathematics basic' => '241',
        'science' => '086',
        'social science' => '087',
        'psychology' => '037',
        'home science' => '064',
        'painting' => '049',
        'information technology' => '402',
        'computer applications' => '165',
    ];

    /** Look up the official CBSE Class XII code for a subject label, case-insensitively. */
    public static function forClass12Label(string $label): ?string
    {
        $key = mb_strtolower(trim($label));

        return self::CLASS_12_CODES[$key] ?? null;
    }

    /** Look up the official CBSE Class X code for a subject label, case-insensitively. */
    public static function forClass10Label(string $label): ?string
    {
        $key = mb_strtolower(trim($label));

        return self::CLASS_10_CODES[$key] ?? null;
    }

    /** @return array<string, string> a copy of the full lower-case label => code map */
    public static function class12Map(): array
    {
        return self::CLASS_12_CODES;
    }

    /** @return array<string, string> a copy of the full lower-case label => code map */
    public static function class10Map(): array
    {
        return self::CLASS_10_CODES;
    }
}
