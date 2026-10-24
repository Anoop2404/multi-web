<?php

namespace Tests\Unit\Support;

use App\Support\FestCategoryOrder;
use PHPUnit\Framework\TestCase;

class FestCategoryOrderTest extends TestCase
{
    public function test_number_sort_handles_roman_labels_and_opaque_keys(): void
    {
        $labels = ['cc9' => 'Category III (Classes VIII, IX & X)', 'cc2' => 'Category IV', 'cc7' => 'Category I', 'cc1' => 'Category II', 'cc4' => 'Category X'];
        $this->assertSame(['cc7', 'cc1', 'cc9', 'cc2', 'cc4'], FestCategoryOrder::sort(array_keys($labels), $labels));
        $this->assertSame(['u7', 'u9', 'u11'], FestCategoryOrder::sort(['u11', 'u7', 'u9'], []));
    }

    public function test_custom_order_keeps_new_categories_and_name_sort_is_independent(): void
    {
        $labels = ['a' => 'Zebras', 'b' => 'Apples', 'c' => 'Bananas'];
        $this->assertSame(['c', 'b', 'a'], FestCategoryOrder::sort(['a', 'b', 'c'], $labels, ['category_display_sort' => 'custom', 'category_display_order' => ['c']]));
        $this->assertSame(['b', 'c', 'a'], FestCategoryOrder::sort(['a', 'b', 'c'], $labels, ['category_display_sort' => 'name']));
    }
}
