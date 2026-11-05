<?php

namespace Tests\Unit\Services\Events;

use App\Services\Events\FestCertificateService;
use PHPUnit\Framework\TestCase;

class SportsCertificateCategoryTest extends TestCase
{
    public function test_sports_category_keeps_only_the_under_age_label(): void
    {
        $method = new \ReflectionMethod(FestCertificateService::class, 'sportsCertificateCategory');
        foreach ([
            'Under 14 Boys' => 'Under 14',
            'Under 14 Girls (Classes 7 & 8)' => 'Under 14',
            'Under 12 (Kiddies)' => 'Under 12',
            'UNDER-17 Girls' => 'Under 17',
            'Under 19' => 'Under 19',
            'Open' => 'Open',
        ] as $label => $expected) {
            $this->assertSame($expected, $method->invoke(null, $label));
        }
    }
}
