<?php

namespace App\Services\Training;

use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingSession;
use App\Support\TenantBranding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Str;

class TrainingQrService
{
    public function ensureProgramTokens(TrainingProgram $program): TrainingProgram
    {
        $dirty = false;

        if (! filled($program->qr_registration_token)) {
            $program->qr_registration_token = $this->uniqueToken('qr_registration_token');
            $dirty = true;
        }

        if (! filled($program->attendance_qr_token)) {
            $program->attendance_qr_token = $this->uniqueToken('attendance_qr_token');
            $dirty = true;
        }

        if ($dirty) {
            $program->save();
        }

        return $program;
    }

    public function ensureSessionToken(TrainingSession $session): TrainingSession
    {
        if (! filled($session->attendance_token)) {
            $session->attendance_token = $this->uniqueToken('attendance_token', TrainingSession::class);
            $session->save();
        }

        return $session;
    }

    public function registrationUrl(TrainingProgram $program): string
    {
        $this->ensureProgramTokens($program);

        return url('/training/register/'.$program->qr_registration_token);
    }

    public function attendanceUrl(TrainingProgram $program, ?TrainingSession $session = null): string
    {
        if ($session) {
            $this->ensureSessionToken($session);

            return url('/training/attendance/'.$session->attendance_token);
        }

        $this->ensureProgramTokens($program);

        return url('/training/attendance/program/'.$program->attendance_qr_token);
    }

    /**
     * Branding payload for printable / downloadable QR posters.
     *
     * @return array{
     *     org_name: string,
     *     logo_src: ?string,
     *     program_title: string,
     *     label: string,
     *     instruction: string,
     *     venue: ?string,
     *     dates: ?string,
     *     url: string
     * }
     */
    public function posterBranding(
        Tenant $sahodaya,
        TrainingProgram $program,
        string $url,
        string $label,
        string $instruction,
        ?TrainingSession $session = null,
    ): array {
        $venue = $session?->venue ?: $program->venue;
        $dates = null;

        if ($session?->scheduled_at) {
            $dates = $session->scheduled_at->timezone(config('app.timezone'))->format('d M Y · h:i A');
        } elseif ($program->start_date) {
            $dates = $program->start_date->format('d M Y');
            if ($program->end_date && ! $program->end_date->isSameDay($program->start_date)) {
                $dates .= ' – '.$program->end_date->format('d M Y');
            }
        }

        return [
            'org_name' => $sahodaya->name,
            'logo_src' => TenantBranding::logoEmbedSrc($sahodaya),
            'program_title' => $program->title,
            'label' => $label,
            'instruction' => $instruction,
            'venue' => filled($venue) ? $venue : null,
            'dates' => $dates,
            'url' => $url,
        ];
    }

    public function png(string $url, int $size = 400): string
    {
        $writer = new PngWriter;
        $qr = new QrCode(data: $url, size: $size, margin: 10);

        return $writer->write($qr)->getString();
    }

    public function svg(string $url, int $size = 400): string
    {
        $writer = new SvgWriter;
        $qr = new QrCode(data: $url, size: $size, margin: 10);

        return $writer->write($qr)->getString();
    }

