<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills `subjects.code` with the real, official CBSE Class XII subject
 * code (e.g. "301" for English Core) for every existing subject row whose
 * label matches one we could verify — replacing the auto-derived slug
 * (e.g. "ENGLISHCOR") that BoardResultMastersController::seedStandardSubjects()
 * and storeSubject() previously generated from the label text.
 *
 * Codes are inlined here (not read from App\Support\CbseSubjectCodes) on
 * purpose, matching this codebase's existing convention of not coupling
 * migrations to application code (see 2026_08_06_000002_...) — a migration
 * should keep working even if the app class it might have referenced is
 * later changed or removed. App\Support\CbseSubjectCodes carries the same
 * map for runtime use (seedStandardSubjects()/storeSubject()); keep both in
 * sync if CBSE publishes a revision.
 *
 * Scope: Class XII only — see CbseSubjectCodes for why Class X isn't covered.
 * "Philosophy" is deliberately left out — no verified official CBSE code was
 * found for it; confirm against cbse.gov.in before adding one.
 *
 * Skips a row instead of updating it if the target code is already taken by
 * a different row in the same (sahodaya_id) scope, to avoid tripping the
 * `subjects_sahodaya_id_code_unique` constraint — logs what it skipped so it
 * can be resolved by hand.
 */
return new class extends Migration
{
    private const CLASS_12_CODES = [
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
        'history' => '027',
        'political science' => '028',
        'geography' => '029',
        'economics' => '030',
        'psychology' => '037',
        'sociology' => '039',
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

    public function up(): void
    {
        if (! Schema::hasTable('subjects')) {
            return;
        }

        $map = self::CLASS_12_CODES;
        $updated = 0;
        $skipped = [];

        DB::table('subjects')->orderBy('id')->chunkById(200, function ($rows) use ($map, &$updated, &$skipped) {
            foreach ($rows as $row) {
                $code = $map[mb_strtolower(trim((string) $row->label))] ?? null;
                if ($code === null || $code === $row->code) {
                    continue;
                }

                $conflict = DB::table('subjects')
                    ->where('id', '!=', $row->id)
                    ->where('code', $code)
                    ->where(function ($q) use ($row) {
                        $row->sahodaya_id === null
                            ? $q->whereNull('sahodaya_id')
                            : $q->where('sahodaya_id', $row->sahodaya_id);
                    })
                    ->exists();

                if ($conflict) {
                    $skipped[] = "#{$row->id} \"{$row->label}\" (sahodaya {$row->sahodaya_id}) — code {$code} already used by another subject in scope";

                    continue;
                }

                DB::table('subjects')->where('id', $row->id)->update([
                    'code' => $code,
                    'updated_at' => now(),
                ]);
                $updated++;
            }
        });

        logger()->info('[2026_08_20_000001] Set CBSE codes on '.$updated.' subject row(s).');
        if ($skipped !== []) {
            logger()->warning('[2026_08_20_000001] Skipped '.count($skipped)." subject row(s) due to code conflicts:\n".implode("\n", $skipped));
        }
    }

    public function down(): void
    {
        // Not reversible — the pre-migration codes were auto-derived from the label and
        // not recorded anywhere, so there's nothing meaningful to restore them to.
    }
};
