<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class FestIdCardCustomCardTest extends TestCase
{
    public function test_dynamic_text_format_combines_editable_labels_and_card_values(): void
    {
        $html = view('fest.id-cards.partials.custom-card', [
            'cardWidthMm' => 89,
            'cardHeightMm' => 135,
            'backgroundUrl' => null,
            'card' => [
                'category' => 'II',
                'roll_no' => '10495',
                'gender_upper' => 'FEMALE',
            ],
            'fields' => [[
                'key' => 'student_info_inline',
                'type' => 'text',
                'source' => 'student_info_inline',
                'text_format' => 'CATEGORY: {category} | ROLL NO: {roll_no} | GENDER: {gender_upper}',
            ]],
        ])->render();

        $this->assertStringContainsString('CATEGORY: II | ROLL NO: 10495 | GENDER: FEMALE', $html);
    }

    public function test_participating_item_list_numbers_only_the_registered_items(): void
    {
        $html = view('fest.id-cards.partials.custom-card', [
            'cardWidthMm' => 89,
            'cardHeightMm' => 135,
            'backgroundUrl' => null,
            'card' => [
                'participating_items' => ['Essay Writing', 'Elocution', 'Quiz'],
            ],
            'fields' => [[
                'key' => 'participating_items',
                'type' => 'item_list',
                'source' => 'participating_items',
                'top' => 84,
                'left' => 5,
                'width' => 90,
                'height' => 11,
                'columns' => 2,
                'max_items' => 7,
            ]],
        ])->render();

        $this->assertStringContainsString('1) Essay Writing', $html);
        $this->assertStringContainsString('2) Elocution', $html);
        $this->assertStringContainsString('3) Quiz', $html);
        $this->assertStringNotContainsString('4)', $html);
    }

    public function test_participating_item_list_never_renders_more_than_seven_items(): void
    {
        $html = view('fest.id-cards.partials.custom-card', [
            'cardWidthMm' => 89,
            'cardHeightMm' => 135,
            'backgroundUrl' => null,
            'card' => [
                'participating_items' => collect(range(1, 8))->map(fn ($number) => "Item {$number}")->all(),
            ],
            'fields' => [[
                'key' => 'participating_items',
                'type' => 'item_list',
                'source' => 'participating_items',
                'columns' => 2,
                'max_items' => 7,
            ]],
        ])->render();

        $this->assertStringContainsString('7) Item 7', $html);
        $this->assertStringNotContainsString('Item 8', $html);
    }
}
