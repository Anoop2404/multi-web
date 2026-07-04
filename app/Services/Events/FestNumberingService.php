<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestLevelRegistration;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use Illuminate\Support\Facades\DB;

class FestNumberingService
{
    /** @return array<string, mixed> */
    public function settings(FestEvent $event): array
    {
        $defaults = [
            'event_reg_start' => 1,
            'event_reg_prefix' => strtoupper(substr($event->level_round ?? 'S', 0, 1)).'-',
            'chest_no_start' => 1,
            'chest_no_prefix' => '',
            'auto_assign_on_approve' => true,
            'auto_assign_chest_on_create' => false,
        ];

        $stored = is_array($event->numbering_settings) ? $event->numbering_settings : [];

        return array_merge($defaults, $stored);
    }

    public function nextEventRegNumber(FestEvent $event): string
    {
        $settings = $this->settings($event);
        $prefix = (string) ($settings['event_reg_prefix'] ?? 'S-');
        $start = (int) ($settings['event_reg_start'] ?? 1);

        $maxSeq = FestLevelRegistration::where('event_id', $event->id)
            ->pluck('registration_number')
            ->map(function (?string $num) use ($prefix) {
                if (! $num || ! str_starts_with($num, $prefix)) {
                    return 0;
                }
                $tail = substr($num, strlen($prefix));

                return is_numeric($tail) ? (int) $tail : 0;
            })
            ->max();

        $next = max($start, ($maxSeq ?? 0) + 1);

        return sprintf('%s%04d', $prefix, $next);
    }

    public function nextChestNumber(FestEvent $event, FestEventItem $item): int
    {
        return DB::transaction(function () use ($event, $item) {
            FestEvent::where('id', $event->id)->lockForUpdate()->first();

            $settings = $this->settings($event);
            $start = (int) ($settings['chest_no_start'] ?? 1);

            $max = FestParticipant::whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('item_id', $item->id))
                ->max('chest_no');

            return max($start, ($max ?? 0) + 1);
        });
    }

    public function nextItemRegistrationNumber(FestEvent $event, FestEventItem $item): string
    {
        $start = (int) ($item->item_reg_id_start ?? 1);

        $maxSeq = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('item_id', $item->id))
            ->whereNotNull('item_registration_number')
            ->pluck('item_registration_number')
            ->map(function (?string $num) {
                if (! $num || ! preg_match('/(\d+)$/', $num, $m)) {
                    return 0;
                }

                return (int) $m[1];
            })
            ->max();

        $next = max($start, ($maxSeq ?? 0) + 1);
        $code = $item->item_code ?: ('I'.$item->id);

        return sprintf('%s-%04d', strtoupper($code), $next);
    }

    public function assignParticipantNumbers(FestParticipant $participant): void
    {
        $participant->loadMissing('registration.event', 'registration.item', 'student');
        $registration = $participant->registration;
        $event = $registration?->event;
        $item = $registration?->item;

        if (! $event || ! $item || ! $participant->student_id) {
            return;
        }

        $updates = ['event_id' => $event->id];

        if (! $participant->level_registration_number && $participant->student) {
            $updates['level_registration_number'] = app(FestLevelRegistrationService::class)
                ->issueForStudent($event, $participant->student);
        }

        if (! $participant->item_registration_number) {
            $updates['item_registration_number'] = $this->nextItemRegistrationNumber($event, $item);
        }

        $settings = $this->settings($event);
        if (! $participant->chest_no && ($settings['auto_assign_chest_on_create'] ?? false)) {
            $updates['chest_no'] = $this->nextChestNumber($event, $item);
        }

        $participant->update($updates);
    }

    /** Assign chest numbers to approved participants missing them. */
    public function assignMissingChestNumbers(FestEvent $event): int
    {
        $count = 0;

        FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->with('registration.item')
            ->whereNull('chest_no')
            ->each(function (FestParticipant $p) use ($event, &$count) {
                if (! $p->registration?->item_id) {
                    return;
                }
                $p->update([
                    'event_id' => $event->id,
                    'chest_no' => $this->nextChestNumber($event, $p->registration->item),
                ]);
                $count++;
            });

        return $count;
    }

    /** Assign item reg numbers to participants missing them. */
    public function assignMissingItemRegNumbers(FestEvent $event): int
    {
        $count = 0;

        FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->whereIn('status', ['submitted', 'approved']))
            ->with('registration.item')
            ->whereNull('item_registration_number')
            ->each(function (FestParticipant $p) use ($event, &$count) {
                if (! $p->registration?->item) {
                    return;
                }
                $this->assignParticipantNumbers($p);
                $count++;
            });

        return $count;
    }
}
