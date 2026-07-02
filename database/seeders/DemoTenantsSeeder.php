<?php

namespace Database\Seeders;

use App\Models\Circular;
use App\Models\KalotsavEvent;
use App\Models\OfficeBearers;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\SahodayaDatabaseProvisioner;
use App\Support\SahodayaHomepageContent;
use App\Support\SahodayaSiteTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoTenantsSeeder extends Seeder
{
    public function run(): void
    {
        $malappuram = Tenant::updateOrCreate(
            ['subdomain' => 'malappuram'],
            [
                'type'      => 'sahodaya',
                'name'      => 'Malappuram Sahodaya',
                'domain'    => 'malappuramsahodaya.test',
                'is_active' => true,
                'plan'      => 'pro',
            ]
        );

        if (config('tenancy.database_per_sahodaya', true)) {
            app(SahodayaDatabaseProvisioner::class)->ensureReady($malappuram, seedDefaults: true, createIfMissing: true);
            $malappuram->run(function () use ($malappuram) {
                $this->seedMalappuramTenantData($malappuram);
            });
        } else {
            $malappuram->run(function () use ($malappuram) {
                $this->seedMalappuramTenantData($malappuram);
            });
        }

        $this->seedMemberSchools($malappuram);
        $this->seedUsers($malappuram);

        $this->command?->info('Demo tenants seeded.');
        $this->command?->line('  Superadmin:  http://'.(config('tenancy.central_domains')[2] ?? 'superadmin.test').'/admin');
        $this->command?->line('  Malappuram:  http://malappuramsahodaya.test  (prod: https://malappuramsahodaya.com)');
        $this->command?->line('  Subdomain:   http://malappuram.'.config('tenancy.tenant_base_domain'));
        $this->command?->line('  Login:       admin@sahodaya.test / password');
        $this->command?->line('  Sahodaya:    sahodaya@malappuram.test / password');
        $this->command?->line('  School:      admin@amu-school.test / password');
    }

    private function seedMalappuramTenantData(Tenant $malappuram): void
    {
        SahodayaProfile::updateOrCreate(
            ['tenant_id' => $malappuram->id],
            [
                'prefix'         => 'MLP',
                'cbse_region'    => 'Malappuram',
                'contact_phone'  => '+91 98765 43210',
                'contact_email'  => 'office@malappuramsahodaya.test',
                'address'        => 'Sahodaya Complex Office, Malappuram, Kerala 676505',
                'student_data_mode'    => 'counts_only',
                'membership_fee_type'  => 'fixed',
                'fixed_membership_fee_amount' => 5000,
                'teacher_registration_enabled' => true,
                'payment_bank_name'    => 'State Bank of India',
                'payment_account_no'   => '12345678901',
                'payment_ifsc'         => 'SBIN0001234',
                'payment_upi'          => 'malappuramsahodaya@oksbi',
                'payment_instructions' => 'Include your school name and registration number in the payment reference. Upload the payment screenshot after transfer.',
            ]
        );

        if ($malappuram->sections()->count() === 0) {
            SahodayaSiteTemplate::apply($malappuram);
        }

        $this->command?->line('Seeding fest master catalogs…');
        (new FestCatalogSeeder)->run($malappuram->id);

        $malappuram->setSetting('logo', '/images/tenants/malappuram-logo.png');

        SahodayaHomepageContent::update($malappuram, [
            'heading'   => 'Malappuram Sahodaya',
            'tagline'   => 'Uniting CBSE schools across Malappuram for academic excellence and collaborative growth.',
            'motto'     => 'Caring and Sharing',
            'phone'     => '+91 98765 43210',
            'email'     => 'office@malappuramsahodaya.test',
            'address'   => 'Sahodaya Complex Office, Malappuram, Kerala 676505',
            'announcements' => [
                ['title' => 'Kalotsav 2025-26 registrations open', 'url' => '#academic', 'date' => 'Jun 2025', 'badge' => 'News'],
                ['title' => 'Membership renewal 2025-26', 'url' => '/school-register', 'date' => 'May 2025', 'badge' => 'Alert'],
            ],
        ]);

        $this->seedOfficeBearers($malappuram);
        $this->seedCirculars($malappuram);
        $this->seedKalotsav($malappuram);
    }

    private function seedOfficeBearers(Tenant $sahodaya): void
    {
        if (OfficeBearers::where('tenant_id', $sahodaya->id)->count() > 4) {
            return;
        }

        $bearers = [
            ['role' => 'President', 'name' => 'Dr. Rajesh Kumar', 'school_name' => 'AMU Residential School', 'phone' => '+91 98470 11111'],
            ['role' => 'General Secretary', 'name' => 'Mrs. Priya Nair', 'school_name' => 'Kendriya Vidyalaya Malappuram', 'phone' => '+91 98470 22222'],
            ['role' => 'Treasurer', 'name' => 'Mr. Abdul Rahman', 'school_name' => 'Al Birr School', 'phone' => '+91 98470 33333'],
            ['role' => 'IT Coordinator', 'name' => 'Mr. Arun Menon', 'school_name' => 'MES School', 'email' => 'it@malappuramsahodaya.test'],
            ['role' => 'Academic Co-ordinator', 'name' => 'Dr. Sreeja V', 'school_name' => 'Devagiri CMI School'],
        ];

        foreach ($bearers as $i => $bearer) {
            OfficeBearers::updateOrCreate(
                ['tenant_id' => $sahodaya->id, 'role' => $bearer['role'], 'name' => $bearer['name']],
                array_merge($bearer, ['display_order' => $i, 'is_active' => true])
            );
        }
    }

    private function seedCirculars(Tenant $sahodaya): void
    {
        if (Circular::where('tenant_id', $sahodaya->id)->exists()) {
            return;
        }

        Circular::create([
            'tenant_id'    => $sahodaya->id,
            'title'        => 'Kalotsav 2025-26 — General Guidelines',
            'category'     => 'Kalotsav',
            'issued_date'  => now()->subDays(5),
            'file_path'    => '',
        ]);

        Circular::create([
            'tenant_id'    => $sahodaya->id,
            'title'        => 'Membership Renewal Circular 2025-26',
            'category'     => 'Membership',
            'issued_date'  => now()->subDays(12),
            'file_path'    => '',
        ]);
    }

    private function seedKalotsav(Tenant $sahodaya): void
    {
        if (KalotsavEvent::where('tenant_id', $sahodaya->id)->exists()) {
            return;
        }

        KalotsavEvent::create([
            'tenant_id'     => $sahodaya->id,
            'name'          => 'Kalotsav 2025-26',
            'type'          => 'Kalotsav',
            'academic_year' => '2025-26',
            'event_date'    => now()->addMonths(2),
            'venue'         => 'AMU Residential School, Malappuram',
            'description'   => 'Inter-school arts & cultural festival',
            'is_active'     => true,
        ]);

        KalotsavEvent::create([
            'tenant_id'     => $sahodaya->id,
            'name'          => 'Athletic Meet 2025-26',
            'type'          => 'Sports',
            'academic_year' => '2025-26',
            'event_date'    => now()->addMonths(4),
            'venue'         => 'District Sports Complex',
            'is_active'     => true,
        ]);
    }

    private function seedMemberSchools(Tenant $sahodaya): void
    {
        $schools = [
            ['name' => 'AMU Residential School', 'subdomain' => 'amu-school'],
            ['name' => 'Kendriya Vidyalaya Malappuram', 'subdomain' => 'kv-malappuram'],
            ['name' => 'Al Birr International School', 'subdomain' => 'albirr'],
            ['name' => 'MES English Medium School', 'subdomain' => 'mes-school'],
            ['name' => 'Devagiri CMI Public School', 'domain' => 'devagiri-school.test'],
        ];

        foreach ($schools as $school) {
            $key = isset($school['subdomain'])
                ? ['subdomain' => $school['subdomain']]
                : ['domain' => $school['domain']];

            Tenant::firstOrCreate(
                array_merge($key, ['parent_id' => $sahodaya->id]),
                [
                    'id'                => (string) Str::uuid(),
                    'type'              => 'school',
                    'name'              => $school['name'],
                    'domain'            => $school['domain'] ?? null,
                    'subdomain'         => $school['subdomain'] ?? null,
                    'membership_status' => 'approved',
                    'school_prefix'     => strtoupper(substr(preg_replace('/[^a-z]/i', '', $school['name']), 0, 3)),
                    'is_active'         => true,
                    'plan'              => 'basic',
                ]
            );
        }
    }

    private function seedUsers(Tenant $sahodaya): void
    {
        $sahodayaAdmin = User::firstOrCreate(
            ['email' => 'sahodaya@malappuram.test'],
            [
                'name'           => 'Malappuram Sahodaya Admin',
                'tenant_id'      => $sahodaya->id,
                'password'       => bcrypt('password'),
                'plain_password' => 'password',
            ]
        );
        $sahodayaAdmin->assignRole('sahodaya_admin');

        $amuSchool = Tenant::where('subdomain', 'amu-school')->where('parent_id', $sahodaya->id)->first();
        if ($amuSchool) {
            $sahodaya->run(function () use ($amuSchool) {
                app(\App\Services\Students\SchoolClassProvisioner::class)->ensureForSchool($amuSchool);
            });

            $schoolAdmin = User::firstOrCreate(
                ['email' => 'admin@amu-school.test'],
                [
                    'name'              => 'AMU School Admin',
                    'tenant_id'         => $amuSchool->id,
                    'password'          => bcrypt('password'),
                    'plain_password'    => 'password',
                    'email_verified_at' => now(),
                ]
            );
            if (! $schoolAdmin->hasVerifiedEmail()) {
                $schoolAdmin->markEmailAsVerified();
            }
            $schoolAdmin->assignRole('school_admin');
        }
    }
}
