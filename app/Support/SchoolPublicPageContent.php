<?php

namespace App\Support;

use App\Models\Tenant;

class SchoolPublicPageContent
{
    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'branding' => [
                'subtitle' => 'CBSE Affiliated School',
            ],
            'common' => [
                'back_to_home' => 'Back to home',
            ],
            'pages' => [
                'about' => [
                    'title' => 'About Our School',
                    'eyebrow' => 'Vision & Values',
                    'subheading' => 'Fostering academic excellence, character building, and holistic education.',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'academics' => [
                    'title' => 'Academic Programmes',
                    'eyebrow' => 'CBSE Curriculum',
                    'subheading' => 'Comprehensive academic curriculum, innovative teaching methods, and student development.',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'admissions' => [
                    'title' => 'Admissions',
                    'eyebrow' => 'Join Our School',
                    'subheading' => 'Admission details, eligibility criteria, and application enquiry desk.',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'disclosure' => [
                    'title' => 'CBSE Mandatory Public Disclosure',
                    'eyebrow' => 'CBSE Affiliation Bye-Laws',
                    'subheading' => 'Mandatory public disclosures, affiliation documents, infrastructure, and governance.',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'contact' => [
                    'title' => 'Contact Us',
                    'eyebrow' => 'Get In Touch',
                    'subheading' => 'Call, email, or send us an enquiry.',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'faculty' => [
                    'title' => 'Faculty',
                    'eyebrow' => 'Our Team',
                    'subheading' => 'Meet the teachers and school leaders who guide our students.',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'downloads' => [
                    'title' => 'Downloads',
                    'eyebrow' => 'School Resources',
                    'subheading' => 'Access school forms, calendars, circulars, and documents.',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'careers' => [
                    'title' => 'Careers',
                    'eyebrow' => 'Join Our Team',
                    'subheading' => 'View current teaching and non-teaching opportunities.',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'alumni' => [
                    'title' => 'Alumni',
                    'eyebrow' => 'Our Community',
                    'subheading' => 'Reconnect with the school and share your journey.',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'achievements' => [
                    'title' => 'Achievements',
                    'eyebrow' => 'Celebrating Success',
                    'subheading' => 'Academic, cultural, and sporting milestones from our school community.',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'news' => [
                    'title' => 'News & Announcements',
                    'empty_message' => 'No news articles published yet.',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'events' => [
                    'title' => 'Events',
                    'empty_message' => 'No events listed yet.',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'gallery' => [
                    'title' => 'Gallery',
                    'empty_title' => 'Gallery updates coming soon',
                    'empty_description' => 'School events, celebrations and campus moments will appear here when albums are published.',
                    'empty_album_message' => 'This album has no photos yet.',
                    'photo_singular' => 'photo',
                    'photo_plural' => 'photos',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'results' => [
                    'title' => 'Board Examination Results',
                    'intro_prefix' => 'Academic year',
                    'intro_suffix' => 'Class X (AISSE) & Class XII (AISSCE)',
                    'download_label' => 'Download Full Result (PDF)',
                    'empty_message' => 'Results have not been published yet.',
                    'appeared_label' => 'Appeared',
                    'pass_label' => 'Pass %',
                    'distinctions_label' => 'Distinctions',
                    'first_class_label' => 'First Class',
                    'top_scorers_label' => 'Top Scorers',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'admission_enquiry' => [
                    'eyebrow' => 'Admissions',
                    'title' => 'Admission Enquiry',
                    'intro' => 'Fill in the form below and our admissions team will get in touch with you.',
                    'student_name_label' => 'Student Name',
                    'date_of_birth_label' => 'Date of Birth',
                    'class_label' => 'Class Applying For',
                    'parent_name_label' => 'Parent / Guardian Name',
                    'phone_label' => 'Phone',
                    'email_label' => 'Email',
                    'address_label' => 'Address',
                    'message_label' => 'Message / Additional Info',
                    'select_placeholder' => 'Select',
                    'submit_label' => 'Submit Enquiry',
                    'success_message' => 'Thank you! Your enquiry has been received. We will contact you shortly.',
                    'seo_title' => '',
                    'seo_description' => '',
                ],
                'admin_login' => [
                    'badge' => 'School administration',
                    'intro' => 'Sign in to manage the school website, admissions, announcements, gallery, staff, and contact details.',
                    'step_one' => 'Update website sections, page text, colours, navigation, and footer links',
                    'step_two' => 'Publish news, events, gallery albums, staff profiles, and results',
                    'step_three' => 'Review admission enquiries and keep school contact details current',
                    'form_eyebrow' => 'Secure school access',
                    'form_title' => 'School Administration Login',
                    'form_description' => 'Use the administrator credentials created for this school.',
                    'submit_label' => 'Sign in to dashboard',
                    'footer_note' => 'School administration access',
                ],
                'portal_landing' => [
                    'eyebrow' => 'School Website',
                    'title' => 'Administration Access',
                    'intro' => 'Sign in to manage this school website and its published information.',
                    'action_label' => 'School Administration Login',
                    'action_description' => 'Manage website content, enquiries, staff, news, events, gallery, and results',
                    'footer_note' => 'School website administration portal',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function resolve(Tenant $tenant): array
    {
        $stored = $tenant->settings()->where('key', 'site_content')->first()?->value ?? [];

        return array_replace_recursive(self::defaults(), is_array($stored) ? $stored : []);
    }

    /** @return array<string, string> */
    public static function page(Tenant $tenant, string $page): array
    {
        return self::resolve($tenant)['pages'][$page] ?? [];
    }

    /** @return array<string, string> */
    public static function seo(Tenant $tenant, string $page, array $fallback): array
    {
        $content = self::page($tenant, $page);
        $title = trim((string) ($content['seo_title'] ?? ''));
        $description = trim((string) ($content['seo_description'] ?? ''));

        return array_merge($fallback, array_filter([
            'title' => $title !== '' ? self::interpolate($title, $tenant) : null,
            'description' => $description !== '' ? self::interpolate($description, $tenant) : null,
        ], fn (?string $value) => $value !== null));
    }

    private static function interpolate(string $value, Tenant $tenant): string
    {
        return str_replace(['{{school_name}}', '{{ school_name }}'], $tenant->name, $value);
    }
}
