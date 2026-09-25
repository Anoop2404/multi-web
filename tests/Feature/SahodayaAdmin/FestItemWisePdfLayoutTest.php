<?php

namespace Tests\Feature\SahodayaAdmin;

use Tests\TestCase;

/**
 * Item-wise PDF layout: per item, participants grouped under a heading per school; the item
 * header carries class category, gender and Individual/Group; and the marks columns can be
 * left off for a plain participant list.
 */
class FestItemWisePdfLayoutTest extends TestCase
{
    private function html(bool $showMarks): string
    {
        $row = fn (int $id, string $school, string $name, string $type, ?string $gender) => [
            'id' => $id, 'item_id' => 7, 'item_title' => 'Group Dance', 'item_code' => 'GD1',
            'category_label' => 'Category 2 — Classes 5, 6 & 7', 'gender_label' => $gender, 'type_label' => $type,
            'phase_name' => null, 'region_name' => null, 'school_name' => $school, 'participant' => $name,
            'reg_no' => "R{$id}", 'chest_no' => $id, 'status' => 'approved', 'grade' => 'A', 'position' => 1, 'score' => '88', 'points' => 5,
        ];
        $rows = [
            $row(1, 'Zed School', 'Zoe', 'Group', 'Girls'),
            $row(2, 'Alpha School', 'Amy', 'Group', 'Girls'),
            $row(3, 'Alpha School', 'Ann', 'Group', 'Girls'),
        ];

        return view('fest.reports.item-wise-marks', [
            'sahodaya' => (object) ['name' => 'S'], 'event' => (object) ['title' => 'Layout Fest'], 'rows' => $rows,
            'showPhase' => false, 'showRegion' => false, 'showPoints' => false, 'showMarks' => $showMarks,
            'generatedBy' => 'x', 'generatedAt' => 'now', 'forWhom' => null, 'logoSrc' => null,
        ])->render();
    }

    public function test_item_header_shows_category_gender_and_type_and_rows_group_by_school(): void
    {
        $html = $this->html(true);

        $this->assertStringContainsString('Category 2 — Classes 5, 6 &amp; 7', $html);
        $this->assertStringContainsString('Gender: <strong>Girls</strong>', $html);
        $this->assertStringContainsString('Type: <strong>Group</strong>', $html);

        // School headings, alphabetical, each with its own count -- Alpha (2) before Zed (1).
        $this->assertMatchesRegularExpression('/ALPHA SCHOOL.*?&middot; 2.*?Amy.*?Ann.*?ZED SCHOOL.*?&middot; 1.*?Zoe/s', $html);
        // The School column is gone (the heading row replaces it).
        $this->assertStringNotContainsString('>School<', $html);
        $this->assertStringContainsString('>Chest<', $html);
        $this->assertStringContainsString('>Grade<', $html);
        $this->assertStringContainsString('>Rank<', $html);
        $this->assertStringContainsString('>Score<', $html);
    }

    public function test_marks_columns_can_be_left_off(): void
    {
        $html = $this->html(false);

        foreach (['Chest', 'Grade', 'Rank', 'Score'] as $col) {
            $this->assertStringNotContainsString(">{$col}<", $html);
        }
        $this->assertStringContainsString('>Participant<', $html);
        $this->assertStringContainsString('>Reg No<', $html);
        $this->assertStringContainsString('>Status<', $html);
        $this->assertStringContainsString('colspan="4"', $html);
    }
}
