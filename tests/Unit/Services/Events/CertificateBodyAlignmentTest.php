<?php

namespace Tests\Unit\Services\Events;

use App\Models\CertificateTemplate;
use PHPUnit\Framework\TestCase;

class CertificateBodyAlignmentTest extends TestCase
{
    public function test_justify_overrides_authored_alignment_but_preserves_participation_table_alignment(): void
    {
        $rows = CertificateTemplate::bodyParagraphs(
            '<div style="text-align:center!important;color:black"><p align="left">This is the body.</p>{participation_items_box}</div>',
            ['participation_items_box' => '<table><tr><td style="text-align:left">Race</td></tr></table>'],
            false,
            ['body' => ['align' => 'justify']],
        );
        $this->assertStringContainsString('text-align:justify!important;color:black', $rows[0]);
        $this->assertStringContainsString('align="justify"', $rows[0]);
        $this->assertStringContainsString('<td style="text-align:left">Race</td>', $rows[0]);
    }

    public function test_justified_prose_wraps_without_changing_token_content_or_paragraphs(): void
    {
        $body = "This is to certify<br>that {recipient_name}\nhas participated.\n\n{participation_items_box}";
        $rows = CertificateTemplate::bodyParagraphs($body, [
            'recipient_name' => 'Student',
            'participation_items_box' => '<div>Race<br>Jump</div>',
        ], false, ['body' => ['align' => 'justify']]);

        $this->assertSame('This is to certify that Student has participated.', $rows[0]);
        $this->assertStringContainsString('<br>', $rows[1]);
        $this->assertCount(2, $rows);
        $centered = CertificateTemplate::bodyParagraphs('First<br>Second', [], false, ['body' => ['align' => 'center']]);
        $this->assertSame('First<br>Second', $centered[0]);
    }
}
