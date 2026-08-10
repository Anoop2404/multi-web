<?php

namespace Tests\Unit\Support;

use App\Support\FestReportCatalog;
use Tests\TestCase;

/**
 * Catalog contract test required by
 * docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md §4.3: "Unknown or incomplete
 * metadata must fail a catalog contract test." Every entry FestReportCatalog::exports()
 * returns must carry the dataset/supported_scopes/supports_competition_phase fields
 * added by FestReportCatalog::scopeMetadata() (Phase 2).
 */
class FestReportCatalogMetadataTest extends TestCase
{
    public function test_every_export_has_complete_scope_metadata(): void
    {
        $exports = FestReportCatalog::exports('tenant-fixture', 1);

        $this->assertNotEmpty($exports);

        foreach ($exports as $export) {
            $id = $export['id'];

            $this->assertArrayHasKey('dataset', $export, "Export '{$id}' is missing 'dataset' in FestReportCatalog::SCOPE_METADATA.");
            $this->assertIsString($export['dataset']);
            $this->assertNotSame('', $export['dataset']);

            $this->assertArrayHasKey('supported_scopes', $export, "Export '{$id}' is missing 'supported_scopes'.");
            $this->assertIsArray($export['supported_scopes']);
            $this->assertNotEmpty($export['supported_scopes'], "Export '{$id}' declares zero supported scopes.");
            foreach ($export['supported_scopes'] as $scope) {
                $this->assertContains($scope, ['self', 'combined', 'region', 'finale', 'cluster'], "Export '{$id}' declares unknown scope '{$scope}'.");
            }

            $this->assertArrayHasKey('supports_competition_phase', $export, "Export '{$id}' is missing 'supports_competition_phase'.");
            $this->assertIsBool($export['supports_competition_phase']);
        }
    }

    public function test_scope_metadata_lookup_for_unknown_id_is_empty_not_guessed(): void
    {
        $this->assertSame([], FestReportCatalog::scopeMetadata('this-export-id-does-not-exist'));
    }
}
