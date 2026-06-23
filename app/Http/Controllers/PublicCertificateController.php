<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\Events\FestCertificateService;

class PublicCertificateController extends Controller
{
    public function verify(string $uuid)
    {
        $certificate = Certificate::where('verification_uuid', $uuid)->firstOrFail();
        $payload = app(FestCertificateService::class)->payloadFor($certificate);

        return view('fest.certificate-verify', $payload);
    }

    public function print(string $uuid)
    {
        $certificate = Certificate::where('verification_uuid', $uuid)->firstOrFail();
        $payload = app(FestCertificateService::class)->payloadFor($certificate);

        return view('fest.certificate-print', $payload);
    }
}
