<?php

namespace App\Services\Events;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class FestIdCardQrService
{
    public function dataUri(string $payload, int $size = 300, int $margin = 1): string
    {
        $writer = new PngWriter;
        $qr = new QrCode(
            data: $payload,
            size: $size,
            margin: $margin,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
        );

        $result = $writer->write($qr);

        return 'data:image/png;base64,'.base64_encode($result->getString());
    }
}
