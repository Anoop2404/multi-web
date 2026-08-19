<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Support\SchoolClassCategoryResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolClassCategoryResolverTest extends TestCase
{
    use RefreshDatabase;
    public function test_resolves_highest_class_from_profile_payload(): void
    {
        $school = new Tenant([
            'id' => 'test-school-12',
            'application_payload' => [
                'highest_class' => 'Class 12',
            ],
        ]);

        $tier = SchoolClassCategoryResolver::feeTierFor($school);

        $this->assertSame('senior_secondary', $tier);
    }

    public function test_resolves_highest_class_secondary_from_profile_payload(): void
    {
        $school = new Tenant([
            'id' => 'test-school-10',
            'application_payload' => [
                'highest_class' => 'Class 10',
            ],
        ]);

        $tier = SchoolClassCategoryResolver::feeTierFor($school);

        $this->assertSame('secondary', $tier);
    }
}
