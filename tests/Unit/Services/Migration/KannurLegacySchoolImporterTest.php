<?php

namespace Tests\Unit\Services\Migration;

use App\Services\Migration\KannurLegacySchoolImporter;
use Tests\TestCase;

class KannurLegacySchoolImporterTest extends TestCase
{
    private KannurLegacySchoolImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = app(KannurLegacySchoolImporter::class);
    }

    public function test_resolve_email_from_legacy_school_row(): void
    {
        $email = $this->importer->resolveEmail([
            'email' => 'cmmurali27@gmail.com',
        ]);

        $this->assertSame('cmmurali27@gmail.com', $email);
    }

    public function test_map_highest_class_number_to_label(): void
    {
        $this->assertSame('Class 12', $this->importer->mapHighestClass('12'));
        $this->assertSame('Class 10', $this->importer->mapHighestClass('10'));
    }

    public function test_allocate_prefix_avoids_duplicates(): void
    {
        $used = ['930389' => true];

        $prefix = $this->importer->allocatePrefix('930389', 'MES PUBLIC SCHOOL', '18191', $used);

        $this->assertNotSame('930389', $prefix);
        $this->assertArrayHasKey($prefix, $used);
    }

    public function test_build_payload_includes_affiliation_and_email(): void
    {
        $payload = $this->importer->buildPayload(
            [
                'school_name'    => 'MES PUBLIC SCHOOL',
                'phone_no'       => '04902960996',
                'highest_class'  => '10',
                'user_id'        => '18191',
            ],
            'cmmurali27@gmail.com',
            '930389',
            'MES',
        );

        $this->assertSame('cmmurali27@gmail.com', $payload['school_email']);
        $this->assertSame('930389', $payload['cbse_affiliation']);
        $this->assertSame('Class 10', $payload['highest_class']);
    }
}
