<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestFoodCatalogItem;
use App\Models\FestFoodMenuItem;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestFoodMenuSyncService;
use App\Services\Events\FestPartitionService;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FestFoodMenuController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event, FestPartitionService $partitions)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        // Sorted in PHP, not via orderBy('meal_type') — meal_type is a plain varchar, so a
        // SQL sort would put 'dinner' before 'lunch' alphabetically. See
        // FestFoodMenuItem::sortForDisplay() for the canonical chronological order.
        $items = FestFoodMenuItem::sortForDisplay(FestFoodMenuItem::forEvent($event->id)->get());
        $isPartitionedHub = $partitions->isPartitionedHub($event);

        $catalogItems = FestFoodCatalogItem::forEvent($event->id)
            ->withCount('menuItems')
            ->orderBy('name')
            ->get()
            ->map(fn (FestFoodCatalogItem $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'default_price' => (float) $c->default_price,
                'is_active' => $c->is_active,
                'slots_count' => $c->menu_items_count,
            ]);

        return $this->inertia('Sahodaya/Events/FoodMenu', $this->withEventActivity($event, FestPageActivity::FOOD_MENU, [
            'event' => $event->only('id', 'title', 'event_start', 'event_end', 'food_payee_type', 'food_host_school_id', 'conducting_school_id', 'require_payment_for_coupons'),
            'hierarchy' => $event->hierarchyContext(),
            'menuItems' => $items,
            'catalogItems' => $catalogItems,
            'mealTypes' => $this->mealTypeOptions(),
            'eventDates' => $this->eventDateOptions($event),
            'schoolOptions' => Tenant::where('parent_id', $this->sahodaya->id)
                ->where('type', 'school')
                ->orderBy('name')
                ->get(['id', 'name']),
            'isPartitionedHub' => $isPartitionedHub,
            'foodRegionSummary' => $isPartitionedHub ? $partitions->foodRegionDrillDownSummary($event) : [],
        ]));
    }

    /**
     * Copy this hub's menu items + payee settings onto every region partition child —
     * additive/idempotent (FestFoodMenuSyncService never overwrites a region's own
     * customizations). For hubs that add/edit menu items after regions already exist;
     * spawn-time copying (FestPartitionService::spawnPartition()) covers new regions.
     */
    public function syncToRegions(string $tenantId, FestEvent $event, FestFoodMenuSyncService $sync, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($event->parent_event_id, 422, 'Sync the menu from the hub event, not a partition.');

        $updated = $sync->copyMenuToAllPartitions($event);

        $audit->festEvent($event, FestPageActivity::FOOD_MENU, 'fest.food_menu.synced_to_regions', 'Food menu applied to region partitions', [
            'regions_updated' => count($updated),
        ]);

        return back()->with('success', count($updated).' region partition(s) updated with this menu.');
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
                Rule::exists(Tenant::class, 'id')->where('parent_id', $this->sahodaya->id)->where('type', 'school'),
            ],
            'require_payment_for_coupons' => ['nullable', 'boolean'],
        ]);

        $event->update([
            'food_payee_type' => $data['food_payee_type'],
            'food_host_school_id' => $data['food_payee_type'] === 'host_school' ? $data['food_host_school_id'] : null,
            'require_payment_for_coupons' => $data['require_payment_for_coupons'] ?? $event->require_payment_for_coupons ?? false,
        ]);

        $audit->festEvent($event, FestPageActivity::FOOD_MENU, 'fest.food_menu.payee_updated', 'Food payment payee updated', [
            'payee_type' => $data['food_payee_type'],
        ]);

        return back()->with('success', 'Food payment settings updated. This applies to new bills going forward.');
    }

    /** Add a reusable food item to this event's catalog. Not itself schedulable — see assignCatalogItems(). */
    public function catalogStore(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'default_price' => 'required|numeric|min:0|max:99999.99',
        ]);

        $item = FestFoodCatalogItem::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $event->id,
            ...$data,
        ]);

        $audit->festEvent($event, FestPageActivity::FOOD_MENU, 'fest.food_catalog.created', "Food item '{$item->name}' added to catalog", [
            'catalog_item_id' => $item->id,
        ]);

        return back()->with('success', 'Food item added.');
    }

    public function catalogUpdate(Request $request, string $tenantId, FestEvent $event, FestFoodCatalogItem $catalogItem, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($catalogItem->event_id !== $event->id, 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'default_price' => 'required|numeric|min:0|max:99999.99',
            'is_active' => 'nullable|boolean',
        ]);

        $catalogItem->update([
            ...$data,
            'is_active' => $data['is_active'] ?? false,
        ]);

        $audit->festEvent($event, FestPageActivity::FOOD_MENU, 'fest.food_catalog.updated', "Food item '{$catalogItem->name}' updated", [
            'catalog_item_id' => $catalogItem->id,
        ]);

        return back()->with('success', 'Food item updated.');
    }

    public function catalogDestroy(string $tenantId, FestEvent $event, FestFoodCatalogItem $catalogItem, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($catalogItem->event_id !== $event->id, 404);

        $name = $catalogItem->name;
        // Menu items already scheduled from this catalog entry keep their own name/price
        // snapshot and are untouched — only the traceability link is cleared. Done
        // explicitly here rather than relying solely on the migration's nullOnDelete FK
        // action, since adding a foreign key to an already-existing SQLite table doesn't
        // reliably cascade through Schema::table()'s table-rebuild strategy.
        FestFoodMenuItem::where('catalog_item_id', $catalogItem->id)->update(['catalog_item_id' => null]);
        $catalogItem->delete();

        $audit->festEvent($event, FestPageActivity::FOOD_MENU, 'fest.food_catalog.deleted', "Food item '{$name}' removed from catalog", []);

        return back()->with('success', 'Food item removed from catalog.');
    }

    /**
     * Bulk-schedule a set of catalog items onto one date+meal slot in a single action —
     * mirrors FestEventPhaseService::assignItemsToPhase()'s validation shape (both sides
     * scoped to this event via Rule::exists()->where()), but CREATES fest_food_menu_items
     * rows rather than overwriting a single FK, since one food item is typically served on
     * many dates/meals rather than belonging to exactly one bucket. Idempotent per
     * (date, meal, name): re-assigning an item already scheduled for that slot is silently
     * skipped rather than creating a duplicate.
     */
    public function assignCatalogItems(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'catalog_item_ids' => 'required|array|min:1',
            'catalog_item_ids.*' => ['integer', Rule::exists('fest_food_catalog_items', 'id')->where('event_id', $event->id)],
            'menu_date' => $this->menuDateRules($event),
            'meal_type' => ['required', 'string', Rule::in(array_keys($this->mealTypeOptions()))],
        ]);

        $catalogItems = FestFoodCatalogItem::where('event_id', $event->id)
            ->whereIn('id', $data['catalog_item_ids'])
            ->get();

        $created = 0;
        foreach ($catalogItems as $catalogItem) {
            // whereDate(), not where() — menu_date is validated as a plain 'Y-m-d' string,
            // but the date-cast column is persisted with a time component, so a raw string
            // equality check here would never match and silently create duplicates.
            $exists = FestFoodMenuItem::where('event_id', $event->id)
                ->whereDate('menu_date', $data['menu_date'])
                ->where('meal_type', $data['meal_type'])
                ->where('name', $catalogItem->name)
                ->exists();

            if ($exists) {
                continue;
            }

            FestFoodMenuItem::create([
                'tenant_id' => $this->sahodaya->id,
                'event_id' => $event->id,
                'catalog_item_id' => $catalogItem->id,
                'menu_date' => $data['menu_date'],
                'meal_type' => $data['meal_type'],
                'name' => $catalogItem->name,
                'description' => $catalogItem->description,
                'price' => $catalogItem->default_price,
                'is_available' => true,
                'sort_order' => 0,
            ]);
            $created++;
        }

        $skipped = $catalogItems->count() - $created;

        $audit->festEvent($event, FestPageActivity::FOOD_MENU, 'fest.food_menu.catalog_assigned', "{$created} catalog item(s) assigned to {$data['meal_type']} on {$data['menu_date']}", [
            'menu_date' => $data['menu_date'],
            'meal_type' => $data['meal_type'],
            'count' => $created,
        ]);

        $message = "Assigned {$created} item(s).";
        if ($skipped > 0) {
            $message .= " {$skipped} already existed for that date/meal and were skipped.";
        }

        return back()->with('success', $message);
    }

    public function store(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'menu_date' => $this->menuDateRules($event),
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
            'menu_date' => $this->menuDateRules($event),
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
        return FestFoodMenuItem::MEAL_TYPES;
    }

    /**
     * Menu dates are bound to this specific event's own run — the hub and a region/phase
     * leaf event can have different event_start/event_end, so binding to $event (whichever
     * one the controller is scoped to) already does the right thing per region without any
     * extra region-specific logic here. Either bound is skipped if not set on the event,
     * so events without confirmed dates yet aren't blocked from adding a menu.
     */
    private function menuDateRules(FestEvent $event): array
    {
        $rules = ['required', 'date'];

        if ($event->event_start) {
            $rules[] = 'after_or_equal:'.$event->event_start->format('Y-m-d');
        }
        if ($event->event_end) {
            $rules[] = 'before_or_equal:'.$event->event_end->format('Y-m-d');
        }

        return $rules;
    }

    /**
     * Every date in the event's run, for the admin UI to offer as a picker instead of a
     * free-form date field — capped well above any real fest's length as a sanity bound in
     * case event_start/event_end are mis-set far apart. Empty (and the UI falls back to a
     * plain date input) when either bound isn't set yet.
     */
    private function eventDateOptions(FestEvent $event): array
    {
        if (! $event->event_start || ! $event->event_end || $event->event_start->gt($event->event_end)) {
            return [];
        }

        $days = $event->event_start->diffInDays($event->event_end);
        if ($days > 60) {
            return [];
        }

        $dates = [];
        for ($date = $event->event_start->copy(); $date->lte($event->event_end); $date->addDay()) {
            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }
}
