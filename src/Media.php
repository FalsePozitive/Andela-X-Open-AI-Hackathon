<?php
declare(strict_types=1);
namespace Passport;

final class Media
{
    public const TYPES = ['image/jpeg' => 'image', 'image/png' => 'image', 'image/webp' => 'image', 'audio/mpeg' => 'audio', 'audio/wav' => 'audio', 'audio/x-wav' => 'audio', 'video/mp4' => 'video', 'video/webm' => 'video'];

    public static function store(array $file, string $type, string $directory): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Problem('UPLOAD', 'Choose a valid file of up to 10 MB.');
        }
        return self::process($file['tmp_name'], $type, $directory);
    }

    public static function process(string $source, string $type, string $directory): array
    {
        $size = filesize($source);
        if ($size === false || $size < 1 || $size > 10 * 1024 * 1024) {
            throw new Problem('UPLOAD', 'Files must be between 1 byte and 10 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        if (!is_string($mime) || (self::TYPES[$mime] ?? null) !== $type) {
            throw new Problem('UPLOAD', 'The file contents do not match a supported media type.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create media storage.');
        }
        $name = bin2hex(random_bytes(24));
        $target = $directory . '/' . $name;
        if ($type === 'image') {
            $dimensions = @getimagesize($source);
            if (!$dimensions || $dimensions[0] * $dimensions[1] > 16000000) {
                throw new Problem('UPLOAD', 'Images must be at most 16 megapixels.');
            }
            $image = @imagecreatefromstring((string) file_get_contents($source));
            if (!$image) {
                throw new Problem('UPLOAD', 'This image could not be read.');
            }
            // Decode and create new pixels: no EXIF or ancillary metadata is copied.
            $ok = imagewebp($image, $target, 85);
            imagedestroy($image);
            $mime = 'image/webp';
        } else {
            $ok = copy($source, $target);
        }
        if (!$ok) {
            throw new \RuntimeException('Could not save media.');
        }
        chmod($target, 0600);
        return ['filePath' => $name, 'mimeType' => $mime, 'contentHash' => hash_file('sha256', $target)];
    }
}
