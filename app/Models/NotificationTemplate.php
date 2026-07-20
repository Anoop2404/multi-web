<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = ["tenant_id", "slug", "title", "body_template", "channels_json", "is_active"];

    protected $casts = [
        'is_active' => 'boolean',
        'channels_json' => 'array',
    ];

    /**
     * Resolve a template's title/body by slug with {{variable}} substitution,
     * falling back to the given defaults when no active template row exists
     * (e.g. before the slug has been seeded, or an admin deactivated it).
     * Used by transactional email senders (membership, fee receipts, auth)
     * that render full HTML emails rather than in-app notifications.
     *
     * @param  array<string, mixed>  $variables
     * @return array{title: string, body: string}
     */
    public static function renderOrDefault(string $slug, array $variables, string $defaultTitle, string $defaultBody): array
    {
        $template = static::where('slug', $slug)->where('is_active', true)->first();

        $title = $template?->title ?? $defaultTitle;
        $body = $template?->body_template ?? $defaultBody;

        foreach ($variables as $key => $value) {
            $needle = '{{'.$key.'}}';
            $title = str_replace($needle, (string) $value, $title);
            $body = str_replace($needle, (string) $value, $body);
        }

        return ['title' => $title, 'body' => $body];
    }
}
