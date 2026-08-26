<?php

namespace App\Http\Controllers\SchoolAdmin;

class WebsiteHubController extends SchoolAdminController
{
    public function index()
    {
        $base = "/school-admin/{$this->school->id}";

        return $this->inertia('School/Website/Hub', [
            'links' => [
                'site_builder' => "{$base}/site-builder",
                'news' => "{$base}/news",
                'events' => "{$base}/events",
                'gallery' => "{$base}/gallery",
                'staff' => "{$base}/staff",
                'achievements' => "{$base}/achievements",
                'downloads' => "{$base}/downloads",
                'job_vacancies' => "{$base}/job-vacancies",
                'alumni' => "{$base}/alumni",
                'testimonials' => "{$base}/testimonials",
                'contact' => "{$base}/contact",
                'enquiries' => "{$base}/enquiries",
            ],
        ]);
    }
}
