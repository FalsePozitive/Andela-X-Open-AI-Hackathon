<?php
declare(strict_types=1);
namespace Passport;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

final class Watermark
{
    public static function url(array $story, array $config): string
    {
        if (!preg_match('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}$/', $story['id'])) {
            throw new Problem('NOT_FOUND', 'Story not found.', 404);
        }
        return rtrim($config['base_url'], '/') . '/p/' . $story['id'];
    }

    public static function qr(array $story, array $config, int $size = 132): string
    {
        return (new PngWriter())->write(QrCode::create(self::url($story, $config))->setSize($size)->setMargin(16))->getString();
    }

    private static function directory(array $config): string
    {
        $path = $config['storage_path'] . '/derivatives';
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new \RuntimeException('Cannot create private derivative storage.');
        }
        return $path;
    }

    public static function original(array $story, array $config): string
    {
        if ($story['sensitivity'] !== 'OPEN' || !is_string($story['filePath']) || !preg_match('/^[a-f0-9]{48}$/', $story['filePath'])) {
            throw new Problem('NOT_FOUND', 'Media not available.', 404);
        }
        $path = $config['storage_path'] . '/uploads/' . $story['filePath'];
        if (!is_file($path)) {
            throw new Problem('NOT_FOUND', 'Media not available.', 404);
        }
        return $path;
    }

    public static function image(array $story, array $config): string
    {
        $source = self::original($story, $config);
        if ($story['mediaType'] !== 'image') {
            throw new Problem('NOT_FOUND', 'Image not available.', 404);
        }
        $path = self::directory($config) . '/' . hash('sha256', 'image-v1|' . $story['id'] . '|' . hash_file('sha256', $source) . '|' . self::url($story, $config)) . '.png';
        if (is_file($path)) {
            return $path;
        }
        $original = @imagecreatefromstring((string) file_get_contents($source));
        if (!$original) {
            throw new Problem('MEDIA', 'The stored image could not be read.', 422);
        }
        $width = imagesx($original); $height = imagesy($original);
        $canvas = imagecreatetruecolor(max(640, $width), max(360, $height));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 251, 241));
        imagecopy($canvas, $original, intdiv(imagesx($canvas) - $width, 2), intdiv(imagesy($canvas) - $height, 2), 0, 0, $width, $height);
        imagedestroy($original);
        self::badge($canvas, $story, $config);
        self::save($canvas, $path);
        return $path;
    }

    private static function badge(\GdImage $canvas, array $story, array $config): void
    {
        $qr = imagecreatefromstring(self::qr($story, $config));
        if (!$qr) {throw new \RuntimeException('QR image failed.');}
        $width = imagesx($qr) + 16; $height = imagesy($qr) + 46;
        $x = imagesx($canvas) - $width - 12; $y = imagesy($canvas) - $height - 12;
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $gold = imagecolorallocate($canvas, 218, 165, 32);
        $ink = imagecolorallocate($canvas, 51, 37, 19);
        imagefilledrectangle($canvas, $x, $y, $x + $width - 1, $y + $height - 1, $white);
        imagerectangle($canvas, $x, $y, $x + $width - 1, $y + $height - 1, $gold);
        imagecopy($canvas, $qr, $x + 8, $y + 5, 0, 0, imagesx($qr), imagesy($qr));
        imagestring($canvas, 4, $x + 12, $y + $height - 36, 'okwethu', $ink);
        imagestring($canvas, 2, $x + 12, $y + $height - 18, $story['id'], $ink);
        imagedestroy($qr);
    }

    public static function cover(array $story, array $config): string
    {
        self::original($story, $config);
        $path = self::directory($config) . '/' . hash('sha256', 'cover-v1|' . $story['id'] . '|' . $story['title'] . '|' . self::url($story, $config)) . '.png';
        if (is_file($path)) {return $path;}
        $canvas = imagecreatetruecolor(800, 800);
        $gold = imagecolorallocate($canvas, 218, 165, 32); $paper = imagecolorallocate($canvas, 255, 251, 241); $ink = imagecolorallocate($canvas, 51, 37, 19);
        imagefill($canvas, 0, 0, $paper); imagefilledrectangle($canvas, 0, 0, 799, 24, $gold);
        $logoPath = dirname(__DIR__) . '/resources/okwethu-logo.png';
        if (is_file($logoPath)) {
            $logo = imagecreatefrompng($logoPath);
            if ($logo) {imagecopyresampled($canvas, $logo, 40, 50, 0, 0, 640, 224, imagesx($logo), imagesy($logo)); imagedestroy($logo);}
        }
        imagestring($canvas, 5, 55, 330, 'STORY PASSPORT', $ink);
        imagestring($canvas, 5, 55, 370, $story['id'], $ink);
        $font = dirname(__DIR__) . '/vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf';
        if (is_file($font) && function_exists('imagettftext')) {
            $lines = explode("\n", wordwrap(mb_strimwidth($story['title'], 0, 75, '...'), 32));
            foreach (array_slice($lines, 0, 3) as $i => $line) {imagettftext($canvas, 22, 0, 55, 440 + $i * 36, $ink, $font, $line);}
        }
        imagestring($canvas, 3, 55, 650, 'Scan for credit, context and declared terms.', $ink);
        imagestring($canvas, 2, 55, 680, 'A record of a claim, not proof of ownership.', $ink);
        self::badge($canvas, $story, $config); self::save($canvas, $path);
        return $path;
    }

    public static function audioZip(array $story, array $config): string
    {
        $source = self::original($story, $config);
        if ($story['mediaType'] !== 'audio') {throw new Problem('NOT_FOUND', 'Audio not available.', 404);}
        if (!class_exists(\ZipArchive::class)) {throw new Problem('UNAVAILABLE', 'Audio packages need the PHP ZIP extension enabled by the host.', 503);}
        $extension = $story['mimeType'] === 'audio/mpeg' ? 'mp3' : 'wav';
        $path = self::directory($config) . '/' . hash('sha256', 'audio-v1|' . $story['id'] . '|' . hash_file('sha256', $source) . '|' . self::url($story, $config)) . '.zip';
        if (is_file($path)) {return $path;}
        $cover = self::cover($story, $config); $temporary = tempnam(dirname($path), 'zip-');
        if ($temporary === false) {throw new \RuntimeException('Cannot create audio package.');}
        $zip = new \ZipArchive();
        if ($zip->open($temporary, \ZipArchive::OVERWRITE) !== true) {throw new \RuntimeException('Cannot write audio package.');}
        $zip->addFile($source, 'audio-' . $story['id'] . '.' . $extension);
        $zip->addFile($cover, 'okwethu-qr-cover.png');
        $zip->addFromString('READ-ME.txt', 'Okwethu story passport: ' . self::url($story, $config) . "\nKeep the QR cover with this audio when sharing. The QR is on the cover, not in the sound.\nRead the live passport and obtain any required permission before use. A QR is not proof of ownership or permission.\n");
        $zip->close(); chmod($temporary, 0600);
        if (!rename($temporary, $path)) {unlink($temporary); throw new \RuntimeException('Cannot save audio package.');}
        return $path;
    }

    private static function save(\GdImage $image, string $path): void
    {
        $temporary = tempnam(dirname($path), 'img-');
        if ($temporary === false) {throw new \RuntimeException('Cannot create image.');}
        $ok = imagepng($image, $temporary, 6); imagedestroy($image); chmod($temporary, 0600);
        if (!$ok || !rename($temporary, $path)) {unlink($temporary); throw new \RuntimeException('Cannot save image.');}
    }
}
