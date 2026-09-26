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

    /** assetUrl()'s S3 existence check, answered from the cache so no network call is made. */
    private function rememberOnS3(string $path): void
    {
        \Illuminate\Support\Facades\Cache::put('tenant-storage:s3-exists:'.sha1($path), true, 3600);
    }

    public function test_logos_get_the_stable_public_cloudfront_address(): void
    {
        config(['filesystems.disks.s3.public_url' => 'https://dpublic456.cloudfront.net', 'filesystems.disks.s3.url' => 'https://dpublic456.cloudfront.net']);
        Storage::forgetDisk('s3');
        $this->rememberOnS3('logos/t1/logo.jpg');

        $first = TenantStorage::assetUrl(null, 'logos/t1/logo.jpg');

        $this->assertSame('https://dpublic456.cloudfront.net/domains/logos/t1/logo.jpg', $first);
        $this->assertSame($first, TenantStorage::assetUrl(null, 'logos/t1/logo.jpg'), 'Stable across renders, so browsers and CloudFront cache it.');
    }

    public function test_other_s3_images_get_a_signed_cloudfront_url_that_stays_the_same_across_renders(): void
    {
        $this->configureCloudFront();
        $this->rememberOnS3('certificate-templates/bg.png');

        $first = TenantStorage::assetUrl(null, 'certificate-templates/bg.png');

        $this->assertStringStartsWith('https://dprivate123.cloudfront.net/domains/certificate-templates/bg.png?Expires='.TenantStorage::cacheableCloudFrontExpiry(), $first);
        $this->assertSame($first, TenantStorage::assetUrl(null, 'certificate-templates/bg.png'));
        $this->assertGreaterThanOrEqual(time() + 86400, TenantStorage::cacheableCloudFrontExpiry(), 'Must outlive a cached public page.');
    }

    public function test_without_cloudfront_other_s3_images_keep_their_presigned_s3_url(): void
    {
        $this->rememberOnS3('certificate-templates/bg.png');

        $url = TenantStorage::assetUrl(null, 'certificate-templates/bg.png');

        $this->assertStringContainsString('test-bucket', $url);
        $this->assertStringContainsString('X-Amz-Signature=', $url);
    }

    public function test_download_response_redirects_s3_images_with_the_cacheable_link_and_documents_with_the_short_one(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('news/photo.jpg', 'jpg');
        Storage::disk('s3')->put('receipts/proof.pdf', 'pdf');
        $this->configureCloudFront();
        $tenant = new \App\Models\Tenant;

        $image = TenantStorage::downloadResponse($tenant, 'news/photo.jpg');
        $this->assertInstanceOf(RedirectResponse::class, $image);
        $this->assertStringContainsString('Expires='.TenantStorage::cacheableCloudFrontExpiry(), $image->getTargetUrl());

        $document = TenantStorage::downloadResponse($tenant, 'receipts/proof.pdf');
        $this->assertInstanceOf(RedirectResponse::class, $document);
        parse_str(parse_url($document->getTargetUrl(), PHP_URL_QUERY), $params);
        $this->assertLessThanOrEqual(time() + 601, (int) $params['Expires']);
    }

    public function test_download_response_still_streams_without_cloudfront(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('news/photo.jpg', 'jpg');

        $response = TenantStorage::downloadResponse(new \App\Models\Tenant, 'news/photo.jpg');

        $this->assertNotInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_private_temporary_url_prefers_cloudfront_and_falls_back_to_the_disk(): void
    {
        $this->assertStringContainsString('X-Amz-Signature=', TenantStorage::privateTemporaryUrl('s3', 'circulars/c.pdf', now()->addMinutes(15)));

        $this->configureCloudFront();
        $url = TenantStorage::privateTemporaryUrl('s3', 'circulars/c.pdf', now()->addMinutes(15));
        $this->assertStringStartsWith('https://dprivate123.cloudfront.net/domains/circulars/c.pdf?Expires=', $url);
    }
}
