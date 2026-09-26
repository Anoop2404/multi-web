<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantStorageTest extends TestCase
{
    public function test_known_cmyk_background_is_color_managed_to_srgb_for_pdf(): void
    {
        $tenant = new Tenant(['id' => 'b7f9b005-9f08-4833-8c02-8767a440ad01']);
        $relative = "sahodaya/{$tenant->id}/id-card-templates/backgrounds/kalotsav-student-id-template-2.jpg";
        $stored = base_path('storage/app/public/'.$relative);
        $source = database_path('seeders/assets/id-card-templates/kalotsav-student-id-template-2.jpg');
        @mkdir(dirname($stored), 0777, true);
        copy($source, $stored);

        try {
            $dataUri = TenantStorage::backgroundDataUri($tenant, $relative, 1600);

            $this->assertNotNull($dataUri);
            $this->assertStringStartsWith('data:image/jpeg;base64,', $dataUri);
            $embedded = base64_decode(substr($dataUri, strpos($dataUri, ',') + 1), true);
            $this->assertNotFalse($embedded);
            $srgb = database_path('seeders/assets/id-card-templates/kalotsav-student-id-template-2-srgb.jpg');
            $this->assertSame(hash_file('sha256', $srgb), hash('sha256', $embedded));

            $imageInfo = getimagesizefromstring($embedded);
            $this->assertSame(3, $imageInfo['channels'] ?? null);
            $this->assertSame(1654, $imageInfo[1] ?? null);
        } finally {
            @unlink($stored);
        }
    }

    public function test_customer_specific_background_correction_does_not_apply_to_other_sahodayas(): void
    {
        $tenant = new Tenant(['id' => (string) Str::uuid()]);
        $relative = "sahodaya/{$tenant->id}/id-card-templates/backgrounds/kalotsav-student-id-template-2.jpg";
        $stored = base_path('storage/app/public/'.$relative);
        $source = database_path('seeders/assets/id-card-templates/kalotsav-student-id-template-2.jpg');
        @mkdir(dirname($stored), 0777, true);
        copy($source, $stored);

        try {
            $dataUri = TenantStorage::backgroundDataUri($tenant, $relative, 1600);
            $embedded = base64_decode(substr($dataUri, strpos($dataUri, ',') + 1), true);

            $this->assertNotFalse($embedded);
            $this->assertSame(hash_file('sha256', $source), hash('sha256', $embedded));
            $this->assertSame(4, getimagesizefromstring($embedded)['channels'] ?? null);
        } finally {
            @unlink($stored);
        }
    }

    public function test_resolves_tenant_suffixed_public_storage_path(): void
    {
        $school = new Tenant(['id' => (string) Str::uuid()]);
        $relative = "payments/{$school->id}/proof.png";

        $tenantDir = base_path('storage/tenant'.$school->id.'/app/public/'.$relative);
        @mkdir(dirname($tenantDir), 0777, true);
        file_put_contents($tenantDir, 'proof');

        $this->assertSame($tenantDir, TenantStorage::publicFilePath($school, $relative));

        @unlink($tenantDir);
    }

    public function test_resolves_shared_storage_path(): void
    {
        $school = new Tenant(['id' => (string) Str::uuid()]);
        $relative = "payments/{$school->id}/proof.png";

        $sharedPath = base_path('storage/app/shared/'.$relative);
        @mkdir(dirname($sharedPath), 0777, true);
        file_put_contents($sharedPath, 'proof');

        $this->assertSame($sharedPath, TenantStorage::publicFilePath($school, $relative));

        @unlink($sharedPath);
    }

    public function test_resolves_tenant_file_while_sahodaya_tenancy_is_active(): void
    {
        $sahodaya = new Tenant(['id' => (string) Str::uuid(), 'type' => 'sahodaya']);
        $school = new Tenant(['id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id]);
        $relative = "payments/{$school->id}/proof.png";

        $tenantDir = base_path('storage/tenant'.$school->id.'/app/public/'.$relative);
        @mkdir(dirname($tenantDir), 0777, true);
        file_put_contents($tenantDir, 'proof');

        if (function_exists('tenancy') && class_exists(\Stancl\Tenancy\Tenancy::class)) {
            tenancy()->initialize($sahodaya);
        }

        $this->assertSame($tenantDir, TenantStorage::publicFilePath($school, $relative));

        @unlink($tenantDir);
    }

    public function test_store_uploaded_file_falls_back_to_shared_when_s3_write_fails_locally(): void
    {
        Storage::fake('s3');
        config(['filesystems.upload_disk' => 's3']);
        $this->app->detectEnvironment(fn () => 'local');

        $file = \Illuminate\Http\UploadedFile::fake()->image('STU_26_0001.jpg');

        $path = TenantStorage::storeUploadedFile($file, 'students/test-school');

        Storage::disk('shared')->assertExists($path);
        $this->assertStringStartsWith('students/test-school/', $path);
    }

    public function test_direct_photo_url_uses_the_cdn_thumbnail_without_probing_or_proxying_the_file(): void
    {
        config([
            'filesystems.disks.s3.key' => 'test-key',
            'filesystems.disks.s3.secret' => 'test-secret',
            'filesystems.disks.s3.region' => 'ap-south-1',
            'filesystems.disks.s3.bucket' => 'test-bucket',
            'filesystems.disks.s3.url' => 'https://media.example.test',
            'filesystems.disks.s3.public_url' => 'https://media.example.test',
            'filesystems.disks.s3.root' => 'domains',
        ]);

        $path = 'students/school-123/photo.jpg';

        $this->assertSame(
            'https://media.example.test/domains/'.$path.'.thumb.jpg',
            TenantStorage::directPhotoUrl($path),
        );
        $this->assertSame(
            'https://media.example.test/domains/'.$path,
            TenantStorage::directPhotoUrl($path, thumbnail: false),
        );
    }
}
