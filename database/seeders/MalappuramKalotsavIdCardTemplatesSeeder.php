<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\Events\IdCardTemplatePresetInstaller;
use Illuminate\Database\Seeder;

/**
 * Installs the three approved Kalotsav student ID-card designs.
 *
 * The same preset installer powers the template-builder buttons. Every run
 * creates a fresh set, with numbered "Copy" suffixes when necessary.
 *
 * Usage: php artisan db:seed --class=MalappuramKalotsavIdCardTemplatesSeeder
 */
class MalappuramKalotsavIdCardTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        if (tenancy()->initialized) {
            $this->seedForTenant((string) tenant('id'));

            return;
        }

        $explicitId = env('SAHODAYA_UUID') ?: env('TENANT_ID');
        if ($explicitId) {
            $sahodaya = Tenant::find($explicitId);
            if ($sahodaya) {
                $sahodaya->run(fn () => $this->seedForTenant((string) $sahodaya->id));

                return;
            }
        }

        $sahodaya = Tenant::query()
            ->where('type', 'sahodaya')
            ->where(function ($query) {
                $query->where('subdomain', 'malappuram')
                    ->orWhere('domain', 'malappuramsahodaya.test')
                    ->orWhere('name', 'Malappuram Sahodaya');
            })
            ->first();

        if (! $sahodaya) {
            $this->command?->warn('MalappuramKalotsavIdCardTemplatesSeeder: Malappuram Sahodaya was not found.');

            return;
        }

        $sahodaya->run(fn () => $this->seedForTenant((string) $sahodaya->id));
    }

    public function seedForTenant(string $tenantId): void
    {
        $created = app(IdCardTemplatePresetInstaller::class)->installAll($tenantId);

        $this->command?->info(
            'Created three new Kalotsav student ID-card templates (IDs: '.$created->pluck('id')->implode(', ').').'
        );
    }
}
