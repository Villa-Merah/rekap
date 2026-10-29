<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkNotice
{
    public static function send(int $userId, string $title, string $body, string $url, string $key): void
    {
        DB::transaction(function () use ($userId, $title, $body, $url, $key) {
            if (! DB::table('work_notice_keys')->insertOrIgnore(['user_id' => $userId, 'event_key' => $key])) {
                return;
            }
            User::findOrFail($userId)->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'workflow',
                'data' => ['title' => $title, 'body' => $body, 'url' => $url],
            ]);
        });
    }

    public static function audit(int $actorId, string $action, string $type, int $subjectId, array $details = []): void
    {
        DB::table('audit_logs')->insert(['user_id' => $actorId, 'action' => $action, 'subject_type' => $type, 'subject_id' => $subjectId, 'details' => json_encode($details), 'created_at' => now(), 'updated_at' => now()]);
    }
}
