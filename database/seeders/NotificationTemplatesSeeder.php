<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug'          => 'fest.registration.approved',
                'title'         => 'Event registration approved',
                'body_template' => 'Your registration for {{event_title}} ({{item_title}}) has been approved.',
            ],
            [
                'slug'          => 'fest.registration.rejected',
                'title'         => 'Event registration rejected',
                'body_template' => 'Your registration for {{event_title}} was not approved. Contact your Sahodaya for details.',
            ],
            [
                'slug'          => 'fest.results.published',
                'title'         => 'Event results published',
                'body_template' => 'Results for {{event_title}} are now published.',
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                array_merge($template, ['is_active' => true, 'channels_json' => ['in_app']])
            );
        }
    }
}
