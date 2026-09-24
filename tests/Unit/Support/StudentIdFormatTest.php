<?php

namespace Tests\Unit\Support;

use App\Support\StudentIdFormat;
use Tests\TestCase;

class StudentIdFormatTest extends TestCase
{
    public function test_strips_the_stu_year_prefix_down_to_the_sequence(): void
    {
        $this->assertSame('1111', StudentIdFormat::shortId('STU/27/1111'));
    }

    public function test_shortens_each_member_of_a_comma_joined_team_reg_no(): void
    {
        $this->assertSame('1111, 1112', StudentIdFormat::shortId('STU/27/1111, STU/27/1112'));
    }

    public function test_returns_a_value_with_no_slash_unchanged(): void
    {
        $this->assertSame('REG-Alpha', StudentIdFormat::shortId('REG-Alpha'));
    }

    public function test_null_and_blank_stay_null(): void
    {
        $this->assertNull(StudentIdFormat::shortId(null));
        $this->assertNull(StudentIdFormat::shortId(''));
    }
}
