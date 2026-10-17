<?php

use App\Models\NotificationTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            [
                'slug'          => 'mcq.registration.cancelled_admin',
                'title'         => 'MCQ registration cancelled by Sahodaya',
                'body_template' => 'Registration for {{student_name}} in {{exam_title}} was cancelled by Sahodaya. Reason: {{rejection_reason}}',
            ],
            [
                'slug'          => 'mcq.registration.cancelled_by_school',
                'title'         => 'MCQ registration cancelled by school',
                'body_template' => 'Registration for {{student_name}} in {{exam_title}} was cancelled by the school.',
            ],
        ];

        foreach ($templates as $data) {
            NotificationTemplate::firstOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, [
                    'channels_json' => ['database' => true, 'mail' => false, 'sms' => false],
                    'is_active'     => true,
                ])
            );
        }
    }

    public function down(): void
    {
        NotificationTemplate::whereIn('slug', [
            'mcq.registration.cancelled_admin',
            'mcq.registration.cancelled_by_school',
        ])->delete();
    }
};
