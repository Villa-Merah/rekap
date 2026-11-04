<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StaffReportPdf;
use App\Services\WorkAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('work.reports', $this->data($request));
    }

    public function download(Request $request): Response
    {
        $data = $this->data($request);
        abort_unless($data['member'], 404);
        $evidenceImages = [];
        $evidenceBytes = 0;
        foreach ($data['evidence']->flatten(1) as $file) {
            if (Storage::disk('local')->exists($file->path)) {
                $evidenceBytes += Storage::disk('local')->size($file->path);
            }
        }
        if ($evidenceBytes > 40 * 1024 * 1024) {
            return response()->view('work.reports', [...$data, 'pdfError' => 'Ukuran bukti melebihi 40 MB. Pilih periode lebih pendek untuk mengunduh PDF lengkap.'], 422);
        }
        foreach ($data['evidence']->flatten(1) as $file) {
            $disk = Storage::disk('local');
            if (! $disk->exists($file->path)) {
                $evidenceImages[$file->id] = null;

                continue;
            }
            $contents = $disk->get($file->path);
            $image = @getimagesizefromstring($contents);
            $mime = $image['mime'] ?? null;
            $evidenceImages[$file->id] = in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
                ? 'data:'.$mime.';base64,'.base64_encode($contents)
                : null;
        }

        try {
            $pdf = StaffReportPdf::render(view('work.report-pdf', [...$data, 'evidenceImages' => $evidenceImages])->render());
        } catch (\RuntimeException $exception) {
            report($exception);

            return response()->view('work.reports', [...$data, 'pdfError' => 'PDF belum dapat dibuat. Pastikan dependensi Composer dan ekstensi PHP GD sudah terpasang serta folder storage dapat ditulis, lalu coba kembali.'], 503);
        }

        $filename = 'laporan-aktivitas-'.$data['member']->id.'-'.$data['from'].'-'.$data['to'].'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function data(Request $request): array
    {
        $input = $request->validate([
            'user_id' => 'nullable|integer|min:1',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);
        $timezone = config('workflow.timezone');
        $today = CarbonImmutable::now($timezone);
        $from = $input['from'] ?? $today->startOfMonth()->toDateString();
        $to = $input['to'] ?? $today->toDateString();
        $start = CarbonImmutable::parse($from, $timezone)->startOfDay();
        $end = CarbonImmutable::parse($to, $timezone)->endOfDay();
        if ($end->lt($start) || $start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['to' => 'Periode harus berurutan dan maksimal 366 hari.']);
        }

        $actor = $request->user();
        $members = User::query()->where('role', '!=', 'Superadmin')->when(! WorkAccess::globalView($actor), fn ($query) => WorkAccess::manages($actor)
            ? $query->where('branch', $actor->branch)
            : $query->where('id', $actor->id))->orderBy('name')->get();
        $member = isset($input['user_id'])
            ? $members->firstWhere('id', (int) $input['user_id'])
            : ($actor->role === 'Superadmin' ? $members->first() : $members->firstWhere('id', $actor->id));
        if (! $member) {
            abort_if(isset($input['user_id']) || $members->isNotEmpty(), 404);

            return compact('members', 'member', 'from', 'to');
        }

        $assignments = DB::table('task_assignments')
            ->join('work_tasks', 'work_tasks.id', '=', 'task_assignments.task_id')
            ->where('task_assignments.user_id', $member->id)
            ->whereBetween('task_assignments.created_at', [$start->utc(), $end->utc()])
            ->select('task_assignments.*', 'work_tasks.title', 'work_tasks.platform', 'work_tasks.target_url', 'work_tasks.due_at')
            ->orderBy('task_assignments.created_at')->limit(501)->get();
        if ($assignments->count() > 500) {
            throw ValidationException::withMessages(['from' => 'Periode memuat lebih dari 500 tugas. Pilih periode lebih pendek.']);
        }
        $submissions = DB::table('task_submissions')->whereIn('assignment_id', $assignments->pluck('id'))
            ->orderByDesc('revision')->get()->unique('assignment_id')->keyBy('assignment_id');
        $evidence = DB::table('task_evidence')->whereIn('submission_id', $submissions->pluck('id'))
            ->orderBy('id')->get()->groupBy('submission_id');
        $accounts = DB::table('social_accounts')->where('user_id', $member->id)
            ->whereBetween('created_at', [$start->utc(), $end->utc()])->orderBy('platform')->orderBy('id')->get();
        $accountCounts = $accounts->countBy('platform');
        $summary = [
            'received' => $assignments->count(),
            'completed' => $assignments->where('status', 'Disetujui')->count(),
            'waiting' => $assignments->where('status', 'Menunggu verifikasi')->count(),
            'unfinished' => $assignments->whereNotIn('status', ['Disetujui', 'Ditolak'])->count(),
            'rejected' => $assignments->where('status', 'Ditolak')->count(),
            'accounts' => $accounts->count(),
        ];
        $generatedAt = CarbonImmutable::now($timezone);

        return compact('members', 'member', 'from', 'to', 'assignments', 'submissions', 'evidence', 'accounts', 'accountCounts', 'summary', 'generatedAt');
    }
}
