<?php

namespace Tests\Unit;

use App\Support\IndianAmountInWords;
use PHPUnit\Framework\TestCase;

class IndianAmountInWordsTest extends TestCase
{
    public function test_formats_rupees_with_paise(): void
    {
        $this->assertSame('Five Thousand Rupees Only', IndianAmountInWords::rupees(5000));
        $this->assertStringContainsString('Ninety Two', IndianAmountInWords::rupees(92));
        $this->assertStringContainsString('Paise', IndianAmountInWords::rupees(92.50));
    }
}
