<?php

namespace App\Support;

class SchoolFestProgram
{
    /** @var array<string, array{slug: string, label: string, eventType: string}> */
    public const MAP = [
        'kalotsav'     => ['slug' => 'kalotsav', 'label' => 'Kalotsav', 'eventType' => 'kalolsavam'],
        'sports-meet'  => ['slug' => 'sports-meet', 'label' => 'Sports Meet', 'eventType' => 'sports'],
        'kids-fest'    => ['slug' => 'kids-fest', 'label' => 'Kids Fest', 'eventType' => 'kids_fest'],
        'teacher-fest' => ['slug' => 'teacher-fest', 'label' => 'Teacher Fest', 'eventType' => 'teacher_fest'],
        'custom'       => ['slug' => 'custom', 'label' => 'Custom Events', 'eventType' => 'custom'],
    ];

    /** @return array{slug: string, label: string, eventType: string} */
    public static function meta(string $program): array
    {
        abort_unless(isset(self::MAP[$program]), 404);

        return self::MAP[$program];
    }

    public static function eventType(string $program): string
    {
        return self::meta($program)['eventType'];
    }

    public static function slugForEventType(string $eventType): string
    {
        return match ($eventType) {
            'sports'       => 'sports-meet',
            'kids_fest'    => 'kids-fest',
            'teacher_fest' => 'teacher-fest',
            'custom'       => 'custom',
            default        => 'kalotsav',
        };
    }
}
