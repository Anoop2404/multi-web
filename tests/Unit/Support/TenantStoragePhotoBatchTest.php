<?php

namespace Tests\Unit\Support;

use App\Support\TenantStorage;
use Aws\Result;
use GuzzleHttp\Promise\Create;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class TenantStoragePhotoBatchTest extends TestCase
{
    public function test_student_photo_cache_keys_are_scoped_to_school_and_photo_path(): void
    {
        $a = new \App\Models\Student(['tenant_id' => 'school-a', 'photo' => 'photos/a.jpg']);
        $a->id = 1;
        $b = clone $a;
        $b->tenant_id = 'school-b';
        $this->assertNotSame($a->photoDataUriCacheKey(), $b->photoDataUriCacheKey());
        $b = clone $a;
        $b->photo = 'photos/replacement.jpg';
        $this->assertNotSame($a->photoDataUriCacheKey(), $b->photoDataUriCacheKey());
    }

    public function test_private_photo_batch_reuses_cache_and_falls_back_from_missing_thumbnail_to_original(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD required for photo thumbnails.');
        }
        config(['cache.default' => 'array', 'filesystems.upload_disk' => 's3',
            'filesystems.disks.s3.key' => 'test', 'filesystems.disks.s3.secret' => 'test',
            'filesystems.disks.s3.bucket' => 'test']);
        Cache::put('cached-photo', 'data:image/jpeg;base64,already');
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        $client = Mockery::mock();
        $client->shouldReceive('getObjectAsync')->once()->withArgs(fn ($args) => $args['Bucket'] === 'test'
            && $args['Key'] === 'domains/photos/a.jpg.thumb.jpg')->andReturn(Create::promiseFor(new Result(['Body' => $bytes])));
        $client->shouldReceive('getObjectAsync')->once()->withArgs(fn ($args) => $args['Key'] === 'domains/photos/b.jpg.thumb.jpg')
            ->andReturn(Create::rejectionFor(new \RuntimeException('Legacy thumbnail missing')));
        $client->shouldReceive('getObjectAsync')->once()->withArgs(fn ($args) => $args['Key'] === 'domains/photos/b.jpg')
            ->andReturn(Create::promiseFor(new Result(['Body' => $bytes])));
        $disk = Mockery::mock();
        $disk->shouldReceive('getClient')->once()->andReturn($client);
        $disk->shouldReceive('getConfig')->once()->andReturn(['root' => 'domains', 'bucket' => 'test']);
        Storage::shouldReceive('disk')->once()->with('s3')->andReturn($disk);

        TenantStorage::warmPhotoDataUris(['cached-photo' => 'photos/c.jpg', 'photo-a' => 'photos/a.jpg', 'photo-b' => 'photos/b.jpg']);
        $this->assertSame('data:image/jpeg;base64,already', Cache::get('cached-photo'));
        $this->assertStringStartsWith('data:image/jpeg;base64,', Cache::get('photo-a'));
        $this->assertStringStartsWith('data:image/jpeg;base64,', Cache::get('photo-b'));
        TenantStorage::warmPhotoDataUris(['photo-a' => 'photos/a.jpg', 'photo-b' => 'photos/b.jpg']);
    }
}
