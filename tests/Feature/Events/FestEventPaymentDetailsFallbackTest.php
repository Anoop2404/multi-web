<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FestEventPaymentDetailsFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_falls_back_to_sahodaya_profile_payment_details_when_custom_instructions_are_null(): void
    {
        $sahodaya = Tenant::create([
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'subdomain' => 'test-sahodaya-'.uniqid(),
        ]);

        $profile = SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'payment_bank_name' => 'State Bank of India',
            'payment_account_no' => '99988877766',
            'payment_ifsc' => 'SBIN0009999',
            'payment_upi' => 'default@oksbi',
            'payment_instructions' => 'Include school code in transfer reference.',
            'payment_qr_code' => 'payment_qr_codes/default_qr.png',
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Test Fest Event',
            'event_type' => 'kalotsav',
            'fee_settings' => ['fee_model' => 'none'],
        ]);

        $this->assertEquals($profile->paymentDetailsText(), $event->paymentDetailsText());
        $this->assertEquals('/storage/payment_qr_codes/default_qr.png', $event->paymentQrCodeUrl());
    }

    public function test_event_uses_custom_payment_instructions_and_qr_code_when_configured(): void
    {
        $sahodaya = Tenant::create([
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya 2',
            'subdomain' => 'test-sahodaya-'.uniqid(),
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'payment_bank_name' => 'State Bank of India',
            'payment_account_no' => '99988877766',
            'payment_ifsc' => 'SBIN0009999',
            'payment_upi' => 'default@oksbi',
            'payment_qr_code' => 'payment_qr_codes/default_qr.png',
        ]);

        $customText = "Custom Event Bank Account:\nBank: HDFC Bank\nAccount: 112233445566\nIFSC: HDFC0001234\nUPI: event@okhdfc";
        $customQr = 'payment_qr_codes/event_custom_qr.png';

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Test Custom Fest Event',
            'event_type' => 'kalotsav',
            'fee_settings' => [
                'fee_model' => 'none',
                'payment_instructions' => $customText,
                'payment_qr_code' => $customQr,
            ],
        ]);

        $this->assertEquals($customText, $event->paymentDetailsText());
        $this->assertEquals('/storage/payment_qr_codes/event_custom_qr.png', $event->paymentQrCodeUrl());
    }

    public function test_phase_resolves_custom_instructions_or_falls_back_to_event_or_sahodaya(): void
    {
        $sahodaya = Tenant::create([
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya 3',
            'subdomain' => 'test-sahodaya-'.uniqid(),
        ]);

        $profile = SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'payment_bank_name' => 'Canara Bank',
            'payment_account_no' => '111222333',
            'payment_ifsc' => 'CNRB0001111',
            'payment_qr_code' => 'payment_qr_codes/sahodaya_qr.png',
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Phased Event Test',
            'event_type' => 'kalotsav',
            'fee_settings' => [
                'payment_instructions' => "Event Level Account:\nBank: SBI\nAccount: 555666777",
                'payment_qr_code' => 'payment_qr_codes/event_qr.png',
            ],
        ]);

        $phase1 = FestEventPhase::create([
            'event_id' => $event->id,
            'name' => 'Phase 1 - Regional',
            'payment_instructions' => "Phase 1 Account:\nBank: Axis Bank\nAccount: 999111222",
            'payment_qr_code' => 'payment_qr_codes/phase1_qr.png',
        ]);

        $phase2 = FestEventPhase::create([
            'event_id' => $event->id,
            'name' => 'Phase 2 - State Finale',
        ]);

        // Phase 1 has custom payment details
        $this->assertStringContainsString('Axis Bank', $phase1->paymentDetailsText($event, $profile));
        $this->assertEquals('/storage/payment_qr_codes/phase1_qr.png', $phase1->paymentQrCodeUrl($event, $profile));

        // Phase 2 falls back to Event level
        $this->assertStringContainsString('SBI', $phase2->paymentDetailsText($event, $profile));
        $this->assertEquals('/storage/payment_qr_codes/event_qr.png', $phase2->paymentQrCodeUrl($event, $profile));
    }
}
