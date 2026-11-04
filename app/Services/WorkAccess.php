<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class WorkAccess
{
    public static function readOnly(User $user): bool
    {
        return in_array(mb_strtolower(trim($user->role)), ['direktur utama', 'direktur', 'komisaris'], true);
    }

    public static function manages(User $user, ?string $branch = null): bool
    {
        return $user->role === 'Superadmin' || ($user->role === 'Kacab' && $user->branch !== null && ($branch === null || $user->branch === $branch));
    }

    public static function assignsTasks(User $user, ?string $branch = null): bool
    {
        return $user->role === 'Supervisor' || ($user->role === 'Kacab' && $user->branch !== null && ($branch === null || $user->branch === $branch));
    }

    public static function globalView(User $user): bool
    {
        return in_array($user->role, ['Superadmin', 'Supervisor'], true);
    }

    public static function reviews(User $user, object $assignment): bool
    {
        return self::assignsTasks($user) && $user->id !== $assignment->user_id
            && ($user->role === 'Supervisor' || $user->id === $assignment->reviewer_id);
    }

    public static function canView(User $user, object $task): bool
    {
        return self::globalView($user) || self::manages($user, $task->branch) || DB::table('task_assignments')->where('task_id', $task->id)->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)->orWhere('reviewer_id', $user->id);
        })->exists();
    }
}
