<?php

namespace Tests\Unit\Services\Migration;

use App\Services\Migration\KannurLegacyMembershipImporter;
use App\Services\Migration\LegacySqlInsertParser;
use Tests\TestCase;

class KannurLegacyMembershipImporterTest extends TestCase
{
    private LegacySqlInsertParser $parser;

    private KannurLegacyMembershipImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new LegacySqlInsertParser;
        $this->importer = app(KannurLegacyMembershipImporter::class);
    }

    public function test_parser_reads_legacy_tables_from_sql_dump(): void
    {
        $sql = <<<'SQL'
INSERT INTO `tb_payments` (`payment_id`, `school_id`, `year_id`, `payment_method`, `payment_type`, `payment_details`, `payment_date`, `receipt_file`, `created_date`, `amount`, `is_verified`, `is_locked`, `supporting_file`) VALUES
(96, 18089, 1, 'CASH', 'CASH', '151818,151824,151834', '0000-00-00', '1780483508_proof.jpg', '2026-06-03 16:15:08', '7000', 'Y', 'N', NULL);

INSERT INTO `tb_schools` (`school_id`, `school_name`, `email`, `school_is`, `status`, `highest_class`, `website`, `phone_no`, `land_no`, `fax_no`, `affiliation_no`, `created_date`, `is_deleted`, `is_approved`, `deleted_by`, `deleted_date`, `user_id`, `pincode`, `noc`, `district`, `taluk`) VALUES
(338, 'test_school', 'fasalekd@gmail.com', NULL, NULL, 12, '', '9496076221', NULL, NULL, '102010', '2026-06-03 16:02:58', 'Y', 'N', NULL, NULL, 18089, '', '', NULL, '');
SQL;

        $payments = $this->parser->parseTable($sql, 'tb_payments');
        $schools = $this->parser->parseTable($sql, 'tb_schools');

        $this->assertCount(1, $payments);
        $this->assertSame('18089', $payments[0]['school_id']);
        $this->assertSame('7000', $payments[0]['amount']);
        $this->assertCount(1, $schools);
        $this->assertSame('18089', $schools[0]['user_id']);
    }

    public function test_combined_slabs_include_flat_registration_fee(): void
    {
        $slabs = $this->importer->combinedSlabs(
            [
                ['min_strength' => '0', 'max_strength' => '500', 'fees' => '1000', 'year_id' => '1'],
                ['min_strength' => '501', 'max_strength' => '1000', 'fees' => '1500', 'year_id' => '1'],
            ],
            5000.0,
            '2026-27',
        );

        $this->assertSame([
            ['academic_year' => '2026-27', 'min_students' => 0, 'max_students' => 500, 'amount' => 6000.0],
            ['academic_year' => '2026-27', 'min_students' => 501, 'max_students' => 1000, 'amount' => 6500.0],
        ], $slabs);
    }

    public function test_legacy_academic_year_is_normalized(): void
    {
        $this->assertSame('2026-27', $this->importer->normalizeAcademicYear('2026-2027'));
    }

    public function test_linked_school_users_exclude_admin_accounts(): void
    {
        $linked = $this->importer->linkedSchoolUserIds([
            ['user_id' => '1111', 'role' => '1', 'is_deleted' => 'N'],
            ['user_id' => '18186', 'role' => '2', 'is_deleted' => 'N'],
            ['user_id' => '18177', 'role' => '2', 'is_deleted' => 'Y'],
        ]);

        $this->assertSame(['18186' => true], $linked);
    }

    public function test_legacy_class_names_map_to_global_categories(): void
    {
        $this->assertSame('PRE', $this->importer->legacyClassToCategoryCode('KG'));
        $this->assertSame('PRY', $this->importer->legacyClassToCategoryCode('Primary'));
        $this->assertSame('UP', $this->importer->legacyClassToCategoryCode('7'));
        $this->assertSame('SEC', $this->importer->legacyClassToCategoryCode('10'));
        $this->assertSame('SrSEC', $this->importer->legacyClassToCategoryCode('12'));
    }
}
