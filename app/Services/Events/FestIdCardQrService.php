<?php

namespace App\Services\Events;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class FestIdCardQrService
{
    public function dataUri(string $payload): string
    {
        $writer = new PngWriter;
        $qr = new QrCode(
            data: $payload,
            size: 120,
            margin: 2,
        );

        $result = $writer->write($qr);

        return 'data:image/png;base64,'.base64_encode($result->getString());
    }
}
