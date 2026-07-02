<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestFoodCoupon;
use Barryvdh\DomPDF\Facade\Pdf;

class FestFoodCouponController extends SchoolAdminController
{
    public function index(string $tenantId)
    {
        $school = $this->school;

        $events = FestEvent::where('tenant_id', $school->parent_id)
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'event_start', 'status']);

        $eventId = request()->query('event_id') ? (int) request()->query('event_id') : null;

        $coupons = FestFoodCoupon::where('school_id', $school->id)
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->with('event')
            ->orderByDesc('valid_date')
            ->get();

        return $this->inertia('School/Fest/FoodCoupons', [
            'events'  => $events,
            'coupons' => $coupons,
            'filters' => ['event_id' => $eventId],
        ]);
    }

    public function print(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $coupons = FestFoodCoupon::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->where('status', 'issued')
            ->orderBy('coupon_code')
            ->get();

        return Pdf::loadView('fest.catering.food-coupons', [
            'event'   => $event,
            'school'  => $this->school,
            'coupons' => $coupons,
        ])->download('food-coupons-'.$this->school->school_prefix.'-'.$event->id.'.pdf');
    }
}
