<?php

namespace App\Http\Controllers\SahodayaAdmin\Concerns;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Support\FestIdCardTemplates;
use App\Support\TenantBranding;
use Illuminate\Http\Request;

trait BuildsFestIdCardResponses
{
    /** @return array<string, mixed> */
    protected function idCardFilters(Request $request): array
    {
        return array_filter([
            'school_id'       => $request->input('school_id'),
            'item_id'         => $request->integer('item_id') ?: null,
            'scope'           => in_array($request->input('scope'), ['item', 'event', 'head', 'head_all'], true)
                ? $request->input('scope') : null,
            'head_id'         => $request->integer('head_id') ?: null,
            'layout'          => in_array($request->input('layout'), ['individual', 'team'], true)
                ? $request->input('layout') : null,
            'participant_ids' => $request->input('participant_ids'),
            'student_id'      => $request->integer('student_id') ?: null,
            'volunteer_ids'   => $request->input('volunteer_ids'),
            'staff_ids'       => $request->input('staff_ids'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected function idCardSheetView(Request $request): string
    {
        return FestIdCardTemplates::sheetView($request->input('template'));
    }

    /** @param  list<array<string, mixed>>  $cards */
    /** @param  list<array{item_title: string, cards: list<array<string, mixed>>}>|null  $sections */
    protected function idCardViewData(
        FestEvent $event,
        Tenant $sahodaya,
        array $cards,
        string $audience,
        bool $showTitle,
        ?array $sections = null,
    ): array {
        return [
            'cards'          => $cards,
            'sections'       => $sections,
            'clusterName'    => $sahodaya->name,
            'clusterLogoSrc' => TenantBranding::logoEmbedSrc($sahodaya),
            'eventTitle'     => $event->title,
            'audience'       => $audience,
            'showTitle'      => $showTitle,
        ];
    }
}
