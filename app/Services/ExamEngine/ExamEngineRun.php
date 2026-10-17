<?php

namespace App\Services\ExamEngine;

use App\Models\McqExam;

/** Run identity and engine flags for an exam (exam-engine/CONTRACT.md §1). */
final class ExamEngineRun
{
    public static function for(McqExam $exam): string
    {
        return $exam->tenant_id.':'.$exam->id;
    }

    /** The exam is delivered by the exam engine (rather than the in-app online exam). */
    public static function usesEngine(McqExam $exam): bool
    {
        return $exam->isOnlineDelivery() && ($exam->settings_json['engine'] ?? null) === 'engine';
    }

    /** null (not armed), 'armed' or 'closed'. */
    public static function state(McqExam $exam): ?string
    {
        return $exam->settings_json['engine_state'] ?? null;
    }

    /** Engine settings for the exam, filled with the configured defaults. */
    public static function settings(McqExam $exam): array
    {
        return array_merge(config('exam_engine.defaults'), $exam->settings_json['engine_settings'] ?? []);
    }
}
