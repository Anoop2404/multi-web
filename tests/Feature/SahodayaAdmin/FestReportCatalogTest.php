<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Support\FestReportCatalog;
use Tests\TestCase;

class FestReportCatalogTest extends TestCase
{
    public function test_every_export_has_complete_scope_metadata(): void
    {
        $exports = FestReportCatalog::exports('tenant-uuid', 101);

        $this->assertNotEmpty($exports);

        foreach ($exports as $exp) {
            $id = $exp['id'];

            $metadata = FestReportCatalog::scopeMetadata($id);

            $this->assertNotEmpty(
                $metadata,
                "Export '{$id}' is missing scopeMetadata entry in FestReportCatalog (§4.3 contract requirement)."
            );

            $this->assertArrayHasKey('dataset', $metadata, "Export '{$id}' metadata missing 'dataset'.");
            $this->assertContains(
                $metadata['dataset'],
                ['registration', 'schedule', 'results', 'finance', 'catering', 'audit', 'catalog'],
                "Export '{$id}' has invalid dataset family '{$metadata['dataset']}'."
            );

            $this->assertArrayHasKey('supported_scopes', $metadata, "Export '{$id}' metadata missing 'supported_scopes'.");
            $this->assertIsArray($metadata['supported_scopes']);
            $this->assertNotEmpty($metadata['supported_scopes']);

            $this->assertArrayHasKey('supports_competition_phase', $metadata, "Export '{$id}' metadata missing 'supports_competition_phase'.");
            $this->assertIsBool($metadata['supports_competition_phase']);
        }
    }

    public function test_interactive_pages_catalog_returns_valid_list(): void
    {
        $pages = FestReportCatalog::interactivePages('tenant-uuid', 101, 'kalolsavam');

        $this->assertNotEmpty($pages);
        foreach ($pages as $page) {
            $this->assertArrayHasKey('id', $page);
            $this->assertArrayHasKey('label', $page);
            $this->assertArrayHasKey('href', $page);
        }
    }
}
