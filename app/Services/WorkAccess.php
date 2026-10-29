<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class WorkAccess
{
    public static function manages(User $user, ?string $branch = null): bool
    {
        return $user->role === 'Superadmin' || ($user->role === 'Kacab' && $user->branch !== null && ($branch === null || $user->branch === $branch));
    }

    public static function canView(User $user, object $task): bool
    {
        return self::manages($user, $task->branch) || DB::table('task_assignments')->where('task_id', $task->id)->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)->orWhere('reviewer_id', $user->id);
        })->exists();
    }
}
