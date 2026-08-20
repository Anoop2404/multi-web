<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
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
}
