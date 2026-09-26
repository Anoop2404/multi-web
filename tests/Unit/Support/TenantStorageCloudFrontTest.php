<?php

namespace Tests\Unit\Support;

use App\Support\TenantStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Private S3 downloads handed out as signed CloudFront URLs (so the bytes go
 * S3 -> CloudFront -> browser and never through the app server) — and left streaming
 * exactly as before whenever CloudFront isn't fully configured.
 */
class TenantStorageCloudFrontTest extends TestCase
{
    private string $privateKeyPath;

    private string $publicKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privatePem);
        $this->publicKeyPem = openssl_pkey_get_details($key)['key'];
        $this->privateKeyPath = tempnam(sys_get_temp_dir(), 'cf-key-');
        file_put_contents($this->privateKeyPath, $privatePem);

        config([
            'filesystems.disks.s3.key' => 'test-key',
            'filesystems.disks.s3.secret' => 'test-secret',
            'filesystems.disks.s3.bucket' => 'test-bucket',
            'filesystems.disks.s3.region' => 'ap-south-1',
            'filesystems.disks.s3.root' => 'domains',
        ]);
        Storage::forgetDisk('s3');
    }

    protected function tearDown(): void
    {
        @unlink($this->privateKeyPath);
        parent::tearDown();
    }

    private function configureCloudFront(array $overrides = []): void
    {
        config(['services.cloudfront' => $overrides + [
            'url' => 'https://dprivate123.cloudfront.net',
            'key_pair_id' => 'KTESTKEYPAIR',
            'private_key_path' => $this->privateKeyPath,
            'origin_path' => '',
            'ttl' => 600,
        ]]);
    }

    public function test_signed_url_points_at_the_full_s3_key_with_a_valid_canned_policy_signature(): void
    {
        $this->configureCloudFront();

        $url = TenantStorage::cloudFrontSignedUrl('certificates/t1/49/participation/7-uuid.pdf', 'Asha K.pdf', false);

        $this->assertNotNull($url);
        [$resource, $query] = explode('?', $url, 2);
        $this->assertSame('https://dprivate123.cloudfront.net/domains/certificates/t1/49/participation/7-uuid.pdf', $resource);

        parse_str($query, $params);
        $this->assertSame('attachment; filename="Asha K.pdf"; filename*=UTF-8\'\'Asha%20K.pdf', $params['response-content-disposition']);
        $this->assertSame('KTESTKEYPAIR', $params['Key-Pair-Id']);
        $this->assertGreaterThan(time() + 500, (int) $params['Expires']);

        // CloudFront's own check: the canned policy for the signed URL (everything before
        // &Expires) verified against the key group's public key.
        $signedResource = substr($url, 0, strpos($url, '&Expires='));
        $policy = '{"Statement":[{"Resource":"'.$signedResource.'","Condition":{"DateLessThan":{"AWS:EpochTime":'.$params['Expires'].'}}}]}';
        $signature = base64_decode(strtr($params['Signature'], '-_~', '+=/'));
        $this->assertSame(1, openssl_verify($policy, $signature, $this->publicKeyPem, OPENSSL_ALGO_SHA1));
    }

    public function test_inline_without_a_filename_signs_the_bare_object_url(): void
    {
        $this->configureCloudFront();

        $url = TenantStorage::cloudFrontSignedUrl('docs/report.pdf', null, true);

        $this->assertStringStartsWith('https://dprivate123.cloudfront.net/domains/docs/report.pdf?Expires=', $url);
    }

    public function test_origin_path_is_stripped_from_the_object_key(): void
    {
        $this->configureCloudFront(['origin_path' => '/domains']);

        $url = TenantStorage::cloudFrontSignedUrl('docs/report.pdf', null, true);

        $this->assertStringStartsWith('https://dprivate123.cloudfront.net/docs/report.pdf?', $url);
    }

    public function test_nothing_is_signed_unless_cloudfront_is_fully_configured(): void
    {
        $this->configureCloudFront(['key_pair_id' => null]);
        $this->assertFalse(TenantStorage::cloudFrontConfigured());
        $this->assertNull(TenantStorage::cloudFrontSignedUrl('docs/report.pdf'));

        $this->configureCloudFront(['private_key_path' => '/nonexistent/key.pem']);
        $this->assertFalse(TenantStorage::cloudFrontConfigured());
        $this->assertNull(TenantStorage::cloudFrontSignedUrl('docs/report.pdf'));
    }

    public function test_download_private_redirects_an_s3_file_to_cloudfront_when_configured(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('exports/batch-1.zip', 'zip-bytes');
        $this->configureCloudFront();

        $response = TenantStorage::downloadPrivate('exports/batch-1.zip', 's3', 'Certificates.zip');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringStartsWith('https://dprivate123.cloudfront.net/', $response->getTargetUrl());
        $this->assertStringContainsString('Signature=', $response->getTargetUrl());
    }

    public function test_download_private_still_streams_when_cloudfront_is_not_configured(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('exports/batch-1.zip', 'zip-bytes');
        config(['services.cloudfront' => ['url' => null, 'key_pair_id' => null, 'private_key_path' => null]]);

        $response = TenantStorage::downloadPrivate('exports/batch-1.zip', 's3', 'Certificates.zip');

        $this->assertNotInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }
}
