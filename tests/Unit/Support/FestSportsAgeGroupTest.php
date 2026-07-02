<?php

namespace Tests\Unit\Support;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\Student;
use App\Support\FestSportsAgeGroup;
use Tests\TestCase;

class FestSportsAgeGroupTest extends TestCase
{
    public function test_default_cutoff_is_last_december_of_event_year(): void
    {
        $event = new FestEvent([
            'event_type' => 'sports',
            'event_start' => '2026-03-15',
        ]);

        $cutoff = FestSportsAgeGroup::cutoffDate($event);

        $this->assertSame('2026-12-31', $cutoff->format('Y-m-d'));
    }

    public function test_u17_eligibility_uses_age_on_cutoff_date(): void
    {
        $event = new FestEvent([
            'event_type' => 'sports',
            'event_start' => '2026-06-01',
        ]);

        $eligible = new Student(['dob' => '2010-06-01']);
        $tooOld = new Student(['dob' => '2009-06-01']);

        $this->assertTrue(FestSportsAgeGroup::qualifiesForAgeGroup($eligible, 'u17', $event));
        $this->assertFalse(FestSportsAgeGroup::qualifiesForAgeGroup($tooOld, 'u17', $event));
        $this->assertSame(16, FestSportsAgeGroup::ageOnCutoff($eligible, $event));
        $this->assertSame(17, FestSportsAgeGroup::ageOnCutoff($tooOld, $event));
    }

    public function test_u14_eligibility_uses_age_on_cutoff_date(): void
    {
        $event = new FestEvent([
            'event_type' => 'sports',
            'event_start' => '2026-06-01',
        ]);

        $eligible = new Student(['dob' => '2013-06-15']);
        $tooOld = new Student(['dob' => '2012-06-15']);

        $this->assertTrue(FestSportsAgeGroup::qualifiesForAgeGroup($eligible, 'u14', $event));
        $this->assertFalse(FestSportsAgeGroup::qualifiesForAgeGroup($tooOld, 'u14', $event));
    }

    public function test_birth_dates_match_cbse_brackets_for_2026(): void
    {
        $event = new FestEvent([
            'event_type' => 'sports',
            'event_start' => '2026-06-01',
        ]);

        $this->assertSame('2013-01-01', FestSportsAgeGroup::birthDateOnOrAfter('u14', $event)?->format('Y-m-d'));
        $this->assertSame('2010-01-01', FestSportsAgeGroup::birthDateOnOrAfter('u17', $event)?->format('Y-m-d'));
        $this->assertSame('2008-01-01', FestSportsAgeGroup::birthDateOnOrAfter('u19', $event)?->format('Y-m-d'));
    }

    public function test_eligible_age_groups_include_all_under_n_bands_for_item_registration(): void
    {
        $event = new FestEvent([
            'event_type' => 'sports',
            'event_start' => '2026-06-01',
        ]);

        $u14Student = new Student(['dob' => '2013-06-01']);
        $u17Student = new Student(['dob' => '2010-06-01']);
        $young = new Student(['dob' => '2020-06-01']);

        $this->assertSame('u14', FestSportsAgeGroup::assignedAgeGroupForStudent($u14Student, $event));
        $this->assertEquals(['u14', 'u17', 'u19'], FestSportsAgeGroup::eligibleAgeGroupsForStudent($u14Student, $event));

        $this->assertSame('u17', FestSportsAgeGroup::assignedAgeGroupForStudent($u17Student, $event));
        $this->assertEquals(['u17', 'u19'], FestSportsAgeGroup::eligibleAgeGroupsForStudent($u17Student, $event));

        $youngEligible = FestSportsAgeGroup::eligibleAgeGroupsForStudent($young, $event);
        $this->assertContains('u8', $youngEligible);
        $this->assertContains('u14', $youngEligible);
    }

    public function test_u14_item_accepts_any_student_under_fourteen(): void
    {
        $event = new FestEvent([
            'event_type' => 'sports',
            'event_start' => '2026-06-01',
        ]);
        $item = new FestEventItem(['age_group' => 'u14', 'gender' => 'male']);

        $age8 = new Student(['dob' => '2018-06-01', 'gender' => 'male']);
        $age11 = new Student(['dob' => '2015-06-01', 'gender' => 'male']);
        $tooOld = new Student(['dob' => '2012-06-15', 'gender' => 'male']);

        $this->assertTrue(FestSportsAgeGroup::qualifiesForItem($age8, $item, $event));
        $this->assertTrue(FestSportsAgeGroup::qualifiesForItem($age11, $item, $event));
        $this->assertFalse(FestSportsAgeGroup::qualifiesForItem($tooOld, $item, $event));
    }

    public function test_assigned_age_group_is_tightest_band_for_display(): void
    {
        $event = new FestEvent([
            'event_type' => 'sports',
            'event_start' => '2026-06-01',
        ]);

        $u14Student = new Student(['dob' => '2013-06-01']);
        $u17Student = new Student(['dob' => '2010-06-01']);

        $this->assertSame('u14', FestSportsAgeGroup::assignedAgeGroupForStudent($u14Student, $event));
        $this->assertSame('u17', FestSportsAgeGroup::assignedAgeGroupForStudent($u17Student, $event));
    }

    public function test_item_eligibility_label_includes_age_and_gender(): void
    {
        $event = new FestEvent(['event_type' => 'sports']);
        $item = new FestEventItem([
            'age_group' => 'u17',
            'gender' => 'female',
        ]);

        $this->assertSame('Under 17 · Girls', FestSportsAgeGroup::itemEligibilityLabel($item, $event));
    }
}
