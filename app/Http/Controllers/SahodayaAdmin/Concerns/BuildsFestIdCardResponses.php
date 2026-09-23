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
            'eventType'      => $event->event_type,
            'isSports'       => $event->event_type === 'sports',
            'programLabel'   => $event->event_type === 'sports' ? 'Sports Meet' : (\App\Support\ProgramRouteMap::labelForEventType($event->event_type) ?: 'Kalotsav'),
        ];

        if (! $customTemplate) {
            return $base;
        }

        return array_merge($base, [
            'backgroundUrl' => $customTemplate->background_path
                ? ($isPdf
                    // For PDF generation the external Chromium service cannot load relative
                    // URLs ("/storage/...") or signed S3 URLs without network access.
                    // backgroundDataUri() embeds the image as base64 so it travels inline
                    // in the HTML string sent to the renderer. If embedding fails we fall
                    // back to an absolute URL using the app's own base URL so that a
                    // self-hosted Chromium instance on the same server can still load it.
                    ? (TenantStorage::backgroundDataUri($sahodaya, $customTemplate->background_path)
                        ?: (($u = TenantStorage::logoUrl($sahodaya, $customTemplate->background_path)) && ! str_starts_with($u, '/') ? $u : url($u ?? ''))
                      )
                    : TenantStorage::logoUrl($sahodaya, $customTemplate->background_path))
                : null,
            'fields'        => $customTemplate->fields(),
            'cardWidthMm'   => $customTemplate->card_width_mm,
            'cardHeightMm'  => $customTemplate->card_height_mm,
            'cardsPerPage'  => $customTemplate->cards_per_page,
            'pageWidthMm'   => $customTemplate->page_width_mm,
            'pageHeightMm'  => $customTemplate->page_height_mm,
            'gridLayout'    => $customTemplate->gridLayout(),
        ]);
    }
}
