<?php

namespace App\Http\Controllers;

use App\Services\WorkAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless(WorkAccess::assignsTasks($actor), 403);
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(['Menunggu verifikasi', 'Disetujui', 'Perlu perbaikan', 'Ditolak', 'semua'])],
        ]);
        $selectedStatus = $filters['status'] ?? 'Menunggu verifikasi';
        $latestIds = DB::table('task_submissions')->selectRaw('MAX(id)')->groupBy('assignment_id');
        $items = DB::table('task_submissions')->join('task_assignments', 'task_assignments.id', '=', 'task_submissions.assignment_id')
            ->join('work_tasks', 'work_tasks.id', '=', 'task_assignments.task_id')
            ->join('users', 'users.id', '=', 'task_assignments.user_id')
            ->whereIn('task_submissions.id', $latestIds)->where('task_assignments.user_id', '!=', $actor->id)
            ->when($actor->role !== 'Supervisor', fn ($query) => $query->where('task_assignments.reviewer_id', $actor->id))
            ->when($selectedStatus !== 'semua', fn ($query) => $query->where('task_assignments.status', $selectedStatus))
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($query) => $query
                ->where('work_tasks.title', 'like', '%'.$filters['q'].'%')->orWhere('users.name', 'like', '%'.$filters['q'].'%')))
            ->select('task_assignments.id as assignment_id', 'task_assignments.status', 'work_tasks.title', 'work_tasks.branch',
                'work_tasks.platform', 'users.name as recipient_name', 'task_submissions.revision', 'task_submissions.created_at as submitted_at')
            ->orderByDesc('task_submissions.id')->paginate(20)->withQueryString();

        return view('work.verifications', compact('items', 'selectedStatus'));
    }

    public function show(Request $request, int $assignment): View
    {
        $actor = $request->user();
        abort_unless(WorkAccess::assignsTasks($actor), 403);
        $row = DB::table('task_assignments')->join('users', 'users.id', '=', 'task_assignments.user_id')
            ->select('task_assignments.*', 'users.name')->where('task_assignments.id', $assignment)->first();
        abort_unless($row && WorkAccess::reviews($actor, $row), 404);
        $task = DB::table('work_tasks')->find($row->task_id);
        $task->activities = json_decode($task->activities, true);
        $submissions = DB::table('task_submissions')->where('assignment_id', $assignment)->orderByDesc('id')->get();
        abort_if($submissions->isEmpty(), 404);
        $evidence = DB::table('task_evidence')->whereIn('submission_id', $submissions->pluck('id'))->get()->groupBy('submission_id');

        return view('work.show', [
            'task' => $task, 'assignments' => collect([$row]), 'submissions' => $submissions,
            'evidence' => $evidence, 'accounts' => collect(), 'verificationMode' => true,
        ]);
    }
}
