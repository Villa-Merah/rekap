<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class StaffReportPdf
{
    public static function render(string $html): string
    {
        $browser = config('reports.browser');
        if (! $browser || ! is_file($browser)) {
            throw new RuntimeException('Browser PDF tidak tersedia. Atur REPORT_PDF_BROWSER.');
        }
        $directory = storage_path('app/private/report-temp/'.Str::uuid());
        File::makeDirectory($directory, 0700, true);
        try {
            $source = $directory.'/report.html';
            $output = $directory.'/report.pdf';
            File::put($source, $html);
            $url = 'file:///'.ltrim(str_replace('\\', '/', $source), '/');
            $result = Process::timeout(90)->run([
                $browser, '--headless', '--disable-gpu', '--disable-extensions', '--disable-background-networking',
                '--no-first-run', '--no-default-browser-check', '--no-pdf-header-footer',
                '--user-data-dir='.$directory.'/profile', '--print-to-pdf='.$output, $url,
            ]);
            if (! $result->successful() || ! is_file($output)) {
                throw new RuntimeException('Browser gagal menghasilkan laporan PDF.');
            }
            $pdf = File::get($output);
            if (! str_starts_with($pdf, '%PDF-')) {
                throw new RuntimeException('Hasil ekspor bukan PDF yang valid.');
            }

            return $pdf;
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
