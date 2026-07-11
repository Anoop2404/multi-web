<?php

namespace App\Services\Training;

use App\Models\TrainingFeedback;
use App\Models\TrainingRegistration;
use Illuminate\Validation\ValidationException;

class TrainingFeedbackService
{
    /**
     * @param  array{rating: int, comments?: ?string, content_rating?: ?int, trainer_rating?: ?int, venue_rating?: ?int}  $data
     */
    public function submit(TrainingRegistration $reg, array $data): TrainingFeedback
    {
        $reg->loadMissing('feedback');

        if ($reg->feedback) {
            throw ValidationException::withMessages([
                'feedback' => 'Feedback has already been submitted for this registration.',
            ]);
        }

        if (! in_array($reg->status, ['confirmed', 'completed'], true)) {
            throw ValidationException::withMessages([
                'registration' => 'Feedback is only available for confirmed or completed registrations.',
            ]);
        }

        $rating = (int) $data['rating'];
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages([
                'rating' => 'Overall rating must be between 1 and 5.',
            ]);
        }

        foreach (['content_rating', 'trainer_rating', 'venue_rating'] as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                continue;
            }
            $value = (int) $data[$field];
            if ($value < 1 || $value > 5) {
                throw ValidationException::withMessages([
                    $field => 'Rating must be between 1 and 5.',
                ]);
            }
        }

        return TrainingFeedback::create([
            'program_id' => $reg->program_id,
            'registration_id' => $reg->id,
            'teacher_id' => $reg->teacher_id,
            'rating' => $rating,
            'comments' => $data['comments'] ?? null,
            'content_rating' => isset($data['content_rating']) && $data['content_rating'] !== ''
                ? (int) $data['content_rating']
                : null,
            'trainer_rating' => isset($data['trainer_rating']) && $data['trainer_rating'] !== ''
                ? (int) $data['trainer_rating']
                : null,
            'venue_rating' => isset($data['venue_rating']) && $data['venue_rating'] !== ''
                ? (int) $data['venue_rating']
                : null,
            'status' => 'submitted',
        ]);
    }

    public function markReviewed(TrainingFeedback $feedback, ?int $userId): void
    {
        if ($feedback->status === 'reviewed') {
            return;
        }

        $feedback->update([
            'status' => 'reviewed',
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $userId,
        ]);
    }
}
