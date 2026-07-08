<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\TrainingRegistration;
use App\Services\Events\FestCertificateService;
use App\Services\Events\FestIdCardQrService;
use App\Services\Training\TrainingCertificateService;

class PublicCertificateController extends Controller
{
    public function verify(string $uuid)
    {
        $certificate = Certificate::where('verification_uuid', $uuid)->firstOrFail();

        if ($certificate->entity_type === TrainingRegistration::class) {
            $registration = TrainingRegistration::with(['program', 'teacher', 'school'])->findOrFail($certificate->entity_id);
            $sahodaya = \App\Models\Tenant::findOrFail($registration->program->tenant_id);
            $service = app(TrainingCertificateService::class);

            return view('training.certificate-verify', [
                'certificate'  => $certificate,
                'registration' => $registration,
                'sahodaya'     => $sahodaya,
                'fieldValues'  => $service->resolveFieldValues($registration, $sahodaya),
                'daysPresent'  => $service->presentDaysCount($registration),
            ]);
        }

        $payload = app(FestCertificateService::class)->payloadFor($certificate);
        $payload['qr_src'] = app(FestIdCardQrService::class)->dataUri(route('certificates.verify', $certificate->verification_uuid, absolute: true));

        return view('fest.certificate-verify', $payload);
    }

    public function print(string $uuid)
    {
        $certificate = Certificate::where('verification_uuid', $uuid)->firstOrFail();

        if ($certificate->entity_type === TrainingRegistration::class) {
            $registration = TrainingRegistration::with(['program', 'teacher'])->findOrFail($certificate->entity_id);
            $sahodaya = \App\Models\Tenant::findOrFail($registration->program->tenant_id);
            $service = app(TrainingCertificateService::class);
            $render = $service->renderContext($registration, $sahodaya);

            return view('training.certificate', array_merge($render, [
                'registration' => $registration,
                'certificate'  => $certificate,
                'sahodaya'     => $sahodaya,
                'fieldValues'  => $service->resolveFieldValues($registration, $sahodaya),
            ]));
        }

        $payload = app(FestCertificateService::class)->payloadFor($certificate);
        $payload['qr_src'] = app(FestIdCardQrService::class)->dataUri(route('certificates.verify', $certificate->verification_uuid, absolute: true));

        return view('fest.certificate-print', $payload);
    }
}
