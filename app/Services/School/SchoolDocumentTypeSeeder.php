<?php

namespace App\Services\School;

use App\Models\SchoolDocumentType;
use App\Models\Tenant;

class SchoolDocumentTypeSeeder
{
    /** @var list<array{code: string, name: string, is_required: bool, validity_months: ?int}> */
    private const DEFAULTS = [
        ['code' => 'affiliation_letter', 'name' => 'Affiliation letter', 'is_required' => true, 'validity_months' => 12],
        ['code' => 'recognition_certificate', 'name' => 'Recognition certificate', 'is_required' => true, 'validity_months' => 12],
        ['code' => 'fire_safety', 'name' => 'Fire safety certificate', 'is_required' => false, 'validity_months' => 12],
    ];

    public function seedForSahodaya(string $sahodayaId): void
    {
        foreach (self::DEFAULTS as $i => $row) {
            SchoolDocumentType::firstOrCreate(
                ['sahodaya_id' => $sahodayaId, 'code' => $row['code']],
                [
                    'name'             => $row['name'],
                    'is_required'      => $row['is_required'],
                    'validity_months'  => $row['validity_months'],
                    'sort_order'       => $i + 1,
                    'is_active'        => true,
                ],
            );
        }
    }
}
