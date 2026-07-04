<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\Tenant;
use Illuminate\Support\Str;

class FestWinnerPosterService
{
    /** @return array{content: string, mime: string, filename: string} */
    public function render(FestEvent $event, FestEventItem $item, FestMark $mark, Tenant $tenant): array
    {
        $mark->loadMissing(['participant.student', 'participant.teacher', 'participant.registration.school']);

        $participant = $mark->participant;
        $name = $participant?->student?->name ?? $participant?->teacher?->name ?? 'Winner';
        $school = $participant?->registration?->school?->name ?? '';
        $position = (int) ($mark->position ?? 0);
        $positionLabel = match ($position) {
            1       => '1st Place',
            2       => '2nd Place',
            3       => '3rd Place',
            default => $position > 0 ? "#{$position}" : 'Winner',
        };

        $eventTypeLabel = match ($event->event_type) {
            'sports'       => 'Sports Meet',
            'kalolsavam'   => 'Kalotsavam',
            'kids_fest'    => 'Kids Fest',
            'teacher_fest' => 'Teacher Fest',
            'english_fest' => 'English Fest',
            'science_fest' => 'Science Fest',
            default        => 'Festival',
        };

        $accent = match ($position) {
            1       => '#D97706',
            2       => '#64748B',
            3       => '#B45309',
            default => '#0F766E',
        };

        $svg = view('fest.posters.winner', [
            'tenantName'      => $tenant->name,
            'eventTitle'      => $event->title,
            'eventTypeLabel'  => $eventTypeLabel,
            'itemTitle'       => $item->title,
            'winnerName'      => $name,
            'schoolName'      => $school,
            'positionLabel'   => $positionLabel,
            'grade'           => $mark->grade,
            'score'           => $mark->score,
            'measurement'     => trim(($mark->measurement_value ?? '').' '.($mark->measurement_unit ?? '')),
            'accent'          => $accent,
        ])->render();

        $slug = Str::slug("{$item->title}-{$name}-poster") ?: 'winner-poster';

        return [
            'content'  => $svg,
            'mime'     => 'image/svg+xml',
            'filename' => "{$slug}.svg",
        ];
    }
}
