<?php

namespace App\Http\Controllers\SahodayaAdmin\Concerns;

use Illuminate\Http\Request;

/**
 * Shared by every controller whose "print/export for several items" action accepts a
 * checkbox multi-select (?item_ids=a,b,c) plus phase/competition-area quick filters
 * (?phase_id=, ?area_id=) -- the picker on Sahodaya/Events/BulkSheets.vue drives all of
 * them with the same three query params. Originally FestMarkEntryController-only (see
 * its bulkSheets()/markEntrySheet()/cumulativeSheet()/resultDeclarationSheet()); moved
 * here once FestChestNumberController needed the identical parsing.
 */
trait ParsesBulkSheetFilters
{
    /** @return array{0: list<int>, 1: int|null, 2: int|null} */
    private function parseBulkSheetFilters(Request $request): array
    {
        $itemIds = array_values(array_filter(array_map(
            'intval',
            array_filter(explode(',', (string) $request->input('item_ids', '')), fn ($v) => $v !== ''),
        )));

        return [$itemIds, $request->integer('phase_id') ?: null, $request->integer('area_id') ?: null];
    }
}
