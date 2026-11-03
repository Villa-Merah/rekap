<?php

$browserPaths = PHP_OS_FAMILY === 'Windows' ? [
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
] : ['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'];

return [
    'browser' => env('REPORT_PDF_BROWSER', collect($browserPaths)->first(fn (string $path): bool => is_file($path))),
];
