<?php

namespace App\Services\External;

use App\Models\ExternalSahodaya;

class ExternalConductService
{
    /**
     * Process certified-offline result sheet upload for external conduct.
     *
     * @param array<int, array{item_code: string, winner_name: string, school_name: string, position: int, score: float}> $results
     * @return array{processed: int, evidence_file: string, status: string}
     */
    public function processCertifiedOfflineResults(ExternalSahodaya $sahodaya, array $results, string $evidenceFilePath): array
    {
        if (empty($evidenceFilePath)) {
            throw new \InvalidArgumentException('Evidence result sheet file is mandatory for certified-offline conduct.');
        }

        return [
            'external_sahodaya_id' => $sahodaya->id,
            'processed'            => count($results),
            'evidence_file'        => $evidenceFilePath,
            'status'               => 'results_certified',
            'certified_at'         => now()->toIso8601String(),
        ];
    }
}
