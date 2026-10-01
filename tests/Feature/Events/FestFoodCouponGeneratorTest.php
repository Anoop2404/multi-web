<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestFoodBill;
use App\Models\FestFoodCoupon;
use App\Models\FestFoodOrderItem;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestFoodCouponGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;
    private Tenant $school;
    private User $sahodayaAdmin;
    private FestEvent $event;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('shared');
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Demo Sahodaya',
            'domain' => 'demo-sahodaya.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $this->sahodaya->id,
            'prefix' => 'DS',
            'student_data_mode' => 'counts_only',
        ]);

        $this->school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'St Marys HSS',
            'parent_id' => $this->sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $this->sahodayaAdmin = User::factory()->create([
            'tenant_id' => $this->sahodaya->id,
            'email' => 'admin@demo-sahodaya.test',
            'email_verified_at' => now(),
        ]);
        $this->sahodayaAdmin->assignRole('sahodaya_admin');

        $this->event = FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Sahodaya Fest 2026',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
            'food_payee_type' => 'sahodaya',
            'event_start' => now()->toDateString(),
            'event_end' => now()->addDays(2)->toDateString(),
        ]);
    }

    public function test_coupon_code_serialization_for_three_food_types(): void
    {
        // 1. Breakfast serialization (BF-0001, BF-0002)
        $bf1 = FestFoodCoupon::generateSerializedCode($this->event, 'breakfast');
        $this->assertSame('BF-0001', $bf1['code']);

        FestFoodCoupon::create([
            'event_id' => $this->event->id,
            'coupon_code' => $bf1['code'],
            'sequence_no' => $bf1['sequence_no'],
            'qr_token' => FestFoodCoupon::generateQrToken(),
            'meal_type' => 'breakfast',
            'valid_date' => now()->toDateString(),
            'head_count' => 1,
            'status' => 'issued',
        ]);

        $bf2 = FestFoodCoupon::generateSerializedCode($this->event, 'breakfast');
        $this->assertSame('BF-0002', $bf2['code']);

        // 2. Lunch serialization (LN-0001)
        $ln1 = FestFoodCoupon::generateSerializedCode($this->event, 'lunch');
        $this->assertSame('LN-0001', $ln1['code']);

        FestFoodCoupon::create([
            'event_id' => $this->event->id,
            'coupon_code' => $ln1['code'],
            'sequence_no' => $ln1['sequence_no'],
            'qr_token' => FestFoodCoupon::generateQrToken(),
            'meal_type' => 'lunch',
            'valid_date' => now()->toDateString(),
            'head_count' => 1,
            'status' => 'issued',
        ]);

        // 3. Dinner serialization (DN-0001)
        $dn1 = FestFoodCoupon::generateSerializedCode($this->event, 'dinner');
        $this->assertSame('DN-0001', $dn1['code']);
    }

    public function test_qr_token_is_non_serialized_and_unique(): void
    {
        $token1 = FestFoodCoupon::generateQrToken();
        $token2 = FestFoodCoupon::generateQrToken();

        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
        $this->assertNotSame($token1, $token2);
        $this->assertSame(10, strlen($token1));
        // Must be uppercase alphanumeric
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{10}$/', $token1);
    }

    public function test_admin_can_generate_extra_buffer_coupons(): void
    {
        $response = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-coupons.generate-extra', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
        ]), [
            'meal_type' => 'lunch',
            'valid_date' => now()->toDateString(),
            'quantity' => 5,
            'school_id' => null,
            'notes' => 'Volunteer & Guest Buffer',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $coupons = FestFoodCoupon::where('event_id', $this->event->id)->get();
        $this->assertCount(5, $coupons);
        $this->assertTrue($coupons->every(fn ($c) => $c->is_extra === true));
        $this->assertSame(['LN-0001', 'LN-0002', 'LN-0003', 'LN-0004', 'LN-0005'], $coupons->pluck('coupon_code')->all());
        $this->assertTrue($coupons->every(fn ($c) => !empty($c->qr_token)));
    }

    public function test_admin_can_ungenerate_unredeemed_coupons_and_preserves_redeemed(): void
    {
        // Generate 3 extra coupons
        $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-coupons.generate-extra', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
        ]), [
            'meal_type' => 'breakfast',
            'valid_date' => now()->toDateString(),
            'quantity' => 3,
        ]);

        $coupons = FestFoodCoupon::where('event_id', $this->event->id)->get();
        $this->assertCount(3, $coupons);

        // Redeem the first coupon
        $first = $coupons->first();
        $first->update(['status' => 'redeemed', 'redeemed_at' => now()]);

        // Ungenerate all unredeemed
        $response = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-coupons.ungenerate', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
        ]), [
            'scope' => 'all_unredeemed',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Only the redeemed coupon must survive
        $remaining = FestFoodCoupon::where('event_id', $this->event->id)->get();
        $this->assertCount(1, $remaining);
        $this->assertSame($first->id, $remaining->first()->id);
        $this->assertSame('redeemed', $remaining->first()->status);
    }

    public function test_admin_can_upload_and_remove_template_background_image(): void
    {
        $file = UploadedFile::fake()->image('coupon_template_bg.jpg', 600, 400);

        $uploadResp = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-coupons.template-background', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
        ]), [
            'background_image' => $file,
        ]);

        $uploadResp->assertRedirect();
        $this->event->refresh();
        $this->assertNotNull($this->event->food_coupon_bg_image);

        // Remove background image
        $removeResp = $this->actingAs($this->sahodayaAdmin)->delete(route('sahodaya.events.food-coupons.template-background.remove', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
        ]));

        $removeResp->assertRedirect();
        $this->event->refresh();
        $this->assertNull($this->event->food_coupon_bg_image);
    }

    public function test_public_qr_code_verification_and_redemption(): void
    {
        $codeData = FestFoodCoupon::generateSerializedCode($this->event, 'dinner');
        $token = FestFoodCoupon::generateQrToken();

        $coupon = FestFoodCoupon::create([
            'event_id' => $this->event->id,
            'coupon_code' => $codeData['code'],
            'sequence_no' => $codeData['sequence_no'],
            'qr_token' => $token,
            'meal_type' => 'dinner',
            'valid_date' => now()->toDateString(),
            'head_count' => 1,
            'status' => 'issued',
            'school_id' => $this->school->id,
        ]);

        // Access public verification URL
        $verifyResp = $this->get(route('food-coupons.verify', ['token' => $token]));
        $verifyResp->assertStatus(200);
        $verifyResp->assertSee('DN-0001');
        $verifyResp->assertSee($token);
        $verifyResp->assertSee('Valid Coupon');

        // Redeem via verification page
        $redeemResp = $this->post(route('food-coupons.verify.redeem', ['token' => $token]));
        $redeemResp->assertRedirect();

        $coupon->refresh();
        $this->assertSame('redeemed', $coupon->status);
        $this->assertNotNull($coupon->redeemed_at);
    }

    public function test_print_10_per_sheet_pdf_downloads_successfully(): void
    {
        // Create 10 coupons to fill 1 A4 sheet
        for ($i = 0; $i < 10; $i++) {
            $codeData = FestFoodCoupon::generateSerializedCode($this->event, 'lunch');
            FestFoodCoupon::create([
                'event_id' => $this->event->id,
                'coupon_code' => $codeData['code'],
                'sequence_no' => $codeData['sequence_no'],
                'qr_token' => FestFoodCoupon::generateQrToken(),
                'meal_type' => 'lunch',
                'valid_date' => now()->toDateString(),
                'head_count' => 1,
                'status' => 'issued',
                'school_id' => $this->school->id,
            ]);
        }

        $printResp = $this->actingAs($this->sahodayaAdmin)->get(route('sahodaya.events.food-coupons.print', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
        ]));

        $printResp->assertStatus(200);
        $this->assertSame('application/pdf', $printResp->headers->get('content-type'));
    }

    public function test_tea_and_other_meal_types_can_be_issued_and_serialized(): void
    {
        // 1. Tea serialization (TE-0001)
        $te1 = FestFoodCoupon::generateSerializedCode($this->event, 'tea');
        $this->assertSame('TE-0001', $te1['code']);

        $teaCoupon = FestFoodCoupon::create([
            'event_id' => $this->event->id,
            'coupon_code' => $te1['code'],
            'sequence_no' => $te1['sequence_no'],
            'qr_token' => FestFoodCoupon::generateQrToken(),
            'meal_type' => 'tea',
            'valid_date' => now()->toDateString(),
            'head_count' => 10,
            'status' => 'issued',
            'school_id' => $this->school->id,
        ]);

        $this->assertSame('tea', $teaCoupon->meal_type);
        $this->assertSame('TE-0001', $teaCoupon->coupon_code);

        // 2. Extra coupon generation endpoint with tea
        $resp = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-coupons.generate-extra', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
        ]), [
            'meal_type' => 'tea',
            'valid_date' => now()->toDateString(),
            'quantity' => 2,
        ]);

        $resp->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fest_food_coupons', [
            'event_id' => $this->event->id,
            'meal_type' => 'tea',
            'coupon_code' => 'TE-0002',
        ]);
        $this->assertDatabaseHas('fest_food_coupons', [
            'event_id' => $this->event->id,
            'meal_type' => 'tea',
            'coupon_code' => 'TE-0003',
        ]);
    }

    public function test_issue_from_bill_supports_tea_meal_type(): void
    {
        $bill = FestFoodBill::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $this->event->id,
            'school_id' => $this->school->id,
            'status' => FestFoodBill::STATUS_OPEN,
            'amount_total' => 500,
            'amount_paid' => 500,
        ]);

        FestFoodOrderItem::create([
            'bill_id' => $bill->id,
            'menu_date' => now()->toDateString(),
            'meal_type' => 'tea',
            'item_name' => 'Evening Tea & Snacks',
            'unit_price' => 25,
            'quantity' => 20,
            'line_total' => 500,
        ]);

        $resp = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-coupons.issue-from-bill', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
        ]));

        $resp->assertSessionHasNoErrors();
        $coupons = FestFoodCoupon::where('event_id', $this->event->id)
            ->where('school_id', $this->school->id)
            ->where('meal_type', 'tea')
            ->orderBy('sequence_no')
            ->get();

        $this->assertCount(20, $coupons);
        $this->assertSame('TE-0001', $coupons->first()->coupon_code);
        $this->assertSame('TE-0020', $coupons->last()->coupon_code);
        $this->assertTrue($coupons->every(fn ($c) => $c->head_count === 1));
        $this->assertSame(20, $coupons->pluck('qr_token')->unique()->count());
    }
}

