<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MyProfileController extends Controller
{
    public function show(Request $request): View
    {
        $member = $request->user();
        $assignmentQuery = DB::table('task_assignments')->where('task_assignments.user_id', $member->id);
        $statusCounts = (clone $assignmentQuery)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $summary = [
            'received' => (int) $statusCounts->sum(),
            'completed' => (int) $statusCounts->get('Disetujui', 0),
            'waiting' => (int) $statusCounts->get('Menunggu verifikasi', 0),
            'in_progress' => (int) $statusCounts->get('Dalam proses', 0),
            'not_started' => (int) $statusCounts->get('Belum dikerjakan', 0),
            'revision' => (int) $statusCounts->get('Perlu perbaikan', 0),
            'rejected' => (int) $statusCounts->get('Ditolak', 0),
        ];
        $progress = $summary['received'] > 0 ? (int) round($summary['completed'] / $summary['received'] * 100) : 0;
        $assignments = (clone $assignmentQuery)->join('work_tasks', 'work_tasks.id', '=', 'task_assignments.task_id')
            ->select('task_assignments.*', 'work_tasks.title', 'work_tasks.platform', 'work_tasks.due_at')
            ->orderByDesc('task_assignments.id')->paginate(10, ['*'], 'tasks_page')->withQueryString();
        $latestSubmissionIds = DB::table('task_submissions')->whereIn('assignment_id', $assignments->pluck('id'))
            ->selectRaw('MAX(id) as id')->groupBy('assignment_id');
        $submissions = DB::table('task_submissions')->whereIn('id', $latestSubmissionIds)->get()->keyBy('assignment_id');
        foreach ($submissions as $submission) {
            $submission->activity_labels = collect(json_decode($submission->reported_activities, true) ?? [])
                ->map(fn ($activity) => is_array($activity) ? ($activity['activity'] ?? null) : $activity)
                ->filter(fn ($activity) => is_string($activity) && $activity !== '')
                ->unique()->implode(', ');
        }
        $evidence = DB::table('task_evidence')->whereIn('submission_id', $submissions->pluck('id'))
            ->orderBy('id')->get()->groupBy('submission_id');
        $accountQuery = DB::table('social_accounts')->where('user_id', $member->id);
        $accountCounts = (clone $accountQuery)->selectRaw('platform, COUNT(*) as total')->groupBy('platform')->pluck('total', 'platform');
        $accounts = (clone $accountQuery)->orderBy('platform')->orderByDesc('id')->paginate(20, ['*'], 'accounts_page')->withQueryString();
        $quotas = DB::table('account_quotas')->where('user_id', $member->id)->pluck('required_count', 'platform');

        return view('work.profile', compact('member', 'summary', 'progress', 'assignments', 'submissions', 'evidence', 'accountCounts', 'accounts', 'quotas'));
    }
}
