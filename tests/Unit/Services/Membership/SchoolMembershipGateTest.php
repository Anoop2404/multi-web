<?php

namespace Tests\Unit\Services\Membership;

use App\Models\Tenant;
use App\Services\Membership\SchoolMembershipGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchoolMembershipGateTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $status): Tenant
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'S', 'is_active' => true,
        ]);

        return Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Sch',
            'parent_id' => $sahodaya->id, 'is_active' => true, 'membership_status' => $status,
        ]);
    }

    public function test_approved_membership_unlocks_programs(): void
    {
        $gate = app(SchoolMembershipGate::class);
        $this->assertTrue($gate->isPaid($this->school('approved')));
        $this->assertNull($gate->blockReason($this->school('approved')));
    }

    public function test_pending_membership_blocks_programs(): void
    {
        $gate = app(SchoolMembershipGate::class);
        $pending = $this->school('pending');

        $this->assertFalse($gate->isPaid($pending));
        $this->assertNotNull($gate->blockReason($pending));
    }

    public function test_rejected_membership_blocks_programs(): void
    {
        $gate = app(SchoolMembershipGate::class);
        $this->assertFalse($gate->isPaid($this->school('rejected')));
    }
}
