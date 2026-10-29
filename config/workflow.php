<?php

return [
    'timezone' => env('ORGANIZATION_TIMEZONE', 'Asia/Jakarta'),
    'evidence_max_kb' => (int) env('EVIDENCE_MAX_KB', 5120),
    'evidence_max_files' => (int) env('EVIDENCE_MAX_FILES', 6),
    'evidence_types' => ['jpg', 'jpeg', 'png', 'webp'],
    'account_target' => 10,
    'platforms' => [
        'YouTube' => ['like', 'komentar', 'share', 'view'],
        'Instagram' => ['like', 'komentar', 'share', 'save', 'view'],
        'Facebook' => ['like', 'komentar', 'share', 'save', 'view'],
        'TikTok' => ['like', 'komentar', 'share', 'save', 'view', 'repost'],
    ],
    'account_platforms' => ['Google/Gmail', 'YouTube', 'Instagram', 'Facebook', 'TikTok'],
];
