<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestFoodMenuItem;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FestFoodMenuController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $items = FestFoodMenuItem::forEvent($event->id)
            ->orderBy('menu_date')
            ->orderBy('meal_type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->inertia('Sahodaya/Events/FoodMenu', $this->withEventActivity($event, FestPageActivity::FOOD_MENU, [
            'event' => $event->only('id', 'title', 'event_start', 'event_end', 'food_payee_type', 'food_host_school_id', 'conducting_school_id'),
            'menuItems' => $items,
            'mealTypes' => $this->mealTypeOptions(),
            'schoolOptions' => Tenant::where('parent_id', $this->sahodaya->id)
                ->where('type', 'school')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]));
    }

    /** Which school (if any) food payments for this event are payable to. */
    public function updatePayee(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'food_payee_type' => ['required', Rule::in(['sahodaya', 'host_school'])],
            'food_host_school_id' => [
                Rule::requiredIf($request->input('food_payee_type') === 'host_school'),
                'nullable',
                Rule::exists('tenants', 'id')->where('parent_id', $this->sahodaya->id)->where('type', 'school'),
            ],
        ]);

        $event->update([
            'food_payee_type' => $data['food_payee_type'],
            'food_host_school_id' => $data['food_payee_type'] === 'host_school' ? $data['food_host_school_id'] : null,
        ]);

        $audit->festEvent($event, FestPageActivity::FOOD_MENU, 'fest.food_menu.payee_updated', 'Food payment payee updated', [
            'payee_type' => $data['food_payee_type'],
        ]);

        return back()->with('success', 'Food payment settings updated. This applies to new bills going forward.');
    }

    public function store(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'menu_date' => 'required|date',
            'meal_type' => ['required', 'string', Rule::in(array_keys($this->mealTypeOptions()))],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0|max:99999.99',
            'is_available' => 'nullable|boolean',
            'max_per_school' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $item = FestFoodMenuItem::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $event->id,
            'is_available' => $data['is_available'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            ...collect($data)->except(['is_available', 'sort_order'])->all(),
        ]);

        $audit->festEvent($event, FestPageActivity::FOOD_MENU, 'fest.food_menu.created', "Menu item '{$item->name}' added", [
            'menu_item_id' => $item->id,
        ]);

        return back()->with('success', 'Menu item added.');
    }

    public function update(Request $request, string $tenantId, FestEvent $event, FestFoodMenuItem $menuItem, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($menuItem->event_id !== $event->id, 404);

        $data = $request->validate([
            'menu_date' => 'required|date',
            'meal_type' => ['required', 'string', Rule::in(array_keys($this->mealTypeOptions()))],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0|max:99999.99',
            'is_available' => 'nullable|boolean',
            'max_per_school' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $menuItem->update([
            ...$data,
            'is_available' => $data['is_available'] ?? false,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        $audit->festEvent($event, FestPageActivity::FOOD_MENU, 'fest.food_menu.updated', "Menu item '{$menuItem->name}' updated", [
            'menu_item_id' => $menuItem->id,
        ]);

        return back()->with('success', 'Menu item updated.');
    }

    public function destroy(string $tenantId, FestEvent $event, FestFoodMenuItem $menuItem, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($menuItem->event_id !== $event->id, 404);

        $name = $menuItem->name;
        // Existing order-item rows keep their snapshotted name/price (menu_item_id just
        // becomes null via the FK's nullOnDelete) — deleting a menu item never rewrites
        // history on bills that already ordered it.
        $menuItem->delete();

        $audit->festEvent($event, FestPageActivity::FOOD_MENU, 'fest.food_menu.deleted', "Menu item '{$name}' removed", []);

        return back()->with('success', 'Menu item removed.');
    }

    private function mealTypeOptions(): array
    {
        return [
            'breakfast' => 'Breakfast',
            'lunch' => 'Lunch',
            'snacks' => 'Snacks',
            'tea' => 'Tea',
            'dinner' => 'Dinner',
            'other' => 'Other',
        ];
    }
}
