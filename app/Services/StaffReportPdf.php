<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class StaffReportPdf
{
    public static function render(string $html): string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('Ekstensi PHP GD diperlukan untuk gambar bukti pada PDF.');
        }
        $directory = storage_path('app/private/report-temp/'.Str::uuid());
        File::makeDirectory($directory, 0700, true);
        try {
            $options = new Options([
                'tempDir' => $directory,
                'fontDir' => $directory,
                'fontCache' => $directory,
                'chroot' => [$directory],
                'allowedProtocols' => ['data://'],
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'isJavascriptEnabled' => false,
                'isFontSubsettingEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);
            $document = new Dompdf($options);
            $document->loadHtml($html, 'UTF-8');
            $document->setPaper('A4', 'portrait');
            $document->render();
            $canvas = $document->getCanvas();
            $font = $document->getFontMetrics()->getFont('DejaVu Sans');
            $canvas->page_text(430, 812, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', $font, 8, [0.4, 0.46, 0.43]);
            $pdf = $document->output();
            if (! str_starts_with($pdf, '%PDF-')) {
                throw new RuntimeException('Hasil ekspor bukan PDF yang valid.');
            }

            return $pdf;
        } catch (\Exception $exception) {
            throw new RuntimeException('Pembuatan laporan PDF gagal.', previous: $exception);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
