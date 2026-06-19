<?php

namespace App\Support;

class FeatureFlags
{
    public static function websiteEnabled(): bool
    {
        return (bool) config('features.website_enabled', false);
    }
}
