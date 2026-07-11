<?php

namespace Tests\Unit\Services\BoardResults;

use App\Support\AchievementCatalog;
use PHPUnit\Framework\TestCase;

class AchievementCatalogTest extends TestCase
{
    public function test_normalizes_legacy_labels_to_keys(): void
    {
        $this->assertSame('academic', AchievementCatalog::normalizeCategory('Academic'));
        $this->assertSame('sports', AchievementCatalog::normalizeCategory('Sports'));
        $this->assertSame('school', AchievementCatalog::normalizeLevel('School Level'));
        $this->assertSame('national', AchievementCatalog::normalizeLevel('National'));
    }

    public function test_unknown_category_falls_back_to_other(): void
    {
        $this->assertSame('other', AchievementCatalog::normalizeCategory('Mystery'));
    }
}
