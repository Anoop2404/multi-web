<?php

namespace Tests\Unit\Views;

use App\Support\ProgramRouteMap;
use Tests\TestCase;

class FestIdCardPassCardProgramLabelTest extends TestCase
{
    public function test_program_route_map_resolves_event_type_labels(): void
    {
        $this->assertSame('Sports Meet', ProgramRouteMap::labelForEventType('sports'));
        $this->assertSame('Kalotsav', ProgramRouteMap::labelForEventType('kalolsavam'));
        $this->assertSame('Kalotsav', ProgramRouteMap::labelForEventType('kalotsav'));
        $this->assertSame('Kids Fest', ProgramRouteMap::labelForEventType('kids-fest'));
    }

    public function test_pass_card_html_renders_sports_meet_for_sports_event(): void
    {
        $html = view('fest.id-cards.partials.pass-card', [
            'card' => [
                'name' => 'Adithya R',
                'program_label' => 'Sports Meet',
                'is_sports' => true,
                'event_type' => 'sports',
                'academic_year' => '2026-2027',
                'sahodaya_name' => 'Thrissur Sahodaya',
                'phase_name' => 'U17 Badminton Girls',
            ],
            'clusterName' => 'Thrissur Sahodaya',
            'clusterLogoSrc' => null,
            'eventTitle' => 'Sports Meet 2026-27',
        ])->render();

        $this->assertStringContainsString('Sports Meet', $html);
        $this->assertStringNotContainsString('Kalotsav', $html);
    }

    public function test_pass_card_pdf_renders_sports_meet_for_sports_event(): void
    {
        $html = view('fest.id-cards.partials.pass-card-pdf', [
            'card' => [
                'name' => 'Adithya R',
                'program_label' => 'Sports Meet',
                'is_sports' => true,
                'event_type' => 'sports',
                'academic_year' => '2026-2027',
                'sahodaya_name' => 'Thrissur Sahodaya',
                'phase_name' => 'U17 Badminton Girls',
            ],
            'clusterName' => 'Thrissur Sahodaya',
            'clusterLogoSrc' => null,
            'eventTitle' => 'Sports Meet 2026-27',
        ])->render();

        $this->assertStringContainsString('Sports Meet', $html);
        $this->assertStringNotContainsString('Kalotsav', $html);
    }

    public function test_pass_card_html_renders_kalotsav_for_kalolsavam_event(): void
    {
        $html = view('fest.id-cards.partials.pass-card', [
            'card' => [
                'name' => 'Fathima Noor',
                'program_label' => 'Kalotsav',
                'is_sports' => false,
                'event_type' => 'kalolsavam',
                'academic_year' => '2026-2027',
                'sahodaya_name' => 'Thrissur Sahodaya',
            ],
            'clusterName' => 'Thrissur Sahodaya',
            'clusterLogoSrc' => null,
            'eventTitle' => 'Kalotsav 2026-27',
        ])->render();

        $this->assertStringContainsString('Kalotsav', $html);
        $this->assertStringNotContainsString('Sports Meet', $html);
    }

    public function test_pass_card_renders_dob_and_sports_category(): void
    {
        $card = [
            'name' => 'Aaradhya V Menon',
            'program_label' => 'Sports Meet',
            'is_sports' => true,
            'event_type' => 'sports',
            'academic_year' => '2026-2027',
            'sahodaya_name' => 'Malappuram Central Sahodaya',
            'category' => 'Under 8',
            'dob' => '15 Aug 2018',
            'venue' => 'Sacred Heart CMI Public School',
            'event_date' => '30th Aug - 21st Sep 2026',
            'id_label' => 'Reg ID',
            'id_number' => '122',
        ];

        $html = view('fest.id-cards.partials.pass-card', [
            'card' => $card,
            'clusterName' => 'Malappuram Central Sahodaya',
            'clusterLogoSrc' => null,
            'eventTitle' => 'Sports Meet 2026-27',
        ])->render();

        $this->assertStringContainsString('15 Aug 2018', $html);
        $this->assertStringContainsString('DOB', $html);
        $this->assertStringContainsString('Under 8', $html);
        $this->assertStringNotContainsString('Under VIII', $html);

        $pdfHtml = view('fest.id-cards.partials.pass-card-pdf', [
            'card' => $card,
            'clusterName' => 'Malappuram Central Sahodaya',
            'clusterLogoSrc' => null,
            'eventTitle' => 'Sports Meet 2026-27',
        ])->render();

        $this->assertStringContainsString('15 Aug 2018', $pdfHtml);
        $this->assertStringContainsString('DOB', $pdfHtml);
        $this->assertStringContainsString('Under 8', $pdfHtml);
        $this->assertStringNotContainsString('Under VIII', $pdfHtml);
    }
}