    /** Branded poster PNG suitable for print / WhatsApp sharing. */
    public function brandedPng(string $url, array $branding, int $width = 900): string
    {
        $height = (int) round($width * 1.32);
        $img = imagecreatetruecolor($width, $height);
        if ($img === false) {
            return $this->png($url, 400);
        }

        $navy = imagecolorallocate($img, 15, 39, 68);
        $accent = imagecolorallocate($img, 30, 90, 158);
        $white = imagecolorallocate($img, 255, 255, 255);
        $slate = imagecolorallocate($img, 51, 65, 85);
        $muted = imagecolorallocate($img, 100, 116, 139);
        $panel = imagecolorallocate($img, 248, 250, 252);
        $border = imagecolorallocate($img, 226, 232, 240);

        imagefilledrectangle($img, 0, 0, $width, $height, $white);
        imagefilledrectangle($img, 0, 0, $width, (int) round($height * 0.18), $navy);
        imagefilledrectangle($img, 0, (int) round($height * 0.18), $width, (int) round($height * 0.185), $accent);

        $pad = (int) round($width * 0.07);
        $font = $this->ttfPath();
        $fontBold = $this->ttfBoldPath() ?? $font;
        $y = (int) round($height * 0.04);

        $logoSize = (int) round($width * 0.09);
        $logoX = $pad;
        $textX = $pad;
        if ($branding['logo_src'] ?? null) {
            $logo = $this->gdImageFromSrc($branding['logo_src']);
            if ($logo) {
                $this->drawContain($img, $logo, $logoX, $y, $logoSize, $logoSize);
                imagedestroy($logo);
                $textX = $logoX + $logoSize + (int) round($width * 0.025);
            }
        }

        if ($font) {
            $orgSize = $width * 0.028;
            $orgLines = $this->wrapText($font, $orgSize, (string) ($branding['org_name'] ?? 'Sahodaya'), (int) ($width - $textX - $pad));
            $orgY = $y + (int) round($logoSize * 0.35);
            foreach ($orgLines as $line) {
                imagettftext($img, $orgSize, 0, $textX, $orgY, $white, $fontBold ?? $font, $line);
                $orgY += (int) round($orgSize * 1.35);
            }

            $tagSize = $width * 0.018;
            imagettftext($img, $tagSize, 0, $textX, $orgY + (int) round($tagSize * 0.4), imagecolorallocate($img, 186, 210, 235), $font, 'Teacher Training');
        }

        $contentTop = (int) round($height * 0.22);
        if ($font) {
            $labelSize = $width * 0.022;
            $label = strtoupper((string) ($branding['label'] ?? 'QR Code'));
            $labelBox = imagettfbbox($labelSize, 0, $fontBold ?? $font, $label);
            $labelW = abs(($labelBox[2] ?? 0) - ($labelBox[0] ?? 0));
            $badgePadX = (int) round($width * 0.02);
            $badgePadY = (int) round($width * 0.012);
            $badgeX = (int) (($width - $labelW) / 2) - $badgePadX;
            $badgeY = $contentTop;
            imagefilledrectangle(
                $img,
                $badgeX,
                $badgeY,
                $badgeX + $labelW + ($badgePadX * 2),
                $badgeY + (int) round($labelSize) + ($badgePadY * 2),
                $accent
            );
            imagettftext(
                $img,
                $labelSize,
                0,
                $badgeX + $badgePadX,
                $badgeY + (int) round($labelSize) + $badgePadY - 2,
                $white,
                $fontBold ?? $font,
                $label
            );

            $titleSize = $width * 0.038;
            $titleLines = $this->wrapText($fontBold ?? $font, $titleSize, (string) ($branding['program_title'] ?? ''), (int) ($width - ($pad * 2)));
            $titleY = $badgeY + (int) round($labelSize) + ($badgePadY * 2) + (int) round($titleSize * 1.6);
            foreach ($titleLines as $line) {
                $box = imagettfbbox($titleSize, 0, $fontBold ?? $font, $line);
                $lineW = abs(($box[2] ?? 0) - ($box[0] ?? 0));
                imagettftext($img, $titleSize, 0, (int) (($width - $lineW) / 2), $titleY, $navy, $fontBold ?? $font, $line);
                $titleY += (int) round($titleSize * 1.35);
            }

            $metaY = $titleY + (int) round($width * 0.015);
            $metaSize = $width * 0.02;
            foreach (array_filter([$branding['dates'] ?? null, $branding['venue'] ?? null]) as $meta) {
                $box = imagettfbbox($metaSize, 0, $font, (string) $meta);
                $lineW = abs(($box[2] ?? 0) - ($box[0] ?? 0));
                imagettftext($img, $metaSize, 0, (int) (($width - $lineW) / 2), $metaY, $slate, $font, (string) $meta);
                $metaY += (int) round($metaSize * 1.45);
            }

            $qrTop = $metaY + (int) round($width * 0.03);
        } else {
            $qrTop = $contentTop + 40;
        }

        $qrSize = (int) round($width * 0.48);
        $qrBinary = $this->png($url, $qrSize);
        $qr = @imagecreatefromstring($qrBinary);
        if ($qr) {
            $framePad = (int) round($width * 0.025);
            $frameX = (int) (($width - $qrSize) / 2) - $framePad;
            $frameY = $qrTop;
            imagefilledrectangle($img, $frameX, $frameY, $frameX + $qrSize + ($framePad * 2), $frameY + $qrSize + ($framePad * 2), $panel);
            imagerectangle($img, $frameX, $frameY, $frameX + $qrSize + ($framePad * 2), $frameY + $qrSize + ($framePad * 2), $border);
            imagecopy($img, $qr, $frameX + $framePad, $frameY + $framePad, 0, 0, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
            $afterQr = $frameY + $qrSize + ($framePad * 2) + (int) round($width * 0.04);
        } else {
            $afterQr = $qrTop + $qrSize;
        }

        if ($font) {
            $instrSize = $width * 0.022;
            $instr = (string) ($branding['instruction'] ?? 'Scan with your phone camera');
            $box = imagettfbbox($instrSize, 0, $fontBold ?? $font, $instr);
            $lineW = abs(($box[2] ?? 0) - ($box[0] ?? 0));
            imagettftext($img, $instrSize, 0, (int) (($width - $lineW) / 2), $afterQr, $accent, $fontBold ?? $font, $instr);

            $urlMaxWidth = (int) round($width * 0.72);
            $urlSize = $width * 0.0115;
            $urlLines = $this->wrapUrlLines($font, $urlSize, (string) ($branding['url'] ?? $url), $urlMaxWidth);
            $urlY = $afterQr + (int) round($instrSize * 1.7);
            $footerTop = $height - (int) round($height * 0.035) - (int) round($urlSize);
            foreach ($urlLines as $line) {
                if ($urlY > $footerTop) {
                    break;
                }
                $box = imagettfbbox($urlSize, 0, $font, $line);
                $lineW = abs(($box[2] ?? 0) - ($box[0] ?? 0));
                imagettftext($img, $urlSize, 0, (int) (($width - $lineW) / 2), $urlY, $muted, $font, $line);
                $urlY += (int) round($urlSize * 1.45);
            }
        }

        imagefilledrectangle($img, 0, $height - (int) round($height * 0.035), $width, $height, $navy);

        ob_start();
        imagepng($img);
        $binary = (string) ob_get_clean();
        imagedestroy($img);

        return $binary !== '' ? $binary : $this->png($url, 400);
    }

    /** Branded poster SVG. */
    public function brandedSvg(string $url, array $branding, int $width = 900): string
    {
        $height = (int) round($width * 1.32);
        $qrSize = (int) round($width * 0.48);
        $qrData = 'data:image/png;base64,'.base64_encode($this->png($url, $qrSize));
        $logo = $branding['logo_src'] ?? null;
        $org = htmlspecialchars((string) ($branding['org_name'] ?? 'Sahodaya'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars((string) ($branding['program_title'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars(strtoupper((string) ($branding['label'] ?? 'QR Code')), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $instruction = htmlspecialchars((string) ($branding['instruction'] ?? 'Scan with your phone camera'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $dates = isset($branding['dates']) ? htmlspecialchars((string) $branding['dates'], ENT_XML1 | ENT_QUOTES, 'UTF-8') : null;
        $venue = isset($branding['venue']) ? htmlspecialchars((string) $branding['venue'], ENT_XML1 | ENT_QUOTES, 'UTF-8') : null;
        $linkRaw = (string) ($branding['url'] ?? $url);
        $pad = (int) round($width * 0.07);
        $logoSize = (int) round($width * 0.09);
        $headerH = (int) round($height * 0.18);
        $accentH = max(4, (int) round($height * 0.005));
        $qrX = (int) (($width - $qrSize) / 2);
        $qrY = (int) round($height * 0.42);
        $framePad = (int) round($width * 0.025);
        $cx = $width / 2;
        $orgSize = round($width * 0.028, 2);
        $tagSize = round($width * 0.018, 2);
        $labelSize = round($width * 0.02, 2);
        $titleSize = round($width * 0.036, 2);
        $metaSize = round($width * 0.02, 2);
        $instrSize = round($width * 0.022, 2);
        $urlSize = round($width * 0.0115, 2);
        $logoY = (int) round($height * 0.04);
        $orgY = (int) round($height * 0.09);
        $tagY = (int) round($height * 0.125);
        $badgeW = (int) round($width * 0.36);
        $badgeH = (int) round($width * 0.045);
        $badgeX = (int) (($width - $badgeW) / 2);
        $badgeY = (int) round($height * 0.22);
        $labelY = (int) round($height * 0.248);
        $titleY = (int) round($height * 0.30);
        $footerH = (int) round($height * 0.035);
        $footerY = $height - $footerH;
        $instrY = (int) ($qrY + $qrSize + $framePad + ($width * 0.045));
        $urlY = (int) ($qrY + $qrSize + $framePad + ($width * 0.075));
        $textX = $logo ? $pad + $logoSize + (int) round($width * 0.025) : $pad;
        $frameX = $qrX - $framePad;
        $frameY = $qrY - $framePad;
        $frameW = $qrSize + ($framePad * 2);
        $frameH = $qrSize + ($framePad * 2);

        $logoMarkup = '';
        if ($logo) {
            $logoEsc = htmlspecialchars($logo, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $logoMarkup = '<image href="'.$logoEsc.'" x="'.$pad.'" y="'.$logoY.'" width="'.$logoSize.'" height="'.$logoSize.'" preserveAspectRatio="xMidYMid meet"/>';
        }

        $metaLines = '';
        $metaY = (int) round($height * 0.34);
        if ($dates) {
            $metaLines .= '<text x="'.$cx.'" y="'.$metaY.'" text-anchor="middle" fill="#334155" font-size="'.$metaSize.'" font-family="DejaVu Sans, Arial, sans-serif">'.$dates.'</text>';
            $metaY += (int) round($width * 0.03);
        }
        if ($venue) {
            $metaLines .= '<text x="'.$cx.'" y="'.$metaY.'" text-anchor="middle" fill="#334155" font-size="'.$metaSize.'" font-family="DejaVu Sans, Arial, sans-serif">'.$venue.'</text>';
        }

        $urlMarkup = '';
        $font = $this->ttfPath();
        $urlLineHeight = (int) round($urlSize * 1.5);
        if ($font) {
            $urlLines = $this->wrapUrlLines($font, (float) $urlSize, $linkRaw, (int) round($width * 0.72));
        } else {
            $urlLines = $this->chunkUrl($linkRaw, 48);
        }
        foreach ($urlLines as $i => $line) {
            $y = $urlY + ($i * $urlLineHeight);
            if ($y > $footerY - 4) {
                break;
            }
            $escaped = htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $urlMarkup .= '<text x="'.$cx.'" y="'.$y.'" text-anchor="middle" fill="#64748b" font-size="'.$urlSize.'" font-family="DejaVu Sans, Arial, sans-serif">'.$escaped.'</text>';
        }

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
  <rect width="100%" height="100%" fill="#ffffff"/>
  <rect width="100%" height="{$headerH}" fill="#0f2744"/>
  <rect y="{$headerH}" width="100%" height="{$accentH}" fill="#1e5a9e"/>
  {$logoMarkup}
  <text x="{$textX}" y="{$orgY}" fill="#ffffff" font-size="{$orgSize}" font-weight="700" font-family="DejaVu Sans, Arial, sans-serif">{$org}</text>
  <text x="{$textX}" y="{$tagY}" fill="#bad2eb" font-size="{$tagSize}" font-family="DejaVu Sans, Arial, sans-serif">Teacher Training</text>
  <rect x="{$badgeX}" y="{$badgeY}" width="{$badgeW}" height="{$badgeH}" rx="4" fill="#1e5a9e"/>
  <text x="{$cx}" y="{$labelY}" text-anchor="middle" fill="#ffffff" font-size="{$labelSize}" font-weight="700" font-family="DejaVu Sans, Arial, sans-serif">{$label}</text>
  <text x="{$cx}" y="{$titleY}" text-anchor="middle" fill="#0f2744" font-size="{$titleSize}" font-weight="700" font-family="DejaVu Sans, Arial, sans-serif">{$title}</text>
  {$metaLines}
  <rect x="{$frameX}" y="{$frameY}" width="{$frameW}" height="{$frameH}" fill="#f8fafc" stroke="#e2e8f0" stroke-width="2"/>
  <image href="{$qrData}" x="{$qrX}" y="{$qrY}" width="{$qrSize}" height="{$qrSize}"/>
  <text x="{$cx}" y="{$instrY}" text-anchor="middle" fill="#1e5a9e" font-size="{$instrSize}" font-weight="700" font-family="DejaVu Sans, Arial, sans-serif">{$instruction}</text>
  {$urlMarkup}
  <rect y="{$footerY}" width="100%" height="{$footerH}" fill="#0f2744"/>
</svg>
SVG;
    }

    public function dataUri(string $url, int $size = 240): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($url, $size));
    }

    public function isRegistrationOpen(TrainingProgram $program): bool
    {
        if (! $program->qr_registration_enabled) {
            return false;
        }

        if (! in_array($program->status, ['published', 'ongoing', 'registration_open'], true)) {
            return false;
        }

        $today = now()->startOfDay();

        if ($program->registration_open && $today->lt($program->registration_open->copy()->startOfDay())) {
            return false;
        }

        if ($program->registration_close && $today->gt($program->registration_close->copy()->startOfDay())) {
            return false;
        }

        return true;
    }

    private function uniqueToken(string $column, string $model = TrainingProgram::class): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while ($model::where($column, $token)->exists());

        return $token;
    }

    private function ttfPath(): ?string
    {
        $candidates = [
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial.ttf',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function ttfBoldPath(): ?string
    {
        $candidates = [
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** @return \GdImage|resource|null */
    private function gdImageFromSrc(string $src)
    {
        if (str_starts_with($src, 'data:')) {
            $parts = explode(',', $src, 2);
            if (count($parts) !== 2) {
                return null;
            }
            $binary = base64_decode($parts[1], true);
            if ($binary === false) {
                return null;
            }

            $img = @imagecreatefromstring($binary);

            return $img ?: null;
        }

        $path = $src;
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            return null;
        }
        if (str_starts_with($src, '/')) {
            $path = public_path(ltrim($src, '/'));
        }
        if (! is_file($path)) {
            return null;
        }

        $img = @imagecreatefromstring((string) file_get_contents($path));

        return $img ?: null;
    }

    /** @param \GdImage|resource $dst @param \GdImage|resource $src */
    private function drawContain($dst, $src, int $x, int $y, int $boxW, int $boxH): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw < 1 || $sh < 1) {
            return;
        }
        $scale = min($boxW / $sw, $boxH / $sh);
        $dw = max(1, (int) round($sw * $scale));
        $dh = max(1, (int) round($sh * $scale));
        $dx = $x + (int) (($boxW - $dw) / 2);
        $dy = $y + (int) (($boxH - $dh) / 2);
        imagecopyresampled($dst, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
    }

    /** @return list<string> */
    private function wrapUrlLines(string $font, float $size, string $url, int $maxWidth): array
    {
        $lines = $this->wrapText($font, $size, $url, $maxWidth, 6);
        if (count($lines) < 2) {
            return $lines;
        }

        // Avoid a tiny orphan last line (e.g. "uz6") by rebalancing.
        $last = $lines[count($lines) - 1];
        $prev = $lines[count($lines) - 2];
        if (mb_strlen($last) >= 12 || mb_strlen($prev) < 20) {
            return $lines;
        }

        $move = (int) ceil((mb_strlen($prev) - mb_strlen($last)) / 2);
        $move = min($move, mb_strlen($prev) - 12);
        if ($move < 4) {
            return $lines;
        }

        $lines[count($lines) - 2] = mb_substr($prev, 0, mb_strlen($prev) - $move);
        $lines[count($lines) - 1] = mb_substr($prev, mb_strlen($prev) - $move).$last;

        return $lines;
    }

    /** @return list<string> */
    private function chunkUrl(string $url, int $charsPerLine): array
    {
        return array_values(array_filter(str_split($url, max(16, $charsPerLine))));
    }

    /** @return list<string> */
    private function wrapText(string $font, float $size, string $text, int $maxWidth, int $maxLines = 4): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $text) ?: [$text];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            foreach ($this->splitTokenToFit($font, $size, $word, $maxWidth) as $piece) {
                $trial = $current === '' ? $piece : $current.' '.$piece;
                $box = imagettfbbox($size, 0, $font, $trial);
                $w = abs(($box[2] ?? 0) - ($box[0] ?? 0));
                if ($w <= $maxWidth || $current === '') {
                    $current = $trial;
                    continue;
                }
                $lines[] = $current;
                $current = $piece;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return array_slice($lines, 0, max(1, $maxLines));
    }

    /** @return list<string> */
    private function splitTokenToFit(string $font, float $size, string $token, int $maxWidth): array
    {
        $box = imagettfbbox($size, 0, $font, $token);
        $w = abs(($box[2] ?? 0) - ($box[0] ?? 0));
        if ($w <= $maxWidth) {
            return [$token];
        }

        $chars = preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($token);
        $parts = [];
        $chunk = '';
        foreach ($chars as $char) {
            $trial = $chunk.$char;
            $box = imagettfbbox($size, 0, $font, $trial);
            $tw = abs(($box[2] ?? 0) - ($box[0] ?? 0));
            if ($tw <= $maxWidth || $chunk === '') {
                $chunk = $trial;
                continue;
            }
            $parts[] = $chunk;
            $chunk = $char;
        }
        if ($chunk !== '') {
            $parts[] = $chunk;
        }

        return $parts !== [] ? $parts : [$token];
    }
}
