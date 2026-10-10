<?php

namespace Tests\Unit\Services\Events;

use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Services\Events\FestCertificateService;
use Tests\TestCase;

class CertificateItemPrintOrderTest extends TestCase
{
    public function test_item_prints_rank_then_school_with_unranked_last(): void
    {
        $certificates = collect();
        $payloads = collect();
        foreach ([[1, null, 'Alpha'], [2, 2, 'Alpha'], [3, 1, 'Zulu'], [4, 1, 'Alpha']] as [$id, $rank, $school]) {
            $certificate = new Certificate;
            $certificate->id = $id;
            $certificate->cert_type = 'winner';
            $certificates->push($certificate);
            $registration = new FestRegistration;
            $registration->setRelation('school', new Tenant(['name' => $school]));
            $registration->setRelation('originSchool', null);
            $payloads->put($id, ['mark' => new FestMark(['position' => $rank]), 'registration' => $registration]);
        }
        $service = \Mockery::mock(FestCertificateService::class)->makePartial();
        $service->shouldReceive('exportScope')->andReturn([$certificates, $payloads]);
        $service->shouldReceive('exportContextBuilder')->andReturn(fn ($certificate, $payload) => ['id' => $certificate->id]);

        $rows = $service->exportPayloadsForEvent(new FestEvent, false, false, itemId: 10);

        $this->assertSame([4, 3, 2, 1], $rows->pluck('id')->all());
        $certificates->each(fn ($certificate) => $certificate->cert_type = 'participation');
        $participation = $service->exportPayloadsForEvent(new FestEvent, false, false, itemId: 10);
        $this->assertSame([1, 2, 3, 4], $participation->pluck('id')->all());
    }
}
