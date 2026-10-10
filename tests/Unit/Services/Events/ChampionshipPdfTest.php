<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use Tests\TestCase;

class ChampionshipPdfTest extends TestCase
{
    public function test_pdf_groups_categories_and_preserves_joint_winners(): void
    {
        $html = view('fest.reports.individual-championship', [
            'event' => new FestEvent(['title' => 'Test Fest']), 'orgName' => 'Sahodaya',
            'preview' => true, 'labels' => ['hs' => 'High School'],
            'rows' => collect([
                ['category' => 'hs', 'rank' => 1, 'points' => 20, 'student' => ['name' => 'First Student'], 'school' => 'School A'],
                ['category' => 'hs', 'rank' => 1, 'points' => 20, 'student' => ['name' => 'Joint Student'], 'school' => 'School B'],
            ]),
        ])->render();
        $this->assertSame(1, substr_count($html, '<h2>High School</h2>'));
        $this->assertStringContainsString('First Student', $html);
        $this->assertStringContainsString('Joint Student', $html);
        $this->assertStringContainsString('Admin preview', $html);
        $this->assertStringStartsWith('%PDF-', \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->output());
    }
}
