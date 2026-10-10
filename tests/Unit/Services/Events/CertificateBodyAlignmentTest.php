<?php

namespace Tests\Unit\Services\Events;

use App\Models\CertificateTemplate;
use PHPUnit\Framework\TestCase;

class CertificateBodyAlignmentTest extends TestCase
{
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
