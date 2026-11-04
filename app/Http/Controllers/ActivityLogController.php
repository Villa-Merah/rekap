<?php

namespace App\Http\Controllers;

use App\Services\WorkAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'action' => 'nullable|string|max:255',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);
        $actor = $request->user();
        $scope = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->when($actor->role !== 'Superadmin', function ($query) use ($actor): void {
                if (WorkAccess::manages($actor)) {
                    $query->where('users.branch', $actor->branch)->where('users.role', '!=', 'Superadmin');
                } else {
                    $query->where('audit_logs.user_id', $actor->id);
                }
            });
        $actions = (clone $scope)->distinct()->orderBy('audit_logs.action')->pluck('audit_logs.action');
        $timezone = config('workflow.timezone');
        $logs = $scope->when($filters['q'] ?? null, fn ($query, $keyword) => $query->where(fn ($query) => $query
            ->where('users.name', 'like', '%'.$keyword.'%')->orWhere('audit_logs.action', 'like', '%'.$keyword.'%')))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('audit_logs.action', $action))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('audit_logs.created_at', '>=', CarbonImmutable::parse($from, $timezone)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('audit_logs.created_at', '<=', CarbonImmutable::parse($to, $timezone)->endOfDay()->utc()))
            ->select('audit_logs.id', 'audit_logs.action', 'audit_logs.subject_type', 'audit_logs.subject_id', 'audit_logs.created_at', 'audit_logs.details', 'users.name as actor_name', 'users.branch as actor_branch')
            ->orderByDesc('audit_logs.id')->paginate(25)->withQueryString();
        $labels = ['role' => 'Jabatan', 'branch' => 'Cabang', 'revision' => 'Revisi', 'required_count' => 'Target akun', 'platform' => 'Platform', 'status' => 'Status'];
        $logs->getCollection()->transform(function ($log) use ($labels) {
            $details = json_decode($log->details, true) ?? [];
            $log->summary = [];
            foreach ($labels as $key => $label) {
                if (isset($details[$key]) && is_scalar($details[$key])) {
                    $log->summary[$label] = (string) $details[$key];
                }
            }
            unset($log->details);

            return $log;
        });

        return view('work.activity-logs', compact('logs', 'actions'));
    }
}
