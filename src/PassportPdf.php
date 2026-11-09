<?php
declare(strict_types=1);
namespace Passport;

use Dompdf\Dompdf;
use Dompdf\Options;

final class PassportPdf
{
    public static function render(array $rawStory, array $config): string
    {
        // Always apply the same public projection as the passport, including
        // for steward callers. Never embed the raw registry record in a PDF.
        $story = Rules::publicStory($rawStory);
        $restricted = $story['sensitivity'] === 'RESTRICTED';
        $cache = $config['storage_path'] . '/pdf-cache';
        if (!is_dir($cache) && !mkdir($cache, 0700, true) && !is_dir($cache)) {
            throw new \RuntimeException('Cannot create PDF cache.');
        }
        $cacheFile = $cache . '/' . hash('sha256', 'pdf-v4|' . json_encode($story, JSON_THROW_ON_ERROR) . '|' . $config['base_url'] . '|' . gmdate('Y-m-d')) . '.pdf';
        if (is_file($cacheFile)) {return (string) file_get_contents($cacheFile);}
        $options = new Options();
        $options->set('isRemoteEnabled', false); $options->set('isPhpEnabled', false); $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans'); $options->set('chroot', dirname(__DIR__));
        $options->set('tempDir', $cache); $options->set('fontCache', $cache);
        $qr = 'data:image/png;base64,' . base64_encode(Watermark::qr($rawStory, $config, 220));
        $logo = 'data:image/png;base64,' . base64_encode((string) file_get_contents(dirname(__DIR__) . '/resources/okwethu-logo.png'));
        $image = null;
        if (!$restricted && $rawStory['mediaType'] === 'image' && $rawStory['filePath']) {
            $marked = imagecreatefrompng(Watermark::image($rawStory, $config));
            if ($marked) {
                $scale = min(1, 1200 / max(imagesx($marked), imagesy($marked)));
                $thumbnail = imagescale($marked, max(1, (int) round(imagesx($marked) * $scale)), max(1, (int) round(imagesy($marked) * $scale)));
                imagedestroy($marked);
                if ($thumbnail) {ob_start();imagepng($thumbnail);$image = 'data:image/png;base64,' . base64_encode((string) ob_get_clean());imagedestroy($thumbnail);}
            }
        }
        $e = Web::escape(...);
        $url = Watermark::url($rawStory, $config);
        $credit=Rules::credit($rawStory,$config['base_url']);
        $title = $story['title'] ?? Copy::UI['restricted'];
        $issued = gmdate('d M Y') . ' (UTC)';
        ob_start();require dirname(__DIR__) . '/templates/pdf.php';$html = (string) ob_get_clean();
        $pdf = new Dompdf($options); $pdf->setPaper('A4'); $pdf->loadHtml($html, 'UTF-8'); $pdf->render();
        $pdf->getCanvas()->page_text(474, 804, 'Page {PAGE_NUM} / {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [0.3, 0.25, 0.15]);
        $bytes = $pdf->output();
        $temporary = tempnam($cache, 'pdf-');
        if ($temporary !== false) {file_put_contents($temporary, $bytes);chmod($temporary, 0600);if (!rename($temporary, $cacheFile)) {unlink($temporary);}}
        return $bytes;
    }
}

