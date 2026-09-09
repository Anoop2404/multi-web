<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Support\TenantPublicSite;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class SettingsController extends SchoolAdminController
{
    public function index()
    {
        $settings = $this->school->settings()->get()->pluck('value', 'key')->toArray();

        return $this->inertia('School/Settings/Index', [
            'settings' => $settings,
            'publicWebsiteEnabled' => TenantPublicSite::isEnabled($this->school),
            'paymentDetails' => $this->school->paymentDetails(),
            'paymentQrCodeUrl' => $this->school->paymentQrCodeUrl(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            'map_url' => 'nullable|url|max:1000',
            'weekday_hours' => 'nullable|string|max:100',
            'saturday_hours' => 'nullable|string|max:100',
            'address_city' => 'nullable|string|max:100',
            'facebook' => 'nullable|url|max:255',
            'youtube' => 'nullable|url|max:255',
            'instagram' => 'nullable|url|max:255',
            'whatsapp_number' => 'nullable|string|max:20',
            'cbse_affiliation_number' => 'nullable|string|max:50',
            'cbse_badge_show' => 'nullable|boolean',
            'logo' => 'nullable|image|max:2048',
            'seo_title' => 'nullable|string|max:70',
            'seo_description' => 'nullable|string|max:160',
            'seo_keywords' => 'nullable|string|max:500',
            'seo_tagline' => 'nullable|string|max:200',
            'locale' => 'nullable|string|in:en,ml',
            'public_website_enabled' => 'nullable|boolean',
            'payment_bank_name' => 'nullable|string|max:150',
            'payment_account_no' => 'nullable|string|max:50',
            'payment_ifsc' => 'nullable|string|max:20',
            'payment_upi' => 'nullable|string|max:100',
            'payment_qr_code' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:3072',
            'remove_payment_qr_code' => 'nullable|boolean',
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $path = TenantStorage::storeLogo($request->file('logo'), $this->school->id);
            $this->school->setSetting('logo', $path);
        }

        // Save contact info
        $contactFields = ['phone', 'email', 'address', 'city', 'pincode', 'map_url', 'weekday_hours', 'saturday_hours'];
        if (collect($contactFields)->contains(fn (string $field) => $request->exists($field))) {
            $contact = $this->school->getSetting('contact', []) ?? [];
            foreach ($contactFields as $field) {
                if ($request->exists($field)) {
                    $contact[$field] = $data[$field] ?? null;
                }
            }
            $this->school->setSetting('contact', $contact);
        }

        if ($request->exists('address_city')) {
            $this->school->setSetting('address_city', $data['address_city'] ?? null);
        }

        // Bank/UPI details this school receives food payments to when designated the
        // "host school" for an event (FestEvent::food_payee_type === 'host_school') — see
        // FestFoodOrderController::show(), which surfaces this to ordering schools.
        $paymentFields = ['payment_bank_name', 'payment_account_no', 'payment_ifsc', 'payment_upi'];
        if (collect($paymentFields)->contains(fn (string $field) => $request->exists($field)) || $request->hasFile('payment_qr_code') || $request->boolean('remove_payment_qr_code')) {
            $payment = $this->school->paymentDetails();
            if ($request->exists('payment_bank_name')) {
                $payment['bank_name'] = $data['payment_bank_name'] ?? null;
            }
            if ($request->exists('payment_account_no')) {
                $payment['account_no'] = $data['payment_account_no'] ?? null;
            }
            if ($request->exists('payment_ifsc')) {
                $payment['ifsc'] = $data['payment_ifsc'] ?? null;
            }
            if ($request->exists('payment_upi')) {
                $payment['upi'] = $data['payment_upi'] ?? null;
            }
            if ($request->hasFile('payment_qr_code')) {
                $payment['qr_code'] = TenantStorage::storeUploadedFile($request->file('payment_qr_code'), 'payment_qr_codes');
            } elseif ($request->boolean('remove_payment_qr_code')) {
                $payment['qr_code'] = null;
            }
            $this->school->setSetting('payment', $payment);
        }

        $isSettingsForm = $request->routeIs('school.settings.update');

        // The Settings screen always owns these fields, including intentional blanks.
        if ($isSettingsForm) {
            $widgets = $this->school->getWidgets();
            $widgets['social_links'] = array_merge($widgets['social_links'] ?? [], [
                'facebook' => $data['facebook'] ?? null,
                'youtube' => $data['youtube'] ?? null,
                'instagram' => $data['instagram'] ?? null,
            ]);
            $widgets['whatsapp_number'] = $data['whatsapp_number'] ?? null;
            $widgets['cbse_affiliation_number'] = $data['cbse_affiliation_number'] ?? null;
            $widgets['cbse_badge_show'] = $data['cbse_badge_show'] ?? false;
            $this->school->setSetting('widgets', $widgets);

            $seo = [
                'title' => $data['seo_title'] ?? null,
                'description' => $data['seo_description'] ?? null,
                'keywords' => $data['seo_keywords'] ?? null,
                'tagline' => $data['seo_tagline'] ?? null,
            ];
            $existing = $this->school->settings()->where('key', 'seo')->first()?->value ?? [];
            $this->school->setSetting('seo', array_merge($existing, $seo));

            $this->school->setSetting('locale', $data['locale'] ?? 'en');

            TenantPublicSite::setEnabled($this->school, $request->boolean('public_website_enabled'));
        }

        return back()->with('success', 'Settings saved.');
    }
}
