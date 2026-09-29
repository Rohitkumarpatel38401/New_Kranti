<?php

namespace App\Support;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR codes as PNG data URIs, ready to embed in PDF views.
 */
class Qr
{
    public static function dataUri(string $content, int $scale = 5): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => true,
            'scale' => $scale,
            'quietzoneSize' => 1,
        ]);

        return (new QRCode($options))->render($content);
    }
}
