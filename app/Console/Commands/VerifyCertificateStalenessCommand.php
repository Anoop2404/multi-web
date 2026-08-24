<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use App\Models\Tenant;
use App\Services\Events\FestCertificateService;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

/**
 * Authoritative staleness sweep: recomputes each cached certificate's content hash from
 * current source data/template and compares it to what was stored at render time (see
 * FestCertificateService::contentHash(), RenderCertificateChunkJob). Self-sufficient —
 * it doesn't depend on any write-path marker having fired (see
 * CertificateStalenessMarker, wired into mark-entry/template-save paths separately) to
 * eventually catch drift; those markers only make is_stale flip sooner than this sweep's
 * own schedule would.
 */
class VerifyCertificateStalenessCommand extends Command
{
    protected $signature = 'certificates:verify-staleness';

    protected $description = 'Recompute cached certificates\' content hashes and flag any that no longer match their source data or template';

    public function handle(FestCertificateService $service): int
    {
        $checked = 0;
        $flagged = 0;

        $sahodayas = Tenant::query()->sahodayas()->where('is_active', true)->get();

        foreach ($sahodayas as $sahodaya) {
            try {
                TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($service, &$checked, &$flagged) {
                    [$tenantChecked, $tenantFlagged] = $this->verifyForCurrentTenant($service);
                    $checked += $tenantChecked;
                    $flagged += $tenantFlagged;
                });
            } catch (\Throwable $e) {
                // One Sahodaya's database not being ready (or any other per-tenant
                // failure) shouldn't abort the sweep for every other Sahodaya — the
                // whole point of this command is a broad, resilient pass.
                $this->warn("Skipped {$sahodaya->id}: {$e->getMessage()}");
            }
        }

        $this->info("Checked {$checked} certificate(s), flagged {$flagged} as stale.");

        return self::SUCCESS;
    }

    /** @return array{0: int, 1: int} [checked, flagged] */
    private function verifyForCurrentTenant(FestCertificateService $service): array
    {
        $checked = 0;
        $flagged = 0;

        Certificate::whereNotNull('content_hash')
            ->whereNotNull('file_path')
            ->where('is_stale', false)
            ->chunkById(200, function ($certificates) use ($service, &$checked, &$flagged) {
                $templateCache = [];
                $participantsCache = [];

                foreach ($certificates as $certificate) {
                    $checked++;

                    try {
                        $context = $service->renderContext($certificate, null, $templateCache, $participantsCache);
                        $freshHash = $service->contentHash($context);
                        $matches = $freshHash === $certificate->content_hash;

                        $certificate->update($matches
                            ? ['stale_checked_at' => now()]
                            : ['is_stale' => true, 'stale_checked_at' => now()]);

                        if (! $matches) {
                            $flagged++;
                        }
                    } catch (\Throwable) {
                        // Source row vanished entirely (e.g. the participant was hard-
                        // deleted) — nothing to re-hash against; leave it as-is rather
                        // than let one bad row abort the whole chunk.
                        continue;
                    }
                }
            });

        return [$checked, $flagged];
    }
}
