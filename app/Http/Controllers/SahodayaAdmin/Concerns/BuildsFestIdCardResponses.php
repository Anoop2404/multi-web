<?php

namespace App\Http\Controllers\SahodayaAdmin\Concerns;

use App\Models\FestEvent;
use App\Models\IdCardTemplate;
use App\Models\Tenant;
use App\Support\FestIdCardTemplates;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
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

    /**
     * Resolve a Sahodaya-uploaded custom ID card template for this event/item/audience,
     * if one is configured and active. Returns null to fall back to the built-in
     * standard/premium layouts.
     */
    protected function resolveCustomIdCardTemplate(FestEvent $event, ?int $itemId, string $audience): ?IdCardTemplate
    {
        return IdCardTemplate::resolveFor($event, $itemId, $audience);
    }

    protected function idCardSheetView(Request $request, ?IdCardTemplate $customTemplate = null): string
    {
        if ($customTemplate) {
            return 'fest.id-cards.custom-sheet';
        }

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
        ?IdCardTemplate $customTemplate = null,
        bool $isPdf = false,
    ): array {
        $base = [
            'cards'          => $cards,
            'sections'       => $sections,
            'clusterName'    => $sahodaya->name,
            'clusterLogoSrc' => TenantBranding::logoEmbedSrc($sahodaya),
            'eventTitle'     => $event->title,
            'audience'       => $audience,
            'showTitle'      => $showTitle,
            'isPdf'          => $isPdf,
        ];

        if (! $customTemplate) {
            return $base;
        }

        return array_merge($base, [
            'backgroundUrl' => $customTemplate->background_path
                ? TenantStorage::logoUrl($sahodaya, $customTemplate->background_path)
                : null,
            'fields'        => $customTemplate->fields(),
            'cardWidthMm'   => $customTemplate->card_width_mm,
            'cardHeightMm'  => $customTemplate->card_height_mm,
            'cardsPerPage'  => $customTemplate->cards_per_page,
        ]);
    }
}
