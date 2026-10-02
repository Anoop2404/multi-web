<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestFoodCoupon;
use App\Models\Tenant;
use App\Services\Events\FestIdCardQrService;
use App\Support\PdfGenerator;
use App\Support\TenantBranding;
use Illuminate\Http\Request;

class FestFoodCouponController extends SchoolAdminController
{
    public function index(string $tenantId, Request $request)
    {
        $school = $this->school;

        $events = FestEvent::where('tenant_id', $school->parent_id)
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'event_start', 'status']);

        $eventId = $request->query('event_id') ? (int) $request->query('event_id') : null;
        $event = $eventId ? FestEvent::find($eventId) : null;
        $mealType = $request->query('meal_type');

        $query = FestFoodCoupon::where('school_id', $school->id)
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->when($mealType, fn ($q) => $q->where('meal_type', $mealType))
            ->with('event')
            ->orderByDesc('valid_date')
            ->orderBy('meal_type')
            ->orderBy('sequence_no');

        $coupons = $query->get();

        return $this->inertia('School/Fest/FoodCoupons', [
            'events'   => $events,
            'event'    => $event ? $event->only('id', 'title', 'event_type', 'event_start', 'event_end') : null,
            'coupons'  => $coupons,
            'filters'  => [
                'event_id' => $eventId,
                'meal_type' => $mealType,
            ],
            'mealTypes'=> FestFoodCoupon::MEAL_LABELS,
        ]);
    }

    public function print(string $tenantId, FestEvent $event, Request $request, FestIdCardQrService $qrService)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $query = FestFoodCoupon::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->where('status', 'issued');

        if ($meal = $request->query('meal_type')) {
            $query->where('meal_type', $meal);
        }

        $coupons = $query->orderBy('meal_type')
            ->orderBy('sequence_no')
            ->get();

        if ($coupons->isEmpty()) {
            return back()->with('error', 'No issued food coupons found to print.');
        }

        $sahodaya = Tenant::find($event->tenant_id);
        $bgDataUri = $event->foodCouponBgImageDataUri($sahodaya);
        $baseUrl = url('/');

        $preparedCoupons = [];
        foreach ($coupons as $c) {
            $verifyUrl = $c->verificationUrl($baseUrl);
            $qrData = $qrService->dataUri($verifyUrl, 300);
            $preparedCoupons[] = [
                'id' => $c->id,
                'coupon_code' => $c->coupon_code,
                'qr_token' => $c->qr_token,
                'meal_type' => $c->meal_type,
                'formatted_date' => $c->valid_date?->format('d M Y') ?? 'N/A',
                'head_count' => 1,
                'is_extra' => $c->is_extra,
                'school_name' => $this->school->name,
                'qr_src' => $qrData,
            ];
        }

        $perSheet = (int) $request->query('per_sheet', $request->query('per_page', 12));
        if (! in_array($perSheet, [10, 12], true)) {
            $perSheet = 12;
        }

        $isPreview = $request->boolean('preview') || $request->boolean('inline');
        $filename = 'food-coupons-'.$this->school->school_prefix.'-'.$event->id."-{$perSheet}per-sheet.pdf";

        return PdfGenerator::fromView(
            view: 'fest.catering.food-coupons',
            data: [
                'event'     => $event,
                'school'    => $this->school,
                'sahodaya'  => $sahodaya,
                'logoSrc'   => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
                'bgDataUri' => $bgDataUri,
                'layout'    => $event->foodCouponLayout($sahodaya),
                'coupons'   => $preparedCoupons,
                'perSheet'  => $perSheet,
            ],
            filename: $filename,
            inline: $isPreview,
            isLandscape: false,
            requireBrowserRenderer: !empty(config('services.pdf_converter.url')),
        );
    }
}
